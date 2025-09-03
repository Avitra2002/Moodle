<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'cv_status_change' => [
        'capability' => '',
        'defaults' => [
            'popup' => MESSAGE_FORCED,
			'email' => MESSAGE_FORCED,
        ],
    ],
];