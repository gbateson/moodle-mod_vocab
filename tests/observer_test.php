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
 * Tests for what games do when the questions and question categories they use are deleted.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\observer
 * @covers     \mod_vocab\notification
 * @covers     \vocabgame_quiz\game
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass the course. */
    protected $course;

    /** @var \stdClass a teacher, who can manage the activities. */
    protected $teacher;

    /** @var \core_question_generator */
    protected $questions;

    /** @var \context where the course's questions are: the course (Moodle 4.x) or its question bank (Moodle 5.x). */
    protected $questioncontext;

    /**
     * Make a course with a teacher and a student.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $generator->create_and_enrol($this->course, 'student');
        $this->questions = $generator->get_plugin_generator('core_question');
        // This also makes the course's default category, which cannot be deleted.
        $this->questioncontext = $generator->get_plugin_generator('mod_vocab')->get_question_context($this->course);
    }

    /**
     * Make an activity with some words and a quiz.
     *
     * @param string[] $words
     * @param int $categoryid the quiz's question category, or 0 for any category.
     * @param \stdClass|null $course the course, or null for this test's course.
     * @return int the id of the quiz's game instance.
     */
    protected function create_quiz(array $words, int $categoryid, ?\stdClass $course = null): int {
        $generator = $this->getDataGenerator();
        $vocab = $generator->create_module('vocab', ['course' => ($course ?? $this->course)->id]);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, $words);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        return progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE, ['categoryid' => $categoryid]);
    }

    /**
     * Make a question about a word.
     *
     * @param string $word
     * @param int $categoryid
     * @return int the question id.
     */
    protected function create_question(string $word, int $categoryid): int {
        global $DB;
        $question = $this->questions->create_question('truefalse', null, ['category' => $categoryid]);
        $wordid = (int) $DB->get_field('vocab_words', 'id', ['word' => $word], MUST_EXIST);
        progress::link_questions($wordid, [$question->id]);
        return (int) $question->id;
    }

    /**
     * Get the category of a game instance.
     *
     * @param int $instanceid
     * @return int
     */
    protected function get_categoryid(int $instanceid): int {
        global $DB;
        $config = json_decode($DB->get_field('vocab_game_instances', 'configdata', ['id' => $instanceid]), true);
        return (int) $config['categoryid'];
    }

    /**
     * When a quiz's category is deleted, the quiz uses its parent, or any category if the
     * parent is the top category. Only the activity's teachers are told.
     */
    public function test_question_category_deleted(): void {
        $parent = $this->questions->create_question_category(['contextid' => $this->questioncontext->id, 'name' => 'Food']);
        $category = $this->questions->create_question_category(['parent' => $parent->id, 'name' => 'Fruit']);
        $subcategory = $this->questions->create_question_category(['parent' => $category->id, 'name' => 'Apples']);
        $quiz = $this->create_quiz(['apple'], $category->id);
        $subquiz = $this->create_quiz(['apple'], $subcategory->id);
        $anyquiz = $this->create_quiz(['apple'], 0);

        $this->setAdminUser();
        $manager = new \core_question\category_manager();
        $sink = $this->redirectMessages();
        $manager->delete_category($category->id);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame((int) $parent->id, $this->get_categoryid($quiz));
        $this->assertSame((int) $subcategory->id, $this->get_categoryid($subquiz));
        $this->assertSame(0, $this->get_categoryid($anyquiz));
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($this->teacher->id, $message->useridto);
        $this->assertSame('mod_vocab', $message->component);
        $this->assertSame('gameproblem', $message->eventtype);
        $this->assertStringContainsString('"Quiz (Recognize)" needs attention', $message->subject);
        $this->assertStringContainsString('"Fruit"', $message->fullmessage);
        $this->assertStringContainsString('"Food"', $message->fullmessage);

        // The parent's parent is the top category, so the quiz now uses any category.
        $sink = $this->redirectMessages();
        $manager->delete_category($parent->id);
        $this->assertSame(0, $this->get_categoryid($quiz));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Activities that are being deleted are left alone.
     */
    public function test_question_category_deleted_activity_being_deleted(): void {
        global $DB;
        $category = $this->questions->create_question_category(['contextid' => $this->questioncontext->id]);
        $quiz = $this->create_quiz(['apple'], $category->id);
        $vocabid = $DB->get_field('vocab_game_instances', 'vocabid', ['id' => $quiz]);
        $cm = get_coursemodule_from_instance('vocab', $vocabid);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm->id]);

        $this->setAdminUser();
        $sink = $this->redirectMessages();
        (new \core_question\category_manager())->delete_category($category->id);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
        $this->assertSame((int) $category->id, $this->get_categoryid($quiz));
    }

    /**
     * When questions are deleted, their links to words are removed, and teachers are
     * told when a quiz has no questions left. Quizzes in other courses are not affected.
     */
    public function test_question_deleted(): void {
        global $DB;
        $category = $this->questions->create_question_category(['contextid' => $this->questioncontext->id]);
        $this->create_quiz(['apple', 'banana'], $category->id);
        $apple = $this->create_question('apple', $category->id);
        $banana = $this->create_question('banana', $category->id);

        // The same word in another course, with its own question.
        $generator = $this->getDataGenerator();
        $othercourse = $generator->create_course();
        $otherteacher = $generator->create_and_enrol($othercourse, 'editingteacher');
        $othercategory = $this->questions->create_question_category([
            'contextid' => $generator->get_plugin_generator('mod_vocab')->get_question_context($othercourse)->id,
        ]);
        $this->create_quiz(['apple'], 0, $othercourse);
        $this->create_question('apple', $othercategory->id);

        $sink = $this->redirectMessages();
        question_delete_question($apple);
        $this->assertFalse($DB->record_exists('vocab_word_questions', ['questionid' => $apple]));
        $this->assertCount(0, $sink->get_messages(), 'The quiz still has a question about banana.');

        question_delete_question($banana);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($this->teacher->id, $message->useridto);
        $this->assertNotEquals($otherteacher->id, $message->useridto);
        $this->assertStringContainsString('no questions left', $message->fullmessage);
    }

    /**
     * When a question bank (Moodle 5.x) is deleted, its categories go without
     * question_category_deleted events. Quizzes whose category is missing then use any category.
     */
    public function test_question_bank_deleted(): void {
        global $DB;
        $category = $this->questions->create_question_category(['contextid' => $this->questioncontext->id]);
        $kept = $this->questions->create_question_category(['contextid' => $this->questioncontext->id]);
        $quiz = $this->create_quiz(['apple'], $category->id);
        $keptquiz = $this->create_quiz(['apple'], $kept->id);
        $DB->delete_records('question_categories', ['id' => $category->id]);

        // An event like the one that is triggered when a question bank activity is deleted.
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $event = \core\event\course_module_deleted::create([
            'objectid' => $page->cmid,
            'courseid' => $this->course->id,
            'context' => \context_module::instance($page->cmid),
            'other' => ['modulename' => 'qbank', 'instanceid' => $page->id, 'name' => 'Question bank'],
        ]);
        $sink = $this->redirectMessages();
        $event->trigger();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(0, $this->get_categoryid($quiz));
        $this->assertSame((int) $kept->id, $this->get_categoryid($keptquiz));
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('A question bank has been deleted', reset($messages)->fullmessage);
    }

    /**
     * A question that students have answered in a quiz is in use, so Moodle hides it
     * instead of deleting it: its links to words are kept, and the quiz stops using it.
     */
    public function test_question_in_use(): void {
        global $DB;
        $category = $this->questions->create_question_category(['contextid' => $this->questioncontext->id]);
        $instanceid = $this->create_quiz(['apple'], $category->id);
        $questionid = $this->create_question('apple', $category->id);
        $instance = $DB->get_record('vocab_game_instances', ['id' => $instanceid]);
        $progress = progress::create((int) $instance->vocabid);
        $cm = get_coursemodule_from_instance('vocab', $instance->vocabid);
        $instance->name = 'quiz';
        $game = gamebase::create($progress->get_vocab(), $cm, $instance);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertNotNull($game->start_attempt((int) $student->id));
        $this->assertTrue(questions_in_use([$questionid]));

        $sink = $this->redirectMessages();
        question_delete_question($questionid);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
        $this->assertTrue($DB->record_exists('question', ['id' => $questionid]));
        $this->assertTrue($DB->record_exists('vocab_word_questions', ['questionid' => $questionid]));
        $words = array_keys($progress->get_words());
        $this->assertSame([], $game->get_ready_words($words));
    }
}
