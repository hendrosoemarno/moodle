<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Deklarasi fitur yang didukung plugin.
 * Silakan ubah sesuai kebutuhan Anda.
 */
function smpreport_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:              return true;   // ada intro/introformat
        case FEATURE_SHOW_DESCRIPTION:       return true;   // tampilkan deskripsi di course page
        case FEATURE_COMPLETION_TRACKS_VIEWS:return true;   // dukung completion saat dilihat
        case FEATURE_GRADE_HAS_GRADE:        return false;  // tidak pakai gradebook
        case FEATURE_BACKUP_MOODLE2:         return false;  // set true jika Anda menyiapkan berkas backup/restore
        default:                             return null;
    }
}

/**
 * Tambah instance baru.
 * Dipanggil saat submit mod_form.php (add).
 */
function smpreport_add_instance($data, $mform = null) {
    global $DB;

    // Pastikan field inti ada (sesuaikan dengan install.xml Anda).
    if (!isset($data->intro))        { $data->intro = ''; }
    if (!isset($data->introformat))  { $data->introformat = FORMAT_HTML; }

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    // Tabel instance BARU: smpreport
    return $DB->insert_record('smpreport', $data);
}

/**
 * Update instance.
 * Dipanggil saat submit mod_form.php (update).
 */
function smpreport_update_instance($data, $mform = null) {
    global $DB;

    // Moodle mengirim $data->instance berisi id record instance.
    $record               = new stdClass();
    $record->id           = (int)$data->instance;
    $record->name         = $data->name ?? '';         // sesuaikan field yang Anda simpan
    $record->intro        = $data->intro ?? '';
    $record->introformat  = $data->introformat ?? FORMAT_HTML;
    $record->timemodified = time();

    // Jika Anda menyimpan field lain dari mod_form, set di sini juga:
    // $record->course = $data->course; // biasanya sudah terset saat create, tidak wajib diupdate

    return $DB->update_record('smpreport', $record);
}

/**
 * Hapus instance.
 * Dipanggil ketika aktivitas dihapus dari course.
 * Pastikan juga menghapus data turunan (jika ada) yang terkait instance ini.
 */
function smpreport_delete_instance($id) {
    global $DB;

    // Hapus data turunan di sini jika Anda punya tabel lain yang refer ke instance ini.
    // Contoh:
    // $DB->delete_records('smpreport_logs', ['smpreportid' => $id]);

    return $DB->delete_records('smpreport', ['id' => $id]);
}
