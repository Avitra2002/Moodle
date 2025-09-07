<?php

namespace local_cohortenrolsync\task;

use local_cohortenrolsync\services\batch_manager;

defined('MOODLE_INTERNAL') || die();


class batch_process_task extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('batchprocesstask', 'local_cohortenrolsync');
    }
    
    
    public function execute() { //called by cron in background
        $data = $this->get_custom_data();
        $jobid = $data->jobid;
        
        mtrace('Processing batch job: ' . $jobid);
        
        try {
            batch_manager::execute_batch_job($jobid);
            mtrace('Batch job completed successfully: ' . $jobid);
            
        } catch (\Exception $e) {
            mtrace('Batch job failed: ' . $jobid . ' - ' . $e->getMessage());
            throw $e;
        }
    }
}