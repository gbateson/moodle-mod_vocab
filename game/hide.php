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
 * Hide a game in a Vocabulary activity from students, or show it again.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT); // The game instance.
$enabled = required_param('enabled', PARAM_BOOL);

$instance = $DB->get_record('vocab_game_instances', ['id' => $id], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('vocab', $instance->vocabid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:manage', $context);
require_sesskey();

\mod_vocab\progress::set_game_instance_enabled($id, $enabled);
$message = get_string(($enabled ? 'gameshown' : 'gamehidden'), 'mod_vocab');
redirect(
    new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
