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

/** =========================================================================
 * Kumpulkan daftar quiz di course INI sebagai filter
 * ========================================================================= */
$coursequizids = $DB->get_fieldset_select('quiz', 'id', 'course = :cid', ['cid' => $course->id]);

/** =========================================================================
 * Ambil data dari view (HANYA untuk course saat ini)
 * ========================================================================= */
$current_userid = $USER->id;
$raw_records = [];

if (!empty($coursequizids)) {
    list($inquizsql, $inquizparams) = $DB->get_in_or_equal($coursequizids, SQL_PARAMS_NAMED);
    $sql = "
        SELECT
            qas_id,
            userid,
            qa_id,
            quiz_id,
            quiz_name,
            category_name,
            qc_id,
            total_questions_in_category,
            correct_questions_in_category
        FROM
            vw_nilai_qc
        WHERE
            userid = :userid
            AND quiz_id $inquizsql
    ";
    $params = array_merge(['userid' => $current_userid], $inquizparams);
    $raw_records = $DB->get_records_sql($sql, $params);
}

/** =========================================================================
 * Ambil nama kategori asli (qc.name) via qc_id (1 query batch)
 * ========================================================================= */
$qcmap = []; // [qc_id => question_categories.name]
if (!empty($raw_records)) {
    $qcids = [];
    foreach ($raw_records as $r) {
        if (!empty($r->qc_id)) {
            $qcids[(int)$r->qc_id] = true;
        }
    }
    $qcids = array_keys($qcids);

    if (!empty($qcids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($qcids, SQL_PARAMS_NAMED);
        $qcrecs = $DB->get_records_select('question_categories', "id $insql", $inparams, '', 'id, name');
        foreach ($qcrecs as $qr) {
            $qcmap[(int)$qr->id] = $qr->name; // nama kategori penuh (bisa berisi tag bin/mat)
        }
    }
}

/** =========================================================================
 * Deteksi subject berdasarkan tag 'bin' / 'mat'
 * ========================================================================= */
$detect_subject_by_tag = function(string $text): ?string {
    $lc = mb_strtolower(' ' . $text . ' ');
    if (preg_match('/(^|[\s\-])bin([\s\-\)]|$)/u', $lc)) return 'Bahasa Indonesia';
    if (preg_match('/(^|[\s\-])mat([\s\-\)]|$)/u', $lc)) return 'Matematika';
    return null;
};

// Bagi data ke dua subject
$raw_by_subject = [
    'Matematika' => [],
    'Bahasa Indonesia' => [],
];

foreach ($raw_records as $rec) {
    $fullcat = $qcmap[$rec->qc_id] ?? $rec->category_name;
    $subject = $detect_subject_by_tag($fullcat) ?? $detect_subject_by_tag($rec->quiz_name);
    if ($subject && array_key_exists($subject, $raw_by_subject)) {
        $raw_by_subject[$subject][] = $rec;
    }
}

/** =========================================================================
 * Fungsi pivot + render tabel per subject
 * ========================================================================= */
$render_subject_table = function(string $subject, array $subject_records) use ($OUTPUT) {
    echo $OUTPUT->heading(format_string($subject), 3);

    if (empty($subject_records)) {
        echo html_writer::div('Tidak ada data laporan untuk ' . s($subject) . ' pada course ini.', 'alert alert-info');
        return;
    }

    $pivoted_data  = [];
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

    // Data baris
    $table->data = [];
    foreach ($pivoted_data as $data) {
        $row = [ s($data['quiz_name']) ];

        foreach ($all_categories as $category) {
            $correct = isset($data['categories'][$category]['correct']) ? (int)$data['categories'][$category]['correct'] : 0;
            $total   = isset($data['categories'][$category]['total'])   ? (int)$data['categories'][$category]['total']   : 0;

            $percentage = ($total > 0) ? ($correct / $total) * 100.0 : 0.0;
            if     ($percentage >= 90) $assessment = 'Excellent';
            elseif ($percentage >= 70) $assessment = 'Kompeten';
            elseif ($percentage >= 50) $assessment = 'Bisa';
            else                       $assessment = 'Kurang';

            $row[] = $assessment;
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
};

/** =========================================================================
 * Render dua tabel (atas)
 * ========================================================================= */
$render_subject_table('Matematika', $raw_by_subject['Matematika']);
echo html_writer::empty_tag('hr', ['class' => 'my-4']);
$render_subject_table('Bahasa Indonesia', $raw_by_subject['Bahasa Indonesia']);

/** =========================================================================
 * Bagian bawah: render Keterangan Penilaian & Kompetensi BI dari DB (selalu muncul)
 * ========================================================================= */

// Garis pemisah
echo html_writer::empty_tag('hr', ['class' => 'my-4']);

// 1) Keterangan Penilaian
if (!empty($studentreport->legend)) {
    echo format_text($studentreport->legend, $studentreport->legendformat ?? FORMAT_HTML, ['context' => $context]);
} else {
    // Fallback default singkat jika belum diisi lewat mod_form.
    $legend = new html_table();
    $legend->attributes = ['class' => 'generaltable'];
    $legend->head = ['Persentase Capaian', 'Kategori', 'Makna Umum'];
    $legend->data = [
        ['0–49%',   html_writer::tag('strong', 'Kurang'),    'Belum menguasai materi'],
        ['50–69%',  html_writer::tag('strong', 'Bisa'),      'Baru mulai bisa, masih butuh bimbingan'],
        ['70–89%',  html_writer::tag('strong', 'Kompeten'),  'Sudah cukup menguasai kompetensi'],
        ['90–100%', html_writer::tag('strong', 'Excellent'), 'Sudah menguasai kompetensi'],
    ];
    echo html_writer::tag('div',
        html_writer::tag('h4', 'Keterangan Penilaian', ['class' => 'mb-2']) .
        html_writer::table($legend),
        ['class' => 'box generalbox', 'style' => 'max-width:820px;margin:16px 0;']
    );
}

// 2) Kompetensi Bahasa Indonesia
if (!empty($studentreport->kompetensi)) {
    echo format_text($studentreport->kompetensi, $studentreport->kompetensiformat ?? FORMAT_HTML, ['context' => $context]);
} else {
    // Fallback default singkat jika belum diisi lewat mod_form.
    $out  = html_writer::tag('h4', 'Kompetensi Singkat Membaca Teks', ['class' => 'mb-2']);
    $out .= html_writer::tag('p', 'Silakan isi keterangan kompetensi di pengaturan aktivitas (mod_form).');
    echo html_writer::tag('div', $out, ['class' => 'box generalbox', 'style' => 'max-width:920px;margin:16px 0;']);
}

echo $OUTPUT->footer();
