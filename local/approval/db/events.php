<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_updated',
        'callback'    => '\local_approval\observer::on_user_updated',
        'includefile' => '/local/approval/classes/observer.php',
        'priority'    => 9999,
        'internal'    => false,
    ],
];
