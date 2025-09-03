<?php
require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_login();
$systemcontext = context_system::instance();
if (!is_siteadmin() && !user_has_role_assignment($USER->id, 1, $systemcontext->id)) {
    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');
}

class activity_resource_summary_table extends table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns(array('course_name', 'section_name', 'activity_name', 'total_students', 'students_completed', 'completion_percentage'));
        $this->define_headers(array('Course Name', 'Section', 'Activity/Resource', 'Students Enrolled', 'Students Completed', 'Completion %'));
    }
    
    public function col_activity_name($values) {
        return $values->activity_name;
    }
}

// Set up the page and table
$PAGE->set_url('/local/completion_report/activity_resource_report.php');
$PAGE->set_context(context_system::instance());
$download = optional_param('download', '', PARAM_ALPHA);

$filename = "activity_resource_summary";
$table = new activity_resource_summary_table($filename);

$table->is_downloading($download, $filename, 'Activity/Resource Summary');

if (!$table->is_downloading()) {
    $PAGE->set_title('Activity/Resource Summary');
    $PAGE->set_heading('Activity/Resource Summary');
    echo $OUTPUT->header();
}

// SQL query to get activity/resource details along with enrollment and completion data
$fields = "
    cm.id AS module_id,
    c.fullname AS course_name,
    cs.name AS section_name,
    (SELECT md.name FROM {modules} md WHERE md.id = cm.module) AS activity_name,
    (SELECT COUNT(DISTINCT ue.userid)
     FROM {user_enrolments} ue
     JOIN {enrol} e ON ue.enrolid = e.id
     JOIN {role_assignments} ra ON ue.userid = ra.userid
     WHERE e.courseid = c.id AND ra.roleid = 5) AS total_students,
    (SELECT COUNT(DISTINCT cmc.userid) 
     FROM {course_modules_completion} cmc 
     WHERE cmc.coursemoduleid = cm.id AND cmc.completionstate = 1) AS students_completed,
    ROUND(((SELECT COUNT(DISTINCT cmc.userid) 
            FROM {course_modules_completion} cmc 
            WHERE cmc.coursemoduleid = cm.id AND cmc.completionstate = 1) / 
           (SELECT COUNT(*) FROM {user_enrolments} ue 
            JOIN {enrol} e ON ue.enrolid = e.id 
            WHERE e.courseid = cm.course)) * 100, 2) AS completion_percentage";

$from = "{course_modules} cm
         JOIN {course_sections} cs ON cm.section = cs.id
         JOIN {course} c ON cm.course = c.id";

$where = "cm.visible = 1 
          AND c.id IS NOT NULL 
          AND cs.id IS NOT NULL";


// Set the SQL for the table
$table->set_sql($fields, $from, $where);
$table->define_baseurl($PAGE->url);
$table->sortable(false, 'uniqueid');

// Output the table
$table->out(10, true); // Pagination set to 10 per page

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
?>
