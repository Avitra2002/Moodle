<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_customdashboard_upgrade($oldversion) {
    global $DB;
    
    if ($oldversion < 2025071102) {
        $existing = $DB->get_records_sql("
            SELECT bi.id 
            FROM {block_instances} bi 
            WHERE bi.blockname = 'html' 
            AND bi.pagetypepattern = 'local-customdashboard-index'
            AND bi.configdata LIKE '%Bulk Modification%'
        ");
        
        if (empty($existing)){
            $blockconfig = new stdClass();
            $blockconfig->text = '<a href="/local/cohortenrolsync/index.php">Bulk Enrolment Form</a>';
            $blockconfig->title = 'Bulk Modification';
            $blockconfig->format = '1';
            
            $bulkmodblock = new stdClass();
            $bulkmodblock->blockname = 'html';
            $bulkmodblock->parentcontextid = 1;
            $bulkmodblock->showinsubcontexts = 1;
            $bulkmodblock->requiredbytheme = 0;
            $bulkmodblock->pagetypepattern = 'local-customdashboard-index';
            $bulkmodblock->subpagepattern = null;
            $bulkmodblock->defaultregion = 'side-pre';
            $bulkmodblock->defaultweight = 1;
            $bulkmodblock->configdata = base64_encode(serialize($blockconfig));
            $bulkmodblock->timecreated = time();
            $bulkmodblock->timemodified = time();
            
            $bulkmodblockid = $DB->insert_record('block_instances', $bulkmodblock);
        }
        
        upgrade_plugin_savepoint(true, 2025071102, 'local', 'customdashboard');
    }
    
    return true;
}