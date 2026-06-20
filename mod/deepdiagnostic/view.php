<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('deepdiagnostic', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$deepdiagnostic = $DB->get_record('deepdiagnostic', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/deepdiagnostic:view', $context);

$PAGE->set_url('/mod/deepdiagnostic/view.php', ['id' => $id]);
$PAGE->set_title(format_string($deepdiagnostic->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($deepdiagnostic->name));

$currentuserid = $USER->id;

$quizzes = $DB->get_records('quiz', ['course' => $course->id], 'id ASC');

$deepquizzes = [];
foreach ($quizzes as $q) {
    if (preg_match('/^Deep\s+(\d+)\s+(.+)$/i', $q->name, $matches)) {
        $deepquizzes[] = [
            'id'       => $q->id,
            'number'   => (int)$matches[1],
            'topic'    => trim($matches[2]),
            'maxgrade' => (float)$q->grade,
        ];
    }
}

usort($deepquizzes, fn($a, $b) => $a['number'] <=> $b['number']);

if (empty($deepquizzes)) {
    echo html_writer::div(get_string('no_quizzes', 'deepdiagnostic'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$quizdata = [];
$highestattempted = 0;
foreach ($deepquizzes as $qz) {
    $best = $DB->get_record_sql('
        SELECT MAX(sumgrades) AS bestgrade
        FROM {quiz_attempts}
        WHERE quiz = :quizid AND userid = :userid AND state = :state
    ', ['quizid' => $qz['id'], 'userid' => $currentuserid, 'state' => 'finished']);

    $hasattempt = $best && $best->bestgrade !== null;
    $percentage = 0;
    if ($hasattempt) {
        $percentage = ($qz['maxgrade'] > 0) ? ($best->bestgrade / $qz['maxgrade']) * 100 : 0;
        if ($qz['number'] > $highestattempted) {
            $highestattempted = $qz['number'];
        }
    }

    $quizdata[] = [
        'number'      => $qz['number'],
        'topic'       => $qz['topic'],
        'hasattempt'  => $hasattempt,
        'percentage'  => $percentage,
    ];
}

$results = [];
$highesttopic = '';
foreach ($quizdata as $qd) {
    if (!$qd['hasattempt']) {
        continue;
    }

    if ($qd['number'] < $highestattempted) {
        $statustext = 'sudah bisa dan sudah kompeten';
    } else {
        if ($qd['percentage'] <= 0) {
            $statustext = 'belum kompeten';
        } elseif ($qd['percentage'] < 50) {
            $statustext = 'sudah bisa namun belum kompeten';
        } else {
            $statustext = 'sudah bisa dan sudah kompeten';
        }
        $highesttopic = $qd['topic'];
    }

    $results[] = [
        'number'     => $qd['number'],
        'topic'      => $qd['topic'],
        'statustext' => $statustext,
    ];
}

$studentname = fullname($USER);

$headertext = '';
if (!empty($deepdiagnostic->template_header)) {
    $headertext = format_text($deepdiagnostic->template_header, $deepdiagnostic->template_headerformat ?? FORMAT_HTML, ['context' => $context]);
}
$headertext = str_replace('{studentname}', s($studentname), $headertext);
echo html_writer::div($headertext, 'deepdiagnostic-header');

$itemshtml = '';
foreach ($results as $r) {
    $itemshtml .= html_writer::tag('p', '🔹 ' . s($r['topic']) . ' ' . s($r['statustext']));
}
echo html_writer::div($itemshtml, 'deepdiagnostic-items', ['style' => 'margin: 16px 0;']);

$footertext = '';
if (!empty($deepdiagnostic->template_footer)) {
    $footertext = format_text($deepdiagnostic->template_footer, $deepdiagnostic->template_footerformat ?? FORMAT_HTML, ['context' => $context]);
}
$footertext = str_replace('{studentname}', s($studentname), $footertext);
$footertext = str_replace('{levelnum}', (string)$highestattempted, $footertext);
$footertext = str_replace('{leveltopic}', s($highesttopic), $footertext);
echo html_writer::div($footertext, 'deepdiagnostic-footer');

echo $OUTPUT->footer();
