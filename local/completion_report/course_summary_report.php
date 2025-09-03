<?php

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_login();
if (!is_siteadmin()) {
    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');
}
class course_summary_table extends table_sql {
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->define_columns(array('semestre', 'cours_original', 'total_no_student', 'how_many_completed', 'completion_percentage'));
        $this->define_headers(array('Semester', 'Course Original', 'Students Enrolled', 'Students Completed', 'Completion %'));
    }
}

// Set up the page
$PAGE->set_url('/local/completion_report/course_summary_report.php');
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

// SQL query fields, from and where clauses
$fields = "d.course_original AS cours_original,
           (SELECT name FROM {course_sections} WHERE id = d.section_id) AS semestre,
           d.cm_id,
           (SELECT COUNT(*) FROM {user_enrolments} ue 
            JOIN {enrol} e ON ue.enrolid = e.id 
            WHERE e.courseid = 2) AS total_no_student,
           (SELECT COUNT(*) FROM {course_modules_completion} cmc 
            WHERE cmc.coursemoduleid IN 
                  (SELECT cm_id FROM {course_division} WHERE module_id = d.module_id) 
            AND cmc.completionstate = 1) AS how_many_completed,
           ROUND(((SELECT COUNT(*) FROM {course_modules_completion} cmc 
                   WHERE cmc.coursemoduleid IN 
                  (SELECT cm_id FROM {course_division} WHERE module_id = d.module_id) 
                   AND cmc.completionstate = 1) / 
                  (SELECT COUNT(*) FROM {user_enrolments} ue 
                   JOIN {enrol} e ON ue.enrolid = e.id 
                   WHERE e.courseid = 2)) * 100, 2) AS completion_percentage";

$from = "{course_division} d";
$where = "d.section_id IN (4,5,6,7,8,9) AND d.course_original IS NOT NULL";

$table->set_sql($fields, $from, $where);
$table->define_baseurl($PAGE->url);
$table->sortable(false, 'uniqueid');;

// Render table
$table->out(200, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
?>
