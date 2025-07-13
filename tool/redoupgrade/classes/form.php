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
 * tool/redoupgrade/classes/form.php
 *
 * @package    vocabtool_redoupgrade
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_redoupgrade;

/**
 * form
 *
 * @package    vocabtool_redoupgrade
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /**
     * Defines the form elements for selecting a target version and submitting the upgrade task.
     *
     * Adds a version selector and a submit button labeled "Redo upgrade".
     */
    public function definition() {
        $mform = $this->_form;
        $this->set_form_id($mform);

        $name = 'version';
        $this->add_heading($mform, $name, true);

        $name = 'targetversion';
        $options = $this->get_versions();
        $this->add_field_select($mform, $name, $options, PARAM_INT);

        // Use "Redo task" as the label for the submit button.
        $label = $this->get_string('redoupgrade');
        $this->add_action_buttons(true, $label);
    }

    /**
     * Scans version-related PHP files to extract available Moodle plugin versions.
     *
     * Parses version numbers from /version.php and /db/upgrade.php, and converts them
     * into human-readable dates with optional patch info.
     *
     * @param string $basedir Relative path to the plugin directory (default: /mod/vocab).
     * @param string $dateformat PHP date format string for displaying version dates.
     *
     * @return array Associative array of version numbers mapped to formatted date strings.
     */
    protected function get_versions($basedir='/mod/vocab', $dateformat='jS M Y') {
        global $CFG;
        $versions = [];

        $filepaths = ['/version.php', '/db/upgrade.php'];
        foreach ($filepaths as $filepath) {
            $contents = file_get_contents($CFG->dirroot."/$basedir/$filepath");
            $search = '/\$.*version *= *(\d{4})(\d{2})(\d{2})(\d{2})\b/';
            preg_match_all($search, $contents, $matches);
            $imax = count($matches[0]);
            for ($i = 0; $i < $imax; $i++) {
                $yy = $matches[1][$i];
                $mm = $matches[2][$i];
                $dd = $matches[3][$i];
                $vv = $matches[4][$i];
                $version = "$yy$mm$dd$vv";
                $versions[$version] = date($dateformat, mktime(0, 0, 0, $mm, $dd, $yy)).(intval($vv) == 0 ? '' : " ($vv)");
            }
        }
        krsort($versions);
        return $versions;
    }
}
