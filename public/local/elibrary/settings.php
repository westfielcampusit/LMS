<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_elibrary_manage',
        get_string('manage', 'local_elibrary'),
        new moodle_url('/local/elibrary/index.php'),
        'local/elibrary:manage'
    ));
}
