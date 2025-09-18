<?php



require_once('../../config.php');

require_once($CFG->libdir . '/csvlib.class.php');

require_once($CFG->dirroot . '/local/completion_report/upload_form.php'); // your custom form



require_login();

core_php_time_limit::raise(60 * 60); // 1 hour should be enough.

raise_memory_limit(MEMORY_HUGE);

$context = context_system::instance();

require_capability('moodle/user:update', $context); // Only admins



$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/local/completion_report/import.php'));

$PAGE->set_title('Import Course Completion CSV');

$PAGE->set_heading('Import Course Completion CSV');



echo $OUTPUT->header();



$form = new local_completion_report_upload_form();



if ($form->is_cancelled()) {

    redirect($PAGE->url);

} else if ($data = $form->get_data()) {



    $iid = csv_import_reader::get_new_iid('uploadcsv');

    $csv = new csv_import_reader($iid, 'uploadcsv');



    $content = $form->get_file_content('csvfile');

    $status = $csv->load_csv_content($content, 'utf-8', ',');



    $csv->init();

    $error = $csv->get_error();

    unset($content);



    if (!is_null($error)) {

        echo $OUTPUT->notification("CSV load failed: " . $error, 'notifyproblem');

    }



    $headers = $csv->get_columns();

    if (!$headers) {

        echo $OUTPUT->notification("CSV missing headers.", 'notifyproblem');

        echo $OUTPUT->footer();

        exit;

    }



    // Profile field map

    $profilefields = $DB->get_records_menu('user_info_field', null, '', 'shortname, id');



    // Course custom field map

    $customfields = $DB->get_records_menu('customfield_field', null, '', 'shortname, id');



    require_once($CFG->libdir . '/completionlib.php');



    $rownum = 1;



    while ($row = $csv->next()) {

        $rownum++;

        $record = array_combine($headers, $row);
        

        $email = trim($record['email']);

        $coursename = trim($record['course_name']);



        if (empty($email) || empty($coursename)) {

            mtrace("Row $rownum skipped: Missing email or course name");

            continue;

        }



        // Get user

        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

        if (!$user) {

            mtrace("Row $rownum: User with email $email not found");

            continue;

        }



        // Get course

        $course = $DB->get_record('course', ['fullname' => $coursename]);

        if (!$course) {

            mtrace("Row $rownum: Course '$coursename' not found");

            continue;

        }



        // Enrol user or update enrolment start date if already enrolled

        // Get the manual enrolment instance for the course
        $enrol = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol'    => 'manual',
            'status'   => ENROL_INSTANCE_ENABLED
        ], '*', IGNORE_MULTIPLE);

        if ($enrol) {
            $enrolmentid = '';
            $enrolplugin = enrol_get_plugin('manual');

            // Check if the user is already enrolled in the course (any enrol method)
            $alreadyenrolled = $DB->record_exists_sql("
                SELECT 1
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid   = :userid
                AND e.courseid  = :courseid",
                [
                    'userid'   => $user->id,
                    'courseid' => $course->id
                ]
            );

            $enrolmenttimestamp = !empty($record['enrolment_date'])
                ? strtotime($record['enrolment_date'])
                : time();

                

            if (!$alreadyenrolled) {
                
                // Enrol user with role ID 5 and start date
                $enrolplugin->enrol_user($enrol, $user->id, 5, $enrolmenttimestamp);

                // Update enrol instance timestart if different
                // if ($enrol->timestart != $enrolmenttimestamp) {
                //     $enrol->timestart = $enrolmenttimestamp;
                //     $DB->update_record('enrol', $enrol);
                // }

                // Get the enrolment ID from user_enrolments
                $userenrols = $DB->get_record('user_enrolments', [
                    'userid'  => $user->id,
                    'enrolid' => $enrol->id
                ], '*', MUST_EXIST);

                $enrolmentid = $userenrols->id;

            } else {
                // User already enrolled — update enrolment dates if needed
                $userenrol = $DB->get_record_sql("
                    SELECT ue.*
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE ue.userid   = :userid
                    AND e.courseid  = :courseid
                    LIMIT 1",
                    [
                        'userid'   => $user->id,
                        'courseid' => $course->id
                    ]
                );

                // $changed = false;

                // if ($userenrol && $userenrol->timestart != $enrolmenttimestamp) {
                //     $userenrol->timestart = $enrolmenttimestamp;
                //     $changed = true;
                // }

                // if ($userenrol && $userenrol->timecreated > $enrolmenttimestamp) {
                //     $userenrol->timecreated = $enrolmenttimestamp;
                //     $changed = true;
                // }

                //if ($changed) {
                    $userenrol->timestart = $enrolmenttimestamp;
                    $DB->update_record('user_enrolments', $userenrol);
                    $enrolmentid = $userenrol->id;
                //}

                // Also keep enrol instance timestart in sync
                // if ($enrol->timestart != $enrolmenttimestamp) {
                //     $enrol->timestart = $enrolmenttimestamp;
                //     $DB->update_record('enrol', $enrol);
                // }
            }
        }

        // Now insert/update mdl_local_userprofilefields
        if(!empty($enrolmentid)){
            $profiledata = new stdClass();
            $profiledata->enrollmentid             = $enrolmentid;
            $profiledata->administrative_positions = $record['administrative_positions'] ?? null;
            $profiledata->personal_type            = $record['personal_type'] ?? null;
            $profiledata->coursetype               = $record['coursetype'] ?? null;
            $profiledata->coursegroup              = $record['coursegroup'] ?? null;
            $profiledata->number_of_days           = $record['number_of_days'] ?? null;
            $profiledata->position_in_the_line_of_work      = $record['position_lineofwork'] ?? null;
            $profiledata->timecreated              = time();
            $profiledata->timemodified             = time();

            $existing = $DB->get_record('local_userprofilefields', ['enrollmentid' => $enrolmentid]);

            if ($existing) {
                $profiledata->id = $existing->id; // Needed for update
                $DB->update_record('local_userprofilefields', $profiledata);
            } else {
                $profiledata->timecreated = time();
                $DB->insert_record('local_userprofilefields', $profiledata);
            }
        }

        if(!empty($record['course_last_access'])){
            $lstacdate = $record['course_last_access'];
            $customdate = strtotime($lstacdate);
            // Check if a record already exists
            $usrlstaces = $DB->get_record('user_lastaccess', [
                'userid'   => $user->id,
                'courseid' => $course->id
            ]);

            if ($usrlstaces) {
                // Update existing
                $usrlstaces->timeaccess = $customdate;
                $usrlstaces->lastaccess = $customdate;
                
                $DB->update_record('user_lastaccess', $usrlstaces);
            } else {
                // Insert new
                $newrecord = new stdClass();
                $newrecord->userid     = $user->id;
                $newrecord->courseid   = $course->id;
                $newrecord->timeaccess = $customdate;
                $newrecord->lastaccess = $customdate;
                $DB->insert_record('user_lastaccess', $newrecord);
            }
        }
 


        // Handle course completion if flag set to '1' or 'true'

        $completionflag = false;

        if (isset($record['course_completion'])) {

            $val = strtolower(trim($record['course_completion']));

            if ($val === '1' || $val === 'true') {

                $completionflag = true;

            }
				 $completionflag = true;
        }



        if ($completionflag) {

            // Mark all activities complete & course complete

            $userid = $user->id;

            $courseid = $course->id;

            $completioninfo = new completion_info(get_course($course->id));



            $modinfo = get_fast_modinfo($course->id);

            foreach ($modinfo->cms as $cm) {

                if ($completioninfo->is_enabled($cm) == COMPLETION_TRACKING_MANUAL ||

                    $completioninfo->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {



                    $completioninfo->update_state($cm, COMPLETION_COMPLETE, $user->id);

                }

            }



            // Mark course completion record

            $completionobj = new completion_completion([

                'userid' => $user->id,

                'course' => $course->id

            ]);



            //$completiontimestamp = strtotime($record['course_completion_date']);
            $completiontimestamp = strtotime(str_replace('/', '-', $record['course_completion_date']));

            if(empty($completiontimestamp)){
                $completiontimestamp = time();
            }
            

            $completion = $DB->get_record('course_completions', [

                'userid' => $user->id,

                'course' => $course->id,

            ]);



            if ($completion) {

                if ($completion->timecompleted != $completiontimestamp) {

                    $completion->timecompleted = $completiontimestamp;

                    $completion->timemodified = time();

                    $DB->update_record('course_completions', $completion);

                }

            } else {

                $completion = new stdClass();

                $completion->userid = $user->id;

                $completion->course = $course->id;

                $completion->timecompleted = $completiontimestamp;

                $completion->timemodified = time();

                $DB->insert_record('course_completions', $completion);

            }


            //global $DB;
            //$customtimestamp = time();
            // Try to load existing course completion record.
            $existing = $DB->get_record('course_completions', [
                'userid' => $user->id,
                'course' => $course->id
            ]);

            if ($existing) {
                $completion = new completion_completion((array)$existing);

                if ($completion->is_complete()) {
                    if ($completion->timecompleted != $completiontimestamp) {
                        $DB->update_record('course_completions', [
                            'id' => $completion->id,
                            'timecompleted' => $completiontimestamp,
                            'timemodified'  => time()
                        ]);
                    }
                } else {
                    $completion->mark_complete($completiontimestamp);
                }

            } else {
                $completion = new completion_completion([
                    'userid'       => $user->id,
                    'course'       => $course->id,
                    'timeenrolled' => 0,
                    'timestarted'  => 0
                ]);
                $completion->mark_complete($completiontimestamp);
            }




            // if (!$completionobj->is_complete()) {

            //     $completionobj->mark_complete();

            // }

        }



        // Update user profile fields

        foreach ($profilefields as $shortname => $fieldid) {

            if (!isset($record[$shortname])) {

                continue;

            }

            $value = trim($record[$shortname]);

            if ($value === '') {

                continue;

            }

            $existing = $DB->get_record('user_info_data', [

                'userid' => $user->id,

                'fieldid' => $fieldid

            ]);


            if ($existing) {

                $existing->data = $value;

                $DB->update_record('user_info_data', $existing);

            } else {

                $new = new stdClass();

                $new->userid = $user->id;

                $new->fieldid = $fieldid;

                $new->data = $value;

                $DB->insert_record('user_info_data', $new);

            }

        }



        // Update course custom fields

        foreach ($customfields as $shortname => $fieldid) {

            if (!isset($record[$shortname])) {

                continue;

            }

            $value = trim($record[$shortname]);

            if ($value === '') {

                continue;

            }

            if(empty($enrolmentid)){
                continue;
            }


            // $existing = $DB->get_record('customfield_data', [

            //     'fieldid' => $fieldid,

            //     'instanceid' => $course->id

            // ]);

            $existing = $DB->get_record('customfield_data', [

                'fieldid' => $fieldid,

                'instanceid' => $enrolmentid

            ]);

            if ($existing) {

                $existing->value = $value;

                $DB->update_record('customfield_data', $existing);

            } else {

                $new = new stdClass();

                $new->fieldid = $fieldid;

                $new->instanceid = $enrolmentid;

                $new->value = $value;

                $new->valueformat = 1;

                $new->timecreated = time();

                $new->timemodified = time();

                $DB->insert_record('customfield_data', $new);

            }

        }



        // mtrace("Row $rownum: Imported user $email into course $coursename");

    }



    $csv->cleanup(true);
    purge_all_caches();
    echo $OUTPUT->notification("Import completed", 'notifysuccess');

}



$form->display();

echo $OUTPUT->footer();

