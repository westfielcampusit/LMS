<?php
require('../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/elibrary:view', $context);

$PAGE->set_url(new moodle_url('/local/elibrary/view.php'));
$PAGE->set_title(get_string('pluginname', 'local_elibrary'));
$PAGE->set_heading(get_string('pluginname', 'local_elibrary'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resources', 'local_elibrary'));
echo html_writer::tag('p', get_string('elibrarysubtitle', 'local_elibrary'), ['class' => 'text-muted mb-4']);

$resources = $DB->get_records('local_elibrary_item', null, 'timemodified DESC');
if (!$resources) {
    echo $OUTPUT->notification(get_string('noresourcesavailable', 'local_elibrary'), 'info');
} else {
    foreach ($resources as $resource) {
        $content = html_writer::tag('h2', format_string($resource->name), ['class' => 'card-title h5']);
        if (!empty($resource->description)) {
            $content .= html_writer::tag('div', format_text($resource->description, FORMAT_PLAIN, [
                'context' => $context,
            ]), ['class' => 'card-text']);
        }

        $files = get_file_storage()->get_area_files($context->id, 'local_elibrary', 'resource', $resource->id,
            'filename', false);
        $storedfile = reset($files);
        if ($storedfile) {
            $fileurl = moodle_url::make_pluginfile_url($context->id, 'local_elibrary', 'resource', $resource->id,
                $storedfile->get_filepath(), $storedfile->get_filename());
            $content .= html_writer::link($fileurl, get_string('openresource', 'local_elibrary'), [
                'class' => 'btn btn-primary mt-3',
            ]);
        }

        echo html_writer::tag('article', html_writer::tag('div', $content, ['class' => 'card-body']), [
            'class' => 'card mb-3',
        ]);
    }
}

echo $OUTPUT->footer();
