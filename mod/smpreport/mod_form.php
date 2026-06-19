<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_smpreport_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        // Nama aktivitas.
        $mform->addElement('text', 'name', get_string('smpreportname', 'smpreport'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Deskripsi/intro (aktif jika FEATURE_MOD_INTRO = true di lib.php).
        $this->standard_intro_elements();

        // Elemen course module standar (visibility, availability, completion, dll).
        $this->standard_coursemodule_elements();

        // Tombol simpan & batal.
        $this->add_action_buttons();
    }
}
