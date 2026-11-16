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
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabtool_checkfiles
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_checkfiles;

/**
 * tool
 *
 * @package    vocabtool_checkfiles
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'checkfiles';

    /**
     * Check the questions in the chosen question category, and its subcategories,
     * for files that are recorded in Moodle, but are missing from the file store.
     *
     * @param \moodleform $mform
     * @return string HTML of the report.
     */
    public function check_files($mform) {
        global $PAGE;
        $data = $mform->get_data();
        $categoryid = (empty($data->parentcat['id']) ? 0 : (int) $data->parentcat['id']);
        $renderer = $PAGE->get_renderer('mod_vocab');
        $renderer->attach_activity($this->vocab);
        $heading = $this->vocab->get_string('findmissingfiles', $this->get_string('pluginname'));
        return $renderer->heading($heading, 3) .
            $renderer->missing_files_report((int) $this->vocab->course->id, $categoryid);
    }
}
