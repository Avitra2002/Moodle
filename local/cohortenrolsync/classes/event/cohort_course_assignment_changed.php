<?php
namespace local_cohortenrolsync\event;

defined('MOODLE_INTERNAL') || die();

class cohort_course_assignment_changed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return 'Cohort-course assignment changed';
    }

    public function get_description() {
        return "Cohort {$this->other['cohortid']} assignment changed for course {$this->other['courseid']}.";
    }
}
