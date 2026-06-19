<?php
// File: local/payment/return.php
require('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);

// set context course
$PAGE->set_context(context_course::instance($course->id));

// tambahkan notifikasi sukses
\core\notification::success(get_string('paymentsuccess', 'local_payment', $course->fullname));

// redirect ke halaman course
redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
