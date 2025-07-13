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
 * mod/vocab/tool/redotask/lib.php
 *
 * @package    vocabtool_redotask
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to which the node for this tool will be added.
 */
function vocabtool_redotask_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    return vocab_extend_navigation_for_developer($settings, $node, 'tool', 'redotask');
}

/**
 * Define the icon for this vocab tool
 */
function vocabtool_redotask_get_fontawesome_icon_map() {
    return ['vocabtool_redotask:redotask' => 'fa-solid fa-clock-rotate-left vocabtool_redotask_icon'];
}
