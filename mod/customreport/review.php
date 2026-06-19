<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/engine/renderer.php');

$attemptid = required_param('attempt', PARAM_INT);
require_login();

// Ambil record attempt
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

// Validasi user
if ($attempt->userid != $USER->id && !is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

// Ambil data quiz, course, dan cm
$quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Siapkan halaman
$PAGE->set_cm($cm, $course);
$PAGE->set_url(new moodle_url('/mod/customreport/review.php', ['attempt' => $attemptid]));
$PAGE->set_title("Review Quiz: " . format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading("Review of: " . format_string($quiz->name));

// Ringkasan attempt
echo html_writer::start_div('quiz-review-summary');
echo html_writer::tag('p', "<strong>Started:</strong> " . userdate($attempt->timestart));
echo html_writer::tag('p', "<strong>Finished:</strong> " . userdate($attempt->timefinish));
echo html_writer::tag('p', "<strong>Your Score:</strong> " . format_float($attempt->sumgrades, 2) . " / " . format_float($quiz->sumgrades, 2));
echo html_writer::end_div();

// Load question usage
$quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

// Konfigurasi tampilan soal
$displayoptions = new question_display_options();
$displayoptions->flags = question_display_options::HIDDEN;
$displayoptions->marks = question_display_options::MARK_AND_MAX;
$displayoptions->feedback = question_display_options::VISIBLE;
$displayoptions->numpartscorrect = question_display_options::VISIBLE;
$displayoptions->correctness = question_display_options::VISIBLE;

// Gunakan renderer internal dari question engine
$qeoutput = $PAGE->get_renderer('question');

// Render semua soal
$slots = $quba->get_slots();
foreach ($slots as $i => $slot) {
    echo html_writer::tag('h3', "Soal " . ($i + 1), ['style' => 'margin-top: 30px']);
    echo $quba->render_question($slot, $displayoptions, (string)($i + 1));
}



echo $OUTPUT->footer();
