<?php
require_once('../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/local/completion_report/classes/form/upload_history_form.php');

require_login();
$context = context_system::instance();
require_capability('moodle/user:update', $context); // Only admins

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/completion_report/import.php'));
$PAGE->set_title('Import External Training History');
$PAGE->set_heading('Import External Training History');

echo $OUTPUT->header();

$form = new \local_completion_report\form\upload_history_form();

$requiredheaders = [
    'email',
    'Full Name',
    'Personal Type',
    'Position Number',
    'Position Characteristics',
    'Administrative Position',
    'Position Line-of-work',
    'Position-Type',
    'Position Level',
    'Course Type',
    'Course Group',
    'Course Name',
    'Enrolment Date',
    'Completion Date',
    'Number of Days',
    'Intake No.',
    'Organizing Agency',
    'Venue',
    'Organizing Country',
    'Name of Scholarship',
    'Scholarship Sponsoring Country',
    'Remarks',
    'Transmittal Letter No.',
    'Date of Transmittal Letter',
    'Project Name',
    'Order Number',
    'Order Date',
    'Division/Department',
    'One level below the Division/Department',
    'Two levels below the Division/Department',
    'Division/Department (Operational)',
    'One level below the Division/Department (Operational)',
    'Two levels below the Division/Department (Operational)',
];

echo $OUTPUT->notification(
    html_writer::tag('strong', '📌 Expected CSV headers (first row):') . '<br>' .
    implode(' <span style="color:#999">|</span> ', $requiredheaders),
    'notifymessage'
);


if ($form->is_cancelled()) {
    redirect($PAGE->url);
} else if ($data = $form->get_data()) {

    $iid = csv_import_reader::get_new_iid('uploadcsv');
    $csv = new csv_import_reader($iid, 'uploadcsv');

    $content = $form->get_file_content('csvfile');
    $status = $csv->load_csv_content($content, 'utf-8', ',');
    unset($content);

    if ($error = $csv->get_error()) {
        echo $OUTPUT->notification("CSV load failed: " . $error, 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    $csv->init();
    $headers = $csv->get_columns();

    if (!$headers) {
        echo $OUTPUT->notification("CSV missing headers.", 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }

    $headers = array_map('trim', $headers);

    $missing = array_diff($requiredheaders, $headers);

    $canprocess = true;

    if (!empty($missing)) {
        echo $OUTPUT->notification(
            "❌ Import failed. Missing required columns: " . implode(', ', $missing),
            'notifyproblem'
        );
        // echo $OUTPUT->footer();
        // exit;
        $canprocess=false;
    }

    $rownum = 1;
    $inserted = 0; $linked = 0; $unlinked = 0;

    if($canprocess){

        while ($row = $csv->next()) {
            $rownum++;
            $record = array_combine($headers, $row);

            // Match user by email
            $email = strtolower(trim($record['email'] ?? ''));
            $user = null;
            if ($email) {
                $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            }

            // Prepare record
            $new = new stdClass();
            $new->userid       = $user ? $user->id : null;
            $new->email        = $email ?: null;
            $new->fullname     = $record['Full Name'] ?? null;
            $new->personal_type = $record['Personal Type'] ?? null;
            $new->position_number = $record['Position Number'] ?? null;
            $new->position_characteristics = $record['Position Characteristics'] ?? null;
            $new->administrative_position = $record['Administrative Position'] ?? null;
            $new->position_lineofwork = $record['Position Line-of-work'] ?? null;
            $new->position_type = $record['Position-Type'] ?? null;
            $new->position_level = $record['Position Level'] ?? null;
            $new->course_type = $record['Course Type'] ?? null;
            $new->course_group = $record['Course Group'] ?? null;
            $new->course_name  = $record['Course Name'] ?? null;
            $new->enrolment_date = !empty($record['Enrolment Date']) ? strtotime($record['Enrolment Date']) : null;
            $new->completion_date = !empty($record['Completion Date']) ? strtotime($record['Completion Date']) : null;
            $new->number_of_days = $record['Number of Days'] ?? null;
            $new->intake_no = $record['Intake No.'] ?? null;
            $new->organizing_agency = $record['Organizing Agency'] ?? null;
            $new->venue = $record['Venue'] ?? null;
            $new->organizing_country = $record['Organizing Country'] ?? null;
            $new->scholarship_name = $record['Name of Scholarship'] ?? null;
            $new->scholarship_country = $record['Scholarship Sponsoring Country'] ?? null;
            $new->remarks = $record['Remarks'] ?? null;
            $new->transmittal_letter_no = $record['Transmittal Letter No.'] ?? null;
            $new->transmittal_letter_date = !empty($record['Date of Transmittal Letter']) ? strtotime($record['Date of Transmittal Letter']) : null;
            $new->project_name = $record['Project Name'] ?? null;
            $new->order_number = $record['Order Number'] ?? null;
            $new->order_date = !empty($record['Order Date']) ? strtotime($record['Order Date']) : null;
            $new->division_department = $record['Division/Department'] ?? null;
            $new->division_department_lvl1 = $record['One level below the Division/Department'] ?? null;
            $new->division_department_lvl2 = $record['Two levels below the Division/Department'] ?? null;
            $new->division_department_operational = $record['Division/Department (Operational)'] ?? null;
            $new->division_department_op_lvl1 = $record['One level below the Division/Department (Operational)'] ?? null;
            $new->division_department_op_lvl2 = $record['Two levels below the Division/Department (Operational)'] ?? null;
            $new->timecreated = time();

            $DB->insert_record('local_coursehistory', $new);

            $inserted++;
            if ($user) {
                $linked++;
            } else {
                $unlinked++;
            }
        }

        $csv->cleanup(true);

        echo $OUTPUT->notification("✅ Import completed: $inserted records ($linked linked, $unlinked unlinked).", 'notifysuccess');
    }
}

$form->display();
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/completion_report/report.php'),
        '📑 Go to Training History Report',
        ['class' => 'btn btn-primary', 'style' => 'margin:20px 0; display:inline-block;']
    ),
    'report-nav-btn'
);

echo $OUTPUT->footer();
