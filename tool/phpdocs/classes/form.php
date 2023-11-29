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
    public function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $textoptions = ['size' => '32'];
        $filetypes = $this->get_filetypes();
        $actions = $this->get_actions();

        // Heading for file settings.
        $this->add_heading($mform, 'filesettings', $this->tool, true);

        $params = array_merge($textoptions, ['disabled' => 'disabled']);
        $this->add_field_text($mform, 'folderpath', PARAM_PATH, '/mod/vocab', $params);
        $this->add_field_text($mform, 'filepath', PARAM_PATH, '', $textoptions);

        $this->add_field_select($mform, 'filetypes', $filetypes, PARAM_ALPHA, ['php'], 'multiple');
        $mform->disabledIf('filetypes', 'filepath', 'ne', '');

        // Heading for search and replace settings.
        $this->add_heading($mform, 'searchreplaceactions', $this->tool, true);

        $this->add_field_select($mform, 'copyrightaction', $actions, PARAM_INT, self::ACTION_REPORT_ALL);
        $this->add_field_select($mform, 'phpdocsaction', $actions, PARAM_INT, self::ACTION_REPORT_ALL);

        // Heading for copyright settings.
        $this->add_heading($mform, 'copyrightsettings', $this->tool, true);

        $this->add_field_text($mform, 'package', PARAM_TEXT, 'mod_vocab', $textoptions);
        $this->add_field_text($mform, 'startyear', PARAM_TEXT, date('Y'), $textoptions);
        $this->add_field_text($mform, 'authorname', PARAM_TEXT, fullname($USER), $textoptions);
        $this->add_field_text($mform, 'authorcontact', PARAM_TEXT, $USER->email, $textoptions);
        $this->add_field_text($mform, 'sinceversion', PARAM_TEXT, floatval($CFG->release), $textoptions);

        // Use "proceed" as the label for the submit button.
        // Note that "go" is also available.
        $this->add_action_buttons(true, get_string('proceed'));
    }

    public function get_filetypes() {
        return [
            'php' => get_string('phpfiles', $this->tool),
            'js' => get_string('jsfiles', $this->tool),
            'css' => get_string('cssfiles', $this->tool),
            'xml' => get_string('xmlfiles', $this->tool),
        ];
    }

    public function get_actions() {
        return [
            self::ACTION_NONE => get_string('none'),
            self::ACTION_REPORT_ALL => get_string('reportall', $this->tool),
            self::ACTION_REPORT_MISSING => get_string('reportmissing', $this->tool),
            self::ACTION_REPORT_INCORRECT => get_string('reportincorrect', $this->tool),
            self::ACTION_FIX_ALL => get_string('fixall', $this->tool),
            self::ACTION_FIX_MISSING => get_string('fixmissing', $this->tool),
            self::ACTION_FIX_INCORRECT => get_string('fixincorrect', $this->tool),
            self::ACTION_REMOVE_ALL => get_string('removeall', $this->tool),
        ];
    }
}

