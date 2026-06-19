<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_studentreport_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        // Nama aktivitas.
        $mform->addElement('text', 'name', get_string('studentreportname', 'studentreport'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Deskripsi/Intro.
        $this->standard_intro_elements();

        // Opsi editor.
        $editoroptions = [
            'maxfiles'  => 0,
            'maxbytes'  => 0,
            'trusttext' => true,
            'context'   => $this->context,
            'subdirs'   => 0
        ];

        // Keterangan Penilaian (legend).
        $mform->addElement('editor', 'legend_editor', get_string('legendlabel', 'studentreport'), null, $editoroptions);
        $mform->addHelpButton('legend_editor', 'legend', 'studentreport');
        $mform->setType('legend_editor', PARAM_RAW);

        // Kompetensi BI.
        $mform->addElement('editor', 'kompetensi_editor', get_string('kompetensilabel', 'studentreport'), null, $editoroptions);
        $mform->addHelpButton('kompetensi_editor', 'kompetensi', 'studentreport');
        $mform->setType('kompetensi_editor', PARAM_RAW);

        // Elemen standar modul.
        $this->standard_coursemodule_elements();

        // Tombol simpan.
        $this->add_action_buttons();
    }

    // Prefill editor saat edit & set default saat add.
    public function data_preprocessing(&$defaultvalues) {
        // Default LEGEND (HTML tabel seperti sebelumnya).
        $defaultlegend = <<<HTML
<div class="box generalbox" style="max-width:820px;margin:16px 0;">
  <h4>Keterangan Penilaian</h4>
  <table class="generaltable">
    <thead>
      <tr><th>Persentase Capaian</th><th>Kategori</th><th>Makna Umum</th></tr>
    </thead>
    <tbody>
      <tr><td>0–49%</td><td><strong>Kurang</strong></td><td>Belum menguasai materi</td></tr>
      <tr><td>50–69%</td><td><strong>Bisa</strong></td><td>Baru mulai bisa, masih butuh bimbingan</td></tr>
      <tr><td>70–89%</td><td><strong>Kompeten</strong></td><td>Sudah cukup menguasai kompetensi</td></tr>
      <tr><td>90–100%</td><td><strong>Excellent</strong></td><td>Sudah menguasai kompetensi</td></tr>
    </tbody>
  </table>
</div>
HTML;

        // Default KOMPETENSI BI (teks yang Anda pakai).
        $defaultkompetensi = <<<HTML
<div class="box generalbox" style="max-width:920px;margin:16px 0;">
  <h4>Kompetensi Singkat Membaca Teks</h4>
  <h5>1. Pemahaman Tekstual</h5>
  <ul>
    <li><strong>Identifikasi objek</strong>: Mengenali objek berdasarkan kosakata yang digunakan dalam teks fiksi maupun nonfiksi.</li>
    <li><strong>Penggunaan kosakata</strong>: Mengidentifikasi pemakaian kosakata umum dan kosakata khusus dalam berbagai bidang.</li>
    <li><strong>Ikhtisar atau bagan</strong>: Menyusun kembali informasi dari teks ke dalam bentuk ikhtisar atau bagan.</li>
    <li><strong>Informasi tersurat</strong>: Menemukan informasi yang secara jelas tertulis di dalam teks.</li>
  </ul>
  <h5>2. Pemahaman Inferensial</h5>
  <ul>
    <li><strong>Menyimpulkan ide pokok</strong>: Menemukan gagasan utama, gagasan pendukung, amanat, tokoh, peristiwa, dan nilai-nilai dalam teks.</li>
    <li><strong>Menyimpulkan perubahan</strong>: Mengidentifikasi perubahan sederhana pada objek, tokoh, atau latar dalam teks fiksi maupun nonfiksi.</li>
    <li><strong>Makna ungkapan</strong>: Menjelaskan arti ungkapan yang terdapat dalam teks.</li>
  </ul>
  <h5>3. Evaluasi dan Apresiasi</h5>
  <ul>
    <li><strong>Relevansi peristiwa</strong>: Menilai keterkaitan peristiwa dalam teks dengan kehidupan sehari-hari berdasarkan pengalaman atau pengetahuan pribadi.</li>
    <li><strong>Kesesuaian antarunsur</strong>: Menilai konsistensi antarunsur atau antarinformasi dalam teks.</li>
    <li><strong>Respons emosional</strong>: Menyimpulkan tanggapan emosional terhadap unsur dalam teks fiksi.</li>
  </ul>
</div>
HTML;

        // LEGEND.
        if (!empty($defaultvalues['legend'])) {
            $defaultvalues['legend_editor']['text']   = $defaultvalues['legend'];
            $defaultvalues['legend_editor']['format'] = $defaultvalues['legendformat'] ?? FORMAT_HTML;
        } else {
            $defaultvalues['legend_editor']['text']   = $defaultlegend;
            $defaultvalues['legend_editor']['format'] = FORMAT_HTML;
        }

        // KOMPETENSI.
        if (!empty($defaultvalues['kompetensi'])) {
            $defaultvalues['kompetensi_editor']['text']   = $defaultvalues['kompetensi'];
            $defaultvalues['kompetensi_editor']['format'] = $defaultvalues['kompetensiformat'] ?? FORMAT_HTML;
        } else {
            $defaultvalues['kompetensi_editor']['text']   = $defaultkompetensi;
            $defaultvalues['kompetensi_editor']['format'] = FORMAT_HTML;
        }
    }
}
