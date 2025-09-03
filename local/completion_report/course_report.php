<?php

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_login();
$systemcontext = context_system::instance();
if (!is_siteadmin() && !user_has_role_assignment($USER->id, 1, $systemcontext->id)) {
    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');
}

// Define table class for the course-wise report
class course_summary_table extends table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        // Define columns and headers for the table
        $this->define_columns(array('course_name', 'total_students', 'students_completed', 'completion_percentage'));
        $this->define_headers(array('Course Name', 'Students Enrolled', 'Students Completed', 'Completion %'));
    }
    
    // Define the course name column
    public function col_course_name($values) {
        return $values->course_name;
    }
}

// Set up the page and table
$PAGE->set_url('/local/completion_report/course_report.php');
$PAGE->set_context(context_system::instance());
$download = optional_param('download', '', PARAM_ALPHA);

$filename = "course_summary";
$table = new course_summary_table($filename);

$table->is_downloading($download, $filename, 'Course Summary');

if (!$table->is_downloading()) {
    $PAGE->set_title('Course Summary');
    $PAGE->set_heading('Course Summary');
    echo $OUTPUT->header();
}

// SQL query to get course details along with enrollment and completion data
$fields = "
    c.id AS course_id,
    c.fullname AS course_name,
    (SELECT COUNT(DISTINCT ue.userid)
    FROM {user_enrolments} ue
    JOIN {enrol} e ON ue.enrolid = e.id
    JOIN {role_assignments} ra ON ue.userid = ra.userid
    WHERE e.courseid = c.id AND ra.roleid = 5
    ) AS total_students,
    (SELECT COUNT(DISTINCT cmc.userid) 
     FROM {course_modules_completion} cmc 
     JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
     WHERE cm.course = c.id AND cmc.completionstate = 1) AS students_completed,
    ROUND(((SELECT COUNT(DISTINCT cmc.userid) 
           FROM {course_modules_completion} cmc 
           JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
           WHERE cm.course = c.id AND cmc.completionstate = 1) / 
          (SELECT COUNT(*) FROM {user_enrolments} ue 
           JOIN {enrol} e ON ue.enrolid = e.id 
           WHERE e.courseid = c.id)) * 100, 2) AS completion_percentage";

$from = "{course} c";

$where = "c.visible = 1 AND c.id != 1";  // Exclude site home course (id = 1)

// Set the SQL for the table
$table->set_sql($fields, $from, $where);
$table->define_baseurl($PAGE->url);
$table->sortable(false, 'uniqueid');

// Output the table with pagination set to 10 rows per page
$table->out(10, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
?>
