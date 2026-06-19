<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    // Siapa saja yang boleh melihat aktivitas/report ini.
    'mod/smpreport:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'manager'         => CAP_ALLOW,
            'editingteacher'  => CAP_ALLOW,
            'teacher'         => CAP_ALLOW,
            'student'         => CAP_ALLOW,   // ← hapus baris ini jika HANYA guru yang boleh lihat
            'guest'           => CAP_PREVENT,
        ),
    ),

    // Siapa yang boleh menambahkan aktivitas ke course.
    'mod/smpreport:addinstance' => array(
        'riskbitmask' => RISK_XSS,            // umum dipakai untuk aktivitas yang menampilkan konten bebas
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ),
);
