<?php
// Student payment overview. Financial records are always scoped to the signed-in user.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/enrollib.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url(new moodle_url('/theme/westfield/payments.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('payments', 'theme_westfield'));
$PAGE->set_heading(get_string('payments', 'theme_westfield'));
$PAGE->navbar->add(get_string('payments', 'theme_westfield'));

$data = \theme_westfield\output\payments::for_user((int)$USER->id);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_westfield/payments', $data);
echo $OUTPUT->footer();
