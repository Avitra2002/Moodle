<?php
defined('MOODLE_INTERNAL') || die();

$observers = [

    // Cohort membership
    ['eventname' => '\core\event\cohort_member_added',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_member_added'],

    ['eventname' => '\core\event\cohort_member_removed',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_member_removed'],

    // Cohort lifecycle
    ['eventname' => '\core\event\cohort_created',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_created'],

    ['eventname' => '\core\event\cohort_updated',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_updated'],

    ['eventname' => '\core\event\cohort_deleted',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_deleted'],

    // Course lifecycle
    ['eventname' => '\core\event\course_created',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::course_created'],

    ['eventname' => '\core\event\course_updated',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::course_updated'],

    ['eventname' => '\core\event\course_deleted',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::course_deleted'],

    // Category lifecycle
    ['eventname' => '\core\event\course_category_created',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::category_created'],

    ['eventname' => '\core\event\course_category_updated',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::category_updated'],

    ['eventname' => '\core\event\course_category_deleted',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::category_deleted'],

    // User lifecycle
    ['eventname' => '\core\event\user_created',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_created'],

    ['eventname' => '\core\event\user_updated',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_updated'],

    ['eventname' => '\core\event\user_deleted',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_deleted'],

    // User enrolments
    ['eventname' => '\core\event\user_enrolment_created',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_enrolment_created'],

    ['eventname' => '\core\event\user_enrolment_deleted',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_enrolment_deleted'],

    ['eventname' => '\core\event\user_enrolment_updated',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::user_enrolment_updated'],

    // Custom mapping events
    ['eventname' => '\local_cohortenrolsync\event\cohort_category_assignment_changed',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_category_assignment_changed'],

    ['eventname' => '\local_cohortenrolsync\event\cohort_course_assignment_changed',
     'callback'  => '\local_cohortenrolsync\observers\event_handler::cohort_course_assignment_changed'],
];
