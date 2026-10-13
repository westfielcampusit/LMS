<?php
require_once(__DIR__ . '/../../config.php');
require_login();
if (!\local_westfieldpayments\reminders::blocked((int)$USER->id)) {
    redirect(new moodle_url('/my/'));
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/westfieldpayments/blocked.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('accessblockedtitle', 'local_westfieldpayments'));
$PAGE->set_heading(get_string('accessblockedtitle', 'local_westfieldpayments'));
echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('accessblocked', 'local_westfieldpayments'), 'warning');
echo $OUTPUT->single_button(new moodle_url('/theme/westfield/payments.php'),
    get_string('paymentdetails', 'local_westfieldpayments'), 'get');
echo $OUTPUT->single_button(new moodle_url('/login/logout.php', ['sesskey' => sesskey()]), get_string('logout'), 'post');
echo $OUTPUT->footer();
