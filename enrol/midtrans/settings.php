<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Midtrans enrolment plugin settings and presets.
 *
 * @package    enrol_midtrans
 * @copyright  2025 [topexam.id] - based on code by Eugene Venter and Petr Skoda
 * @author     [topexam.id]
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_midtrans/settings', get_string('pluginname', 'enrol_midtrans'), get_string('pluginname_desc', 'enrol_midtrans')));

    $settings->add(new admin_setting_configtext('enrol_midtrans/serverkey', get_string('midtrans_serverkey', 'enrol_midtrans'), get_string('midtrans_serverkey_desc', 'enrol_midtrans'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('enrol_midtrans/clientkey', get_string('midtrans_clientkey', 'enrol_midtrans'), get_string('midtrans_clientkey_desc', 'enrol_midtrans'), '', PARAM_TEXT));

    // Custom currency options for Midtrans (primarily IDR)
    $midtranscurrencies = array(
        'IDR' => 'Indonesian Rupiah (IDR)', // Default currency for Midtrans
        'USD' => 'US Dollar (USD)',         // Optional, if Midtrans supports it
    );
    $settings->add(new admin_setting_configselect('enrol_midtrans/currency', get_string('currency', 'enrol_midtrans'), get_string('currency_desc', 'enrol_midtrans'), 'IDR', $midtranscurrencies));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_midtrans_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_midtrans/status',
        get_string('status', 'enrol_midtrans'), get_string('status_desc', 'enrol_midtrans'), ENROL_INSTANCE_DISABLED, $options));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_midtrans/roleid',
            get_string('defaultrole', 'enrol_midtrans'),
            get_string('defaultrole_desc', 'enrol_midtrans'),
            $student->id ?? null,
            $options));
    }

    $settings->add(new admin_setting_configduration('enrol_midtrans/enrolperiod',
        get_string('enrolperiod', 'enrol_midtrans'), get_string('enrolperiod_desc', 'enrol_midtrans'), 0));
}