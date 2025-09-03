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

require_once('../../config.php');

require "$CFG->libdir/tablelib.php";

require_once($CFG->dirroot . '/user/profile/lib.php');

require_once('lib.php');

require_login();

$systemcontext = context_system::instance();

require_capability('moodle/user:update', $systemcontext); // Only admins

/*if (!is_siteadmin() && !user_has_role_assignment($USER->id, 1, $systemcontext->id)) {

    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');

}*/

$download = optional_param('download', '', PARAM_ALPHA);

$title = 'Training History Report';

$PAGE->set_context(context_system::instance());

$PAGE->set_url($CFG->wwwroot . '/local/completion_report/report.php');



$instance = new stdClass();



$mform = new \local_completion_report\form\filter_course_report(null);



$table = new \local_completion_report\tables\course_report('uniqueid');

$table->is_downloading($download, 'course_completion_report', 'course_completion_report');



if (!$table->is_downloading()) {

    // Only print headers if not asked to download data

    // Print the page header

    $PAGE->set_title($title);

    $PAGE->set_heading($title);

    $PAGE->set_pagelayout('standard');

    echo $OUTPUT->header();

}





//$fields = "ue.id, u.firstname,u.lastname, u.email, u.id, u.country, u.city, c.fullname as coursename, ue.timecreated as enrolment_date, cc.timecompleted as course_completion, ul.timeaccess as course_last_access, c.id as courseid, ue.userid";

//$from = "{user_enrolments} ue JOIN {user} u ON  u.id = ue.userid  LEFT JOIN {enrol} e ON e.id = ue.enrolid JOIN {course} c ON c.id = e.courseid LEFT JOIN {context} cont ON cont.instanceid = c.id LEFT JOIN {role_assignments} ra ON ra.contextid = cont.id AND ra.userid = ue.userid LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid LEFT JOIN {user_lastaccess} ul ON ul.userid = ue.userid AND ul.courseid = c.id";

$fields = "

    ue.id,

    u.firstname,

    u.lastname,

    luf.administrative_positions AS luf_administrative_positions,

    luf.position_in_the_line_of_work AS luf_position_in_the_line_of_work,

    luf.personal_type AS luf_personal_type,

    luf.coursetype AS luf_coursetype,

    luf.coursegroup AS luf_coursegroup,

    luf.number_of_days AS luf_number_of_days,

    pn.data AS position_number,

    pc.data AS position_characteristics,

    ap.data AS administrative_positions,

    plw.data AS position_line_work,

    ptype.data AS position_type,

    pl.data AS position_level,

    CONCAT(u.firstname, ' ', u.lastname) AS fullname,

    u.email,

    u.country,

    u.city,

    c.fullname AS coursename,

    ue.timestart  AS enrolment_date,

    cc.timecompleted AS course_completion,

    ul.timeaccess AS course_last_access,

    c.id AS courseid,

    ue.userid,



    

    

    

    off.data AS office_division,

    off1.data AS office_division_1_level,

    off2.data AS office_division_2_levels,

    opoff.data AS office_division_operation,

    op1.data AS operation_division_1_level,

    op2.data AS operation_division_2_levels,



    

    cd_model.value AS model_no,

    cd_org_agency.value AS organizing_agency,

    cd_venue.value AS venue,

    cd_org_country.value AS organizing_country,

    cd_scholarship.value AS name_of_scholarship,

    cd_capital_owner.value AS capital_owning_country,

    cd_note.value AS note,

    cd_dl_number.value AS delivery_letter_number,

    cd_dl_date.value AS dated_delivery_letter,

    cd_project.value AS project_name,

    cd_order_number.value AS order_number,

    cd_order_date.value AS dated_order";



$from=" mdl_user_enrolments ue

JOIN mdl_user u ON u.id = ue.userid

LEFT JOIN mdl_local_userprofilefields luf ON luf.enrollmentid = ue.id

LEFT JOIN mdl_enrol e ON e.id = ue.enrolid

JOIN mdl_course c ON c.id = e.courseid

LEFT JOIN mdl_context cont ON cont.instanceid = c.id AND cont.contextlevel = 50

LEFT JOIN mdl_role_assignments ra ON ra.contextid = cont.id AND ra.userid = ue.userid

LEFT JOIN mdl_course_completions cc ON cc.course = c.id AND cc.userid = ue.userid

LEFT JOIN mdl_user_lastaccess ul ON ul.userid = ue.userid AND ul.courseid = c.id





LEFT JOIN mdl_user_info_data pt ON pt.userid = u.id AND pt.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'personnel_type' LIMIT 1)

LEFT JOIN mdl_user_info_data pn ON pn.userid = u.id AND pn.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'position_number' LIMIT 1)

LEFT JOIN mdl_user_info_data pc ON pc.userid = u.id AND pc.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'Position_Characteristics' LIMIT 1)

LEFT JOIN mdl_user_info_data ap ON ap.userid = u.id AND ap.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'administrativepositions' LIMIT 1)

LEFT JOIN mdl_user_info_data plw ON plw.userid = u.id AND plw.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'positionlinework' LIMIT 1)

LEFT JOIN mdl_user_info_data ptype ON ptype.userid = u.id AND ptype.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'position_type' LIMIT 1)

LEFT JOIN mdl_user_info_data pl ON pl.userid = u.id AND pl.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'position_level' LIMIT 1)

LEFT JOIN mdl_user_info_data off ON off.userid = u.id AND off.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'Office_Division' LIMIT 1)

LEFT JOIN mdl_user_info_data off1 ON off1.userid = u.id AND off1.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'below1levelofpile' LIMIT 1)

LEFT JOIN mdl_user_info_data off2 ON off2.userid = u.id AND off2.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = '2_levels_below_the_pile' LIMIT 1)

LEFT JOIN mdl_user_info_data opoff ON opoff.userid = u.id AND opoff.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'officedivisionoperation' LIMIT 1)

LEFT JOIN mdl_user_info_data op1 ON op1.userid = u.id AND op1.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'belowdivision1leveloperational' LIMIT 1)

LEFT JOIN mdl_user_info_data op2 ON op2.userid = u.id AND op2.fieldid = (SELECT id FROM mdl_user_info_field WHERE shortname = 'Below2levelsoperational' LIMIT 1)





LEFT JOIN mdl_customfield_data cd_model ON cd_model.instanceid = ue.id AND  cd_model.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'modelno'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_org_agency ON cd_org_agency.instanceid = ue.id AND  cd_org_agency.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'organizingagency'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_venue ON cd_venue.instanceid = ue.id AND  cd_venue.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'venue'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_org_country ON cd_org_country.instanceid = ue.id  AND cd_org_country.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'organizingcountry'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_scholarship ON cd_scholarship.instanceid = ue.id  AND cd_scholarship.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'nameofscholarship'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_capital_owner ON cd_capital_owner.instanceid = ue.id  AND cd_capital_owner.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'capitalowningcountry'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_note ON cd_note.instanceid = ue.id  AND cd_note.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'note'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_dl_number ON cd_dl_number.instanceid = ue.id  AND cd_dl_number.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'deliveryletternumber' LIMIT 1)

LEFT JOIN mdl_customfield_data cd_dl_date ON cd_dl_date.instanceid = ue.id  AND cd_dl_date.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'dateddeliveryletter'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_project ON cd_project.instanceid = ue.id  AND cd_project.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'projectname'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_order_number ON cd_order_number.instanceid = ue.id  AND cd_order_number.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'ordernumber'  LIMIT 1)

LEFT JOIN mdl_customfield_data cd_order_date ON cd_order_date.instanceid = ue.id  AND cd_order_date.fieldid = (SELECT id FROM mdl_customfield_field WHERE shortname = 'datedorder'  LIMIT 1)";



$sql = "SELECT $fields FROM $from";




$coursenamewhere = '';

$coursecompletionwhere = '';

$datefromwhere = '';

$datetowhere = '';



// Form processing and displaying is done here.

if ($mform->is_cancelled()) {

    unset_filter_variables();

    redirect($PAGE->url);

} else if ($fromform = $mform->get_data()) {

    unset_filter_variables();



    // If user use course name filter. 

    if (!empty($fromform->coursename)) {

        $courseid = trim($fromform->coursename);

        $courseid = strip_tags($fromform->coursename);

        $coursenamewhere = "AND c.id = $courseid";

        $_SESSION['coursename'] = $courseid;

    }

    // If user use course course completion filter. 

    if (!empty($fromform->course_completion)) {

        $coursecompletion = trim($fromform->course_completion);

        $coursecompletion = strip_tags($fromform->course_completion);

        if ($coursecompletion == "yes") {

            $coursecompletionwhere = "AND cc.timecompleted IS NOT NULL";

        } else {

            $coursecompletionwhere = "AND cc.timecompleted IS NULL ";

        }

        $_SESSION['course_completion'] = $coursecompletion;

    }



    // If user use date from filter. 

    if (!empty($fromform->datefrom)) {

        $datefrom = trim($fromform->datefrom);

        $datefrom = strip_tags($fromform->datefrom);

        $datefromwhere = "AND ue.timecreated >= '$datefrom'";

        $_SESSION['datefrom'] = $datefrom;

    }



    // If user use date from filter. 

    if (!empty($fromform->dateto)) {

        $dateto = trim($fromform->dateto);

        $dateto = strip_tags($fromform->dateto);

        $datetowhere = "AND ue.timecreated <= '$dateto'";

        $_SESSION['dateto'] = $dateto;

    }

}



if (isset($_SESSION['dateto'])) {

    $instance->dateto = $_SESSION['dateto'];

    $datetowhere = "AND ue.timecreated <= '$instance->dateto'";

}

if (isset($_SESSION['datefrom'])) {

    $instance->datefrom = $_SESSION['datefrom'];

    $datefromwhere = "AND ue.timecreated >= '$instance->datefrom'";

}

if (isset($_SESSION['course_completion'])) {

    $instance->course_completion = $_SESSION['course_completion'];

    if ($instance->course_completion == "yes") {

        $coursecompletionwhere = "AND cc.timecompleted IS NOT NULL";

    } else {

        $coursecompletionwhere = "AND cc.timecompleted IS NULL ";

    }

}

if (isset($_SESSION['coursename'])) {

    $instance->coursename = $_SESSION['coursename'];

    $coursenamewhere = "AND c.id = $instance->coursename";

}

if (!is_siteadmin() && !user_has_role_assignment($USER->id, 1, $systemcontext->id)) {

$coursenamewhere1=" AND u.id = $USER->id"; 

}

$where = "u.deleted = 0 AND u.suspended = 0 AND cont.contextlevel = 50 AND ra.roleid = 5 $coursenamewhere1 $coursenamewhere $coursecompletionwhere $datefromwhere $datetowhere";





// Work out the sql for the table.

$table->set_sql($fields, $from, $where);


$table->sortable(false, 'uniqueid');

$table->define_baseurl($PAGE->url);

/* 

$sql = "SELECT {$table->sql->fields}

        FROM {$table->sql->from}

        WHERE {$table->sql->where}";



echo "<pre>Final SQL:\n{$sql}</pre>";

 */



if (!$table->is_downloading()) {

    $mform->set_data($instance);

    $mform->display();



    $pagesize = optional_param('perpage', 20, PARAM_INT);  

    $table->out($pagesize, true); 

} else {

    $table->out(0, true);  

}



if (!$table->is_downloading()) {

    echo $OUTPUT->footer();

}

