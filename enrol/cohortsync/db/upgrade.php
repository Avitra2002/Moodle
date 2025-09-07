<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_cohortsync_upgrade($oldversion) {
    if ($oldversion < 2025090304) {
        // Enable the plugin if not already enabled
        $enabled_plugins = explode(',', get_config('', 'enrol_plugins_enabled'));
        
        if (!in_array('cohortsync', $enabled_plugins)) {
            $enabled_plugins[] = 'cohortsync';
            $new_config = implode(',', $enabled_plugins);
            set_config('enrol_plugins_enabled', $new_config);
        }
        
        upgrade_plugin_savepoint(true, 2025090304, 'enrol', 'cohortsync');
    }
    
    return true;
}