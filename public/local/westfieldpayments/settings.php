<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_westfieldpayments', get_string('pluginname', 'local_westfieldpayments'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configcheckbox('local_westfieldpayments/remindersenabled',
        get_string('remindersenabled', 'local_westfieldpayments'),
        get_string('remindersenabled_desc', 'local_westfieldpayments'), 1));
    $days = array_combine(range(1, 365), range(1, 365));
    foreach (['reminderinterval', 'blockgrace'] as $setting) {
        $settings->add(new admin_setting_configselect('local_westfieldpayments/' . $setting,
            get_string($setting, 'local_westfieldpayments'),
            get_string($setting . '_desc', 'local_westfieldpayments'), 3, $days));
    }
}
