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

namespace mod_vocab\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_vocab\progress;

/**
 * Tests for the privacy provider of the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     \mod_vocab\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass the activity. */
    protected $vocab;

    /** @var \context_module */
    protected $context;

    /** @var \stdClass[] two students who have played the quiz. */
    protected $students;

    /**
     * Make an activity in which two students have played the quiz.
     */
    protected function make_data(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->vocab = $generator->create_module('vocab', ['course' => $course->id, 'stage_recognize' => 1]);
        progress::add_game_instance($this->vocab->id, 'quiz', progress::STAGE_RECOGNIZE);
        $cm = get_coursemodule_from_instance('vocab', $this->vocab->id);
        $this->context = \context_module::instance($cm->id);
        $generator->get_plugin_generator('mod_vocab')->add_words($this->vocab->id, ['cat', 'dog']);
        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        foreach ($DB->get_records('vocab_words') as $word) {
            $question = $questions->create_question('truefalse', null, ['category' => $category->id]);
            progress::link_questions((int) $word->id, [$question->id]);
        }

        $progress = progress::create($this->vocab->id);
        $instances = $progress->get_game_instances();
        $game = \mod_vocab\gamebase::create($progress->get_vocab(), $cm, reset($instances));
        $this->students = [];
        foreach ([1, 0] as $answer) {
            $student = $generator->create_and_enrol($course, 'student');
            $this->setUser($student);
            $attempt = $game->start_attempt((int) $student->id);
            $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            $responses = array_fill_keys($quba->get_slots(), ['answer' => $answer]);
            $game->process_attempt($attempt, true, $quba->prepare_simulated_post_data($responses));
            $this->students[] = $student;
        }
        $this->setUser();
    }

    /**
     * Count a student's records.
     *
     * @param \stdClass $student
     * @return int[] game attempts, word progress, question usages.
     */
    protected function count_data(\stdClass $student): array {
        global $DB;
        $uniqueids = $DB->get_fieldset_select('vocab_game_attempts', 'uniqueid', 'userid = ?', [$student->id]);
        return [
            $DB->count_records('vocab_game_attempts', ['userid' => $student->id]),
            $DB->count_records('vocab_word_stages', ['userid' => $student->id]),
            $uniqueids ? $DB->count_records_select(
                'question_usages',
                'id ' . $DB->get_in_or_equal($uniqueids)[0],
                $DB->get_in_or_equal($uniqueids)[1]
            ) : 0,
        ];
    }

    /**
     * The personal data is described.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('mod_vocab'))->get_collection();
        $names = array_map(function ($item) {
            return $item->get_name();
        }, $items);
        $expected = ['vocab_game_attempts', 'vocab_word_attempts', 'vocab_word_stages', 'vocab_word_states', 'core_question'];
        foreach ($expected as $name) {
            $this->assertContains($name, $names);
        }
    }

    /**
     * The activities in which a user has data, and the users who have data in an activity, are found.
     */
    public function test_get_contexts_and_users(): void {
        $this->resetAfterTest();
        $this->make_data();
        $contextids = provider::get_contexts_for_userid((int) $this->students[0]->id)->get_contextids();
        $this->assertEquals([$this->context->id], $contextids);
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $other->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_vocab');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(array_column($this->students, 'id'), $userlist->get_userids());
    }

    /**
     * A student's attempts, scores, progress and answers are exported.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $this->make_data();
        $student = $this->students[0];
        $this->export_context_data_for_user((int) $student->id, $this->context, 'mod_vocab');
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertCount(1, $data->attempts);
        $attempt = $data->attempts[0];
        $this->assertSame('quiz', $attempt->game);
        $this->assertEquals(100, $attempt->score);
        $this->assertEqualsCanonicalizing(['cat', 'dog'], array_column($attempt->words, 'word'));
        $this->assertEqualsCanonicalizing(['cat', 'dog'], array_column($data->wordprogress, 'word'));
        $this->assertNotEmpty($data->wordprogress[0]->timecompleted);
    }

    /**
     * One student's data, some students' data, or all the data in an activity, can be deleted.
     */
    public function test_delete(): void {
        $this->resetAfterTest();
        $this->make_data();
        [$first, $second] = $this->students;
        $this->assertSame([1, 2, 1], $this->count_data($first));

        provider::delete_data_for_user(new approved_contextlist($first, 'mod_vocab', [$this->context->id]));
        $this->assertSame([0, 0, 0], $this->count_data($first));
        $this->assertSame([1, 2, 1], $this->count_data($second));

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_vocab', [$second->id]));
        $this->assertSame([0, 0, 0], $this->count_data($second));

        $this->make_data();
        provider::delete_data_for_all_users_in_context($this->context);
        foreach ($this->students as $student) {
            $this->assertSame([0, 0, 0], $this->count_data($student));
        }
        // The activity's word list and games are kept.
        $this->assertCount(2, progress::create($this->vocab->id)->get_words());
        $this->assertCount(1, progress::create($this->vocab->id)->get_game_instances());
    }
}
