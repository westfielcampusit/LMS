<?php
defined('MOODLE_INTERNAL') || die();

function local_westfieldpayments_after_require_login($courseorid, $autologinguest, $cm,
        $setwantsurltome, $preventredirect) {
    \local_westfieldpayments\reminders::enforce((bool)$preventredirect);
}

function local_westfieldpayments_before_http_headers() {
    \local_westfieldpayments\reminders::enforce();
}
