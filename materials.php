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
 * Make a game's missing study materials, in the background.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // The course module id.
$instanceid = required_param('instanceid', PARAM_INT);

$cm = get_coursemodule_from_id('vocab', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$vocab = $DB->get_record('vocab', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:manage', $context);
require_sesskey();

$PAGE->set_url(new moodle_url('/mod/vocab/materials.php', ['id' => $cm->id, 'instanceid' => $instanceid]));
$viewurl = new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
$instance = $DB->get_record('vocab_game_instances', ['id' => $instanceid, 'vocabid' => $vocab->id], '*', MUST_EXIST);
$game = \mod_vocab\gamebase::create($vocab, $cm, $instance);
if (!$game || !$game->can_make_materials()) {
    redirect($viewurl, get_string('materialscannotmake', 'mod_vocab'), null, \core\output\notification::NOTIFY_WARNING);
}

// The materials are made with AI, as this teacher, so they must have accepted the AI policy.
if (!\tool_aibridge\policy::has_accepted((int) $USER->id)) {
    redirect(\tool_aibridge\policy::get_url($context, $viewurl));
}

$summary = $game->get_materials_summary();
$count = $game->make_materials($summary['missing'], (int) $USER->id);
if ($count) {
    $message = ($count == 1) ? get_string('materialsqueuedone', 'mod_vocab') : get_string('materialsqueued', 'mod_vocab', $count);
    redirect($viewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}
redirect($viewurl, get_string('materialsnotqueued', 'mod_vocab'), null, \core\output\notification::NOTIFY_WARNING);
