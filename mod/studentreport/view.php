<?php
// File: mod/studentreport/view.php

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
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

$coursequizids = $DB->get_fieldset_select(
    'quiz',
    'id',
    'course = :cid AND ' . $DB->sql_like('name', ':qname', false),
    [
        'cid'   => $course->id,
        'qname' => '%Try Out%'
    ]
);

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

$qcmap = [];
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
            $qcmap[(int)$qr->id] = $qr->name;
        }
    }
}

$subject_map = [
    'mat'      => 'Matematika',
    'matlan'   => 'Matematika Lanjut',
    'bin'      => 'Bahasa Indonesia',
    'binlan'   => 'Bahasa Indonesia Lanjut',
    'bing'     => 'Bahasa Inggris',
    'binglan'  => 'Bahasa Inggris Lanjut',
    'fis'      => 'Fisika',
    'kim'      => 'Kimia',
    'bio'      => 'Biologi',
    'eko'      => 'Ekonomi',
    'geo'      => 'Geografi',
    'sos'      => 'Sosiologi',
    'sej'      => 'Sejarah',
];

$detect_subject_by_tag = function(string $text) use ($subject_map): ?string {
    $lc = mb_strtolower(' ' . $text . ' ');
    foreach ($subject_map as $tag => $name) {
        if (preg_match('/(^|[\s\-])' . preg_quote($tag, '/') . '([\s\-\)]|$)/u', $lc)) {
            return $name;
        }
    }
    return null;
};

$raw_by_subject = [];
foreach ($subject_map as $tag => $name) {
    $raw_by_subject[$name] = [];
}

foreach ($raw_records as $rec) {
    $fullcat = $qcmap[$rec->qc_id] ?? $rec->category_name;
    $subject = $detect_subject_by_tag($fullcat) ?? $detect_subject_by_tag($rec->quiz_name);
    if ($subject && isset($raw_by_subject[$subject])) {
        $raw_by_subject[$subject][] = $rec;
    }
}

$render_subject_table = function(string $subject, array $subject_records) use ($OUTPUT) {
    echo $OUTPUT->heading(format_string($subject), 3);

    if (empty($subject_records)) {
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

    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = ['Nama Kuis'];
    foreach ($all_categories as $category) {
        $table->head[] = s($category);
    }

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

$anydata = false;
foreach ($subject_map as $tag => $name) {
    $data = $raw_by_subject[$name] ?? [];
    if (empty($data)) {
        continue;
    }
    if ($anydata) {
        echo html_writer::empty_tag('hr', ['class' => 'my-4']);
    }
    $render_subject_table($name, $data);
    $anydata = true;
}

if (!$anydata) {
    echo html_writer::div('Tidak ada data laporan untuk course ini.', 'alert alert-info');
}

echo html_writer::empty_tag('hr', ['class' => 'my-4']);

if (!empty($studentreport->legend)) {
    echo format_text($studentreport->legend, $studentreport->legendformat ?? FORMAT_HTML, ['context' => $context]);
} else {
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

if (!empty($studentreport->kompetensi)) {
    echo format_text($studentreport->kompetensi, $studentreport->kompetensiformat ?? FORMAT_HTML, ['context' => $context]);
} else {
    $out  = html_writer::tag('h4', 'Kompetensi Singkat Membaca Teks', ['class' => 'mb-2']);
    $out .= html_writer::tag('p', 'Silakan isi keterangan kompetensi di pengaturan aktivitas (mod_form).');
    echo html_writer::tag('div', $out, ['class' => 'box generalbox', 'style' => 'max-width:920px;margin:16px 0;']);
}

echo $OUTPUT->footer();
