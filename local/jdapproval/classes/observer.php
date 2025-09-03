<?php
namespace local_jdapproval;
defined('MOODLE_INTERNAL') || die();
use core_user;
use moodle_url;
use core\message\message;
class observer {
    private static $jdfieldid = null;
    private static $jdstatusfieldid = null;
    public static function on_user_updated(\core\event\user_updated $event) {
    global $DB;

    $userid = $event->objectid;
    error_log("🔔 user_updated triggered for user ID: $userid");

    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        error_log("❌ User not found for ID: $userid");
        return;
    }

    // Load JD upload and status field IDs (once)
    if (self::$jdfieldid === null) {
        self::$jdfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'JD_Upload']);
    }
    if (self::$jdstatusfieldid === null) {
        self::$jdstatusfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'jd_status']);
    }

    $cvfieldid = self::$jdfieldid;
    $statusfieldid = self::$jdstatusfieldid;

    if (!$cvfieldid || !$statusfieldid) {
        error_log("❌ JD or status field not found.");
        return;
    }

    $context = \context_user::instance($userid);
    $fs = get_file_storage();
    $filearea = 'files_' . $cvfieldid;

    $files = $fs->get_area_files($context->id, 'profilefield_file', $filearea, 0, 'timemodified DESC', false);

    $latestfile = null;
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            $latestfile = $file;
            break; // We only need the most recent one
        }
    }

    if (!$latestfile) {
        error_log("⚠️ No JD file found in file area for user $userid.");
        return;
    }

    // Check if new file (compare with stored hash)
    $currenthash = $latestfile->get_contenthash();
    $storedhash = get_user_preferences('jd_last_upload_hash', '', $userid);

    if ($storedhash === $currenthash) {
        error_log("ℹ️ JD file already processed for user $userid. Skipping.");
        return;
    }

    // ✅ New file detected
    error_log("🆕 New JD file uploaded for user ID: $userid");
    set_user_preference('jd_last_upload_hash', $currenthash, $userid);

    // Mark status as pending
    $record = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $statusfieldid]);
    if ($record) {
        $record->data = 'pending';
        $DB->update_record('user_info_data', $record);
    } else {
        $DB->insert_record('user_info_data', (object)[
            'userid' => $userid,
            'fieldid' => $statusfieldid,
            'data' => 'pending'
        ]);
    }

    error_log("✅ JD detected and status set to pending for user ID: $userid");

    // Notify admin
    $admin = get_admin();
    self::notify_admin_of_submission($userid, $admin);

    // Notify supervisor
    $supervisoremail = $DB->get_field('user_info_data', 'data', [
        'userid' => $userid,
        'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'supervisor_email'])
    ]);

    if ($supervisoremail) {
        $supervisor = $DB->get_record('user', ['email' => $supervisoremail, 'deleted' => 0, 'suspended' => 0]);
        if ($supervisor) {
            self::notify_supervisor_of_submission($user, $supervisor);
        } else {
            error_log("⚠️ Supervisor not found for email: $supervisoremail");
        }
    }
}

    public static function notify_admin_of_submission(int $userid, \stdClass $admin) {
    global $DB;
    try {
        error_log("✅ Starting notification process for admin: $userid");
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subject = 'New JD Submitted';
        $fullmessage = fullname($user) . " has uploaded or updated their JD.\nPlease review and approve.";
        $message = new \core\message\message();
        $message->component         = 'local_jdapproval';
        $message->name              = 'jd_status_change'; // Must match your message provider
        $message->userfrom          = core_user::get_noreply_user();
        $message->userto            = $admin;
        $message->subject           = $subject;
        $message->fullmessage       = $fullmessage;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = nl2br(htmlspecialchars($fullmessage));
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        $message->contexturl        = new \moodle_url('/local/jdapproval/approve.php');
        $message->contexturlname    = 'JD Approval Panel';
        $msgid = message_send($message);
        if ($msgid === false) {
            error_log("❌ Failed to send message to admin ({$admin->id})");
            
        } else {
            error_log("📨 Success! Notification sent to admin ({$admin->id}), msg ID: $msgid");
        }
        return $msgid;
    } catch (\Exception $e) {
        error_log("🔥 Exception in notify_admin_of_submission: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}
private static function notify_supervisor_of_submission(\stdClass $user, \stdClass $supervisor) {
    $subject = 'New JD Submitted by ' . fullname($user);
    $fullmessage = fullname($user) . " has submitted or updated their JD. Please review it.";
    $message = new \core\message\message();
    $message->component         = 'local_jdapproval';
    $message->name              = 'jd_status_change';
    $message->userfrom          = core_user::get_noreply_user();
    $message->userto            = $supervisor;
    $message->subject           = $subject;
    $message->fullmessage       = $fullmessage;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml   = nl2br($fullmessage);
    $message->smallmessage      = $subject;
    $message->notification      = 1;
    $message->contexturl        = new moodle_url('/local/jdapproval/approve.php');
    $message->contexturlname    = 'JD Approval Panel';
    $msgid = message_send($message);
    error_log("📨 Notification sent to supervisor ({$supervisor->id}), msg ID: " . var_export($msgid, true));
}
}