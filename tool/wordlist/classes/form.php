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

/**
 * form
 *
 * @package    vocabtool_wordlist
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var string the name of this plugin */
    public $subpluginname = 'vocabtool_wordlist';

    /**
     * definition
     *
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Get form data, if any.
        if (($data = data_submitted()) && confirm_sesskey()) {

            // Map element name => type of associated value.
            $names = [
                // Each of these buttons has a corresponding method
                // to take the required action.
                'wordlist' => PARAM_INT,
                'addwords' => PARAM_TEXT,
                'selectwords' => PARAM_INT,
                'importfile' => PARAM_INT,
                'exportfile' => PARAM_FILE,
            ];

            foreach ($names as $name => $type) {

                if ($name == 'wordlist') {
                    // We expect an "action" and a list of selected word ids.
                    $groupname = $name;
                    $actionname = $name.'action';
                    $buttonname = $name.'button';
                } else {
                    // We expect a button and a single parameter.
                    $groupname = $name.'elements';
                    $actionname = '';
                    $buttonname = $name.'button';
                }

                if ($groupname && empty($data->$groupname)) {
                    continue; // Form element was expected but missing.
                }
                if ($actionname && empty($data->{$groupname}[$actionname])) {
                    continue; // Action was expected but not selected.
                }
                if ($buttonname && empty($data->{$groupname}[$buttonname])) {
                    continue; // Button was not pressed.
                }

                if ($actionname) {
                    // Process the "Go" button with an action and selected words.
                    $action = $data->{$groupname}[$actionname];
                    unset($data->{$groupname}[$actionname]);
                    unset($data->{$groupname}[$buttonname]);

                    // Clean the incoming ids of selected words.
                    $wordids = [];
                    foreach ($data->$groupname as $wordid => $value) {
                        if ($wordid = clean_param($wordid, $type)) {
                            $wordids[] = $wordid;
                        }
                    }

                    // Taken action if everything seems OK.
                    if (count($wordids)) {
                        $this->$name($mform, $action, $wordids);
                    }
                } else {
                    // Process the "Add", "Select", and "Export" buttons.
                    $value = $data->{$groupname}[$name];
                    if ($value = clean_param($value, $type)) {
                        $this->$name($mform, $value);
                    }
                }
            }

        }

        $name = 'currentlist';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $this->add_wordlist($mform);

        $name = 'addwords';
        $groupname = $name.'elements';
        $label = $this->get_string($name);
        $elements = [
            $mform->createElement('textarea', $name, '', ['rows' => 2, 'cols' => 20]),
            $mform->createElement('submit', $name.'button', get_string('add'), ['class' => 'align-self-end']),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);

        $name = 'selectwords';
        $groupname = $name.'elements';
        $label = $this->get_string($name);
        $elements = [
            $mform->createElement('text', $name, $label, ['size' => 2]),
            $mform->createElement('submit', $name.'button', get_string('select')),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);
        $mform->setDefault($groupname.'['.$name.']', 10);
        $mform->setType($groupname.'['.$name.']', PARAM_INT);

        $this->add_importfile($mform);
        $this->add_exportfile($mform);

        $PAGE->requires->js_call_amd('vocabtool_wordlist/form', 'init');
    }

    /**
     * validation
     *
     * @uses $USER
     * @param stdClass $data submitted from the form
     * @param array $files
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

        return $errors;
    }

    /**
     * add_wordlist
     *
     * @param moodleform $mform representing the Moodle form
     *
     * TODO: Finish documenting this function
     */
    public function add_wordlist($mform) {
        global $OUTPUT, $PAGE;

        $actions = [
            'view' => $OUTPUT->pix_icon('t/preview', get_string('view')),
            'edit' => $OUTPUT->pix_icon('t/edit', get_string('edit')),
            'tags' => $OUTPUT->pix_icon('t/tags', get_string('tags')),
            'remove' => $OUTPUT->pix_icon('t/delete', get_string('delete')),
        ];
        $stats = [
            'usagecount' => 'success',
            'successrate' => 'warning',
            'masteryrate' => 'primary',
        ];

        $cssclass = (object)[
            'item' => 'd-inline-block pb-1 worditem',

            'index' => 'd-inline-block text-center wordindex',
            'text' => 'd-inline-block wordtext',

            'actionsheadings' => 'd-inline-block rounded mx-1 my-0 p-1 text-center wordactionsheadings',
            'actions' => 'd-inline-block border rounded mx-1 my-0 p-1 bg-light wordactions',
            'action' => 'd-inline-block border-light mx-0 my-0 pl-1 py-0 text-center wordaction',

            'statsheadings' => 'd-inline-block rounded mx-1 my-0 p-1 wordstatsheadings',
            'stats' => 'd-inline-block border rounded mx-1 my-0 p-1 bg-light wordstats',

            'statheading' => 'd-inline-block rounded mx-1 my-0 px-0 pb-1 text-center wordstatheading',
            'stat' => 'd-inline-block rounded mx-1 my-0 p-0 text-center wordstat',
        ];

        $name = 'wordlist';
        $elements = [];
        $wordindex = 0;

        $words = $this->get_vocab()->get_wordlist_words();
        if (empty($words)) {
            $vocab = $this->get_vocab();
            $msg = $this->get_string('nowordsfound');
            $msg = $OUTPUT->notification($msg, 'warning');
            $mform->addElement('html', $msg);
            return;
        }

        $words = [0 => 'selectall'] + $words;
        foreach ($words as $wordid => $word) {

            $isheading = ($wordid == 0);

            $worditem = '';

            // Start the "worditem".
            $params = ['class' => $cssclass->item];
            $worditem .= \html_writer::start_tag('div', $params);

            // Add the word index.
            $text = ($wordindex == 0 ? '' : "$wordindex.");
            $params = ['class' => $cssclass->index];
            $worditem .= \html_writer::tag('div', $text, $params);

            // Add the "wordtext".
            if ($isheading) {
                $text = '';
                $params = ['class' => $cssclass->text.' font-weight-bold'];
            } else {
                $text = $word;
                $params = ['class' => $cssclass->text];
            }
            $worditem .= \html_writer::tag('div', $text, $params);

            // Start "wordactions".
            if ($isheading) {
                $params = ['class' => $cssclass->actionsheadings];
            } else {
                $params = ['class' => $cssclass->actions];
            }
            $worditem .= \html_writer::start_tag('div', $params);

            if ($wordindex == 0) {
                $worditem .= \html_writer::tag('b', get_string('actions'));
            } else {
                foreach ($actions as $action => $icon) {
                    $url = $PAGE->url;
                    $url->params([
                        'wordid' => $wordid,
                        'action' => $action,
                        'sesskey' => sesskey(),
                    ]);
                    $link = \html_writer::link($url, $icon);
                    $worditem .= \html_writer::tag('div', $link, ['class' => $cssclass->action]);
                }
            }

            // End "wordactions".
            $worditem .= \html_writer::end_tag('div');

            // Start "wordstats".
            if ($isheading) {
                $params = ['class' => $cssclass->statsheadings];
            } else {
                $params = ['class' => $cssclass->stats];
            }
            $worditem .= \html_writer::start_tag('div', $params);

            foreach ($stats as $stat => $bg) {
                $israte = (substr($stat, -4) == 'rate');

                // Start "wordstat".
                $class = ($isheading ? $cssclass->statheading : $cssclass->stat);
                $width = ($israte ? '3.0em;' : '2.6em;');
                $params = ['class' => "$class bg-$bg text-light", 'style' => "width: $width"];
                $worditem .= \html_writer::start_tag('div', $params);

                // Add stat text.
                if ($isheading) {
                    $worditem .= \html_writer::tag('small', $this->get_string($stat));
                } else {
                    $worditem .= rand(0, 100).($israte ? '%' : '');
                }
                // End "wordstat".
                $worditem .= \html_writer::end_tag('div');
            }

            // End "wordstats".
            $worditem .= \html_writer::end_tag('div');

            // End "worditem".
            $worditem .= \html_writer::end_tag('div');

            if ($isheading) {
                $params = [
                    'data-selectall' => get_string('selectall'),
                    'data-deselectall' => get_string('deselectall'),
                    'class' => '', // Will be made visible later by Javascript.
                ];
            } else {
                $params = [];
            }

            $elements[] = $mform->createElement('checkbox', $wordid, $worditem, '', $params);
            $wordindex++;
        }

        // Add "with selected" menu and "Go" button.
        $options = [
            '' => $this->get_string('withselected'),
            'remove' => $this->get_vocab()->get_string('remove'),
            'export' => $this->get_vocab()->get_string('export'),
            'getquestions' => $this->get_string('getquestions'),
            'getsamplesentences' => $this->get_string('getsamplesentences'),
        ];
        $elements[] = $mform->createElement('select', $name.'action', '', $options);
        $elements[] = $mform->createElement('submit', $name.'button', get_string('go'));

        $mform->addGroup($elements, $name, '', '');
    }

    /**
     * wordlist
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $action (remove, export, getquestions, getsamplesentences)
     * @param array $wordids
     *
     * TODO: Finish documenting this function
     */
    public function wordlist($mform, $action, $wordids) {
        global $DB, $OUTPUT;

        switch ($action) {
            case 'remove':

                // Build SQL to select word instances with usage count.
                $select = 'vwi.*, vw.word, COUNT(vwu.id) AS usagecount';
                $from = '{vocab_word_instances} vwi '.
                        'JOIN {vocab_words} vw ON vwi.wordid = vw.id '.
                        'LEFT JOIN {vocab_word_usages} vwu ON vwi.id = vwu.wordinstanceid';

                list($where, $params) = $DB->get_in_or_equal($wordids);
                $where = "vwi.vocabid = ? AND vwi.wordid $where";
                $params = array_merge([$this->get_vocab()->id], $params);

                $sql = "SELECT $select FROM $from WHERE $where GROUP BY vwi.id";

                // Initialize the array of messages to report back to user.
                $msg = '';

                // Fetch records from "vocab_word_instances" table.
                if ($records = $DB->get_records_sql($sql, $params)) {
                    $msg = [];
                    foreach ($records as $id => $wordinstance) {
                        if (empty($wordinstance->usagecount)) {
                            // This word instance has not been used, so we can delete it.
                            $DB->delete_records('vocab_word_instances', ['id' => $id]);
                        } else {
                            // This word instance has already been used by some games in
                            // this vocabulary activity, so we cannot simply remove it.
                            // Therefore, in order to keep its scores but at the same
                            // time prevent its use in the future, we disable it.
                            $DB->set_field('vocab_word_instances', 'enabled', 0, ['id' => $id]);
                        }
                        $msg[] = $wordinstance->word;
                    }
                    if (empty($msg)) {
                        $msg = ''; // Shouldn't happen !!
                    } else {
                        $msg = \html_writer::alist($msg);
                        $msg = $this->get_string('wordsremovedfromlist', $msg);
                        $msg = $OUTPUT->notification($msg, 'info');
                    }
                } else {
                    // The selected words were not found in this wordlist.
                    // This is unusual, but may happen during development.
                    $msg = $this->get_string('selectedwordsnotfound');
                    $msg = $OUTPUT->notification($msg, 'warning');
                }

                // Report any messages to the user.
                if ($msg) {
                    $mform->addElement('html', $msg);
                }
                break;

            case 'export':
                break;

            case 'getquestions':
                break;

            case 'getsamplesentences':
                break;
        }
    }

    /**
     * addwords
     *
     * @param moodleform $mform representing the Moodle form
     * @param xxx $newwords
     *
     * TODO: Finish documenting this function
     */
    public function addwords($mform, $newwords) {
        global $OUTPUT;

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

        // Note: we could set lang from form, either same lang for
        // all words, or even different lang for each word.
        $langcode = 'en';

        // Initialize array to store lists of added/found words.
        $msg = (object)[
            'added' => [],
            'found' => [],
        ];

        // Main loop to add new words.
        foreach ($newwords as $newword) {

            if (in_array($newword, $words)) {
                $msg->found[] = $newword;
            } else {
                $lemma = $this->get_lemma($newword, $langcode);
                $langid = $this->get_record_id('vocab_langs', ['langcode' => $langcode]);
                $lemmaid = $this->get_record_id('vocab_lemmas', ['langid' => $langid, 'lemma' => $lemma]);
                $wordid = $this->get_record_id('vocab_words', ['lemmaid' => $lemmaid, 'word' => $newword]);
                $id = $this->get_record_id('vocab_word_instances', ['vocabid' => $vocabid, 'wordid' => $wordid]);
                $words[$wordid] = $newword;
                $msg->added[] = $newword;
            }
        }
        if (empty($msg->added)) {
            $msg->added = ''; // Unexpected - shouldn't happen !!
        } else {
            $msg->added = \html_writer::alist($msg->added);
            $msg->added = $this->get_string('wordsaddedtolist', $msg->added);
            $msg->added = $OUTPUT->notification($msg->added, 'info');
        }
        if (empty($msg->found)) {
            $msg->found = '';
        } else {
            // We don't expect someone to add words that are already in the list but
            // it could happen and is allowed because it doesn't cause any problems.
            $msg->found = \html_writer::alist($msg->found);
            $msg->found = $this->get_string('wordsfoundinlist', $msg->found);
            $msg->found = $OUTPUT->notification($msg->found, 'warning');
        }
        if ($msg = implode('', (array)$msg)) {
            $mform->addElement('html', $msg);
        }
    }

    /**
     * get_lemma
     *
     * @uses $DB
     * @param xxx $word
     * @param xxx $langcode
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_lemma($word, $langcode) {
        global $DB;
        $select = 'lmm.id, lmm.lemma';
        $from = '{vocab_words} wrd, {vocab_lemmas} lmm, {vocab_langs} lng';
        $where = 'wrd.word = ? AND wrd.lemmaid = lmm.id AND lmm.langid = lng.id AND lng.langcode = ?';
        $params = [$word, $langcode];
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
     *
     * TODO: Finish documenting this function
     */
    public function selectwords($mform, $count) {
        global $DB, $OUTPUT;

        // Limit $count to sensible values.
        $count = min(20, max(1, $count));

        // Get ids of all words in all Vocab acitvities in this course.
        $select = 'vwi.id, vwi.wordid';
        $from = '{vocab_word_instances} vwi, {vocab} v';
        $where = 'vwi.vocabid = v.id AND v.course = ?';
        $params = [$this->get_vocab()->course->id];
        $words = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where", $params);

        // Build SQL to select a random word that is not already used in a Vocab activity in this course.
        $select = 'vw.id, vw.word';
        $from = '{vocab_words} vw, {vocab_lemmas} vl';
        if (count($words)) {
            // Get SQL for "<>" or "NOT IN (...)".
            list($where, $params) = $DB->get_in_or_equal($words, SQL_PARAMS_QM, 'param', false);
            $where = "vw.id $where";
        } else {
            $where = 'vw.id > 0';
            $params = [];
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
            case 'postgres':
                $order = 'RANDOM()';
                break;
            default:
                // MySQL ... and anything else.
                $order = 'RAND()';
        }
        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
        $words = $DB->get_records_sql_menu($sql, $params, 0, $count);

        if (empty($words)) {
            // No words could be selected - shouldn't happen !!
            $msg = $this->get_string('nowordsfound');
            $msg = $OUTPUT->notification($msg, 'warning');
            $mform->addElement('html', $msg);
        } else {
            asort($words);
            foreach ($words as $wordid => $word) {
                // Fetch/create a word instance id for this word.
                $params = [
                    'vocabid' => $this->get_vocab()->id,
                    'wordid' => $wordid,
                ];
                $id = $this->get_record_id('vocab_word_instances', $params);
                $DB->set_field('vocab_word_instances', 'enabled', 1, $params);
            }
            $msg = \html_writer::alist($words);
            $msg = $this->get_string('wordsaddedtolist', $msg);
            $msg = $OUTPUT->notification($msg, 'info');
            $mform->addElement('html', $msg);
        }
    }

    /**
     * importfile
     *
     * @param moodleform $mform representing the Moodle form
     * @param xxx $fileid
     *
     * TODO: Finish documenting this function
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
     *
     * TODO: Finish documenting this function
     */
    public function exportfile($mform, $filename) {
        $msg = \html_writer::tag('h4', 'exportfile: '.$filename);
        $mform->addElement('html', $msg);
    }
}
