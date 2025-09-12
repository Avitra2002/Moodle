<?php
// local/customdashboard/course_completion.php
require_once('../../config.php');
require_login();
require_capability('moodle/site:viewreports', context_system::instance());

global $DB, $OUTPUT, $PAGE, $USER;

$PAGE->set_url('/local/customdashboard/course_completion.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading('Progress Tracking & Reporting');

// Handle parameters
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$viewtype = optional_param('viewtype', 'summary', PARAM_TEXT); // summary or detailed
$download = optional_param('download', 0, PARAM_BOOL);


function get_supervised_cohorts($userid) {
    global $DB;
    
    // Site administrators see ALL cohorts
    if (is_siteadmin($userid)) {
        $all_cohorts = $DB->get_records('cohort', null, 'name', 'id, name');
        return array_keys($all_cohorts);
    }
    
    // Check for SPECIFIC cohort assignments FIRST (highest priority)
    // anyone with cohort-specific assignments, regardless of other roles
    
    // Check mdl_tool_cohortroles for specific cohort assignments
    $cohort_assignments = $DB->get_records_sql("
        SELECT DISTINCT tcr.cohortid as id
        FROM {tool_cohortroles} tcr
        JOIN {role} r ON r.id = tcr.roleid 
        WHERE tcr.userid = :userid
        AND r.shortname IN ('supervisor', 'manager')
    ", ['userid' => $userid]);
    
    if (!empty($cohort_assignments)) {
        // Has specific cohort assignments - return only those, ignore system-level roles
        return array_keys($cohort_assignments);
    }
    
    // Alternative check via role_assignments with tool_cohortroles component
    $role_assignment_cohorts = $DB->get_records_sql("
        SELECT DISTINCT ra.itemid as id
        FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = :userid
        AND r.shortname IN ('supervisor', 'manager')
        AND ctx.contextlevel = 10
        AND ra.component = 'tool_cohortroles'
        AND ra.itemid > 0
    ", ['userid' => $userid]);
    
    if (!empty($role_assignment_cohorts)) {
        // Has specific cohort assignments - return only those
        return array_keys($role_assignment_cohorts);
    }
    
    // Only if NO specific cohort assignments, check for system manager access
    $is_system_manager = $DB->record_exists_sql("
        SELECT 1 FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = :userid 
        AND r.shortname = 'manager' 
        AND ctx.contextlevel = 10
        AND ra.component IS NULL
    ", ['userid' => $userid]);
    
    if ($is_system_manager) {
        // Pure system manager with no specific cohort assignments sees all cohorts
        $all_cohorts = $DB->get_records('cohort', null, 'name', 'id, name');
        return array_keys($all_cohorts);
    }
    
    return [];
}

function get_cohort_progress_summary($supervised_cohort_ids, $cohortid = 0, $courseid = 0) {
    global $DB;
    
    if (empty($supervised_cohort_ids)) {
        return [];
    }
    
    // Filter by specific cohort if selected
    $cohort_filter = '';
    $params = [];
    
    if ($cohortid > 0 && in_array($cohortid, $supervised_cohort_ids)) {
        $cohort_filter = "AND coh.id = :selected_cohort";
        $params['selected_cohort'] = $cohortid;
    } else {
        list($cohort_sql, $cohort_params) = $DB->get_in_or_equal($supervised_cohort_ids, SQL_PARAMS_NAMED, 'sup_cohort');
        $cohort_filter = "AND coh.id $cohort_sql";
        $params = array_merge($params, $cohort_params);
    }
    
    // Filter by specific course if selected
    if ($courseid > 0) {
        $cohort_filter .= " AND c.id = :selected_course";
        $params['selected_course'] = $courseid;
    }
    
    // Get basic cohort progress summary first
    $sql = "
        SELECT 
            coh.id,
            coh.name as cohort_name,
            COUNT(DISTINCT cm.userid) as total_members,
            COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN CONCAT(ue.userid, '-', c.id) END) as completed_enrollments,
            COUNT(DISTINCT CASE WHEN ue.userid IS NOT NULL THEN CONCAT(ue.userid, '-', c.id) END) as total_enrollments,
            COUNT(DISTINCT c.id) as total_courses
        FROM {cohort} coh
        JOIN {cohort_members} cm ON cm.cohortid = coh.id
        JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid AND c.visible = 1
        LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id AND cc.timecompleted IS NOT NULL
        WHERE 1=1 $cohort_filter
        GROUP BY coh.id, coh.name
        ORDER BY coh.name
    ";
    
    $results = $DB->get_records_sql($sql, $params);
    
    // Get enrollment breakdown for each cohort
    foreach ($results as &$cohort) {
        $breakdown_params = ['breakdown_cohort' => $cohort->id];
        if ($courseid > 0) {
            $breakdown_params['selected_course'] = $courseid;
        }
        
        $breakdown_sql = "
            SELECT 
                e.enrol,
                COUNT(DISTINCT CASE WHEN ue.userid IS NOT NULL THEN CONCAT(ue.userid, '-', c2.id) END) as total_enrollments,
                COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN CONCAT(ue.userid, '-', c2.id) END) as completed_enrollments
            FROM {cohort} coh2
            JOIN {cohort_members} cm ON cm.cohortid = coh2.id
            JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c2 ON c2.id = e.courseid AND c2.visible = 1
            LEFT JOIN {course_completions} cc ON cc.course = c2.id AND cc.userid = u.id AND cc.timecompleted IS NOT NULL
            WHERE coh2.id = :breakdown_cohort" . ($courseid > 0 ? " AND c2.id = :selected_course" : "") . "
            GROUP BY e.enrol
        ";
        
        $breakdowns = $DB->get_records_sql($breakdown_sql, $breakdown_params);
        
        // Initialize enrollment type data
        $cohort->cohort_total_enrollments = 0;
        $cohort->cohort_completed_enrollments = 0;
        $cohort->cohortsync_total_enrollments = 0;
        $cohort->cohortsync_completed_enrollments = 0;
        $cohort->manual_total_enrollments = 0;
        $cohort->manual_completed_enrollments = 0;
        $cohort->self_total_enrollments = 0;
        $cohort->self_completed_enrollments = 0;
        $cohort->other_total_enrollments = 0;
        $cohort->other_completed_enrollments = 0;
        
        // Populate enrollment type data
        foreach ($breakdowns as $breakdown) {
            switch ($breakdown->enrol) {
                case 'cohort':
                    $cohort->cohort_total_enrollments = $breakdown->total_enrollments;
                    $cohort->cohort_completed_enrollments = $breakdown->completed_enrollments;
                    break;
                case 'cohortsync':
                    $cohort->cohortsync_total_enrollments = $breakdown->total_enrollments;
                    $cohort->cohortsync_completed_enrollments = $breakdown->completed_enrollments;
                    break;
                case 'manual':
                    $cohort->manual_total_enrollments = $breakdown->total_enrollments;
                    $cohort->manual_completed_enrollments = $breakdown->completed_enrollments;
                    break;
                case 'self':
                    $cohort->self_total_enrollments = $breakdown->total_enrollments;
                    $cohort->self_completed_enrollments = $breakdown->completed_enrollments;
                    break;
                default:
                    $cohort->other_total_enrollments += $breakdown->total_enrollments;
                    $cohort->other_completed_enrollments += $breakdown->completed_enrollments;
                    break;
            }
        }
    }
    
    return $results;
}


function get_detailed_progress($supervised_cohort_ids, $cohortid = 0, $courseid = 0) {
    global $DB;
    
    if (empty($supervised_cohort_ids)) {
        return [];
    }
    
    $cohort_filter = '';
    $params = [];
    
    if ($cohortid > 0 && in_array($cohortid, $supervised_cohort_ids)) {
        $cohort_filter = "AND coh.id = :selected_cohort";
        $params['selected_cohort'] = $cohortid;
    } else {
        list($cohort_sql, $cohort_params) = $DB->get_in_or_equal($supervised_cohort_ids, SQL_PARAMS_NAMED, 'sup_cohort');
        $cohort_filter = "AND coh.id $cohort_sql";
        $params = array_merge($params, $cohort_params);
    }
    
    // Filter by specific course if selected
    if ($courseid > 0) {
        $cohort_filter .= " AND c.id = :selected_course";
        $params['selected_course'] = $courseid;
    }
    /// Only show priority enrolment - no duplicates
    // $sql = "
    //     SELECT * FROM (
    //         SELECT DISTINCT
    //             CONCAT(u.id, '-', c.id, '-', ue.id) as unique_key,
    //             u.id as userid,
    //             u.firstname, 
    //             u.lastname, 
    //             u.email,
    //             c.id as courseid,
    //             c.fullname AS coursename,
    //             ROUND(gg.finalgrade, 2) AS grade,
    //             CASE 
    //                 WHEN cc.timecompleted IS NOT NULL THEN 'Completed'
    //                 WHEN ue.userid IS NOT NULL THEN 'In Progress'
    //                 ELSE 'Not Enrolled'
    //             END AS status,
    //             FROM_UNIXTIME(cc.timecompleted) AS completiondate,
    //             coh.name as cohort_name,
    //             CASE 
    //                 WHEN e.enrol = 'cohort' THEN 'Cohort Assignment'
    //                 WHEN e.enrol = 'cohortsync' THEN 'Cohort Sync'
    //                 WHEN e.enrol = 'manual' THEN 'Manual Enrollment'
    //                 WHEN e.enrol = 'self' THEN 'Self Enrollment'
    //                 ELSE 'Other (' || e.enrol || ')'
    //             END as enrollment_type,
    //             ROW_NUMBER() OVER (
    //                 PARTITION BY u.id, c.id 
    //                 ORDER BY 
    //                     CASE e.enrol 
    //                         WHEN 'manual' THEN 1
    //                         WHEN 'cohort' THEN 2  
    //                         WHEN 'self' THEN 3
    //                         WHEN 'cohortsync' THEN 4
    //                         ELSE 5
    //                     END,
    //                     ue.timecreated ASC
    //             ) as enrollment_priority
    //         FROM {cohort} coh
    //         JOIN {cohort_members} cm ON cm.cohortid = coh.id
    //         JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
    //         JOIN {user_enrolments} ue ON ue.userid = u.id
    //         JOIN {enrol} e ON e.id = ue.enrolid
    //         JOIN {course} c ON c.id = e.courseid AND c.visible = 1
    //         LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
    //         LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
    //         LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
    //         WHERE 1=1 $cohort_filter
    //     ) ranked_enrollments
    //     WHERE enrollment_priority = 1
    //     ORDER BY cohort_name, lastname, firstname, coursename
    // ";
    $sql = "
        SELECT DISTINCT
            CONCAT(u.id, '-', c.id, '-', ue.id) as unique_key,
            u.id as userid,
            u.firstname, 
            u.lastname, 
            u.email,
            c.id as courseid,
            c.fullname AS coursename,
            ROUND(gg.finalgrade, 2) AS grade,
            CASE 
                WHEN cc.timecompleted IS NOT NULL THEN 'Completed'
                WHEN ue.userid IS NOT NULL THEN 'In Progress'
                ELSE 'Not Enrolled'
            END AS status,
            FROM_UNIXTIME(cc.timecompleted) AS completiondate,
            coh.name as cohort_name,
            CASE 
                WHEN e.enrol = 'cohort' THEN 'Cohort Assignment'
                WHEN e.enrol = 'cohortsync' THEN 'Cohort Sync'
                WHEN e.enrol = 'manual' THEN 'Manual Enrollment'
                WHEN e.enrol = 'self' THEN 'Self Enrollment'
                ELSE 'Other (' || e.enrol || ')'
            END as enrollment_type
        FROM {cohort} coh
        JOIN {cohort_members} cm ON cm.cohortid = coh.id
        JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid AND c.visible = 1
        LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
        LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
        WHERE 1=1 $cohort_filter
        ORDER BY cohort_name, lastname, firstname, coursename
    ";
    
    return $DB->get_records_sql($sql, $params);
}

// Get supervised cohorts for current user
$supervised_cohort_ids = get_supervised_cohorts($USER->id);

if (empty($supervised_cohort_ids)) {
    throw new moodle_exception('You do not have manager permissions for any cohorts.', 'local_customdashboard');
}

// Get cohort options for filter dropdown
$cohort_options = [0 => 'All Supervised Cohorts'];
if (!empty($supervised_cohort_ids)) {
    list($cohort_sql, $cohort_params) = $DB->get_in_or_equal($supervised_cohort_ids, SQL_PARAMS_NAMED);
    $cohorts = $DB->get_records_sql("SELECT id, name FROM {cohort} WHERE id $cohort_sql ORDER BY name", $cohort_params);
    foreach ($cohorts as $cohort) {
        $cohort_options[$cohort->id] = $cohort->name;
    }
}

// Get course options for filter dropdown (based on selected cohort or all supervised cohorts)
$course_options = [0 => 'All Courses'];
if (!empty($supervised_cohort_ids)) {
    $cohort_filter_for_courses = '';
    $course_params = [];
    
    if ($cohortid > 0 && in_array($cohortid, $supervised_cohort_ids)) {
        $cohort_filter_for_courses = "AND cm.cohortid = :selected_cohort_courses";
        $course_params['selected_cohort_courses'] = $cohortid;
    } else {
        list($cohort_sql, $cohort_params) = $DB->get_in_or_equal($supervised_cohort_ids, SQL_PARAMS_NAMED, 'course_cohort');
        $cohort_filter_for_courses = "AND cm.cohortid $cohort_sql";
        $course_params = array_merge($course_params, $cohort_params);
    }
    
    $course_sql = "
        SELECT DISTINCT c.id, c.fullname
        FROM {course} c
        JOIN {enrol} e ON e.courseid = c.id
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        JOIN {cohort_members} cm ON cm.userid = ue.userid
        WHERE c.visible = 1 $cohort_filter_for_courses
        ORDER BY c.fullname
    ";
    
    $courses = $DB->get_records_sql($course_sql, $course_params);
    foreach ($courses as $course) {
        $course_options[$course->id] = $course->fullname;
    }
}

// Get data based on view type
if ($viewtype === 'detailed') {
    $data = get_detailed_progress($supervised_cohort_ids, $cohortid, $courseid);
} else {
    $data = get_cohort_progress_summary($supervised_cohort_ids, $cohortid, $courseid);
}
$summary_data = get_cohort_progress_summary($supervised_cohort_ids, $cohortid, $courseid);

// Handle CSV download
if ($download && !empty($data)) {
    header('Content-Type: text/csv');
    if ($viewtype === 'detailed') {
        header('Content-Disposition: attachment; filename="detailed_progress_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Full Name', 'Email', 'Course Name', 'Grade', 'Status', 'Completion Date', 'Cohort', 'Enrollment Type']);
        foreach ($data as $row) {
            fputcsv($out, [
                $row->firstname . ' ' . $row->lastname,
                $row->email,
                $row->coursename,
                $row->grade,
                $row->status,
                $row->completiondate,
                $row->cohort_name,
                $row->enrollment_type
            ]);
        }
    } else {
        header('Content-Disposition: attachment; filename="cohort_progress_summary.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Cohort Name', 'Total Members', 'Completed Courses', 'Total Course Enrollments', 'Progress %', 'Courses Available']);
        foreach ($data as $row) {
            $progress_percent = $row->total_enrollments > 0 ? round(($row->completed_enrollments / $row->total_enrollments) * 100, 1) : 0;
            fputcsv($out, [
                $row->cohort_name,
                $row->total_members,
                $row->completed_enrollments,
                $row->total_enrollments,
                $progress_percent . '%',
                $row->total_courses
            ]);
        }
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();
echo '<br>';

// Show supervision scope
echo '<div class="alert alert-info">';
echo '<strong>Your Supervision Scope:</strong> You can view progress for ' . count($supervised_cohort_ids) . ' cohort(s)';
echo '</div>';

if (!empty($summary_data)) {
    echo '<div class="row mb-4">';
    foreach ($summary_data as $cohort_summary) {
        $progress_percent = $cohort_summary->total_enrollments > 0 ? 
            round(($cohort_summary->completed_enrollments / $cohort_summary->total_enrollments) * 100, 1) : 0;
        $progress_color = $progress_percent >= 80 ? 'success' : ($progress_percent >= 50 ? 'warning' : 'danger');
        
        echo '<div class="col-md-6 col-lg-4 mb-3">';
        echo '<div class="card border-' . $progress_color . '">';
        echo '<div class="card-header bg-' . $progress_color . ' text-white">';
        echo '<h5 class="card-title mb-0">' . $cohort_summary->cohort_name . '</h5>';
        echo '</div>';
        echo '<div class="card-body">';
        
        // Members count
        echo '<div class="row mb-2">';
        echo '<div class="col-6"><strong>Members:</strong></div>';
        echo '<div class="col-6">' . $cohort_summary->total_members . '</div>';
        echo '</div>';
        
        // Course completion ratio
        echo '<div class="row mb-2">';
        echo '<div class="col-6"><strong>Progress:</strong></div>';
        echo '<div class="col-6"><span class="badge badge-' . $progress_color . '">' . 
             $cohort_summary->completed_enrollments . ' of ' . $cohort_summary->total_enrollments . ' courses</span></div>';
        echo '</div>';
        
        // Completion percentage
        echo '<div class="row mb-3">';
        echo '<div class="col-6"><strong>Rate:</strong></div>';
        echo '<div class="col-6"><span class="badge badge-' . $progress_color . '">' . $progress_percent . '%</span></div>';
        echo '</div>';
        
        // Progress bar
        echo '<div class="progress mb-2" style="height: 10px;">';
        echo '<div class="progress-bar bg-' . $progress_color . '" style="width: ' . $progress_percent . '%"></div>';
        echo '</div>';
        
        // Course breakdown by enrollment source
        echo '<div class="enrollment-breakdown mt-3">';
        echo '<small class="text-muted d-block mb-2"><strong>Completion by Enrollment Type:</strong></small>';
        
        
        
        if (isset($cohort_summary->cohort_total_enrollments) && $cohort_summary->cohort_total_enrollments > 0) {
            $cohort_percent = round(($cohort_summary->cohort_completed_enrollments / $cohort_summary->cohort_total_enrollments) * 100, 1);
            echo '<div class="mb-1"><span class="badge badge-primary">Cohort Assignment:</span> ' . 
                 $cohort_summary->cohort_completed_enrollments . ' of ' . $cohort_summary->cohort_total_enrollments . 
                 ' (' . $cohort_percent . '%)</div>';
        }
        
        if (isset($cohort_summary->cohortsync_total_enrollments) && $cohort_summary->cohortsync_total_enrollments > 0) {
            $cohortsync_percent = round(($cohort_summary->cohortsync_completed_enrollments / $cohort_summary->cohortsync_total_enrollments) * 100, 1);
            echo '<div class="mb-1"><span class="badge badge-info">Cohort Sync:</span> ' . 
                 $cohort_summary->cohortsync_completed_enrollments . ' of ' . $cohort_summary->cohortsync_total_enrollments . 
                 ' (' . $cohortsync_percent . '%)</div>';
        }
        
        if (isset($cohort_summary->manual_total_enrollments) && $cohort_summary->manual_total_enrollments > 0) {
            $manual_percent = round(($cohort_summary->manual_completed_enrollments / $cohort_summary->manual_total_enrollments) * 100, 1);
            echo '<div class="mb-1"><span class="badge badge-success">Manual Enrollment:</span> ' . 
                 $cohort_summary->manual_completed_enrollments . ' of ' . $cohort_summary->manual_total_enrollments . 
                 ' (' . $manual_percent . '%)</div>';
        }
        
        if (isset($cohort_summary->self_total_enrollments) && $cohort_summary->self_total_enrollments > 0) {
            $self_percent = round(($cohort_summary->self_completed_enrollments / $cohort_summary->self_total_enrollments) * 100, 1);
            echo '<div class="mb-1"><span class="badge badge-warning">Self Enrollment:</span> ' . 
                 $cohort_summary->self_completed_enrollments . ' of ' . $cohort_summary->self_total_enrollments . 
                 ' (' . $self_percent . '%)</div>';
        }
        
        if (isset($cohort_summary->other_total_enrollments) && $cohort_summary->other_total_enrollments > 0) {
            $other_percent = round(($cohort_summary->other_completed_enrollments / $cohort_summary->other_total_enrollments) * 100, 1);
            echo '<div class="mb-1"><span class="badge badge-secondary">Other:</span> ' . 
                 $cohort_summary->other_completed_enrollments . ' of ' . $cohort_summary->other_total_enrollments . 
                 ' (' . $other_percent . '%)</div>';
        }
        
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

// Filter and view controls
echo '<div class="progress-filter box py-3 px-4 mb-4" style="background:#f9f9f9; border:1px solid #ccc; border-radius:8px;">';
echo '<form method="GET" class="form-inline" style="display:flex; gap: 10px; align-items:center; flex-wrap: wrap;">';

echo '<div class="form-group">';
echo '<label for="viewtype" class="mr-2"><strong>View:</strong></label>';
echo '<select name="viewtype" id="viewtype" class="custom-select">';
echo '<option value="summary"' . ($viewtype === 'summary' ? ' selected' : '') . '>Summary View</option>';
echo '<option value="detailed"' . ($viewtype === 'detailed' ? ' selected' : '') . '>Detailed View</option>';
echo '</select>';
echo '</div>';

if (count($cohort_options) > 1) {
    echo '<div class="form-group">';
    echo '<label for="cohortid" class="mr-2"><strong>Cohort:</strong></label>';
    echo '<select name="cohortid" id="cohortid" class="custom-select">';
    foreach ($cohort_options as $id => $name) {
        $selected = ($id == $cohortid) ? ' selected' : '';
        echo '<option value="'.$id.'"'.$selected.'>'.$name.'</option>';
    }
    echo '</select>';
    echo '</div>';
}

if (count($course_options) > 1 && $viewtype === 'detailed') {
    echo '<div class="form-group">';
    echo '<label for="courseid" class="mr-2"><strong>Course:</strong></label>';
    echo '<select name="courseid" id="courseid" class="custom-select">';
    foreach ($course_options as $id => $name) {
        $selected = ($id == $courseid) ? ' selected' : '';
        echo '<option value="'.$id.'"'.$selected.'>'.format_string($name).'</option>';
    }
    echo '</select>';
    echo '</div>';
}

echo '<input type="submit" class="btn btn-secondary ml-2" value="Filter">';
echo '<a href="?viewtype='.$viewtype.'&cohortid='.$cohortid.'&courseid='.$courseid.'&download=1" class="btn btn-success ml-2">Download CSV</a>';
echo '</form>';
echo '</div>';

if ($data) {
    if ($viewtype === 'summary') {
        // Summary view - "X of Y courses completed" by cohort
        echo '<div class="alert alert-success">Showing progress summary for your supervised cohorts</div>';
        echo '<table class="generaltable boxaligncenter" border="1" cellpadding="5" cellspacing="0">';
        echo '<tr>
                <th>Cohort Name</th>
                <th>Members</th>
                <th>Course Progress</th>
                <th>Completion Rate</th>
                <th>Available Courses</th>
              </tr>';
        
        foreach ($data as $row) {
            $progress_percent = $row->total_enrollments > 0 ? round(($row->completed_enrollments / $row->total_enrollments) * 100, 1) : 0;
            $progress_color = $progress_percent >= 80 ? 'success' : ($progress_percent >= 50 ? 'warning' : 'danger');
            
            echo '<tr>
                    <td><strong>'.$row->cohort_name.'</strong></td>
                    <td>'.$row->total_members.'</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-'.$progress_color.'" style="width: '.$progress_percent.'%">
                                '.$row->completed_enrollments.' of '.$row->total_enrollments.'
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-'.$progress_color.'">'.$progress_percent.'%</span></td>
                    <td>'.$row->total_courses.'</td>
                  </tr>';
        }
        echo '</table>';
    } else {
        // Detailed view
        echo '<div class="alert alert-success">Showing detailed progress for individual learners in your supervised cohorts</div>';
        echo '<table class="generaltable boxaligncenter" border="1" cellpadding="5" cellspacing="0">';
        echo '<tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Grade (%)</th>
                <th>Status</th>
                <th>Completion Date</th>
                <th>Cohort</th>
                <th>Enrollment</th>
              </tr>';
        
        foreach ($data as $row) {
            $status_class = $row->status === 'Completed' ? 'success' : ($row->status === 'In Progress' ? 'warning' : 'secondary');
            
            // Color-code enrollment types
            $enrollment_class = 'secondary';
            if ($row->enrollment_type === 'Cohort Assignment') {
                $enrollment_class = 'primary';
            } elseif ($row->enrollment_type === 'Cohort Sync') {
                $enrollment_class = 'info';
            } elseif ($row->enrollment_type === 'Manual Enrollment') {
                $enrollment_class = 'success';
            } elseif ($row->enrollment_type === 'Self Enrollment') {
                $enrollment_class = 'warning';
            }
            
            echo '<tr>
                    <td>'.$row->firstname.' '.$row->lastname.'</td>
                    <td>'.$row->email.'</td>
                    <td>'.$row->coursename.'</td>
                    <td>'.$row->grade.'</td>
                    <td><span class="badge badge-'.$status_class.'">'.$row->status.'</span></td>
                    <td>'.$row->completiondate.'</td>
                    <td><span class="badge badge-dark">'.$row->cohort_name.'</span></td>
                    <td><span class="badge badge-'.$enrollment_class.'">'.$row->enrollment_type.'</span></td>
                  </tr>';
        }
        echo '</table>';
    }
} else {
    echo '<div class="alert alert-warning">No records found for your supervised cohorts.</div>';
}

echo $OUTPUT->footer();
?>