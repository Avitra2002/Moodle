<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_cohortsync_install() {
    global $CFG;
    
    // Get current enabled plugins
    $enabled_plugins = explode(',', get_config('', 'enrol_plugins_enabled'));
    
    // Add cohortsync if not already present
    if (!in_array('cohortsync', $enabled_plugins)) {
        $enabled_plugins[] = 'cohortsync';
        $new_config = implode(',', $enabled_plugins);
        set_config('enrol_plugins_enabled', $new_config);
    }
    
    return true;
}