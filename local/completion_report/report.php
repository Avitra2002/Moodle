<?php
require_once('../../config.php');
require_once("$CFG->libdir/tablelib.php");

require_login();
$context = context_system::instance();
require_capability('moodle/user:update', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/completion_report/report.php'));
$PAGE->set_title('External Training Report');
$PAGE->set_heading('External Training Report');

echo $OUTPUT->header();

$table = new \local_completion_report\tables\history_report('externalhistory');
$table->define_baseurl($PAGE->url);
$table->out(50, true);

echo $OUTPUT->footer();
