<?php

defined('MOODLE_INTERNAL') || die();

class enrol_cohortsync_plugin extends enrol_plugin {
    
    public function get_info_icons(array $instances) {
        return [new pix_icon('i/cohort', get_string('pluginname', 'enrol_cohortsync'))];
    }
    
    public function get_instance_name($instance) {
        return get_string('pluginname', 'enrol_cohortsync');
    }
    
    public function allow_enrol(stdClass $instance) {
        return false; // Only sync engine can enrol users
    }
    
    public function allow_unenrol(stdClass $instance) {
        return false; // Only  sync engine can unenrol users
    }
    
    public function use_standard_editing_ui() {
        return true;
    }
    
    public function add_instance($course, array $fields = null) {
        global $DB;
        
        $fields = (array)$fields;
        $fields['status'] = $fields['status'] ?? ENROL_INSTANCE_ENABLED;
        $fields['name'] = $fields['name'] ?? '';
        $fields['roleid'] = $fields['roleid'] ?? $this->get_config('roleid', 0);
        
        return parent::add_instance($course, $fields);
    }
    
    public function update_instance($instance, $data) {
        $data->roleid = $data->roleid ?? $this->get_config('roleid', 0);
        return parent::update_instance($instance, $data);
    }
    
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = [];
        
        // no manual actions - everything is managed by sync engine
        
        return $actions;
    }
    

    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        // Auto-enable instance during restore
        $instanceid = $this->add_instance($course, (array)$data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }
    

    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
    
        return;
    }
    

    public function course_updated($inserted, $course, $data) {
        return;
    }
}


function enrol_cohortsync_plugin() {
    return enrol_get_plugin('cohortsync');
}