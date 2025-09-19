<?php
require_once('../../config.php');
require_login();

if (!has_capability('moodle/site:config', context_system::instance()) &&
    !has_capability('moodle/site:manageblocks', context_system::instance())) {
    throw new required_capability_exception(
        context_system::instance(),
        'moodle/site:config',
        'nopermissions',
        ''
    );
}

$PAGE->set_url(new moodle_url('/local/customdashboard/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Admin Dashboard');
$PAGE->set_heading('Custom Admin Dashboard');
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();

global $DB;

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
    WHERE r.shortname IN ('Supervisor','manager') AND u.deleted = 0
");

$totalcourses = $DB->count_records('course') - 1;

// 🎓 Certified users
$courses = $DB->get_records_sql("SELECT id FROM {course} WHERE id != 1");
$certified_users = [];

foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $students = get_enrolled_users($context, 'moodle/course:view', 0, 'u.id');
    foreach ($students as $student) {
        $progress = \core_completion\progress::get_course_progress_percentage($course, $student->id);
        if ($progress === 100) {
            $certified_users[$student->id] = true;
        }
    }
}
$total_certified = count($certified_users);
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

.upcoming-due-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 30px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}
.upcoming-due-table th {
    background-color: #007bff;
    color: #fff;
    padding: 12px;
    text-align: left;
}
.upcoming-due-table td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}
.upcoming-due-table tr:hover {
    background-color: #f5f5f5;
}
.due-date {
    color: #cc0000;
    font-weight: bold;
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

<?php
// 🕒 Upcoming due dates
$modules = ['assign' => 'duedate', 'quiz' => 'timeclose', 'forum' => 'duedate'];
$upcoming = [];

foreach ($modules as $modname => $timeduefield) {
    $modtable = $DB->get_prefix() . $modname;
    if (!$DB->get_manager()->table_exists($modname)) continue;

    $sql = "
        SELECT 
            cm.id AS cmid,
            c.id AS courseid,
            c.fullname AS coursename,
            m.name AS modname,
            m.$timeduefield AS duetime,
            :mod1 AS modtype
        FROM {$modtable} m
        JOIN {course_modules} cm ON cm.instance = m.id
        JOIN {modules} md ON md.id = cm.module AND md.name = :mod
        JOIN {course} c ON c.id = cm.course
        WHERE m.$timeduefield IS NOT NULL 
          AND m.$timeduefield > :now 
          AND cm.visible = 1 
          AND c.visible = 1
    ";

    $params = [
        'mod' => $modname,
        'mod1' => $modname,
        'now' => time()
    ];

    $records = $DB->get_records_sql($sql, $params);
    foreach ($records as $rec) {
        $upcoming[] = (object)[
            'courseid'   => $rec->courseid,
            'coursename' => $rec->coursename,
            'modname'    => $rec->modname,
            'modtype'    => $rec->modtype,
            'duetime'    => $rec->duetime,
            'cmid'       => $rec->cmid
        ];
    }
}


usort($upcoming, fn($a, $b) => $a->duetime - $b->duetime);
$upcoming = array_slice($upcoming, 0, 5);
?>

<!-- 📊 Reports Section -->
<h3 style="margin-top:40px;">📑 Quick Access Reports</h3>

<div class="reports-grid">
    <!-- Training History -->
    <div class="report-card">
        <h4>🎓 Training History</h4>
        <ul>
            <li><a href="<?= $CFG->wwwroot ?>/local/completion_report/report.php">Training History Report</a></li>
            <li><a href="<?= $CFG->wwwroot ?>/local/completion_report/import.php">Training History Import</a></li>
        </ul>
    </div>

    <!-- Progress Reports -->
    <div class="report-card">
        <h4>📈 Progress Reports</h4>
        <ul>
            <li><a href="<?= $CFG->wwwroot ?>/local/customdashboard/course_completion.php">User Progress Report</a></li>
            <li><a href="<?= $CFG->wwwroot ?>/local/completion_report/course_report.php">Course Completion Report</a></li>
        </ul>
    </div>

    <!-- To Do / Review -->
    <div class="report-card">
        <h4>📝 To Do / Review</h4>
        <ul>
            <li><a href="<?= $CFG->wwwroot ?>/local/approval/approve.php">CVs to Review</a></li>
            <li><a href="<?= $CFG->wwwroot ?>/local/jdapproval/approve.php">JDs to Review</a></li>
        </ul>
    </div>
</div>

<style>
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-top: 20px;
    margin-bottom: 40px;
}

.report-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.report-card h4 {
    margin-bottom: 12px;
    color: #007bff;
}

.report-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.report-card li {
    margin: 8px 0;
}

.report-card a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
    transition: color 0.2s;
}

.report-card a:hover {
    color: #007bff;
}
</style>


<!-- 📋 Table Output -->
<h3>🕒 Top 5 Upcoming Activity Due Dates</h3>
<?php if (count($upcoming) > 0): ?>
    <table class="upcoming-due-table">
        <thead>
            <tr>
                <th>Course</th>
                <th>Activity</th>
                <th>Type</th>
                <th>Due Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming as $item): 
                $link = new moodle_url('/mod/' . $item->modtype . '/view.php', ['id' => $item->cmid]);
            ?>
            <tr>
                <td><?= format_string($item->coursename) ?></td>
                <td><?= format_string($item->modname) ?></td>
                <td><?= ucfirst($item->modtype) ?></td>
                <td class="due-date"><?= date('M d, Y H:i', $item->duetime) ?></td>
                <td><a href="<?= $link ?>" class="btn btn-sm btn-primary" target="_blank">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No upcoming due dates found.</p>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
