<?php
defined('MOODLE_INTERNAL') || die();

/** Serves E-Library files to users who can view the library. */
function local_elibrary_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'resource') {
        return false;
    }
    require_login();
    if (!has_capability('local/elibrary:view', $context)) {
        return false;
    }
    $itemid = (int)array_shift($args);
    if (!$itemid || !$DB->record_exists('local_elibrary_item', ['id' => $itemid])) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_elibrary', 'resource', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
