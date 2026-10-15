<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/form/resource_form.php');

$id = optional_param('id', 0, PARAM_INT);
$context = context_system::instance();
require_login();
require_capability('local/elibrary:manage', $context);
$resource = $id ? $DB->get_record('local_elibrary_item', ['id' => $id], '*', MUST_EXIST) : null;
$url = new moodle_url('/local/elibrary/edit.php', $id ? ['id' => $id] : []);
admin_externalpage_setup('local_elibrary_manage', '', null, $url);

$title = $id ? get_string('editresource', 'local_elibrary') : get_string('addresource', 'local_elibrary');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$mform = new \local_elibrary\form\resource_form($url);
$draftitemid = file_get_submitted_draft_itemid('resourcefile');
$fileoptions = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => '*'];
if ($resource) {
    file_prepare_draft_area($draftitemid, $context->id, 'local_elibrary', 'resource', $resource->id, $fileoptions);
    $resource->resourcefile = $draftitemid;
    $mform->set_data($resource);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/elibrary/index.php'));
} else if ($data = $mform->get_data()) {
    $now = time();
    if ($resource) {
        $resource->name = $data->name;
        $resource->description = $data->description;
        $resource->timemodified = $now;
        $DB->update_record('local_elibrary_item', $resource);
        $itemid = $resource->id;
        $message = get_string('resourceupdated', 'local_elibrary');
    } else {
        $newresource = (object)[
            'name' => $data->name,
            'description' => $data->description,
            'timecreated' => $now,
            'timemodified' => $now,
            'userid' => $USER->id,
        ];
        $itemid = $DB->insert_record('local_elibrary_item', $newresource);
        $message = get_string('resourceadded', 'local_elibrary');
    }
    file_save_draft_area_files($data->resourcefile, $context->id, 'local_elibrary', 'resource', $itemid,
        $fileoptions);
    redirect(new moodle_url('/local/elibrary/index.php'), $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();
