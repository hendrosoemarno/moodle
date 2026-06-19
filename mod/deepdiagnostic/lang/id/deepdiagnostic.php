<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Deep Diagnostic Report';
$string['modulename'] = 'Deep Diagnostic Report';
$string['modulenameplural'] = 'Deep Diagnostic Reports';
$string['modulename_help'] = 'Menampilkan laporan diagnostik hasil kuis Deep untuk siswa.';
$string['pluginadministration'] = 'Administrasi Deep Diagnostic Report';

$string['deepdiagnostic:addinstance'] = 'Tambah Deep Diagnostic Report baru';
$string['deepdiagnostic:view'] = 'Lihat Deep Diagnostic Report';

$string['privacy:metadata'] = 'Plugin Deep Diagnostic Report tidak menyimpan data pribadi.';

$string['deepdiagnosticname'] = 'Nama laporan';
$string['template_header'] = 'Templat Pembuka';
$string['template_header_help'] = 'Teks yang ditampilkan sebelum daftar hasil analisis. Gunakan {studentname} untuk nama siswa.';
$string['template_footer'] = 'Templat Penutup';
$string['template_footer_help'] = 'Teks yang ditampilkan setelah daftar hasil analisis. Gunakan {studentname} untuk nama siswa, {levelnum} untuk nomor level, dan {leveltopic} untuk topik level.';

$string['status_kompeten'] = 'sudah bisa dan sudah kompeten';
$string['status_belum_kompeten'] = 'belum kompeten';
$string['status_sebagian'] = 'sudah bisa namun belum kompeten';
$string['item_format'] = '🔹 {$a->topic} {$a->status}';
$string['no_quizzes'] = 'Tidak ditemukan kuis Deep pada kursus ini.';
$string['no_data'] = 'Belum ada data hasil kuis untuk ditampilkan.';
