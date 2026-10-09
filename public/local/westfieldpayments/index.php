<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();
\local_westfieldpayments\ledger::require_manager();

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$onlydue = optional_param('onlydue', 0, PARAM_BOOL);
$selectedusers = optional_param_array('selectedusers', [], PARAM_INT);
$openstudent = optional_param('openstudent', 0, PARAM_BOOL);
$search = trim(optional_param('search', '', PARAM_TEXT));
$courseurl = new moodle_url('/local/westfieldpayments/index.php', ['courseid' => $courseid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/westfieldpayments/index.php',
    ['courseid' => $courseid, 'userid' => $userid, 'onlydue' => $onlydue]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('managepayments', 'local_westfieldpayments'));
$PAGE->set_heading(get_string('managepayments', 'local_westfieldpayments'));
$PAGE->navbar->add(get_string('managepayments', 'local_westfieldpayments'));

$courseoptions = [];
foreach ($DB->get_records_select('course', 'id <> ?', [SITEID], 'fullname ASC', 'id, fullname') as $course) {
    $courseoptions[$course->id] = format_string($course->fullname, true, [
        'context' => context_course::instance($course->id),
    ]);
}

$courseplan = null;
$planform = $paymentform = null;
$students = [];
$balances = [];
$duebalances = [];
$markedDueAmount = 0;
$hasdueinstallment = false;
if ($courseid) {
    $course = get_course($courseid);
    $courseplan = \local_westfieldpayments\ledger::course_plan($courseid);
    $installments = $courseplan
        ? $DB->get_records('local_wfp_courseinst', ['planid' => $courseplan->id], 'installmentnumber ASC')
        : [];

    $planform = new \local_westfieldpayments\form\course_plan($courseurl);
    $formdata = [
        'fee' => $courseplan ? \local_westfieldpayments\ledger::decimal((int)$courseplan->fee) : '',
        'discount' => $courseplan ? \local_westfieldpayments\ledger::decimal((int)$courseplan->discount) : '0.00',
        'currency' => $courseplan ? $courseplan->currency : 'LKR',
        'plan' => $courseplan ? $courseplan->plan : '',
        'installmentcount' => count($installments),
    ];
    foreach ($installments as $installment) {
        $index = (int)$installment->installmentnumber;
        $formdata['installmentamount_' . $index] = \local_westfieldpayments\ledger::decimal((int)$installment->amount);
        $formdata['installmentdate_' . $index] = (int)$installment->duedate;
        $formdata['installmentdue_' . $index] = (int)$installment->paymentdue;
        $hasdueinstallment = $hasdueinstallment || !empty($installment->paymentdue);
        if (!empty($installment->paymentdue)) {
            $markedDueAmount += (int)$installment->amount;
        }
    }
    $planform->set_data((object)$formdata);
    $PAGE->requires->js(new moodle_url('/local/westfieldpayments/installments.js'));

    if ($data = $planform->get_data()) {
        require_sesskey();
        \local_westfieldpayments\ledger::save_course_plan($courseid, $data);
        redirect($courseurl, get_string('plansaved', 'local_westfieldpayments'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $students = get_enrolled_users(context_course::instance($courseid), '', 0, 'u.*', 'u.firstname, u.lastname');
    if ($openstudent) {
        $selectedusers = array_values(array_unique(array_filter($selectedusers)));
        if (count($selectedusers) !== 1 || !isset($students[$selectedusers[0]])) {
            throw new moodle_exception('selectonestudent', 'local_westfieldpayments');
        }
        $userid = (int)$selectedusers[0];
    }
    if ($userid && !isset($students[$userid])) {
        throw new moodle_exception('notenrolled', 'local_westfieldpayments');
    }

    if ($courseplan) {
        $paidamounts = [];
        $manualpayments = $DB->get_records_sql("SELECT a.userid, SUM(p.amount) AS amount
            FROM {local_wfp_accounts} a JOIN {local_wfp_payments} p ON p.accountid = a.id
            WHERE a.courseid = :courseid AND p.currency = :currency GROUP BY a.userid",
            ['courseid' => $courseid, 'currency' => $courseplan->currency]);
        foreach ($manualpayments as $payment) {
            $paidamounts[$payment->userid] = (int)$payment->amount;
        }
        $onlinepayments = $DB->get_records_sql("SELECT p.userid, SUM(p.amount) AS amount
            FROM {payments} p JOIN {enrol} e ON e.id = p.itemid AND e.enrol = 'fee'
            WHERE e.courseid = :courseid AND p.component = 'enrol_fee' AND p.paymentarea = 'fee'
                AND p.currency = :currency GROUP BY p.userid",
            ['courseid' => $courseid, 'currency' => $courseplan->currency]);
        foreach ($onlinepayments as $payment) {
            $paidamounts[$payment->userid] = ($paidamounts[$payment->userid] ?? 0) +
                (int)round((float)$payment->amount * 100);
        }
        $planbalance = (int)$courseplan->fee - (int)$courseplan->discount;
        foreach ($students as $student) {
            $paid = $paidamounts[$student->id] ?? 0;
            $balances[$student->id] = max(0, $planbalance - $paid);
            $duebalances[$student->id] = max(0, $markedDueAmount - $paid);
        }
    }

    if ($userid && $courseplan) {
        $paymenturl = new moodle_url('/local/westfieldpayments/index.php',
            ['courseid' => $courseid, 'userid' => $userid, 'onlydue' => $onlydue]);
        $paymentform = new \local_westfieldpayments\form\payment($paymenturl,
            ['currency' => $courseplan->currency]);
        if ($data = $paymentform->get_data()) {
            require_sesskey();
            \local_westfieldpayments\ledger::add_payment($userid, $courseid, $data);
            redirect($paymenturl, get_string('paymentsaved', 'local_westfieldpayments'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('westfield-payment-admin');
echo html_writer::tag('p', get_string('intro', 'local_westfieldpayments'), ['class' => 'text-muted']);
echo html_writer::start_div('westfield-payment-picker');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/westfieldpayments/index.php'),
]);
echo html_writer::label(get_string('course'), 'payment-course');
echo html_writer::select($courseoptions, 'courseid', $courseid, ['0' => get_string('choosedots')],
    ['id' => 'payment-course']);
echo html_writer::tag('button', get_string('choosecourse', 'local_westfieldpayments'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

if (!$courseid) {
    echo $OUTPUT->notification(get_string('selectprompt', 'local_westfieldpayments'), 'info');
} else {
    echo $OUTPUT->heading(format_string($course->fullname), 3);
    echo html_writer::start_div('westfield-payment-admin-card');
    echo $OUTPUT->heading(get_string('courseplanheading', 'local_westfieldpayments'), 4);
    $planform->display();
    echo html_writer::end_div();

    echo html_writer::start_div('westfield-payment-admin-card');
    echo $OUTPUT->heading(get_string('coursestudents', 'local_westfieldpayments'), 4);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/westfieldpayments/index.php'),
        'class' => 'd-flex mb-3',
        'role' => 'search',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'onlydue', 'value' => (int)$onlydue]);
    echo html_writer::empty_tag('input', [
        'type' => 'search',
        'name' => 'search',
        'value' => $search,
        'id' => 'payment-student-search',
        'class' => 'form-control mr-2',
        'placeholder' => get_string('searchstudents', 'local_westfieldpayments'),
        'aria-label' => get_string('searchstudents', 'local_westfieldpayments'),
    ]);
    echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
    if ($search !== '') {
        echo html_writer::link(new moodle_url('/local/westfieldpayments/index.php',
            ['courseid' => $courseid, 'onlydue' => $onlydue]), get_string('clear'), ['class' => 'btn btn-link']);
    }
    echo html_writer::end_tag('form');
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/westfieldpayments/index.php'),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'search', 'value' => $search]);
    echo html_writer::checkbox('onlydue', 1, $onlydue, get_string('onlydue', 'local_westfieldpayments'), [
        'id' => 'payment-only-due',
        'onchange' => 'this.form.submit()',
    ]);
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', get_string('selectstudent', 'local_westfieldpayments')) .
        html_writer::tag('th', get_string('student', 'local_westfieldpayments')) .
        html_writer::tag('th', get_string('paymentbalance', 'theme_westfield')) .
        html_writer::tag('th', get_string('dueduebalance', 'local_westfieldpayments'))));
    echo html_writer::start_tag('tbody');
    $visiblecount = 0;
    foreach ($students as $student) {
        $balance = $balances[$student->id] ?? null;
        if ($onlydue && (!$courseplan || !$hasdueinstallment || $duebalances[$student->id] <= 0)) {
            continue;
        }
        if ($search !== '' && core_text::strpos(core_text::strtolower(fullname($student) . ' ' . $student->email),
                core_text::strtolower($search)) === false) {
            continue;
        }
        $visiblecount++;
        $ischecked = $userid === (int)$student->id;
        $checkbox = html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'selectedusers[]',
            'value' => $student->id,
            'aria-label' => get_string('selectstudentname', 'local_westfieldpayments', fullname($student)),
            'checked' => $ischecked,
        ]);
        $balancecontent = $courseplan
            ? \local_westfieldpayments\ledger::decimal((int)$balance / 100) . ' ' . $courseplan->currency
            : get_string('paymentnotconfigured', 'theme_westfield');
        $duebalancecontent = $courseplan
            ? \local_westfieldpayments\ledger::decimal((int)($duebalances[$student->id] ?? 0) / 100) .
                ' ' . $courseplan->currency
            : get_string('paymentnotconfigured', 'theme_westfield');
        echo html_writer::tag('tr',
            html_writer::tag('td', $checkbox) .
            html_writer::tag('td', fullname($student) . ' (' . s($student->email) . ')') .
            html_writer::tag('td', $balancecontent) .
            html_writer::tag('td', $duebalancecontent));
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    if (!$students) {
        echo html_writer::tag('p', get_string('nostudents', 'local_westfieldpayments'));
    } else if (!$visiblecount) {
        echo html_writer::tag('p', get_string($search !== '' ? 'nosearchresults' : 'noduestudents',
            'local_westfieldpayments'));
    } else {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'onlydue', 'value' => (int)$onlydue]);
        echo html_writer::tag('button', get_string('choosestudent', 'local_westfieldpayments'), [
            'type' => 'submit',
            'name' => 'openstudent',
            'value' => '1',
            'class' => 'btn btn-primary',
        ]);
    }
    echo html_writer::end_tag('form');
    echo html_writer::end_div();

    if ($userid) {
        echo $OUTPUT->heading(fullname($students[$userid]), 3);
        echo html_writer::tag('p', get_string('manualnote', 'local_westfieldpayments'), ['class' => 'alert alert-info']);
        if ($paymentform) {
            echo html_writer::start_div('westfield-payment-admin-card');
            echo $OUTPUT->heading(get_string('addpayment', 'local_westfieldpayments'), 4);
            $paymentform->display();
            echo html_writer::end_div();
            echo $OUTPUT->heading(get_string('preview', 'local_westfieldpayments'), 3);
            $preview = \theme_westfield\output\payments::for_user($userid);
            $preview['courses'] = array_values(array_filter($preview['courses'],
                static fn($item) => $item['courseid'] === $courseid));
            $preview['coursecount'] = count($preview['courses']);
            if ($preview['courses']) {
                $preview['courses'][0]['open'] = true;
            }
            echo $OUTPUT->render_from_template('theme_westfield/payments', $preview);
        } else {
            echo $OUTPUT->notification(get_string('savecourseplanfirst', 'local_westfieldpayments'), 'info');
        }
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();
