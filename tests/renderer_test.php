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

namespace mod_vocab;

/**
 * Tests of the game tiles on the activity's page.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab_renderer
 */
final class renderer_test extends \advanced_testcase {
    /**
     * Get the game tiles of an activity, as a user sees them.
     *
     * @param \stdClass $course
     * @param \stdClass $vocab
     * @param \stdClass $user
     * @return string HTML
     */
    protected function view_games(\stdClass $course, \stdClass $vocab, \stdClass $user): string {
        global $PAGE;
        $this->setUser($user);
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/mod/vocab/view.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm, $course);
        $renderer = $PAGE->get_renderer('mod_vocab');
        $renderer->attach_activity(activity::create($course, $cm, $vocab, \context_module::instance($cm->id)));
        return $renderer->view_games();
    }

    /**
     * Teachers see all games, with their controls; students see only the games they can play.
     */
    public function test_view_games(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        $shown = progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE, [], 'Food quiz');
        $hidden = progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE, [], 'Old quiz');
        progress::set_game_instance_enabled($hidden, false);
        $offstage = progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_PRODUCE, [], 'Chat');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        $html = $this->view_games($course, $vocab, $teacher);
        foreach ([$shown, $hidden, $offstage] as $id) {
            $this->assertStringContainsString('data-instanceid="' . $id . '"', $html);
            $this->assertStringContainsString('/mod/vocab/game/delete.php?id=' . $id, $html);
        }
        $this->assertStringContainsString(get_string('hiddenfromstudents'), $html);
        $this->assertStringContainsString(get_string('stagenotenabled', 'mod_vocab'), $html);
        $this->assertStringContainsString('/mod/vocab/game/add.php?cmid=', $html);
        $this->assertStringContainsString('enabled=1', $html); // The "Show game" item of the hidden game.

        // The quiz game has a picture, on teachers' and students' tiles.
        $this->assertStringContainsString('vocab-game-has-image', $html);
        $this->assertStringContainsString('/vocabgame_quiz/', $html);

        $html = $this->view_games($course, $vocab, $student);
        $this->assertStringContainsString('Food quiz', $html);
        $this->assertStringContainsString('vocab-game-has-image', $html);
        $this->assertStringNotContainsString('Old quiz', $html);
        $this->assertStringNotContainsString('data-instanceid', $html);
        $this->assertStringNotContainsString('/mod/vocab/game/add.php', $html);
    }

    /**
     * A teacher sees a message when the activity has no games yet.
     */
    public function test_no_games_yet(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $html = $this->view_games($course, $vocab, $teacher);
        $this->assertStringContainsString(get_string('nogamesyet', 'mod_vocab'), $html);
    }
}
