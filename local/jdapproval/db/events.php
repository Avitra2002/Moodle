<?php
defined('MOODLE_INTERNAL') || die();
$observers = [
    [
        'eventname'   => '\core\event\user_updated',
        'callback'    => '\local_jdapproval\observer::on_user_updated',
        'includefile' => '/local/jdapproval/classes/observer.php',
        'priority'    => 10000,
        'internal'    => false,
    ],
];