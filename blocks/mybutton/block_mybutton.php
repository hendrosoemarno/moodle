<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Block: mybutton
 * - Tahap awal: combobox paket disembunyikan
 * - Klik pertama tombol: tampilkan combobox, ubah label tombol menjadi "Bayar"
 * - Klik kedua tombol: validasi paket, tampilkan notice, redirect ke createinvoice.php
 */
class block_mybutton extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_mybutton');
    }

    // Tampilkan hanya di halaman course.
    public function applicable_formats(): array {
        return [
            'course-view' => true,
            'site-index'  => false,
            'mod'         => false,
            'my'          => false,
        ];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Format detik menjadi string ringkas: "X hari Y jam Z menit"
     */
    private function format_duration_brief(int $seconds): string {
        if ($seconds <= 0) {
            return '0 menit';
        }
        $minutes = intdiv($seconds, 60);
        $seconds = $seconds % 60;

        $hours   = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        $days    = intdiv($hours, 24);
        $hours   = $hours % 24;

        $parts = [];
        if ($days > 0)   { $parts[] = $days . ' hari'; }
        if ($hours > 0)  { $parts[] = $hours . ' jam'; }
        if ($minutes > 0 || empty($parts)) { $parts[] = $minutes . ' menit'; }

        return implode(' ', $parts);
    }

    /**
     * Ambil opsi paket dari custom field course "billing_options"
     * Format per baris: key|label|price(|days)
     * Contoh baris:
     *   onetime|1x|25000|0
     *   monthly|1 bulan|50000|30
     *   yearly|1 tahun|600000|365
     *
     * @return array<string,array{label:string,price:int,days:int}>
     */
    private function get_billing_options(int $courseid): array {
        $options = [];

        // Siapkan dir log diagnostik
        global $CFG;
        $diagdir = $CFG->dataroot . '/temp/block_mybutton_diag/';
        @mkdir($diagdir, 0777, true);

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

            // 2) Normalisasi:
            //    - ubah </p> dan <br> jadi newline
            //    - strip_tags
            //    - decode entity
            //    - normalisasi newline
            $norm = preg_replace('/<\/p>\s*/i', "\n", $raw);
            $norm = preg_replace('/<br\s*\/?>/i', "\n", $norm);
            $norm = strip_tags($norm);
            $norm = html_entity_decode($norm, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $norm = str_replace(["\r\n", "\r"], "\n", $norm);
            $norm = trim($norm);

            // LOG RAW & NORM
            @file_put_contents(
                $diagdir . 'billing_source_' . date('Ymd_His') . '.log',
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

                $keyRaw = $parts[0];
                $label  = $parts[1];
                $price  = preg_replace('/[^\d]/', '', (string)$parts[2]);
                $days   = isset($parts[3]) ? (int)$parts[3] : 0;

                // Sanitisasi key: decode entity, buang tag, lowercase, sisakan a-z0-9_-
                $key = strtolower($keyRaw);
                $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $key = strip_tags($key);
                $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

                if ($key !== '' && $label !== '' && $price !== '' && is_numeric($price)) {
                    $options[$key] = [
                        'label' => $label,
                        'price' => (int)$price,
                        'days'  => $days > 0 ? $days : 0,
                    ];
                }
            }

            // LOG hasil parse
            @file_put_contents(
                $diagdir . 'billing_parsed_' . date('Ymd_His') . '.log',
                print_r($options, true) . "\n",
                FILE_APPEND
            );

            break; // selesai setelah ketemu field-nya
        }

        return $options;
    }

    public function get_content(): stdClass {
        global $COURSE, $USER, $DB, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        // URL endpoint server-side yang membuat invoice dan redirect ke Duitku
        $targeturl = new moodle_url('/local/payment/createinvoice.php', ['courseid' => $COURSE->id]);

        // ---------------------------------------------------------------------
        // 1) HITUNG SISA ENROLMENT DURATION UNTUK USER SAAT INI
        // ---------------------------------------------------------------------
        $remaininglabel = get_string('remaininglabel', 'block_mybutton');
        $unlimitedtext  = get_string('unlimited', 'block_mybutton');
        $expiredtext    = get_string('expired', 'block_mybutton');
        $notenrolled    = get_string('notenrolled', 'block_mybutton');

        $remaininghtml = '';
        $now = time();

        $instances = enrol_get_instances($COURSE->id, true); // only enabled enrols
        if (!empty($instances) && isloggedin() && !isguestuser()) {
            $enrolids = array_map(static function($inst) { return (int)$inst->id; }, $instances);
            if (!empty($enrolids)) {
                list($inSql, $inParams) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED, 'enrolid');
                $params = array_merge(['userid' => $USER->id], $inParams);

                $userenrols = $DB->get_records_select('user_enrolments', "userid = :userid AND enrolid {$inSql}", $params);

                if ($userenrols) {
                    $hasUnlimited = false;
                    $maxTimeEnd   = null;

                    foreach ($userenrols as $ue) {
                        if ((int)$ue->timeend === 0) {
                            $hasUnlimited = true;
                            break;
                        }
                        if ($maxTimeEnd === null || (int)$ue->timeend > $maxTimeEnd) {
                            $maxTimeEnd = (int)$ue->timeend;
                        }
                    }

                    if ($hasUnlimited) {
                        $remaininghtml = html_writer::div(
                            html_writer::tag('strong', $remaininglabel . ': ') . $unlimitedtext,
                            'alert alert-success alert-block fade show',
                            ['style' => 'margin-bottom:10px;']
                        );
                    } else if ($maxTimeEnd === null) {
                        $remaininghtml = html_writer::div(
                            html_writer::tag('strong', $remaininglabel . ': ') . $notenrolled,
                            'alert alert-warning alert-block fade show',
                            ['style' => 'margin-bottom:10px;']
                        );
                    } else if ($maxTimeEnd <= $now) {
                        $remaininghtml = html_writer::div(
                            html_writer::tag('strong', $remaininglabel . ': ') . $expiredtext,
                            'alert alert-danger alert-block fade show',
                            ['style' => 'margin-bottom:10px;']
                        );
                    } else {
                        $remainSeconds = $maxTimeEnd - $now;
                        $formatted     = $this->format_duration_brief($remainSeconds);

                        $remaininghtml = html_writer::div(
                            html_writer::tag('strong', $remaininglabel . ': ') . s($formatted),
                            'alert alert-info alert-block fade show',
                            ['style' => 'margin-bottom:10px;']
                        );
                    }
                } else {
                    $remaininghtml = html_writer::div(
                        html_writer::tag('strong', $remaininglabel . ': ') . $notenrolled,
                        'alert alert-warning alert-block fade show',
                        ['style' => 'margin-bottom:10px;']
                    );
                }
            }
        } else {
            $remaininghtml = html_writer::div(
                html_writer::tag('strong', $remaininglabel . ': ') . $notenrolled,
                'alert alert-warning alert-block fade show',
                ['style' => 'margin-bottom:10px;']
            );
        }

        // ---------------------------------------------------------------------
        // 2) DROPDOWN PAKET (awalnya DISEMBUNYIKAN)
        // ---------------------------------------------------------------------
        $plans = $this->get_billing_options($COURSE->id);
        $selectid     = 'mybutton_plan_' . $this->instance->id;
        $selectwrapid = 'mybutton_planwrap_' . $this->instance->id;
        $planselecthtml = '';

        if (!empty($plans)) {
            $optionshtml = [];
            $optionshtml[] = html_writer::tag('option', 'Pilih durasi…', [
                'value'    => '',
                'selected' => 'selected',
                'disabled' => 'disabled'
            ]);
            foreach ($plans as $key => $meta) {
                $labelprice = $meta['label'] . ' — Rp ' . number_format($meta['price'], 0, ',', '.');
                $optionshtml[] = html_writer::tag('option', $labelprice, ['value' => s($key)]);
            }

            $select = html_writer::tag('label', 'Pilih Paket Perpanjangan', ['for' => $selectid, 'class' => 'form-label']) .
                      html_writer::tag('select', implode('', $optionshtml), [
                          'id'    => $selectid,
                          'class' => 'form-select mb-2'
                      ]);

            // AWALNYA DISEMBUNYIKAN
            $planselecthtml = html_writer::div($select, '', [
                'id'    => $selectwrapid,
                'style' => 'display:none;margin-bottom:8px;'
            ]);

            // (opsional) log daftar opsi yang dirender
            @file_put_contents(
                $CFG->dataroot . '/temp/block_mybutton_diag/option_values_' . date('Ymd_His') . '.log',
                "Course {$COURSE->id}\nOptions keys: " . implode(',', array_keys($plans)) . "\n",
                FILE_APPEND
            );
        }

        // ---------------------------------------------------------------------
        // 3) TOMBOL + ALERT (NOTIFIKASI) SAAT DIKLIK — MODE 2 FASE
        // ---------------------------------------------------------------------
        $buttonid = 'mybutton_btn_' . $this->instance->id;
        $noticeid = 'mybutton_notice_' . $this->instance->id;

        // label awal tombol (Perpanjangan) dari string bahasa agar konsisten
        $initialbtnlabel = get_string('buttonlabel', 'block_mybutton'); // mis. "Perpanjangan"
        $paylabel        = 'Bayar'; // bisa dibuat get_string('paynow', 'block_mybutton') bila ada

        $buttonhtml = html_writer::tag('button',
            $initialbtnlabel,
            [
                'class'      => 'btn btn-primary',
                'id'         => $buttonid,
                'type'       => 'button',
                'aria-label' => get_string('buttondesc', 'block_mybutton'),
            ]
        );

        $notice = html_writer::div(
            html_writer::tag('strong', get_string('notice_heading', 'block_mybutton')) . ' ' .
            html_writer::span(get_string('notice_text', 'block_mybutton')),
            'alert alert-info alert-block fade show',
            [
                'id'    => $noticeid,
                'style' => 'display:none;margin-top:10px;',
                'role'  => 'status',
            ]
        );

        // JS: klik pertama → munculkan select & ubah tombol jadi "Bayar"
        //     klik kedua    → validasi & redirect
        $js = "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var btn     = document.getElementById('$buttonid');
                var notice  = document.getElementById('$noticeid');
                var sel     = document.getElementById('$selectid');
                var selwrap = document.getElementById('$selectwrapid');
                var baseUrl = " . json_encode($targeturl->out(false)) . ";

                if (!btn) return;

                // fase awal: pilih paket (select hidden)
                btn.dataset.phase = 'choose'; // choose -> pay

                btn.addEventListener('click', function(e) {
                    e.preventDefault();

                    if (btn.dataset.phase === 'choose') {
                        if (selwrap) { selwrap.style.display = 'block'; }
                        try { sel && sel.focus(); } catch (e) {}
                        btn.textContent = " . json_encode($paylabel) . ";
                        btn.dataset.phase = 'pay';
                        return; // belum redirect
                    }

                    if (btn.dataset.phase === 'pay') {
                        if (sel && !sel.value) {
                            alert('Silakan pilih paket terlebih dahulu.');
                            try { sel.focus(); } catch (e) {}
                            return;
                        }

                        var url = baseUrl;
                        if (sel && sel.value) {
                            url += (url.indexOf('?') === -1 ? '?' : '&') + 'plan=' + encodeURIComponent(sel.value);
                        }

                        // cegah double-click
                        btn.disabled = true;

                        if (notice) {
                            notice.style.display = 'block';
                            try { notice.scrollIntoView({behavior:'smooth', block:'nearest'}); } catch (e) {}
                        }

                        setTimeout(function() {
                            window.location.href = url;
                        }, 500);
                    }
                });
            });
            </script>
        ";

        // Susun konten block: sisa durasi → (select hidden) → tombol → notice → js
        $this->content->text =
            $remaininghtml .
            $planselecthtml . // <== hidden di awal
            html_writer::div($buttonhtml, 'mb-2') .
            $notice .
            $js;

        return $this->content;
    }
}
