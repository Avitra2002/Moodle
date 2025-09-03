<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:viewreports', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$PAGE->set_url('/completion_rate_report.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Course Completion Rate Report');
$PAGE->set_heading('Course Completion Rate Report');

$download = optional_param('download', 0, PARAM_BOOL);

// SQL to fetch completion stats
$sql = "
SELECT 
    c.id,
    c.fullname AS coursename,
    COUNT(DISTINCT ue.userid) AS enrolled,
    COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN cc.userid END) AS completed,
    COUNT(DISTINCT ue.userid) - COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN cc.userid END) AS incomplete,
    ROUND(
        (COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN cc.userid END) * 100.0) / 
        COUNT(DISTINCT ue.userid), 2
    ) AS completionrate
FROM {course} c
JOIN {enrol} e ON e.courseid = c.id
JOIN {user_enrolments} ue ON ue.enrolid = e.id
LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
WHERE c.id > 1 AND c.visible = 1
GROUP BY c.id, c.fullname
ORDER BY completionrate DESC
";

$data = $DB->get_records_sql($sql);

// CSV download
if ($download && !empty($data)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="completion_rate_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Course Name', 'Enrolled', 'Completed', 'Incomplete', 'Completion Rate (%)']);
    foreach ($data as $row) {
        fputcsv($out, [
            $row->coursename,
            $row->enrolled,
            $row->completed,
            $row->incomplete,
            $row->completionrate
        ]);
    }
    fclose($out);
    exit;
}

// HTML output
echo $OUTPUT->header();
//echo $OUTPUT->heading('Course Completion Rate Report');

echo '<div class="box py-3 px-4" style="background:#f9f9f9; border:1px solid #ccc; border-radius:8px;">';
echo '<form method="get" class="form-inline" style="margin-bottom: 15px;">';
echo '<input type="hidden" name="download" value="1">';
echo '<button type="submit" class="btn btn-success"> Download CSV</button>';
echo '</form>';
echo '</div>';

if ($data) {
    echo '<div class="table-responsive mt-3">';
    echo '<table class="table table-striped table-bordered">';
    echo '<thead><tr>
        <th>Course Name</th>
        <th>Total Enrolled</th>
        <th>Completed</th>
        <th>Incomplete</th>
        <th>Completion Rate (%)</th>
    </tr></thead><tbody>';

    foreach ($data as $row) {
        echo '<tr>
            <td>' . format_string($row->coursename) . '</td>
            <td>' . $row->enrolled . '</td>
            <td>' . $row->completed . '</td>
            <td>' . $row->incomplete . '</td>
            <td>' . $row->completionrate . '%</td>
        </tr>';
    }

    echo '</tbody></table></div>';
} else {
    echo '<p>No course data found.</p>';
}

echo $OUTPUT->footer();
