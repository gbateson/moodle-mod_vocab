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
 * Choose a game to add to a Vocabulary activity, then go to its settings form.
 * With JavaScript, the same choice is shown in a popup on the activity's page.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('vocab', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:manage', $context);

$plugin = 'mod_vocab';
$url = new moodle_url('/mod/vocab/game/add.php', ['cmid' => $cm->id]);
$viewurl = new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($cm->name) . ': ' . get_string('addgame', $plugin));
$PAGE->set_heading(format_string($course->fullname));

$renderer = $PAGE->get_renderer('mod_vocab');
$games = $renderer->game_choices($cm->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choosegame', $plugin));
if (empty($games)) {
    echo $OUTPUT->notification(get_string('nogamesinstalled', $plugin), 'info');
} else {
    echo $OUTPUT->render_from_template('mod_vocab/game_chooser', ['games' => $games]);
}
echo $OUTPUT->single_button($viewurl, get_string('cancel'), 'get');
echo $OUTPUT->footer();
