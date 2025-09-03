<?php
require_once('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$sectionid = required_param('sectionid', PARAM_INT);

$sql = "SELECT cm.id, m.name AS modulename, cm.instance
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = :courseid AND cm.section = :sectionid";

$params = ['courseid' => $courseid, 'sectionid' => $sectionid];
$module_records = $DB->get_records_sql($sql, $params);

$modules = [];
foreach ($module_records as $cmid => $module) {
    $table_name = $module->modulename; 

    // Query to fetch data from the dynamic table
    $module_data = $DB->get_record($table_name, ['id' => $module->instance]);

    if ($module_data) {
        $modules[$cmid] = [
            'id' => $module_data->id,
            'name' => $module_data->name,
        ];
    }
}

echo json_encode($modules);
?>
