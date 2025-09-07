<?php
namespace local_cohortenrolsync\task;

use local_cohortenrolsync\core\sync_engine;

defined('MOODLE_INTERNAL') || die();

class sync_task extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('synctask', 'local_cohortenrolsync');
    }
    
    public function execute() {
        global $CFG;
        
        mtrace('Starting cohort sync task...');
        
        $start_time = microtime(true);
        
        
        $batch_size = get_config('local_cohortenrolsync', 'sync_batch_size') ?: 100;
        
        try {
            // Run full sync
            $results = sync_engine::full_sync($batch_size);
            
            // Log results
            mtrace('Sync completed successfully:');
            mtrace('- Users processed: ' . $results['users_processed']);
            mtrace('- Assignments added: ' . $results['total_assignments_added']);
            mtrace('- Assignments removed: ' . $results['total_assignments_removed']);
            
            if (!empty($results['errors'])) {
                mtrace('- Errors encountered: ' . count($results['errors']));
                foreach ($results['errors'] as $error) {
                    mtrace('  Error: ' . $error);
                }
            }
            
        } catch (\Exception $e) {
            mtrace('Sync task failed: ' . $e->getMessage());
            throw $e; // Re-throw so Moodle knows the task failed
        }
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        mtrace("Cohort sync task completed in {$duration} seconds");
    }
}