<?php
namespace local_completion_report\form;

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class upload_history_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('file'), null, [
            'accepted_types' => ['.csv']
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('upload'));
    }
}
