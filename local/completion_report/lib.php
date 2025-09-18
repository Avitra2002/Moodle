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


function local_completion_report_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $DB;

    $category = new core_user\output\myprofile\category('traininghistory', 'Training History/Completed Courses', 'contact');
    $tree->add_category($category);

    //external training records
    $external_records = $DB->get_records('local_coursehistory', ['userid' => $user->id], 'completion_date DESC');
    
    // moodle course completions
    $sql = "SELECT cc.id, c.fullname as course_name, cc.timecompleted as completion_date, 'moodle' as source
            FROM {course_completions} cc
            JOIN {course} c ON cc.course = c.id
            WHERE cc.userid = ? AND cc.timecompleted IS NOT NULL
            ORDER BY cc.timecompleted DESC";
    $moodle_completions = $DB->get_records_sql($sql, [$user->id]);
    
    
    $all_trainings = [];
    
    foreach ($external_records as $rec) {
        $all_trainings[] = [
            'name' => format_string($rec->course_name),
            'date' => $rec->completion_date,
            'source' => 'external'
        ];
    }
    
    foreach ($moodle_completions as $completion) {
        $all_trainings[] = [
            'name' => format_string($completion->course_name),
            'date' => $completion->completion_date,
            'source' => 'moodle'
        ];
    }
    
    // Sort all trainings by date (newest first)
    usort($all_trainings, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    
    $content = '';
    
    if (!empty($all_trainings)) {
        // Summary statistics
        $total_count = count($all_trainings);
        $moodle_count = count($moodle_completions);
        $external_count = count($external_records);
        
        $content .= '<div class="alert alert-info py-2 mb-3">';
        $content .= '<small class="text-muted">(' . $moodle_count . ' Moodle, ' . $external_count . ' External)</small>';
        $content .= '</div>';
        
        $list_id = 'training-list-' . $user->id;
        $button_id = 'training-btn-' . $user->id;
        
        $content .= '<div id="' . $list_id . '">';
        $content .= '<ul class="list-unstyled small">';
        
        foreach ($all_trainings as $index => $training) {
            $icon = $training['source'] == 'moodle' ? 
                '<i class="fa fa-graduation-cap text-primary mr-1"></i>' : 
                '<i class="fa fa-certificate text-success mr-1"></i>';
            
            
            if ($index >= 5) {
                $content .= '<li class="mb-1 hidden-training-item" style="display: none;">';
            } else {
                $content .= '<li class="mb-1">';
            }
            
            $content .= $icon . $training['name'];
            $content .= '</li>';
        }
        
        $content .= '</ul>';
        
        // Add show more button if needed
        if ($total_count > 5) {
            $remaining = $total_count - 5;
            $content .= '<button id="' . $button_id . '" class="btn btn-sm btn-outline-secondary" type="button">';
            $content .= 'Show ' . $remaining . ' more';
            $content .= '</button>';
        }
        
        $content .= '</div>'; 
        

        if ($total_count > 5) {
            $remaining = $total_count - 5;
            $content .= "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var button = document.getElementById('" . $button_id . "');
                var container = document.getElementById('" . $list_id . "');
                
                if (button && container) {
                    button.addEventListener('click', function() {
                        var hiddenItems = container.querySelectorAll('.hidden-training-item');
                        console.log('Found ' + hiddenItems.length + ' hidden items');
                        
                        var isHidden = hiddenItems.length > 0 && hiddenItems[0].style.display === 'none';
                        console.log('Currently hidden: ' + isHidden);
                        
                        for (var i = 0; i < hiddenItems.length; i++) {
                            if (isHidden) {
                                hiddenItems[i].style.display = 'list-item';
                            } else {
                                hiddenItems[i].style.display = 'none';
                            }
                        }
                        
                        if (isHidden) {
                            button.textContent = 'Show less';
                        } else {
                            button.textContent = 'Show " . $remaining . " more';
                        }
                    });
                } else {
                    console.log('Button or container not found');
                    console.log('Button:', button);
                    console.log('Container:', container);
                }
            });
            </script>";
        }
        
    } else {
        $content .= '<div class="text-muted">No completed trainings found.</div>';
    }

    $node = new core_user\output\myprofile\node(
        'traininghistory',
        'trainings', 
        null,
        null,
        null,
        $content
    );
    $tree->add_node($node);
}