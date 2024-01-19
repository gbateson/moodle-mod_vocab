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
     * @todo Finish documenting this function
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $name = 'wordlist';
        $this->add_heading($mform, $name, 'mod_vocab', true);

        $name = 'selectedwords';
        $label = $this->get_string($name);

        $elements = [];

        $params = [
            'data-selectall' => get_string('selectall'),
            'data-deselectall' => get_string('deselectall'),
            'class' => 'd-none',
        ];
        $elements[] = $mform->createElement('checkbox', 'selectall', get_string('selectall'), '', $params);

        $words = $this->get_vocab()->get_wordlist_words();
        foreach ($words as $id => $word) {
            $elements[] = $mform->createElement('checkbox', $id, $word);
        }
        $br = \html_writer::tag('span', '', ['style' => 'width: 100%!important']);
        $spacer = \html_writer::tag('span', '', ['style' => 'width: 24px!important']);
        $mform->addGroup($elements, $name, $label, $br);
        $mform->addHelpButton($name, $name, $this->subpluginname);

        $this->add_heading($mform, 'questionsettings', $this->subpluginname, true);

        $name = 'questiontypes';
        $options = $this->get_question_types();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'multichoice', 'multiple');

        $name = 'questionlevels';
        $options = $this->get_question_levels();
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
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_question_types() {
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
     * get_question_levels
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_question_levels() {
        // ToDo: get these levels from the vocab_levelnames table.
        return [
            'A1' => $this->get_string('cefr_a1_description'),
            'A2' => $this->get_string('cefr_a2_description'),
            'B1' => $this->get_string('cefr_b1_description'),
            'B2' => $this->get_string('cefr_b2_description'),
            'C1' => $this->get_string('cefr_c1_description'),
            'C2' => $this->get_string('cefr_c2_description'),
        ];
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
     */
    public function generate_questions() {
        global $DB;

        // Get form data, if any.
        if (($data = data_submitted()) && confirm_sesskey()) {

            $words = false;
            $qtypes = false;
            $qlevels = false;
            $qcount = $data->questioncount;

            $parentcatid = 0;
            $parentcatname = '';

            $subcattype = 0;
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

            if (property_exists($data, 'questionlevels') && is_array($data->questionlevels)) {
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
                    $type = $data->{$groupname}[$name];
                    $types = $this->get_subcategory_types();
                    if (array_key_exists($type, $types)) {
                        $subcattype = $type;
                        $subcatname = $types[$type];
                    }
                    unset($type, $types);
                }
                unset($data->$groupname);
            }

            if ($words || $qtypes || $qlevels) {

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
    }
}
