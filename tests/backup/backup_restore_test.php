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

namespace mod_vocab\backup;

use mod_vocab\gamebase;
use mod_vocab\progress;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_vocab_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_vocab_activity_structure_step::class)]
/**
 * Tests for backing up and restoring a Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_vocab_activity_structure_step
 * @covers     \restore_vocab_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var \stdClass the course. */
    protected $course;

    /** @var \stdClass the activity. */
    protected $vocab;

    /** @var \stdClass a student who has played the quiz. */
    protected $student;

    /** @var \stdClass the quiz's question category. */
    protected $category;

    /** @var int the quiz, which uses the category. */
    protected $quizid;

    /**
     * Make an activity with two words, two stages, two quizzes, a question about
     * each word, and a student's finished quiz attempt.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->vocab = $generator->create_module('vocab', ['course' => $this->course->id, 'name' => 'Fruit words']);
        $vocabgenerator = $generator->get_plugin_generator('mod_vocab');

        // The word "apple" has a language and lemma, and "banana" does not.
        $langid = $DB->insert_record('vocab_langs', (object) ['langcode' => 'en']);
        $lemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => $langid, 'lemma' => 'apple']);
        $DB->insert_record('vocab_words', (object) ['lemmaid' => $lemmaid, 'word' => 'apple']);
        $vocabgenerator->add_words($this->vocab->id, ['apple', 'banana']);
        progress::save_stages($this->vocab->id, [progress::STAGE_RECOGNIZE => [1, 2], progress::STAGE_RECALL => [1, 1]]);

        $questions = $generator->get_plugin_generator('core_question');
        $questioncontext = $vocabgenerator->get_question_context($this->course);
        $this->category = $questions->create_question_category(['contextid' => $questioncontext->id, 'name' => 'Fruit']);
        foreach (['apple', 'banana'] as $word) {
            $question = $questions->create_question('truefalse', null, ['category' => $this->category->id, 'name' => $word]);
            progress::link_questions((int) $DB->get_field('vocab_words', 'id', ['word' => $word]), [$question->id]);
        }
        $this->quizid = progress::add_game_instance(
            $this->vocab->id,
            'quiz',
            progress::STAGE_RECOGNIZE,
            ['categoryid' => (int) $this->category->id, 'wordcount' => 2],
            'Fruit quiz'
        );
        progress::add_game_instance($this->vocab->id, 'quiz', progress::STAGE_RECALL, ['categoryid' => 0]);

        // The student finishes an attempt at the quiz.
        $progress = progress::create($this->vocab->id);
        $instances = $progress->get_game_instances();
        $cm = get_coursemodule_from_instance('vocab', $this->vocab->id);
        $game = gamebase::create($progress->get_vocab(), $cm, $instances[$this->quizid]);
        $attempt = $game->start_attempt((int) $this->student->id);
        $this->assertNotNull($attempt);
        $game->process_attempt($attempt, true, []);
    }

    /**
     * Back up the activity, and restore it into a course.
     *
     * @param \stdClass $course the course to restore into.
     * @param bool $userdata include students' attempts and progress.
     * @param bool $wholecourse back up the whole course, not only the activity.
     * @return \stdClass the restored activity.
     */
    protected function backup_and_restore(\stdClass $course, bool $userdata, bool $wholecourse = false): \stdClass {
        global $CFG, $DB, $USER;
        $cm = get_coursemodule_from_instance('vocab', $this->vocab->id);
        $bc = new \backup_controller(
            ($wholecourse ? \backup::TYPE_1COURSE : \backup::TYPE_1ACTIVITY),
            ($wholecourse ? $this->course->id : $cm->id),
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $folder = 'vocab-' . random_string(8);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), "$CFG->tempdir/backup/$folder");
        $bc->destroy();

        $before = $DB->get_fieldset_select('vocab', 'id', 'course = ?', [$course->id]);
        $rc = new \restore_controller(
            $folder,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->get_plan()->get_setting('users')->set_value($userdata);
        $this->assertTrue($rc->execute_precheck(), json_encode($rc->get_precheck_results()));
        $rc->execute_plan();
        $rc->destroy();

        $after = $DB->get_fieldset_select('vocab', 'id', 'course = ?', [$course->id]);
        $newids = array_values(array_diff($after, $before));
        $this->assertCount(1, $newids);
        return $DB->get_record('vocab', ['id' => reset($newids)], '*', MUST_EXIST);
    }

    /**
     * Restored into another course with user data: the words, stages, games, questions
     * and the student's attempt and progress are restored, with their ids mapped.
     */
    public function test_restore_into_other_course(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->enrol_user($this->student->id, $course->id, 'student');
        $vocab = $this->backup_and_restore($course, true);
        $this->assertSame('Fruit words', $vocab->name);

        // The same words of this site, in the same order.
        $sql = 'SELECT vwi.sortorder, vwi.wordid FROM {vocab_word_instances} vwi WHERE vwi.vocabid = ? ORDER BY vwi.sortorder';
        $this->assertEquals(
            $DB->get_records_sql_menu($sql, [$this->vocab->id]),
            $DB->get_records_sql_menu($sql, [$vocab->id])
        );
        $this->assertEquals(
            $DB->get_records_menu('vocab_stages', ['vocabid' => $this->vocab->id], 'stage', 'stage, completecount'),
            $DB->get_records_menu('vocab_stages', ['vocabid' => $vocab->id], 'stage', 'stage, completecount')
        );

        // Both quizzes, with the first one's category mapped to the restored copy in the other course.
        $progress = progress::create($vocab->id);
        $instances = array_values($progress->get_game_instances(false));
        $this->assertCount(2, $instances);
        $this->assertSame('Fruit quiz', $instances[0]->title);
        $config = json_decode($instances[0]->configdata, true);
        if (\mod_vocab\question_banks::uses_bank_activities()) {
            // Moodle 5.x: on the same site, the original shared question bank is used, as for quizzes.
            $this->assertEquals($this->category->id, $config['categoryid']);
        } else {
            // Moodle 4.x: the questions are copied into the other course.
            $this->assertNotEquals($this->category->id, $config['categoryid']);
            $category = $DB->get_record('question_categories', ['id' => $config['categoryid']], '*', MUST_EXIST);
            $this->assertSame('Fruit', $category->name);
            $this->assertArrayHasKey($category->contextid, \mod_vocab\question_banks::get_contexts($course->id));
        }
        $this->assertSame(0, json_decode($instances[1]->configdata, true)['categoryid']);

        // The words are linked to the restored questions, so the restored quiz has questions.
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $game = gamebase::create($progress->get_vocab(), $cm, $instances[0]);
        $this->assertSame([], $game->get_problems());
        $this->assertCount(2, $game->get_questions(array_keys($progress->get_words())));
        // The other quiz (any category) has questions in the other course: copies (4.x),
        // or the questions of attempts that were saved in a new bank (5.x).
        $game = gamebase::create($progress->get_vocab(), $cm, $instances[1]);
        $this->assertSame([], $game->get_problems());

        // The student's attempt, with its question usage and the words of its questions.
        $attempt = $DB->get_record('vocab_game_attempts', ['gameinstanceid' => $instances[0]->id], '*', MUST_EXIST);
        $this->assertEquals($this->student->id, $attempt->userid);
        $this->assertNotEmpty($attempt->uniqueid);
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $this->assertSame(2, $quba->question_count());
        $slots = json_decode($attempt->data, true)['slots'];
        $this->assertEqualsCanonicalizing(array_keys($progress->get_words()), array_values($slots));
        $this->assertSame(2, $DB->count_records('vocab_word_usages', ['gameattemptid' => $attempt->id]));
        $sql = 'SELECT COUNT(1)
                  FROM {vocab_word_stages} vws
                  JOIN {vocab_word_instances} vwi ON vwi.id = vws.wordinstanceid
                 WHERE vwi.vocabid = ? AND vws.userid = ?';
        $this->assertSame(
            $DB->count_records_sql($sql, [$this->vocab->id, $this->student->id]),
            $DB->count_records_sql($sql, [$vocab->id, $this->student->id])
        );
    }

    /**
     * Restored from a backup of the whole course, into a new course: the quiz uses the
     * restored copy of its category in the new course, and has questions.
     */
    public function test_restore_course_backup(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $vocab = $this->backup_and_restore($course, false, true);
        $progress = progress::create($vocab->id);
        $instances = array_values($progress->get_game_instances(false));
        $this->assertCount(2, $instances);
        $categoryid = json_decode($instances[0]->configdata, true)['categoryid'];
        $this->assertNotEquals($this->category->id, $categoryid);
        $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
        $this->assertSame('Fruit', $category->name);
        $this->assertArrayHasKey($category->contextid, \mod_vocab\question_banks::get_contexts($course->id));
        $game = gamebase::create($progress->get_vocab(), get_coursemodule_from_instance('vocab', $vocab->id), $instances[0]);
        $this->assertSame([], $game->get_problems());
        $this->assertCount(2, $game->get_questions(array_keys($progress->get_words())));
    }

    /**
     * Restored without user data: no attempts or progress.
     */
    public function test_restore_without_user_data(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $vocab = $this->backup_and_restore($course, false);
        $instanceids = $DB->get_fieldset_select('vocab_game_instances', 'id', 'vocabid = ?', [$vocab->id]);
        $this->assertCount(2, $instanceids);
        [$select, $params] = $DB->get_in_or_equal($instanceids);
        $this->assertSame(0, $DB->count_records_select('vocab_game_attempts', "gameinstanceid $select", $params));
        $this->assertSame(0, $DB->count_records_sql(
            'SELECT COUNT(1) FROM {vocab_word_stages} vws
               JOIN {vocab_word_instances} vwi ON vwi.id = vws.wordinstanceid WHERE vwi.vocabid = ?',
            [$vocab->id]
        ));
    }

    /**
     * Duplicated in the same course: the copy uses the same question category.
     */
    public function test_duplicate(): void {
        global $DB;
        $cm = get_coursemodule_from_instance('vocab', $this->vocab->id);
        if (method_exists('\\core_courseformat\\local\\cmactions', 'duplicate')) {
            // Moodle 5.2 and later.
            $newcm = \core_courseformat\formatactions::cm($this->course->id)->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($cm->id));
        }
        $instances = array_values(progress::create($newcm->instance)->get_game_instances(false));
        $this->assertCount(2, $instances);
        $this->assertEquals($this->category->id, json_decode($instances[0]->configdata, true)['categoryid']);
        $this->assertSame(2, $DB->count_records('vocab_word_instances', ['vocabid' => $newcm->instance]));
    }
}
