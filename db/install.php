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
 * db/install.php: Event handlers for mod_vocab
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Add the CEFR levels to the dictionary, and the activity's prompts and formats to the
 * AI bridge's library, if the bridge is installed. Otherwise, the bridge adds them when
 * it is installed.
 */
function xmldb_vocab_install() {
    // Add the CEFR levels, A1 to C2, to the dictionary.
    \mod_vocab\dictionary::install_levels();

    if (\mod_vocab\aibridge_migration::is_bridge_ready()) {
        \tool_aibridge\library::sync_component('mod_vocab');
    }
}
