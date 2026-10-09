<?php
namespace local_westfieldpayments;
defined('MOODLE_INTERNAL') || die();

/** Student course accounts. Amounts are stored and calculated as integer cents. */
class ledger {
    public const MAX_INSTALLMENTS = 24;
    public const CURRENCIES = ['LKR', 'USD', 'GBP', 'EUR', 'AUD', 'CAD', 'INR', 'AED', 'SGD'];
    public const METHODS = ['cash', 'bank', 'card', 'online', 'other'];

    public static function cents(string $value): int {
        $value = trim($value);
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $value)) {
            throw new \invalid_parameter_exception(get_string('invalidamount', 'local_westfieldpayments'));
        }
        $parts = explode('.', $value);
        return ((int)$parts[0] * 100) + (int)str_pad($parts[1] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public static function require_manager(): void {
        require_capability('moodle/site:config', \context_system::instance());
    }

    public static function require_enrolment(int $userid, int $courseid): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        if ($courseid == SITEID || isguestuser($user) ||
                !is_enrolled(\context_course::instance($courseid), $user, '', false)) {
            throw new \moodle_exception('notenrolled', 'local_westfieldpayments');
        }
    }

    public static function account(int $userid, int $courseid) {
        global $DB;
        return $DB->get_record('local_wfp_accounts', ['userid' => $userid, 'courseid' => $courseid]);
    }

    public static function course_plan(int $courseid) {
        global $DB;
        return $DB->get_record('local_wfp_courseplans', ['courseid' => $courseid]);
    }

    public static function save_course_plan(int $courseid, \stdClass $data): int {
        global $DB, $USER;
        self::require_manager();
        if ($courseid <= SITEID) {
            throw new \invalid_parameter_exception(get_string('invalidplan', 'local_westfieldpayments'));
        }
        $fee = self::cents((string)$data->fee);
        $discount = self::cents((string)$data->discount);
        if ($discount > $fee || !in_array($data->currency, self::CURRENCIES, true)) {
            throw new \invalid_parameter_exception(get_string('invalidplan', 'local_westfieldpayments'));
        }
        $count = (int)($data->installmentcount ?? 0);
        if ($count < 0 || $count > self::MAX_INSTALLMENTS) {
            throw new \invalid_parameter_exception(get_string('invalidinstallmentcount', 'local_westfieldpayments'));
        }
        $installmenttotal = 0;
        for ($index = 1; $index <= $count; $index++) {
            $amount = self::cents((string)($data->{'installmentamount_' . $index} ?? ''));
            if ($amount <= 0 || (int)($data->{'installmentdate_' . $index} ?? 0) <= 0) {
                throw new \invalid_parameter_exception(get_string('invalidinstallment', 'local_westfieldpayments'));
            }
            $installmenttotal += $amount;
        }
        if ($count > 0 && $installmenttotal !== $fee - $discount) {
            throw new \invalid_parameter_exception(get_string('installmentstotalmismatch', 'local_westfieldpayments'));
        }

        $lock = self::lock_course($courseid);
        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();
            $plan = self::course_plan($courseid);
            $accounts = $DB->get_records('local_wfp_accounts', ['courseid' => $courseid]);
            $record = (object)[
                'courseid' => $courseid,
                'currency' => $data->currency,
                'fee' => $fee,
                'discount' => $discount,
                'plan' => trim($data->plan ?? ''),
                'modifiedby' => $USER->id,
                'timemodified' => time(),
            ];
            if ($plan) {
                $record->id = $plan->id;
                $DB->update_record('local_wfp_courseplans', $record);
                $planid = (int)$plan->id;
            } else {
                $planid = (int)$DB->insert_record('local_wfp_courseplans', $record);
            }
            foreach ($accounts as $account) {
                $account->fee = $fee;
                $account->discount = $discount;
                $account->currency = $data->currency;
                $account->plan = $record->plan;
                $account->modifiedby = $USER->id;
                $account->timemodified = time();
                $DB->update_record('local_wfp_accounts', $account);
            }
            $DB->delete_records('local_wfp_courseinst', ['planid' => $planid]);
            for ($index = 1; $index <= $count; $index++) {
                $DB->insert_record('local_wfp_courseinst', (object)[
                    'planid' => $planid,
                    'installmentnumber' => $index,
                    'amount' => self::cents((string)$data->{'installmentamount_' . $index}),
                    'duedate' => (int)$data->{'installmentdate_' . $index},
                    'paymentdue' => empty($data->{'installmentdue_' . $index}) ? 0 : 1,
                ]);
            }
            $transaction->allow_commit();
            return $planid;
        } catch (\Throwable $e) {
            if ($transaction) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public static function add_payment(int $userid, int $courseid, \stdClass $data): int {
        global $DB, $USER;
        self::require_manager();
        self::require_enrolment($userid, $courseid);
        $amount = self::cents((string)$data->amount);
        if ($amount <= 0 || !in_array($data->method, self::METHODS, true) ||
                $data->paidat > time() || $data->paidat <= 0 ||
                !preg_match('/^[a-f0-9]{32}$/D', $data->requestid) ||
                \core_text::strlen($data->reference ?? '') > 100) {
            throw new \invalid_parameter_exception(get_string('invalidpayment', 'local_westfieldpayments'));
        }
        $lock = self::lock_course($courseid);
        try {
            $account = self::account($userid, $courseid);
            if (!$account) {
                $plan = self::course_plan($courseid);
                if (!$plan) {
                    throw new \moodle_exception('saveplanfirst', 'local_westfieldpayments');
                }
                $account = (object)[
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'currency' => $plan->currency,
                    'fee' => $plan->fee,
                    'discount' => $plan->discount,
                    'plan' => $plan->plan,
                    'createdby' => $USER->id,
                    'modifiedby' => $USER->id,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                $account->id = $DB->insert_record('local_wfp_accounts', $account);
            }
            $existing = $DB->get_record('local_wfp_payments', ['requestid' => $data->requestid]);
            if ($existing) {
                if ((int)$existing->accountid !== (int)$account->id) {
                    throw new \invalid_parameter_exception('Invalid payment request.');
                }
                return (int)$existing->id;
            }
            return (int)$DB->insert_record('local_wfp_payments', (object)[
                'accountid' => $account->id, 'amount' => $amount, 'paidat' => $data->paidat,
                'currency' => $account->currency,
                'method' => $data->method, 'reference' => trim($data->reference ?? ''),
                'note' => trim($data->note ?? ''), 'requestid' => $data->requestid,
                'createdby' => $USER->id, 'timecreated' => time(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private static function lock_course(int $courseid) {
        $lock = \core\lock\lock_config::get_lock_factory('local_westfieldpayments')
            ->get_lock('course:' . $courseid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        return $lock;
    }
}
