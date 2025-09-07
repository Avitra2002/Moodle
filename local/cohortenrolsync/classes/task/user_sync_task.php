<?php
namespace local_cohortenrolsync\task;

use local_cohortenrolsync\core\sync_engine;

defined('MOODLE_INTERNAL') || die();

class user_sync_task extends \core\task\adhoc_task {

    public function get_name() {
        return 'Cohort enrol sync: user incremental sync';
    }

    public function execute() {
        $data = $this->get_custom_data();
        if (!empty($data->userids) && is_array($data->userids)) {
            sync_engine::incremental_sync($data->userids);
        }
    }
}
