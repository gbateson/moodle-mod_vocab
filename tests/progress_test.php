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
 * Tests for students' progress with words, through the stages of learning.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\progress
 */
final class progress_test extends \advanced_testcase {
    /**
     * Make an activity with words and stages.
     *
     * @param array $stages stage => [passcount, completecount].
     * @param array $settings fields of the activity, e.g. attempttype.
     * @return array [progress, word instance ids, user id]
     */
    protected function make_activity(array $stages, array $settings = []): array {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['apple', 'banana', 'cherry']);
        if ($settings) {
            $DB->update_record('vocab', (object) (['id' => $vocab->id] + $settings));
        }
        progress::save_stages($vocab->id, $stages);
        $user = $generator->create_and_enrol($course, 'student');
        $progress = progress::create($vocab->id);
        return [$progress, array_keys($progress->get_words()), (int) $user->id];
    }

    /**
     * Play a game at a stage, with scores for some words.
     *
     * @param progress $progress
     * @param int $userid
     * @param string $stage
     * @param array $scores word instance id => score (0 to 1).
     * @param int|null $time
     */
    protected function play(progress $progress, int $userid, string $stage, array $scores, ?int $time = null): void {
        $instance = (object) ['id' => 0, 'stage' => $stage];
        $attempt = $progress->start_attempt($instance, $userid, array_keys($scores));
        $progress->finish_attempt($attempt, $scores, $time);
    }

    /**
     * Words pass and complete stages, and later stages offer only words passed at the earlier ones.
     */
    public function test_stages(): void {
        $this->resetAfterTest();
        [$progress, $words, $userid] = $this->make_activity([
            progress::STAGE_RECOGNIZE => [1, 2],
            progress::STAGE_RECALL => [1, 1],
        ]);
        [$apple, $banana, $cherry] = $words;
        $this->assertSame([progress::STAGE_RECOGNIZE, progress::STAGE_RECALL], array_keys($progress->get_stages()));
        $this->assertSame($words, array_keys($progress->get_available_words($userid, progress::STAGE_RECOGNIZE)));
        $this->assertSame([], $progress->get_available_words($userid, progress::STAGE_RECALL));
        $this->assertSame([3, 0, 0, 3], $progress->get_counts($userid));

        // Apple passes "recognize", banana fails.
        $this->play($progress, $userid, progress::STAGE_RECOGNIZE, [$apple => 1, $banana => 0.5], 1000);
        $this->assertSame([$apple], array_keys($progress->get_available_words($userid, progress::STAGE_RECALL)));
        $this->assertSame([3, 0, 2, 1], $progress->get_counts($userid));
        // Words in progress come before new ones, least recently played first.
        $this->assertSame([$apple, $banana, $cherry], array_keys($progress->get_available_words($userid, 'recognize')));

        // Apple completes "recognize", so it is no longer offered there.
        $this->play($progress, $userid, progress::STAGE_RECOGNIZE, [$apple => 0.9], 2000);
        $this->assertSame([$banana, $cherry], array_keys($progress->get_available_words($userid, 'recognize')));

        // Apple completes "recall": it is mastered.
        $this->play($progress, $userid, progress::STAGE_RECALL, [$apple => 1], 3000);
        $this->assertSame([3, 1, 1, 1], $progress->get_counts($userid));
        $this->assertSame([], $progress->get_available_words($userid, progress::STAGE_RECALL));
        $this->assertEquals(33.33, $progress->get_grade($userid));

        // Choosing words: only those that are ready, and no more than asked for.
        $this->assertSame([$banana], $progress->choose_words($userid, 'recognize', 5, [$banana]));
        $this->assertSame([$banana], $progress->choose_words($userid, 'recognize', 1));
    }

    /**
     * The activity's mastery settings say which successes count.
     */
    public function test_count_successes(): void {
        $this->resetAfterTest();
        [$progress] = $this->make_activity([progress::STAGE_RECALL => [2, 3]]);
        $stage = (object) ['passcount' => 2, 'completecount' => 3];
        $history = function (array $scores) {
            $items = [];
            foreach ($scores as $i => $score) {
                $items[] = (object) ['score' => $score, 'timecreated' => 1000 * ($i + 1)];
            }
            return $items;
        };
        $vocab = $progress->get_vocab();

        // Any attempts: every success counts.
        $result = $progress->count_successes($history([100, 0, 90, 100]), $stage);
        $this->assertSame(4, $result['interactions']);
        $this->assertSame(3, $result['successes']);
        $this->assertSame(3000, $result['timepassed']);
        $this->assertSame(4000, $result['timecompleted']);

        // Consecutive attempts: a failure starts the count again.
        $vocab->attempttype = activity::ATTEMPTTYPE_CONSECUTIVE;
        $result = $progress->count_successes($history([100, 0, 90, 100]), $stage);
        $this->assertSame(2, $result['successes']);
        $this->assertSame(4000, $result['timepassed']);
        $this->assertSame(0, $result['timecompleted']);

        // Most recent attempts: only the last three interactions count.
        $vocab->attempttype = activity::ATTEMPTTYPE_RECENT;
        $result = $progress->count_successes($history([100, 100, 0, 0, 100]), $stage);
        $this->assertSame(1, $result['successes']);
        $this->assertSame(2000, $result['timepassed']);

        // A fixed delay: successes too soon after the last one do not count.
        $vocab->attempttype = activity::ATTEMPTTYPE_ANY;
        $vocab->attemptdelay = 1500;
        $result = $progress->count_successes($history([100, 100, 100, 100]), $stage);
        $this->assertSame(2, $result['successes']);
        $this->assertSame(4500, $result['timedue']);

        // An expanding delay (spaced repetition): 1 day, then 2 days ...
        $vocab->attemptdelay = activity::ATTEMPTDELAY_EXPANDING;
        $day = DAYSECS;
        $items = [
            (object) ['score' => 100, 'timecreated' => 0],
            (object) ['score' => 100, 'timecreated' => $day / 2],
            (object) ['score' => 100, 'timecreated' => $day],
            (object) ['score' => 100, 'timecreated' => 2 * $day],
        ];
        $result = $progress->count_successes($items, $stage);
        $this->assertSame(2, $result['successes']);
        $this->assertSame(3 * $day, $result['timedue']);
        // ... unless the games close sooner.
        $vocab->gamesclose = 2 * $day;
        $result = $progress->count_successes($items, $stage);
        $this->assertSame(3, $result['successes']);
        $this->assertSame(2 * $day, $result['timecompleted']);
    }

    /**
     * A finished attempt records a score for each word, and its overall score.
     */
    public function test_finish_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [$progress, $words, $userid] = $this->make_activity([progress::STAGE_RECOGNIZE => [1, 1]]);
        $instance = (object) ['id' => 0, 'stage' => progress::STAGE_RECOGNIZE];
        $attempt = $progress->start_attempt($instance, $userid, [$words[0], $words[1]]);
        $this->assertEquals($attempt->id, progress::get_unfinished_attempt(0, $userid)->id);
        // The second word has no score, so it gets 0; the third was not given, but is scored too.
        $progress->finish_attempt($attempt, [$words[0] => 1, $words[2] => 0.5]);
        $this->assertNull(progress::get_unfinished_attempt(0, $userid));
        $record = $DB->get_record('vocab_game_attempts', ['id' => $attempt->id]);
        $this->assertEquals(progress::ATTEMPT_FINISHED, $record->status);
        $this->assertEquals(50, $record->score);
        $this->assertCount(3, progress::get_attempt_words($attempt->id));
        $this->assertSame(
            [progress::STATE_COMPLETED, progress::STATE_INPROGRESS, progress::STATE_INPROGRESS],
            array_values($progress->get_word_states($userid))
        );
    }

    /**
     * Games are added at the end, can share a game and stage, and keep or suggest their titles.
     */
    public function test_add_and_update_game_instances(): void {
        global $DB;
        $this->resetAfterTest();
        [$progress] = $this->make_activity([progress::STAGE_RECOGNIZE => [1, 1], progress::STAGE_RECALL => [1, 1]]);
        $vocabid = $progress->get_vocab()->id;

        $first = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE, ['wordcount' => 3]);
        $second = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE, [], '  Food quiz  ');
        $this->assertSame([$first, $second], array_keys($progress->get_game_instances()));
        $select = 'vocabid = ? ORDER BY sortorder';
        $sortorders = $DB->get_fieldset_select('vocab_game_instances', 'sortorder', $select, [$vocabid]);
        $this->assertSame(['1', '2'], array_values($sortorders));

        $cm = get_coursemodule_from_instance('vocab', $vocabid);
        $instances = $progress->get_game_instances();
        $class = gamebase::get_class('quiz');
        $label = gamebase::create($progress->get_vocab(), $cm, $instances[$first])->get_label();
        $this->assertSame($class::get_default_title(progress::STAGE_RECOGNIZE), $label);
        $label = gamebase::create($progress->get_vocab(), $cm, $instances[$second])->get_label();
        $this->assertSame('Food quiz', $label);

        progress::update_game_instance($first, progress::STAGE_RECALL, ['wordcount' => 5], 'Spelling');
        $record = $DB->get_record('vocab_game_instances', ['id' => $first]);
        $this->assertSame(progress::STAGE_RECALL, $record->stage);
        $this->assertSame('Spelling', $record->title);
        $this->assertSame(['wordcount' => 5], json_decode($record->configdata, true));
        $this->assertSame('1', $record->sortorder);
    }

    /**
     * A game that has not been played is deleted; one that has is hidden, and can be shown again.
     */
    public function test_delete_and_hide_game_instances(): void {
        global $DB;
        $this->resetAfterTest();
        [$progress, $words, $userid] = $this->make_activity([progress::STAGE_RECOGNIZE => [1, 1]]);
        $vocabid = $progress->get_vocab()->id;
        $unplayed = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE);
        $played = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE);
        $instance = $DB->get_record('vocab_game_instances', ['id' => $played]);
        $progress->start_attempt($instance, $userid, $words);
        $this->assertSame(0, progress::count_game_instance_attempts($unplayed));
        $this->assertSame(1, progress::count_game_instance_attempts($played));

        $this->assertTrue(progress::delete_game_instance($unplayed));
        $this->assertFalse($DB->record_exists('vocab_game_instances', ['id' => $unplayed]));

        $this->assertFalse(progress::delete_game_instance($played));
        $this->assertSame([], $progress->get_game_instances());
        $this->assertSame([$played], array_keys($progress->get_game_instances(false)));
        $this->assertSame(1, progress::count_game_instance_attempts($played));

        progress::set_game_instance_enabled($played, true);
        $this->assertSame([$played], array_keys($progress->get_game_instances()));
    }

    /**
     * Games can be put in a new order; games of other activities, and repeated ids, are ignored.
     */
    public function test_reorder_game_instances(): void {
        $this->resetAfterTest();
        [$progress] = $this->make_activity([progress::STAGE_RECOGNIZE => [1, 1]]);
        $vocabid = $progress->get_vocab()->id;
        [$other] = $this->make_activity([progress::STAGE_RECOGNIZE => [1, 1]]);
        $otherid = progress::add_game_instance($other->get_vocab()->id, 'quiz', progress::STAGE_RECOGNIZE);
        $a = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE);
        $b = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE);
        $c = progress::add_game_instance($vocabid, 'quiz', progress::STAGE_RECOGNIZE);

        progress::reorder_game_instances($vocabid, [$c, $a, $c, $otherid]);
        $this->assertSame([$c, $a, $b], array_keys($progress->get_game_instances()));
        $this->assertSame([$otherid], array_keys($other->get_game_instances()));

        progress::reorder_game_instances($vocabid, [$b, $a, $c]);
        $this->assertSame([$b, $a, $c], array_keys($progress->get_game_instances()));
    }

    /**
     * Questions made by the question bank tool are linked to their words.
     */
    public function test_link_questions(): void {
        global $DB;
        $this->resetAfterTest();
        progress::link_questions(5, [10, 11, 10]);
        progress::link_questions(5, [11]);
        $this->assertSame(2, $DB->count_records('vocab_word_questions', ['wordid' => 5]));
    }
}
