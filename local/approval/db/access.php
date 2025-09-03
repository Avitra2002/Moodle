<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/approval:reviewcv' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
