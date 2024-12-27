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
 * ai/tts/classes/form.php
 *
 * @package    vocabai_tts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_tts;

/**
 * Main settings form for a DALL-E AI assistant subplugin.
 *
 * @package    vocabai_tts
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "key" field in the config record */ 
    const CONFIG_KEY = 'ttskey';

    /** var string the name of the "model" field in the config record */ 
    const CONFIG_MODEL = 'ttsmodel';
    
    /** var string a comma-delimited list of required fields */ 
    const REQUIRED_FIELDS = 'ttsurl, ttskey, ttsmodel';

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // If any of this user's configs are found below,
        // export will be enabled.
        $enableexport = false;

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {
            $enableexport = true;

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
                'ttsurl' => 'https://api.openai.com/v1/audio/speech',
                'ttskey' => '',
                'ttsmodel' => 'tts-1',
                'voice' => 'alloy',
                'response_format' => 'mp3',
                'speed' => '1.0',
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
        }

        // Cache the label separator, e.g. ": ".
        $labelsep = get_string('labelsep', 'langconfig');

        // Display the config settings that apply to this context and are
        // owned by other users. These are NOT editable by the current user.
        $configs = $this->get_subplugin()->get_configs('otherusers', 'thiscontext', $default->id);
        if (count($configs)) {

            $name = 'keysownedbyothers';
            $this->add_heading($mform, $name, true);

            if (is_siteadmin()) {
                // Site admin can always edit, copy and delete anything.
                $actions = ['edit', 'copy', 'delete'];
            } else {
                $actions = [];

                // Display message to non-admin users (e.g. teachers)
                // explaining that they cannot edit keys owned by other people.
                $text = $this->get_string('note');
                $text = \html_writer::tag('span', $text, ['class' => 'text-danger']);
                $text = $text.$labelsep.$this->get_string('cannoteditkeys');
                $text = \html_writer::tag('h5', $text, ['class' => 'cannotedit']);
                $mform->addElement('html', $text);
            }
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions, true)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        // Display the config settings that are owned by this user but do not
        // apply to the current context. These are editable by the current user.
        $configs = $this->get_subplugin()->get_configs('thisuser', 'othercontexts', $default->id);
        if (count($configs)) {
            $enableexport = true;

            $name = 'otherkeysownedbyme';
            $this->add_heading($mform, $name, true);

            $actions = ['edit', 'copy', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        // Display the config settings that owned by this user and apply to
        // the current context. These are editable by the current user.
        $configs = $this->get_subplugin()->get_configs('thisuser', 'thiscontext', $default->id);
        if (count($configs)) {
            $enableexport = true;

            $name = 'keysownedbyme';
            $this->add_heading($mform, $name, true);

            $actions = ['edit', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        $this->add_heading($mform, $mainheading, true);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'ttsurl';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'ttskey';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'ttsmodel';
        $options = ['tts-1', 'tts-1-hd'];
        $options = array_flip($options);
        foreach ($options as $option => $i) {
            $options[$option] = strtoupper($option).$labelsep.$this->get_string($option);
        }
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'settings';
        $this->add_heading($mform, $name, true);

        $name = 'voice';
        $options = [
            'alloy' => $this->get_string($name.'alloy'),
            'echo' => $this->get_string($name.'echo'),
            'fable' => $this->get_string($name.'fable'),
            'onyx' => $this->get_string($name.'onyx'),
            'nova' => $this->get_string($name.'nova'),
            'shimmer' => $this->get_string($name.'shimmer'),
        ];
        $this->add_field_select($mform, $name, $options, PARAM_ALPHA, $default->$name);

        $name = 'response_format';
        $options = [
            'mp3' => $this->get_string($name.'mp3'),
            'opus' => $this->get_string($name.'opus'),
            'aac' => $this->get_string($name.'aac'),
            'flac' => $this->get_string($name.'flac'),
            'wav' => $this->get_string($name.'wav'),
            'pcm' => $this->get_string($name.'pcm'),
        ];
        $this->add_field_select($mform, $name, $options, PARAM_ALPHA, $default->$name);

        $name = 'speed'; // Default is 1.0.
        $options = ['0.25' => '0.25'];
        for ($i=0.5; $i<=4.0; $i+=0.5) {
            $options["$i"] = number_format($i, 1);
        }
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);

        // -----------------------
        // You can generate spoken audio in these languages
        // by providing the input text in the required language.
        // -----------------------
        // Afrikaans, Arabic, Armenian, Azerbaijani, Belarusian,
        // Bosnian, Bulgarian, Catalan, Chinese, Croatian, Czech,
        // Danish, Dutch, English, Estonian, Finnish, French,
        // Galician, German, Greek, Hebrew, Hindi, Hungarian,
        // Icelandic, Indonesian, Italian, Japanese, Kannada,
        // Kazakh, Korean, Latvian, Lithuanian, Macedonian,
        // Malay, Marathi, Maori, Nepali, Norwegian, Persian,
        // Polish, Portuguese, Romanian, Russian, Serbian, Slovak,
        // Slovenian, Spanish, Swahili, Swedish, Tagalog, Tamil,
        // Thai, Turkish, Ukrainian, Urdu, Vietnamese, Welsh

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }

        $PAGE->requires->js_call_amd('vocabai_tts/form', 'init');
    }
}
