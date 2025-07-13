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
 * @package    vocabtool_redoupgrade
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_redoupgrade;

/**
 * tool
 *
 * @package    vocabtool_redoupgrade
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'redoupgrade';

    public function redo_upgrade($mform, $dateformat='jS M Y') {
        global $CFG, $DB;

        // Initialize HTML output string.
        $output = '';

        // Fetch the incoming form data.
        $data = $mform->get_data();

        if (empty($data->targetversion)) {
            return false;
        }

        // Extract taskid.
        $version = intval($data->targetversion);

        // Format the plugin version.
        if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})/', "$version", $match)) {
            $yy = $match[1];
            $mm = $match[2];
            $dd = $match[3];
            $vv = intval($match[4]);
            $text = date($dateformat, mktime(0, 0, 0, $mm, $dd, $yy)).($vv == 0 ? '' : " ($vv)");
        } else {
            $text = ''; // Shouldn't happen !!
        }

        // Reset the plugin version.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('config_plugins')) {
            // This table is available in Moodle >= 2.6.
            $params = ['plugin' => $this->plugin, 'name' => 'version'];
            $DB->set_field('config_plugins', 'value', $version - 1, $params);
            // Force Moodle to refetch versions.
            if (isset($CFG->allversionshash)) {
                unset_config('allversionshash');
            }
        }

        // Inform user that module version has been reset.
        $str = $this->get_string('redoversiondate', (object)[
            'plugin' => $this->vocab->get_string('modulename'),
            'version' => $version,
            'datetext' => $text,
        ]);
        $output .= \html_writer::tag('p', $str, ['class' => 'alert alert-success']);

        // Add a link to the upgrade page.
        $href = new \moodle_url('/admin/index.php', ['confirmplugincheck' => 1, 'cache' => 0]);
        $str = \html_writer::tag('a', $this->get_string('clicktocontinue'), ['href' => $href]);
        $output .= \html_writer::tag('p', $str);

        return $output;
    }
}
