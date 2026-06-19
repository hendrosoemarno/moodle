<?php
defined('MOODLE_INTERNAL') || die();

function smareport_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_BACKUP_MOODLE2:          return false;
        default:                              return null;
    }
}

function smareport_add_instance($data, $mform = null) {
    global $DB;
    if (!isset($data->intro))       { $data->intro = ''; }
    if (!isset($data->introformat)) { $data->introformat = FORMAT_HTML; }
    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record('smareport', $data);
}

function smareport_update_instance($data, $mform = null) {
    global $DB;
    $rec = new stdClass();
    $rec->id           = (int)$data->instance;
    $rec->name         = $data->name ?? '';
    $rec->intro        = $data->intro ?? '';
    $rec->introformat  = $data->introformat ?? FORMAT_HTML;
    $rec->timemodified = time();
    return $DB->update_record('smareport', $rec);
}

function smareport_delete_instance($id) {
    global $DB;
    // Hapus data turunan di sini jika ada.
    return $DB->delete_records('smareport', ['id' => $id]);
}
