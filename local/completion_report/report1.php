<?php
require_once('../../config.php');
require "$CFG->libdir/tablelib.php";
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once('lib.php');
require_once('classes/form/filter_module_activity_report.php');
require_login();
$session_courseid = isset($_SESSION['coursename']) ? $_SESSION['coursename'] : '';
$session_sectionid = isset($_SESSION['sectionname']) ? $_SESSION['sectionname'] : '';
$session_modulename = isset($_SESSION['modulename']) ? $_SESSION['modulename'] : '';

echo "<script>
    var sessionCourseId = '$session_courseid';
    var sessionSectionId = '$session_sectionid';
    var sessionModuleName = '$session_modulename';
</script>";

if (!is_siteadmin()) {
    throw new moodle_exception(get_string('nopermission', 'local_completion_report'), 'error');
}

$download = optional_param('download', '', PARAM_ALPHA);
$title = get_string('module_activity_report', 'local_completion_report');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($CFG->wwwroot . '/local/completion_report/report1.php');
$PAGE->requires->js_call_amd('local_completion_report/filter_module_activity', 'init'); // Ensure JS file is included

$instance = new stdClass();

$mform = new \local_completion_report\form\filter_module_activity_report(null);

$table = new \local_completion_report\tables\module_activity_report('uniqueid');
$table->is_downloading($download, 'module_activity_report', 'module_activity_report');

if (!$table->is_downloading()) {
    // Only print headers if not asked to download data
    // Print the page header
    $PAGE->set_title($title);
    $PAGE->set_heading($title);
    $PAGE->set_pagelayout('standard');
    echo $OUTPUT->header();
}

$fields = "cc.id, c.fullname AS coursename, cs.name AS sectionname, m.name AS modulename, CONCAT(u.firstname, ' ', u.lastname) AS studentname, u.email, cc.timemodified AS completion_time, cc.completionstate AS completion_status";
$from = "{course_modules_completion} cc
         JOIN {course_modules} cm ON cm.id = cc.coursemoduleid
         JOIN {course_sections} cs ON cs.id = cm.section
         JOIN {modules} m ON m.id = cm.module
         JOIN {course} c ON c.id = cm.course
         JOIN {user} u ON u.id = cc.userid";
$where = '1 = 1 ';
$modulenamewhere = " ";
$sectionnamewhere = " ";
$coursenamewhere  = " ";
$modulecompletionwhere  = " ";
$datefromwhere = " ";
$datetowhere = " ";
// Form processing and displaying is done here.
if ($mform->is_cancelled()) {
    // Clear specific session variables
    unset($_SESSION['datefrom']);
    unset($_SESSION['dateto']);
    unset($_SESSION['module_completion']);
    unset($_SESSION['coursename']);
    unset($_SESSION['sectionname']);
    unset($_SESSION['modulename']);
    unset_filter_variables();
    redirect($PAGE->url);
} else if ($fromform = $mform->get_data()) {
    unset_filter_variables();

    if (!empty($fromform->coursename)) {
        $courseid = trim($fromform->coursename);
        $courseid = strip_tags($fromform->coursename);
        $coursenamewhere = " AND c.id = '$courseid' ";
        $_SESSION['coursename'] = $courseid;
    }
    if (!empty($_POST['sectionname'])) {
        $sectionid = trim($_POST['sectionname']);
        $sectionid = strip_tags($_POST['sectionname']);
        $sectionnamewhere = " AND cs.id = '$sectionid' ";
        $_SESSION['sectionname'] = $sectionid;
    }
    // if (!empty($fromform->modulename)) {
    //     $modulename = trim($fromform->modulename);
    //     $modulename = strip_tags($fromform->modulename);
    //     $modulenamewhere = " AND m.name = '$modulename' ";
    //     $_SESSION['modulename'] = $modulename;
    // }
    if (!empty($fromform->module_completion)) {
        $modulecompletion = trim($fromform->module_completion);
        $modulecompletion = strip_tags($fromform->module_completion);
        if ($modulecompletion == "yes") {
            $modulecompletionwhere = " AND cc.completionstate = 1 ";
        } else {
            $modulecompletionwhere = " AND cc.completionstate != 1 ";
        }
        $_SESSION['module_completion'] = $modulecompletionwhere;
    }
    if (!empty($fromform->datefrom)) {
        $datefrom = trim($fromform->datefrom);
        $datefrom = strip_tags($fromform->datefrom);
        $datefromwhere = " AND cc.timemodified >= '$datefrom' ";
        $_SESSION['datefrom'] = $datefrom;
    }
    if (!empty($fromform->dateto)) {
        $dateto = trim($fromform->dateto);
        $dateto = strip_tags($fromform->dateto);
        $datetowhere = " AND cc.timemodified <= '$dateto' ";
        $_SESSION['dateto'] = $dateto;
    }
    echo "<script>
            var sessionSectionId = '$sectionid';
        </script>";
}
if (isset($_SESSION['dateto'])) {
    $instance->dateto = $_SESSION['dateto'];
    $datetowhere = " AND cc.timemodified <= '$instance->dateto' ";
}
if (isset($_SESSION['datefrom'])) {
    $instance->datefrom = $_SESSION['datefrom'];
    $datefromwhere = " AND cc.timemodified >= '$instance->datefrom' ";
}
if (isset($_SESSION['module_completion'])) {
    $instance->module_completion = $_SESSION['module_completion'];
    $modulecompletionwhere = $instance->module_completion;
}
if (isset($_SESSION['coursename'])) {
    $instance->coursename = $_SESSION['coursename'];
    $coursenamewhere = " AND c.id = $instance->coursename" ;
}
if (isset($_SESSION['sectionname'])) {
    $instance->sectionname = $_SESSION['sectionname'];
    $sectionnamewhere = " AND cs.id = $instance->sectionname" ;
}
// if (isset($_SESSION['modulename'])) {
//     $instance->modulename = $_SESSION['modulename'];
//     $modulenamewhere = " AND m.name = '$instance->modulename' " ;
// }

$where .= " AND u.deleted = 0 AND u.suspended = 0 $modulenamewhere $sectionnamewhere $coursenamewhere  $modulecompletionwhere $datefromwhere $datetowhere";

$table->set_sql($fields, $from, $where);
$table->sortable(false, 'uniqueid');
$table->define_baseurl($PAGE->url);

if (!$table->is_downloading()) {
    $mform->set_data($instance);
    $mform->display();
}

$table->out(20, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
?>

<script type="text/javascript">
    // Inline JavaScript for handling course and section selection
    $(document).ready(function() {
        // Check if course is already selected and load sections
        var initialCourseId = sessionCourseId;
        if (initialCourseId) {
            updateSections(initialCourseId, function() {
                if (sessionSectionId) {
                    $('#id_sectionname').val(sessionSectionId);
                    $('#id_sectionname').trigger('change');
                }
            });
        }

        // Event listener for course change
        $('#id_coursename').on('change', function() {
            var courseId = $(this).val();
            updateSections(courseId);
        });

        // Event listener for section change
        $('#id_sectionname').on('change', function() {
            var sectionId = $(this).val();
            var courseId = 2;
            updateModules(courseId, sectionId, function() {
                if (sessionModuleName) {
                    $('#id_modulename').val(sessionModuleName);
                }
            });
        });

        // Function to update sections based on selected course
        function updateSections(courseId, callback) {
            // Clear current options
            $('#id_sectionname').empty().append('<option value="">Select Section</option>');

            if (!courseId) {
                return;
            }

            $.ajax({
                url: M.cfg.wwwroot + '/local/completion_report/get_sections.php',
                type: 'GET',
                data: { courseid: courseId },
                success: function(data) {
                    var sections = JSON.parse(data);
                    $.each(sections, function(index, value) {
                        $('#id_sectionname').append($('<option>', {
                            value: index,
                            text: value
                        }));
                    });

                    if (callback) {
                        callback();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error fetching sections: ', error);
                }
            });
        }

        // Function to update modules based on selected course and section
        function updateModules(courseId, sectionId, callback) {
            // Clear current options
            $('#id_modulename').empty().append('<option value="">Select Module/Activity</option>');
        
            if (!courseId || !sectionId) {
                return;
            }
        
            $.ajax({
                url: M.cfg.wwwroot + '/local/completion_report/get_modules.php',
                type: 'GET',
                data: { courseid: courseId, sectionid: sectionId },
                success: function(data) {
                    try {
                        var modules = JSON.parse(data);
        
                        $.each(modules, function(id, module) {
                            $('#id_modulename').append($('<option>', {
                                value: id,
                                text: module.name
                            }));
                        });
        
                        if (callback) {
                            callback();
                        }
                    } catch (e) {
                        console.error('Error parsing JSON data: ', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching modules: ', error);
                }
            });
        }

    });
</script>
<!--<script type="text/javascript">-->
<!--    $(document).ready(function() {-->
<!--        $('#id_coursename').on('change', function() {-->
<!--            var courseId = $(this).val();-->
<!--            updateSections(courseId);-->
<!--        });-->

<!--        $('#id_sectionname').on('change', function() {-->
<!--            var sectionId = $(this).val();-->
<!--            var courseId = $('#id_coursename').val();-->
<!--            updateModules(courseId, sectionId);-->
<!--        });-->

<!--        function updateSections(courseId) {-->
<!--            $('#id_sectionname').empty().append('<option value="">Select Section</option>');-->

<!--            if (!courseId) {-->
<!--                return;-->
<!--            }-->

<!--            $.ajax({-->
<!--                url: M.cfg.wwwroot + '/local/completion_report/get_sections.php',-->
<!--                type: 'GET',-->
<!--                data: { courseid: courseId },-->
<!--                success: function(data) {-->
<!--                    var sections = JSON.parse(data);-->
<!--                    $.each(sections, function(index, value) {-->
<!--                        $('#id_sectionname').append($('<option>', {-->
<!--                            value: index,-->
<!--                            text: value-->
<!--                        }));-->
<!--                    });-->

<!--                },-->
<!--                error: function(xhr, status, error) {-->
<!--                    console.log('Error fetching sections: ', error);-->
<!--                }-->
<!--            });-->
<!--        }-->

<!--        function updateModules(courseId, sectionId) {-->
<!--            $('#id_modulename').empty().append('<option value="">Select Module/Activity</option>');-->

<!--            if (!courseId || !sectionId) {-->
<!--                return;-->
<!--            }-->

<!--            $.ajax({-->
<!--                url: M.cfg.wwwroot + '/local/completion_report/get_modules.php',-->
<!--                type: 'GET',-->
<!--                data: { courseid: courseId, sectionid: sectionId },-->
<!--                success: function(data) {-->
<!--                    var modules = JSON.parse(data);-->
<!--                    $.each(modules, function(index, value) {-->
<!--                        $('#id_modulename').append($('<option>', {-->
<!--                            value: index,-->
<!--                            text: value-->
<!--                        }));-->
<!--                    });-->
<!--                },-->
<!--                error: function(xhr, status, error) {-->
<!--                    console.log('Error fetching modules: ', error);-->
<!--                }-->
<!--            });-->
<!--        }-->
<!--    });-->
<!--</script>-->
