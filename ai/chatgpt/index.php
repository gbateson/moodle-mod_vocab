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
 * mod/vocab/ai/chatgpt/index.php
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require('../../../../config.php');

$ai = \vocabai_chatgpt\ai::create();

if (empty($ai->vocab) || empty($ai->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($ai->vocab->course, false, $ai->vocab->cm);
require_capability('mod/vocab:manage', $ai->vocab->context);

// Setup page url, title, heading and attributes,
// collapse navigation and set page layout.
$ai->setup_page();

if ($ai->action_cancelled()) {
    $ai->action_cancel();
    // Script stops here.
}

if ($ai->action_requested()) {
    if ($ai->action_confirmed()) {
        $ai->action_execute();
    } else {
        $ai->action_confirm('key');
    }
    // Script stops here.
}

// Get form data, if any.
if (($data = data_submitted()) && confirm_sesskey()) {
    $ai->save_config($data);
    $ai->unset_form_elements($data);
    if ($ai->config && $ai->action == 'edit') {
        $completed = $ai->action.'completed';
        redirect($ai->index_url(), $ai->get_string($completed));
        // Script stops here.
    }
}

$mform = $ai->get_mform();

if ($mform->is_cancelled()) {
    redirect($ai->vocab->view_url());
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
