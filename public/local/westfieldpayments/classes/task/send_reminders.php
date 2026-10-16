<?php
namespace local_westfieldpayments\task;
defined('MOODLE_INTERNAL') || die();

class send_reminders extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('sendreminders', 'local_westfieldpayments');
    }

    public function execute() {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        if (!\local_westfieldpayments\reminders::enabled()) {
            return;
        }
        foreach ($DB->get_records('local_wfp_courseplans') as $plan) {
            $lock = \core\lock\lock_config::get_lock_factory('local_westfieldpayments')
                ->get_lock('course:' . $plan->courseid, 10);
            if (!$lock) {
                throw new \moodle_exception('locktimeout');
            }
            try {
                // Re-read after acquiring the same lock used by plan and payment edits.
                $plan = \local_westfieldpayments\ledger::course_plan((int)$plan->courseid);
                \local_westfieldpayments\reminders::reconcile_course((int)$plan->courseid);
                $students = get_enrolled_users(\context_course::instance($plan->courseid),
                    'moodle/course:isincompletionreports', 0, 'u.*', null, 0, 0, true);
                foreach ($students as $student) {
                    if (!$student->deleted && !$student->suspended) {
                        \local_westfieldpayments\reminders::process($student, $plan, time());
                    }
                }
            } finally {
                $lock->release();
            }
        }
    }
}
