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
 * @package    vocabutil_redoupgrade
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Upgrade the "redoupgrade" util of the Vocabulary activity from an older version: e.g. its capabilities.
 *
 * @param int $oldversion the version that is currently installed
 * @return bool true if the upgrade succeeded
 */
function xmldb_vocabutil_redoupgrade_upgrade($oldversion) {
    global $CFG, $DB;
    $result = true;

    $newversion = 2023080104;
    if ($oldversion < $newversion) {
        update_capabilities('vocabutil/redoupgrade');
        upgrade_plugin_savepoint($result, $newversion, 'vocabutil', 'redoupgrade');
    }

    return $result;
}
