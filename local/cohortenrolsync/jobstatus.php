<?php

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/cohortenrolsync/jobstatus.php', ['id' => $id]));
$PAGE->set_title(get_string('jobstatus', 'local_cohortenrolsync'));
$PAGE->set_heading(get_string('jobstatus', 'local_cohortenrolsync'));

$job = \local_cohortenrolsync\services\batch_manager::get_job_status($id);
if (!$job) {
    print_error('Job not found');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('jobstatus', 'local_cohortenrolsync'));

$table = new html_table();
$table->head = ['Field', 'Value'];
$table->data = [
    ['ID', $job->id],
    ['Type', $job->operation_type],
    ['Status', $job->status],
    ['Progress', $job->progress . '%'],
    ['Created', userdate($job->timecreated)],
    ['Modified', userdate($job->timemodified)],
];

if (!empty($job->error_message)) {
    $table->data[] = ['Error', s($job->error_message)];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
