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

$download = optional_param('download', '', PARAM_ALPHA);
$search   = optional_param('search', '', PARAM_TEXT);

$table = new \local_completion_report\tables\history_report('externalhistory', $search);


$baseurl = new moodle_url('/local/completion_report/report.php');
if (!empty($search)) {
    $baseurl->param('search', $search);
}
$table->define_baseurl($baseurl);

// ✅ Check if downloading BEFORE any output
$table->is_downloading($download, 'external_training_report', 'External Training Report');

if ($table->is_downloading()) {
    // Export mode - search is already applied in constructor
    $table->out(0, false);
    exit;
}

// Now safe to output HTML
echo $OUTPUT->header();

// Search form
echo '<form method="get" action="">';
echo '<input type="text" name="search" value="' . s($search) . '" placeholder="Search by full name">';
echo '<input type="submit" value="Search">';
echo '</form>';

// Normal paginated view
$table->out(50, true);

echo $OUTPUT->footer();