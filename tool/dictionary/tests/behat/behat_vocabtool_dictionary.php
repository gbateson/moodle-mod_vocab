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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for the dictionary tool.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_vocabtool_dictionary extends behat_base {
    /**
     * Get the URL of a page of this tool, for "I am on the "vocab1" "vocabtool_dictionary > Dictionary" page".
     *
     * @param string $type the page: "Dictionary".
     * @param string $identifier the idnumber or name of a Vocabulary activity.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        if (strtolower($type) !== 'dictionary') {
            throw new Exception("Unrecognised vocabtool_dictionary page type \"$type\".");
        }
        $cm = $this->get_cm_by_activity_name('vocab', $identifier);
        return new moodle_url('/mod/vocab/tool/dictionary/index.php', ['id' => $cm->id]);
    }
}
