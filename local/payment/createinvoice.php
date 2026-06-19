<?php

//////////sudah sukses redirect ke duitku sesuai pilihan paket

// File: /local/payment/createinvoice.php
require('../../config.php');
require_once($CFG->dirroot . '/user/profile/lib.php'); // custom profile fields

$courseid = required_param('courseid', PARAM_INT);
$plan     = optional_param('plan', '', PARAM_ALPHANUMEXT); // contoh: onetime, monthly, quarterly, yearly

// Halaman (tidak benar2 ditampilkan karena redirect)
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/payment/createinvoice.php', ['courseid' => $courseid, 'plan' => $plan]));
$PAGE->set_pagelayout('standard');

// Wajib login & akses course
if (!isloggedin() || isguestuser()) {
    \core\notification::warning(get_string('notloggedin', 'local_payment'));
    redirect(new moodle_url('/login/index.php'));
}
require_login($courseid);

// Ambil course
$course = get_course($courseid);
if (!$course) {
    \core\notification::error(get_string('missingcourse', 'local_payment'));
    redirect(new moodle_url('/'));
    exit;
}

/**
 * Helper: baca custom field course "billing_options".
 * Mendukung value berformat HTML (<p>...)</p> atau <br>, yang akan dinormalisasi jadi baris teks.
 * Format setiap baris: key|label|harga|durasi_hari (durasi opsional)
 * Return: ['key'=>['label'=>'...','price'=>int,'days'=>int]]
 */
function local_payment_get_billing_options(int $courseid): array {
    $options = [];

    if (!class_exists('\core_customfield\handler')) {
        return $options;
    }
    $handler = \core_customfield\handler::get_handler('core_course', 'course');
    if (!$handler) {
        return $options;
    }

    $dcs = $handler->get_instance_data($courseid, true);
    foreach ($dcs as $dc) {
        $field = $dc->get_field();
        if (strtolower((string)$field->get('shortname')) !== 'billing_options') {
            continue;
        }

        // 1) Ambil nilai mentah (bisa HTML)
        $raw = (string)$dc->get_value();

        // 2) Normalisasi HTML -> newline, lalu strip tag
        $norm = preg_replace('/<\/p>\s*/i', "\n", $raw);
        $norm = preg_replace('/<br\s*\/?>/i', "\n", $norm);
        $norm = strip_tags($norm);
        $norm = str_replace(["\r\n", "\r"], "\n", $norm);
        $norm = trim($norm);

        // (opsional) LOG diagnostik
        $diagdir = $GLOBALS['CFG']->dataroot . '/temp/duitku_diag/';
        @mkdir($diagdir, 0777, true);
        @file_put_contents(
            $diagdir.'billing_fix_'.date('Ymd_His').'.log',
            "RAW:\n$raw\n\nNORM:\n$norm\n\n",
            FILE_APPEND
        );

        if ($norm === '') {
            break;
        }

        // 3) Parse per baris: key|label|price(|days)
        foreach (explode("\n", $norm) as $line) {
            $line = trim($line);
            if ($line === '') { continue; }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) { continue; }

            $key   = strtolower($parts[0]); // lookup case-insensitive
            $label = $parts[1];
            $price = preg_replace('/[^\d]/', '', $parts[2]);
            $days  = isset($parts[3]) ? (int)$parts[3] : 0;

            if ($key !== '' && $label !== '' && $price !== '' && is_numeric($price)) {
                $options[$key] = [
                    'label' => $label,
                    'price' => (int)$price,
                    'days'  => $days > 0 ? $days : 0,
                ];
            }
        }

        break; // selesai setelah menemukan field-nya
    }

    // (opsional) LOG hasil parse
    $diagdir = $GLOBALS['CFG']->dataroot . '/temp/duitku_diag/';
    @mkdir($diagdir, 0777, true);
    @file_put_contents(
        $diagdir.'billing_parsed_'.date('Ymd_His').'.log',
        print_r($options, true) . "\n",
        FILE_APPEND
    );

    return $options;
}

// ===========================
// 1) Kredensial dari settings plugin
// ===========================
$merchantCode = (string)(get_config('local_payment', 'merchantcode') ?? '');
$merchantKey  = (string)(get_config('local_payment', 'merchantkey') ?? '');
$isSandbox    = (bool)(get_config('local_payment', 'sandbox') ?? true);

if ($merchantCode === '' || $merchantKey === '') {
    \core\notification::error('Merchant Code atau Merchant Key belum diisi di Site administration → Plugins → Local plugins → Pembayaran (Gateway).');
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    exit;
}

// ===========================
// 2) Tentukan harga & label produk
// ===========================
$productBase   = format_string($course->fullname);
$productLabel  = '';
$paymentAmount = 101000; // fallback jika plan & price tidak ada

$plans = local_payment_get_billing_options($course->id);
$plan_lookup = strtolower(trim($plan));

// --- DIAGNOSTIK: cek plan & plan_lookup ---
$diagdir = $CFG->dataroot . '/temp/duitku_diag/';
@mkdir($diagdir, 0777, true);

$plancheck  = "=== Plan check at ".date('c')." ===\n";
$plancheck .= "Raw plan param   : " . var_export($plan, true) . "\n";
$plancheck .= "Plan lookup      : " . $plan_lookup . "\n";
$plancheck .= "Available keys   : " . implode(',', array_keys($plans)) . "\n";
$plancheck .= "Plans array      :\n" . print_r($plans, true) . "\n";

$planhit = ($plan_lookup !== '' && isset($plans[$plan_lookup])) ? 'YES' : 'NO';
$plancheck .= "Will enter IF?    : " . $planhit . "\n";

@file_put_contents(
    $diagdir . 'plan_check_' . date('Ymd_His') . '.log',
    $plancheck . "\n",
    FILE_APPEND
);

if ($plan_lookup !== '' && isset($plans[$plan_lookup])) {
    // pakai paket dari billing_options
    $paymentAmount = (int)$plans[$plan_lookup]['price'];
    $productLabel  = $plans[$plan_lookup]['label'];

    // log bahwa cabang ini dieksekusi
    $hit  = ">>> ENTERED IF at ".date('c')."\n";
    $hit .= "Chosen key   : " . $plan_lookup . "\n";
    $hit .= "Chosen label : " . $productLabel . "\n";
    $hit .= "Chosen price : " . $paymentAmount . "\n\n";
    @file_put_contents(
        $CFG->dataroot . '/temp/duitku_diag/plan_check_' . date('Ymd_His') . '.log',
        $hit,
        FILE_APPEND
    );
} else {
    // fallback: custom field 'price' (harus text input agar bebas angka besar)
    try {
        if (class_exists('\core_customfield\handler')) {
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            if ($handler) {
                $dcs = $handler->get_instance_data($course->id, true);
                foreach ($dcs as $dc) {
                    $field = $dc->get_field();
                    if (strtolower((string)$field->get('shortname')) === 'price') {
                        $raw = $dc->get_value();
                        if (is_string($raw)) {
                            $raw = preg_replace('/[^\d]/', '', $raw);
                        }
                        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
                            $paymentAmount = (int)$raw;
                        }
                        break;
                    }
                }
            }
        }
    } catch (\Throwable $e) { /* ignore */ }
}
$productDetail = $productBase . ($productLabel ? ' — ' . $productLabel : '');

// ===========================
// 3) Data pelanggan
// ===========================
$email = (string)$USER->email;

// Ambil nomor dari custom profile field "Parent WA Number" (beberapa kemungkinan shortname)
$phoneNumber = '';
$extra = profile_user_record($USER->id, false);
$tryshortnames = ['parentwanumber', 'parent_wa', 'parent_wa_number', 'parentwa', 'wa', 'whatsapp_parent'];
if (!empty($extra)) {
    foreach ($tryshortnames as $sn) {
        if (!empty($extra->$sn)) {
            $phoneNumber = trim((string)$extra->$sn);
            break;
        }
    }
}
if ($phoneNumber === '') {
    if (!empty($USER->phone1)) {
        $phoneNumber = $USER->phone1;
    } else if (!empty($USER->phone2)) {
        $phoneNumber = $USER->phone2;
    }
}
if ($phoneNumber === '') {
    \core\notification::warning(get_string('phonehint', 'local_payment'));
    $phoneNumber = '08123456789'; // fallback uji (hindari di produksi)
}

$firstname = trim($USER->firstname ?? '');
$lastname  = trim($USER->lastname ?? '');

// ===========================
// 4) OrderId unik + URLs
// ===========================
$merchantOrderId = 'ORD-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
$callbackUrl     = (new moodle_url('/local/payment/callback.php'))->out(false);
$returnUrl       = (new moodle_url('/local/payment/return.php', ['courseid' => $courseid]))->out(false);

// ===========================
// 5) Load library Duitku non-composer
// ===========================
require_once($CFG->dirroot . '/local/payment/thirdparty/duitku/Duitku.php');

// Konfig
$duitkuConfig = new \Duitku\Config($merchantKey, $merchantCode);
$duitkuConfig->setSandboxMode($isSandbox);
$duitkuConfig->setSanitizedMode(true);
$duitkuConfig->setDuitkuLogs(true);

// customerDetail untuk tab Pelanggan di redirect page
$customerDetail = array_filter([
    'firstName'   => $firstname ?: null,
    'lastName'    => $lastname ?: null,
    'email'       => $email ?: null,
    'phoneNumber' => $phoneNumber ?: null,
]);

// ===========================
// 6) Susun parameter POP
// ===========================
$params = [
    'paymentAmount'   => (int)$paymentAmount,
    'merchantOrderId' => $merchantOrderId,
    'productDetails'  => (string)$productDetail,

    // optional top-level
    'email'           => (string)$email,
    'phoneNumber'     => (string)$phoneNumber,
    'customerVaName'  => fullname($USER),

    'itemDetails'     => [[
        'name'     => (string)$productDetail,
        'price'    => (int)$paymentAmount,
        'quantity' => 1
    ]],
    'callbackUrl'     => $callbackUrl,
    'returnUrl'       => $returnUrl,
    'expiryPeriod'    => 60
];
if (!empty($customerDetail)) {
    $params['customerDetail'] = $customerDetail;
}

// (Opsional) metadata plan/days untuk callback enrolment otomatis
if ($plan_lookup !== '' && isset($plans[$plan_lookup])) {
    $params['additionalParam'] = json_encode([
        'courseid' => $courseid,
        'plan'     => $plan_lookup,
        'days'     => $plans[$plan_lookup]['days'] ?? 0,
        'userid'   => $USER->id, // <-- TAMBAHKAN INI
    ]);
}

// ===========================
// 7) Logging request (diagnostik)
// ===========================
$reqdir = $CFG->dataroot . '/temp/duitku_request/';
if (!file_exists($reqdir)) { @mkdir($reqdir, 0777, true); }
$reqfile = $reqdir . 'request_' . date('Ymd_His') . '.log';
$reqblob  = "=== Request at ".date('c')." ===\n";
$reqblob .= "User ID: {$USER->id}\n";
$reqblob .= "Course ID: {$courseid}\n";
$reqblob .= "Plan raw: " . ($plan !== '' ? $plan : '-') . "\n";
$reqblob .= "Plan lookup: " . ($plan_lookup !== '' ? $plan_lookup : '-') . "\n";
$reqblob .= "Available keys: " . implode(',', array_keys($plans)) . "\n";
$reqblob .= "Chosen amount: {$paymentAmount}\n";
$reqblob .= "Product detail: {$productDetail}\n";
$reqblob .= "MerchantCode: {$merchantCode}\n";
$reqblob .= "Sandbox: " . ($isSandbox ? 'true' : 'false') . "\n";
$reqblob .= "Payload:\n" . print_r($params, true) . "\n\n";
@file_put_contents($reqfile, $reqblob, FILE_APPEND);

// ===========================
// 8) Panggil API POP & tangani respons
// ===========================
try {
    $responseJson = \Duitku\Pop::createInvoice($params, $duitkuConfig);
} catch (Exception $e) {
    \core\notification::error(get_string('posterror', 'local_payment') . ' ' . s($e->getMessage()));
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    exit;
}

// Logging response
$resdir = $CFG->dataroot . '/temp/duitku_response/';
if (!file_exists($resdir)) { @mkdir($resdir, 0777, true); }
$resfile = $resdir . 'response_' . date('Ymd_His') . '.log';
$resblob  = "=== Response at ".date('c')." ===\n";
$resblob .= $responseJson . "\n\n";
@file_put_contents($resfile, $resblob, FILE_APPEND);

// Parse JSON
$data = json_decode($responseJson, true);
if (!is_array($data)) {
    \core\notification::error(get_string('badresponse', 'local_payment'));
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    exit;
}

// Ambil paymentUrl
$paymenturl = $data['paymentUrl'] ?? null;
if (empty($data['reference']) || empty($paymenturl)) {
    $snippet = s(mb_substr($responseJson, 0, 400));
    \core\notification::error(get_string('nopaymenturl', 'local_payment') . ' ' . $snippet);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    exit;
}

// Redirect user ke halaman pembayaran Duitku
redirect(new moodle_url($paymenturl));
