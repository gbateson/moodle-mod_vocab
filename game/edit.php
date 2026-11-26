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
 * Add a game to a Vocabulary activity, or edit one of its games, without JavaScript.
 * With JavaScript, the same form (\mod_vocab\gameform) is shown in a popup.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$id = optional_param('id', 0, PARAM_INT); // A game instance, to edit it.
if ($id) {
    $instance = $DB->get_record('vocab_game_instances', ['id' => $id], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('vocab', $instance->vocabid, 0, false, MUST_EXIST);
    $params = ['id' => $id];
    $heading = 'editgame';
} else {
    $cmid = required_param('cmid', PARAM_INT); // The course module, to add a game to it.
    $game = required_param('game', PARAM_PLUGIN); // The game to add, e.g. "quiz".
    $cm = get_coursemodule_from_id('vocab', $cmid, 0, false, MUST_EXIST);
    $params = ['cmid' => $cm->id, 'game' => $game];
    $heading = 'addgame';
}

$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:manage', $context);

$url = new moodle_url('/mod/vocab/game/edit.php', $params);
$viewurl = new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($cm->name) . ': ' . get_string($heading, 'mod_vocab'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \mod_vocab\gameform($url);
$form->set_data_for_dynamic_submission();
if ($form->is_cancelled()) {
    redirect($viewurl);
}
if ($form->get_data()) {
    $form->process_dynamic_submission();
    redirect($viewurl, get_string('gamesaved', 'mod_vocab'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($heading, 'mod_vocab'));
$form->display();
echo $OUTPUT->footer();
