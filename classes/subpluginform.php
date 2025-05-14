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
 * Internal library of functions for the Vocabulary activity module
 *
 * All the vocab specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

defined('MOODLE_INTERNAL') || die;

// Fetch the parent class, "moodleform".
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * \mod_vocab\subpluginform
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
abstract class subpluginform extends \moodleform {

    /**
     * @var public string $subpluginname the name of the subplugin that is
     *      creating this form. This value can be used in get_string().
     */
    public $subpluginname = '';

    /**
     * The constructor function for vocab subpluginform class.
     *
     * @param mixed $action the action attribute for the form.
     * @param mixed $customdata if your form definition method needs access to other data.
     * @param string $method "post" or "get".
     * @param string $target target frame for form submission.
     * @param mixed $attributes you can pass a string of html attributes here or an array.
     * @param bool $editable
     * @param array $ajaxformdata Forms submitted via ajax, must pass their data here.
     */
    public function __construct($action=null, $customdata=null, $method='post',
                                $target='', $attributes=null, $editable=true,
                                $ajaxformdata=null) {

        // Set the subplugin name.
        $this->subpluginname = $customdata['subplugin']->plugin;

        // Call the parent constructor in the usual way.
        parent::__construct($action, $customdata, $method, $target,
                            $attributes, $editable, $ajaxformdata);
    }

    /**
     * Get the subplugin object for this form. We assume that
     * the subplugin was passed in when the form was created
     * by \mod_vocab\subpluginbase::get_mform()
     *
     * @return object $subplugin
     */
    public function get_subplugin() {
        return $this->_customdata['subplugin'];
    }

    /**
     * Get the main vocab object from the subplugin for this form.
     *
     * @return object $vocab
     */
    public function get_vocab() {
        return $this->get_subplugin()->vocab;
    }

    /**
     * Get a string for the subplugin that is displayed this form.
     * If the string is not found in the language pack for the current subplugin,
     * then corresponding string in the parent activity's language pack will be used.
     *
     * @param string $name the name of the required string
     * @param mixed $a (optional, default=null) additional value or values required for the string
     * @return string requested string from the lang pack for the current subplugin
     */
    public function get_string($name, $a=null) {
        $component = $this->get_subplugin()->get_string_component($name);
        return get_string($name, $component, $a);
    }

    /**
     * Set the id of this form. This is useful for CSS styling.
     *
     * @param object $mform representing the Moodle form
     * @param string $id (optional, default='') the new form id
     * @return mixed default value of setting
     */
    public function set_form_id($mform, $id='') {
        if ($id == '') {
            $id = str_replace('\\', '_', get_called_class());
            $id = ltrim($id, '_');
        }
        $attributes = $mform->getAttributes();
        $attributes['id'] = $id;
        $mform->setAttributes($attributes);
    }

    /**
     * Add a heading to the given $mform
     *
     * @param string $name the name of a form element
     * @param string $attributes (passed by reference) the attributes on this form element.
     * @return array of names ['name' => name, 'strname' => 'strname']
     */
    public function get_names($name, &$attributes) {
        $names = [
            'name' => $name,
            'strname' => $name,
        ];
        foreach ($names as $key => $value) {
            if (is_array($attributes) && array_key_exists($key, $attributes)) {
                $names[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }
        return array_values($names);
    }

    /**
     * Add a heading to the given $mform
     *
     * @param object $mform representing the Moodle form
     * @param string $name the name of this heading
     * @param bool $expanded
     * @param array $a arguments, if any, required for header string (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_heading($mform, $name, $expanded, $a=null) {
        $component = $this->get_subplugin()->get_string_component($name);
        $label = get_string($name, $component, $a);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }


    /**
     * Add a help button to element, only one button per element is allowed.
     *
     * @param object $mform representing the Moodle form
     * @param string $name name of the element to add the item to
     * @param string $strname help string identifier without _help suffix
     * @return void ... but may update $mform.
     */
    public function add_help_button($mform, $name, $strname) {
        $component = $this->get_subplugin()->get_string_component($strname);
        return $mform->addHelpButton($name, $strname, $component);
    }

    /**
     * Add a add_field_static field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this static element
     * @param mixed $value the value to be displayed
     * @param array $attributes (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_static($mform, $name, $value, $attributes=null) {
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement('static', $name, $label, $value);

        // Extract $showhelp boolean value from $attributes under the following conditions:
        // [1] $attributes is the boolean flag whose value is TRUE
        // [2] $attributes is the string whose value is "showhelp"
        // [3] $attributes is an array with a "showhelp" key whose value is either TRUE or "showhelp"
        // [4] $attributes is an object with a "showhelp" property whose value is either TRUE or "showhelp".
        $showhelp = '';
        switch (true) {
            case is_bool($attributes):
                $showhelp = $attributes;
                break;

            case is_string($attributes):
                $showhelp = $attributes;
                break;

            case is_array($attributes):
                if (array_key_exists('showhelp', $attributes)) {
                    $showhelp = $attributes['showhelp'];
                }
                break;

            case is_object($attributes):
                if (property_exists($attributes, 'showhelp')) {
                    $showhelp = $attributes->showhelp;
                }
                break;
        }
        if (is_string($showhelp)) {
            $showhelp = ($showhelp == 'showhelp');
        }
        if ($showhelp) {
            $this->add_help_button($mform, $name, $strname);
        }
    }

    /**
     * Add a text field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this text element
     * @param mixed $type a PARAM_xxx constant value
     * @param mixed $default
     * @param array $attributes (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_text($mform, $name, $type, $default, $attributes=null) {
        if ($attributes) {
            if (is_scalar($attributes)) {
                if (is_numeric($attributes)) {
                    // A single number is assumed to be the 'size'.
                    $attributes = ['size' => $attributes];
                } else {
                    // An on/off attribute e.g. 'disabled'.
                    $attributes = [$attributes => $attributes];
                }
            }
            if (array_key_exists('multiple', $attributes)) {
                $attributes['size'] = min(6, count($options));
            }
        }
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement('text', $name, $label, $attributes);
        $this->add_help_button($mform, $name, $strname);
        $mform->setDefault($name, $default);
        $mform->setType($name, $type);
    }

    /**
     * Add a textarea field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this text element
     * @param mixed $type a PARAM_xxx constant value
     * @param mixed $default
     * @param array $attributes (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_textarea($mform, $name, $type, $default, $attributes=null) {
        if (is_array($attributes)) {
            $attributes = array_merge(['rows' => 5, 'cols' => 40], $attributes);
        }
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement('textarea', $name, $label, $attributes);
        $this->add_help_button($mform, $name, $strname);
        $mform->setDefault($name, $default);
        $mform->setType($name, $type);
    }

    /**
     * Add a selectgroups field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this select element
     * @param array $options to display in the drop menu
     * @param mixed $type a PARAM_xxx constant value
     * @param mixed $default (optional, default=null)
     * @param array $attributes (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_selectgroups($mform, $name, $options, $type, $default=null, $attributes=null) {
        $this->add_field_select($mform, $name, $options, $type, $default, $attributes, 'selectgroups');
    }

    /**
     * Add a select field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this select element
     * @param array $options to display in the drop menu
     * @param mixed $type a PARAM_xxx constant value
     * @param mixed $default (optional, default=null)
     * @param array $attributes (optional, default=null)
     * @param string $elementtype (optional, default="select")
     * @return void ... but may update $mform.
     */
    public function add_field_select($mform, $name, $options, $type, $default=null, $attributes=null, $elementtype='select') {
        if ($attributes) {
            if (is_scalar($attributes)) {
                // An on/off attribute e.g. 'disabled' or 'multiple'.
                $attributes = [$attributes => $attributes];
            }
            if (array_key_exists('multiple', $attributes)) {
                $attributes['size'] = min(6, max(10, count($options)));
            }
        }
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement($elementtype, $name, $label, $options, $attributes);
        $this->add_help_button($mform, $name, $strname);
        $mform->setType($name, $type);
        if ($default) {
            $mform->setDefault($name, $default);
        }
    }

    /**
     * Add a date_time field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this date_time element
     * @param array $attributes (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_datetime($mform, $name, $attributes=null) {
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement('date_time_selector', $name, $label, $attributes);
        $this->add_help_button($mform, $name, $strname);
    }

    /**
     * Add a filemanager field to the given $mform
     * File manager can store multiple files intended to be kept on the server.
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_filemanager($mform, $name, $attributes=null, $options=null) {
        $this->add_field_file('filemanager', $mform, $name, $attributes, $options);
    }

    /**
     * Add a filepicker field to the given $mform
     * File picker is intended to select a disposable file.
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_filepicker($mform, $name, $attributes=null, $options=null) {
        $this->add_field_file('filepicker', $mform, $name, $attributes, $options);
    }

    /**
     * Add a filepicker or filemanager field to the given $mform.
     *
     * @param string $type either "filemanager" or "filepicker"
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void ... but may update $mform.
     */
    public function add_field_file($type, $mform, $name, $attributes=null, $options=null) {
        list($name, $strname) = $this->get_names($name, $attributes);
        $label = $this->get_string($strname);
        $mform->addElement($type, $name, $label, $attributes, $options);
        $this->add_help_button($mform, $name, $strname);
        if ($options) {
            if (is_array($options) && array_key_exists('required', $options)) {
                $required = ($options['required'] ? true : false);
            } else if (is_scalar($options)) {
                $required = ($options == 'required');
            } else {
                $required = false;
            }
            if ($required) {
                $mform->addRule($name, null, 'required');
            }
        }
    }

    /*//////////////////////////////
    // Shared between vocabai_prompt
    // and vocabtool_questionbank.
    //////////////////////////////*/

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param string $type
     * @param string $subtype
     * @return array of AI assistants [config name => path]
     */
    public function get_subplugins($type, $subtype) {
        $plugins = \core_component::get_plugin_list($type);
        foreach ($plugins as $name => $path) {
            $ai = "\\vocabai_$name\\ai";
            if ($ai::create()->subtype == $subtype) {
                continue;
            }
            unset($plugins[$name]);
        }
        return $plugins;
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param string $subtype on of the \mod_vocab\aibase::SUBTYPE_XXX constants.
     * @param boolean $optional TRUE if this field is optional, otherwise FALSE
     * @return array of AI assistants [config name => localized name]
     */
    public function get_assistant_options($subtype, $optional=false) {
        global $DB;

        // Get all relevant contexts (activity, course, coursecat, site).
        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($ctxselect, $ctxparams) = $DB->get_in_or_equal($contexts);

        // Get all available AI assistants.
        $type = 'vocabai';
        $plugins = $this->get_subplugins($type, $subtype);

        if (empty($plugins)) {
            return null;
        }

        $prefix = $type.'_';
        $prefixlen = strlen($prefix);

        // Prefix all the plugin names with the $prefix string
        // and get create the sql conditions.
        $plugins = array_keys($plugins);

        $plugins = substr_replace($plugins, $prefix, 0, 0);
        list($select, $params) = $DB->get_in_or_equal($plugins);

        $select = "contextid $ctxselect AND subplugin $select";
        $params = array_merge($ctxparams, $params);

        if ($options = $DB->get_records_select_menu('vocab_config', $select, $params, 'id', 'id, subplugin')) {
            $options = array_unique($options); // Remove duplicates.
            foreach ($options as $id => $subplugin) {
                $name = substr($subplugin, $prefixlen);
                $options[$id] = get_string($name, $subplugin);
            }
            $options = array_filter($options); // Remove blanks.
            asort($options);
            if ($optional) {
                $options = ([0 => get_string('none')] + $options);
            }
        }
        return $options;
    }

    /**
     * Get a list of AI config options that are available to the current user and context.
     *
     * @param string $type of config ("prompts" or "formats")
     * @param mixed $namefields array mapping fields' DB names to their form names.
     * @param string $selectstring name of string to display as first option
     * @param boolean $optional TRUE if this field is optional, otherwise FALSE.
     * @return array of AI config options [config id => config name]
     */
    public function get_config_options($type, $namefields, $selectstring='', $optional=false) {
        global $DB;
        $options = [];

        // Expand $namefields into a array of $namefields.
        if (is_string($namefields)) {
            if ($namefields == '*' || $namefields == 'all') {
                $namefields = '\\vocabai_'.$type.'\\ai';
                $namefields = $namefields::get_settingnames();
            } else {
                $namefields = explode(',', $namefields);
                $namefields = array_map('trim', $namefields);
                $namefields = array_filter($namefields);
            }
            // Assume DB field names are the same as form field names.
            $namefields = array_combine($namefields, $namefields);
        }
        $countfields = count($namefields);
        $namefield = key($namefields);

        // Get all relevant contexts (activity, course, coursecat, site).
        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($where, $params) = $DB->get_in_or_equal($contexts);

        // Set up SQL to extract record of the required $type.
        $select = 'vcs.*';
        $from = '{vocab_config_settings} vcs '.
                'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id';
        $where = "vc.contextid $where AND vc.subplugin = ?";
        $params[] = "vocabai_$type"; // The AI subplugin type.
        if ($countfields == 1) {
            $where .= ' AND vcs.name = ?';
            $params[] = $namefield;
        }
        $sql = "SELECT $select FROM $from WHERE $where";
        if ($records = $DB->get_records_sql($sql, $params)) {
            foreach ($records as $record) {
                $configid = $record->configid;
                $name = $record->name;
                $value = $record->value;
                if ($countfields == 1) {
                    if ($name == $namefield) {
                        $options[$configid] = $value;
                    }
                } else if (array_key_exists($name, $namefields)) {
                    if (empty($options[$configid])) {
                        $options[$configid] = (object)[];
                    }
                    $formname = $namefields[$name];
                    $options[$configid]->$formname = $value;
                }
            }
        }
        if ($countfields > 1) {
            ksort($options);
        } else {
            asort($options);
            if ($optional) {
                $options = ([0 => get_string('none')] + $options);
            } else if (count($options) > 1) {
                if ($selectstring) {
                    $selectstring = $this->get_string($selectstring);
                }
                $options = ([0 => $selectstring] + $options);
            }
        }
        return $options;
    }

    /**
     * get_question_formats
     *
     * @return array $formats of question formats for which we can generate questions.
     */
    public static function get_question_formats() {
        // ToDo: Could include aiken, hotpot, missingword, multianswer.
        return self::get_question_plugins('qformat', ['gift', 'multianswer', 'xml']);
    }

    /**
     * get_question_types
     *
     * @return array $types of question types for which we can generate questions.
     */
    public static function get_question_types() {
        // ToDo: Could include ordering, essayautograde, speakautograde and sassessment.
        $include = ['match', 'multianswer', 'multichoice', 'shortanswer', 'truefalse'];
        $order = ['multichoice', 'truefalse', 'match', 'shortanswer', 'multianswer'];
        return self::get_question_plugins('qtype', $include, $order);
    }

    /**
     * Get question plugins ("qtype" or "qformat")
     *
     * @param string $plugintype
     * @param array $include (optional, default=null)
     * @param array $order (optional, default=[])
     * @return array $plugins of question formats for which we can generate questions.
     */
    public static function get_question_plugins($plugintype, $include=null, $order=[]) {

        // Get the full list of plugins of the required type.
        $plugins = \core_component::get_plugin_list($plugintype);

        // Remove items that are not in the $include array.
        foreach (array_keys($plugins) as $name) {
            if ($include === null || in_array($name, $include)) {
                $plugins[$name] = get_string('pluginname', $plugintype.'_'.$name);
            } else {
                unset($plugins[$name]);
            }
        }

        // Sort items alphabetically (maintain key association).
        asort($plugins);

        // Ensure first few items are the common ones.
        $order = array_flip($order);
        foreach (array_keys($order) as $name) {
            if (array_key_exists($name, $plugins)) {
                $order[$name] = $plugins[$name];
            } else {
                unset($order[$name]);
            }
        }
        $plugins = $order + $plugins;

        return $plugins;
    }

    /**
     * get_question_type_text
     *
     * @param string $qtype a question type e.g. "multichoice", "truefalse"
     * @return string human readable text version of the given $qtype
     */
    public static function get_question_type_text($qtype) {
        $qtypes = self::get_question_types();
        if (array_key_exists($qtype, $qtypes)) {
            return $qtypes[$qtype];
        } else {
            // Illegal value - shouldn't happen !!
            return $qtype;
        }
    }

    /**
     * Adds a form field for selecting an AI text assistant.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param array $options The available AI text assistants.
     * @return void ... but may update $mform.
     */
    public function add_field_textassistant($mform, $options) {
        $this->add_field_ai_assistant($mform, 'textassistant', $options, 'chatgpt');
    }

    /**
     * Adds a form field for selecting a tuning file.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param array $options The available tuning files.
     * @return void ... but may update $mform.
     */
    public function add_field_tuningfile($mform, $options) {
        $this->add_field_ai_assistant($mform, 'file', $options, 'files');
    }

    /**
     * Adds a form field for selecting an AI audio assistant.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param array $options The available AI audio assistants.
     * @return void ... but may update $mform.
     */
    public function add_field_audioassistant($mform, $options) {
        $this->add_field_ai_assistant($mform, 'audioassistant', $options, 'tts');
    }

    /**
     * Adds a form field for selecting an AI image assistant.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param array $options The available AI image assistants.
     * @return void ... but may update $mform.
     */
    public function add_field_imageassistant($mform, $options) {
        $this->add_field_ai_assistant($mform, 'imageassistant', $options, 'dalle');
    }

    /**
     * Adds a form field for selecting an AI video assistant.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param array $options The available AI video assistants.
     * @return void ... but may update $mform.
     */
    public function add_field_videoassistant($mform, $options) {
        $this->add_field_ai_assistant($mform, 'videoassistant', $options, 'tts');
    }

    /**
     * Adds a form field for selecting an AI assistant.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param string $name Full form field name e.g. "textassistant".
     * @param array $options The available AI assistants.
     * @param string $defaulttype the name of the default AI type.
     * @return void ... but may update $mform.
     */
    public function add_field_ai_assistant($mform, $name, $options, $defaulttype) {
        // Remove "assistant" from $name to get an AI type.
        // E.g. file, image, audio, video.
        $type = str_replace('assistant', '', $name);
        if (empty($options)) {
            $cmid = $this->get_vocab()->cm->id;
            $url = new \moodle_url('/mod/vocab/ai/'.$defaulttype.'/index.php', ['id' => $cmid]);
            $msg = \html_writer::link($url, $this->get_string('clicktoadd'.$type));
            $msg = $this->get_string('no'.$type.'sfound', $msg);
            $this->add_field_static($mform, $name, $msg, 'showhelp');
        } else {
            $this->add_field_select($mform, $name, $options, PARAM_INT);
        }
    }

    /**
     * Adds a form field for selecting a parent question category.
     *
     * @param MoodleQuickForm $mform The form to which the field should be added.
     * @param string $name of the form field.
     * @param int $defaultid the id of the default parent category (optional, default=0.
     * @return void ... but may update $mform.
     */
    public function add_parentcategory($mform, $name, $defaultid=0) {

        // Get the course context.
        $courseid = $this->get_vocab()->course->id;
        $context = \context_course::instance($courseid);

        // Fetch the list of question categories in this course.
        $categories = $this->get_question_categories();

        // Get the name of the default question category for this course.
        if ($defaultid == 0) {
            $defaultname = $context->get_context_name(false, true);
            $defaultname = get_string('defaultfor', 'question', $defaultname);
            $defaultname = shorten_text($defaultname, 255);

            // Extract the id of the default question category in this course.
            $defaultid = array_search($defaultname, $categories);
            if ($defaultid === false) {
                $defaultid = 0; // Shouldn't happen !!
            }
        }

        $strname = 'parentcategory';
        $label = $this->get_string($strname);

        $elements = [
            $mform->createElement('select', 'id', '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories()),
        ];
        $mform->addGroup($elements, $name, $label);
        $this->add_help_button($mform, $name, $strname);

        $elementname = $name.'[id]';
        $mform->setType($elementname, PARAM_INT);
        $mform->setDefault($elementname, $defaultid);
    }

    /**
     * Generates a link to the "Manage question categories" page.
     *
     * @return string HTML link to the question category management page.
     */
    public function link_to_managequestioncategories() {
        $link = '/question/bank/managecategories/category.php';
        $params = ['courseid' => $this->get_vocab()->course->id];
        $link = new \moodle_url($link, $params);

        $text = $this->get_string('managequestioncategories');
        $params = ['onclick' => "this.target='VOCAB'"];
        $link = \html_writer::link($link, $text, $params);

        $params = ['class' => 'w-100 pl-1'];
        return \html_writer::tag('small', $link, $params);
    }

    /**
     * Retrieves the list of question categories for the current course.
     *
     * @return array An array of question categories.
     */
    public function get_question_categories() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/questionlib.php');

        $courseid = $this->get_vocab()->course->id;
        $coursecontext = \context_course::instance($courseid);
        $coursecategory = $this->get_top_question_category($coursecontext->id, true);

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
            return [];
        }
    }

    /**
     * Gets the top question category in the given course context.
     * This function can optionally create the top category if it doesn't exist.
     *
     * This function mimics question_get_top_category() in "lib/questionlib.php",
     * but does not insist on CONTEXT_MODULE.
     *
     * @param int $contextid A context id.
     * @param bool $create Whether create a top category if it doesn't exist.
     * @return bool|stdClass The top question category for that context, or false if none.
     */
    public function get_top_question_category($contextid, $create = false) {
        global $DB;

        $table = 'question_categories';
        $params = ['contextid' => $contextid, 'parent' => 0];
        if ($category = $DB->get_record($table, $params)) {
            return $category;
        }

        if ($create) {
            $category = (object)[
                'name' => 'top', // Name will be localised at the display time.
                'contextid' => $contextid,
                'info' => '',
                'parent' => 0,
                'sortorder' => 0,
                'stamp' => make_unique_id_code(),
            ];
            if ($category->id = $DB->insert_record($table, $category)) {
                return $category;
            }
        }

        return false;
    }

    /**
     * Adds a group of subcategory checkboxes to a Moodle form.
     *
     * @param MoodleQuickForm $mform The form object to add elements to.
     * @param string $name The base name for the checkbox group.
     * @param int $defaultvalue Bitmask representing default checked values.
     * @param string $defaultcustomname (Optional) Default text for the custom name field.
     *
     * @return void ... but may update $mform.
     */
    public function add_subcategories($mform, $name, $defaultvalue, $defaultcustomname='') {
        $form = '\\vocabtool_questionbank\\form';
        return $this->add_checkboxes(
            $mform, $name, 'subcategories',
            self::get_subcategory_types(),
            $form::SUBCAT_NONE, $form::SUBCAT_CUSTOMNAME,
            $defaultvalue, $defaultcustomname
        );
    }

    /**
     * Adds a group of question tag checkboxes to a Moodle form.
     *
     * @param MoodleQuickForm $mform The form object to add elements to.
     * @param string $name The base name for the checkbox group.
     * @param int $defaultvalue Bitmask representing default checked values.
     * @param string $defaultcustomname (Optional) Default text for the custom tag field.
     *
     * @return void ... but may update $mform.
     */
    public function add_questiontags($mform, $name, $defaultvalue, $defaultcustomname='') {
        $form = '\\vocabtool_questionbank\\form';
        return $this->add_checkboxes(
            $mform, $name, 'questiontags',
            $this->get_questiontag_types(),
            $form::QTAG_NONE, $form::QTAG_CUSTOMTAGS,
            $defaultvalue, $defaultcustomname
        );
    }

    /**
     * Adds a custom group of checkboxes with optional text input to a Moodle form.
     *
     * @param MoodleQuickForm $mform The form object to add elements to.
     * @param string $name The base name for the checkbox group.
     * @param string $strname The string identifier used for the group label and help button.
     * @param array $options Associative array of checkbox values and their labels.
     * @param int $valuenone The value representing the 'none' option (disables others).
     * @param int $valuecustom The value that triggers display of the custom text input.
     * @param int $defaultvalue Bitmask representing default checked values.
     * @param string $defaultcustomname (Optional) Default text for the custom input field.
     *
     * @return void ... but may update $mform.
     */
    public function add_checkboxes($mform, $name, $strname,
                                   $options, $valuenone, $valuecustom,
                                   $defaultvalue, $defaultcustomname='') {

        // The name of the form field containing the custom name/tag string.
        $customname = $name.'[name]';

        $label = $this->get_string($strname);

        // Cache line break element.
        $linebreak = \html_writer::tag('span', '', ['class' => 'w-100']);

        foreach ($options as $value => $text) {
            $elements[] = $mform->createElement('checkbox', $value, $text);
            if ($value == $valuecustom) {
                $elements[] = $mform->createElement('text', 'name', '', ['size' => 20]);
            }
            $elements[] = $mform->createElement('html', $linebreak);
        }
        $mform->addGroup($elements, $name, $label, '');
        $this->add_help_button($mform, $name, $strname);
        $elementnone = $name.'['.$valuenone.']';
        foreach ($options as $value => $text) {
            $elementname = $name.'['.$value.']';
            $mform->setType($elementname, PARAM_INT);
            if ($value == $valuecustom) {
                $mform->setType($customname, PARAM_TEXT);
                $mform->setDefault($customname, $defaultcustomname);
                $mform->disabledIf($customname, $elementname, 'notchecked');
            }
            if ($defaultvalue & $value) {
                $mform->setDefault($elementname, 1);
            }
            if ($value > 0) {
                $mform->disabledIf($elementname, $elementnone, 'checked');
            }
        }
    }

    /**
     * Get questiontag types
     *
     * @return array of questiontag types.
     */
    public static function get_questiontag_types() {
        $form = '\\vocabtool_questionbank\\form';
        return [
            $form::QTAG_NONE => get_string('none'),
            $form::QTAG_AI => get_string('ai_generated', 'mod_vocab'),
            $form::QTAG_PROMPTHEAD => get_string('prompthead', 'mod_vocab'),
            $form::QTAG_PROMPTTAIL => get_string('prompttail', 'mod_vocab'),
            $form::QTAG_MEDIATYPE => get_string('mediatype', 'mod_vocab'),
            $form::QTAG_WORD => get_string('word', 'mod_vocab'),
            $form::QTAG_QUESTIONTYPE => get_string('questiontype', 'mod_vocab'),
            $form::QTAG_VOCABLEVEL => get_string('vocablevel', 'mod_vocab'),
            $form::QTAG_CUSTOMTAGS => get_string('customtags', 'mod_vocab'),
        ];
    }

    /**
     * Get subcategory types
     *
     * @return array of subcategory types.
     */
    public static function get_subcategory_types() {
        $form = '\\vocabtool_questionbank\\form';
        return [
            $form::SUBCAT_NONE => get_string('none'),
            $form::SUBCAT_CUSTOMNAME => get_string('customname', 'mod_vocab'),
            $form::SUBCAT_SECTIONNAME => get_string('sectionname', 'mod_vocab'),
            $form::SUBCAT_ACTIVITYNAME => get_string('activityname', 'mod_vocab'),
            $form::SUBCAT_WORD => get_string('word', 'mod_vocab'),
            $form::SUBCAT_QUESTIONTYPE => get_string('questiontype', 'mod_vocab'),
            $form::SUBCAT_VOCABLEVEL => get_string('vocablevel', 'mod_vocab'),
            $form::SUBCAT_PROMPTHEAD => get_string('prompthead', 'mod_vocab'),
            $form::SUBCAT_PROMPTTAIL => get_string('prompttail', 'mod_vocab'),
        ];
    }

    /**
     * Get status types
     *
     * @return array of status types.
     */
    public function get_status_types() {
        $tool = $this->get_subplugin();
        return [
            $tool::TASKSTATUS_NOTSET => $this->get_string('taskstatus_notset'),
            $tool::TASKSTATUS_QUEUED => $this->get_string('taskstatus_queued'),
            $tool::TASKSTATUS_CHECKING_PARAMS => $this->get_string('taskstatus_checkingparams'),
            $tool::TASKSTATUS_FETCHING_RESULTS => $this->get_string('taskstatus_fetchingresults'),
            $tool::TASKSTATUS_AWAITING_REVIEW => $this->get_string('taskstatus_awaitingreview'),
            $tool::TASKSTATUS_AWAITING_IMPORT => $this->get_string('taskstatus_awaitingimport'),
            $tool::TASKSTATUS_IMPORTING_RESULTS => $this->get_string('taskstatus_importingresults'),
            $tool::TASKSTATUS_ADDING_MULTIMEDIA => $this->get_string('taskstatus_addingmultimedia'),
            $tool::TASKSTATUS_COMPLETED => $this->get_string('taskstatus_completed'),
            $tool::TASKSTATUS_CANCELLED => $this->get_string('taskstatus_cancelled'),
            $tool::TASKSTATUS_FAILED => $this->get_string('taskstatus_failed'),
        ];
    }

    /**
     * Add a form section to import a file.
     *
     * @param moodleform $mform representing the Moodle form
     * @return void ... but may update $mform.
     */
    public function add_importfile($mform) {
        $this->add_heading($mform, 'import', false);

        $name = 'importfile';
        $groupname = $name.'elements';
        $label = $this->get_string($name);
        $options = ['accepted_types' => ['.txt', '.xml']];
        // Perhaps we could also consider csv, xlsx, xls, ods?
        $elements = [
            $mform->createElement('filepicker', $name, $label, '', $options),
            $mform->createElement('submit', $name.'button', $this->get_string('import')),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $this->add_help_button($mform, $groupname, $name);
    }

    /**
     * Add a form section to export a file.
     *
     * @param moodleform $mform representing the Moodle form
     * @return void ... but may update $mform.
     */
    public function add_exportfile($mform) {
        $this->add_exportfile_heading($mform);
        $this->add_exportfile_settings($mform);
    }

    /**
     * Add a export heading and file element to a Moodle form.
     *
     * @param moodleform $mform representing the Moodle form
     * @return void (but will update $mform)
     */
    public function add_exportfile_heading($mform) {
        global $PAGE;
        $this->add_heading($mform, 'export', false);

        $filename = $this->get_vocab()->name;
        $filename = preg_replace('/[ \._]+/', '_', $filename);
        $filename = trim($filename, ' -._');
        $filename = $filename.'.xml';

        $name = 'exportfile';
        $groupname = $name.'elements';
        $label = $this->get_string($name);
        $elements = [
            $mform->createElement('text', $name, $label, '', ['size' => 20]),
            $mform->createElement('submit', $name.'button', $this->get_string('export')),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $this->add_help_button($mform, $groupname, $name);
        $mform->setDefault($groupname.'['.$name.']', $filename);
        $mform->setType($groupname.'['.$name.']', PARAM_FILE);

        $PAGE->requires->js_call_amd('mod_vocab/export', 'init');
    }

    /**
     * Add export settings to a Moodle form.
     *
     * @param moodleform $mform representing the Moodle form
     * @return void ... but may update $mform.
     */
    public function add_exportfile_settings($mform) {
    }

    /**
     * Return the value of an optional script parameter.
     *
     * @param mixed $names either the name of a single paramater, of an array of of possible names for the parameter
     * @param mixed $default value
     * @param mixed $type a PARAM_xxx constant value
     * @param integer $depth the maximum depth of array parameters
     * @return mixed, either an actual value from the form, or a suitable default
     */
    public static function get_optional_param($names, $default, $type, $depth=1) {
        return \mod_vocab\activity::get_optional_param($names, $default, $type, $depth);
    }
}
