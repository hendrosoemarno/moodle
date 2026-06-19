<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // ID dari course module
$cm = get_coursemodule_from_id('customreport', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$customreport = $DB->get_record('customreport', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/customreport:view', $context);

$PAGE->set_url('/mod/customreport/view.php', ['id' => $id]);
$PAGE->set_title(format_string($customreport->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($customreport->name));

// Ambil data quiz attempt dalam 30 hari terakhir
$sql = "SELECT qa.id AS quizattid, qa.userid, u.username,
               q.id AS quizid, q.name AS quizname,
               c.id AS courseid, c.fullname AS course,
               q.sumgrades AS jmlsoal, qa.sumgrades AS jmlbenar,
               qa.timestart, qa.timefinish
        FROM {quiz_attempts} qa
        INNER JOIN {user} u ON qa.userid = u.id
        INNER JOIN {quiz} q ON q.id = qa.quiz
        INNER JOIN {course} c ON q.course = c.id
        WHERE qa.timestart > :recenttime
        ORDER BY qa.timestart DESC";

$params = ['recenttime' => time() - 3024000];
$records = $DB->get_records_sql($sql, $params);

if ($records) {
    $table = new html_table();
    $table->head = [
        'Username', 'Course', 'Quiz',
        'Jumlah Soal', 'Benar',
        'Mulai', 'Selesai', 'Aksi'
    ];
    $table->data = [];

    foreach ($records as $r) {
        // Buat URL ke halaman review
        $reviewurl = new moodle_url('/mod/customreport/review.php', ['attempt' => $r->quizattid]);
        // $button = html_writer::link($reviewurl, 'Review', ['class' => 'btn btn-primary']);
        $button = html_writer::link($reviewurl, 'Review', ['class' => 'btn btn-primary', 'target' => '_blank']);

$graphurl = new moodle_url('/mod/customreport/graph.php', ['attempt' => $r->quizattid]);
$graphbutton = html_writer::link($graphurl, 'View Graph', ['class' => 'btn btn-secondary btn-sm ml-2', 'target' => '_blank']);

        $table->data[] = [
            s($r->username),
            format_string($r->course),
            format_string($r->quizname),
            format_float($r->jmlsoal, 0),
            format_float($r->jmlbenar, 0),
            userdate($r->timestart),
            userdate($r->timefinish),
            $button,
            $graphbutton
        ];
    }

    echo html_writer::table($table);
} else {
    echo html_writer::div('Tidak ada quiz yang dikerjakan dalam 30 hari terakhir.', 'alert alert-info');
}

echo $OUTPUT->footer();
