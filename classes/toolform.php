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

// Fetch the parent class.
require_once($CFG->dirroot.'/mod/vocab/classes/form.php');

/**
 * \mod_vocab\toolform
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
abstract class toolform extends \mod_vocab\form {

    /**
     * get_vocab
     *
     * @return object $vocab
     */
    public function get_vocab() {
        if (isset($this->_customdata['tool'])) {
            return $this->_customdata['tool']->vocab;
        }
        return null;
    }

    public function add_field_text($mform, $name, $type, $default, $attributes=null) {
        if ($attributes) {
            if (is_scalar($attributes)) {
                if (is_numeric($attributes)) {
                    $attributes = array('size' => $attributes);
                } else {
                    // e.g. 'disabled'
                    $attributes = array($attributes => $attributes);
                }
            }
            if (array_key_exists('multiple', $attributes)) {
                $attributes['size'] = min(6, count($options));
            }
        }
        $label = get_string($name, $this->tool);
        $mform->addElement('text', $name, $label, $attributes);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setDefault($name, $default);
        $mform->setType($name, $type);
    }

    public function add_field_select($mform, $name, $options, $type, $default, $attributes=null) {
        if ($attributes) {
            if (is_scalar($attributes)) {
                // e.g. 'disabled' or 'multiple'
                $attributes = array($attributes => $attributes);
            }
            if (array_key_exists('multiple', $attributes)) {
                $attributes['size'] = min(6, count($options));
            }
        }
        $label = get_string($name, $this->tool);
        $mform->addElement('select', $name, $label, $options, $attributes);
        $mform->addHelpButton($name, $name, $this->tool);
        $mform->setType($name, $type);
        $mform->setDefault($name, $default);
    }

    public function add_field_filepicker($mform, $name, $attributes=null, $options=null) {
        $label = get_string($name, $this->tool);
        $mform->addElement('filepicker', $name, $label, $attributes, $options);
        $mform->addHelpButton($name, $name, $this->tool);
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
     * Get the id of the record that is uniquely identified by an array of
     * field names of values. If no such record exists it will be created.
     * Any field values that are too long for the corresponding database
     * field will be truncated to a suitable length.
     *
     * @uses $DB
     * @param string $table name of a table in the database
     * @param array $fields array of database field names and values
     * @return integer
     */
    public function get_record_id($table, $fields) {
        global $DB;
        $id = $DB->get_field($table, 'id', $fields);
        if ($id === false || $id === 0 || $id === null) {
            $id = $DB->insert_record($table, $fields);
        }
        return $id;
    }
}