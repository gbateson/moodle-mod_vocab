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
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

/** Include required files */
require_once('../../../config.php');

require_login($SITE);
require_capability('moodle/site:config', context_system::instance());

$vocab = \mod_vocab\activity::create();

// Set the page url.
$PAGE->set_url(new moodle_url('/mod/vocab/sortstrings.php'));

// Set the page title.
$title = $vocab->get_string('pluginname');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer($vocab->plugin);
$renderer->attach_activity($vocab);

echo $renderer->sort_lang_strings($vocab->plugin, true);
