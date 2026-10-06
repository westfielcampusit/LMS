<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();
\local_westfieldpayments\ledger::require_manager();

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$url = new moodle_url('/local/westfieldpayments/index.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managepayments', 'local_westfieldpayments'));
$PAGE->set_heading(get_string('managepayments', 'local_westfieldpayments'));
$PAGE->navbar->add(get_string('managepayments', 'local_westfieldpayments'));

$courseoptions = [];
foreach ($DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname ASC', 'id, fullname') as $course) {
    $courseoptions[$course->id] = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
}
$students = [];
$studentoptions = [];
if ($courseid) {
    $course = get_course($courseid);
    $students = get_enrolled_users(context_course::instance($courseid), '', 0, 'u.*', 'u.firstname, u.lastname');
    foreach ($students as $student) {
        $studentoptions[$student->id] = fullname($student) . ' (' . $student->email . ')';
    }
}

$accountform = $paymentform = null;
if ($userid && $courseid) {
    \local_westfieldpayments\ledger::require_enrolment($userid, $courseid);
    $account = \local_westfieldpayments\ledger::account($userid, $courseid);
    $accountform = new \local_westfieldpayments\form\account($url, ['account' => $account]);
    if ($account) {
        $accountform->set_data((object)[
            'fee' => \local_westfieldpayments\ledger::decimal((int)$account->fee),
            'discount' => \local_westfieldpayments\ledger::decimal((int)$account->discount),
            'currency' => $account->currency, 'plan' => $account->plan,
        ]);
        $paymentform = new \local_westfieldpayments\form\payment($url, ['currency' => $account->currency]);
    }
    if ($data = $accountform->get_data()) {
        require_sesskey();
        \local_westfieldpayments\ledger::save_account($userid, $courseid, $data);
        redirect($url, get_string('plansaved', 'local_westfieldpayments'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($paymentform && ($data = $paymentform->get_data())) {
        require_sesskey();
        \local_westfieldpayments\ledger::add_payment($userid, $courseid, $data);
        redirect($url, get_string('paymentsaved', 'local_westfieldpayments'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('westfield-payment-admin');
echo html_writer::tag('p', get_string('intro', 'local_westfieldpayments'), ['class' => 'text-muted']);
echo html_writer::start_div('westfield-payment-picker');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/westfieldpayments/index.php')]);
echo html_writer::label(get_string('course'), 'payment-course');
echo html_writer::select($courseoptions, 'courseid', $courseid, ['0' => get_string('choosedots')], ['id' => 'payment-course']);
echo html_writer::tag('button', get_string('choosecourse', 'local_westfieldpayments'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
if ($courseid && $studentoptions) {
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/local/westfieldpayments/index.php')]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::label(get_string('student', 'local_westfieldpayments'), 'payment-student');
    echo html_writer::select($studentoptions, 'userid', $userid, ['0' => get_string('choosedots')], ['id' => 'payment-student']);
    echo html_writer::tag('button', get_string('choosestudent', 'local_westfieldpayments'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
} else if ($courseid) {
    echo html_writer::tag('p', get_string('nostudents', 'local_westfieldpayments'));
}
echo html_writer::end_div();

if ($accountform) {
    echo $OUTPUT->heading(fullname($students[$userid]), 3);
    echo html_writer::tag('p', get_string('manualnote', 'local_westfieldpayments'), ['class' => 'alert alert-info']);
    echo html_writer::start_div('westfield-payment-admin-grid');
    echo html_writer::start_div('westfield-payment-admin-card');
    echo $OUTPUT->heading(get_string('accountheading', 'local_westfieldpayments'), 4);
    $accountform->display();
    echo html_writer::end_div();
    echo html_writer::start_div('westfield-payment-admin-card');
    echo $OUTPUT->heading(get_string('addpayment', 'local_westfieldpayments'), 4);
    if ($paymentform) {
        $paymentform->display();
    } else {
        echo html_writer::tag('p', get_string('saveplanfirst', 'local_westfieldpayments'));
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->heading(get_string('preview', 'local_westfieldpayments'), 3);
    $preview = \theme_westfield\output\payments::for_user($userid);
    $preview['courses'] = array_values(array_filter($preview['courses'], static fn($item) => $item['courseid'] === $courseid));
    $preview['coursecount'] = count($preview['courses']);
    if ($preview['courses']) {
        $preview['courses'][0]['open'] = true;
    }
    echo $OUTPUT->render_from_template('theme_westfield/payments', $preview);
} else {
    echo $OUTPUT->notification(get_string('selectprompt', 'local_westfieldpayments'), 'info');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
