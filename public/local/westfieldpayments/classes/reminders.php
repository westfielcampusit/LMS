<?php
namespace local_westfieldpayments;
defined('MOODLE_INTERNAL') || die();

/** Two notices for a continuing overdue balance, followed by a payment access hold. */
class reminders {
    public static function enabled(): bool {
        return get_config('local_westfieldpayments', 'remindersenabled') !== '0';
    }

    public static function days(string $setting): int {
        $value = get_config('local_westfieldpayments', $setting);
        return max(1, min(365, $value === false ? 3 : (int)$value));
    }

    public static function eligible(int $userid, int $courseid): bool {
        $context = \context_course::instance($courseid);
        return !is_siteadmin($userid)
            && !has_capability('moodle/site:config', \context_system::instance(), $userid)
            && !has_capability('moodle/course:update', $context, $userid)
            && !has_capability('moodle/course:manageactivities', $context, $userid)
            && is_enrolled($context, $userid, 'moodle/course:isincompletionreports', true);
    }

    /** Allocate generic payments in installment order; targeted payments stay with their installment. */
    public static function calculate_due(\stdClass $plan, array $installments, array $manual,
            array $online, int $now): int {
        $allocated = [];
        $unallocated = 0;
        $totalpaid = 0;
        foreach ($manual as $payment) {
            if ($payment->currency !== $plan->currency || $payment->paymenttype === 'registration') {
                continue;
            }
            $totalpaid += (int)$payment->amount;
            if ($payment->paymenttype === 'installment') {
                $number = (int)$payment->installmentnumber;
                $allocated[$number] = ($allocated[$number] ?? 0) + (int)$payment->amount;
            } else {
                $unallocated += (int)$payment->amount;
            }
        }
        foreach ($online as $payment) {
            if ($payment->currency === $plan->currency) {
                $amount = (int)round((float)$payment->amount * 100);
                $unallocated += $amount;
                $totalpaid += $amount;
            }
        }
        usort($installments, static fn($a, $b) => $a->installmentnumber <=> $b->installmentnumber);
        $due = 0;
        foreach ($installments as $installment) {
            $remaining = max(0, (int)$installment->amount - ($allocated[$installment->installmentnumber] ?? 0));
            $applied = min($remaining, $unallocated);
            $remaining -= $applied;
            $unallocated -= $applied;
            if ($installment->paymentdue && $installment->duedate <= $now) {
                $due += $remaining;
            }
        }
        return min($due, max(0, (int)$plan->fee - (int)$plan->discount - $totalpaid));
    }

    public static function due(int $userid, int $courseid, ?int $now = null): int {
        global $DB;
        $plan = ledger::course_plan($courseid);
        if (!$plan) {
            return 0;
        }
        $installments = $DB->get_records('local_wfp_courseinst', ['planid' => $plan->id]);
        $manual = $DB->get_records_sql("SELECT p.* FROM {local_wfp_payments} p
            JOIN {local_wfp_accounts} a ON a.id = p.accountid
            WHERE a.userid = :userid AND a.courseid = :courseid", compact('userid', 'courseid'));
        $online = $DB->get_records_sql("SELECT p.id, p.amount, p.currency FROM {payments} p
            JOIN {enrol} e ON e.id = p.itemid AND e.enrol = 'fee'
            WHERE p.userid = :userid AND e.courseid = :courseid
                AND p.component = 'enrol_fee' AND p.paymentarea = 'fee'", compact('userid', 'courseid'));
        return self::calculate_due($plan, array_values($installments), $manual, $online, $now ?? time());
    }

    /** Called under the course lock after payment or plan changes. No messages are sent here. */
    public static function reconcile_course(int $courseid): void {
        global $DB;
        foreach ($DB->get_records('local_wfp_reminders', ['courseid' => $courseid]) as $state) {
            if (!self::eligible((int)$state->userid, $courseid) || !self::due((int)$state->userid, $courseid)) {
                $DB->delete_records('local_wfp_reminders', ['id' => $state->id]);
            }
        }
    }

    /** Latest payment data is checked on every protected request, including existing sessions. */
    public static function blocked(int $userid): bool {
        global $DB;
        if (!self::enabled() || is_siteadmin($userid)) {
            return false;
        }
        foreach ($DB->get_records('local_wfp_reminders', ['userid' => $userid]) as $state) {
            if (!self::due($userid, (int)$state->courseid)) {
                // Recheck under the course lock before clearing a resolved reminder cycle.
                $lock = \core\lock\lock_config::get_lock_factory('local_westfieldpayments')
                    ->get_lock('course:' . $state->courseid, 10);
                if ($lock) {
                    try {
                        if (!self::due($userid, (int)$state->courseid)) {
                            $DB->delete_records('local_wfp_reminders', ['id' => $state->id]);
                        }
                    } finally {
                        $lock->release();
                    }
                }
                continue;
            }
            if ($state->remindercount == 2 && $state->blockat && $state->blockat <= time()
                    && self::eligible($userid, (int)$state->courseid)
                    && self::due($userid, (int)$state->courseid) > 0) {
                return true;
            }
        }
        return false;
    }

    /** Return the next action. Never catch up both reminders in a single execution. */
    public static function next_action(?\stdClass $state, int $now, int $interval): int {
        if (!$state || !$state->remindercount) {
            return 1;
        }
        return $state->remindercount == 1 && $now >= $state->lastsent + $interval ? 2 : 0;
    }

    /** One student; caller holds the course lock to serialize against payment entry. */
    public static function process(\stdClass $user, \stdClass $plan, int $now): void {
        global $DB;
        $conditions = ['userid' => $user->id, 'courseid' => $plan->courseid];
        $due = self::due((int)$user->id, (int)$plan->courseid, $now);
        if (!$due || !self::eligible((int)$user->id, (int)$plan->courseid)) {
            $DB->delete_records('local_wfp_reminders', $conditions);
            return;
        }
        $state = $DB->get_record('local_wfp_reminders', $conditions) ?: null;
        $number = self::next_action($state, $now, self::days('reminderinterval') * DAYSECS);
        if (!$number) {
            return;
        }
        // The in-app notification and count commit together. Moodle dispatches email after commit.
        $transaction = $DB->start_delegated_transaction();
        // Persist a zero-count state first. A failed send remains retryable and cannot cause a hold.
        if (!$state) {
            $state = (object)($conditions + ['remindercount' => 0, 'lastsent' => 0, 'blockat' => 0]);
            $state->id = $DB->insert_record('local_wfp_reminders', $state);
        }
        $blockat = $number === 2 ? $now + self::days('blockgrace') * DAYSECS : 0;
        $course = get_course($plan->courseid);
        $url = new \moodle_url('/theme/westfield/payments.php');
        $a = (object)[
            'number' => $number, 'course' => format_string($course->fullname),
            'amount' => ledger::decimal($due) . ' ' . $plan->currency,
            'url' => $url->out(false),
            'deadline' => $blockat ? userdate($blockat, '', $user->timezone) : '',
            'days' => self::days('reminderinterval'),
        ];
        $message = new \core\message\message();
        $message->component = 'local_westfieldpayments';
        $message->name = 'paymentreminder';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->notification = 1;
        $message->subject = get_string('remindersubject', 'local_westfieldpayments', $a);
        $message->fullmessage = get_string('reminderbody' . $number, 'local_westfieldpayments', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->fullmessage;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('paymentdetails', 'local_westfieldpayments');
        if (message_send($message)) {
            $state->remindercount = $number;
            $state->lastsent = $now;
            $state->blockat = $blockat;
            $DB->update_record('local_wfp_reminders', $state);
        }
        $transaction->allow_commit();
    }

    public static function enforce(bool $preventredirect = false): void {
        global $USER, $SCRIPT;
        if (CLI_SCRIPT || !isloggedin() || isguestuser() || during_initial_install()
                || (int)get_config('local_westfieldpayments', 'version') < 2026101300) {
            return;
        }
        if (in_array($SCRIPT, ['/local/westfieldpayments/blocked.php', '/theme/westfield/payments.php',
                '/login/logout.php'], true)) {
            return;
        }
        if (self::blocked((int)$USER->id)) {
            if ($preventredirect || AJAX_SCRIPT || WS_SERVER) {
                throw new \moodle_exception('accessblocked', 'local_westfieldpayments');
            }
            redirect(new \moodle_url('/local/westfieldpayments/blocked.php'));
        }
    }
}
