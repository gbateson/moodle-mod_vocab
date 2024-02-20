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
 * tool/questionbank/classes/form.php
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_questionbank;

/**
 * form
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var string the name of this plugin */
    public $subpluginname = 'vocabtool_questionbank';

    /** @var string internal value to represent creating no question subcategories */
    const SUBCAT_NONE = 'none';

    /** @var string internal value to represent the creation of a "single" question subcategory */
    const SUBCAT_SINGLE = 'single';

    /** @var string internal value to represent the "automatic" creation of question subcategories */
    const SUBCAT_AUTOMATIC = 'automatic';

    /**
     * definition
     *
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform = $this->_form;
        $this->set_form_id($mform);

        if (($data = data_submitted()) && confirm_sesskey()) {
            $this->generate_questions($mform, $data);
        }

        $name = 'wordlist';
        $this->add_heading($mform, $name, 'mod_vocab', true);

        $words = $this->get_vocab()->get_wordlist_words();
        if (empty($words)) {
            $msg = $this->get_string('nowordsfound');
            $msg = $OUTPUT->notification($msg, 'warning');
            $mform->addElement('html', $msg);
            return;
        }

        $name = 'selectedwords';
        $label = $this->get_string($name);

        $elements = [];

        $params = [
            'data-selectall' => get_string('selectall'),
            'data-deselectall' => get_string('deselectall'),
            'class' => 'd-none',
        ];
        $elements[] = $mform->createElement('checkbox', 'selectall', get_string('selectall'), '', $params);

        foreach ($words as $id => $word) {
            $elements[] = $mform->createElement('checkbox', $id, $word);
        }
        $br = \html_writer::tag('span', '', ['style' => 'width: 100%!important']);
        $spacer = \html_writer::tag('span', '', ['style' => 'width: 24px!important']);
        $mform->addGroup($elements, $name, $label, $br);
        $mform->addHelpButton($name, $name, $this->subpluginname);

        $this->add_heading($mform, 'questionsettings', $this->subpluginname, true);

        $name = 'questiontypes';
        $options = self::get_question_types();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'multichoice', 'multiple');

        $name = 'questionlevels';
        $options = self::get_question_levels();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'A2', 'multiple');

        $name = 'questioncount';
        $this->add_field_text($mform, $name, PARAM_INT, 5, 2);

        $name = 'categorysettings';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $this->add_parentcategory($mform);
        $this->add_subcategories($mform);

        // Use "generatequestions" as the label for the submit button.
        $label = $this->get_string('generatequestions');
        $this->add_action_buttons(true, $label);

        $PAGE->requires->js_call_amd('vocabtool_questionbank/form', 'init');
    }

    /**
     * get_question_types
     *
     * @return array $types of question types for which we can generate questions.
     *
     * TODO: Finish documenting this function
     */
    public static function get_question_types() {
        // ToDo: Could include ordering, essayautograde, speakautograde and sassessment.
        $include = ['match', 'multianswer', 'multichoice', 'shortanswer', 'truefalse'];
        $types = \core_component::get_plugin_list('qtype');
        foreach ($types as $name => $dir) {
            if (in_array($name, $include)) {
                $types[$name] = get_string('pluginname', "qtype_$name");
            } else {
                unset($types[$name]);
            }
        }
        asort($types); // Sort alphabetically (maintain key association).
        return $types;
    }

    /**
     * get_question_type_text
     *
     * @param string $qtype a question type e.g. "multichoice", "truefalse"
     * @return string human readable text version of the given $qtype
     */
    public static function get_question_type_text($qtype) {
        $qtypes = self::get_question_types();
        if (array_key_exists($qtype, $qtypes)) {
            return $qtypes[$qtype];
        } else {
            // Illegal value - shouldn't happen !!
            return $qtype;
        }
    }

    /**
     * get_question_levels
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public static function get_question_levels() {
        // ToDo: get these levels from the vocab_levelnames table.
        $plugin = 'vocabtool_questionbank';
        return [
            'A1' => get_string('cefr_a1_description', $plugin),
            'A2' => get_string('cefr_a2_description', $plugin),
            'B1' => get_string('cefr_b1_description', $plugin),
            'B2' => get_string('cefr_b2_description', $plugin),
            'C1' => get_string('cefr_c1_description', $plugin),
            'C2' => get_string('cefr_c2_description', $plugin),
        ];
    }

    /**
     * get_question_level_text
     *
     * @param string $qlevel a question level e.g. "multichoice", "truefalse"
     * @return string human readable text version of the given $qlevel
     */
    public static function get_question_level_text($qlevel) {
        $qlevels = self::get_question_levels();
        if (array_key_exists($qlevel, $qlevels)) {
            return $qlevels[$qlevel];
        } else {
            // Illegal value - shouldn't happen !!
            return $qlevel;
        }
    }

    /**
     * add_parentcategory
     *
     * @param moodleform $mform representing the Moodle form
     *
     * TODO: Finish documenting this function
     */
    public function add_parentcategory($mform) {

        $defaultid = 0;

        // Get the course context.
        $courseid = $this->get_vocab()->course->id;
        $context = \context_course::instance($courseid);

        // Get the name of the default question category for this course.
        $defaultname = $context->get_context_name(false, true);
        $defaultname = get_string('defaultfor', 'question', $defaultname);
        $defaultname = shorten_text($defaultname, 255);

        // Fetch the list of question categories in this course.
        $categories = $this->get_question_categories();

        // Extract the id of the default question category in this course.
        $defaultid = array_search($defaultname, $categories);
        if ($defaultid === false) {
            $defaultid = 0; // Shouldn't happen !!
        }

        $name = 'parentcategory';
        $label = $this->get_string($name);
        $groupname = $name.'elements';

        $elements = [
            $mform->createElement('select', $name, '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories()),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);

        $mform->setType($groupname.'['.$name.']', PARAM_TEXT);
        $mform->setDefault($groupname.'['.$name.']', $defaultid);
    }

    /**
     * link_to_managequestioncategories
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function link_to_managequestioncategories() {
        $link = '/question/bank/managecategories/category.php';
        $params = ['courseid' => $this->get_vocab()->course->id];
        $link = new \moodle_url($link, $params);

        $text = $this->get_string('managequestioncategories');
        $params = ['onclick' => "this.target='VOCAB'"];
        $link = \html_writer::link($link, $text, $params);

        $params = ['class' => 'w-100 pl-1'];
        return \html_writer::tag('small', $link, $params);
    }

    /**
     * get_question_categories
     *
     * @uses $CFG
     * @uses $DB
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_question_categories() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/questionlib.php');

        $courseid = $this->get_vocab()->course->id;
        $coursecontext = \context_course::instance($courseid);
        $coursecategory = question_get_top_category($coursecontext->id, true); // Create if necessary.

        $categories = question_categorylist($coursecategory->id);
        list($select, $params) = $DB->get_in_or_equal($categories);
        if ($categories = $DB->get_records_select_menu('question_categories', "id $select", $params, 'sortorder', 'id, name')) {

            if ($coursecategory->name == 'top') {
                $name = $coursecontext->get_context_name(false, false, true);
                $name = get_string('topfor', 'question', $name);
                if (array_key_exists($coursecategory->id, $categories)) {
                    $categories[$coursecategory->id] = $name;
                }
            }
            return $categories;
        } else {
            return [];
        }
    }

    /**
     * add_subcategories
     *
     * @param moodleform $mform representing the Moodle form
     *
     * TODO: Finish documenting this function
     */
    public function add_subcategories($mform) {
        $name = 'subcategories';
        $label = $this->get_string($name);

        $groupname = $name.'elements';
        $cattype = $groupname.'[cattype]';
        $catname = $groupname.'[catname]';

        $options = $this->get_subcategory_types();
        $elements = [
            $mform->createElement('select', 'cattype', '', $options),
            $mform->createElement('text', 'catname', '', ['size' => 20]),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);

        $mform->setType($cattype, PARAM_ALPHA);
        $mform->setDefault($cattype, self::SUBCAT_AUTOMATIC);

        $mform->setType($catname, PARAM_TEXT);
        $mform->setDefault($catname, '');
        $mform->disabledIf($catname, $cattype, 'neq', 'single');
    }

    /**
     * Get subcategory options
     *
     * @return array of subcategory options.
     */
    public function get_subcategory_types() {
        return [
            self::SUBCAT_NONE => get_string('none'),
            self::SUBCAT_SINGLE => $this->get_string('singlesubcategory'),
            self::SUBCAT_AUTOMATIC => $this->get_string('automaticsubcategories'),
        ];
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
        $errors = parent::validation($data, $files);

        $names = ['selectedwords', 'questiontypes',
                  'questionlevels', 'questioncount',
                  'parentcategoryelements', 'subcategorieselements'];

        foreach ($names as $name) {
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('empty'.$name);
            }
        }

        return $errors;
    }

    /**
     * generate_questions
     *
     * @uses $DB
     * @uses $OUTPUT
     * @uses $USER
     * @param moodleform $mform representing the Moodle form
     * @param object $data submitted from the Moodle form
     * @return void, but may add records to the "task_adhoc" table in the Moodle database.
     *
     * TODO: Finish documenting this function
     */
    public function generate_questions($mform, $data) {
        global $DB, $OUTPUT, $USER;

        // Intialize arrays for messages to report success or failure
        // when setting up adhoc tasks to generate questions.
        $success = [];
        $failure = [];

        $words = false;
        $qtypes = false;
        $qlevels = false;
        $qcount = $data->questioncount;

        $parentcatid = 0;
        $parentcatname = '';

        $subcattype = 0;
        $subcatname = '';

        // Cache the vocabid.
        $vocabid = $this->get_vocab()->id;

        // Get sensible value for number of tries.
        $mintries = 1;
        $maxtries = 10;
        $name = 'maxtries';
        if (isset($data->$name) && is_numeric($data->$name)) {
            $maxtries = min($maxtries, max($mintries, $data->$name));
        }

        // Get question format (GIFT or XML)
        $name = 'qformat';
        $qformat = (empty($data->$name) ? 'gift' : $data->$name);

        // Get config id of AI assistant.
        $name = 'aiid';
        $aiid = (empty($data->$name) ? 0 : $data->$name);

        // Get config id of prompt.
        $name = 'promptid';
        $promptid = (empty($data->$name) ? 0 : $data->$name);

        if (property_exists($data, 'selectedwords')) {
            unset($data->selectedwords['selectall']);

            $select = 'vwi.wordid, vw.word';
            $from = '{vocab_word_instances} vwi, {vocab_words} vw';
            list($where, $params) = $DB->get_in_or_equal(array_keys($data->selectedwords));
            $where = 'vwi.vocabid = ? AND vwi.wordid = vw.id AND vw.id '.$where;
            $params = array_merge([$vocabid], $params);
            $order = 'vwi.sortorder, vw.word';

            $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
            $words = $DB->get_records_sql_menu($sql, $params);

            unset($data->selectedwords);
        }

        if (property_exists($data, 'questiontypes')) {
            $qtypes = self::get_question_types();
            foreach ($qtypes as $name => $text) {
                if (! in_array($name, $data->questiontypes)) {
                    unset($qtypes[$name]);
                }
            }
            unset($data->questiontypes);
        }

        if (property_exists($data, 'questionlevels') && is_array($data->questionlevels)) {
            $qlevels = self::get_question_levels();
            foreach ($qlevels as $name => $text) {
                if (! in_array($name, $data->questionlevels)) {
                    unset($qlevels[$name]);
                }
            }
            unset($data->questionlevels);
        }

        if (empty($words) || empty($qtypes) || empty($qlevels)) {
            return;
        }

        $name = 'parentcategory';
        $groupname = $name.'elements';
        if (property_exists($data, $groupname)) {
            if (array_key_exists($name, $data->$groupname)) {
                $parentcatid = $data->{$groupname}[$name];
                $categories = $this->get_question_categories();
                if (! array_key_exists($parentcatid, $categories)) {
                    $parentcatid = key($categories);
                }
                unset($categories);
            }
            unset($data->$groupname);
        }

        $groupname = 'subcategorieselements';
        if (property_exists($data, $groupname)) {
            $name = 'cattype';
            if (array_key_exists($name, $data->$groupname)) {
                $subcattype = $data->{$groupname}[$name];
                $types = $this->get_subcategory_types();
                if (! array_key_exists($subcattype, $types)) {
                    $subcattype = self::SUBCAT_AUTOMATIC;
                }
                unset($types);
            }
            $name = 'catname';
            if (array_key_exists($name, $data->$groupname)) {
                $subcatname = $data->{$groupname}[$name];
            }
            // Sanity check on subcat type and name.
            if ($subcattype == self::SUBCAT_SINGLE && $subcatname == '') {
                // Name is missing, so switch type to automatic.
                $subcattype = self::SUBCAT_AUTOMATIC;
            } else if ($subcatname) {
                // Name given but not needed, so remove it.
                $subcatname = '';
            }
            unset($data->$groupname);
        }

        // Initialize arguments for "get_string()" used to report
        // the success or failure of setting up the adhoc task.
        $a = (object)[
            'word' => '',
            'type' => '',
            'level' => '',
            'count' => $qcount,
        ];

        // Set up one task for each level of
        // each question type for each word.
        foreach ($words as $wordid => $word) {
            $a->word = $word;

            foreach ($qtypes as $qtype => $qtypetext) {
                $a->type = $qtypes[$qtype];

                foreach ($qlevels as $qlevel => $qlevelname) {
                    $a->level = $qlevels[$qlevel];

                    // Create the adhoc task.
                    if ($task = new \vocabtool_questionbank\task\questions()) {
                        $task->set_userid($USER->id);
                        $task->set_custom_data([
                            'uniqid' => uniqid($USER->id, true),
                            'vocabid' => $vocabid,
                            'wordid' => $wordid,
                            'qtype' => $qtype,
                            'qlevel' => $qlevel,
                            'qcount' => $qcount,
                            'qformat' => $qformat,
                            'maxtries' => $maxtries,
                            'parentcatid' => $parentcatid,
                            'subcattype' => $subcattype,
                            'subcatname' => $subcatname,
                            'promptid' => $promptid,
                            'aiid' => $aiid,
                        ]);
                        // If successful, the "queue_adhoc_task()" method
                        // returns a record id from the "task_adhoc" table.
                        if (\core\task\manager::queue_adhoc_task($task)) {
                            $success[] = $this->get_string('taskgeneratequestions', $a);
                        } else {
                            $failure[] = $this->get_string('taskgeneratequestions', $a);
                        }
                    } else {
                        // Task object could not be created. - shouldn't happen !!
                        $failure[] = $this->get_string('taskgeneratequestions', $a);
                    }
                }
            }
        }

        // Report back on the success or failure of setting up the adhoc tasks.
        if (count($success)) {
            $success = \html_writer::alist($success);
            $success = $this->get_string('scheduletaskssuccess', $success);
            $strsuccess = get_string('success').get_string('labelsep', 'langconfig');
            $strsuccess = \html_writer::tag('b', $strsuccess, ['class' => 'text-success']);
            $success = $OUTPUT->notification($strsuccess.$success, 'success');
            $mform->addElement('html', $success);
        }
        if (count($failure)) {
            $failure = \html_writer::alist($failure);
            $failure = $this->get_string('scheduletasksfailure', $failure);
            $strfailure = get_string('error').get_string('labelsep', 'langconfig');
            $strfailure = \html_writer::tag('b', $strfailure, ['class' => 'text-danger']);
            $failure = $OUTPUT->notification($strfailure.$failure, 'warning');
            $mform->addElement('html', $failure);
        }
    }

    /**
     * generate_questions_save
     *
     * TODO: Finish documenting this function
     */
    public function generate_questions_save() {
        $dl = ['class' => 'row', 'style' => 'max-width: 720px;'];
        $dt = ['class' => 'col-3 text-right'];
        $dd = ['class' => 'col-9'];
        $br = \html_writer::empty_tag('br');

        echo \html_writer::start_tag('dl', $dl);
        if ($words) {
            echo \html_writer::tag('dt', 'Words: ', $dt).
                 \html_writer::tag('dd', implode(', ', $words), $dd);
        }
        if ($qtypes) {
            echo \html_writer::tag('dt', 'Question types:', $dt).
                 \html_writer::tag('dd', implode($br, $qtypes), $dd);
        }
        if ($qlevels) {
            echo \html_writer::tag('dt', 'Question levels:', $dt).
                 \html_writer::tag('dd', implode($br, $qlevels), $dd);
        }
        echo \html_writer::tag('dt', 'Question count:', $dt).
             \html_writer::tag('dd', $qcount, $dd);

        echo \html_writer::tag('dt', 'Parent category:', $dt).
             \html_writer::tag('dd', "$parentcatname (id=$parentcatid)", $dd);

        echo \html_writer::tag('dt', 'Subcategory type:', $dt).
             \html_writer::tag('dd', "$subcatname (type=$subcattype)", $dd);

        echo \html_writer::end_tag('dl');
    }
}
