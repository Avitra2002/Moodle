<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
    'local/jdapproval:reviewjd' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
     
];