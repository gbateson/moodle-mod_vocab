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
 * ai/files/classes/form.php
 *
 * @package    vocabai_files
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_files;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_files
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "name" field from the config record */
    const CONFIG_NAME = 'filename';

    /** var string the name of the "name" field from the config record */
    const CONFIG_TEXT = 'filedescription';

    /** var string a comma-delimited list of required fields */
    const REQUIRED_FIELDS = 'file';

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // The sort field used to sort configs by alphabetically.
        $sortfield = 'filedescription';

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            // Check we have expected fields.
            foreach ($this->get_subplugin()->get_settingnames() as $name) {
                if (empty($default->$name)) {
                    $default->$name = null;
                }
            }

            $mainheading = 'editfile';
            $submitlabel = get_string('save');

        } else {

            $mainheading = 'addnewfile';
            $submitlabel = get_string('add');

            // Get current year, month and day.
            list($year, $month, $day) = explode(' ', date('Y m d'));

            // Define default values for new file.
            $default = (object)[
                'id' => 0,
                'filedescription' => '',
                'fileitemid' => '',
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
        }

        // Add configs that are related to this user and/or context.
        list($expanded, $enableexport) = $this->add_configs($mform, $default, 'files');

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        // Note, a section cannot be collapsed if it contains required fields.
        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'filedescription';
        $this->add_field_text($mform, $name, PARAM_TEXT, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        // This field is intended to contain "tuning" samples, which are
        // examples of responses that the AI engine should try to mimic.
        $name = 'fileitemid';
        $options = ['accepted_types' => '.txt, .json', 'maxfiles' => 1];
        $this->add_field_filemanager($mform, $name, null, $options);
        // For more information about fine-tuning files and fine-tuning jobs, see:
        // https://platform.openai.com/docs/guides/fine-tuning/preparing-your-dataset.

        $this->add_tuning_models($mform, $default);
        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }

        $PAGE->requires->js_call_amd('vocabai_files/form', 'init');
    }

    /**
     * Show list of tuned AI models that have been trained on this tuning file.
     *
     * @param object $mform
     * @param object $default values for the current file instance
     */
    public function add_tuning_models($mform, $default) {
        $plugintype = 'vocabai';
        $plugins = \core_component::get_plugin_list($plugintype);
        unset($plugins['files'], $plugins['formats'], $plugins['prompts']);
        foreach ($plugins as $pluginname => $pluginpath) {
            $name = $pluginname.'modelid'; // E.g. "chatgptmodelid".
            $plugin = $plugintype.'_'.$pluginname; // E.g. "vocabai_chatgpt".
            if (isset($default->$name) && ($value = $default->$name)) {
                $label = get_string($name, $plugin);
                $mform->addElement('static', $name, $label, $value);
                $mform->addHelpButton($name, $name, $plugin);
            }
        }
    }
}
