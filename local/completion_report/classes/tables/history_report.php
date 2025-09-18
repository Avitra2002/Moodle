<?php
namespace local_completion_report\tables;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/tablelib.php');

class history_report extends \table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $columns = [
            'fullname' => 'Full Name',
            'email' => 'Email',
            'personal_type' => 'Personal Type',
            'position_number' => 'Position Number',
            'position_characteristics' => 'Position Characteristics',
            'administrative_position' => 'Administrative Position',
            'position_lineofwork' => 'Position Line-of-work',
            'position_type' => 'Position Type',
            'position_level' => 'Position Level',
            'course_type' => 'Course Type',
            'course_group' => 'Course Group',
            'course_name' => 'Course Name',
            'enrolment_date' => 'Enrolment Date',
            'completion_date' => 'Completion Date',
            'number_of_days' => 'Number of Days',
            'intake_no' => 'Intake No.',
            'organizing_agency' => 'Organizing Agency',
            'venue' => 'Venue',
            'organizing_country' => 'Organizing Country',
            'scholarship_name' => 'Name of Scholarship',
            'scholarship_country' => 'Scholarship Sponsoring Country',
            'remarks' => 'Remarks',
            'transmittal_letter_no' => 'Transmittal Letter No.',
            'transmittal_letter_date' => 'Date of Transmittal Letter',
            'project_name' => 'Project Name',
            'order_number' => 'Order Number',
            'order_date' => 'Order Date',
            'division_department' => 'Division/Department',
            'division_department_lvl1' => 'One level below Division/Department',
            'division_department_lvl2' => 'Two levels below Division/Department',
            'division_department_operational' => 'Division/Department (Operational)',
            'division_department_op_lvl1' => 'One level below (Operational)',
            'division_department_op_lvl2' => 'Two levels below (Operational)',
        ];


        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        $fields = "
            id, userid, email, personal_type, fullname,
            position_number, position_characteristics, administrative_position,
            position_lineofwork, position_type, position_level,
            course_type, course_group, course_name,
            enrolment_date, completion_date, number_of_days, intake_no,
            organizing_agency, venue, organizing_country,
            scholarship_name, scholarship_country, remarks,
            transmittal_letter_no, transmittal_letter_date,
            project_name, order_number, order_date,
            division_department, division_department_lvl1, division_department_lvl2,
            division_department_operational, division_department_op_lvl1, division_department_op_lvl2
        ";

        $from   = "{local_coursehistory}";
        $where  = "1=1";

        $this->set_sql($fields, $from, $where);
    }

    public function col_fullname($row) {
        global $OUTPUT;
        if ($row->userid) {
            $url = new \moodle_url('/user/profile.php', ['id' => $row->userid]);
            return \html_writer::link($url, $row->fullname);
        }
        return $row->fullname;
    }

    public function col_completion_date($row) {
        return $row->completion_date ? userdate($row->completion_date) : '';
    }
}
