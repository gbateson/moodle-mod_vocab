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
 * tool/wordlist/classes/form.php
 *
 * @package    vocabtool_wordlist
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_wordlist;

defined('MOODLE_INTERNAL') || die;

// Fetch the parent class.
require_once($CFG->dirroot.'/mod/vocab/classes/toolform.php');

/**
 * form
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    // cache the plugin name
    public $tool = 'vocabtool_wordlist';

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {
        global $PAGE, $_POST;

        $mform = $this->_form;
        $this->set_form_id($mform);

        if (($data = data_submitted()) && confirm_sesskey()) {

            // element name => type of associated value
            $names = array('addwords' => PARAM_TEXT,
                           'selectwords' => PARAM_INT,
                           'importfile' => PARAM_INT,
                           'exportfile' => PARAM_FILE);

            foreach ($names as $name => $type) {

                $groupname = $name.'elements';
                $buttonname = $name.'button';

                if (empty($data->$groupname)) {
                    continue; // No form element - shouldn't happen !!
                }
                if (empty($data->$groupname[$buttonname])) {
                    continue; // Button was not pressed, so ignore.
                }
                if (empty($data->$groupname[$name])) {
                    continue; // No data - shouldn't happen !!
                }
                $value = $data->$groupname[$name];
                if ($value = clean_param($value, $type)) {
                    $this->$name($mform, $value);
                }
            }
        }

        $this->add_heading($mform, 'currentlist', $this->tool, true);

        $name = 'currentlist';
        $mform->addElement('html', $this->get_wordlist());

        $name = 'addwords';
        $groupname = $name.'elements';
        $label = get_string($name, $this->tool);
        $options = array('rows' => 2, 'cols' => 20);
        $elements = array(
            $mform->createElement('textarea', $name, '', $options),
            $mform->createElement('submit', $name.'button', get_string('add', $this->tool))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $name = 'selectwords';
        $groupname = $name.'elements';
        $label = get_string($name, $this->tool);
        $elements = array(
            $mform->createElement('text', $name, $label, array('size' => 2)),
            $mform->createElement('submit', $name.'button', get_string('select', $this->tool))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);
        $mform->setDefault($groupname.'['.$name.']', 10);
        $mform->setType($groupname.'['.$name.']', PARAM_INT);

        $this->add_heading($mform, 'import', $this->tool, false);

        $name = 'importfile';
        $groupname = $name.'elements';
        $label = get_string($name, $this->tool);
        $options = array('accepted_types' => array('.txt', '.xml')); // '.csv', '.xlsx', '.xls', '.ods'
        $elements = array(
            $mform->createElement('filepicker', $name, $label, '', $options),
            $mform->createElement('submit', $name.'button', get_string('import', $this->tool))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $this->add_heading($mform, 'export', $this->tool, false);

        $filename = $this->get_vocab()->name;
        $filename = preg_replace('/[ \.]+/', '_', $filename).'.xml';

        $name = 'exportfile';
        $groupname = $name.'elements';
        $label = get_string($name, $this->tool);
        $elements = array(
            $mform->createElement('text', $name, $label, '', array('size' => 20)),
            $mform->createElement('submit', $name.'button', get_string('export', $this->tool))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);
        $mform->setDefault($groupname.'['.$name.']', $filename);
        $mform->setType($groupname.'['.$name.']', PARAM_FILE);

        $PAGE->requires->js_call_amd('vocabtool_wordlist/form', 'init');
    }

    /**
     * validation
     */
    function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

        return $errors;
    }

    /**
     * validation
     */
    public function get_wordlist() {
        global $OUTPUT;
        $list = array();
        $words = $this->get_vocab()->get_wordlist_words();
        if (count($words)) {
            $params = array('class' => 'rounded border bg-light py-2 pr-3');
            return \html_writer::alist(array_values($words), $params, 'ol');
        } else {
            $msg = $this->get_vocab()->get_string('nowordsfound');
            return $OUTPUT->notification($msg, 'info');
        }
    }

    /**
     * addwords
     *
     * @param moodleform $mform representing the Moodle form
     * @param xxx $newwords
     * @todo Finish documenting this function
     */
    public function addwords($mform, $newwords) {

        // Get list seperator for the current language,
        // e.g. "," (comma) for the "en" language pack.
        $listsep = get_string('listsep', 'langconfig');
        $newline = "\n";

        $newwords = str_replace($listsep, $newline, $newwords);
        $newwords = explode($newline, $newwords);
        $newwords = array_map('trim', $newwords);
        $newwords = array_filter($newwords);

        // Cache the current list of words.
        $words = $this->get_vocab()->get_wordlist_words();

        // Cache the vocabid.
        $vocabid = $this->get_vocab()->id;

        // ToDo: set lang from form, either same lang for
        // all words, or even different lang for each word.
        $langcode = 'en';

        $msg = array();
        foreach ($newwords as $newword) {

            if (in_array($newword, $words)) {
                $msg[] = get_string('wordexistsinlist', $this->tool, $newword);
            } else {
                $lemma = $this->get_lemma($newword, $langcode);
                $langid = $this->get_record_id('vocab_langs', array('langcode' => $langcode));
                $lemmaid = $this->get_record_id('vocab_lemmas', array('langid' => $langid, 'lemma' => $lemma));
                $wordid = $this->get_record_id('vocab_words', array('lemmaid' => $lemmaid, 'word' => $newword));
                $id = $this->get_record_id('vocab_word_instances', array('vocabid' => $vocabid, 'wordid' => $wordid));
                $msg[] = get_string('wordaddedtolist', $this->tool, $newword);
                $words[$wordid] = $newword;
            }
        }
        if (count($msg)) {
            $mform->addElement('html', \html_writer::alist($msg));
        }

        $this->unset_element('addwordselements');
    }

    /**
     * get_lemma
     *
     * @uses $DB
     * @param xxx $word
     * @param xxx $langcode
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_lemma($word, $langcode) {
        global $DB;
        $select = 'lmm.id, lmm.lemma';
        $from = '{vocab_words} wrd, {vocab_lemmas} lmm, {vocab_langs} lng';
        $where = 'wrd.word = ? AND wrd.lemmaid = lmm.id AND lmm.langid = lng.id AND lng.langcode = ?';
        $params = array($word, $langcode);
        if ($lemmas = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
            // Lemma found in $DB.
            return reset($lemmas)->lemma;
        } else {
            // ToDo: look up this word's lemma on the internet.
            return $word;
        }

    }

    /**
     * selectwords
     *
     * @uses $DB
     * @param moodleform $mform representing the Moodle form
     * @param xxx $count
     * @return xxx
     * @todo Finish documenting this function
     */
    public function selectwords($mform, $count) {
        global $DB, $OUTPUT;

        // Limit $count to sensible values.
        $count = min(20, max(1, $count));

        // Get ids of all words in all Vocab acitvities in this course.
        $select = 'vwi.id, vwi.wordid';
        $from = '{vocab_word_instances} vwi, {vocab} v';
        $where = 'vwi.vocabid = v.id AND v.course = ?';
        $params = array($this->get_vocab()->course->id);
        $words = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where", $params);

        // Build SQL to select a random word that is not already used in a Vocab activity in this course.
        $select = 'vw.id, vw.word';
        $from = '{vocab_words} vw, {vocab_lemmas} vl';
        if (count($words)) {
            // Get SQL for "<>" or "NOT IN (...)"
            list($where, $params) = $DB->get_in_or_equal($words, SQL_PARAMS_QM, 'param', false);
            $where = "vw.id $where";
        } else {
            $where = 'vw.id > 0';
            $params = array();
        }
        $where .= ' AND vw.lemmaid = vl.id AND vw.word = vl.lemma';

        // DB-specific SQL for MSSQL and Oracle.
        switch ($DB->get_dbfamily()) {
            case 'mssql':
                $select = "TOP $count $select";
                $order = 'NEWID()';
                break;
            case 'oracle':
                $order = "DBMS_RANDOM.value FETCH NEXT $count ROWS ONLY";
                break;
            default:
                // MySQL, PostgreSQL ... and anything else.
                $order = 'RAND()';
        }
        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
        if ($words = $DB->get_records_sql_menu($sql, $params, 0, $count)) {
            asort($words);
            foreach (array_keys($words) as $wordid) {
                // Fetch/create a word instance id for this word.
                $params = array('vocabid' => $this->get_vocab()->id,
                                'wordid' => $wordid);
                $wordinstanceid = $this->get_record_id('vocab_word_instances', $params);
            }
            $params = array('class' => 'rounded border bg-light py-2 pr-3');
            $msg = \html_writer::alist(array_values($words), $params, 'ol');
            return $mform->addElement('html', $msg);
        } else {
            $msg = $this->get_vocab()->get_string('nowordsfound');
            $msg = $OUTPUT->notification($msg, 'info');
            return $mform->addElement('html', $msg);
        }
    }

    /**
     * importfile
     *
     * @param moodleform $mform representing the Moodle form
     * @param xxx $fileid
     * @todo Finish documenting this function
     */
    public function importfile($mform, $fileid) {
        $msg = \html_writer::tag('h4', 'importfile: '.$fileid);
        $mform->addElement('html', $msg);
    }

    /**
     * exportfile
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $filename
     * @todo Finish documenting this function
     */
    public function exportfile($mform, $filename) {
        $msg = \html_writer::tag('h4', 'exportfile: '.$filename);
        $mform->addElement('html', $msg);
    }

    /**
     * unset_element
     *
     * @param string $name
     * @todo Finish documenting this function
     */
    public function unset_element($name) {
        if (isset($_GET[$name])) {
            unset($_GET[$name]);
        }
        if (isset($_POST[$name])) {
            unset($_POST[$name]);
        }
    }
}
