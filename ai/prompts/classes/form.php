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
 * ai/prompts/classes/form.php
 *
 * @package    vocabai_prompts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_prompts;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_prompts
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "name" field from the config record */
    const CONFIG_NAME = 'promptname';

    /** var string the name of the "text" field in the config record */
    const CONFIG_TEXT = 'prompttext';

    /** var array containing the names of required fields */
    const REQUIRED_FIELDS = ['promptname', 'prompttext'];

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Get current year, month and day.
        list($year, $month, $day) = explode(' ', date('Y m d'));

        // Define default values for new prompt.
        $default = (object)[
            'id' => 0,
            // Basic settings.
            'promptname' => '',
            'prompttext' => '',
            // Default settings used by vocabtool_questionbank.
            'prompttextid' => 0,
            'promptfileid' => 0,
            'promptqformat' => 'gift',
            'promptimageid' => 0,
            'promptaudioid' => 0,
            'promptvideoid' => 0,
            'promptqtypes' => [],
            'promptqcount' => '',
            'promptreview' => 0,
            'promptparentcatid' => 0,
            'promptsubcattype' => 0,
            'promptsubcatname' => '',
            'prompttagtypes' => 0,
            'prompttagnames' => '',
            // Sharing settings.
            'contextlevel' => CONTEXT_MODULE,
            'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
            'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
        ];

        // Try and get current config for editing.
        if ($config = $this->get_subplugin()->config) {

            // Transfer values form $config record.
            foreach ($default as $name => $value) {
                if ($name == 'promptqtypes') {
                    if (isset($config->$name)) {
                        $value = json_decode($config->$name);
                        if (json_last_error() == JSON_ERROR_NONE) {
                            foreach ($value as $qtype => $formatid) {
                                $default->$qtype = [
                                    'enable' => 1,
                                    'format' => $formatid,
                                ];
                            }
                        }
                    }
                } else if (isset($config->$name)) {
                    $default->$name = $config->$name;
                }
            }

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            $mainheading = 'editprompt';
            $submitlabel = get_string('save');

        } else {
            $mainheading = 'addnewprompt';
            $submitlabel = get_string('add');
        }

        // Add configs that are related to this user and/or context.
        list($expanded, $enableexport) = $this->add_configs($mform, $default, 'prompts');

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        // Note, a section cannot be collapsed if it contains required fields.
        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'promptname';
        $this->add_field_text($mform, $name, PARAM_TEXT, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'prompttext';
        $this->add_field_textarea($mform, $name, PARAM_TEXT, $default->$name, ['rows' => '5', 'cols' => 40]);
        $mform->addRule($name, $addmissingvalue, 'required');

        // Heading for default AI settings.
        $this->add_heading($mform, 'defaultaisettings', false);

        $name = 'prompttextid';
        $a = ['strname' => 'textassistant'];
        $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_TEXT);
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name, $a);

        $name = 'promptfileid';
        $a = ['strname' => 'file'];
        $options = $this->get_config_options('files', 'filedescription', 'selectfile', true);
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name, $a);

        // Question import format (e.g. GIFT).
        $name = 'promptqformat';
        $a = ['strname' => 'qformat'];
        $options = self::get_question_formats();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, $default->$name, $a);

        $name = 'promptimageid';
        $a = ['strname' => 'imageassistant'];
        $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_IMAGE, true);
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name, $a);

        $name = 'promptaudioid';
        $a = ['strname' => 'audioassistant'];
        $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_AUDIO, true);
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name, $a);

        $name = 'promptvideoid';
        $a = ['strname' => 'videoassistant'];
        $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_VIDEO, true);
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name, $a);

        // Heading for default question settings.
        $this->add_heading($mform, 'defaultquestiontypes', false);

        // Cache some field labels.
        // If we omit the enable label completely, the vertical spacing gets messed up,
        // so to compensate, we use a non-blank space. Could also use get_string('enable').
        $enablelabel = '&nbsp;';
        $promptlabel = get_string('promptname', 'vocabai_prompts');
        $formatlabel = get_string('formatname', 'vocabai_formats');
        $formats = $this->get_config_options('formats', 'formatname', 'selectformat');

        $qtypes = self::get_question_types();
        foreach ($qtypes as $qtype => $label) {
            // Add the checkbox, prompt menu and format menu for this question type.
            $elements = [];
            $elements[] = $mform->createElement('checkbox', 'enable', $enablelabel);
            $elements[] = $mform->createElement('select', 'format', $formatlabel, $formats);
            $mform->addGroup($elements, $qtype, $label);
            $mform->addHelpButton($qtype, 'pluginname', "qtype_$qtype");

            // Set the default format to be the first of any that contain
            // the question type in their name.
            if (isset($default->$qtype)) {
                $mform->setDefault($qtype.'[enable]', $default->{$qtype}['enable']);
                $mform->setDefault($qtype.'[format]', $default->{$qtype}['format']);
            } else if ($defaults = preg_grep('/'.preg_quote($label, '/').'/', $formats)) {
                $mform->setDefault($qtype.'[format]', key($defaults));
            }
            // Disable the format menu until the question type becomes checked.
            $mform->hideIf($qtype.'[format]', $qtype.'[enable]', 'notchecked');
        }

        // Heading for default question settings.
        $this->add_heading($mform, 'defaultquestionsettings', false);

        $name = 'promptqcount';
        $a = ['strname' => 'questioncount', 'size' => 2];
        $this->add_field_text($mform, $name, PARAM_INT, $default->$name, $a);

        $name = 'promptreview';
        $a = ['strname' => 'questionreview'];
        $options = [get_string('no'), get_string('yes')];
        $this->add_field_select($mform, "$name", $options, PARAM_ALPHANUM, $default->$name, $a);

        // Heading for default question settings.
        $this->add_heading($mform, 'defaultquestioncategories', false);

        // The parent category group includes 'log[][id]'.
        $name = 'parentcat';
        $this->add_parentcategory($mform, $name, $default->promptparentcatid);

        // The subcategories group includes 'subcat[type]' and 'subcat[name]'.
        $name = 'subcat';
        $this->add_subcategories($mform, $name, $default->promptsubcattype, $default->promptsubcatname);

        // Heading for default question settings.
        $this->add_heading($mform, 'defaultquestiontags', false);

        // The subcategories group includes 'qtag[type]' and 'qtag[name]'.
        $name = 'qtag';
        $this->add_questiontags($mform, $name, $default->prompttagtypes, $default->prompttagnames);

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }

        $PAGE->requires->js_call_amd('vocabai_prompts/form', 'init');
    }
}
