<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_cohortenrolsync\task\sync_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',       // Run at 2 AM
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];