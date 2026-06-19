<?php
defined('MOODLE_INTERNAL') || die();

function deepdiagnostic_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        default:                              return null;
    }
}

function deepdiagnostic_add_instance($data, $mform = null) {
    global $DB;
    $record = new stdClass();
    $record->name         = $data->name;
    $record->intro        = $data->intro ?? '';
    $record->introformat  = $data->introformat ?? FORMAT_HTML;
    $record->timecreated  = time();
    $record->timemodified = $record->timecreated;

    if (isset($data->template_header_editor)) {
        $record->template_header       = $data->template_header_editor['text'] ?? '';
        $record->template_headerformat = $data->template_header_editor['format'] ?? FORMAT_HTML;
    }
    if (isset($data->template_footer_editor)) {
        $record->template_footer       = $data->template_footer_editor['text'] ?? '';
        $record->template_footerformat = $data->template_footer_editor['format'] ?? FORMAT_HTML;
    }

    return $DB->insert_record('deepdiagnostic', $record);
}

function deepdiagnostic_update_instance($data, $mform = null) {
    global $DB;
    $record = new stdClass();
    $record->id           = (int)$data->instance;
    $record->name         = $data->name ?? '';
    $record->intro        = $data->intro ?? '';
    $record->introformat  = $data->introformat ?? FORMAT_HTML;
    $record->timemodified = time();

    if (isset($data->template_header_editor)) {
        $record->template_header       = $data->template_header_editor['text'] ?? '';
        $record->template_headerformat = $data->template_header_editor['format'] ?? FORMAT_HTML;
    }
    if (isset($data->template_footer_editor)) {
        $record->template_footer       = $data->template_footer_editor['text'] ?? '';
        $record->template_footerformat = $data->template_footer_editor['format'] ?? FORMAT_HTML;
    }

    return $DB->update_record('deepdiagnostic', $record);
}

function deepdiagnostic_delete_instance($id) {
    global $DB;
    return $DB->delete_records('deepdiagnostic', ['id' => $id]);
}
