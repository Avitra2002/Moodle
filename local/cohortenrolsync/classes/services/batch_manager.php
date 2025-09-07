<?php
// TODO: refactor
namespace local_cohortenrolsync\services;
use local_cohortenrolsync\event\cohort_category_assignment_changed;
use local_cohortenrolsync\event\cohort_course_assignment_changed;

defined('MOODLE_INTERNAL') || die();

class batch_manager {

    const STATUS_QUEUED     = 'queued';
    const STATUS_RUNNING    = 'running';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    
    public static function create_bulk_job($operation_type, $data) {
        global $DB, $USER;

        $valid_operations = [
            'assign_courses_to_users',
            'assign_category_to_users',
            'assign_category_to_cohort',
            'assign_courses_to_cohort',
            'remove_courses_from_users',
            'remove_courses_from_cohort',
            'remove_category_from_cohort',
            'remove_users_from_cohort',
            'remove_users_from_category'
        ];

        if (!in_array($operation_type, $valid_operations)) {
            throw new \invalid_parameter_exception('Invalid operation type');
        }

        //job record
        $job = new \stdClass();
        $job->operation_type = $operation_type;
        $job->operation_data = json_encode($data);
        $job->status         = self::STATUS_QUEUED;
        $job->progress       = 0;
        $job->total_items    = self::calculate_total_items($operation_type, $data);
        $job->created_by     = $USER->id;
        $job->timecreated    = time();
        $job->timemodified   = time();

        $jobid = $DB->insert_record('cohortsync_batch_jobs', $job);

       // queue or start task
        $running = $DB->record_exists('cohortsync_batch_jobs', ['status' => self::STATUS_RUNNING]);

        self::queue_batch_job($jobid);
        return $jobid;
    }


    public static function execute_batch_job($jobid) {
        global $DB;

        $job = $DB->get_record('cohortsync_batch_jobs', ['id' => $jobid]);
        if (!$job) {
            throw new \invalid_parameter_exception('Job not found');
        }

        // Mark as running.
        $job->status      = self::STATUS_RUNNING;
        $job->timestarted = time();
        $DB->update_record('cohortsync_batch_jobs', $job);

        try {
            $data = json_decode($job->operation_data, true);

            switch ($job->operation_type) {
                case 'assign_courses_to_users':
                    self::execute_assign_courses_to_users($jobid, $data);
                    break;
                case 'assign_category_to_cohort':
                    self::execute_assign_category_to_cohort($jobid, $data);
                    break;
                case 'assign_courses_to_cohort':
                    self::execute_assign_courses_to_cohort($jobid, $data);
                    break;
                case 'remove_courses_from_users':
                    self::execute_remove_courses_from_users($jobid, $data);
                    break;
                case 'remove_courses_from_cohort':
                    self::execute_remove_courses_from_cohort($jobid, $data);
                    break;
                case 'remove_category_from_cohort':
                    self::execute_remove_category_from_cohort($jobid, $data);
                    break;
                case 'assign_category_to_users':
                    self::execute_assign_category_to_users($jobid, $data);
                    break;
                case 'remove_users_from_cohort':
                    self::execute_remove_users_from_cohort($jobid, $data);
                    break;
                case 'remove_users_from_category':
                    self::execute_remove_users_from_category($jobid, $data);
                    break;

                default:
                    throw new \invalid_parameter_exception('Unknown operation type');
            }

            // mark as completed.
            $job->status        = self::STATUS_COMPLETED;
            $job->progress      = 100;
            $job->timecompleted = time();
            $DB->update_record('cohortsync_batch_jobs', $job);

        } catch (\Exception $e) {
            $job->status        = self::STATUS_FAILED;
            $job->error_message = $e->getMessage();
            $job->timecompleted = time();
            $DB->update_record('cohortsync_batch_jobs', $job);
            throw $e;
        }
    }
    private static function queue_user_sync_chunks(array $userids, int $chunk_size = 100) {
        if (empty($userids)) {
            return;
        }

        $chunks = array_chunk(array_unique($userids), $chunk_size);

        foreach ($chunks as $chunk) {
            $task = new \local_cohortenrolsync\task\user_sync_task();
            $task->set_custom_data(['userids' => $chunk]);
            \core\task\manager::queue_adhoc_task($task);
        }
    }


    /// operation methods
    
    // assign multple courses to multiple users
    private static function execute_assign_courses_to_users($jobid, $data) {
        global $DB;

        $userids    = $data['userids'];
        $courseids  = $data['courseids'];
        $reason     = $data['reason'] ?? 'Bulk assignment';
        $assignedby = $data['assignedby'] ?? 0;

        $total     = count($userids) * count($courseids);
        $processed = 0;

        $user_chunks = array_chunk($userids, 50);

        foreach ($user_chunks as $user_chunk) {
            $assignments = [];

            foreach ($user_chunk as $userid) {
                foreach ($courseids as $courseid) {
                    $exists = $DB->record_exists('employee_course_assign', [
                        'userid'   => $userid,
                        'courseid' => $courseid
                    ]);

                    if (!$exists) {
                        $assignments[] = [
                            'userid'      => $userid,
                            'courseid'    => $courseid,
                            'assignedby'  => $assignedby,
                            'reason'      => $reason,
                            'timecreated' => time(),
                            'timemodified'=> time()
                        ];
                    }

                    $processed++;
                }
            }

            if (!empty($assignments)) {
                $DB->insert_records('employee_course_assign', $assignments);
            }

            $progress = round(($processed / $total) * 100);
            $DB->set_field('cohortsync_batch_jobs', 'progress', $progress, ['id' => $jobid]);

            gc_collect_cycles();
        }

        // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
        self::queue_user_sync_chunks($userids);
    }

    //assign category to cohort
    private static function execute_assign_category_to_cohort($jobid, $data) {
        global $DB;

        $cohortid   = $data['cohortid'];
        $categoryid = $data['categoryid'];
        $roleid     = $data['roleid'] ?? 5;
        $assignedby = $data['assignedby'] ?? 0;

        $exists = $DB->record_exists('cohort_category_assign', [
            'cohortid'   => $cohortid,
            'categoryid' => $categoryid
        ]);

        if (!$exists) {
            $assignment = (object)[
                'cohortid'    => $cohortid,
                'categoryid'  => $categoryid,
                'roleid'      => $roleid,
                'assignedby'  => $assignedby,
                'timecreated' => time(),
                'timemodified'=> time()
            ];
            $DB->insert_record('cohort_category_assign', $assignment);
        }

        cohort_category_assignment_changed::create([
            'context' => \context_system::instance(),
            'other'   => [
                'cohortid'   => $cohortid,
                'categoryid' => $categoryid,
            ]
        ])->trigger();

        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if (!empty($userids)) {
            // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
            self::queue_user_sync_chunks($userids);
        }

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    public static function get_job_status($jobid) {
        global $DB;
        return $DB->get_record('cohortsync_batch_jobs', ['id' => $jobid]);
    }

    //queue job as adhoc
    private static function queue_batch_job($jobid) {
        $task = new \local_cohortenrolsync\task\batch_process_task();
        $task->set_custom_data(['jobid' => $jobid]);
        \core\task\manager::queue_adhoc_task($task);
    }

    
    private static function calculate_total_items($operation_type, $data) {
        switch ($operation_type) {
            case 'assign_courses_to_users':
                return count($data['userids']) * count($data['courseids']);
            case 'assign_category_to_cohort':
            case 'assign_courses_to_cohort':
                return 1;
            default:
                return 1;
        }
    }

    private static function execute_remove_courses_from_users($jobid, $data) {
        global $DB;
        $userids   = $data['userids'];
        $courseids = $data['courseids'];

        foreach ($userids as $userid) {
            foreach ($courseids as $courseid) {
                $DB->delete_records('employee_course_assign', [
                    'userid' => $userid,
                    'courseid' => $courseid
                ]);
            }
        }

        // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
        self::queue_user_sync_chunks($userids);
        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    // remove course from cohort
    private static function execute_remove_courses_from_cohort($jobid, $data) {
        global $DB;
        $cohortid = $data['cohortid'];
        $courseids = $data['courseids'];

        foreach ($courseids as $courseid) {
            $DB->delete_records('cohort_course_assign', [
                'cohortid' => $cohortid,
                'courseid' => $courseid
            ]);

            // event per course removed
            \local_cohortenrolsync\event\cohort_course_assignment_changed::create([
                'context' => \context_system::instance(),
                'other'   => [
                    'cohortid' => $cohortid,
                    'courseid' => $courseid,
                ]
            ])->trigger();
        }

        // Sync members of this cohort.
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if (!empty($userids)) {
            // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
            self::queue_user_sync_chunks($userids);
        }

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    
    private static function execute_remove_category_from_cohort($jobid, $data) {
        global $DB;
        $cohortid = $data['cohortid'];
        $categoryid = $data['categoryid'];

        $DB->delete_records('cohort_category_assign', [
            'cohortid' => $cohortid,
            'categoryid' => $categoryid
        ]);

        cohort_category_assignment_changed::create([
            'context' => \context_system::instance(),
            'other'   => [
                'cohortid'   => $cohortid,
                'categoryid' => $categoryid,
            ]
        ])->trigger();

        // Sync members of this cohort.
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if (!empty($userids)) {
            // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
            self::queue_user_sync_chunks($userids);
        }

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    private static function execute_assign_category_to_users($jobid, $data) {
        global $DB;

        $userids = $data['userids'];
        $categoryid = $data['categoryid'];
        $roleid = $data['roleid'] ?? 5;
        $assignedby = $data['assignedby'] ?? 0;

        foreach ($userids as $userid) {
            $exists = $DB->record_exists('employee_category_assign', [
                'userid' => $userid,
                'categoryid' => $categoryid
            ]);

            if (!$exists) {
                $assignment = new \stdClass();
                $assignment->userid = $userid;
                $assignment->categoryid = $categoryid;
                $assignment->roleid = $roleid;
                $assignment->assignedby = $assignedby;
                $assignment->timecreated = time();
                $assignment->timemodified = time();
                $DB->insert_record('employee_category_assign', $assignment);
            }
        }

        // Sync all those users
        // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
        self::queue_user_sync_chunks($userids);

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    private static function execute_remove_users_from_cohort($jobid, $data) {
        global $DB;

        $cohortid = $data['cohortid'];
        $userids  = $data['userids'];

        foreach ($userids as $userid) {
            $DB->delete_records('cohort_members', [
                'cohortid' => $cohortid,
                'userid'   => $userid
            ]);
        }

        // Sync affected users.
        // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
        self::queue_user_sync_chunks($userids);

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    private static function execute_remove_users_from_category($jobid, $data) {
        global $DB;

        $categoryid = $data['categoryid'];
        $userids    = $data['userids'];

        foreach ($userids as $userid) {
            $DB->delete_records('employee_category_assign', [
                'userid'     => $userid,
                'categoryid' => $categoryid
            ]);
        }

        // Sync affected users
        // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
        self::queue_user_sync_chunks($userids);

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

    private static function execute_assign_courses_to_cohort($jobid, $data) {
        global $DB;

        $cohortid  = $data['cohortid'];
        $courseids = $data['courseids'];
        $roleid    = $data['roleid'] ?? 5;
        $assignedby = $data['assignedby'] ?? 0;

        foreach ($courseids as $courseid) {
            $exists = $DB->record_exists('cohort_course_assign', [
                'cohortid' => $cohortid,
                'courseid' => $courseid
            ]);

            if (!$exists) {
                $assignment = (object)[
                    'cohortid'    => $cohortid,
                    'courseid'    => $courseid,
                    'roleid'      => $roleid,
                    'assignedby'  => $assignedby,
                    'timecreated' => time(),
                    'timemodified'=> time()
                ];
                $DB->insert_record('cohort_course_assign', $assignment);

                // sync members
                \local_cohortenrolsync\event\cohort_course_assignment_changed::create([
                    'context' => \context_system::instance(),
                    'other'   => [
                        'cohortid' => $cohortid,
                        'courseid' => $courseid,
                    ]
                ])->trigger();
            }
        }

        // Sync members 
        $userids = $DB->get_fieldset('cohort_members', 'userid', ['cohortid' => $cohortid]);
        if (!empty($userids)) {
            // \local_cohortenrolsync\core\sync_engine::incremental_sync($userids);
            self::queue_user_sync_chunks($userids);
        }

        $DB->set_field('cohortsync_batch_jobs', 'progress', 100, ['id' => $jobid]);
    }

}
