<?php
require_once(__DIR__ . '/../../config.php');

$attemptid = required_param('attempt', PARAM_INT);
require_login();

// Ambil record attempt
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

// Validasi user
if ($attempt->userid != $USER->id && !is_siteadmin()) {
    throw new moodle_exception('accessdenied', 'admin');
}

// Ambil data quiz dan course
$quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

// Ambil course module untuk customreport berdasarkan course
$customreport = $DB->get_record_sql("
    SELECT cm.*, cr.name
    FROM {course_modules} cm
    JOIN {modules} md ON md.id = cm.module
    JOIN {customreport} cr ON cr.id = cm.instance
    WHERE cm.course = :courseid AND md.name = 'customreport'
    LIMIT 1", ['courseid' => $course->id], IGNORE_MISSING);

if (!$customreport) {
    // Fallback: Gunakan course module dari quiz jika customreport tidak ditemukan
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('customreport', $customreport->id, $course->id, false, MUST_EXIST);
}

$context = context_module::instance($cm->id);

// Siapkan halaman
$PAGE->set_url('/mod/customreport/graph.php', ['attempt' => $attemptid]);
$PAGE->set_title("Quiz Performance Graph: " . format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));

// Output header
echo $OUTPUT->header();
echo $OUTPUT->heading("Quiz Performance Graph: " . format_string($quiz->name));

// Data dummy untuk grafik
$correct = $attempt->sumgrades;
$incorrect = $quiz->sumgrades - $correct;

// Tombol kembali ke view.php
$backurl = new moodle_url('/mod/customreport/view.php', ['id' => $cm->id]);
echo html_writer::link($backurl, 'Back to View', ['class' => 'btn btn-secondary mb-3']);

// Output grafik
?>
<!-- Include Chart.js with a specific version -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<canvas id="performanceChart" width="400" height="200"></canvas>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Periksa apakah Chart.js tersedia
        if (typeof Chart === 'undefined') {
            console.error('Chart.js failed to load');
            document.getElementById('performanceChart').style.display = 'none';
            document.body.insertAdjacentHTML('beforeend', '<div class="alert alert-danger">Error: Unable to load chart library.</div>');
            return;
        }

        var ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Correct', 'Incorrect'],
                datasets: [{
                    data: [<?php echo $correct; ?>, <?php echo $incorrect; ?>],
                    backgroundColor: ['#36A2EB', '#FF6384'],
                    borderColor: ['#2E86C1', '#E74C3C'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Quiz Performance'
                    }
                }
            }
        });
    });
</script>
<?php
echo $OUTPUT->footer();
?>