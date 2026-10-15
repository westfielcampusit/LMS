<?php
require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT);
$context = context_system::instance();
require_login();
require_capability('local/elibrary:manage', $context);
require_sesskey();
$resource = $DB->get_record('local_elibrary_item', ['id' => $id], '*', MUST_EXIST);
admin_externalpage_setup('local_elibrary_manage');

$fs = get_file_storage();
$fs->delete_area_files($context->id, 'local_elibrary', 'resource', $id);
$DB->delete_records('local_elibrary_item', ['id' => $id]);
redirect(new moodle_url('/local/elibrary/index.php'), get_string('resourcedeleted', 'local_elibrary'), null,
    \core\output\notification::NOTIFY_SUCCESS);
