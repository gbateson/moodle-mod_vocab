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

    /**
     * get_record_id
     *
     * @uses $DB
     * @param string $table name of a table in the database
     * @param array $fields of database field names and values
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