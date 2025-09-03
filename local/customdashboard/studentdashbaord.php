<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

require_login();

$PAGE->set_url(new moodle_url('/local/customdashboard/studentdashbaord.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Student Dashboard');
$PAGE->set_heading('Welcome, ' . fullname($USER));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

global $DB, $USER;

// 🎓 My Courses
$enrolled_courses = enrol_get_users_courses($USER->id, true, '*');

$labels = [];
$progress_data = [];
$grades_data = [];
$certified_courses = [];
$upcoming_activities = [];

foreach ($enrolled_courses as $course) {
    $labels[] = format_string($course->fullname);

    // Course Progress
    $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
    $progress_data[] = is_numeric($progress) ? round($progress) : 0;

    // Grades
    $grade_item = grade_get_course_grade($USER->id, $course->id);
    $grades_data[] = $grade_item && isset($grade_item->finalgrade) ? round($grade_item->finalgrade) : 0;

    // Certification
    if ($progress === 100) {
        $certified_courses[] = $course->fullname;
    }

    // Upcoming Activities
    $mods = ['assign' => 'duedate', 'quiz' => 'timeclose'];
    foreach ($mods as $modname => $timeduefield) {
        if (!$DB->get_manager()->table_exists($modname)) continue;

        $sql = "
            SELECT m.$timeduefield AS duetime, cm.id as cmid, c.fullname, cm.module
            FROM {{$modname}} m
            JOIN {course_modules} cm ON cm.instance = m.id
            JOIN {modules} md ON md.id = cm.module AND md.name = :modname
            JOIN {course} c ON c.id = cm.course
            WHERE c.id = :cid AND cm.visible = 1 AND m.$timeduefield > :now
        ";

        $params = [
            'modname' => $modname,
            'cid' => $course->id,
            'now' => time()
        ];

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $rec) {
            $upcoming_activities[] = (object)[
                'coursename' => $rec->fullname,
                'modtype' => $modname,
                'modname' => ucfirst($modname),
                'duetime' => $rec->duetime,
                'cmid' => $rec->cmid
            ];
        }
    }
}

$total_courses = count($enrolled_courses);
$completed_courses = count($certified_courses);
$avg_progress = $total_courses > 0 ? round(array_sum($progress_data) / $total_courses) : 0;
$cert_count = $completed_courses; // Assuming certified means completed
?>
<div class="dashboard-summary">
    <div class="tile tile-courses">
        🎓 <strong>Enrolled Courses</strong>
        <div class="tile-number"><?= $total_courses ?></div>
    </div>
    <div class="tile tile-completed">
        ✅ <strong>Completed Courses</strong>
        <div class="tile-number"><?= $completed_courses ?></div>
    </div>
    <div class="tile tile-progress">
        📈 <strong>Avg. Progress</strong>
        <div class="tile-number"><?= $avg_progress ?>%</div>
    </div>
    <div class="tile tile-certs">
        🏅 <strong>Certifications</strong>
        <div class="tile-number"><?= $cert_count ?></div>
    </div>
</div>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}
.chart-box {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.1);
}
.upcoming-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.upcoming-table th, .upcoming-table td {
    padding: 10px;
    border: 1px solid #ddd;
}
.upcoming-table th {
    background-color: #007bff;
    color: #fff;
}
.badge {
    padding: 5px 10px;
    border-radius: 5px;
    color: #fff;
}
.btn-link {
    color: #007bff;
    text-decoration: underline;
}

.certification-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
}

.cert-card {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 15px 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    flex: 1 1 calc(50% - 15px);
}

.cert-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background-color: #e9f7ef;
}

.cert-icon {
    font-size: 32px;
    margin-right: 15px;
    color: #17a2b8;
}

.cert-details {
    flex-grow: 1;
}

.cert-title {
    font-size: 16px;
    font-weight: 600;
    color: #343a40;
}

.cert-badge {
    background-color: #28a745;
    color: white;
    padding: 3px 10px;
    font-size: 12px;
    border-radius: 20px;
    margin-top: 5px;
    display: inline-block;
}

.dashboard-summary {
    display: flex;
    gap: 20px;
    margin: 20px 0 40px;
    flex-wrap: wrap;
}

.tile {
    flex: 1 1 200px;
    padding: 20px;
    color: #fff;
    border-radius: 12px;
    font-size: 18px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.tile-number {
    font-size: 28px;
    font-weight: bold;
    margin-top: 10px;
}

/* Different background colors */
.tile-courses {
    background: #007bff;
}
.tile-completed {
    background: #28a745;
}
.tile-progress {
    background: #ffc107;
    color: #333;
}
.tile-certs {
    background: #6f42c1;
}

</style>

<div class="dashboard-grid">
    <div class="chart-box">
        <h3>📘 Course Progress</h3>
        <canvas id="progressChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>📈 My Grades</h3>
        <canvas id="gradesChart"></canvas>
    </div>
</div>

<?php if (!empty($certified_courses)): ?> 
    <div class="chart-box">
        <h3>🏅 Certifications Achieved</h3>
        <div class="certification-grid">
            <?php foreach ($certified_courses as $cert): ?>
                <div class="cert-card">
                    <div class="cert-icon">🎓</div>
                    <div class="cert-details">
                        <div class="cert-title"><?= format_string($cert) ?></div>
                        <span class="cert-badge">Completed</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<br>

<h3 class="mb-3">🗓️ Upcoming Activities</h3>

<?php if (!empty($upcoming_activities)): ?>
    <table class="upcoming-table">
        <thead>
            <tr>
                <th>📘 Course</th>
                <th>📄 Activity</th>
                <th>📌 Type</th>
                <th>⏰ Due Date</th>
                <th>🔗 Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming_activities as $item): 
                $link = new moodle_url('/mod/' . $item->modtype . '/view.php', ['id' => $item->cmid]);
                $badgeColor = match($item->modtype) {
                    'assign' => '#007bff',
                    'quiz'   => '#28a745',
                    default => '#6c757d'
                };
            ?>
                <tr>
                    <td><?= format_string($item->coursename) ?></td>
                    <td><?= format_string($item->modname) ?></td>
                    <td><span class="badge" style="background-color: <?= $badgeColor ?>;"><?= ucfirst($item->modtype) ?></span></td>
                    <td><?= date('M d, Y - H:i', $item->duetime) ?></td>
                    <td><a href="<?= $link ?>" class="btn-link" target="_blank">Open</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="no-data">No upcoming activities found.</p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const progressData = <?= json_encode($progress_data) ?>;
const gradesData = <?= json_encode($grades_data) ?>;

new Chart(document.getElementById('progressChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Progress (%)',
            data: progressData,
            backgroundColor: '#36a2eb'
        }]
    },
    options: {
        plugins: { title: { display: true, text: 'Progress in Enrolled Courses' } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});

new Chart(document.getElementById('gradesChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Grade (%)',
            data: gradesData,
            backgroundColor: '#4bc0c0'
        }]
    },
    options: {
        plugins: { title: { display: true, text: 'Grades per Course' } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
</script>

<?php

function grade_get_course_grade($userid, $courseid) {
    global $DB;
    return $DB->get_record_sql("
        SELECT gg.finalgrade
        FROM {grade_items} gi
        JOIN {grade_grades} gg ON gi.id = gg.itemid
        WHERE gi.courseid = :courseid
          AND gi.itemtype = 'course'
          AND gg.userid = :userid
    ", ['courseid' => $courseid, 'userid' => $userid]);
}

echo $OUTPUT->footer();


?>
