<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:viewreports', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$PAGE->set_url('/custom_user_progress_report.php');
$PAGE->set_context(context_system::instance());
//$PAGE->set_title('User Progress Report');
$PAGE->set_heading('User Progress Report');

// Handle course filter.
$courseid = optional_param('courseid', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_TEXT);
$download = optional_param('download', 0, PARAM_BOOL);

// Get courses for dropdown.
$courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname', 'id, fullname');
$courses = [0 => 'All Courses'] + $courses;

// Build SQL condition.
$params = [];
$where = "c.visible = 1 AND u.deleted = 0";
if ($courseid) {
    $where .= " AND c.id = :courseid";
    $params['courseid'] = $courseid;
}
$sql = "SELECT 
            u.firstname, u.lastname, u.email, 
            c.fullname AS coursename,
            ROUND(gg.finalgrade, 2) AS grade,
            CASE 
                WHEN cc.timecompleted IS NOT NULL THEN 'Completed'
                ELSE 'In Progress'
            END AS status,
            FROM_UNIXTIME(cc.timecompleted) AS completiondate
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
        LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
        WHERE $where
        ORDER BY u.lastname, c.fullname";

$data = $DB->get_records_sql($sql, $params);

// CSV download
if ($download && !empty($data)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_progress_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Full Name', 'Email', 'Course Name', 'Grade', 'Status', 'Completion Date']);
    foreach ($data as $row) {
        fputcsv($out, [
            $row->firstname . ' ' . $row->lastname,
            $row->email,
            $row->coursename,
            $row->grade,
            $row->status,
            $row->completiondate
        ]);
    }
    fclose($out);
    exit;
}

// Output HTML
echo $OUTPUT->header();
//echo $OUTPUT->heading('User Progress Report');
echo '<br>';
echo '<div class="user-progress-filter box py-3 px-4 mb-4" style="background:#f9f9f9; border:1px solid #ccc; border-radius:8px;">';
echo '<form method="GET" class="form-inline" style="display:flex; gap: 10px; align-items:center; flex-wrap: wrap;">';

echo '<div class="form-group">';
echo '<label for="courseid" class="mr-2"><strong>Course:</strong></label>';
echo '<select name="courseid" id="courseid" class="custom-select">';
foreach ($courses as $id => $name) {
    $selected = ($id == $courseid) ? ' selected' : '';
    echo '<option value="'.$id.'"'.$selected.'>'.$name.'</option>';
}
echo '</select>';
echo '</div>';

echo '<input type="submit" class="btn btn-secondary ml-2" value="Filter">';

echo '<a href="?courseid='.$courseid.'&download=1" class="btn btn-success ml-2"> Download CSV</a>';

echo '</form>';
echo '</div>';


if ($data) {
    echo '<table class="generaltable boxaligncenter" border="1" cellpadding="5" cellspacing="0">';
    echo '<tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Course</th>
            <th>Grade (%)</th>
            <th>Status</th>
            <th>Completion Date</th>
          </tr>';
    foreach ($data as $row) {
        echo '<tr>
                <td>'.fullname($row).'</td>
                <td>'.$row->email.'</td>
                <td>'.$row->coursename.'</td>
                <td>'.$row->grade.'</td>
                <td>'.$row->status.'</td>
                <td>'.$row->completiondate.'</td>
              </tr>';
    }
    echo '</table>';
} else {
    echo '<p>No records found.</p>';
}

echo $OUTPUT->footer();
