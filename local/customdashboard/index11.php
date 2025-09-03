<?php 
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customdashboard/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Admin Dashboard');
$PAGE->set_heading('Custom Admin Dashboard');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

global $DB, $CFG;

// 🧮 Summary Counts
$totalstudents = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname = 'Employee' AND u.deleted = 0
");

$totalteachers = $DB->count_records_sql("
    SELECT COUNT(DISTINCT u.id)
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    WHERE r.shortname IN ('Supervisor') AND u.deleted = 0
");

$totalcourses = $DB->count_records('course') - 1;

// 🎓 Certification courses
$certification_course_ids = [5, 8, 12];
$certified_users = [];

foreach ($certification_course_ids as $cert_course_id) {
    $course = get_course($cert_course_id);
    $context = context_course::instance($cert_course_id);
    $students = get_enrolled_users($context, '', 0);

    foreach ($students as $student) {
        $progress = \core_completion\progress::get_course_progress_percentage($course, $student->id);
        if ($progress === 100) {
            $certified_users[$student->id] = true;
        }
    }
}

$total_certified = count($certified_users);

// 📊 Course-wise Stats
$courses = $DB->get_records_sql("SELECT id, fullname FROM {course} WHERE id != 1");

$labels = [];
$enrolled_data = [];
$completed_data = [];
$inprogress_data = [];

foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $students = get_enrolled_users($context, '', 0);

    $total = count($students);
    $completed = 0;
    $inprogress = 0;

    foreach ($students as $student) {
        $progress = \core_completion\progress::get_course_progress_percentage($course, $student->id);
        if ($progress === 100) {
            $completed++;
        } else {
            $inprogress++;
        }
    }

    $labels[] = $course->fullname;
    $enrolled_data[] = $total;
    $completed_data[] = $completed;
    $inprogress_data[] = $inprogress;
}
?>

<!-- 🎨 STYLES -->
<style>
.dashboard-tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}
.tile {
    flex: 1;
    color: white;
    padding: 20px;
    border-radius: 10px;
    font-size: 1.2em;
    text-align: center;
    min-width: 200px;
}
.tile-students  { background-color: #007bff; }
.tile-teachers  { background-color: #28a745; }
.tile-courses   { background-color: #6f42c1; }
.tile-certified { background-color: #17a2b8; }

.graph-row {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}
.graph-row .graph {
    flex: 1 1 calc(50% - 30px);
}

ul.due-list {
    margin-top: 40px;
    padding-left: 20px;
}
ul.due-list li {
    margin-bottom: 10px;
}
ul.due-list span {
    color: #cc0000;
}
</style>

<!-- 🔢 SUMMARY TILES -->
<div class="dashboard-tiles">
    <div class="tile tile-students">
        👨‍🎓 <h2><?= $totalstudents ?></h2><p>Total Employee</p>
    </div>
    <div class="tile tile-teachers">
        👩‍🏫 <h2><?= $totalteachers ?></h2><p>Total Supervisor</p>
    </div>
    <div class="tile tile-courses">
        📚 <h2><?= $totalcourses ?></h2><p>Total Courses</p>
    </div>
    <div class="tile tile-certified">
        🎓 <h2><?= $total_certified ?></h2><p>Total Certified</p>
    </div>
</div>

<!-- 📊 GRAPHS -->
<div class="graph-row">
    <div class="graph">
        <canvas id="enrollmentChart"></canvas>
    </div>
    <div class="graph">
        <canvas id="statusChart"></canvas>
    </div>
</div>

<!-- 📈 CHARTS SCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const enrolled = <?= json_encode($enrolled_data) ?>;
const completed = <?= json_encode($completed_data) ?>;
const inProgress = <?= json_encode($inprogress_data) ?>;

// Bar chart: Enrollments
new Chart(document.getElementById('enrollmentChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Enrolled Employees',
            data: enrolled,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Enrollment per Course' }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Doughnut chart: Completion vs In Progress
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'In Progress'],
        datasets: [{
            label: 'Course Completion Overview',
            data: [
                completed.reduce((a, b) => a + b, 0),
                inProgress.reduce((a, b) => a + b, 0)
            ],
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 205, 86, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Overall Completion Status'
            },
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<!-- 🕒 UPCOMING DUE DATES -->
<h3 style="margin-top: 60px;">🕒 Top 5 Upcoming Activity Due Dates</h3>

<?php
$modules = ['assign' => 'duedate', 'quiz' => 'timeclose', 'forum' => 'duedate'];
$upcoming = [];

foreach ($modules as $modname => $timeduefield) {
    $modtable = "{" . $modname . "}";
    if (!$DB->get_manager()->table_exists($modtable)) continue;

    $sql = "
        SELECT cm.id as cmid, c.id as courseid, c.fullname as coursename, m.name as modname, m.$timeduefield as duetime, '$modname' as modtype
        FROM $modtable m
        JOIN {course_modules} cm ON cm.instance = m.id AND cm.module = (SELECT id FROM {modules} WHERE name = :mod)
        JOIN {course} c ON c.id = cm.course
        WHERE m.$timeduefield > :now AND cm.visible = 1 AND c.visible = 1
    ";

    $params = ['mod' => $modname, 'now' => time()];
    $records = $DB->get_records_sql($sql, $params);

    foreach ($records as $rec) {
        $upcoming[] = (object)[
            'courseid' => $rec->courseid,
            'coursename' => $rec->coursename,
            'modname' => $rec->modname,
            'modtype' => $rec->modtype,
            'duetime' => $rec->duetime,
            'cmid' => $rec->cmid
        ];
    }
}

usort($upcoming, function($a, $b) {
    return $a->duetime - $b->duetime;
});

$upcoming = array_slice($upcoming, 0, 5);

if (count($upcoming) > 0): ?>
    <ul class="due-list">
        <?php foreach ($upcoming as $item): 
            $link = new moodle_url('/mod/' . $item->modtype . '/view.php', ['id' => $item->cmid]);
            ?>
            <li>
                <strong><?= format_string($item->coursename) ?>:</strong> 
                <a href="<?= $link ?>" target="_blank"><?= format_string($item->modname) ?></a>
                (<?= ucfirst($item->modtype) ?>) –
                <span><?= date('M d, Y H:i', $item->duetime) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No upcoming due dates.</p>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
