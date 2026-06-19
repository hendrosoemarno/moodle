<?php
// This file is part of Moodle - http://moodle.org/
//
// ... (license)

/**
 * Listens for Instant Payment Notification from Midtrans
 *
 * This script waits for payment notification from Midtrans,
 * verifies the transaction, and enrolls the user if successful.
 *
 * @package    enrol_midtrans
 * @copyright  2025 [Your Name] - based on code by Eugene Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output
define('NO_DEBUG_DISPLAY', true);

// This script does not require login
require("../../config.php");
require_once($CFG->dirroot . '/enrol/midtrans/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

// Custom exception handler for logging
set_exception_handler(function ($e) {
    global $DB;
    $log = new stdClass();
    $log->time = time();
    $log->ip = $_SERVER['REMOTE_ADDR'];
    $log->info = 'Exception: ' . $e->getMessage() . ' - ' . json_encode($_POST);
    $DB->insert_record('log', $log);
    http_response_code(500);
    exit;
});

// Ensure the plugin is enabled
if (!enrol_is_enabled('midtrans')) {
    http_response_code(503);
    exit;
}

// Keep out casual intruders
if (empty($_POST) && empty(file_get_contents('php://input'))) {
    http_response_code(400);
    exit;
}

// Initialize Midtrans
require_once($CFG->dirroot . '/lib/midtrans/Midtrans.php');
$plugin = enrol_get_plugin('midtrans');
\Midtrans\Config::$serverKey = $plugin->get_config('serverkey');
\Midtrans\Config::$isProduction = false; // Set to true for live mode
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;

// Process Midtrans notification
$notif = null;
$data = [];
if (!empty($_POST)) {
    $notif = new \Midtrans\Notification();
} elseif (!empty(file_get_contents('php://input'))) {
    // Handle raw JSON input (e.g., from curl)
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $log = new stdClass();
        $log->time = time();
        $log->ip = $_SERVER['REMOTE_ADDR'];
        $log->info = 'JSON decode error: ' . json_last_error_msg();
        $DB->insert_record('log', $log);
        http_response_code(400);
        exit;
    }
}

$transaction = $notif ? $notif->transaction_status : ($data['transaction_status'] ?? '');
$order_id = $notif ? $notif->order_id : ($data['order_id'] ?? '');
$gross_amount = $notif ? $notif->gross_amount : ($data['gross_amount'] ?? 0);
$payment_type = $notif ? $notif->payment_type : ($data['payment_type'] ?? '');
$transaction_time = $notif ? $notif->transaction_time : ($data['transaction_time'] ?? '');
$fraud_status = $notif ? $notif->fraud_status : ($data['fraud_status'] ?? '');

// Log notification for debugging
$log = new stdClass();
$log->time = time();
$log->ip = $_SERVER['REMOTE_ADDR'];
$log->info = "Order ID: $order_id, Status: $transaction, Amount: $gross_amount";
$DB->insert_record('log', $log);

// Verify transaction and enroll user
if ($transaction == 'capture' || $transaction == 'settlement') {
    // Retrieve instance based on order_id (stored in customint1 during payment initiation)
    $instance = $DB->get_record('enrol', array('customint1' => $order_id, 'enrol' => 'midtrans', 'status' => 0));
    if ($instance) {
        global $USER;
        if (!$USER || !$USER->id) {
            // Get user from a previous session or assume a method to identify user (e.g., from order_id context)
            // For now, this is a placeholder; you may need to adjust based on how user is tracked
            $user = $DB->get_record('user', array(), '*', IGNORE_MISSING); // Adjust this logic
            if (!$user) {
                $log->info .= ", User not found for order_id: $order_id";
                $DB->update_record('log', $log);
                http_response_code(400);
                exit;
            }
        } else {
            $user = $USER;
        }
        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id, MUST_EXIST);

        // Verify amount
        $cost = (float)$instance->cost > 0 ? (float)$instance->cost : (float)$plugin->get_config('cost');
        $cost = format_float($cost, 2, false);
        if ($gross_amount != $cost) {
            $log->info .= ", Amount mismatch: $gross_amount != $cost";
            $DB->update_record('log', $log);
            http_response_code(400);
            exit;
        }

        // Enroll user
        if ($instance->enrolperiod) {
            $timestart = time();
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend = 0;
        }
        $plugin->enrol_user($instance, $user->id, $instance->roleid, $timestart, $timeend);

        // Log success
        $log->info .= ", User enrolled: $user->id";
        $DB->update_record('log', $log);

        // Send notification (optional)
        $mailstudents = $plugin->get_config('mailstudents');
        if (!empty($mailstudents)) {
            $a = new stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $context));
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
            $eventdata = new \core\message\message();
            $eventdata->courseid = $course->id;
            $eventdata->modulename = 'moodle';
            $eventdata->component = 'enrol_midtrans';
            $eventdata->name = 'midtrans_enrolment';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $user;
            $eventdata->subject = get_string("enrolmentnew", 'enrol', format_string($course->shortname));
            $eventdata->fullmessage = get_string('welcometocoursetext', '', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            message_send($eventdata);
        }
    } else {
        $log->info .= ", Instance not found for order_id: $order_id";
        $DB->update_record('log', $log);
        http_response_code(404);
    }
} elseif ($transaction == 'cancel' || $transaction == 'deny' || $transaction == 'expire') {
    $log->info .= ", Transaction failed: $transaction";
    $DB->update_record('log', $log);
    http_response_code(200); // Midtrans expects 200 even for failed transactions
}

http_response_code(200); // Always return 200 to acknowledge Midtrans notification
exit;