<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_payment', get_string('pluginname', 'local_payment'));

    $settings->add(new admin_setting_configtext(
        'local_payment/merchantcode',
        'Merchant Code',
        'Kode merchant dari Duitku.',
        '', PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_payment/merchantkey',
        'Merchant Key',
        'API key dari Duitku.',
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_payment/sandbox',
        'Sandbox Mode',
        'Gunakan sandbox untuk pengujian.',
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
