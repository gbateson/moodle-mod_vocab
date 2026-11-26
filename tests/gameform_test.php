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

use core_form\external\dynamic_form;

/**
 * Tests of the form for adding and editing the games in an activity.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\gameform
 */
final class gameform_test extends \advanced_testcase {
    /**
     * Make an activity that uses the "recognize" and "recall" stages, and a teacher and a student.
     *
     * @return array [course module, vocab id, teacher, student]
     */
    protected function make_activity(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1], progress::STAGE_RECALL => [1, 1]]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        return [$cm, (int) $vocab->id, $teacher, $student];
    }

    /**
     * Submit the form in the same way as the popup on the activity page.
     *
     * @param array $data the form's fields.
     * @return array the result of the dynamic_form web service function.
     */
    protected function submit(array $data): array {
        // The form checks the session key of the request, as well as the one in the form's data.
        $_POST['sesskey'] = sesskey();
        $data += ['sesskey' => sesskey(), '_qf__mod_vocab_gameform' => 1];
        return dynamic_form::execute(gameform::class, http_build_query($data, '', '&'));
    }

    /**
     * A teacher adds a game, then edits it.
     */
    public function test_add_and_edit_game(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, $vocabid, $teacher] = $this->make_activity();
        $this->setUser($teacher);

        $result = $this->submit([
            'cmid' => $cm->id, 'game' => 'quiz', 'id' => 0,
            'stage' => progress::STAGE_RECALL, 'title' => '', 'wordcount' => 3,
        ]);
        $this->assertTrue($result['submitted']);
        $id = json_decode($result['data'])->id;
        $record = $DB->get_record('vocab_game_instances', ['id' => $id]);
        $this->assertEquals($vocabid, $record->vocabid);
        $this->assertSame(progress::STAGE_RECALL, $record->stage);
        $this->assertSame('', $record->title);
        $this->assertSame(3, json_decode($record->configdata, true)['wordcount']);

        $result = $this->submit([
            'id' => $id, 'stage' => progress::STAGE_RECOGNIZE, 'title' => 'Food quiz', 'wordcount' => 4,
        ]);
        $this->assertTrue($result['submitted']);
        $record = $DB->get_record('vocab_game_instances', ['id' => $id]);
        $this->assertSame(progress::STAGE_RECOGNIZE, $record->stage);
        $this->assertSame('Food quiz', $record->title);
        $this->assertSame(4, json_decode($record->configdata, true)['wordcount']);
        $this->assertSame(1, $DB->count_records('vocab_game_instances', ['vocabid' => $vocabid]));
    }

    /**
     * A suggested title is saved as empty, even if it is the suggested title for another
     * stage, so that the game's title follows its stage.
     */
    public function test_suggested_title(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, , $teacher] = $this->make_activity();
        $this->setUser($teacher);
        $result = $this->submit([
            'cmid' => $cm->id, 'game' => 'quiz', 'id' => 0,
            'stage' => progress::STAGE_RECALL, 'title' => 'Quiz (Recognize)',
        ]);
        $id = json_decode($result['data'])->id;
        $this->assertSame('', $DB->get_field('vocab_game_instances', 'title', ['id' => $id]));
    }

    /**
     * The form is shown again, not saved, for a stage that the activity does not use.
     */
    public function test_stage_must_be_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        [$cm, $vocabid, $teacher] = $this->make_activity();
        $this->setUser($teacher);
        $result = $this->submit(['cmid' => $cm->id, 'game' => 'quiz', 'id' => 0, 'stage' => progress::STAGE_PRODUCE]);
        $this->assertFalse($result['submitted']);
        $this->assertSame(0, $DB->count_records('vocab_game_instances', ['vocabid' => $vocabid]));
    }

    /**
     * Students cannot add games, and unknown games cannot be added.
     */
    public function test_access(): void {
        $this->resetAfterTest();
        [$cm, , $teacher, $student] = $this->make_activity();
        $this->setUser($student);
        try {
            $this->submit(['cmid' => $cm->id, 'game' => 'quiz', 'id' => 0, 'stage' => progress::STAGE_RECALL]);
            $this->fail('A student added a game');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        $this->submit(['cmid' => $cm->id, 'game' => 'nosuchgame', 'id' => 0, 'stage' => progress::STAGE_RECALL]);
    }
}
