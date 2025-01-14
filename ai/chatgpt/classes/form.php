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
 * ai/chatgpt/classes/form.php
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_chatgpt;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "key" field in the config record */
    const CONFIG_KEY = 'chatgptkey';

    /** var string the name of the "model" field in the config record */
    const CONFIG_MODEL = 'chatgptmodel';

    /** var string a comma-delimited list of required fields */
    const REQUIRED_FIELDS = 'chatgpturl,chatgptkey, chatgptmodel';
    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            // Check we have expected fields.
            $ai = '\\'.$this->subpluginname.'\\ai';
            foreach ($ai::get_settingnames() as $name) {
                if (empty($default->$name)) {
                    $default->$name = null;
                }
            }

            $mainheading = 'editkey';
            $submitlabel = get_string('save');

        } else {

            $mainheading = 'addnewkey';
            $submitlabel = get_string('add');

            // Get current year, month and day.
            list($year, $month, $day) = explode(' ', date('Y m d'));

            // Define default values for new key.
            $default = (object)[
                'id' => 0,
                'chatgpturl' => 'https://api.openai.com/v1/chat/completions',
                'chatgptkey' => '',
                'chatgptmodel' => 'gpt-4o-mini',
                'temperature' => 0.2,
                'top_p' => 0.1,
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
        }

        // Cache the label separator, e.g. ": ".
        $labelsep = get_string('labelsep', 'langconfig');

        // Add configs that are related to this user and/or context.
        list($expanded, $enableexport) = $this->add_configs($mform, $default, 'keys');

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'chatgpturl';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'chatgptkey';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'chatgptmodel';
        $options = ['gpt-3.5-turbo', 'gpt-4o-mini', 'gpt-4o', 'gpt-4'];
        $options = array_flip($options);
        foreach ($options as $option => $i) {
            $options[$option] = $option.$labelsep.$this->get_string($option);
        }

        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'settings';
        $this->add_heading($mform, $name, true);

        // Generate reusable menu of numeric values for "temperature" and "top_p".
        $options = [];
        for ($i = 0.0; $i <= 1; $i += 0.1) {
            if ($i == 0.0) {
                $options[''] = 'Not set';
            }
            $i = sprintf('%0.1f', $i);
            $options["$i"] = "$i";
        }

        // For more information about, and comparison of, temperature and p_top, see:
        // https://community.openai.com/t/cheat-sheet-mastering-temperature-and-top-p-in-chatgpt-api/172683.
        $name = 'temperature';
        $this->add_field_select($mform, $name, $options, PARAM_LOCALISEDFLOAT, $default->$name);

        $name = 'top_p';
        $this->add_field_select($mform, $name, $options, PARAM_LOCALISEDFLOAT, $default->$name);

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }
    }
}
