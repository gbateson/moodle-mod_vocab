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
 * The structure of a Vocabulary activity's backup.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Back up a Vocabulary activity: its settings, stages, word list and games, the
 * questions about its words and, with user data, students' progress and attempts.
 *
 * Words belong to the whole site, so each word is backed up as its language code,
 * lemma and word, and found or created again on restore. Their dictionary details
 * (definitions, multimedia, and so on) are not backed up (see docs/plan-manage-games.md).
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */
class backup_vocab_activity_structure_step extends backup_questions_activity_structure_step {
    /**
     * Define the structure of the backup.
     *
     * @return backup_nested_element the root element, wrapped for an activity.
     */
    protected function define_structure() {
        global $DB;

        $userinfo = $this->get_setting_value('userinfo');

        // The activity.
        $vocab = new backup_nested_element('vocab', ['id'], $this->get_fieldnames('vocab', ['id', 'course']));

        // Its stages of learning.
        $stages = new backup_nested_element('stages');
        $stage = new backup_nested_element('stage', ['id'], ['stage', 'passcount', 'completecount']);

        // Its words, with the language, lemma and word, to find or create them on restore.
        $wordinstances = new backup_nested_element('wordinstances');
        $wordinstance = new backup_nested_element('wordinstance', ['id'], [
            'wordid', 'enabled', 'sortorder', 'word', 'lemma', 'langcode',
        ]);

        // The questions about each word.
        $wordquestions = new backup_nested_element('wordquestions');
        $wordquestion = new backup_nested_element('wordquestion', ['id'], ['questionid', 'questionbankentryid']);

        // Its games.
        $gameinstances = new backup_nested_element('gameinstances');
        $gameinstance = new backup_nested_element('gameinstance', ['id'], [
            'game', 'title', 'sortorder', 'attemptlimit', 'configdata', 'stage', 'enabled',
        ]);

        // Build the tree, in the order needed to restore it: words before games,
        // because attempts refer to words.
        $vocab->add_child($stages);
        $stages->add_child($stage);

        $vocab->add_child($wordinstances);
        $wordinstances->add_child($wordinstance);
        $wordinstance->add_child($wordquestions);
        $wordquestions->add_child($wordquestion);

        $vocab->add_child($gameinstances);
        $gameinstances->add_child($gameinstance);

        // Sources.
        $vocab->set_source_table('vocab', ['id' => backup::VAR_ACTIVITYID]);
        $stage->set_source_table('vocab_stages', ['vocabid' => backup::VAR_PARENTID], 'id ASC');
        $wordinstance->set_source_sql(
            'SELECT vwi.id, vwi.wordid, vwi.enabled, vwi.sortorder, vw.word, vle.lemma, vla.langcode
               FROM {vocab_word_instances} vwi
               JOIN {vocab_words} vw ON vw.id = vwi.wordid
          LEFT JOIN {vocab_lemmas} vle ON vle.id = vw.lemmaid
          LEFT JOIN {vocab_langs} vla ON vla.id = vle.langid
              WHERE vwi.vocabid = ?
           ORDER BY vwi.sortorder, vwi.id',
            [backup::VAR_PARENTID]
        );
        // Words are shared by the whole site, and so are their links to questions,
        // so only the links to questions in this course's question banks are included.
        [$wordquestionsql, $wordquestionparams] = $this->get_word_questions_sql();
        $wordquestion->set_source_sql($wordquestionsql, $wordquestionparams);
        $gameinstance->set_source_sql(
            'SELECT vgi.id, vg.name AS game, vgi.title, vgi.sortorder, vgi.attemptlimit,
                    vgi.configdata, vgi.stage, vgi.enabled
               FROM {vocab_game_instances} vgi
               JOIN {vocab_games} vg ON vg.id = vgi.gameid
              WHERE vgi.vocabid = ?
           ORDER BY vgi.sortorder, vgi.id',
            [backup::VAR_PARENTID]
        );

        // The questions about the words are included with the activity. Moodle 4.x includes the
        // question banks of annotated questions; Moodle 5.x includes annotated question bank entries.
        $wordquestion->annotate_ids('question', 'questionid');
        if ($DB->get_manager()->table_exists('question_bank_entries')) {
            $wordquestion->annotate_ids('question_bank_entry', 'questionbankentryid');
        }

        if ($userinfo) {
            // Students' attempts at the games.
            $gameattempts = new backup_nested_element('gameattempts');
            $gameattempt = new backup_nested_element('gameattempt', ['id'], [
                'userid', 'score', 'status', 'timestarted', 'timecompleted', 'stage', 'uniqueid', 'data', 'timemodified',
            ]);
            $wordusages = new backup_nested_element('wordusages');
            $wordusage = new backup_nested_element('wordusage', ['id'], ['wordinstanceid']);
            $wordattempts = new backup_nested_element('wordattempts');
            $wordattempt = new backup_nested_element('wordattempt', ['id'], ['score', 'timecreated']);

            // Students' progress with each word.
            $wordstages = new backup_nested_element('wordstages');
            $wordstage = new backup_nested_element('wordstage', ['id'], [
                'userid', 'wordinstanceid', 'stage', 'interactions', 'successes', 'timelastplayed',
                'timelastsuccess', 'timedue', 'timepassed', 'timecompleted', 'timemodified',
            ]);
            $wordstates = new backup_nested_element('wordstates');
            $wordstate = new backup_nested_element('wordstate', ['id'], ['userid', 'wordinstanceid', 'state']);

            $gameinstance->add_child($gameattempts);
            $gameattempts->add_child($gameattempt);
            // The questions of a quiz attempt, as for mod_quiz.
            $this->add_question_usages($gameattempt, 'uniqueid');
            $gameattempt->add_child($wordusages);
            $wordusages->add_child($wordusage);
            $wordusage->add_child($wordattempts);
            $wordattempts->add_child($wordattempt);

            $vocab->add_child($wordstages);
            $wordstages->add_child($wordstage);
            $vocab->add_child($wordstates);
            $wordstates->add_child($wordstate);

            $gameattempt->set_source_table('vocab_game_attempts', ['gameinstanceid' => backup::VAR_PARENTID], 'id ASC');
            $wordusage->set_source_table('vocab_word_usages', ['gameattemptid' => backup::VAR_PARENTID], 'id ASC');
            $wordattempt->set_source_table('vocab_word_attempts', ['wordusageid' => backup::VAR_PARENTID], 'id ASC');
            $wordstage->set_source_sql(
                'SELECT vws.*
                   FROM {vocab_word_stages} vws
                   JOIN {vocab_word_instances} vwi ON vwi.id = vws.wordinstanceid
                  WHERE vwi.vocabid = ?
               ORDER BY vws.id',
                [backup::VAR_PARENTID]
            );
            $wordstate->set_source_sql(
                'SELECT vws.*
                   FROM {vocab_word_states} vws
                   JOIN {vocab_word_instances} vwi ON vwi.id = vws.wordinstanceid
                  WHERE vwi.vocabid = ?
               ORDER BY vws.id',
                [backup::VAR_PARENTID]
            );

            $gameattempt->annotate_ids('user', 'userid');
            $wordstage->annotate_ids('user', 'userid');
            $wordstate->annotate_ids('user', 'userid');
        }

        // Files.
        $vocab->annotate_files('mod_vocab', 'intro', null);

        return $this->prepare_activity_structure($vocab);
    }

    /**
     * Get the SQL for the links from a word to questions in this course's question
     * banks: the course (Moodle 4.x), or its question bank activities (Moodle 5.x).
     *
     * @return array [string $sql, array $params] for set_source_sql().
     */
    protected function get_word_questions_sql(): array {
        global $DB;
        $contextids = array_keys(\mod_vocab\question_banks::get_contexts($this->get_courseid()));
        if (empty($contextids)) {
            $contextids = [0];
        }
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids);
        if (\mod_vocab\progress::has_question_versions()) {
            $join = 'JOIN {question_bank_entries} qbe ON qbe.id = vwq.questionbankentryid
                     JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid';
        } else {
            $join = 'JOIN {question} q ON q.id = vwq.questionid
                     JOIN {question_categories} qc ON qc.id = q.category';
        }
        $sql = "SELECT vwq.id, vwq.questionid, vwq.questionbankentryid
                  FROM {vocab_word_questions} vwq
                       $join
                 WHERE vwq.wordid = ? AND qc.contextid $contextsql
              ORDER BY vwq.id";
        $params = ['../../wordid'];
        foreach ($contextparams as $contextid) {
            $params[] = ['sqlparam' => $contextid];
        }
        return [$sql, $params];
    }

    /**
     * Get the names of a table's fields.
     *
     * @param string $tablename the name of the table, without its prefix.
     * @param array $excludedfieldnames these field names are left out.
     * @return array of field names
     */
    protected function get_fieldnames($tablename, array $excludedfieldnames) {
        global $DB;
        return array_values(array_diff(array_keys($DB->get_columns($tablename)), $excludedfieldnames));
    }
}
