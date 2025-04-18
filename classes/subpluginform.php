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
     * @return void (but will update $mform)
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
     * @return void, but may update $mform
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
     * @return void (but may update $mform)
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
     * @return void (but may update $mform)
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
     * @return void (but may update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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

    /**
     * Add a form section to import a file.
     *
     * @param moodleform $mform representing the Moodle form
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
     * @return void (but will update $mform)
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
