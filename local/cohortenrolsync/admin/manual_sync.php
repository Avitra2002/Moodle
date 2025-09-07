<?php

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

use local_cohortenrolsync\core\sync_engine;

admin_externalpage_setup('local_cohortenrolsync');

$PAGE->set_title(get_string('manualsync', 'local_cohortenrolsync'));
$PAGE->set_heading(get_string('manualsync', 'local_cohortenrolsync'));

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualsync', 'local_cohortenrolsync'));

if ($action === 'sync' && confirm_sesskey()) {
    echo $OUTPUT->notification('Starting sync...', 'info');
    
    $start_time = microtime(true);
    
    try {
        $results = sync_engine::full_sync();
        
        $duration = round(microtime(true) - $start_time, 2);
        
        echo html_writer::start_tag('div', ['class' => 'alert alert-success']);
        echo html_writer::tag('h4', 'Sync completed successfully!');
        echo html_writer::tag('p', "Duration: {$duration} seconds");
        echo html_writer::tag('p', "Users processed: {$results['users_processed']}");
        echo html_writer::tag('p', "Assignments added: {$results['total_assignments_added']}");
        echo html_writer::tag('p', "Assignments removed: {$results['total_assignments_removed']}");
        
        if (!empty($results['errors'])) {
            echo html_writer::tag('p', 'Errors: ' . count($results['errors']));
            echo html_writer::start_tag('ul');
            foreach ($results['errors'] as $error) {
                echo html_writer::tag('li', $error);
            }
            echo html_writer::end_tag('ul');
        }
        echo html_writer::end_tag('div');
        
    } catch (Exception $e) {
        echo $OUTPUT->notification('Sync failed: ' . $e->getMessage(), 'error');
    }
    
} else {
    // Show sync status and button
    $stats = sync_engine::get_sync_status();
    
    echo html_writer::start_tag('div', ['class' => 'card']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);
    
    echo html_writer::tag('h5', 'Current Status', ['class' => 'card-title']);
    
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Total course assignments: ' . $stats['total_course_assignments']);
    echo html_writer::tag('li', 'Total category assignments: ' . $stats['total_category_assignments']);
    echo html_writer::end_tag('ul');
    
    // Sync button
    $sync_url = new moodle_url('/local/cohortenrolsync/admin/manual_sync.php', 
        ['action' => 'sync', 'sesskey' => sesskey()]);
    
    echo html_writer::tag('p', 'Click below to run a full sync of all user assignments:');
    echo html_writer::link($sync_url, 'Run Full Sync Now', 
        ['class' => 'btn btn-primary', 'onclick' => 'return confirm("This may take several minutes. Continue?")']);
    
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();