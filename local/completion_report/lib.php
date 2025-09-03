<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the moodle hooks for the completion report.
 *
 * @package   local_completion_report
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function local_completion_report_extend_navigation(global_navigation $navigation){
    global $DB, $USER, $PAGE, $CFG;
    $context = context_system::instance();
    if (isloggedin() && !isguestuser()) {
        $discussionnode = $PAGE->navigation->add(
            "Discussion forum",
            new moodle_url('/mod/forum/view.php?f=12'),
            navigation_node::NODETYPE_LEAF,
            'discussion_forum',
            'discussion_forum',
            new pix_icon('i/user', 'Discussion forum')
        );
        $discussionnode->showinflatnavigation = true;
    }

    
    $has_teacher_role = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => 3]);

    
    if (is_siteadmin() || user_has_role_assignment($USER->id, 1, $systemcontext->id)) {
        $reportsnode = $PAGE->navigation->add(
            "Reports",
            null,
            navigation_node::NODETYPE_BRANCH,
            'reports',
            'reports',
            new pix_icon('i/report', 'reports')
        );        
    
        $coursenode = $reportsnode->add(
            "Program Completion Report",
            new moodle_url('/local/completion_report/report.php'),
            navigation_node::NODETYPE_LEAF,
            'local_completion_report',
            'local_completion_report',
            new pix_icon('i/user', 'user report')
        );
        
        $coursenode1 = $reportsnode->add(
            "Course-wise Summary Report",
            new moodle_url('/local/completion_report/course_report.php'),
            navigation_node::NODETYPE_LEAF,
            'local_completion_report',
            'local_completion_report',
            new pix_icon('i/course', 'course report')
        );
    
        $coursenode2 = $reportsnode->add(
            "Activity-wise Summary Report",
            new moodle_url('/local/completion_report/activity_resource_report.php'),
            navigation_node::NODETYPE_LEAF,
            'local_completion_report',
            'local_completion_report',
            new pix_icon('i/mnethost', 'activity report')
        );
    
        $coursenode3 = $reportsnode->add(
            "Activity Completion Report",
            new moodle_url('/local/completion_report/activity_completion_report.php'),
            navigation_node::NODETYPE_LEAF,
            'local_completion_report',
            'local_completion_report',
            new pix_icon('i/completion-manual-y', 'completion report')
        );
    
        $reportsnode->showinflatnavigation = true;
    }

}

function unset_filter_variables(){
    unset($_SESSION['coursename']);
    unset($_SESSION['course_completion']);
    unset($_SESSION['datefrom']);
    unset($_SESSION['dateto']);
}


