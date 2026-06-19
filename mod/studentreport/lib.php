<?php
defined('MOODLE_INTERNAL') || die();

function studentreport_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        default:                              return null;
    }
}

function studentreport_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    // Ambil dari editor → kolom DB.
    if (isset($data->legend_editor)) {
        $data->legend       = $data->legend_editor['text'] ?? '';
        $data->legendformat = $data->legend_editor['format'] ?? FORMAT_HTML;
    }
    if (isset($data->kompetensi_editor)) {
        $data->kompetensi       = $data->kompetensi_editor['text'] ?? '';
        $data->kompetensiformat = $data->kompetensi_editor['format'] ?? FORMAT_HTML;
    }

    return $DB->insert_record('studentreport', $data);
}

function studentreport_update_instance($data, $mform = null) {
    global $DB;
    $record               = new stdClass();
    $record->id           = (int)$data->instance;
    $record->name         = $data->name ?? '';
    $record->intro        = $data->intro ?? '';
    $record->introformat  = $data->introformat ?? FORMAT_HTML;
    $record->timemodified = time();

    // Ambil dari editor → kolom DB.
    if (isset($data->legend_editor)) {
        $record->legend       = $data->legend_editor['text'] ?? '';
        $record->legendformat = $data->legend_editor['format'] ?? FORMAT_HTML;
    }
    if (isset($data->kompetensi_editor)) {
        $record->kompetensi       = $data->kompetensi_editor['text'] ?? '';
        $record->kompetensiformat = $data->kompetensi_editor['format'] ?? FORMAT_HTML;
    }

    return $DB->update_record('studentreport', $record);
}

function studentreport_delete_instance($id) {
    global $DB;
    return $DB->delete_records('studentreport', ['id' => $id]);
}
