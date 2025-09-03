<?php
require_once('../../config.php');
 
require_login();
$systemctx = context_system::instance();
#require_capability('moodle/user:update', $systemctx); // Only admins
require_capability('local/approval:reviewcv', $systemctx);

global $DB, $OUTPUT, $PAGE, $USER;

$PAGE->set_context($systemctx);
$PAGE->set_url(new moodle_url('/local/approval/approve.php'));
$PAGE->set_title('CV Approval Panel');
$PAGE->set_heading('CV Approval Panel');

// Function to send bell notification and message to user on CV status change
function notify_user_cv_status_change(int $userid, string $status, stdClass $adminuser) {
    global $DB;

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    $subject = 'CV ' . ucfirst($status);
    $fullmessage = "Hello " . fullname($user) . ",\n\nYour CV has been {$status} by the administrator.\n\nRegards,\n" . fullname($adminuser);

    $from = core_user::get_support_user();

    $message = new \core\message\message();
    $message->component         = 'local_approval';      // Your plugin/component name
    $message->name              = 'cv_status_change';    // Arbitrary internal message name
    $message->userfrom          = $from;
    $message->userto            = $user;
    $message->subject           = $subject;
    $message->fullmessage       = $fullmessage;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml   = nl2br(htmlentities($fullmessage));
    $message->smallmessage      = $subject;
    $message->notification      = 1;                     // Mark as notification (shows in bell)
    $message->contexturl        = new moodle_url('/user/profile.php', ['id' => $user->id]);
    $message->contexturlname    = 'View your profile';

    $result = message_send($message);
	/* if (!$result) {
		debugging("Failed to send message for user ID $userid");
	} else {
		debugging("Sent message for user ID $userid");
	} */
	
	
}

// Get field IDs
$cvfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'CV_Upload']);
$statusfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'cv_status']);

// Handle approval actions
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($userid && in_array($action, ['approve', 'reject'])) {
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    $record = $DB->get_record('user_info_data', [
        'userid' => $userid,
        'fieldid' => $statusfieldid
    ]);

    if ($record) {
        $record->data = $status;
        $DB->update_record('user_info_data', $record);
    } else {
        $new = (object)[
            'userid' => $userid,
            'fieldid' => $statusfieldid,
            'data' => $status
        ];
        $DB->insert_record('user_info_data', $new);
    }

    // Notify user about status change
    notify_user_cv_status_change($userid, $status, $USER);

    redirect(new moodle_url('/local/approval/approve.php'), "User status updated and notified.");
    
   
}

/**
$sql = "SELECT u.id, u.firstname, u.lastname, u.email,u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               cv.data AS cvfilename,
               st.data AS cvstatus
        FROM {user} u
        JOIN {user_info_data} cv ON cv.userid = u.id AND cv.fieldid = :cvfield
        LEFT JOIN {user_info_data} st ON st.userid = u.id AND st.fieldid = :statusfield
        WHERE cv.data IS NOT NULL";
**/
$isadmin = is_siteadmin();
$params = ['cvfield' => $cvfieldid, 'statusfield' => $statusfieldid];
$supervisorfield = $DB->get_record('user_info_field', ['shortname' => 'supervisor_email'], 'id', IGNORE_MISSING);

if ($supervisorfield) {
    $supervisoremailfieldid = $supervisorfield->id;
} else {
   // throw new moodle_exception('Custom profile field "supervisor_email" not found');
   $supervisoremailfieldid = '';
}


$sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               cv.data AS cvfilename,
               st.data AS cvstatus
        FROM {user} u
        JOIN {user_info_data} cv ON cv.userid = u.id AND cv.fieldid = :cvfield
        LEFT JOIN {user_info_data} st ON st.userid = u.id AND st.fieldid = :statusfield";

if (!$isadmin) {
    // Join to supervisor_email field to restrict by manager email
    $sql .= " JOIN {user_info_data} sup ON sup.userid = u.id AND sup.fieldid = :supfield
              WHERE cv.data IS NOT NULL AND LOWER(sup.data) = LOWER(:manageremail)";
    $params['supfield'] = $supervisoremailfieldid;
    $params['manageremail'] = $USER->email;
} else {
    $sql .= " WHERE cv.data IS NOT NULL";
}


$users = $DB->get_records_sql($sql, $params);

echo $OUTPUT->header();
//echo $OUTPUT->heading('CV Approval Dashboard');

$table = new html_table();
$table->head = ['Name', 'Email', 'CV File', 'Status', 'Action'];

foreach ($users as $user) {
    $context = context_user::instance($user->id);
    $fs = get_file_storage();
    $cvlink = html_writer::span('❌ Not uploaded', 'text-danger');

    // This is the correct file area: 'profilefield_<fieldid>'
   //$filearea = 'profilefield_' . $cvfieldid;
   $filearea = 'files_' . $cvfieldid;
    $files = $fs->get_area_files(
        $context->id,
        'profilefield_file',
        $filearea,
        0,            
        'filename',
        false        
    );

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            $cvurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
            $cvlink = html_writer::link($cvurl, '📎 View CV');
            error_log("Found file: " . $file->get_filename() . " in area: " . $file->get_filearea());
            error_log("Context ID: $context->id, File area: $filearea");

            break;
        }
    }

    $status = $user->cvstatus ?? 'pending';

    if ($status === 'approved') {
        $actions = '✔️ Approved';
    } elseif ($status === 'rejected') {
        $actions = '❌ Rejected';
    } else {
        $approveurl = new moodle_url('/local/approval/approve.php', ['userid' => $user->id, 'action' => 'approve']);
        $rejecturl = new moodle_url('/local/approval/approve.php', ['userid' => $user->id, 'action' => 'reject']);

        $actions = html_writer::link(
            $approveurl,
            '✅ Approve',
            ['onclick' => "return confirm('Are you sure you want to approve this CV?');"]
        ) . ' | ' .
        html_writer::link(
            $rejecturl,
            '❌ Reject',
            ['onclick' => "return confirm('Are you sure you want to reject this CV?');"]
        );
    }

    $table->data[] = [
        fullname($user),
        $user->email,
        $cvlink,
        ucfirst($status),
        $actions
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
