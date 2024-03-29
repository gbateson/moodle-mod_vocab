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
 * Redo an upgrade. This script is intended only for development purposes.
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/** Include required files */
require('../../../../../config.php');

$tool = \vocabtool_questionbank\tool::create();

require_login($SITE);
require_capability('moodle/site:config', context_system::instance());

// Set the page url.
$PAGE->set_url($FULLME);

// Set the page title.
$title = $tool->get_string('pluginname');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer($tool->vocab->plugin);
$renderer->attach_activity($tool->vocab);

echo $renderer->redo_upgrade($tool->plugin, $tool->pluginpath);
