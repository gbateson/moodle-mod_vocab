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
        $label = get_string($name, $this->tool);
        $mform->addElement('select', $name, $label, $this->get_question_types(), array('multiple'));
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 'multichoice');

        $name = 'questionlevels';
        $label = get_string($name, $this->tool);
        $options = range(1, 5);
        $mform->addElement('select', $name, $label, $this->get_question_levels(), array('multiple'));
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 10);

        $name = 'questioncount';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, array('size' => 2));
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 10);

        $this->add_heading($mform, 'categorysettings', $this->tool, true);

        $this->add_parentcategory($mform);
        $this->add_subcategories($mform);

        // Store the course module id.
        $name = 'id';
        $mform->addElement('hidden', $name, optional_param($name, 0, PARAM_INT));
        $mform->setType($name, PARAM_INT);

        // Use "proceed" as the label for the submit button.
        // Note that "go" is also available.
        $this->add_action_buttons(true, get_string('generatequestions', $this->tool));
    }

    public function add_parentcategory($mform) {
        $name = 'parentcategory';
        $label = get_string($name, $this->tool);
        $groupname = $name.'elements';

        $params = array('courseid' => $this->get_vocab()->course->id);
        $link = \html_writer::tag('small', \html_writer::link(
            new \moodle_url('/question/bank/managecategories/category.php', $params),
            get_string('managequestioncategories', $this->tool),
            array('onclick' => "this.target='VOCAB'")
        ), array('class' => 'w-100 pl-1'));

        $elements = array(
            $mform->createElement('select', $name, '', $this->get_question_categories()),
            $mform->createElement('html', $link)
        );
        $mform->addGroup($elements, $groupname, $label);

        $mform->addHelpButton($groupname, $name, $this->tool);
        $mform->setType($name, PARAM_TEXT);
        $mform->setDefault($name, \mod_vocab\activity::MODE_LIVE);
    }

    public function add_subcategories($mform) {
        $name = 'subcategories';
        $label = get_string($name, $this->tool);

        $groupname = $name.'elements';
        $cattype = $groupname.'[cattype]';
        $catname = $groupname.'[catname]';

        $options = array(
            'none' => get_string('none'),
            'single' => get_string('singlesubcategory', $this->tool),
            'automatic' => get_string('automaticsubcategories', $this->tool)
        );
        $elements = array(
            $mform->createElement('select', 'cattype', '', $options),
            $mform->createElement('text', 'catname', '', array('size' => 20))
        );
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->tool);

        $mform->setDefault($cattype, 'automatic');
        $mform->setType($cattype, PARAM_ALPHA);

        $mform->setType($catname, PARAM_TEXT);
        $mform->disabledIf($catname, $cattype, 'neq', 'single');
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
}
