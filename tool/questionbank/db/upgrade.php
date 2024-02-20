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
 * db/upgrade.php: Upgrade code for mod_vocab
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * xmldb_vocabtool_questionbank_upgrade
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $oldversion
 * @return xxx
 *
 * TODO: Finish documenting this function
 */
function xmldb_vocabtool_questionbank_upgrade($oldversion) {
    global $CFG, $DB;
    $result = true;

    // Set the subplugin name and type.
    $type = 'vocabtool';
    $name = 'questionbank';

    // Set the values required to check the DB structure.
    $tableprefix = 'vocabtool_questionbank';
    $pluginname = 'vocabtool_questionbank';
    $plugindir = 'mod/vocab/tool/questionbank';

    // Get the upgrade script for the main plugin.
    require_once($CFG->dirroot.'/mod/vocab/db/upgrade.php');

    // Get the DB manager.
    $dbman = $DB->get_manager();

    $newversion = 2023080104;
    if ($oldversion < $newversion) {
        update_capabilities('vocabtool/questionbank');
        upgrade_plugin_savepoint($result, $newversion, $type, $name);
    }

    $newversion = 2024022012;
    if ($oldversion < $newversion) {
        xmldb_vocab_check_structure($dbman, null, $tableprefix, $pluginname, $plugindir);
        upgrade_plugin_savepoint($result, $newversion, $type, $name);
    }

    return $result;
}
