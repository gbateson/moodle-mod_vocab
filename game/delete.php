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
 * Remove a game from a Vocabulary activity, after confirmation. A game that
 * has been played is hidden, not deleted, so that students' scores are kept.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use mod_vocab\progress;

$id = required_param('id', PARAM_INT); // The game instance.
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$instance = $DB->get_record('vocab_game_instances', ['id' => $id], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('vocab', $instance->vocabid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:manage', $context);

$plugin = 'mod_vocab';
$url = new moodle_url('/mod/vocab/game/delete.php', ['id' => $id]);
$viewurl = new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($cm->name) . ': ' . get_string('deletegame', $plugin));
$PAGE->set_heading(format_string($course->fullname));

if ($confirm && confirm_sesskey()) {
    if (progress::delete_game_instance($id)) {
        $message = get_string('gamedeleted', $plugin);
    } else {
        $message = get_string('gamehiddennotdeleted', $plugin);
    }
    redirect($viewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$vocab = $DB->get_record('vocab', ['id' => $instance->vocabid], '*', MUST_EXIST);
$instance->name = $DB->get_field('vocab_games', 'name', ['id' => $instance->gameid]);
$game = \mod_vocab\gamebase::create($vocab, $cm, $instance);
$title = ($game ? $game->get_label() : $instance->name);
$count = progress::count_game_instance_attempts($id);
if ($count) {
    $message = get_string('confirmhidegame', $plugin, (object) ['title' => $title, 'count' => $count]);
} else {
    $message = get_string('confirmdeletegame', $plugin, $title);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deletegame', $plugin));
$continue = new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(s($message), $continue, $viewurl);
echo $OUTPUT->footer();
