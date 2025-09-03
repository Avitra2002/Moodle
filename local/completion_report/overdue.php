<?php 
require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('report/log:view', $context);

$now = time();
$search = optional_param('search', '', PARAM_RAW);

$overdue = [];

$sqlassign = "SELECT c.fullname AS course, a.name AS activity, a.duedate, u.firstname, u.lastname, u.username FROM {assign} a JOIN {course} c ON c.id = a.course JOIN {assign_submission} s ON s.assignment = a.id JOIN {user} u ON u.id = s.userid WHERE a.duedate < ? AND s.status <> 'submitted'";
$paramsassign = [$now];
$assignments = $DB->get_records_sql($sqlassign, $paramsassign);

foreach ($assignments as $a) {
    $overdue[] = (object)['type' => 'Assignment', 'course' => $a->course, 'activity' => $a->activity, 'duedate' => date('Y-m-d H:i', $a->duedate), 'user' => $a->username . ' (' . $a->firstname . ' ' . $a->lastname . ')'];
}

$sqlquiz = "SELECT c.fullname AS course, q.name AS activity, q.timeclose AS duedate, u.firstname, u.lastname, u.username FROM {quiz} q JOIN {course} c ON c.id = q.course JOIN {quiz_attempts} qa ON qa.quiz = q.id JOIN {user} u ON u.id = qa.userid WHERE q.timeclose < ? AND qa.state <> 'finished'";
$paramsquiz = [$now];
$quizzes = $DB->get_records_sql($sqlquiz, $paramsquiz);

foreach ($quizzes as $q) {
    $overdue[] = (object)['type' => 'Quiz', 'course' => $q->course, 'activity' => $q->activity, 'duedate' => date('Y-m-d H:i', $q->duedate), 'user' => $q->username . ' (' . $q->firstname . ' ' . $q->lastname . ')'];
}

if (optional_param('download', '', PARAM_TEXT) === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="overdue_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Type', 'Course', 'Activity', 'Due Date', 'User']);
    foreach ($overdue as $item) {
        if ($search && stripos($item->course . ' ' . $item->activity . ' ' . $item->user, $search) === false) {
            continue;
        }
        fputcsv($output, [$item->type, $item->course, $item->activity, $item->duedate, $item->user]);
    }
    fclose($output);
    exit;
}

echo $OUTPUT->header();
echo '<br><br><h2>Overdue Assignments and Quizzes</h2><br><br>';
echo '<form method="get">';
echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="Search...">';
echo '<button type="submit">Search</button> <a href="?download=csv&search=' . urlencode($search) . '">Download CSV</a>';
echo '</form><br><br>';
echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">';
echo '<tr style="background:#f2f2f2;"><th>Type</th><th>Course</th><th>Activity</th><th>Due Date</th><th>Created User</th></tr>';
foreach ($overdue as $item) {
    if ($search && stripos($item->course . ' ' . $item->activity . ' ' . $item->user, $search) === false) {
        continue;
    }
    echo '<tr>';
    echo '<td>' . $item->type . '</td>';
    echo '<td>' . htmlspecialchars($item->course) . '</td>';
    echo '<td>' . htmlspecialchars($item->activity) . '</td>';
    echo '<td>' . $item->duedate . '</td>';
    echo '<td>' . htmlspecialchars($item->user) . '</td>';
    echo '</tr>';
}
echo '</table>';
echo $OUTPUT->footer();