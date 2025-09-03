<?php
require_once('../../config.php');
require_login();

if (isguestuser()) {
    redirect(new moodle_url('/'));
}

// Define the page
$PAGE->set_url('/local/activity_completion_report.php');
$PAGE->set_title('Activity Completion Report');
$PAGE->set_heading('Activity Completion Report');
$PAGE->set_pagelayout('standard');

// Fetch courses
$courses = $DB->get_records_sql_menu("SELECT id, fullname FROM {course} WHERE visible = 1 AND id != 1 ORDER BY fullname ASC");

// Display the form with the course dropdown
echo $OUTPUT->header();
?>
<form id="course-form" action="#" method="get">
    <div class="form-group">
        <label for="courseid"><?php echo get_string('select_course', 'local_completion_report'); ?></label>
        <select id="courseid" name="courseid" class="form-control" onchange="redirectToReport()">
            <option value=""><?php echo get_string('select_course', 'local_completion_report'); ?></option>
            <?php
            foreach ($courses as $id => $fullname) {
                echo html_writer::tag('option', s($fullname), ['value' => $id]);
            }
            ?>
        </select>
    </div>
</form>

<script type="text/javascript">
    function redirectToReport() {
        var courseId = document.getElementById('courseid').value;
        if (courseId) {
            window.location.href = '<?php echo $CFG->wwwroot; ?>/report/progress/index.php?course=' + courseId;
        }
    }
</script>
<?php
echo $OUTPUT->footer();
?>
