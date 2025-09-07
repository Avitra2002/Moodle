<?php

namespace local_cohortenrolsync\core;
use local_cohortenrolsync\core\sync_engine;


defined('MOODLE_INTERNAL') || die();

class assignment_resolver {

    //union calc
    public static function get_effective_course_assignments($userid) {
        global $DB;
        
        $assignments = [];
        
        // 1. Get direct course assignments
        $direct = $DB->get_records('employee_course_assign', ['userid' => $userid]);
        foreach ($direct as $assignment) {
            $assignments[$assignment->courseid] = [
                'courseid' => $assignment->courseid,
                'sources' => ['direct'],
                'roleid' => null 
            ];
        }
        
        // 2. Get cohort-based course assignments
        $cohort_assignments = self::get_cohort_course_assignments($userid);
        foreach ($cohort_assignments as $courseid => $data) {
            if (isset($assignments[$courseid])) {
                // Already has this course from direct assignment, add cohort as source
                $assignments[$courseid]['sources'][] = 'cohort';
                if (!$assignments[$courseid]['roleid'] && $data['roleid']) {
                    $assignments[$courseid]['roleid'] = $data['roleid'];
                }
            } else {
                // New course from cohort
                $assignments[$courseid] = [
                    'courseid' => $courseid,
                    'sources' => ['cohort'],
                    'roleid' => $data['roleid']
                ];
            }
        }
        
        // 3. Get category-based course assignments
        $category_assignments = self::get_category_course_assignments($userid);
        foreach ($category_assignments as $courseid => $data) {
            if (isset($assignments[$courseid])) {
                // Already has this course, add category as source
                $assignments[$courseid]['sources'][] = 'category';
                if (!$assignments[$courseid]['roleid'] && $data['roleid']) {
                    $assignments[$courseid]['roleid'] = $data['roleid'];
                }
            } else {
                // New course from category
                $assignments[$courseid] = [
                    'courseid' => $courseid,
                    'sources' => ['category'],
                    'roleid' => $data['roleid']
                ];
            }
        }
        
        // Set default role if none specified
        foreach ($assignments as &$assignment) {
            if (!$assignment['roleid']) {
                $assignment['roleid'] = self::get_default_student_role();
            }
        }
        
        return $assignments;
    }
    
    
    private static function get_cohort_course_assignments($userid) {
        global $DB;
        
        $sql = "SELECT cca.courseid, cca.roleid, c.name as cohortname
                FROM {cohort_course_assign} cca
                JOIN {cohort_members} cm ON cm.cohortid = cca.cohortid
                JOIN {cohort} c ON c.id = cca.cohortid
                WHERE cm.userid = :userid";
                
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        
        $assignments = [];
        foreach ($records as $record) {
            $assignments[$record->courseid] = [
                'roleid' => $record->roleid,
            ];
        }
        
        return $assignments;
    }
    

    private static function get_category_course_assignments($userid) {
        global $DB;
        
        $assignments = [];
        
        // Direct category assignments
        $direct_categories = $DB->get_records('employee_category_assign', ['userid' => $userid]);
        foreach ($direct_categories as $cat_assign) {
            $courses = self::get_courses_in_category($cat_assign->categoryid);
            foreach ($courses as $courseid) {
                $assignments[$courseid] = [
                    'roleid' => null,
                ];
            }
        }
        
        // Cohort-based category assignments
        $sql = "SELECT cca.categoryid, cca.roleid, c.name as cohortname
                FROM {cohort_category_assign} cca
                JOIN {cohort_members} cm ON cm.cohortid = cca.cohortid
                JOIN {cohort} c ON c.id = cca.cohortid
                WHERE cm.userid = :userid";
                
        $cohort_categories = $DB->get_records_sql($sql, ['userid' => $userid]);
        foreach ($cohort_categories as $record) {
            $courses = self::get_courses_in_category($record->categoryid);
            foreach ($courses as $courseid) {
                if (!isset($assignments[$courseid])) {
                    $assignments[$courseid] = [
                        'roleid' => $record->roleid,
                    ];
                }
            }
        }
        
        return $assignments;
    }
    
    
    private static function get_courses_in_category($categoryid) {
        global $DB;
        
        // Get the category and all its subcategories
        $categories = [$categoryid];
        $subcategories = self::get_subcategories($categoryid);
        $categories = array_merge($categories, $subcategories);
        
        if (empty($categories)) {
            return [];
        }
        
        list($insql, $params) = $DB->get_in_or_equal($categories); //eg. insql = "IN (?,?,?)" and params = [5,7,9] -> category ids

        //find is course table WHERE category IN (?,?,?)
        $courses = $DB->get_fieldset_select('course', 'id', "category $insql", $params);
        
        return $courses;
    }
    
    //get all sub cats
    private static function get_subcategories($categoryid) {
        global $DB;
        
        $subcategories = [];
        $children = $DB->get_fieldset('course_categories', 'id', ['parent' => $categoryid]);
        
        foreach ($children as $childid) {
            $subcategories[] = $childid;
            // Recursive call for deeper levels
            $subcategories = array_merge($subcategories, self::get_subcategories($childid));
        }
        
        return $subcategories;
    }
    
    
    public static function calculate_removals($userid, $current_assignments) {
        global $DB;

        // Get all current enrolments managed by our plugin (enrol='cohortsync')
        $sql = "SELECT e.courseid
                FROM {enrol} e
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE ue.userid = :userid
                AND e.enrol = :enrolmethod";

        $current_enrolments = $DB->get_records_sql($sql, [
            'userid'     => $userid,
            'enrolmethod'=> sync_engine::ENROL_METHOD//'cohortsync'
        ]);
        

        $removals = [];
        foreach ($current_enrolments as $enrolment) {
            // If the course is no longer in effective assignments → remove it
            if (!isset($current_assignments[$enrolment->courseid])) {
                $removals[] = [
                    'courseid' => $enrolment->courseid,
                    'userid'   => $userid
                ];
            }
        }

        return $removals;
    }

    
    private static function get_default_student_role() {
        global $DB;
        static $studentrole = null;
        
        if ($studentrole === null) {
            // Get student role directly from database
            $role = $DB->get_record('role', ['shortname' => 'student']);
            $studentrole = $role ? $role->id : 5; // fallback
        }
        
        return $studentrole;
    }
    

}