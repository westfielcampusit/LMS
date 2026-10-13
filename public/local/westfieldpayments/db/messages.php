<?php
defined('MOODLE_INTERNAL') || die();
$messageproviders = [
    'paymentreminder' => [
        'defaults' => [
            'popup' => MESSAGE_FORCED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
