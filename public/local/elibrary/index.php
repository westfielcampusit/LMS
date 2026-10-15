<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
require_login();
require_capability('local/elibrary:manage', $context);
admin_externalpage_setup('local_elibrary_manage');

$PAGE->set_url(new moodle_url('/local/elibrary/index.php'));
$PAGE->set_title(get_string('manage', 'local_elibrary'));
$PAGE->set_heading(get_string('manage', 'local_elibrary'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resources', 'local_elibrary'));
echo $OUTPUT->single_button(new moodle_url('/local/elibrary/edit.php'), get_string('addresource', 'local_elibrary'), 'get');

$resources = $DB->get_records('local_elibrary_item', null, 'timemodified DESC');
if (!$resources) {
    echo $OUTPUT->notification(get_string('noresources', 'local_elibrary'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('name', 'local_elibrary'), get_string('file', 'local_elibrary'),
        get_string('timemodified', 'moodle'), get_string('actions', 'local_elibrary')];
    foreach ($resources as $resource) {
        $files = get_file_storage()->get_area_files($context->id, 'local_elibrary', 'resource', $resource->id,
            'filename', false);
        $storedfile = reset($files);
        $filelabel = $storedfile ? s($storedfile->get_filename()) : get_string('nofiles', 'local_elibrary');
        if ($storedfile) {
            $fileurl = moodle_url::make_pluginfile_url($context->id, 'local_elibrary', 'resource', $resource->id,
                $storedfile->get_filepath(), $storedfile->get_filename());
            $filelabel = html_writer::link($fileurl, $filelabel);
        }
        $edit = html_writer::link(new moodle_url('/local/elibrary/edit.php', ['id' => $resource->id]),
            get_string('edit'));
        $deleteurl = new moodle_url('/local/elibrary/delete.php', ['id' => $resource->id, 'sesskey' => sesskey()]);
        $confirm = new \core\output\actions\confirm_action(get_string('confirmdelete', 'local_elibrary',
            format_string($resource->name)));
        $delete = new \core\output\action_link($deleteurl, get_string('delete'), $confirm);
        $table->data[] = [format_string($resource->name), $filelabel,
            userdate($resource->timemodified), $edit . ' | ' . $OUTPUT->render($delete)];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
