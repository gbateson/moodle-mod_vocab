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
     *      creating this form. This value that can be used in get_string().
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
     * Get a string for the subplugin that is displaying this form.
     *
     * @param string $name the name of the required string
     * @param mixed $a (optional, default=null) additional value or values required for the string
     * @return string requested string from the lang pack for the current subplugin
     */
    public function get_string($name, $a=null) {
        return $this->get_subplugin()->get_string($name, $a);
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
     * @param object $mform representing the Moodle form
     * @param string $name the name of this heading
     * @param string $component
     * @param boolean $expanded
     * @return void (but will update $mform)
     */
    public function add_heading($mform, $name, $component, $expanded) {
        $label = get_string($name, $component);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
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
        $label = $this->get_string($name);
        $mform->addElement('text', $name, $label, $attributes);
        $mform->addHelpButton($name, $name, $this->subpluginname);
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
        if ($attributes) {
            $attributes['rows'] = 5;
            $attributes['cols'] = '40';
        }
        $label = $this->get_string($name);
        $mform->addElement('textarea', $name, $label, $attributes);
        $mform->addHelpButton($name, $name, $this->subpluginname);
        $mform->setDefault($name, $default);
        $mform->setType($name, $type);
    }

    /**
     * Add a select field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name the name of this select element
     * @param array $options to display in the drop menu
     * @param mixed $type a PARAM_xxx constant value
     * @param mixed $default
     * @param array $attributes (optional, default=null)
     * @return void (but will update $mform)
     */
    public function add_field_select($mform, $name, $options, $type, $default, $attributes=null) {
        if ($attributes) {
            if (is_scalar($attributes)) {
                // An on/off attribute e.g. 'disabled' or 'multiple'.
                $attributes = [$attributes => $attributes];
            }
            if (array_key_exists('multiple', $attributes)) {
                $attributes['size'] = min(6, count($options));
            }
        }
        $label = $this->get_string($name);
        $mform->addElement('select', $name, $label, $options, $attributes);
        $mform->addHelpButton($name, $name, $this->subpluginname);
        $mform->setType($name, $type);
        $mform->setDefault($name, $default);
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
        $label = $this->get_string($name);
        $mform->addElement('date_time_selector', $name, $label, $attributes);
        $mform->addHelpButton($name, $name, $this->subpluginname);
    }

    /**
     * Add a filepicker field to the given $mform
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void (but will update $mform)
     */
    public function add_field_filepicker($mform, $name, $attributes=null, $options=null) {
        $label = $this->get_string($name);
        $mform->addElement('filepicker', $name, $label, $attributes, $options);
        $mform->addHelpButton($name, $name, $this->subpluginname);
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
}
