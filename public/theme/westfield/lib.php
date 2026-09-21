<?php

defined('MOODLE_INTERNAL') || die();

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