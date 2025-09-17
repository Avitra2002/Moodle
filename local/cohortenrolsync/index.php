<?php

require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/cohortenrolsync/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_cohortenrolsync'));
$PAGE->set_heading('Bulk Enrolment Form');

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

    echo $OUTPUT->header();

    $operationname = format_string($data->operation_type);
    echo html_writer::tag('div',
        "✅ The operation <strong>{$operationname}</strong> has been submitted.<br>
        Please wait up to <strong>5 minutes</strong> for the synchronisation to complete.",
        ['class' => 'alert alert-success', 'style' => 'margin:20px 0; padding:15px;']
    );

    $dashboardurl = new moodle_url('/local/customdashboard/index.php');
    echo html_writer::link($dashboardurl, 'Return to Dashboard', [
        'class' => 'btn btn-primary',
        'style' => 'margin-top:20px; display:inline-block;'
    ]);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
