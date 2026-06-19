<?php
// File: mod/studentreport/view.php

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // ID dari course module.
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

/** =======================================================================
 *  Legend/Keterangan Penilaian (tampilkan di atas)
 *  =======================================================================
 */
$render_assessment_legend = function() {
    $legend = new html_table();
    $legend->attributes = ['class' => 'generaltable'];
    $legend->head = [
        'Persentase Capaian',
        'Kategori',
        'Makna Umum'
    ];
    $legend->data = [
        ['0–49%',   html_writer::tag('strong', 'Kurang'),    'Belum menguasai materi'],
        ['50–69%',  html_writer::tag('strong', 'Bisa'),      'Baru mulai bisa, masih butuh bimbingan'],
        ['70–89%',  html_writer::tag('strong', 'Kompeten'),  'Sudah cukup menguasai kompetensi'],
        ['90–100%', html_writer::tag('strong', 'Excellent'), 'Sudah menguasai kompetensi'],
    ];

    echo html_writer::tag('div',
        html_writer::tag('h4', 'Keterangan Penilaian', ['class' => 'mb-2']) .
        html_writer::table($legend),
        ['class' => 'box generalbox', 'style' => 'max-width:820px;margin-bottom:1rem;']
    );
};
$render_assessment_legend();

/** =======================================================================
 *  Ambil data dari view
 *  =======================================================================
 */
$current_userid = $USER->id;

$sql = "
    SELECT
        qas_id,
        userid,
        qa_id,
        quiz_name,
        category_name,
        qc_id,
        total_questions_in_category,
        correct_questions_in_category
    FROM
        vw_nilai_qc
    WHERE
        userid = :userid
";
$raw_records = $DB->get_records_sql($sql, ['userid' => $current_userid]);

if (empty($raw_records)) {
    echo html_writer::div('Tidak ada data laporan yang ditemukan untuk pengguna ini.', 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

/** =======================================================================
 *  Ambil nama kategori asli (qc.name) via qc_id (1 query batch)
 *  =======================================================================
 */
$qcids = [];
foreach ($raw_records as $r) {
    if (isset($r->qc_id)) {
        $qcids[(int)$r->qc_id] = true;
    }
}
$qcids = array_keys($qcids);

$qcmap = []; // [qc_id => question_categories.name]
if (!empty($qcids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($qcids, SQL_PARAMS_NAMED);
    $qcrecs = $DB->get_records_select('question_categories', "id $insql", $inparams, '', 'id, name');
    foreach ($qcrecs as $qr) {
        $qcmap[(int)$qr->id] = $qr->name; // nama kategori penuh (berisi tag bin/mat)
    }
}

/** =======================================================================
 *  Deteksi subject berdasarkan tag 'bin' / 'mat' pada nama kategori asli
 *  =======================================================================
 */
$detect_subject_by_tag = function(string $text): ?string {
    // cari token 'bin' / 'mat' (case-insensitive), boleh diapit spasi/strip/kurung
    $lc = mb_strtolower(' ' . $text . ' ');
    if (preg_match('/(^|[\s\-])bin([\s\-\)]|$)/u', $lc)) {
        return 'Bahasa Indonesia';
    }
    if (preg_match('/(^|[\s\-])mat([\s\-\)]|$)/u', $lc)) {
        return 'Matematika';
    }
    return null;
};

// Bagi data ke dua subject
$raw_by_subject = [
    'Matematika' => [],
    'Bahasa Indonesia' => [],
];

foreach ($raw_records as $rec) {
    // nama kategori penuh dari qcmap; fallback ke category_name dari view bila tidak ada
    $fullcat = $qcmap[$rec->qc_id] ?? $rec->category_name;

    $subject = $detect_subject_by_tag($fullcat);
    if ($subject === null) {
        // fallback opsional: coba dari nama kuis
        $subject = $detect_subject_by_tag($rec->quiz_name);
    }
    if ($subject && array_key_exists($subject, $raw_by_subject)) {
        $raw_by_subject[$subject][] = $rec;
    }
}

/** =======================================================================
 *  Keterangan header khusus Bahasa Indonesia
 *  =======================================================================
 */
$bi_preface = (function(): string {
    // H4
    $out = html_writer::tag('h4', 'Kompetensi Singkat Membaca Teks', ['class' => 'mb-2']);

    // H5 + UL: 1. Pemahaman Tekstual
    $ul1 = html_writer::tag('ul',
        html_writer::tag('li', html_writer::tag('strong', 'Identifikasi objek') . ': Mengenali objek berdasarkan kosakata yang digunakan dalam teks fiksi maupun nonfiksi.') .
        html_writer::tag('li', html_writer::tag('strong', 'Penggunaan kosakata') . ': Mengidentifikasi pemakaian kosakata umum dan kosakata khusus dalam berbagai bidang.') .
        html_writer::tag('li', html_writer::tag('strong', 'Ikhtisar atau bagan') . ': Menyusun kembali informasi dari teks ke dalam bentuk ikhtisar atau bagan.') .
        html_writer::tag('li', html_writer::tag('strong', 'Informasi tersurat') . ': Menemukan informasi yang secara jelas tertulis di dalam teks.')
    );
    $out .= html_writer::tag('h5', '1. Pemahaman Tekstual') . $ul1;

    // H5 + UL: 2. Pemahaman Inferensial
    $ul2 = html_writer::tag('ul',
        html_writer::tag('li', html_writer::tag('strong', 'Menyimpulkan ide pokok') . ': Menemukan gagasan utama, gagasan pendukung, amanat, tokoh, peristiwa, dan nilai-nilai dalam teks.') .
        html_writer::tag('li', html_writer::tag('strong', 'Menyimpulkan perubahan') . ': Mengidentifikasi perubahan sederhana pada objek, tokoh, atau latar dalam teks fiksi maupun nonfiksi.') .
        html_writer::tag('li', html_writer::tag('strong', 'Makna ungkapan') . ': Menjelaskan arti ungkapan yang terdapat dalam teks.')
    );
    $out .= html_writer::tag('h5', '2. Pemahaman Inferensial') . $ul2;

    // H5 + UL: 3. Evaluasi dan Apresiasi
    $ul3 = html_writer::tag('ul',
        html_writer::tag('li', html_writer::tag('strong', 'Relevansi peristiwa') . ': Menilai keterkaitan peristiwa dalam teks dengan kehidupan sehari-hari berdasarkan pengalaman atau pengetahuan pribadi.') .
        html_writer::tag('li', html_writer::tag('strong', 'Kesesuaian antarunsur') . ': Menilai konsistensi antarunsur atau antarinformasi dalam teks.') .
        html_writer::tag('li', html_writer::tag('strong', 'Respons emosional') . ': Menyimpulkan tanggapan emosional terhadap unsur dalam teks fiksi.')
    );
    $out .= html_writer::tag('h5', '3. Evaluasi dan Apresiasi') . $ul3;

    // Bungkus dalam box agar rapi
    return html_writer::tag('div', $out, [
        'class' => 'box generalbox',
        'style' => 'max-width:920px;margin:0 0 12px 0;'
    ]);
})();

/** =======================================================================
 *  Fungsi pivot + render tabel per subject
 *  =======================================================================
 */
$render_subject_table = function(string $subject, array $subject_records, string $prefacehtml = '') use ($OUTPUT) {
    echo $OUTPUT->heading(format_string($subject), 3);

    // Khusus Bahasa Indonesia, tampilkan preface (jika ada)
    if ($subject === 'Bahasa Indonesia' && $prefacehtml !== '') {
        echo $prefacehtml;
    }

    if (empty($subject_records)) {
        echo html_writer::div('Tidak ada data laporan untuk ' . s($subject) . ' pada pengguna ini.', 'alert alert-info');
        return;
    }

    // Pivoting
    $pivoted_data = [];
    $all_categories = [];

    foreach ($subject_records as $record) {
        $quiz_key = $record->qa_id;

        if (!isset($pivoted_data[$quiz_key])) {
            $pivoted_data[$quiz_key] = [
                'quiz_name'  => $record->quiz_name,
                'quizid'     => $record->qa_id,
                'categories' => []
            ];
        }

        // Header kategori pakai category_name dari view (sudah “dipotong” rapi)
        $pivoted_data[$quiz_key]['categories'][$record->category_name] = [
            'total'   => (int)$record->total_questions_in_category,
            'correct' => (int)$record->correct_questions_in_category
        ];

        if (!in_array($record->category_name, $all_categories, true)) {
            $all_categories[] = $record->category_name;
        }
    }

    sort($all_categories, SORT_NATURAL | SORT_FLAG_CASE);

    // Tabel
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = ['Nama Kuis'];
    foreach ($all_categories as $category) {
        $table->head[] = s($category);
    }

    // Baris data
    $table->data = [];
    foreach ($pivoted_data as $data) {
        $row = [ s($data['quiz_name']) ];

        foreach ($all_categories as $category) {
            $correct = isset($data['categories'][$category]['correct']) ? (int)$data['categories'][$category]['correct'] : 0;
            $total   = isset($data['categories'][$category]['total'])   ? (int)$data['categories'][$category]['total']   : 0;

            $percentage = ($total > 0) ? ($correct / $total) * 100.0 : 0.0;

            if ($percentage >= 90) {
                $assessment = 'Excellent';
            } elseif ($percentage >= 70) {
                $assessment = 'Kompeten';
            } elseif ($percentage >= 50) {
                $assessment = 'Bisa';
            } else {
                $assessment = 'Kurang';
            }

            $row[] = $assessment;
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
};

/** =======================================================================
 *  Render dua tabel terpisah
 *  =======================================================================
 */
$render_subject_table('Matematika', $raw_by_subject['Matematika']);
echo html_writer::empty_tag('hr', ['class' => 'my-4']);
$render_subject_table('Bahasa Indonesia', $raw_by_subject['Bahasa Indonesia'], $bi_preface);

// Jika semua kosong (tidak ada record yang terklasifikasi)
if (empty($raw_by_subject['Matematika']) && empty($raw_by_subject['Bahasa Indonesia'])) {
    echo html_writer::div('Tidak ada data laporan yang ditemukan untuk pengguna ini.', 'alert alert-info');
}

echo $OUTPUT->footer();
