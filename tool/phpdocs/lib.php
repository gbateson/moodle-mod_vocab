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
 * mod/vocab/tool/phpdocs/lib.php
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to which the node for this tool will be added.
 */
function vocabtool_phpdocs_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    global $CFG, $USER;
    // Restrict this tool to use by the main developer in the development environment.
    if (is_siteadmin() && $USER->username == 'gbateson') {
        if (isset($CFG->debug) && $CFG->debug == DEBUG_DEVELOPER) {
            if (parse_url($CFG->wwwroot, PHP_URL_HOST) == 'localhost') {
                $function = 'vocab_extend_subplugin_navigation';
                $function($node, 'tool', 'phpdocs', $settings->get_page()->cm);
            }
        }
    }
}

/**
 * Define the icon for this vocab tool
 */
function vocabtool_phpdocs_get_fontawesome_icon_map() {
    return array('vocabtool_phpdocs:phpdocs' => 'fa-code');
}