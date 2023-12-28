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
 * Defines the complete vocab structure for backup, with file and id annotations
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * backup_vocab_activity_structure_step
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 4.1
 */
class backup_vocab_activity_structure_step extends backup_activity_structure_step {

    /** maximum number of words to retrieve in one DB query */
    const GET_WORDS_LIMIT = 100;

    /**
     * define_structure
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function define_structure() {

        // Are we including userinfo?
        $userinfo = $this->get_setting_value('userinfo');

        /*////////////////////////////////////////
        // XML nodes declaration - words and games
        ////////////////////////////////////////*/

        // Backup games.
        $games = new backup_nested_element('games');
        $include = $this->get_fieldnames('vocab_games', ['id']);
        $game = new backup_nested_element('game', ['id', $include]);

        // Backup corpuses (used by "vocab_frequencies").
        $corpuses = new backup_nested_element('corpuses');
        $include = $this->get_fieldnames('vocab_corpuses', ['id']);
        $corpus = new backup_nested_element('corpus', ['id', $include]);

        // Backup langs.
        $langs = new backup_nested_element('langs');
        $include = $this->get_fieldnames('vocab_langs', ['id']);
        $lang = new backup_nested_element('lang', ['id', $include]);

        // Backup levels.
        $levels = new backup_nested_element('levels');
        $include = $this->get_fieldnames('vocab_levels', ['id']);
        $level = new backup_nested_element('level', ['id', $include]);
        // Backup  levelnames.
        $levelnames = new backup_nested_element('levelnames');
        $exclude = ['id', 'levelid', 'langid'];
        $include = $this->get_fieldnames('vocab_levelnames', $exclude);
        $levelname = new backup_nested_element('levelname', ['id', $include]);

        // Backup lemmas.
        $lemmas = new backup_nested_element('lemmas');
        $exclude = ['id', 'langid'];
        $include = $this->get_fieldnames('vocab_lemmas', $exclude);
        $lemma = new backup_nested_element('lemma', ['id', $include]);

        // Backup words.
        $words = new backup_nested_element('words');
        $exclude = ['id', 'lemmaid'];
        $include = $this->get_fieldnames('vocab_words', $exclude);
        $word = new backup_nested_element('word', ['id', $include]);

        // Backup antonyms.
        $antonyms = new backup_nested_element('antonyms');
        $exclude = ['id', 'wordid']; // TODO: fix antonymwordid later.
        $include = $this->get_fieldnames('vocab_antonyms', $exclude);
        $antonym = new backup_nested_element('antonym', ['id', $include]);

        // Backup definitions.
        $definitions = new backup_nested_element('definitions');
        $exclude = ['id', 'wordid']; // TODO: fix 'langid' and 'levelid'.
        $include = $this->get_fieldnames('vocab_definitions', $exclude);
        $definition = new backup_nested_element('definition', ['id', $include]);

        // Backup multimedias.
        $multimedias = new backup_nested_element('multimedias');
        $exclude = ['id', 'wordid']; // TODO: fix 'langid' and 'levelid'.
        $include = $this->get_fieldnames('vocab_multimedias', $exclude);
        $multimedia = new backup_nested_element('multimedia', ['id', $include]);

        // Backup frequencies.
        $frequencies = new backup_nested_element('frequencies');
        $exclude = ['id', 'wordid']; // TODO: fix 'corpusid'.
        $include = $this->get_fieldnames('vocab_frequencies', $exclude);
        $frequency = new backup_nested_element('frequency', ['id', $include]);

        // Backup pronunciations.
        $pronunciations = new backup_nested_element('pronunciations');
        $exclude = ['id', 'wordid']; // TODO: fix 'langid'.
        $include = $this->get_fieldnames('vocab_pronunciations', $exclude);
        $pronunciation = new backup_nested_element('pronunciation', ['id', $include]);

        // Backup synonyms.
        $synonyms = new backup_nested_element('synonyms');
        $exclude = ['id', 'wordid']; // TODO: fix synonymwordid.
        $include = $this->get_fieldnames('vocab_synonyms', $exclude);
        $synonym = new backup_nested_element('synonym', ['id', $include]);

        /*////////////////////////////////////////
        // XML nodes declaration - non-user data
        ///////////////////////////////////////*/

        // Backup vocab.
        $exclude = ['id', 'course'];
        $include = $this->get_fieldnames('vocab', $exclude);
        $vocab = new backup_nested_element('vocab', ['id', $include]);

        // Backup game_instances.
        $gameinstances = new backup_nested_element('gameinstances');
        $exclude = ['id', 'vocabid', 'gameid'];
        $include = $this->get_fieldnames('vocab_games', $exclude);
        $gameinstance = new backup_nested_element('gameinstance', ['id', $include]);

        // Backup word_instances.
        $wordinstances = new backup_nested_element('wordinstances');
        $exclude = ['id', 'wordid'];
        $include = $this->get_fieldnames('vocab_words', $exclude);
        $wordinstance = new backup_nested_element('wordinstance', ['id', $include]);

        /*////////////////////////////////////////
        // XML nodes declaration - user data
        ////////////////////////////////////////*/

        if ($userinfo) {

            // Backup game attempts.
            $gameattempts = new backup_nested_element('gameattempts');
            $exclude = ['id', 'gameinstanceid'];
            $include = $this->get_fieldnames('vocab_game_attempts', $exclude);
            $gameattempt = new backup_nested_element('gameattempt', ['id', $include]);

            // Backup word_usage.
            $wordusages = new backup_nested_element('wordusages');
            $exclude = ['id']; // TODO: fix 'gameattemptid', 'wordinstanceid'.
            $include = $this->get_fieldnames('vocab_word_usages', $exclude);
            $wordusage = new backup_nested_element('wordusage', ['id', $include]);

            // Backup word attempts.
            $wordattempts = new backup_nested_element('wordattempts');
            $exclude = ['id', 'wordusageid'];
            $include = $this->get_fieldnames('vocab_word_attempts', $exclude);
            $wordattempt = new backup_nested_element('wordattempt', ['id', $include]);
        }

        /*////////////////////////////////////////
        // Build the tree in the order needed for restore.
        ////////////////////////////////////////*/

        $dictionary = new backup_nested_element('dictionary');

        $dictionary->add_child($langs);
        $langs->add_child($lang);

        $dictionary->add_child($langnames);
        $langnames->add_child($langname);

        $dictionary->add_child($levels);
        $levels->add_child($level);

        $dictionary->add_child($corpuses);
        $corpuses->add_child($corpus);

        $dictionary->add_child($lemmas);
        $lemmas->add_child($lemmas);
        $lemma->add_child($words);
        $words->add_child($word);

        $word->add_child($antonyms);
        $antonyms->add_child($antonym);

        $word->add_child($definitions);
        $definitions->add_child($definition);

        $word->add_child($frequencies);
        $frequencies->add_child($frequency);

        $word->add_child($multimedias);
        $multimedias->add_child($multimedia);

        $word->add_child($pronunciations);
        $pronunciations->add_child($pronunciation);

        $word->add_child($synonyms);
        $synonyms->add_child($synonym);

        $vocab->add_child($dictionary);
        $vocab->add_child($gameinstances);
        $vocab->add_child($wordinstances);

        if ($userinfo) {

            // Backup vocab game_attempts.
            $vocab->add_child($gameattempts);
            $gameattempts->add_child($gameattempt);

            // Backup vocab word_attempts.
            $vocab->add_child($wordattempts);
            $wordattempts->add_child($wordattempt);

            // Backup vocab word_usages.
            $vocab->add_child($wordusages);
            $wordusages->add_child($wordusage);
        }

        /*////////////////////////////////////////
        // Data sources - non-user data.
        ////////////////////////////////////////*/

        $vocab->set_source_table('vocab', ['id' => backup::VAR_ACTIVITYID]);
        $game->set_source_table('vocab_games', ['vocabid' => backup::VAR_PARENTID]);
        $condition->set_source_table('vocab_conditions', ['taskid' => backup::VAR_PARENTID]);

        /*////////////////////////////////////////
        // Data sources - user related data.
        ////////////////////////////////////////*/

        if ($userinfo) {

            // Backup vocab grades.
            $vocabid = $this->get_setting_value(backup::VAR_ACTIVITYID);
            $params = ['parenttype' => ['sqlparam' => 0], 'parentid' => ['sqlparam' => $vocabid]];
            $vocabgrade->set_source_sql('SELECT * FROM {vocab_vocab_grades} WHERE parenttype = ? AND parentid = ?', $params);

            // Backup vocab attempts.
            $params = ['vocabid' => backup::VAR_PARENTID];
            $vocabattempt->set_source_table('vocab_vocab_attempts', $params);

            // Backup task scores.
            $params = ['taskid' => backup::VAR_PARENTID];
            $gamescore->set_source_table('vocab_game_scores', $params);

            // Backup task attempts.
            $params = ['taskid' => backup::VAR_PARENTID];
            $gameattempt->set_source_table('vocab_game_attempts', $params);

            // Backup questions.
            $params = ['taskid' => backup::VAR_PARENTID];
            $question->set_source_table('vocab_questions', $params);

            // Backup responses.
            $params = ['questionid' => backup::VAR_PARENTID];
            $response->set_source_table('vocab_responses', $params);

            // Backup strings.
            list($filter, $params) = $this->get_strings_sql();
            $string->set_source_sql("SELECT * FROM {vocab_strings} WHERE id $filter", $params);
        }

        /*////////////////////////////////////////
        // ID annotations (foreign keys on non-parent tables).
        ////////////////////////////////////////*/

        $vocab->annotate_ids('course_modules', 'entrycm');
        $vocab->annotate_ids('course_modules', 'exitcm');

        $condition->annotate_ids('vocab_conditions', 'conditiontaskid');
        $condition->annotate_ids('vocab_conditions', 'nexttaskid');

        if ($userinfo) {
            $condition->annotate_ids('groups', 'groupid');
            $vocabgrade->annotate_ids('user', 'userid');
            $vocabattempt->annotate_ids('user', 'userid');
            $gamescore->annotate_ids('user', 'userid');
            $gameattempt->annotate_ids('user', 'userid');
            $response->annotate_ids('vocab_game_attempts', 'attemptid');
        }

        /*////////////////////////////////////////
        // File annotations.
        ////////////////////////////////////////*/

        $vocab->annotate_files('mod_vocab', 'sourcefile', null);
        $vocab->annotate_files('mod_vocab', 'entrytext',  null);
        $vocab->annotate_files('mod_vocab', 'exittext',   null);

        // Return the root element (vocab), wrapped into standard activity structure.
        return $this->prepare_activity_structure($vocab);
    }

    /**
     * get_fieldnames
     *
     * @uses $DB
     * @param string $tablename the name of the Moodle table (without prefix)
     * @param array $excludedfieldnames these field names will be excluded
     * @return array of field names
     */
    protected function get_fieldnames($tablename, array $excludedfieldnames) {
        global $DB;
        $include = array_keys($DB->get_columns($tablename));
        return array_diff($include, $excludedfieldnames);
    }
}
