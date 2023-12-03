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

require('../../../../config.php');

$ai = \vocabai_chatgpt\ai::create();

if (empty($ai->vocab) || empty($ai->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($ai->vocab->course, false, $ai->vocab->cm);
require_capability('mod/vocab:manage', $ai->vocab->context);

$PAGE->set_url($ai->index_url());
$PAGE->set_title($ai->get_string('pluginname'));
$PAGE->set_heading($ai->get_string('pluginname'));
$PAGE->activityheader->set_attrs([
    'hidecompletion' => true,
    'description' => $ai->get_string('pluginname'),
]);

$ai->vocab->collapse_navigation();
$ai->vocab->set_pagelayout();

$mform = $ai->get_mform();

if ($mform->is_cancelled()) {
    redirect($ai->vocab->view_url());
}

echo $OUTPUT->header();

if ($mform->is_submitted() && $mform->is_validated()) {
    echo $OUTPUT->box_start();
    echo $mform->saveconfig();
    echo $OUTPUT->box_end();
}

$mform->display();

echo $OUTPUT->footer();
