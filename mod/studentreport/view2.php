<?php
////////bisa keluarkan tabel matriks kemampuan siswa berdasarkan kategori
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // ID dari course module
$cm = get_coursemodule_from_id('studentreport', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$studentreport = $DB->get_record('studentreport', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/studentreport:view', $context);

$PAGE->set_url('/mod/studentreport/view.php', ['id' => $id]);
$PAGE->set_title(format_string($studentreport->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($studentreport->name));

// Ambil ID pengguna yang sedang login
$current_userid = $USER->id;

$sql = "
    SELECT
        qas_id,
        userid,
        qa_id,
        quiz_name,
        category_name,
        total_questions_in_category,
        correct_questions_in_category
    FROM
        vw_nilai_qc
    WHERE
        userid = :userid

";

// Mengambil data dari database dengan parameter binding
$raw_records = $DB->get_records_sql($sql, ['userid' => $current_userid]);

$pivoted_data = [];
$all_categories = [];

        // echo '<pre>';
        // print_r($raw_records);
        // echo '</pre>';


// Proses data untuk pivoting
foreach ($raw_records as $record) {
    $quiz_key = $record->qa_id; // Kunci untuk baris: Quiz Attemp ID

    if (!isset($pivoted_data[$quiz_key])) {
        $pivoted_data[$quiz_key] = [
            'quiz_name' => $record->quiz_name,
            'quizid' => $record->qa_id,
            'categories' => []
        ];
    }

    $pivoted_data[$quiz_key]['categories'][$record->category_name] = [
        'total' => $record->total_questions_in_category,
        'correct' => $record->correct_questions_in_category
    ];
    
    // Kumpulkan semua nama kategori unik
    if (!in_array($record->category_name, $all_categories)) {
        $all_categories[] = $record->category_name;
    }
}

// Urutkan kategori secara alfabetis untuk konsistensi header
sort($all_categories);

if (!empty($pivoted_data)) {
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable']; // Menambahkan kelas CSS Moodle untuk tabel

    // Bangun header tabel dinamis
    $table->head = [
        'Nama Kuis'
    ];

    // Tambahkan header untuk setiap kategori (Total Soal dan Benar)
    foreach ($all_categories as $category) {
        $table->head[] = s($category); // Cukup nama kategori saja jika hanya 1 nilai
    }

    $table->data = [];
    foreach ($pivoted_data as $row_key => $data) {
        $row = [
            s($data['quiz_name'])
        ];
        foreach ($all_categories as $category) {
            // Kita akan menampilkan "Jumlah Benar" (correct_questions_in_category)
            // karena tabel contoh Anda hanya memiliki satu angka per kategori.
            $correct = isset($data['categories'][$category]['correct']) ? $data['categories'][$category]['correct'] : '0'; // Kosongkan jika tidak ada data
            $total = isset($data['categories'][$category]['total']) ? $data['categories'][$category]['total'] : '0'; // Kosongkan jika tidak ada data

            $percentage = 0; // Inisialisasi persentase
            if ($total > 0) {
                $percentage = ($correct / $total) * 100; // Hitung persentase dalam 0-100
            }

            // Menentukan kategori penilaian berdasarkan persentase
            $assessment = '';
            if ($percentage >= 90) {
                $assessment = 'Excellent';
            } elseif ($percentage >= 70) {
                $assessment = 'Kompeten';
            } elseif ($percentage >= 50) {
                $assessment = 'Bisa';
            } else {
                $assessment = 'Kurang';
            }
            
            $row[] = $assessment; // Tambahkan hasil penilaian ke baris tabel
        }
        $table->data[] = $row;
    }

    echo html_writer::table($table);
} else {
    echo html_writer::div('Tidak ada data laporan yang ditemukan untuk pengguna ini.', 'alert alert-info');
}

echo $OUTPUT->footer();