<?php
defined('MOODLE_INTERNAL') || die();

function customreport_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    return $DB->insert_record('customreport', $data);
}

function customreport_update_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    return $DB->update_record('customreport', $data);
}

function customreport_delete_instance($id) {
    global $DB;
    return $DB->delete_records('customreport', ['id' => $id]);
}
