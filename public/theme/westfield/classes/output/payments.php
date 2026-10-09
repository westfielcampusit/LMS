<?php
namespace theme_westfield\output;

defined('MOODLE_INTERNAL') || die();

/** Read-only overview of the student's enrolled courses and Moodle fee payments. */
class payments {
    public static function for_user(int $userid): array {
        global $DB;
        $courses = enrol_get_users_courses($userid, false, 'fullname', 'fullname ASC');
        $result = ['courses' => [], 'coursecount' => count($courses)];
        if (!$courses) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'course');
        $params['userid'] = $userid;
        // Use only fee instances actually associated with this student's enrolments.
        $fees = $DB->get_records_sql("SELECT e.id, e.courseid, e.cost, e.currency
            FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :userid AND e.courseid $insql AND e.enrol = 'fee'", $params);
        $records = $DB->get_records_sql("SELECT p.id, e.courseid, p.amount, p.currency,
                p.gateway, p.timecreated
            FROM {payments} p JOIN {enrol} e ON e.id = p.itemid AND e.enrol = 'fee'
            WHERE p.userid = :userid AND e.courseid $insql
                AND p.component = 'enrol_fee' AND p.paymentarea = 'fee'
            ORDER BY p.timecreated DESC, p.id DESC", $params);
        $accounts = $manual = $installments = $courseplans = $courseinstallments = $courseplansbycourse = [];
        if (get_config('local_westfieldpayments', 'version')) {
            $accounts = $DB->get_records_sql("SELECT * FROM {local_wfp_accounts}
                WHERE userid = :userid AND courseid $insql", $params);
            if ($accounts) {
                [$accountinsql, $accountparams] = $DB->get_in_or_equal(
                    array_keys($accounts), SQL_PARAMS_NAMED, 'account');
                $installments = $DB->get_records_select(
                    'local_wfp_installments', 'accountid ' . $accountinsql, $accountparams,
                    'installmentnumber');
            }
            $courseparams = $params;
            unset($courseparams['userid']);
            $courseplans = $DB->get_records_select(
                'local_wfp_courseplans', 'courseid ' . $insql, $courseparams);
            $courseplansbycourse = [];
            foreach ($courseplans as $plan) {
                $courseplansbycourse[$plan->courseid] = $plan;
            }
            if ($courseplans) {
                [$planinsql, $planparams] = $DB->get_in_or_equal(
                    array_keys($courseplans), SQL_PARAMS_NAMED, 'plan');
                $courseinstallments = $DB->get_records_select(
                    'local_wfp_courseinst', 'planid ' . $planinsql, $planparams, 'installmentnumber');
            }
            $manual = $DB->get_records_sql("SELECT p.*, a.courseid
                FROM {local_wfp_payments} p JOIN {local_wfp_accounts} a ON a.id = p.accountid
                WHERE a.userid = :userid AND a.courseid $insql
                ORDER BY p.paidat DESC, p.id DESC", $params);
        }
        foreach ($courses as $course) {
            $coursefees = array_filter($fees, static fn($fee) => $fee->courseid == $course->id);
            $history = array_filter($records, static fn($record) => $record->courseid == $course->id);
            $courseaccounts = array_filter($accounts, static fn($account) => $account->courseid == $course->id);
            $coursemanual = array_filter($manual, static fn($payment) => $payment->courseid == $course->id);
            $account = reset($courseaccounts) ?: null;
            $courseplan = $courseplansbycourse[$course->id] ?? null;
            if ($courseplan) {
                $schedule = array_filter($courseinstallments, static fn($item) => $item->planid == $courseplan->id);
            } else {
                $schedule = $account
                    ? array_filter($installments, static fn($item) => $item->accountid == $account->id)
                    : [];
            }
            $result['courses'][] = self::course_data($course, $coursefees, $history, !$result['courses'],
                $account, $coursemanual, $schedule, $courseplan);
        }
        return $result;
    }

    /** Build display data without treating current enrolment prices as a historical invoice. */
    public static function course_data(\stdClass $course, array $fees, array $records, bool $open,
            ?\stdClass $account = null, array $manual = [], array $installments = [],
            ?\stdClass $courseplan = null): array {
        $paymentplan = $courseplan ?: $account;
        $data = [
            'courseid' => (int)$course->id,
            'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'open' => $open,
            'fees' => [],
            'history' => [],
            'totals' => [],
            'paymentcount' => count($records) + count($manual),
            'hasaccount' => (bool)$paymentplan,
        ];
        foreach ($installments as $installment) {
            $data['installments'][] = [
                'number' => (int)$installment->installmentnumber,
                'amount' => self::money((int)$installment->amount / 100, $paymentplan->currency),
                'duedate' => userdate((int)$installment->duedate, get_string('strftimedatefullshort')),
                'due' => !empty($installment->paymentdue),
            ];
        }
        foreach ($fees as $fee) {
            if ($fee->cost !== null && $fee->cost !== '' && $fee->currency !== '') {
                $data['fees'][] = ['amount' => self::money($fee->cost, $fee->currency)];
            }
        }
        $totals = [];
        foreach ($records as $record) {
            $totals[$record->currency] = ($totals[$record->currency] ?? 0) + (int)round((float)$record->amount * 100);
            $component = 'paygw_' . $record->gateway;
            $gateway = get_string_manager()->string_exists('pluginname', $component)
                ? get_string('pluginname', $component) : $record->gateway;
            $data['history'][] = [
                'date' => userdate($record->timecreated),
                'amount' => self::money($record->amount, $record->currency),
                'method' => $gateway,
                'reference' => '#' . $record->id,
                'timestamp' => (int)$record->timecreated,
            ];
        }
        foreach ($manual as $record) {
            $totals[$record->currency] = ($totals[$record->currency] ?? 0) + (int)$record->amount;
            $data['history'][] = [
                'date' => userdate($record->paidat),
                'amount' => self::money($record->amount / 100, $record->currency),
                'method' => get_string($record->method, 'local_westfieldpayments'),
                'reference' => $record->reference ?: 'WF-' . $record->id,
                'note' => $record->note,
                'timestamp' => (int)$record->paidat,
            ];
        }
        usort($data['history'], static fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        if ($paymentplan) {
            $data['fees'] = [['amount' => self::money($paymentplan->fee / 100, $paymentplan->currency)]];
            $data['discount'] = self::money($paymentplan->discount / 100, $paymentplan->currency);
            $totals[$paymentplan->currency] = $totals[$paymentplan->currency] ?? 0;
            $balance = (int)$paymentplan->fee - (int)$paymentplan->discount - $totals[$paymentplan->currency];
            $data['balance'] = self::money(max(0, $balance) / 100, $paymentplan->currency);
            if ($balance < 0) {
                $data['credit'] = self::money(-$balance / 100, $paymentplan->currency);
            }
            $data['plan'] = $paymentplan->plan;
        }
        foreach ($totals as $currency => $amount) {
            $data['totals'][] = ['amount' => self::money($amount / 100, $currency)];
        }
        return $data;
    }

    private static function money($amount, string $currency): string {
        return format_float((float)$amount, 2) . ' ' . $currency;
    }
}
