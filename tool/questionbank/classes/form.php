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
    function definition() {

        $mform = $this->_form;
        $this->set_form_id($mform);

        $this->add_heading($mform, 'questionsettings', $this->tool, true);

        $name = 'questiontypes';
        $this->add_field_select($mform, $name, $this->get_question_types(), PARAM_ALPHANUM, 'multichoice', 'multiple');

        $name = 'questionlevels';
        $this->add_field_select($mform, $name, $this->get_question_levels(), PARAM_ALPHANUM, 'B1', 'multiple');

        $name = 'questioncount';
        $this->add_field_text($mform, $name, PARAM_INT, 10, 2);

        $name = 'categorysettings';
        $this->add_heading($mform, $name, $this->tool, true);

        $this->add_parentcategory($mform);
        $this->add_subcategories($mform);

        // Store the course module id.
        $name = 'id';
        $mform->addElement('hidden', $name, optional_param($name, 0, PARAM_INT));
        $mform->setType($name, PARAM_INT);

        // Use "generatequestions" as the label for the submit button.
        $label = get_string('generatequestions', $this->tool);
        $this->add_action_buttons(true, $label);
    }

    public function get_question_types() {
        // ToDo: Maybe include: 'ordering', 'essayautograde', 'speakautograde', 'sassessment'
        $include = array('match', 'multianswer', 'multichoice', 'shortanswer', 'truefalse');
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

    public function get_question_levels() {
        $levels = array('A1', 'A2', 'B1', 'B2', 'C1', 'C2');
        return array_combine($levels, $levels);
    }

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
            $defaultid = 0; //shouldn't happen !!
        }
        
        $name = 'parentcategory';
        $label = get_string($name, $this->tool);
        $groupname = $name.'elements';

        $elements = array(
            $mform->createElement('select', $name, '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories())
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $mform->setType($groupname.'['.$name.']', PARAM_TEXT);
        $mform->setDefault($groupname.'['.$name.']', $defaultid);
    }

    public function link_to_managequestioncategories() {
        $link = '/question/bank/managecategories/category.php';
        $params = array('courseid' => $this->get_vocab()->course->id);
        $link = new \moodle_url($link, $params);

        $text = get_string('managequestioncategories', $this->tool);
        $params = array('onclick' => "this.target='VOCAB'");
        $link = \html_writer::link($link, $text, $params);

        $params = array('class' => 'w-100 pl-1');
        return \html_writer::tag('small', $link, $params);
    }

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
            return array();
        }
    }

    public function add_subcategories($mform) {
        $name = 'subcategories';
        $label = get_string($name, $this->tool);

        $groupname = $name.'elements';
        $cattype = $groupname.'[cattype]';
        $catname = $groupname.'[catname]';

        $options = array(
            self::SUBCAT_NONE => get_string('none'),
            self::SUBCAT_SINGLE => get_string('singlesubcategory', $this->tool),
            self::SUBCAT_AUTOMATIC => get_string('automaticsubcategories', $this->tool)
        );
        $elements = array(
            $mform->createElement('select', 'cattype', '', $options),
            $mform->createElement('text', 'catname', '', array('size' => 20))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $mform->setType($cattype, PARAM_ALPHA);
        $mform->setDefault($cattype, self::SUBCAT_AUTOMATIC);

        $mform->setType($catname, PARAM_TEXT);
        //$mform->setDefault($catname, '');
        $mform->disabledIf($catname, $cattype, 'neq', 'single');
    }

    function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

        return $errors;
    }
}
