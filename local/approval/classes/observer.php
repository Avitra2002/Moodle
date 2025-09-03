<?php

namespace local_approval;

defined('MOODLE_INTERNAL') || die();
use core_user;
use moodle_url;
use core\message\message;

class observer {
    private static $cvfieldid = null;
    private static $statusfieldid = null;

    public static function on_user_updated(\core\event\user_updated $event) {
    global $DB;

    $userid = $event->objectid;
    error_log("🔔 user_updated triggered for user ID: $userid");

    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        error_log("❌ User not found for ID: $userid");
        return;
    }

    // Load field IDs only once
    if (self::$cvfieldid === null) {
        self::$cvfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'CV_Upload']);
    }
    if (self::$statusfieldid === null) {
        self::$statusfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'cv_status']);
    }

    $cvfieldid = self::$cvfieldid;
    $statusfieldid = self::$statusfieldid;

    if (!$cvfieldid || !$statusfieldid) {
        error_log("❌ CV or status field not found.");
        return;
    }

    // Get CV file from user's profile field filearea
    $context = \context_user::instance($userid);
    $fs = get_file_storage();
    $filearea = 'files_' . $cvfieldid;

    $files = $fs->get_area_files($context->id, 'profilefield_file', $filearea, 0, 'timemodified DESC', false);

    $latestfile = null;
    foreach ($files as $file) {
        if (!$file->is_directory()) {
            $latestfile = $file;
            break; // Get only the most recent file
        }
    }

    if (!$latestfile) {
        error_log("⚠️ No CV file found in file area for user $userid.");
        return;
    }

    // Detect new upload by comparing contenthash
    $currenthash = $latestfile->get_contenthash();
    $storedhash = get_user_preferences('cv_last_upload_hash', '', $userid);

    if ($storedhash === $currenthash) {
        error_log("ℹ️ CV file already processed for user $userid. Skipping.");
        return;
    }

    // ✅ New CV file uploaded
    error_log("🆕 New CV file uploaded for user ID: $userid");
    set_user_preference('cv_last_upload_hash', $currenthash, $userid);

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

    error_log("✅ CV detected and status set to pending for user ID: $userid");

    // Notify admin
    $admin = get_admin();
    self::notify_admin_of_submission($userid, $admin);

    // Notify supervisor
    $supervisoremail = $DB->get_field('user_info_data', 'data', [
        'userid' => $userid,
        'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'supervisor_email'])
    ]);

    if ($supervisoremail) {
        $supervisor = $DB->get_record('user', [
            'email' => $supervisoremail,
            'deleted' => 0,
            'suspended' => 0
        ]);

        if ($supervisor) {
            self::notify_supervisor_of_submission($user, $supervisor);
        } else {
            error_log("⚠️ Supervisor not found for email: $supervisoremail");
        }
    }
}




    private static function notify_admin_of_submission(int $userid, \stdClass $admin) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $subject = 'New CV Submitted';
        $fullmessage = fullname($user) . " has uploaded or updated their CV.\nPlease review and approve.";

        $message = new \core\message\message();
        $message->component         = 'local_approval';
        $message->name              = 'cv_status_change';
        $message->userfrom          = core_user::get_noreply_user();
        $message->userto            = $admin;
        $message->subject           = $subject;
        $message->fullmessage       = $fullmessage;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = nl2br($fullmessage);
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        $message->contexturl        = new \moodle_url('/local/approval/approve.php');
        $message->contexturlname    = 'CV Approval Panel';

        $msgid = message_send($message);
        error_log("📨 Notification sent to admin ({$admin->id}), msg ID: " . var_export($msgid, true));
    }


private static function notify_supervisor_of_submission(\stdClass $user, \stdClass $supervisor) {
    $subject = 'New CV Submitted by ' . fullname($user);
    $fullmessage = fullname($user) . " has submitted or updated their CV. Please review it.";

    $message = new \core\message\message();
    $message->component         = 'local_approval';
    $message->name              = 'cv_status_change';
    $message->userfrom          = core_user::get_noreply_user();
    $message->userto            = $supervisor;
    $message->subject           = $subject;
    $message->fullmessage       = $fullmessage;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml   = nl2br($fullmessage);
    $message->smallmessage      = $subject;
    $message->notification      = 1;
    $message->contexturl        = new moodle_url('/local/approval/approve.php');
    $message->contexturlname    = 'CV Approval Panel';

    $msgid = message_send($message);
    error_log("📨 Notification sent to supervisor ({$supervisor->id}), msg ID: " . var_export($msgid, true));
}

}
