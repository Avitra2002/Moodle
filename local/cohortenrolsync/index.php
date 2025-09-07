<?php

require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/cohortenrolsync/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_cohortenrolsync'));
$PAGE->set_heading(get_string('pluginname', 'local_cohortenrolsync'));

$form = new \local_cohortenrolsync\form\bulk_assign_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'local_cohortenrolsync']));
} else if ($data = $form->get_data()) {
    $operationdata = [
        'roleid'    => $data->roleid,
        'reason'    => $data->reason,
        'assignedby'=> $USER->id,
    ];

    switch ($data->operation_type) {
        case 'assign_courses_to_users':
            $operationdata['userids']   = $data->userids ?? [];
            $operationdata['courseids'] = $data->courseids ?? [];
            break;

        case 'assign_courses_to_cohort':
            $operationdata['cohortid']  = $data->cohortid ?? null;
            $operationdata['courseids'] = $data->courseids_cohort ?? [];
            break;

        case 'assign_category_to_cohort':
            $operationdata['cohortid']   = $data->cohortid_category ?? null;
            $operationdata['categoryid'] = $data->categoryid ?? null;
            break;

        case 'assign_category_to_users':
            $operationdata['userids']   = $data->userids_category ?? [];
            $operationdata['categoryid']= $data->categoryid_users ?? null;
            break;

        case 'remove_courses_from_users':
            $operationdata['userids']   = $data->remove_userids_users ?? [];
            $operationdata['courseids'] = $data->remove_courseids_users ?? [];
            break;

        case 'remove_courses_from_cohort':
            $operationdata['cohortid']  = $data->remove_cohortid ?? null;
            $operationdata['courseids'] = $data->remove_courseids_cohort ?? [];
            break;

        case 'remove_category_from_cohort':
            $operationdata['cohortid']   = $data->remove_cohortid_category ?? null;
            $operationdata['categoryid'] = $data->remove_categoryid ?? null;
            break;

        case 'remove_users_from_cohort':
            $operationdata['cohortid'] = $data->remove_cohortid_users ?? null;
            $operationdata['userids']  = $data->remove_userids_cohort ?? [];
            break;

        case 'remove_users_from_category':
            $operationdata['categoryid'] = $data->remove_categoryid_users ?? null;
            $operationdata['userids']    = $data->remove_userids_category ?? [];
            break;
    }

    $jobid = \local_cohortenrolsync\services\batch_manager::create_bulk_job(
        $data->operation_type,
        $operationdata
    );

    // redirect(new moodle_url('/local/cohortenrolsync/jobstatus.php', ['id' => $jobid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkassign', 'local_cohortenrolsync'));
$form->display();
echo $OUTPUT->footer();
