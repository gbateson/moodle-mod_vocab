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
 * tool/checkfiles/classes/form.php
 *
 * @package    vocabtool_checkfiles
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_checkfiles;

/**
 * form
 *
 * @package    vocabtool_checkfiles
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /**
     * Defines the form elements for selecting a target version and submitting the upgrade task.
     *
     * Adds a version selector and a submit button labeled "Redo upgrade".
     */
    public function definition() {
        $mform = $this->_form;
        $this->set_form_id($mform);

        $mform->addElement('html', \html_writer::tag('p', 'Context'));
        $mform->addElement('html', \html_writer::tag('p', 'Question category'));

        // Use "Check files" as the label for the submit button.
        $label = $this->get_string('checkfiles');
        $this->add_action_buttons(true, $label);
    }
}
