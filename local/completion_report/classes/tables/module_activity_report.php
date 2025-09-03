<?php

namespace local_completion_report\tables;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

class module_activity_report extends \table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = array('studentname' , 'coursename', 'sectionname', 'email', 'completion_time', 'completion_status');
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array(get_string('studentname', 'local_completion_report'), get_string('coursename', 'local_completion_report'), 'Section',  get_string('email'), get_string('completion_time', 'local_completion_report'), 
            get_string('completion_status', 'local_completion_report'));
        $this->define_headers($headers);
    }

    public function col_modulename($values) {
        return $values->modulename;
    }

    public function col_completion_status($values) {
        return ($values->completion_status == 1) ? get_string('completed', 'local_completion_report') : get_string('not_completed', 'local_completion_report');
    }

    public function col_completion_time($values) {
        return $values->completion_time ? userdate($values->completion_time) : '-';
    }
}
