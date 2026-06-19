<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_deepdiagnostic_upgrade(int $oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025061900) {
        upgrade_plugin_savepoint(true, 2025061900, 'mod', 'deepdiagnostic');
    }

    return true;
}
