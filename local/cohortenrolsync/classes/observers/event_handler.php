<?php
namespace local_cohortenrolsync\observers;

use local_cohortenrolsync\core\sync_engine;

defined('MOODLE_INTERNAL') || die();

class event_handler {

    // cohort membership and lifecycle
    public static function cohort_member_added(\core\event\cohort_member_added $event) {
        self::queue_user_sync($event->relateduserid);
    }

    public static function cohort_member_removed(\core\event\cohort_member_removed $event) {
        self::queue_user_sync($event->relateduserid);
    }


    public static function cohort_created(\core\event\cohort_created $event) {
        // Nothing immediate, but could sync members if pre-assigned.
    }

    public static function cohort_updated(\core\event\cohort_updated $event) {
        global $DB;
        $cohortid = $event->objectid;
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    public static function cohort_deleted(\core\event\cohort_deleted $event) {
        global $DB;
        $cohortid = $event->objectid;
        // Users lose any assignments inherited via this cohort.
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    
    //course lifecyle
    public static function course_created(\core\event\course_created $event) {
        $courseid = $event->objectid;
        // New course in a category → users with that category need resync.
        self::sync_users_by_category($courseid);
    }

    public static function course_updated(\core\event\course_updated $event) {
        global $DB;
        $courseid = $event->objectid;
        $old = $event->get_record_snapshot('course', $courseid);
        $new = $DB->get_record('course', ['id' => $courseid]);
        if ($old && $new && $old->category != $new->category) {
            self::sync_users_affected_by_course_move($courseid, $old->category, $new->category);
        }
    }

    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = $event->objectid;
        // Find users who had this course via direct/cohort/category.
        $userids = [];
        $userids = array_merge($userids,
            $DB->get_fieldset('employee_course_assign', 'userid', ['courseid' => $courseid])
        );
        $sql = "SELECT cm.userid
                  FROM {cohort_course_assign} cca
                  JOIN {cohort_members} cm ON cm.cohortid = cca.cohortid
                 WHERE cca.courseid = :courseid";
        $userids = array_merge($userids, $DB->get_fieldset_sql($sql, ['courseid' => $courseid]));
        $userids = array_unique($userids);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    //category lifecycle

    public static function category_created(\core\event\course_category_created $event) {
        // Nothing immediate: no courses yet.
    }

    public static function category_updated(\core\event\course_category_updated $event) {
        $categoryid = $event->objectid;
        self::sync_users_by_categoryid($categoryid);
    }

    public static function category_deleted(\core\event\course_category_deleted $event) {
        $categoryid = $event->objectid;
        self::sync_users_by_categoryid($categoryid);
    }

    //user lifecycle

    public static function user_created(\core\event\user_created $event) {
        self::queue_user_sync($event->objectid);
    }

    public static function user_updated(\core\event\user_updated $event) {
        self::queue_user_sync($event->objectid);
    }

    public static function user_deleted(\core\event\user_deleted $event) {
        // Nothing to sync — cleanup handled elsewhere.
    }

    //enrolment lifecycle

    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        self::queue_user_sync($event->relateduserid);
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        self::queue_user_sync($event->relateduserid);
    }

    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        self::queue_user_sync($event->relateduserid);
    }

    //custom mapping

    public static function cohort_category_assignment_changed(\local_cohortenrolsync\event\cohort_category_assignment_changed $event) {
        global $DB;
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $event->other['cohortid']]);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    public static function cohort_course_assignment_changed(\local_cohortenrolsync\event\cohort_course_assignment_changed $event) {
        global $DB;
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $event->other['cohortid']]);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    
    private static function queue_user_sync($userid) {
        if (empty($userid)) {
            return;
        }
        
        // sync_engine::incremental_sync([$userid]);
        \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks([$userid]);
    }

    private static function sync_users_affected_by_course_move($courseid, $oldcat, $newcat) {
        global $DB;
        $userids = [];

        // Direct category assignments
        $userids = array_merge($userids,
            $DB->get_fieldset('employee_category_assign', 'userid', ['categoryid' => $oldcat]),
            $DB->get_fieldset('employee_category_assign', 'userid', ['categoryid' => $newcat])
        );

        // Cohort category assignments
        $sql = "SELECT DISTINCT cm.userid
                  FROM {cohort_category_assign} cca
                  JOIN {cohort_members} cm ON cm.cohortid = cca.cohortid
                 WHERE cca.categoryid = :catid";
        $userids = array_merge($userids,
            $DB->get_fieldset_sql($sql, ['catid' => $oldcat]),
            $DB->get_fieldset_sql($sql, ['catid' => $newcat])
        );

        $userids = array_unique($userids);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }

    private static function sync_users_by_category($courseid) {
        global $DB;
        $categoryid = $DB->get_field('course', 'category', ['id' => $courseid]);
        if ($categoryid) {
            self::sync_users_by_categoryid($categoryid);
        }
    }

    private static function sync_users_by_categoryid($categoryid) {
        global $DB;
        $userids = [];

        $userids = array_merge($userids,
            $DB->get_fieldset('employee_category_assign', 'userid', ['categoryid' => $categoryid])
        );

        $sql = "SELECT DISTINCT cm.userid
                  FROM {cohort_category_assign} cca
                  JOIN {cohort_members} cm ON cm.cohortid = cca.cohortid
                 WHERE cca.categoryid = :catid";
        $userids = array_merge($userids, $DB->get_fieldset_sql($sql, ['catid' => $categoryid]));

        $userids = array_unique($userids);
        if ($userids) {
            // sync_engine::incremental_sync($userids);
            \local_cohortenrolsync\services\batch_manager::queue_user_sync_chunks($userids);
        }
    }
}
