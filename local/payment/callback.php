<?php
// File: local/payment/callback.php
define('NO_OUTPUT_BUFFERING', true);
require('../../config.php');

// Callback dari Duitku TIDAK butuh login user.
@ignore_user_abort(true);
@set_time_limit(60);

// Selalu balas HTTP 200. Duitku mengharapkan teks "SUCCESS".
header('Content-Type: text/plain; charset=utf-8');

// ====== Logging dasar ======
$logdir = $CFG->dataroot . '/temp/duitku_callback/';
if (!file_exists($logdir)) {
    @mkdir($logdir, 0777, true);
}
$logfile = $logdir . 'callback_' . date('Ymd_His') . '_' . uniqid() . '.log';
$log = function(string $msg) use ($logfile) {
    @file_put_contents($logfile, '['.date('c')."] $msg\n", FILE_APPEND);
};

// Ambil payload (Duitku bisa kirim JSON atau form POST).
$raw = file_get_contents('php://input') ?: '';
$payload = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($payload) || empty($payload)) {
    // fallback ke $_POST
    $payload = $_POST ?? [];
}
$log("RAW: " . ($raw !== '' ? $raw : '(empty)'));
$log('PARSED: ' . print_r($payload, true));

// ====== Ambil konfigurasi merchant ======
$merchantCode = (string)(get_config('local_payment', 'merchantcode') ?? '');
$merchantKey  = (string)(get_config('local_payment', 'merchantkey') ?? '');
if ($merchantCode === '' || $merchantKey === '') {
    $log('ERROR: merchantCode/merchantKey kosong di config plugin.');
    echo "SUCCESS"; // tetap SUCCESS agar Duitku tak spam; tapi log error
    exit;
}

// ====== Ambil field penting dari payload ======
$reference        = $payload['reference']        ?? ($payload['Reference']        ?? null);
$resultCode       = $payload['resultCode']       ?? ($payload['ResultCode']       ?? null);
$merchantOrderId  = $payload['merchantOrderId']  ?? ($payload['MerchantOrderId']  ?? null);
$amountStr        = $payload['amount']           ?? ($payload['Amount']           ?? null);
$signature        = $payload['signature']        ?? ($payload['Signature']        ?? null);
$merchantCodeIn   = $payload['merchantCode']     ?? ($payload['MerchantCode']     ?? null);
$additionalParam  = $payload['additionalParam']  ?? ($payload['AdditionalParam']  ?? null);

// Normalisasi amount ke integer (tanpa titik/koma)
$amount = 0;
if ($amountStr !== null) {
    $amount = (int)preg_replace('/[^\d]/', '', (string)$amountStr);
}

// ====== Validasi signature (sesuai dokumentasi Duitku) ======
// Umumnya: signature = md5(merchantCode + amount + merchantOrderId + merchantKey)
$expectedSig = md5($merchantCode . $amount . $merchantOrderId . $merchantKey);
$validSig = (is_string($signature) && strtolower($signature) === strtolower($expectedSig));
$validMc  = ($merchantCodeIn === $merchantCode);

// Log validasi
$log("Sig client : {$signature}");
$log("Sig expect : {$expectedSig}");
$log("Sig valid? : " . ($validSig ? 'YES' : 'NO'));
$log("MC  valid? : " . ($validMc ? 'YES' : 'NO'));

// ====== Cek status sukses ======
// Duitku biasanya pakai '00' untuk sukses. Kadang ada 'SUCCESS'.
$isSuccess = (strval($resultCode) === '00' || strtoupper(strval($resultCode)) === 'SUCCESS');

if (!$validSig || !$validMc) {
    $log('ERROR: Signature/MerchantCode tidak valid. Abort extend enrol.');
    echo "SUCCESS"; // Tetap 200 agar Duitku berhenti retry; kita sudah log
    exit;
}

if (!$isSuccess) {
    $log("INFO: Transaksi belum sukses. resultCode={$resultCode}. Tidak mengubah enrol.");
    echo "SUCCESS";
    exit;
}

// ====== Parse additionalParam untuk ambil courseid, userid, plan, days ======
$meta = [];
if (is_string($additionalParam) && $additionalParam !== '') {
    $tmp = json_decode($additionalParam, true);
    if (is_array($tmp)) {
        $meta = $tmp;
    }
}
$courseid = isset($meta['courseid']) ? (int)$meta['courseid'] : 0;
$userid   = isset($meta['userid'])   ? (int)$meta['userid']   : 0;
$plan     = isset($meta['plan'])     ? (string)$meta['plan']  : '';
$days     = isset($meta['days'])     ? (int)$meta['days']     : 0;

$log("META: courseid={$courseid}, userid={$userid}, plan={$plan}, days={$days}");

if ($courseid <= 0 || $userid <= 0) {
    $log('ERROR: courseid/userid tidak ditemukan di additionalParam. Tidak bisa extend enrol.');
    echo "SUCCESS";
    exit;
}

// ====== Validasi entitas Moodle ======
try {
    $course = get_course($courseid);
} catch (Throwable $e) {
    $log('ERROR: get_course gagal: ' . $e->getMessage());
    echo "SUCCESS";
    exit;
}
if (!$course || empty($course->id)) {
    $log('ERROR: Course tidak valid.');
    echo "SUCCESS";
    exit;
}

$user = core_user::get_user($userid, '*', MUST_EXIST);
if (!$user || empty($user->id)) {
    $log('ERROR: User tidak valid.');
    echo "SUCCESS";
    exit;
}

// ====== Tentukan enrol plugin/instance yang dipakai ======
// Strategi:
// 1. Jika user sudah memiliki enrolment pada course (plugin apapun), extend di instance itu jika memungkinkan.
// 2. Jika belum terdaftar, gunakan plugin 'manual' untuk mendaftarkan dengan durasi sesuai paket.

global $DB;

$instances = enrol_get_instances($courseid, true); // only enabled
$userenrol = null;
$targetinstance = null;

// Cari enrolment user yang sudah ada
if (!empty($instances)) {
    $enrolids = array_map(static function($inst) { return (int)$inst->id; }, $instances);
    list($inSql, $inParams) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'enrolid');
    $params = array_merge(['userid' => $userid], $inParams);
    $userenrols = $DB->get_records_select('user_enrolments', "userid = :userid AND enrolid {$inSql}", $params);

    if (!empty($userenrols)) {
        // Ambil enrolment yang timeend paling akhir (kalau ada lebih dari satu)
        foreach ($userenrols as $ue) {
            if ($userenrol === null || (int)$ue->timeend > (int)$userenrol->timeend) {
                $userenrol = $ue;
            }
        }
        // Cocokkan instance-nya
        if ($userenrol !== null) {
            foreach ($instances as $inst) {
                if ((int)$inst->id === (int)$userenrol->enrolid) {
                    $targetinstance = $inst;
                    break;
                }
            }
        }
    }
}

// Jika belum ada, pilih instance 'manual' kalau tersedia, kalau tidak ambil instance pertama.
if ($targetinstance === null && !empty($instances)) {
    foreach ($instances as $inst) {
        if ($inst->enrol === 'manual') {
            $targetinstance = $inst;
            break;
        }
    }
    if ($targetinstance === null) {
        // fallback: pakai instance pertama
        $targetinstance = reset($instances);
    }
}

if ($targetinstance === null) {
    $log('ERROR: Tidak menemukan enrol instance pada course.');
    echo "SUCCESS";
    exit;
}

// ====== Hitung timeend baru ======
$now = time();
$seconds = max(0, (int)$days) * 86400;
$new_timestart = $now;
$new_timeend = 0; // 0 berarti tanpa batas (jika days=0)

if ($seconds > 0) {
    if ($userenrol && (int)$userenrol->timeend > $now) {
        // Extend dari timeend yang lama (masih aktif)
        $new_timeend = (int)$userenrol->timeend + $seconds;
    } else {
        // Mulai dari sekarang
        $new_timeend = $now + $seconds;
    }
}

// ====== Apply perubahan enrolment ======
$plugin = enrol_get_plugin($targetinstance->enrol);
if (!$plugin) {
    $log('ERROR: enrol plugin ' . $targetinstance->enrol . ' tidak ditemukan.');
    echo "SUCCESS";
    exit;
}

// Cari ROLE student (fallback: archetype student)
$roleid = 0;
if (!empty($targetinstance->roleid)) {
    $roleid = (int)$targetinstance->roleid;
} else {
    $studentroles = get_archetype_roles('student');
    if (!empty($studentroles)) {
        $roleid = (int)reset($studentroles)->id;
    }
}
if ($roleid <= 0) {
    // Fallback kasar: ambil defaultrole dari course atau site
    $roleid = (int)($course->defaultrole ?? 0);
    if ($roleid <= 0) {
        $roleid = 5; // seringkali role id 5 = student, ini fallback terakhir
    }
}

try {
    if ($userenrol) {
        // Update enrolment existing (kalau plugin dukung)
        if (method_exists($plugin, 'update_user_enrol')) {
            $plugin->update_user_enrol($targetinstance, $userid, ENROL_USER_ACTIVE, $new_timestart, $new_timeend);
            $log("UPDATE enrol: instance={$targetinstance->enrol}#{$targetinstance->id} user={$userid} timeend={$new_timeend}");
        } else {
            // Manual update tabel (fallback)
            $userenrol->timestart = $new_timestart;
            $userenrol->timeend   = $new_timeend;
            $userenrol->status    = ENROL_USER_ACTIVE;
            $DB->update_record('user_enrolments', $userenrol);
            $log("UPDATE enrol (DB): instance={$targetinstance->enrol}#{$targetinstance->id} user={$userid} timeend={$new_timeend}");
        }
    } else {
        // Enrol user baru
        if (method_exists($plugin, 'enrol_user')) {
            $plugin->enrol_user($targetinstance, $userid, $roleid, $new_timestart, $new_timeend, ENROL_USER_ACTIVE);
            $log("CREATE enrol: instance={$targetinstance->enrol}#{$targetinstance->id} user={$userid} role={$roleid} timeend={$new_timeend}");
        } else {
            $log('ERROR: Plugin tidak mendukung enrol_user().');
        }
    }
} catch (Throwable $e) {
    $log('ERROR: Gagal apply enrolment: ' . $e->getMessage());
    echo "SUCCESS";
    exit;
}

// (Opsional) Anda bisa menandai transaksi sebagai processed di DB internal di sini.

// ==== Selesai ====
echo "SUCCESS";
exit;
