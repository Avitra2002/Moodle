<?php

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_login();
if (!is_siteadmin()) {
    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');
}

// Define table class for the activity/resource-wise report
class activity_resource_summary_table extends table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        // Define columns and headers for the table
        $this->define_columns(array('course_name', 'section_name', 'activity_name', 'total_students', 'students_completed', 'completion_percentage'));
        $this->define_headers(array('Course Name', 'Section', 'Activity/Resource', 'Students Enrolled', 'Students Completed', 'Completion %'));
    }
    
    // Define the activity/resource column
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
    cmc.coursemoduleid AS cm_id,
    (SELECT md.name FROM {modules} md WHERE md.id = cm.module) AS activity_name,
    (SELECT COUNT(*) FROM {user_enrolments} ue 
     JOIN {enrol} e ON ue.enrolid = e.id 
     WHERE e.courseid = cm.course) AS total_students,
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
         JOIN {course} c ON cm.course = c.id
         JOIN {course_sections} cs ON cm.section = cs.id
         LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id";

$where = "cm.visible = 1";

// Set the SQL for the table
$table->set_sql($fields, $from, $where);
$table->define_baseurl($PAGE->url);
$table->sortable(false, 'uniqueid');

// Output the table
$table->out(100, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
?>
