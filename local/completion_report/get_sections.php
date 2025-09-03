<?php

require_once('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);

// Get sections with non-empty names
$sections = $DB->get_records_sql_menu("
    SELECT id, name 
    FROM {course_sections} 
    WHERE course = :courseid AND (name IS NOT NULL AND name != '') 
    ORDER BY section ASC", 
    ['courseid' => $courseid]
);

echo json_encode($sections);

