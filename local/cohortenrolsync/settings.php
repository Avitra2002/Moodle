<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('local_cohortenrolsync', get_string('pluginname', 'local_cohortenrolsync'));
    
    // Sync batch size
    $settings->add(new admin_setting_configtext(
        'local_cohortenrolsync/sync_batch_size',
        get_string('syncbatchsize', 'local_cohortenrolsync'),
        get_string('syncbatchsize_desc', 'local_cohortenrolsync'),
        100,
        PARAM_INT
    ));
    
    // Immediate sync on cohort changes
    $settings->add(new admin_setting_configcheckbox(
        'local_cohortenrolsync/immediate_sync',
        get_string('immediatesync', 'local_cohortenrolsync'), 
        get_string('immediatesync_desc', 'local_cohortenrolsync'),
        1
    ));
    
    // Debug mode
    $settings->add(new admin_setting_configcheckbox(
        'local_cohortenrolsync/debug_mode',
        get_string('debugmode', 'local_cohortenrolsync'),
        get_string('debugmode_desc', 'local_cohortenrolsync'),
        0
    ));
    
    // Manual sync button
    $url = new moodle_url('/local/cohortenrolsync/admin/manual_sync.php');
    $link = html_writer::link($url, get_string('runmanualsync', 'local_cohortenrolsync'), 
        ['class' => 'btn btn-primary']);
    
    $settings->add(new admin_setting_heading(
        'local_cohortenrolsync/manualsync',
        get_string('manualsync', 'local_cohortenrolsync'),
        get_string('manualsync_desc', 'local_cohortenrolsync') . '<br>' . $link
    ));
    
    $ADMIN->add('localplugins', $settings);
}