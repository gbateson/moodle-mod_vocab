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

/**
 * form
 *
 * @package    vocabtool_phpdocs
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var string the name of this plugin */
    public $subpluginname = 'vocabtool_phpdocs';

    /** @var integer internal value to represent none action */
    const ACTION_NONE = 0;

    /** @var integer internal value to represent "fix all" action */
    const ACTION_FIX_ALL = 1;

    /** @var integer internal value to represent "fix missing" action */
    const ACTION_FIX_MISSING = 2;

    /** @var integer internal value to represent "fix incorrect" action */
    const ACTION_FIX_INCORRECT = 3;

    /** @var integer internal value to represent "report all" action */
    const ACTION_REPORT_ALL = 4;

    /** @var integer internal value to represent "report missing" action */
    const ACTION_REPORT_MISSING = 5;

    /** @var integer internal value to represent "report incorrect" action */
    const ACTION_REPORT_INCORRECT = 6;

    /** @var integer internal value to represent "remove all" action */
    const ACTION_REMOVE_ALL = 7;

    /**
     * definition
     *
     * @uses $CFG
     * @uses $USER
     * @todo Finish documenting this function
     */
    public function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $textoptions = ['size' => '32'];
        $filetypes = $this->get_filetypes();
        $actions = $this->get_actions();

        // Heading for file settings.
        $this->add_heading($mform, 'filesettings', $this->subpluginname, true);

        $params = array_merge($textoptions, ['disabled' => 'disabled']);
        $this->add_field_text($mform, 'folderpath', PARAM_PATH, '/mod/vocab', $params);
        $this->add_field_text($mform, 'filepath', PARAM_PATH, '', $textoptions);

        $this->add_field_select($mform, 'filetypes', $filetypes, PARAM_ALPHA, ['php'], 'multiple');
        $mform->disabledIf('filetypes', 'filepath', 'ne', '');

        // Heading for search and replace settings.
        $this->add_heading($mform, 'searchreplaceactions', $this->subpluginname, true);

        $this->add_field_select($mform, 'copyrightaction', $actions, PARAM_INT, self::ACTION_REPORT_ALL);
        $this->add_field_select($mform, 'phpdocsaction', $actions, PARAM_INT, self::ACTION_REPORT_ALL);

        // Heading for copyright settings.
        $this->add_heading($mform, 'copyrightsettings', $this->subpluginname, true);

        $this->add_field_text($mform, 'package', PARAM_TEXT, 'mod_vocab', $textoptions);
        $this->add_field_text($mform, 'startyear', PARAM_TEXT, date('Y'), $textoptions);
        $this->add_field_text($mform, 'authorname', PARAM_TEXT, fullname($USER), $textoptions);
        $this->add_field_text($mform, 'authorcontact', PARAM_TEXT, $USER->email, $textoptions);
        $this->add_field_text($mform, 'sinceversion', PARAM_TEXT, floatval($CFG->release), $textoptions);

        // Use "proceed" as the label for the submit button.
        // Note that "go" is also available.
        $this->add_action_buttons(true, get_string('proceed'));
    }

    /**
     * get_filetypes
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_filetypes() {
        return [
            'php' => $this->get_string('phpfiles'),
            'js' => $this->get_string('jsfiles'),
            'css' => $this->get_string('cssfiles'),
            'xml' => $this->get_string('xmlfiles'),
        ];
    }

    /**
     * get_actions
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_actions() {
        return [
            self::ACTION_NONE => get_string('none'),
            self::ACTION_REPORT_ALL => $this->get_string('reportall'),
            self::ACTION_REPORT_MISSING => $this->get_string('reportmissing'),
            self::ACTION_REPORT_INCORRECT => $this->get_string('reportincorrect'),
            self::ACTION_FIX_ALL => $this->get_string('fixall'),
            self::ACTION_FIX_MISSING => $this->get_string('fixmissing'),
            self::ACTION_FIX_INCORRECT => $this->get_string('fixincorrect'),
            self::ACTION_REMOVE_ALL => $this->get_string('removeall'),
        ];
    }
}
