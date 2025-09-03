<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

class uploadcv_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Field name must match 'profile_field_<shortname>'
        $mform->addElement('filepicker', 'profile_field_CVUpload', 'Upload Your CV');
        $mform->addRule('profile_field_CVUpload', null, 'required', null, 'client');

        $this->add_action_buttons(true, 'Upload CV');
    }
}

