<?php

namespace local_completion_report\form;

use core_date;
use DateTime;
require_once("$CFG->libdir/formslib.php");

class filter_module_activity_report extends \moodleform {
    public function definition() {
        global $DB, $PAGE;
        $mform = $this->_form;
        $now = new DateTime("now", core_date::get_server_timezone_object());

        $year = $now->format('Y');

        $dateoption = array(
            'startyear' => $year - 10,
            'stopyear'  => $year,
            'timezone'  => usertimezone(),
            'optional'  => true
        );
        // $courses = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE visible = 1");
        // $courses = ['' => get_string('select_course', 'local_completion_report')] + $courses;

        // // Course filter
        // $mform->addElement('select', 'coursename', get_string('course', 'local_completion_report'), $courses);
        $sections = $DB->get_records_sql_menu("
            SELECT id, name 
            FROM {course_sections} 
            WHERE course = :courseid AND id IN (4,5,6,7,8,9) AND (name IS NOT NULL AND name != '')  
            ORDER BY section ASC", 
            ['courseid' => 2]
        );
        $sections = ['' => get_string('select_section', 'local_completion_report')] + $sections;
        // Section filter (empty initially, will be populated dynamically)
        $mform->addElement('select', 'sectionname', get_string('section', 'local_completion_report'),$sections);

        // Module/Activity filter (empty initially, will be populated dynamically)
        $mform->addElement('select', 'modulename', get_string('module_activity', 'local_completion_report'), ['' => get_string('select_module_activity', 'local_completion_report')]);

        // Completion status filter
        $mform->addElement('select', 'module_completion', get_string('completion', 'local_completion_report'), [
            '' => get_string('any', 'local_completion_report'),
            'yes' => get_string('completed', 'local_completion_report'),
            'no' => get_string('not_completed', 'local_completion_report')
        ]);

        // Date from filter
        $mform->addElement('date_selector', 'datefrom', get_string('date_from', 'local_completion_report'), $dateoption);

        // Date to filter
        $mform->addElement('date_selector', 'dateto', get_string('date_to', 'local_completion_report'), $dateoption);

        // Add action buttons
        $this->add_action_buttons(true, get_string('filter', 'local_completion_report'));

        // Include the JavaScript file
        $PAGE->requires->js_call_amd('local_completion_report/filter_module_activity', 'init');
    }
}
