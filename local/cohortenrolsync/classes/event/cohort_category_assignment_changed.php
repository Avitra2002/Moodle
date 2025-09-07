<?php
namespace local_cohortenrolsync\event;

defined('MOODLE_INTERNAL') || die();

class cohort_category_assignment_changed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return 'Cohort-category assignment changed';
    }

    public function get_description() {
        return "Cohort {$this->other['cohortid']} assignment changed for category {$this->other['categoryid']}.";
    }
}
