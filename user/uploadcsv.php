<?php
require_once('./../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once('uploadcv_form.php');

require_login();

$userid = $USER->id;
$context = context_user::instance($userid);

$PAGE->set_url(new moodle_url('/uploadcv.php'));
$PAGE->set_context($context);
$PAGE->set_title('Upload Your CV');
$PAGE->set_heading('Upload Your CV');

$mform = new uploadcv_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $mform->get_data()) {
    // Get draft item ID from form field (must use lowercase prefix: profile_field_)
    $draftitemid = file_get_submitted_draft_itemid('profile_field_CVUpload');

    $options = array(
        'subdirs' => 0,
        'maxfiles' => 1,
        'maxbytes' => 800000000,
        'accepted_types' => ['.pdf', '.doc', '.docx']
    );

    // Save file into the user profile file area
    file_save_draft_area_files(
        $draftitemid,              // Draft item ID
        $context->id,              // User context
        'user',                    // Component
        'profile',                 // File area
        $draftitemid,              // File item ID = use same as draft ID
        $options
    );

    // Store the real file item ID into the custom field
    $user = core_user::get_user($userid);
    $user->profile_field_CVUpload = $draftitemid;

    profile_save_data($user);  // This writes the item ID to the DB

    redirect('/user/uploadcsv.php', 'CV uploaded successfully!', 2);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
