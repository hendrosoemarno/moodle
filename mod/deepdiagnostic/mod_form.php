<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_deepdiagnostic_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('deepdiagnosticname', 'deepdiagnostic'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $editoroptions = [
            'maxfiles'  => 0,
            'maxbytes'  => 0,
            'trusttext' => true,
            'context'   => $this->context,
            'subdirs'   => 0
        ];

        $mform->addElement('editor', 'template_header_editor', get_string('template_header', 'deepdiagnostic'), null, $editoroptions);
        $mform->addHelpButton('template_header_editor', 'template_header', 'deepdiagnostic');
        $mform->setType('template_header_editor', PARAM_RAW);

        $mform->addElement('editor', 'template_footer_editor', get_string('template_footer', 'deepdiagnostic'), null, $editoroptions);
        $mform->addHelpButton('template_footer_editor', 'template_footer', 'deepdiagnostic');
        $mform->setType('template_footer_editor', PARAM_RAW);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$defaultvalues) {
        $defaultheader = <<<HTML
<p>Bismillaah…</p>

<p>Bunda, berikut kami sampaikan <em>hasil analisis Placement Test Matematika dari Ananda {studentname},</em> dengan kesimpulan sebagai berikut:</p>
HTML;

        $defaultfooter = <<<HTML
<p><em>Dengan demikian, Ananda ditempatkan pada Level {levelnum}, yaitu {leveltopic}.</em></p>

<p>Semoga hasil analisis ini bermanfaat dan menjadi awal yang penuh keberkahan dalam proses belajar Ananda. Aamiin…</p>

<p>🔔 <em>Informasi tambahan:</em></p>

<p>✅ Daily task Matematika untuk Ananda sudah tersedia di dalam Aplikasi Pendamping Belajar, menggunakan tautan yang sama seperti sebelumnya, ya Bunda.</p>

<p>✅ Apabila Bunda kurang berkenan Ananda mengakses materi yang mengandung unsur musik, mohon agar tidak memperkenankan Ananda membuka materi di aplikasi terlebih dahulu, dan cukup menunggu materi yang akan disampaikan oleh tentor saat sesi kelas berlangsung. ^_^</p>
HTML;

        if (!empty($defaultvalues['template_header'])) {
            $defaultvalues['template_header_editor']['text'] = $defaultvalues['template_header'];
            $defaultvalues['template_header_editor']['format'] = $defaultvalues['template_headerformat'] ?? FORMAT_HTML;
        } else {
            $defaultvalues['template_header_editor']['text'] = $defaultheader;
            $defaultvalues['template_header_editor']['format'] = FORMAT_HTML;
        }

        if (!empty($defaultvalues['template_footer'])) {
            $defaultvalues['template_footer_editor']['text'] = $defaultvalues['template_footer'];
            $defaultvalues['template_footer_editor']['format'] = $defaultvalues['template_footerformat'] ?? FORMAT_HTML;
        } else {
            $defaultvalues['template_footer_editor']['text'] = $defaultfooter;
            $defaultvalues['template_footer_editor']['format'] = FORMAT_HTML;
        }
    }
}
