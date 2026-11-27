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
 * Reacts when questions and question categories that games use are deleted,
 * in Moodle 4.x and 5.x: games may change their settings, and teachers are told.
 *
 * See also gamebase::get_problems(), which finds the same problems when a game
 * loads, for changes that trigger no events, e.g. when a course is deleted.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A question category has been deleted, by a teacher. Its subcategories
     * have moved to its parent, and its questions have been moved or deleted.
     *
     * @param \core\event\question_category_deleted $event
     */
    public static function question_category_deleted(\core\event\question_category_deleted $event): void {
        $category = $event->get_record_snapshot('question_categories', $event->objectid);
        if (empty($category)) {
            $category = (object) ['id' => $event->objectid, 'name' => '', 'parent' => 0];
        }
        self::tell_games(function (gamebase $game) use ($category) {
            return $game->question_category_deleted($category);
        });
    }

    /**
     * A question has been deleted. Its links to words are removed, and the games
     * in the activities that have those words are told.
     *
     * @param \core\event\question_deleted $event
     */
    public static function question_deleted(\core\event\question_deleted $event): void {
        global $DB;
        $questionid = (int) $event->objectid;
        $wordids = $DB->get_fieldset_select('vocab_word_questions', 'wordid', 'questionid = ?', [$questionid]);
        if (empty($wordids)) {
            return;
        }
        $DB->delete_records('vocab_word_questions', ['questionid' => $questionid]);
        [$wordsql, $params] = $DB->get_in_or_equal(array_unique($wordids));
        $vocabids = $DB->get_fieldset_sql("SELECT DISTINCT vocabid FROM {vocab_word_instances} WHERE wordid $wordsql", $params);
        $question = (object) [
            'id' => $questionid,
            'category' => (int) ($event->other['categoryid'] ?? 0),
            'contextid' => (int) $event->contextid,
        ];
        self::tell_games(function (gamebase $game) use ($question) {
            return $game->question_deleted($question);
        }, $vocabids);
    }

    /**
     * An activity has been deleted. If it was a question bank (Moodle 5.x), its question
     * categories have been deleted too, without question_category_deleted events.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        if (($event->other['modulename'] ?? '') !== 'qbank') {
            return;
        }
        self::tell_games(function (gamebase $game) {
            return $game->question_bank_deleted();
        });
    }

    /**
     * Tell the games that use questions what happened, and send their messages to teachers.
     *
     * @param callable $callback given a game, returns a message for teachers, or null.
     * @param int[]|null $vocabids the activities whose games are told, or null for all.
     */
    protected static function tell_games(callable $callback, ?array $vocabids = null): void {
        foreach (gamebase::get_games() as $class) {
            if (!$class::uses_questions()) {
                continue;
            }
            foreach ($class::get_all($vocabids) as $game) {
                if ($message = $callback($game)) {
                    notification::send($game, $message);
                }
            }
        }
    }
}
