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
 * mod/vocab/tool/import/index.php
 *
 * @package    vocabtool_import
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

// Disable output buffering, because we want to use the progress bar.
define('NO_OUTPUT_BUFFERING', true);

require('../../../../config.php');

$tool = \vocabtool_import\tool::create();
if (empty($tool->vocab) || empty($tool->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($tool->vocab->course, false, $tool->vocab->cm);
require_capability('mod/vocab:manage', $tool->vocab->context);

$PAGE->set_url($tool->index_url());
$PAGE->set_title($tool->get_string('pluginname'));
$PAGE->set_heading($tool->get_string('pluginname'));
$PAGE->activityheader->set_attrs(array(
    'hidecompletion' => true,
    'description' => $tool->get_string('pluginname')
));

$tool->vocab->collapse_navigation();
$tool->vocab->set_pagelayout();

$mform = $tool->get_mform();

// Get the form state, which can be one of
// "upload", "preview", "review", "import" or "cancelled".
$formstate = $mform->get_state();
if ($formstate == 'cancelled') {
    redirect($tool->vocab->view_url());
}

echo $OUTPUT->header();

if ($mform->is_submitted() && $mform->is_validated()) {
    if ($formstate=='preview' || $formstate=='review' || $formstate=='import') {
        echo $OUTPUT->box_start();
        echo $mform->render_data_table();
        echo $OUTPUT->box_end();
    }
}

$mform->display();

echo $OUTPUT->footer();
