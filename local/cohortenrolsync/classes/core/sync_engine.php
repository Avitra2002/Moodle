<?php

namespace local_cohortenrolsync\core;

use local_cohortenrolsync\core\assignment_resolver;

defined('MOODLE_INTERNAL') || die();


class sync_engine {
    
    const ENROL_METHOD = 'cohortsync';
    
    //sync single user
    public static function sync_user($userid) {
        global $DB;
        
        echo "DEBUG: Starting sync for user ID: $userid<br>";
        
        $results = [
            'userid' => $userid,
            'assignments_added' => 0,
            'assignments_removed' => 0,
            'errors' => []
        ];
        
        try {
            $transaction = $DB->start_delegated_transaction();
            
            // courses user should have
            $effective_assignments = assignment_resolver::get_effective_course_assignments($userid);
            
            // courses to be removed
            $removals = assignment_resolver::calculate_removals($userid, $effective_assignments);
            
            foreach ($removals as $removal) {
                if (self::unenrol_user($removal['userid'], $removal['courseid'])) {
                    $results['assignments_removed']++;
                }
                
            }
            
            // add/update current assignments
            foreach ($effective_assignments as $assignment) {
                
                
                $courseid_int = (int)$assignment['courseid'];
                $roleid_int = (int)$assignment['roleid'];
                
                if (self::direct_enrol_user($userid, $courseid_int, $roleid_int)) {
                    $results['assignments_added']++;
                }
                
            }
            
            $transaction->allow_commit();

            debugging('Assignments resolved for userid ' . $userid . ': ' . json_encode($effective_assignments), DEBUG_DEVELOPER);
            debugging('Removals calculated for userid ' . $userid . ': ' . json_encode($removals), DEBUG_DEVELOPER);


            
        } catch (\Exception $e) {
            $transaction->rollback($e);
            $results['errors'][] = 'Transaction failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    private static function direct_enrol_user($userid, $courseid, $roleid) {
        global $DB;

        echo "DEBUG: direct_enrol_user called - userid=$userid, courseid=$courseid, roleid=$roleid<br>";

        try {
            // check if user is already enrolled in this course via 'cohortsync' plugin
            $existing = $DB->get_record_sql("
                SELECT ue.id
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = :userid 
                AND e.courseid = :courseid
                AND e.enrol = :enrolmethod",
                [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'enrolmethod' => self::ENROL_METHOD // "cohortsync"
                ]
            );

            if ($existing) {
                return true; 
            }

            $instance = $DB->get_record('enrol', [
                'courseid' => $courseid,
                'enrol'    => self::ENROL_METHOD
            ]);

            if (!$instance) {

                $instance = new \stdClass();
                $instance->enrol          = self::ENROL_METHOD;  
                $instance->status         = ENROL_INSTANCE_ENABLED;
                $instance->courseid       = $courseid;
                $instance->roleid         = $roleid;
                $instance->enrolstartdate = 0;
                $instance->enrolenddate   = 0;
                $instance->timemodified   = time();
                $instance->timecreated    = time();
                $instance->sortorder      = $DB->get_field('enrol', 'COALESCE(MAX(sortorder), -1) + 1', ['courseid' => $courseid]);
                $instance->name           = 'Cohort Sync enrolments';

                $instance->id = $DB->insert_record('enrol', $instance);
                
            } else {
                echo "DEBUG: Using existing cohortsync instance with ID: {$instance->id}<br>";
            }

            // innsert enrolment
            $enrolment = new \stdClass();
            $enrolment->enrolid      = $instance->id;
            $enrolment->userid       = $userid;
            $enrolment->status       = ENROL_USER_ACTIVE;
            $enrolment->timestart    = 0;
            $enrolment->timeend      = 0;
            $enrolment->timemodified = time();
            $enrolment->timecreated  = time();

            $enrolment->id = $DB->insert_record('user_enrolments', $enrolment);

            // role assign
            $context = \context_course::instance($courseid);
            \role_assign($roleid, $userid, $context->id, 'enrol_cohortsync', $instance->id);

            return true;

        } catch (\Exception $e) {
            echo "ERROR: Exception in direct_enrol_user: " . $e->getMessage() . "<br>";
            return false;
        }
    }

    
    //sync multiple people in batch
    public static function sync_users_batch($userids, $batch_size = 100) {
        $total_results = [
            'users_processed' => 0,
            'total_assignments_added' => 0,
            'total_assignments_removed' => 0,
            'errors' => []
        ];
        
        // Process in chunks to avoid memory issues
        $chunks = array_chunk($userids, $batch_size);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $userid) {
                $user_results = self::sync_user($userid);
                
                $total_results['users_processed']++;
                $total_results['total_assignments_added'] += $user_results['assignments_added'];
                $total_results['total_assignments_removed'] += $user_results['assignments_removed'];
                
                if (!empty($user_results['errors'])) {
                    $total_results['errors'] = array_merge($total_results['errors'], $user_results['errors']);
                }
            }
            
            // Clear memory between chunks
            gc_collect_cycles();
        }
        
        return $total_results;
    }
    
    //Sync all users with assignments in custom tables, called by schedule task
    public static function full_sync($batch_size = 100) {
        global $DB;
        
        $alluserids = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id 
            FROM {user} u 
            WHERE u.deleted = 0 
            AND u.suspended = 0
            AND (
                EXISTS (SELECT 1 FROM {cohort_members} cm WHERE cm.userid = u.id)
                OR EXISTS (SELECT 1 FROM {employee_course_assign} eca WHERE eca.userid = u.id)
                OR EXISTS (SELECT 1 FROM {employee_category_assign} ecata WHERE ecata.userid = u.id)
            )
        ");
        
        return self::sync_users_batch($alluserids, $batch_size);
    }
    
    
    //only processs users affected by recent changes
    public static function incremental_sync($affected_userids) {
        if (empty($affected_userids)) {
            return ['message' => 'No users to sync'];
        }
        
        return self::sync_users_batch($affected_userids);
    }
    
    private static function unenrol_user($userid, $courseid) {
        global $DB;
        
        $enrol_instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => self::ENROL_METHOD,
        ]);
        
        if (!$enrol_instance) {
            return false;
        }
        
        $enrol_plugin = \enrol_get_plugin(self::ENROL_METHOD);
        if (!$enrol_plugin) {
            return false;
        }
        
        $enrol_plugin->unenrol_user($enrol_instance, $userid);
        
        return true;
    }
    
    public static function get_sync_status() {
        global $DB;
        
        $stats = [
            'total_users_with_assignments' => 0,
            'total_course_assignments' => 0,
            'total_category_assignments' => 0,
            'last_sync_time' => null
        ];
        
        // Get counts from our custom tables
        $stats['total_course_assignments'] = $DB->count_records('employee_course_assign') + 
                                           $DB->count_records('cohort_course_assign');
                                           
        $stats['total_category_assignments'] = $DB->count_records('employee_category_assign') + 
                                             $DB->count_records('cohort_category_assign');
        
        
        return $stats;
    }
}