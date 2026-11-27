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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\vocabgame_quiz\game::class)]
/**
 * Tests for the links from words to questions: a link is to every version of a
 * question (its question bank entry), and games use the latest version that is ready.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\progress
 * @covers     \mod_vocab\observer
 * @covers     \vocabgame_quiz\game
 */
final class question_links_test extends \advanced_testcase {
    /** @var \core_question_generator */
    protected $questions;

    /** @var \stdClass the question category. */
    protected $category;

    /** @var int the id of the word "apple". */
    protected $wordid;

    /** @var gamebase the quiz in an activity with the word "apple". */
    protected $game;

    /** @var int[] the activity's word instance ids. */
    protected $words;

    /**
     * Make an activity with the word "apple" and a quiz, and a question category.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        if (!progress::has_question_versions()) {
            $this->markTestSkipped('Questions have no versions before Moodle 4.0.');
        }
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $generator->create_and_enrol($course, 'editingteacher'); // Who is told about problems.
        $vocabgenerator = $generator->get_plugin_generator('mod_vocab');
        $vocabgenerator->add_words($vocab->id, ['apple']);
        $this->wordid = (int) $DB->get_field('vocab_words', 'id', ['word' => 'apple'], MUST_EXIST);
        $this->questions = $generator->get_plugin_generator('core_question');
        $this->category = $this->questions->create_question_category([
            'contextid' => $vocabgenerator->get_question_context($course)->id,
        ]);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE, ['categoryid' => $this->category->id]);
        $progress = progress::create($vocab->id);
        $instances = $progress->get_game_instances();
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $this->game = gamebase::create($progress->get_vocab(), $cm, reset($instances));
        $this->words = array_keys($progress->get_words());
    }

    /**
     * Get the ids of the questions that the quiz would use for "apple".
     *
     * @return int[]
     */
    protected function get_quiz_questions(): array {
        $questions = $this->game->get_questions($this->words);
        return $questions ? reset($questions) : [];
    }

    /**
     * A word is linked once to each question, whichever version is given, and the quiz
     * uses the latest version that is ready.
     */
    public function test_edited_question(): void {
        global $DB;
        $first = $this->questions->create_question('truefalse', null, ['category' => $this->category->id]);
        progress::link_questions($this->wordid, [$first->id]);
        $second = $this->questions->update_question($first, null, ['questiontext' => 'Is an apple a fruit?']);
        $this->assertNotEquals($first->id, $second->id);
        progress::link_questions($this->wordid, [$second->id]);

        $links = $DB->get_records('vocab_word_questions', ['wordid' => $this->wordid]);
        $this->assertCount(1, $links);
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $first->id]);
        $this->assertEquals($entryid, reset($links)->questionbankentryid);
        $this->assertSame([(int) $second->id], $this->get_quiz_questions());

        // If the latest version is hidden, the latest version that is ready is used.
        $DB->set_field('question_versions', 'status', 'hidden', ['questionid' => $second->id]);
        $this->assertSame([(int) $first->id], $this->get_quiz_questions());
    }

    /**
     * When an old version is deleted, the link moves to the latest version, and teachers
     * are not told. When the last version is deleted, the link is removed.
     */
    public function test_deleted_versions(): void {
        global $DB;
        $first = $this->questions->create_question('truefalse', null, ['category' => $this->category->id]);
        progress::link_questions($this->wordid, [$first->id]);
        $second = $this->questions->update_question($first);

        $sink = $this->redirectMessages();
        question_delete_question($first->id);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame([(int) $second->id], $this->get_quiz_questions());
        $link = $DB->get_record('vocab_word_questions', ['wordid' => $this->wordid], '*', MUST_EXIST);
        $this->assertEquals($second->id, $link->questionid);

        question_delete_question($second->id);
        $this->assertFalse($DB->record_exists('vocab_word_questions', ['wordid' => $this->wordid]));
        $this->assertCount(1, $sink->get_messages(), 'The quiz has no questions left.');
        $sink->close();
    }

    /**
     * Links made before words were linked to questions' bank entries are converted:
     * links to questions that no longer exist are removed, and links to several
     * versions of one question are merged.
     */
    public function test_update_question_links(): void {
        global $DB;
        $first = $this->questions->create_question('truefalse', null, ['category' => $this->category->id]);
        $second = $this->questions->update_question($first);
        $other = $this->questions->create_question('truefalse', null, ['category' => $this->category->id]);
        foreach ([$first->id, $second->id, $other->id, 999999] as $questionid) {
            $DB->insert_record('vocab_word_questions', (object) [
                'wordid' => $this->wordid, 'questionid' => $questionid, 'questionbankentryid' => 0, 'timecreated' => time(),
            ]);
        }

        $this->assertSame(2, progress::update_question_links());
        $fields = 'questionid, questionbankentryid';
        $links = $DB->get_records_menu('vocab_word_questions', ['wordid' => $this->wordid], 'questionid', $fields);
        $entries = $DB->get_records_menu('question_versions', null, '', 'questionid, questionbankentryid');
        $this->assertEquals([$first->id => $entries[$first->id], $other->id => $entries[$other->id]], $links);
        $this->assertEqualsCanonicalizing([(int) $second->id, (int) $other->id], $this->get_quiz_questions());
    }
}
