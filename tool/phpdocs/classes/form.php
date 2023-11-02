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
 * tool/phpdocs/classes/form.php
 *
 * @package    vocabtool_phpdocs
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_phpdocs;

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
    public $tool = 'vocabtool_phpdocs';

    const ACTION_NONE = 0;
    const ACTION_FIX_ALL = 1;
    const ACTION_FIX_MISSING = 2;
    const ACTION_FIX_INCORRECT = 3;
    const ACTION_REPORT_ALL = 4;
    const ACTION_REPORT_MISSING = 5;
    const ACTION_REPORT_INCORRECT = 6;
    const ACTION_REMOVE_ALL = 7;

    /**
     * definition
     *
     * @uses $CFG
     * @uses $USER
     * @todo Finish documenting this function
     */
    function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $textoptions = array('size' => '32');

        // Heading for file settings.
        $this->add_heading($mform, 'filesettings', $this->tool, true);

        $name = 'folderpath';
        $label = get_string($name, $this->tool);
        $options = array_merge($textoptions, array('disabled'));
        $mform->addElement('text', $name, $label, $options);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, '/mod/vocab');
        $mform->setType($name, PARAM_PATH);

        $name = 'filetypes';
        $label = get_string($name, $this->tool);
        $options = array('php' => get_string('phpfiles', $this->tool),
                         'js' => get_string('jsfiles', $this->tool),
                         'css' => get_string('cssfiles', $this->tool),
                         'xml' => get_string('xmlfiles', $this->tool));
        $mform->addElement('select', $name, $label, $options, array('multiple'));
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, array('php'));
        $mform->setType($name, PARAM_PATH);

        // Heading for search and replace settings.
        $this->add_heading($mform, 'searchreplaceactions', $this->tool, true);

        $options = array(
            self::ACTION_NONE => get_string('none'),
            self::ACTION_REPORT_ALL => get_string('reportall', $this->tool),
            self::ACTION_REPORT_MISSING => get_string('reportmissing', $this->tool),
            self::ACTION_REPORT_INCORRECT => get_string('reportincorrect', $this->tool),
            self::ACTION_FIX_ALL => get_string('fixall', $this->tool),
            self::ACTION_FIX_MISSING => get_string('fixmissing', $this->tool),
            self::ACTION_FIX_INCORRECT => get_string('fixincorrect', $this->tool),
            self::ACTION_REMOVE_ALL => get_string('removeall', $this->tool)
        );

        $name = 'copyrightaction';
        $label = get_string($name, $this->tool);
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, self::ACTION_REPORT_ALL);
        $mform->setType($name, PARAM_PATH);

        $name = 'phpdocsaction';
        $label = get_string($name, $this->tool);
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, self::ACTION_REPORT_ALL);
        $mform->setType($name, PARAM_PATH);

        // Heading for copyright settings.
        $this->add_heading($mform, 'copyrightsettings', $this->tool, true);

        $name = 'package';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $textoptions);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, 'mod_vocab');
        $mform->setType($name, PARAM_TEXT);

        $name = 'startyear';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $textoptions);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, date('Y'));
        $mform->setType($name, PARAM_TEXT);

        $name = 'authorname';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $textoptions);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, fullname($USER));
        $mform->setType($name, PARAM_TEXT);

        $name = 'authorcontact';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $textoptions);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, $USER->email);
        $mform->setType($name, PARAM_TEXT);

        $name = 'sinceversion';
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $textoptions);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, floatval($CFG->release));
        $mform->setType($name, PARAM_TEXT);

        // Store the course module id.
        $name = 'id';
        $mform->addElement('hidden', $name, optional_param($name, 0, PARAM_INT));
        $mform->setType($name, PARAM_INT);

        // Use "proceed" as the label for the submit button.
        // Note that "go" is also available.
        $this->add_action_buttons(true, get_string('proceed'));
    }
}
