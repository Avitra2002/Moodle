<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'jd_status_change' => [
        'capability' => NULL,
        'defaults' => [
            'popup' => MESSAGE_FORCED,
			'email' => MESSAGE_FORCED,
        ],
    ],
];