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
 * If only one game is installed, go straight to its settings form.
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

$games = \mod_vocab\gamebase::get_games();
if (count($games) == 1) {
    redirect(new moodle_url('/mod/vocab/game/edit.php', ['cmid' => $cm->id, 'game' => array_key_first($games)]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choosegame', $plugin));
if (empty($games)) {
    echo $OUTPUT->notification(get_string('nogamesinstalled', $plugin), 'info');
} else {
    $items = [];
    foreach ($games as $name => $class) {
        $link = html_writer::link(
            new moodle_url('/mod/vocab/game/edit.php', ['cmid' => $cm->id, 'game' => $name]),
            $class::get_title(),
            ['class' => 'font-weight-bold']
        );
        $component = $class::get_component();
        if (get_string_manager()->string_exists('pluginname_help', $component)) {
            $link .= html_writer::div(format_text(get_string('pluginname_help', $component), FORMAT_MARKDOWN), 'small');
        }
        $items[] = $link;
    }
    echo html_writer::alist($items, ['class' => 'list-unstyled vocab-choosegame']);
}
echo $OUTPUT->single_button($viewurl, get_string('cancel'), 'get');
echo $OUTPUT->footer();
