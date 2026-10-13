<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();
\local_westfieldpayments\ledger::require_manager();
$userid = required_param('userid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
\local_westfieldpayments\ledger::require_enrolment($userid, $courseid);
$course = get_course($courseid);
$student = core_user::get_user($userid, '*', MUST_EXIST);
$plan = \local_westfieldpayments\ledger::course_plan($courseid);
if (!$plan) {
    throw new moodle_exception('savecourseplanfirst', 'local_westfieldpayments');
}
$returnurl = new moodle_url('/user/profile.php',
    ['id' => $userid, 'paymentdetails' => 1, 'paymentcourse' => $courseid], 'westfield-profile-payments');
$url = new moodle_url('/local/westfieldpayments/payment.php', ['userid' => $userid, 'courseid' => $courseid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('addpaymentbutton', 'local_westfieldpayments'));
$PAGE->set_heading(fullname($student));
$installments = $DB->get_records('local_wfp_courseinst', ['planid' => $plan->id], 'installmentnumber ASC');
$form = new \local_westfieldpayments\form\payment($url,
    ['currency' => $plan->currency, 'installments' => $installments]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    require_sesskey();
    \local_westfieldpayments\ledger::add_payment($userid, $courseid, $data);
    redirect($returnurl, get_string('paymentsaved', 'local_westfieldpayments'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo html_writer::start_div('westfield-payment-admin');
echo html_writer::start_div('westfield-payment-admin-card');
echo $OUTPUT->heading(get_string('addpaymentbutton', 'local_westfieldpayments'), 3);
echo html_writer::tag('p', format_string($course->fullname));
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
