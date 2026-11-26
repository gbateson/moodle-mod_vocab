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
 * Tests for the functions in lib.php: features, adding, updating and deleting
 * activities (with their stages and games), and grades.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     ::vocab_supports
 * @covers     ::vocab_add_instance
 * @covers     ::vocab_update_instance
 * @covers     ::vocab_delete_instance
 * @covers     ::vocab_get_grades
 */
final class lib_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vocab/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The activity says which features it supports.
     */
    public function test_supports(): void {
        $this->assertTrue(vocab_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(vocab_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(vocab_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertFalse(vocab_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertNull(vocab_supports('not a feature'));
    }

    /**
     * The settings form's stages are saved when an activity is added and updated,
     * and the activity's games, which are managed on its page, are not changed.
     */
    public function test_add_and_update_instance(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $vocab = $this->getDataGenerator()->create_module('vocab', [
            'course' => $course->id,
            'stage_recognize' => 1, 'stage_recognize_pass' => 1, 'stage_recognize_complete' => 3,
            'stage_recall' => 1, 'stage_recall_pass' => 2, 'stage_recall_complete' => 1,
        ]);
        $progress = progress::create($vocab->id);
        $stages = $progress->get_stages();
        $this->assertSame(['recognize', 'recall'], array_keys($stages));
        $this->assertEquals(3, $stages['recognize']->completecount);
        // The number to complete is at least the number to pass.
        $this->assertEquals(2, $stages['recall']->completecount);
        // A new activity has no games.
        $this->assertSame([], $progress->get_game_instances(false));

        // Updating: the recall stage is dropped, but its game is kept as it is.
        $id = progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECALL, ['wordcount' => 4]);
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $data = (object) ['instance' => $vocab->id, 'coursemodule' => $cm->id, 'course' => $course->id,
            'name' => $vocab->name, 'stage_recognize' => 1, 'stage_recognize_pass' => 1, 'stage_recognize_complete' => 1];
        $this->assertTrue(vocab_update_instance($data, null));
        $progress = progress::create($vocab->id);
        $this->assertSame(['recognize'], array_keys($progress->get_stages()));
        $this->assertSame([$id], array_keys($progress->get_game_instances()));
    }

    /**
     * Deleting an activity deletes its stages, games, word list and students' data, but not the words.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id, 'stage_recognize' => 1]);
        $other = $generator->create_module('vocab', ['course' => $course->id, 'stage_recognize' => 1]);
        progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE);
        progress::add_game_instance($other->id, 'quiz', progress::STAGE_RECOGNIZE);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['cat']);
        $generator->get_plugin_generator('mod_vocab')->add_words($other->id, ['cat']);
        $student = $generator->create_and_enrol($course, 'student');
        foreach ([$vocab, $other] as $activity) {
            $progress = progress::create($activity->id);
            $instances = $progress->get_game_instances();
            $instance = reset($instances);
            $attempt = $progress->start_attempt($instance, (int) $student->id, array_keys($progress->get_words()));
            $progress->finish_attempt($attempt, array_fill_keys(array_keys($progress->get_words()), 1));
        }

        course_delete_module($vocab->cmid);
        $this->assertFalse($DB->record_exists('vocab', ['id' => $vocab->id]));
        foreach (['vocab_stages', 'vocab_game_instances', 'vocab_word_instances'] as $table) {
            $this->assertFalse($DB->record_exists($table, ['vocabid' => $vocab->id]), $table);
        }
        // The other activity's data, and the word itself, are kept.
        $this->assertSame([1, 1, 0, 0], progress::create($other->id)->get_counts((int) $student->id));
        $this->assertSame(1, $DB->count_records('vocab_game_attempts'));
        $this->assertTrue($DB->record_exists('vocab_words', ['word' => 'cat']));
        $this->assertSame(1, $DB->count_records('vocab_word_stages'));
        $this->assertSame(1, $DB->count_records('vocab_word_attempts'));
    }

    /**
     * Grades are the percentage of words mastered, scaled to the maximum grade.
     */
    public function test_grades(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id, 'grademax' => 50,
            'stage_recognize' => 1]);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['cat', 'dog']);
        $student = $generator->create_and_enrol($course, 'student');
        $progress = progress::create($vocab->id);
        $words = array_keys($progress->get_words());
        $attempt = $progress->start_attempt((object) ['id' => 0, 'stage' => 'recognize'], (int) $student->id, $words);
        $progress->finish_attempt($attempt, [$words[0] => 1, $words[1] => 0]);

        $grades = vocab_get_grades($progress->get_vocab(), (int) $student->id);
        $this->assertEquals(25, $grades[$student->id]->rawgrade);
        $gradebook = grade_get_grades($course->id, 'mod', 'vocab', $vocab->id, $student->id);
        $this->assertEquals(25, $gradebook->items[0]->grades[$student->id]->grade);
    }
}
