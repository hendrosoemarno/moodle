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
 * Upgrade script for the Midtrans enrolment plugin.
 *
 * @package    enrol_midtrans
 * @copyright  2025 [topexam.id] - based on code by Eugene Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the upgrade process for the enrol_midtrans plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_enrol_midtrans_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // $dbman handles DDL and DML.

    if ($oldversion < 2026061200) {
        // Add any database changes or data migrations here if needed.
        // For example, if you added new tables or fields, define them here using $dbman.

        // No database changes are required in this basic example, so just upgrade the version.
        upgrade_plugin_savepoint(true, 2026061200, 'enrol', 'midtrans');
    }
    
    if ($oldversion < 2026061202) {
    // No database changes needed for this update, just update version.
    upgrade_plugin_savepoint(true, 2026061202, 'enrol', 'midtrans');
    }
    
    if ($oldversion < 2026061206) {
    // No database changes needed, just update version.
    upgrade_plugin_savepoint(true, 2026061206, 'enrol', 'midtrans');
    }
    
    if ($oldversion < 2026061207) {
    // No database changes needed, just update version.
    upgrade_plugin_savepoint(true, 2026061207, 'enrol', 'midtrans');
    }
    
    if ($oldversion < 2026061209) {
    // No database changes needed, just update version.
    upgrade_plugin_savepoint(true, 2026061209, 'enrol', 'midtrans');
}

    return true;
}