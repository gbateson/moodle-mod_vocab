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
    public $tool = 'vocabtool_questionbank';

    const SUBCAT_NONE = 'none';
    const SUBCAT_SINGLE = 'single';
    const SUBCAT_AUTOMATIC = 'automatic';

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $name = 'wordlist';
        $this->add_heading($mform, $name, 'mod_vocab', true);

        $name = 'selectedwords';
        $label = get_string($name, $this->tool);

        $elements = [];

        $params = [
            'data-selectall' => get_string('selectall'),
            'data-deselectall' => get_string('deselectall'),
            'class' => 'd-none',
        ];
        $elements[] = $mform->createElement('checkbox', 'selectall', get_string('selectall'), '', $params);

        $i = 0;
        $words = $this->get_vocab()->get_wordlist_words();
        foreach ($words as $id => $word) {
            $i++;
            $elements[] = $mform->createElement('checkbox', $id, $word);
        }
        $br = \html_writer::tag('span', '', ['class' => 'w-100']);
        $mform->addGroup($elements, $name, $label, $br);
        $mform->addHelpButton($name, $name, $this->tool);

        $this->add_heading($mform, 'questionsettings', $this->tool, true);

        $name = 'questiontypes';
        $options = $this->get_question_types();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'multichoice', 'multiple');

        $name = 'questionlevels';
        $options = $this->get_question_levels();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'B1', 'multiple');

        $name = 'questioncount';
        $this->add_field_text($mform, $name, PARAM_INT, 5, 2);

        $name = 'categorysettings';
        $this->add_heading($mform, $name, $this->tool, true);

        $this->add_parentcategory($mform);
        $this->add_subcategories($mform);

        // Use "generatequestions" as the label for the submit button.
        $label = get_string('generatequestions', $this->tool);
        $this->add_action_buttons(true, $label);

        $PAGE->requires->js_call_amd('vocabtool_questionbank/form', 'init');
    }

    /**
     * get_question_types
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_question_types() {
        // ToDo: Maybe include: 'ordering', 'essayautograde', 'speakautograde', 'sassessment'
        $include = ['match', 'multianswer', 'multichoice', 'shortanswer', 'truefalse'];
        $types = \core_component::get_plugin_list('qtype');
        foreach ($types as $name => $dir) {
            if (in_array($name, $include)) {
                $types[$name] = get_string('pluginname', "qtype_$name");
            } else {
                unset($types[$name]);
            }
        }
        asort($types); // sort alphabetically (maintain key association)
        return $types;
    }

    /**
     * get_question_levels
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_question_levels() {
        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        return array_combine($levels, $levels);
    }

    /**
     * add_parentcategory
     *
     * @param moodleform $mform representing the Moodle form
     * @todo Finish documenting this function
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
            $defaultid = 0; // shouldn't happen !!
        }

        $name = 'parentcategory';
        $label = get_string($name, $this->tool);
        $groupname = $name.'elements';

        $elements = [
            $mform->createElement('select', $name, '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories()),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $mform->setType($groupname.'['.$name.']', PARAM_TEXT);
        $mform->setDefault($groupname.'['.$name.']', $defaultid);
    }

    /**
     * link_to_managequestioncategories
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function link_to_managequestioncategories() {
        $link = '/question/bank/managecategories/category.php';
        $params = ['courseid' => $this->get_vocab()->course->id];
        $link = new \moodle_url($link, $params);

        $text = get_string('managequestioncategories', $this->tool);
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
     * @todo Finish documenting this function
     */
    public function get_question_categories() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/questionlib.php');

        $courseid = $this->get_vocab()->course->id;
        $coursecontext = \context_course::instance($courseid);
        $coursecategory = question_get_top_category($coursecontext->id, true); // create if necessary

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
     * @todo Finish documenting this function
     */
    public function add_subcategories($mform) {
        $name = 'subcategories';
        $label = get_string($name, $this->tool);

        $groupname = $name.'elements';
        $cattype = $groupname.'[cattype]';
        $catname = $groupname.'[catname]';

        $options = [
            self::SUBCAT_NONE => get_string('none'),
            self::SUBCAT_SINGLE => get_string('singlesubcategory', $this->tool),
            self::SUBCAT_AUTOMATIC => get_string('automaticsubcategories', $this->tool),
        ];
        $elements = [
            $mform->createElement('select', 'cattype', '', $options),
            $mform->createElement('text', 'catname', '', ['size' => 20]),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $mform->setType($cattype, PARAM_ALPHA);
        $mform->setDefault($cattype, self::SUBCAT_AUTOMATIC);

        $mform->setType($catname, PARAM_TEXT);
        // $mform->setDefault($catname, '');
        $mform->disabledIf($catname, $cattype, 'neq', 'single');
    }

    /**
     * validation
     *
     * @uses $USER
     * @param stdClass $data submitted from the form
     * @param array $files
     * @return xxx
     * @todo Finish documenting this function
     */
    public function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

        return $errors;
    }

    /**
     * generate_questions
     *
     * @uses $DB
     * @todo Finish documenting this function
     */
    public function generate_questions() {
        global $DB;
        if (($data = data_submitted()) && confirm_sesskey()) {

            $words = false;
            $qtypes = false;
            $qlevels = false;
            $qcount = $data->questioncount;

            $parentcatid = 0;
            $parentcatname = '';

            $subcattype = '';
            $subcatname = '';

            if (property_exists($data, 'selectedwords')) {
                unset($data->selectedwords['selectall']);

                $select = 'vwi.wordid, vw.word';
                $from = '{vocab_word_instances} vwi, {vocab_words} vw';
                list($where, $params) = $DB->get_in_or_equal(array_keys($data->selectedwords));
                $where = 'vwi.vocabid = ? AND vwi.wordid = vw.id AND vw.id '.$where;
                $params = array_merge([$this->get_vocab()->id], $params);
                $order = 'vwi.sortorder, vw.word';

                $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
                $words = $DB->get_records_sql_menu($sql, $params);

                unset($data->selectedwords);
            }

            if (property_exists($data, 'questiontypes')) {
                $qtypes = $this->get_question_types();
                foreach ($qtypes as $name => $text) {
                    if (! in_array($name, $data->questiontypes)) {
                        unset($qtypes[$name]);
                    }
                }
                unset($data->questiontypes);
            }

            if (property_exists($data, 'questionlevels')) {
                $qlevels = $this->get_question_levels();
                foreach ($qlevels as $name => $text) {
                    if (! in_array($name, $data->questionlevels)) {
                        unset($qlevels[$name]);
                    }
                }
                unset($data->questionlevels);
            }

            $name = 'parentcategory';
            $groupname = $name.'elements';
            if (property_exists($data, $groupname)) {
                if (array_key_exists($name, $data->$groupname)) {
                    $parentcatid = $data->{$groupname}[$name];
                    $categories = $this->get_question_categories();
                    if (array_key_exists($parentcatid, $categories)) {
                        $parentcatname = $categories[$parentcatid];
                    } else {
                        // We've been given an invalid $parentcatid !!
                        $parentcatid = key($categories);
                        $parentcatname = reset($categories);
                    }
                }
                unset($data->$groupname);
            }

            $name = 'cattype';
            $groupname = 'subcategorieselements';
            if (property_exists($data, $groupname)) {
                if (array_key_exists($name, $data->$groupname)) {
                    $subcattype = $data->{$groupname}[$name];
                }
                unset($data->$groupname);
            }

            if ($words || $qtypes || $qlevels) {
                $dl = ['class' => 'row', 'style' => 'max-width: 720px;'];
                $dt = ['class' => 'col-3 text-right'];
                $dd = ['class' => 'col-9'];
                echo \html_writer::start_tag('dl', $dl);
                if ($words) {
                    echo \html_writer::tag('dt', 'Words: ', $dt).
                         \html_writer::tag('dd', implode(', ', $words), $dd);
                }
                if ($qtypes) {
                    echo \html_writer::tag('dt', 'Question types:', $dt).
                         \html_writer::tag('dd', implode(', ', $qtypes), $dd);
                }
                if ($qlevels) {
                    echo \html_writer::tag('dt', 'Question levels:', $dt).
                         \html_writer::tag('dd', implode(', ', $qlevels), $dd);
                }
                echo \html_writer::tag('dt', 'Question count:', $dt).
                     \html_writer::tag('dd', $qcount, $dd);

                echo \html_writer::tag('dt', 'Parent category:', $dt).
                     \html_writer::tag('dd', "$parentcatname (id=$parentcatid)", $dd);

                echo \html_writer::tag('dt', 'Subcategory type:', $dt).
                     \html_writer::tag('dd', $subcattype, $dd);

                echo \html_writer::end_tag('dl');
            }
        }
    }
}

