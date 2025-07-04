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
 * ai/formats/classes/form.php
 *
 * @package    vocabai_formats
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_formats;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_formats
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "name" field from the config record */
    const CONFIG_NAME = 'formatname';

    /** var string the name of the "text" field in the config record */
    const CONFIG_TEXT = 'formattext';

    /** var array containing the names of required fields */
    const REQUIRED_FIELDS = ['formatname', 'formattext'];

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Get current year, month and day.
        list($year, $month, $day) = explode(' ', date('Y m d'));

        // Define default values for new format.
        $default = (object)[
            // Basic settings.
            'id' => 0,
            'formatname' => '',
            'formattext' => '',
            // Sharing settings.
            'contextlevel' => CONTEXT_MODULE,
            'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
            'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
        ];

        // Try and get current config for editing.
        if ($config = $this->get_subplugin()->config) {

            // Transfer values form $config record.
            foreach ($default as $name => $value) {
                if (isset($config->$name)) {
                    $default->$name = $config->$name;
                }
            }

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            $mainheading = 'editformat';
            $submitlabel = get_string('save');

        } else {
            $mainheading = 'addnewformat';
            $submitlabel = get_string('add');
        }

        // Add configs that are related to this user and/or context.
        list($expanded, $enableexport) = $this->add_configs($mform, $default, 'formats');

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        // Note, a section cannot be collapsed if it contains required fields.
        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'formatname';
        $this->add_field_text($mform, $name, PARAM_TEXT, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'formattext';
        $this->add_field_textarea($mform, $name, PARAM_TEXT, $default->$name, ['rows' => '5', 'cols' => 40]);
        $mform->addRule($name, $addmissingvalue, 'required');

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }

        $PAGE->requires->js_call_amd('vocabai_formats/form', 'init');
    }
}
