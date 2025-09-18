<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_completion_report_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();


    if ($oldversion < 2025091900) {

        
        $table = new xmldb_table('local_coursehistory');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('personal_type', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('position_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('position_characteristics', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('administrative_position', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('position_lineofwork', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('position_type', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('position_level', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('course_type', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('course_group', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('course_name', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $table->add_field('enrolment_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('completion_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('number_of_days', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('intake_no', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('organizing_agency', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('venue', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('organizing_country', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('scholarship_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('scholarship_country', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('remarks', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('transmittal_letter_no', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('transmittal_letter_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('project_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('order_number', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('order_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('division_department', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('division_department_lvl1', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('division_department_lvl2', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('division_department_operational', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('division_department_op_lvl1', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('division_department_op_lvl2', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);


        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint.
        upgrade_plugin_savepoint(true, 2025091900, 'local', 'completion_report');
    }

    return true;
}
