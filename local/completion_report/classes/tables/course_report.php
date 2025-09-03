<?php



namespace local_completion_report\tables;



use table_sql;

use html_writer;



/**

 * Test table class to be put in test_table.php of root of Moodle installation.

 *  for defining some custom column names and proccessing

 * Username and Password feilds using custom and other column methods.

 */



 

class course_report extends table_sql

{



    /**

     * Constructor

     * @param int $uniqueid all tables have to have a unique id, this is used

     *      as a key when storing table properties like sort order in the session.

     */

    function __construct($uniqueid)

    {

        parent::__construct($uniqueid);

        // Define the list of columns to show.

        $columns = array(

            'personal_type',

            'position_number',

            'position_characteristics',

            'administrative_positions',

            'position_in_the_line_of_work',

            'position_type',

            'position_level',

            'coursetype',

            'coursegroup',

            'fullname',

            'email',

            'coursename',

            'enrolment_date',

            'course_completion',

          /**  'course_last_access', **/

            'completion_percentage',

            'number_of_days',

            'model_no',

            'organizing_agency',

            'venue',

            'organizing_country',

            'name_of_scholarship',

            'capital_owning_country',

            'note',

            'delivery_letter_number',

            'dated_delivery_letter',

            'project_name',

            'order_number',

            'dated_order',

            'office_division',

            'office_division_1_level',

            'office_division_2_levels',

            'office_division_operation',

            'operation_division_1_level',

            'operation_division_2_levels'

        );

        

        $this->define_columns($columns);

        



        // Define the titles of columns to show in header.

        $headers = array(

            'Personnel type',

            'Position number',

            'Position characteristics',

            'Administrative positions',

            'Position in the line of work',

            'Position type',

            'Position level',

            'Course Type',

            'Course Group',

            "Fullname", get_string('email'), //get_string('country'), get_string('city'), 

            //get_string('age', 'local_completion_report'), 

            //get_string('gender', 'local_completion_report'), 

            get_string('coursename', 'local_completion_report'),

            'Start Date',

            'End Date',        

         /**   get_string('course_last_access', 'local_completion_report'), **/

            get_string('completion_percentage', 'local_completion_report'),

            'Number of Days',

            'Model No.',

            'Organizing agency',

            'Venue',

            'Organizing country',

            'Name of scholarship',

            'Capital-owning country',

            'note',

            'Delivery letter number',

            'Dated delivery letter',

            'Project name',

            'Order number',

            'Dated order',

            'Office/Division',

            '1 level below the office/division',

            '2 levels below the office/division',

            'Office/Division (Operation)',

            'Below the Bureau/Division 1 level (Operational)',

            'Below the Bureau/Division 2 levels (Operational)'

        );

        $this->define_headers($headers);

    }



    // function col_schoolname($values)

    // {

    //     global $DB;

    //     $user = $DB->get_record('user', ['id' => $values->userid]);

    //     profile_load_custom_fields($user);

    //     if (isset($user->profile['schoolname'])) {

    //         return $user->profile['schoolname'];

    //     }

    //     return '-';

    // }

    // function col_learner_category($values)

    // {

    //     global $DB;

    //     $user = $DB->get_record('user', ['id' => $values->userid]);

    //     profile_load_custom_fields($user);

    //     if (isset($user->profile['learner_category'])) {

    //         return $user->profile['learner_category'];

    //     }

    //     return '-';

    // }

    function col_age($values)

    {



        global $DB;

        $user = $DB->get_record('user', ['id' => $values->userid]);



        profile_load_custom_fields($user);



        

        if (isset($user->profile['Age'])) {

            return $user->profile['Age'];

        }

        return '-';

    }

    function col_gender($values)

    {

        global $DB;

        $user = $DB->get_record('user', ['id' => $values->userid]);

        profile_load_custom_fields($user);

        if (isset($user->profile['Gender'])) {

            return $user->profile['Gender'];

        }

        return '-';

    }

    function col_country($values)

    { 

        $countries = get_string_manager()->get_list_of_countries();

        if (isset($countries[$values->country])) {

            return $countries[$values->country];

        }

        return '-';

    }

    

    function col_city($values)

    { 

        if( $values->city){

        return $values->city;

    }

    return '-';

    }



   



    function col_course_completion($values)

    {

        if (empty($values->course_completion) || $values->course_completion == 0) {

            return '-';

        }

        return userdate($values->course_completion, '%d %B %Y');

    }



    function col_course_last_access($values)

    {

        if (empty($values->course_last_access) || $values->course_last_access == 0) {

            return '-';

        }

        return userdate($values->course_last_access, '%d %B %Y');

    }



    function col_enrolment_date($values)

    {
        if (empty($values->enrolment_date) || $values->enrolment_date == 0) {

            return '-';

        }

        return userdate($values->enrolment_date, '%d %B %Y');

    }

   

    function col_completion_percentage($values)

    {

        global $DB;

        $course = $DB->get_record('course', ['id' => $values->courseid]);

        $check = \core_completion\progress::get_course_progress_percentage($course, $values->userid);

        if ($check) {

            return round($check, 2) . " %";

        }

        return '0%';

    }

    function col_fullname($values) {

      global $CFG;

      $url = $CFG->wwwroot . '/user/view.php?id=' . $values->userid . '&course=' . $values->courseid;

     return html_writer::link($url, $values->fullname);

    }


    function col_personal_type($values)

    {

        if (empty($values->luf_personal_type)) {

            return '-';

        }

        return $values->luf_personal_type;

    }

    function col_administrative_positions($values)

    {

        if (empty($values->luf_administrative_positions)) {

            return '-';

        }

        return $values->luf_administrative_positions;

    }

    function col_position_in_the_line_of_work($values)

    {

        if (empty($values->luf_position_in_the_line_of_work)) {

            return '-';

        }

        return $values->luf_position_in_the_line_of_work;

    }

    function col_coursetype($values)

    {

        if (empty($values->luf_coursetype)) {

            return '-';

        }

        return $values->luf_coursetype;

    }

    function col_coursegroup($values)

    {

        if (empty($values->luf_coursegroup)) {

            return '-';

        }

        return $values->luf_coursegroup;

    }

    function col_number_of_days($values)

    {

        if (empty($values->luf_number_of_days)) {

            return '-';

        }

        return $values->luf_number_of_days;

    }

}

