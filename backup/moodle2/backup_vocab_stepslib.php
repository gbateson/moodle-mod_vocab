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
 * backup/moodle2/backup_vocab_stepslib.php
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * backup_vocab_activity_structure_step
 * Defines the complete vocab structure for backup, with file and id annotations
 *
 * @copyright 2010 Gordon Bateson (gordon.bateson@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 * @package    mod
 * @subpackage vocab
 */

// Word dictionary tables
// $tables = array(
//     'mdl_vocab_antonyms',
//     'mdl_vocab_corpuses',
//     'mdl_vocab_definitions',
//     'mdl_vocab_frequencies',
//     'mdl_vocab_langnames',
//     'mdl_vocab_langs',
//     'mdl_vocab_lemmas',
//     'mdl_vocab_levelnames',
//     'mdl_vocab_levels',
//     'mdl_vocab_multimedia',
//     'mdl_vocab_pronunciations',
//     'mdl_vocab_synonyms',
//     'mdl_vocab_words',
// );

// Game tables
// $table = array(
//     'mdl_vocab_games',
// );

// Activity tables
// $tables = array(
//     'mdl_vocab',
//     'mdl_vocab_game_instances',
//     'mdl_vocab_word_instances',
//     'mdl_vocab_word_usages'
// );

// User data tables
// $tables = array(
//     'mdl_vocab_game_attempts',
//     'mdl_vocab_word_attempts',
// );

class backup_vocab_activity_structure_step extends backup_activity_structure_step {

    /** maximum number of words to retrieve in one DB query */
    const GET_WORDS_LIMIT = 100;

    /**
     * define_structure
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function define_structure()  {

        // are we including userinfo?
        $userinfo = $this->get_setting_value('userinfo');

        ////////////////////////////////////////////////////////////////////////
        // XML nodes declaration - dictionary data and games
        ////////////////////////////////////////////////////////////////////////

        // games
        $games = new backup_nested_element('games');
        $exclude = array('id');
        $include = $this->get_fieldnames('vocab_games', $exclude);
        $game = new backup_nested_element('game', array('id'), $include);

        // corpuses (used by "vocab_frequencies")
        $corpuses = new backup_nested_element('corpuses');
        $exclude = array('id');
        $include = $this->get_fieldnames('vocab_corpuses', $exclude);
        $corpus = new backup_nested_element('corpus', array('id'), $include);

        // langs
        $langs = new backup_nested_element('langs');
        $exclude = array('id');
        $include = $this->get_fieldnames('vocab_langs', $exclude);
        $lang = new backup_nested_element('lang', array('id'), $include);

        // levels
        $levels = new backup_nested_element('levels');
        $exclude = array('id');
        $include = $this->get_fieldnames('vocab_levels', $exclude);
        $level = new backup_nested_element('level', array('id'), $include);

        // levelnames
        $levelnames = new backup_nested_element('levelnames');
        $exclude = array('id', 'levelid', 'langid');
        $include = $this->get_fieldnames('vocab_levelnames', $exclude);
        $levelname = new backup_nested_element('levelname', array('id'), $include);

        // lemmas
        $lemmas = new backup_nested_element('lemmas');
        $exclude = array('id', 'langid');
        $include = $this->get_fieldnames('vocab_lemmas', $exclude);
        $lemma = new backup_nested_element('lemma', array('id'), $include);

        // words
        $words = new backup_nested_element('words');
        $exclude = array('id', 'lemmaid');
        $include = $this->get_fieldnames('vocab_words', $exclude);
        $word = new backup_nested_element('word', array('id'), $include);

        // antonyms
        $antonyms = new backup_nested_element('antonyms');
        $exclude = array('id', 'wordid'); // antonymwordid
        $include = $this->get_fieldnames('vocab_antonyms', $exclude);
        $antonym = new backup_nested_element('antonym', array('id'), $include);

        // definitions
        $definitions = new backup_nested_element('definitions');
        $exclude = array('id', 'wordid'); // 'langid', 'levelid'
        $include = $this->get_fieldnames('vocab_definitions', $exclude);
        $definition = new backup_nested_element('definition', array('id'), $include);

        // multimedias
        $multimedias = new backup_nested_element('multimedias');
        $exclude = array('id', 'wordid'); // 'langid', 'levelid'
        $include = $this->get_fieldnames('vocab_multimedias', $exclude);
        $multimedia = new backup_nested_element('multimedia', array('id'), $include);

        // frequencies
        $frequencies = new backup_nested_element('frequencies');
        $exclude = array('id', 'wordid'); // 'corpusid'
        $include = $this->get_fieldnames('vocab_frequencies', $exclude);
        $frequency = new backup_nested_element('frequency', array('id'), $include);

        // pronunciations
        $pronunciations = new backup_nested_element('pronunciations');
        $exclude = array('id', 'wordid'); // 'langid'
        $include = $this->get_fieldnames('vocab_pronunciations', $exclude);
        $pronunciation = new backup_nested_element('pronunciation', array('id'), $include);

        // synonyms
        $synonyms = new backup_nested_element('synonyms');
        $exclude = array('id', 'wordid'); // synonymwordid
        $include = $this->get_fieldnames('vocab_synonyms', $exclude);
        $synonym = new backup_nested_element('synonym', array('id'), $include);

        ////////////////////////////////////////////////////////////////////////
        // XML nodes declaration - non-user data
        ////////////////////////////////////////////////////////////////////////

        // vocab
        $exclude = array('id', 'course');
        $include = $this->get_fieldnames('vocab', $exclude);
        $vocab = new backup_nested_element('vocab', array('id'), $include);

        // game_instances
        $gameinstances = new backup_nested_element('gameinstances');
        $exclude = array('id', 'vocabid', 'gameid');
        $include = $this->get_fieldnames('vocab_games', $exclude);
        $gameinstance = new backup_nested_element('gameinstance', array('id'), $include);

        // word_instances
        $wordinstances = new backup_nested_element('wordinstances');
        $exclude = array('id', 'wordid');
        $include = $this->get_fieldnames('vocab_words', $exclude);
        $wordinstance = new backup_nested_element('wordinstance', array('id'), $include);

        ////////////////////////////////////////////////////////////////////////
        // XML nodes declaration - user data
        ////////////////////////////////////////////////////////////////////////

        if ($userinfo) {

            // game attempts
            $gameattempts = new backup_nested_element('gameattempts');
            $exclude = array('id', 'gameinstanceid');
            $include = $this->get_fieldnames('vocab_game_attempts', $exclude);
            $gameattempt = new backup_nested_element('gameattempt', array('id'), $include);

            // word_usage
            $wordusages = new backup_nested_element('wordusages');
            $exclude = array('id'); // 'gameattemptid', 'wordinstanceid'
            $include = $this->get_fieldnames('vocab_word_usages', $exclude);
            $wordusage = new backup_nested_element('wordusage', array('id'), $include);

            // word attempts
            $wordattempts = new backup_nested_element('wordattempts');
            $exclude = array('id', 'wordusageid');
            $include = $this->get_fieldnames('vocab_word_attempts', $exclude);
            $wordattempt = new backup_nested_element('wordattempt', array('id'), $include);
        }

        ////////////////////////////////////////////////////////////////////////
        // build the tree in the order needed for restore
        ////////////////////////////////////////////////////////////////////////

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

            // vocab game_attempts
            $vocab->add_child($gameattempts);
            $gameattempts->add_child($gameattempt);

            // vocab word_attempts
            $vocab->add_child($wordattempts);
            $wordattempts->add_child($wordattempt);

            // vocab word_usages
            $vocab->add_child($wordusages);
            $wordusages->add_child($wordusage);
        }

        ////////////////////////////////////////////////////////////////////////
        // data sources - non-user data
        ////////////////////////////////////////////////////////////////////////

        $vocab->set_source_table('vocab', array('id' => backup::VAR_ACTIVITYID));
        $game->set_source_table('vocab_games', array('vocabid' => backup::VAR_PARENTID));
        $condition->set_source_table('vocab_conditions', array('taskid' => backup::VAR_PARENTID));

        ////////////////////////////////////////////////////////////////////////
        // data sources - user related data
        ////////////////////////////////////////////////////////////////////////

        if ($userinfo) {

            // vocab grades
            $vocabid = $this->get_setting_value(backup::VAR_ACTIVITYID);
            $params = array('parenttype' => array('sqlparam' => 0), 'parentid' => array('sqlparam' => $vocabid));
            $vocabgrade->set_source_sql('SELECT * FROM {vocab_vocab_grades} WHERE parenttype = ? AND parentid = ?', $params);
            //$vocabgrade->set_source_sql("SELECT * FROM {vocab_vocab_grades} WHERE parenttype=0 AND parentid=$vocabid", array());

            // vocab attempts
            $params = array('vocabid' => backup::VAR_PARENTID);
            $vocabattempt->set_source_table('vocab_vocab_attempts', $params);

            // task scores
            $params = array('taskid' => backup::VAR_PARENTID);
            $gamescore->set_source_table('vocab_game_scores', $params);

            // task attempts
            $params = array('taskid' => backup::VAR_PARENTID);
            $gameattempt->set_source_table('vocab_game_attempts', $params);

            // questions
            $params = array('taskid' => backup::VAR_PARENTID);
            $question->set_source_table('vocab_questions', $params);

            // responses
            $params = array('questionid' => backup::VAR_PARENTID);
            $response->set_source_table('vocab_responses', $params);

            // strings
            list($filter, $params) = $this->get_strings_sql();
            $string->set_source_sql("SELECT * FROM {vocab_strings} WHERE id $filter", $params);
        }

        ////////////////////////////////////////////////////////////////////////
        // id annotations (foreign keys on non-parent tables)
        ////////////////////////////////////////////////////////////////////////

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

        ////////////////////////////////////////////////////////////////////////
        // file annotations
        ////////////////////////////////////////////////////////////////////////

        $vocab->annotate_files('mod_vocab', 'sourcefile', null);
        $vocab->annotate_files('mod_vocab', 'entrytext',  null);
        $vocab->annotate_files('mod_vocab', 'exittext',   null);

        // return the root element (vocab), wrapped into standard activity structure
        return $this->prepare_activity_structure($vocab);
    }

    /**
     * get_fieldnames
     *
     * @uses $DB
     * @param string $tablename the name of the Moodle table (without prefix)
     * @param array $excluded_fieldnames these field names will be excluded
     * @return array of field names
     */
    protected function get_fieldnames($tablename, array $excluded_fieldnames)   {
        global $DB;
        $include = array_keys($DB->get_columns($tablename));
        return array_diff($include, $excluded_fieldnames);
    }
}
