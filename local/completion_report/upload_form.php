<?php
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class local_completion_report_upload_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $sampleurl = new moodle_url('/local/completion_report/sample/sample_csv.csv');
        $linkhtml = html_writer::link($sampleurl, get_string('downloadsamplecsv', 'local_completion_report'), [
            'target' => '_blank',
            'style' => 'display:block; margin-bottom:10px; color:blue; text-decoration:underline;'
        ]);
        $mform->addElement('static', 'samplecsvlink', '', $linkhtml);
        $mform->addElement('filepicker', 'csvfile', get_string('uploadfile', 'local_completion_report'), null,
            ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('submit', 'submitbutton', get_string('upload', 'local_completion_report'));
    }

    public function definition_after_data() {
        global $USER;
        $draftitemid = file_get_submitted_draft_itemid('csvfile');
        file_prepare_draft_area(
            $draftitemid,
            context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid
        );
        $this->_form->setDefault('csvfile', $draftitemid);
    }
}
