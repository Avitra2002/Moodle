<?php

namespace local_cohortenrolsync\form;

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");

class bulk_assign_form extends \moodleform {

    protected function definition() {
        global $DB;

        $mform = $this->_form;

        // Operation type
        $mform->addElement('select', 'operation_type',
            get_string('operationtype', 'local_cohortenrolsync'), [
                // Assignments
                'assign_courses_to_users'   => get_string('assigncoursestousers', 'local_cohortenrolsync'),
                'assign_courses_to_cohort'  => get_string('assigncoursestocohort', 'local_cohortenrolsync'),
                'assign_category_to_cohort' => get_string('assigncategorytocohort', 'local_cohortenrolsync'),
                'assign_category_to_users'  => get_string('assigncategorytousers', 'local_cohortenrolsync'),
                // Removals
                'remove_courses_from_users'  => get_string('removecoursesfromusers', 'local_cohortenrolsync'),
                'remove_courses_from_cohort' => get_string('removecoursesfromcohort', 'local_cohortenrolsync'),
                'remove_category_from_cohort'=> get_string('removecategoryfromcohort', 'local_cohortenrolsync'),
                'remove_users_from_cohort'    => get_string('removeusersfromcohort', 'local_cohortenrolsync'),
                'remove_users_from_category'  => get_string('removeusersfromcategory', 'local_cohortenrolsync'),
            ]);
        $mform->setType('operation_type', PARAM_TEXT);

        // Datasets
        $courses    = $DB->get_records_menu('course', null, 'fullname ASC', 'id, fullname');
        $cohorts    = $DB->get_records_menu('cohort', null, 'name ASC', 'id, name');
        $categories = $DB->get_records_menu('course_categories', null, 'name ASC', 'id, name');
        $users      = $DB->get_records_sql_menu("
            SELECT id, CONCAT(firstname, ' ', lastname, ' (', username, ')') AS fullname
            FROM {user}
            WHERE deleted = 0 AND suspended = 0
            ORDER BY firstname ASC
        ");

        // Assign courses to users
        $mform->addElement('autocomplete', 'courseids', get_string('courses'), $courses, ['multiple' => true]);
        $mform->hideIf('courseids', 'operation_type', 'neq', 'assign_courses_to_users');

        $mform->addElement('autocomplete', 'userids', get_string('users'), $users, ['multiple' => true]);
        $mform->hideIf('userids', 'operation_type', 'neq', 'assign_courses_to_users');

        // Assign courses to cohort
        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $cohorts);
        $mform->hideIf('cohortid', 'operation_type', 'neq', 'assign_courses_to_cohort');

        $mform->addElement('autocomplete', 'courseids_cohort', get_string('courses'), $courses, ['multiple' => true]);
        $mform->hideIf('courseids_cohort', 'operation_type', 'neq', 'assign_courses_to_cohort');

        // Assign category to cohort
        $mform->addElement('select', 'categoryid', get_string('category'), $categories);
        $mform->hideIf('categoryid', 'operation_type', 'neq', 'assign_category_to_cohort');

        $mform->addElement('select', 'cohortid_category', get_string('cohort', 'cohort'), $cohorts);
        $mform->hideIf('cohortid_category', 'operation_type', 'neq', 'assign_category_to_cohort');

        // Assign category to users
        $mform->addElement('select', 'categoryid_users', get_string('category'), $categories);
        $mform->hideIf('categoryid_users', 'operation_type', 'neq', 'assign_category_to_users');

        $mform->addElement('autocomplete', 'userids_category', get_string('users'), $users, ['multiple' => true]);
        $mform->hideIf('userids_category', 'operation_type', 'neq', 'assign_category_to_users');

        // Remove courses from users
        $mform->addElement('autocomplete', 'remove_courseids_users', get_string('courses'), $courses, ['multiple' => true]);
        $mform->hideIf('remove_courseids_users', 'operation_type', 'neq', 'remove_courses_from_users');

        $mform->addElement('autocomplete', 'remove_userids_users', get_string('users'), $users, ['multiple' => true]);
        $mform->hideIf('remove_userids_users', 'operation_type', 'neq', 'remove_courses_from_users');

        // Remove courses from cohort
        $mform->addElement('select', 'remove_cohortid', get_string('cohort', 'cohort'), $cohorts);
        $mform->hideIf('remove_cohortid', 'operation_type', 'neq', 'remove_courses_from_cohort');

        $mform->addElement('autocomplete', 'remove_courseids_cohort', get_string('courses'), $courses, ['multiple' => true]);
        $mform->hideIf('remove_courseids_cohort', 'operation_type', 'neq', 'remove_courses_from_cohort');

        // Remove category from cohort
        $mform->addElement('select', 'remove_categoryid', get_string('category'), $categories);
        $mform->hideIf('remove_categoryid', 'operation_type', 'neq', 'remove_category_from_cohort');

        $mform->addElement('select', 'remove_cohortid_category', get_string('cohort', 'cohort'), $cohorts);
        $mform->hideIf('remove_cohortid_category', 'operation_type', 'neq', 'remove_category_from_cohort');

        // Remove users from cohort
        $mform->addElement('select', 'remove_cohortid_users', get_string('cohort', 'cohort'), $cohorts);
        $mform->hideIf('remove_cohortid_users', 'operation_type', 'neq', 'remove_users_from_cohort');

        $mform->addElement('autocomplete', 'remove_userids_cohort', get_string('users'), $users, ['multiple' => true]);
        $mform->hideIf('remove_userids_cohort', 'operation_type', 'neq', 'remove_users_from_cohort');

        // Remove users from category
        $mform->addElement('select', 'remove_categoryid_users', get_string('category'), $categories);
        $mform->hideIf('remove_categoryid_users', 'operation_type', 'neq', 'remove_users_from_category');

        $mform->addElement('autocomplete', 'remove_userids_category', get_string('users'), $users, ['multiple' => true]);
        $mform->hideIf('remove_userids_category', 'operation_type', 'neq', 'remove_users_from_category');

        $roles = get_assignable_roles(\context_system::instance());
        $mform->addElement('select', 'roleid', get_string('role'), $roles);
        if (isset($roles['student'])) {
            $mform->setDefault('roleid', $roles['student']);
        }

        $mform->addElement('text', 'reason', get_string('reason', 'local_cohortenrolsync'));
        $mform->setType('reason', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('submit'));
    }
}
