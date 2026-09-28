<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Explain mobile QR availability on the owner's profile until mobile services are enabled.
 * Moodle's mobile plugin supplies the real QR section once enabled and configured.
 */
function theme_westfield_myprofile_navigation($tree, $user, $iscurrentuser, $course = null) {
    global $CFG, $PAGE;

    if (!$iscurrentuser || $PAGE->theme->name !== 'westfield') {
        return;
    }

    // Keep unfilled student details visible on the owner's profile without duplicating native nodes.
    $notprovided = get_string('profilenotprovided', 'theme_westfield');
    if (empty($user->country) && !isset($tree->nodes['country'])) {
        $tree->add_node(new \core_user\output\myprofile\node(
            'contact', 'country', get_string('country'), null, null, $notprovided
        ));
    }
    require_once($CFG->dirroot . '/user/profile/lib.php');
    foreach (profile_get_user_fields_with_data($user->id) as $field) {
        if (!in_array($field->field->shortname, ['programme', 'batchcohort', 'studentregistration'], true)) {
            continue;
        }
        $nodename = 'custom_field_' . $field->field->shortname;
        if ($field->is_visible() && !isset($tree->nodes[$nodename])) {
            $tree->add_node(new \core_user\output\myprofile\node(
                'contact', $nodename, $field->display_name(), null, null,
                $field->is_empty() ? $notprovided : $field->display_data()
            ));
        }
    }

    if (!empty($CFG->enablemobilewebservice)) {
        return;
    }

    $tree->add_category(new \core_user\output\myprofile\category(
        'westfieldmobile', get_string('mobileapp', 'tool_mobile'), null, 'westfield-mobile-profile'
    ));
    $content = html_writer::tag('p', get_string('mobileqrsetup', 'theme_westfield'));
    $content .= html_writer::tag('button', get_string('viewqrcode', 'tool_mobile'), [
        'type' => 'button',
        'class' => 'btn btn-primary',
        'disabled' => 'disabled',
        'aria-describedby' => 'westfield-mobile-qr-status',
    ]);
    $content .= html_writer::tag('p', get_string('mobileqrunavailable', 'theme_westfield'), [
        'id' => 'westfield-mobile-qr-status',
        'class' => 'westfield-mobile-qr-status',
    ]);
    $tree->add_node(new \core_user\output\myprofile\node(
        'westfieldmobile', 'westfieldmobileqr', get_string('qrcodeformobileappaccess', 'tool_mobile'),
        null, null, $content
    ));
}

/**
 * Build complete WestField SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_westfield_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    /*
     * =====================================================
     * 1. BOOST BASE SCSS
     * =====================================================
     */

    $boostscss =
        $CFG->dirroot .
        '/theme/boost/scss/preset/default.scss';

    if (file_exists($boostscss)) {
        $scss .= file_get_contents($boostscss);
        $scss .= "\n\n";
    }


    /*
     * =====================================================
     * 2. WESTFIELD SCSS PARTIALS
     * =====================================================
     */

    $scssdir = __DIR__ . '/scss/westfield/';

    $files = [
        '_variables.scss',
        '_global.scss',
        '_navbar.scss',
        '_login.scss',
        '_drawer.scss',
        '_sidebar.scss',
        '_dashboard.scss',
        '_courses.scss',
        '_cards.scss',
        '_forms.scss',
        '_buttons.scss',
        '_tables.scss',
        '_admin.scss',
        '_profile.scss',
        '_calendar.scss',
        '_filemanager.scss',
        '_footer.scss',
        '_responsive.scss',
    ];

    foreach ($files as $file) {

        $path = $scssdir . $file;

        if (file_exists($path)) {

            $scss .= "\n\n";
            $scss .= "/* ===== {$file} ===== */\n";
            $scss .= file_get_contents($path);
            $scss .= "\n";

        }
    }

    return $scss;
}
