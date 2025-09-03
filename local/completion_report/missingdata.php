<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_title('Missing Data Report');
require_capability('moodle/site:viewreports', $context);

$fields = $DB->get_records('user_info_field');
$fieldmap = [];
foreach ($fields as $field) {
    $fieldmap[$field->id] = $field->shortname;
}

$users = $DB->get_records_select('user', "deleted = 0 AND username <> 'guest' AND id!=2");

if (optional_param('download', '', PARAM_TEXT) === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="user_custom_fields_report.csv"');
	$output = fopen('php://output', 'w');

	
	fwrite($output, "\xEF\xBB\xBF");
    
    $headers = ['ID', 'Username', 'Email', 'First Name', 'Last Name'];
    foreach ($fieldmap as $shortname) {
        $headers[] = $shortname;
    }
    fputcsv($output, $headers);

    foreach ($users as $user) {
        $row = [$user->id, $user->username, $user->email, $user->firstname, $user->lastname];
        foreach ($fieldmap as $fieldid => $shortname) {
            $data = $DB->get_field('user_info_data', 'data', ['userid' => $user->id, 'fieldid' => $fieldid], IGNORE_MISSING);
            $row[] = $data;
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

echo $OUTPUT->header();
echo '<br><br><h2>Missing Data Report</h2><br><br>';
echo '<form method="get"><input type="text" name="search" placeholder="Search..." value="' . htmlspecialchars(optional_param('search', '', PARAM_RAW)) . '"><button type="submit">Search</button> <a href="?download=csv">Download CSV</a></form><br>';

$search = optional_param('search', '', PARAM_RAW);
echo '<div style="overflow-x:auto;">';
echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%;">';

echo '<tr style="background:#f2f2f2;">';
echo '<th>ID</th><th>Username</th><th>Email</th><th>First Name</th><th>Last Name</th>';
foreach ($fieldmap as $shortname) {
    echo '<th>' . htmlspecialchars($shortname) . '</th>';
}
echo '</tr>';

foreach ($users as $user) {
    $usertext = strtolower($user->username . ' ' . $user->email . ' ' . $user->firstname . ' ' . $user->lastname);
    if ($search && strpos($usertext, strtolower($search)) === false) {
        continue;
    }

    echo '<tr>';
    echo '<td>' . $user->id . '</td>';
    echo '<td>' . htmlspecialchars($user->username) . '</td>';
    echo '<td>' . htmlspecialchars($user->email) . '</td>';
    echo '<td>' . htmlspecialchars($user->firstname) . '</td>';
    echo '<td>' . htmlspecialchars($user->lastname) . '</td>';

    foreach ($fieldmap as $fieldid => $shortname) {
        $data = $DB->get_field('user_info_data', 'data', ['userid' => $user->id, 'fieldid' => $fieldid], IGNORE_MISSING);
        $highlight = ($data === false || $data === '') ? 'background-color: #ffcccc;' : '';
        echo '<td style="' . $highlight . '">' . htmlspecialchars($data) . '</td>';
    }
    echo '</tr>';
}

echo '</table>';
echo '</div>';
echo $OUTPUT->footer();
