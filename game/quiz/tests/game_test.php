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

namespace vocabgame_quiz;

use mod_vocab\progress;

/**
 * Tests for the quiz game.
 *
 * @package    vocabgame_quiz
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\vocabgame_quiz\game::class)]
final class game_test extends \advanced_testcase {
    /**
     * A student answers questions about their words, and each word gets the score of its question.
     */
    public function test_play(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['apple', 'banana', 'cherry']);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        $student = $generator->create_and_enrol($course, 'student');

        // True/false questions about apple and banana; none about cherry.
        $questions = $generator->get_plugin_generator('core_question');
        $coursecontext = \context_course::instance($course->id);
        $category = $questions->create_question_category(['contextid' => $coursecontext->id]);
        $words = $DB->get_records_menu('vocab_words', null, '', 'word, id');
        foreach (['apple', 'banana'] as $word) {
            $question = $questions->create_question('truefalse', null, ['category' => $category->id, 'name' => $word]);
            progress::link_questions((int) $words[$word], [$question->id]);
        }

        progress::save_game_instances($vocab->id, [['quiz', progress::STAGE_RECOGNIZE, ['wordcount' => 5]]]);
        $progress = progress::create($vocab->id);
        $instances = $progress->get_game_instances();
        $instance = reset($instances);
        $game = \mod_vocab\gamebase::create($progress->get_vocab(), $cm, $instance);
        $this->assertInstanceOf(game::class, $game);
        $this->assertSame('Quiz (Recognize)', $game->get_label());

        // Only words with questions are ready.
        $wordinstances = $progress->get_words();
        $ids = array_flip(array_map(function ($word) {
            return $word->word;
        }, $wordinstances));
        $this->assertEqualsCanonicalizing([$ids['apple'], $ids['banana']], $game->get_ready_words(array_keys($wordinstances)));
        $this->assertSame([true, ''], $progress->get_game_state($game, (int) $student->id));

        // The attempt has a question about each word that has one.
        $this->setUser($student);
        $attempt = $game->start_attempt((int) $student->id);
        $slots = game::get_slots($attempt);
        $this->assertEqualsCanonicalizing([$ids['apple'], $ids['banana']], array_values($slots));

        // The student answers apple's question correctly, and banana's wrongly.
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $responses = [];
        foreach ($slots as $slot => $wordinstanceid) {
            $responses[$slot] = ['answer' => ($wordinstanceid == $ids['apple']) ? 1 : 0];
        }
        $postdata = $quba->prepare_simulated_post_data($responses);
        $scores = $game->process_attempt($attempt, true, $postdata);
        $this->assertEquals([$ids['apple'] => 1.0, $ids['banana'] => 0.0], $scores);

        // The scores are recorded: apple is mastered, banana is in progress, cherry is not started.
        $this->assertSame([3, 1, 1, 1], $progress->get_counts((int) $student->id));
        $this->assertNull(progress::get_unfinished_attempt($instance->id, (int) $student->id));

        // Only banana is left, and it has a question, so the game can be played again.
        $this->assertSame([true, ''], $progress->get_game_state($game, (int) $student->id));
    }

    /**
     * A category limits the questions that are used.
     */
    public function test_category(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['apple']);
        progress::save_stages($vocab->id, [progress::STAGE_RECALL => [1, 1]]);
        $questions = $generator->get_plugin_generator('core_question');
        $coursecontext = \context_course::instance($course->id);
        $one = $questions->create_question_category(['contextid' => $coursecontext->id]);
        $two = $questions->create_question_category(['contextid' => $coursecontext->id]);
        $question = $questions->create_question('truefalse', null, ['category' => $one->id]);
        progress::link_questions((int) $DB->get_field('vocab_words', 'id', ['word' => 'apple']), [$question->id]);
        $wordinstanceid = (int) $DB->get_field('vocab_word_instances', 'id', ['vocabid' => $vocab->id]);

        foreach ([0 => 1, $one->id => 1, $two->id => 0] as $categoryid => $expected) {
            $instance = (object) ['id' => 1, 'stage' => 'recall', 'name' => 'quiz',
                'configdata' => json_encode(['categoryid' => $categoryid])];
            $game = \mod_vocab\gamebase::create($vocab, $cm, $instance);
            $this->assertCount($expected, $game->get_ready_words([$wordinstanceid]), "category $categoryid");
        }
        $menu = game::get_category_menu(\context_module::instance($cm->id));
        $this->assertArrayHasKey($one->id, $menu);
        $this->assertArrayHasKey(0, $menu);
    }

    /**
     * Questions for words that have none are made in the background, by the question bank tool.
     */
    public function test_make_materials(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['apple', 'banana']);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questions->create_question('truefalse', null, ['category' => $category->id]);
        progress::link_questions((int) $DB->get_field('vocab_words', 'id', ['word' => 'apple']), [$question->id]);

        $config = ['materialqtype' => 'truefalse', 'materiallevel' => 'B1', 'materialcount' => 3];
        progress::save_game_instances($vocab->id, [['quiz', progress::STAGE_RECOGNIZE, $config]]);
        $progress = progress::create($vocab->id);
        $instances = $progress->get_game_instances();
        $game = \mod_vocab\gamebase::create($progress->get_vocab(), $cm, reset($instances));
        $this->assertTrue($game->can_make_materials());

        $banana = (int) $DB->get_field_sql("SELECT vwi.id FROM {vocab_word_instances} vwi
            JOIN {vocab_words} vw ON vw.id = vwi.wordid WHERE vw.word = 'banana'");
        $summary = $game->get_materials_summary();
        $this->assertSame(['total' => 2, 'ready' => 1, 'pending' => 0, 'missing' => [$banana]], $summary);

        $this->setUser($teacher);
        $this->assertSame(1, $game->make_materials($summary['missing'], (int) $teacher->id));
        $log = $DB->get_record('vocabtool_questionbank_log', ['vocabid' => $vocab->id], '*', MUST_EXIST);
        $this->assertSame('truefalse', $log->qtype);
        $this->assertSame('B1', $log->qlevel);
        $this->assertEquals(3, $log->qcount);
        $this->assertEquals(\vocabtool_questionbank\tool::TASKSTATUS_QUEUED, $log->status);
        $this->assertEquals(game::get_default_promptid(), $log->promptid);
        $this->assertTrue($DB->record_exists('task_adhoc', ['id' => $log->taskid]));

        // Now banana's questions are being made, so they are not made again.
        $this->assertSame(['total' => 2, 'ready' => 1, 'pending' => 1, 'missing' => []], $game->get_materials_summary());
    }
}
