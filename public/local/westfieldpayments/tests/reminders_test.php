<?php
namespace local_westfieldpayments;
defined('MOODLE_INTERNAL') || die();

/** @covers \local_westfieldpayments\reminders */
final class reminders_test extends \advanced_testcase {
    public function test_payment_allocation_and_due_dates(): void {
        $plan = (object)['fee' => 20000, 'discount' => 0, 'currency' => 'LKR'];
        $schedule = [
            (object)['installmentnumber' => 1, 'amount' => 10000, 'paymentdue' => 1, 'duedate' => 100],
            (object)['installmentnumber' => 2, 'amount' => 10000, 'paymentdue' => 1, 'duedate' => 200],
        ];
        $payment = static fn($type, $amount, $number = 0, $currency = 'LKR') => (object)[
            'paymenttype' => $type, 'amount' => $amount, 'installmentnumber' => $number, 'currency' => $currency,
        ];
        $this->assertSame(0, reminders::calculate_due($plan, $schedule, [], [], 99));
        $this->assertSame(10000, reminders::calculate_due($plan, $schedule, [], [], 100));
        $this->assertSame(10000, reminders::calculate_due($plan, $schedule,
            [$payment('installment', 10000, 2)], [], 100));
        $this->assertSame(7000, reminders::calculate_due($plan, $schedule,
            [$payment('coursefee', 3000), $payment('registration', 10000), $payment('coursefee', 10000, 0, 'USD')],
            [], 100));
        $this->assertSame(7500, reminders::calculate_due($plan, $schedule, [],
            [(object)['amount' => '25.00', 'currency' => 'LKR']], 100));
        $this->assertSame(0, reminders::calculate_due($plan, $schedule,
            [$payment('coursefee', 20000)], [], 300));
        $schedule[0]->paymentdue = 0;
        $this->assertSame(0, reminders::calculate_due($plan, $schedule, [], [], 100));
    }

    public function test_missed_cron_runs_do_not_skip_reminders(): void {
        $this->assertSame(1, reminders::next_action(null, 1000000, 300));
        $state = (object)['remindercount' => 1, 'lastsent' => 1000];
        $this->assertSame(0, reminders::next_action($state, 1299, 300));
        $this->assertSame(2, reminders::next_action($state, 1300, 300));
        $this->assertSame(2, reminders::next_action($state, 1000000, 300));
        $state->remindercount = 2;
        $this->assertSame(0, reminders::next_action($state, 1000000, 300));
    }

    public function test_two_notifications_block_and_payment_restores_access(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setAdminUser();
        ledger::save_course_plan($course->id, (object)[
            'fee' => '100.00', 'discount' => '0.00', 'currency' => 'LKR', 'plan' => '',
            'installmentcount' => 1, 'installmentamount_1' => '100.00',
            'installmentdate_1' => time() - 10 * DAYSECS, 'installmentdue_1' => 1,
        ]);
        $plan = ledger::course_plan($course->id);
        $sink = $this->redirectMessages();
        $start = time() - 7 * DAYSECS;
        reminders::process($student, $plan, $start);
        reminders::process($teacher, $plan, $start);
        reminders::process($student, $plan, $start);
        $this->assertCount(1, $sink->get_messages());
        $this->assertFalse(reminders::blocked($student->id));
        reminders::process($student, $plan, $start + 3 * DAYSECS);
        $this->assertCount(2, $sink->get_messages());
        $this->assertTrue(reminders::blocked($student->id));
        $this->assertFalse(reminders::blocked($teacher->id));
        reminders::process($student, $plan, time());
        $this->assertCount(2, $sink->get_messages());
        set_config('remindersenabled', 0, 'local_westfieldpayments');
        $this->assertFalse(reminders::blocked($student->id));
        set_config('remindersenabled', 1, 'local_westfieldpayments');
        ledger::add_payment($student->id, $course->id, (object)[
            'amount' => '100.00', 'method' => 'bank', 'paidat' => time(),
            'paymenttype' => 'coursefee', 'requestid' => bin2hex(random_bytes(16)),
        ]);
        $this->assertFalse(reminders::blocked($student->id));
        $this->assertFalse($DB->record_exists('local_wfp_reminders', ['userid' => $student->id]));
        $sink->close();
    }
}
