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
 * mod/vocab/tool/dictionary/index.php
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require('../../../../config.php');

$tool = \vocabtool_dictionary\tool::create();

if (empty($tool->vocab) || empty($tool->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($tool->vocab->course, false, $tool->vocab->cm);
require_capability('mod/vocab:manage', $tool->vocab->context);

$PAGE->set_url($tool->index_url());
$PAGE->set_title($tool->get_string('pluginname'));
$PAGE->set_heading($tool->get_string('pluginname'));
$PAGE->activityheader->set_attrs([
    'hidecompletion' => true,
    'description' => $tool->get_string('pluginname'),
]);

$tool->vocab->collapse_navigation();
$tool->vocab->set_pagelayout();

$mform = $tool->get_mform();

if ($mform->is_cancelled()) {
    redirect($tool->vocab->view_url());
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
