<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_completion_report
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completion_report\form;

use core_date;
use DateTime;
// moodleform is defined in formslib.php
require_once("$CFG->libdir/formslib.php");

class filter_course_report extends \moodleform
{
    // Add elements to form.
    public function definition()
    {
        global $DB;
        // A reference to the form is stored in $this->form.
        // A common convention is to store it in a variable, such as `$mform`.
        $mform = $this->_form; // Don't forget the underscore!
        $now = new DateTime("now", core_date::get_server_timezone_object());

        $year = $now->format('Y');
   
        $dateoption = array(
            'startyear' => $year - 10,
            'stopyear'  => $year,
            'timezone'  => usertimezone(),
            'optional'  => true
        );
        $courses = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE visible = 1 and id != 1");
        $courses = ['' => "Select Course"] + $courses;
        // Add elements to your form.
        $mform->addElement('select', 'coursename', get_string('coursename', 'local_completion_report'), $courses);
        $mform->addElement('select', 'course_completion', get_string('course_completion', 'local_completion_report'), ['' => 'Please Select', 'yes' => get_string('yes'), 'no' => get_string('no')]);
        //$mform->addElement('date_selector', 'datefrom', get_string('datefrom', 'local_completion_report'), $dateoption);
        //$mform->addElement('date_selector', 'dateto', get_string('dateto', 'local_completion_report'), $dateoption);

        $this->add_action_buttons();
    }

    // Custom validation should be added here.
    function validation($data, $files)
    {
        return [];
    }
}
