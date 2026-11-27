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

/**
 * tests/generator/lib.php
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Data generator for the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_vocab_generator extends testing_module_generator {
    /**
     * Add a Vocabulary activity.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass the activity record, with "cmid".
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        if (!isset($record->name)) {
            $record->name = 'Vocabulary ' . ($this->instancecount + 1);
        }
        return parent::create_instance($record, (array) $options);
    }

    /**
     * Add words to an activity's word list.
     *
     * @param int $vocabid
     * @param string[] $words
     * @return int[] word id => word.
     */
    public function add_words(int $vocabid, array $words): array {
        global $DB;
        $result = [];
        foreach (array_values($words) as $i => $word) {
            $wordid = $DB->get_field('vocab_words', 'id', ['word' => $word]);
            if (!$wordid) {
                $wordid = $DB->insert_record('vocab_words', (object) ['lemmaid' => 0, 'word' => $word]);
            }
            $DB->insert_record('vocab_word_instances', (object) [
                'vocabid' => $vocabid,
                'wordid' => $wordid,
                'enabled' => 1,
                'sortorder' => $i,
            ]);
            $result[$wordid] = $word;
        }
        return $result;
    }

    /**
     * Add a game to an activity.
     *
     * @param array|stdClass $record with vocabid, stage, and optionally game (default "quiz"),
     *     title, enabled (default 1) and configdata (an array).
     * @return int the id of the game instance.
     */
    public function create_game_instance($record): int {
        $record = (array) $record;
        $id = \mod_vocab\progress::add_game_instance(
            (int) $record['vocabid'],
            $record['game'] ?? 'quiz',
            $record['stage'],
            $record['configdata'] ?? [],
            $record['title'] ?? ''
        );
        if (isset($record['enabled']) && !$record['enabled']) {
            \mod_vocab\progress::set_game_instance_enabled($id, false);
        }
        return $id;
    }

    /**
     * Get the context in which a course's questions are kept: the course (Moodle 4.x),
     * or its default question bank, which is made if needed (Moodle 5.x).
     *
     * @param \stdClass $course
     * @return \context
     */
    public function get_question_context(\stdClass $course): \context {
        return \context::instance_by_id(\mod_vocab\question_banks::get_default_category($course)->contextid);
    }
}
