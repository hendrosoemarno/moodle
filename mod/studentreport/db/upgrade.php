<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_studentreport_upgrade(int $oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Ganti versinya (YYYYMMDDXX) sesuai version.php yang akan Anda set.
    if ($oldversion < 2025090801) {
        $table = new xmldb_table('studentreport');

        $field1 = new xmldb_field('legend', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('legendformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 1);
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        $field3 = new xmldb_field('kompetensi', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }

        $field4 = new xmldb_field('kompetensiformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 1);
        if (!$dbman->field_exists($table, $field4)) {
            $dbman->add_field($table, $field4);
        }

        upgrade_plugin_savepoint(true, 2025090801, 'mod', 'studentreport');
    }

    return true;
}
