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
 * The structure step that restores a Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Restore a Vocabulary activity (see backup_vocab_activity_structure_step).
 *
 * Words are found by their language code, lemma and word, or created. The ids that
 * games keep in JSON (a quiz's question category, and the words of a quiz attempt's
 * questions) are mapped to the restored ids.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */
class restore_vocab_activity_structure_step extends restore_questions_activity_structure_step {
    /** @var int|null the id of the game attempt that is being restored, for its question usage. */
    protected $currentattemptid = null;

    /** @var int[] the word of each restored word instance: new word instance id => word id. */
    protected $wordids = [];

    /** @var array the links from words to questions, mapped in after_restore(): [word id, old question id, old entry id]. */
    protected $wordquestions = [];

    /** @var int[] the restored games whose settings are mapped in after_restore(): new id => new id. */
    protected $gameinstanceids = [];

    /**
     * Define the paths of the elements to restore.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $root = '/activity/vocab';
        $paths = [
            new restore_path_element('vocab', $root),
            new restore_path_element('vocab_stage', "$root/stages/stage"),
            new restore_path_element('vocab_word_instance', "$root/wordinstances/wordinstance"),
            new restore_path_element('vocab_word_question', "$root/wordinstances/wordinstance/wordquestions/wordquestion"),
            new restore_path_element('vocab_game_instance', "$root/gameinstances/gameinstance"),
        ];
        if ($userinfo) {
            $attempt = new restore_path_element('vocab_game_attempt', "$root/gameinstances/gameinstance/gameattempts/gameattempt");
            $paths[] = $attempt;
            $this->add_question_usages($attempt, $paths);
            $usagepath = $attempt->get_path() . '/wordusages/wordusage';
            $paths[] = new restore_path_element('vocab_word_usage', $usagepath);
            $paths[] = new restore_path_element('vocab_word_attempt', "$usagepath/wordattempts/wordattempt");
            $paths[] = new restore_path_element('vocab_word_stage', "$root/wordstages/wordstage");
            $paths[] = new restore_path_element('vocab_word_state', "$root/wordstates/wordstate");
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity.
     *
     * @param array $data
     */
    protected function process_vocab($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        foreach (['activityopen', 'activityclose', 'gamesopen', 'gamesclose'] as $name) {
            if (!empty($data->$name)) {
                $data->$name = $this->apply_date_offset($data->$name);
            }
        }
        $newitemid = $DB->insert_record('vocab', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a stage of learning.
     *
     * @param array $data
     */
    protected function process_vocab_stage($data) {
        global $DB;
        $data = (object) $data;
        $data->vocabid = $this->get_new_parentid('vocab');
        $DB->insert_record('vocab_stages', $data);
    }

    /**
     * Restore a word in the word list, finding or creating the word itself.
     *
     * @param array $data
     */
    protected function process_vocab_word_instance($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $wordid = $this->get_wordid((string) $data->word, (string) ($data->lemma ?? ''), (string) ($data->langcode ?? ''));
        $newitemid = $DB->insert_record('vocab_word_instances', (object) [
            'vocabid' => $this->get_new_parentid('vocab'),
            'wordid' => $wordid,
            'enabled' => $data->enabled,
            'sortorder' => $data->sortorder,
        ]);
        $this->wordids[$newitemid] = $wordid;
        $this->set_mapping('vocab_word_instance', $oldid, $newitemid);
    }

    /**
     * Find a word, or create it: its language, lemma and the word itself.
     *
     * @param string $word
     * @param string $lemma empty if the word had no lemma.
     * @param string $langcode empty if the word had no lemma.
     * @return int the id of the word in vocab_words.
     */
    protected function get_wordid(string $word, string $lemma, string $langcode): int {
        global $DB;
        if ($lemma === '' || $langcode === '') {
            // A word without a lemma: use the same word, whatever its lemma.
            $wordid = $DB->get_field('vocab_words', 'MIN(id)', ['word' => $word]);
            return (int) ($wordid ?: $DB->insert_record('vocab_words', (object) ['lemmaid' => 0, 'word' => $word]));
        }
        $langid = $this->get_or_create_id('vocab_langs', ['langcode' => $langcode]);
        $lemmaid = $this->get_or_create_id('vocab_lemmas', ['langid' => $langid, 'lemma' => $lemma]);
        return $this->get_or_create_id('vocab_words', ['lemmaid' => $lemmaid, 'word' => $word]);
    }

    /**
     * Find a record, or create it, as the word list tool does (see toolform::get_record_id()).
     *
     * @param string $table
     * @param array $fields
     * @return int the id of the record.
     */
    protected function get_or_create_id(string $table, array $fields): int {
        global $DB;
        $id = $DB->get_field($table, 'MIN(id)', $fields);
        return (int) ($id ?: $DB->insert_record($table, (object) $fields));
    }

    /**
     * Remember a link from a word to a question. It is restored in after_restore(),
     * when the restored questions are in their final question banks.
     *
     * @param array $data
     */
    protected function process_vocab_word_question($data) {
        $data = (object) $data;
        $wordinstanceid = $this->get_new_parentid('vocab_word_instance');
        if (!empty($this->wordids[$wordinstanceid])) {
            $this->wordquestions[] = [
                $this->wordids[$wordinstanceid],
                (int) $data->questionid,
                (int) ($data->questionbankentryid ?? 0),
            ];
        }
    }

    /**
     * Restore a game. A game that is not installed on this site is skipped.
     *
     * @param array $data
     */
    protected function process_vocab_game_instance($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        if (!$gameid = $DB->get_field('vocab_games', 'id', ['name' => $data->game])) {
            $this->log('Game "' . $data->game . '" is not installed, so it was not restored.', backup::LOG_WARNING);
            return;
        }
        $newitemid = $DB->insert_record('vocab_game_instances', (object) [
            'vocabid' => $this->get_new_parentid('vocab'),
            'gameid' => $gameid,
            'title' => $data->title,
            'sortorder' => $data->sortorder,
            'attemptlimit' => $data->attemptlimit,
            'configdata' => $data->configdata, // Mapped in after_restore().
            'stage' => $data->stage,
            'enabled' => $data->enabled,
        ]);
        $this->set_mapping('vocab_game_instance', $oldid, $newitemid);
        $this->gameinstanceids[$newitemid] = $newitemid;
    }

    /**
     * Map the ids in a game's settings: the question category, if any (see get_category()).
     *
     * @param string $configdata JSON
     * @return string JSON
     */
    protected function map_configdata(string $configdata): string {
        $config = json_decode($configdata, true);
        if (!is_array($config) || empty($config['categoryid'])) {
            return $configdata;
        }
        $config['categoryid'] = $this->get_categoryid((int) $config['categoryid']);
        return json_encode($config);
    }

    /**
     * Get the question category to use instead of a category in the backup:
     * - the restored copy, if it is in one of the target course's question banks;
     * - otherwise, on the same site, the original category, if it still exists, as
     *   Moodle 5.x does for quizzes that use questions from a shared question bank;
     * - otherwise 0, for any category.
     *
     * @param int $oldid
     * @return int
     */
    protected function get_categoryid(int $oldid): int {
        global $DB;
        $newid = (int) $this->get_mappingid('question_category', $oldid);
        $contextid = ($newid ? $DB->get_field('question_categories', 'contextid', ['id' => $newid]) : false);
        if ($contextid && array_key_exists($contextid, $this->get_question_contexts())) {
            return $newid;
        }
        if ($this->task->is_samesite() && $DB->record_exists('question_categories', ['id' => $oldid])) {
            return $oldid;
        }
        return 0;
    }

    /**
     * Get the question version to link a word to instead of a question in the backup:
     * the restored copy (the latest version of its entry, if possible), if it is in one
     * of the target course's question banks. Otherwise, 0: on the same site, the word's
     * links to the original question still apply.
     *
     * @param int $oldquestionid
     * @param int $oldentryid
     * @return int
     */
    protected function get_questionid(int $oldquestionid, int $oldentryid): int {
        global $DB;
        $questionid = 0;
        if ($oldentryid && \mod_vocab\progress::has_question_versions()) {
            if ($entryid = $this->get_mappingid('question_bank_entry', $oldentryid)) {
                $questionid = (int) $DB->get_field_sql(
                    'SELECT questionid FROM {question_versions} WHERE questionbankentryid = ? ORDER BY version DESC',
                    [$entryid],
                    IGNORE_MULTIPLE
                );
            }
        }
        if (!$questionid) {
            $questionid = (int) $this->get_mappingid('question', $oldquestionid);
        }
        if (!$questionid) {
            return 0;
        }
        if (\mod_vocab\progress::has_question_versions()) {
            $sql = 'SELECT qc.contextid
                      FROM {question_versions} qv
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                     WHERE qv.questionid = ?';
        } else {
            $sql = 'SELECT qc.contextid
                      FROM {question} q
                      JOIN {question_categories} qc ON qc.id = q.category
                     WHERE q.id = ?';
        }
        $contextid = $DB->get_field_sql($sql, [$questionid]);
        return ($contextid && array_key_exists($contextid, $this->get_question_contexts()) ? $questionid : 0);
    }

    /**
     * Get the contexts of the target course's questions: the course (Moodle 4.x),
     * or its question banks (Moodle 5.x).
     *
     * @return \context[] context id => context.
     */
    protected function get_question_contexts(): array {
        return \mod_vocab\question_banks::get_contexts($this->get_courseid());
    }

    /**
     * Restore a student's attempt at a game. A quiz attempt's question usage
     * is restored next, and its id is saved in inform_new_usage_id().
     *
     * @param array $data
     */
    protected function process_vocab_game_attempt($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $this->currentattemptid = null;
        $data->gameinstanceid = $this->get_new_parentid('vocab_game_instance');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (empty($data->gameinstanceid) || empty($data->userid)) {
            return;
        }
        $data->uniqueid = 0;
        $data->data = $this->map_attempt_data((string) $data->data);
        $newitemid = $DB->insert_record('vocab_game_attempts', $data);
        $this->currentattemptid = $newitemid;
        $this->set_mapping('vocab_game_attempt', $oldid, $newitemid);
    }

    /**
     * Map the ids in an attempt's data: the word of each question (slot) of a quiz attempt.
     *
     * @param string $json
     * @return string JSON
     */
    protected function map_attempt_data(string $json): string {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['slots']) || !is_array($data['slots'])) {
            return $json;
        }
        foreach ($data['slots'] as $slot => $wordinstanceid) {
            $data['slots'][$slot] = (int) $this->get_mappingid('vocab_word_instance', $wordinstanceid);
        }
        return json_encode($data);
    }

    /**
     * Save the id of the question usage of the attempt that is being restored. If the
     * attempt was skipped, e.g. its student was not restored, the usage is deleted.
     *
     * @param int $newusageid
     */
    protected function inform_new_usage_id($newusageid) {
        global $DB;
        if ($this->currentattemptid) {
            $DB->set_field('vocab_game_attempts', 'uniqueid', $newusageid, ['id' => $this->currentattemptid]);
        } else {
            question_engine::delete_questions_usage_by_activity($newusageid);
        }
    }

    /**
     * Restore the use of a word in an attempt.
     *
     * @param array $data
     */
    protected function process_vocab_word_usage($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->gameattemptid = $this->get_new_parentid('vocab_game_attempt');
        $data->wordinstanceid = $this->get_mappingid('vocab_word_instance', $data->wordinstanceid);
        if (empty($data->gameattemptid) || empty($data->wordinstanceid)) {
            return;
        }
        $newitemid = $DB->insert_record('vocab_word_usages', $data);
        $this->set_mapping('vocab_word_usage', $oldid, $newitemid);
    }

    /**
     * Restore the score of a word in an attempt.
     *
     * @param array $data
     */
    protected function process_vocab_word_attempt($data) {
        global $DB;
        $data = (object) $data;
        $data->wordusageid = $this->get_new_parentid('vocab_word_usage');
        if (!empty($data->wordusageid)) {
            $DB->insert_record('vocab_word_attempts', $data);
        }
    }

    /**
     * Restore a student's progress with a word at a stage.
     *
     * @param array $data
     */
    protected function process_vocab_word_stage($data) {
        $this->restore_user_word_record('vocab_word_stages', $data);
    }

    /**
     * Restore a student's state with a word.
     *
     * @param array $data
     */
    protected function process_vocab_word_state($data) {
        $this->restore_user_word_record('vocab_word_states', $data);
    }

    /**
     * Restore a record about a student and a word, if both were restored.
     *
     * @param string $table
     * @param array $data
     */
    protected function restore_user_word_record(string $table, $data) {
        global $DB;
        $data = (object) $data;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->wordinstanceid = $this->get_mappingid('vocab_word_instance', $data->wordinstanceid);
        if (!empty($data->userid) && !empty($data->wordinstanceid)) {
            $DB->insert_record($table, $data);
        }
    }

    /**
     * Restore the activity's files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_vocab', 'intro', null);
    }

    /**
     * After the whole restore, when restored questions are in their final question banks
     * (in Moodle 5.x, they may be moved at the end), map the games' question categories,
     * and link the words to the restored questions.
     */
    protected function after_restore() {
        global $DB;
        foreach ($this->gameinstanceids as $id) {
            $configdata = (string) $DB->get_field('vocab_game_instances', 'configdata', ['id' => $id]);
            $newconfigdata = $this->map_configdata($configdata);
            if ($newconfigdata !== $configdata) {
                $DB->set_field('vocab_game_instances', 'configdata', $newconfigdata, ['id' => $id]);
            }
        }
        foreach ($this->wordquestions as [$wordid, $oldquestionid, $oldentryid]) {
            if ($questionid = $this->get_questionid($oldquestionid, $oldentryid)) {
                \mod_vocab\progress::link_questions($wordid, [$questionid]);
            }
        }
    }
}
