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
 * settings.php
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // The question types for which the question bank tool can generate questions.
    $settings->add(new admin_setting_heading(
        'vocabtool_questionbank/heading',
        new lang_string('pluginname', 'vocabtool_questionbank'),
        ''
    ));
    $choices = [];
    $defaults = [];
    if (!during_initial_install()) {
        $choices = \mod_vocab\subpluginform::get_importable_question_types();
        foreach (\mod_vocab\subpluginform::get_default_question_types() as $qtype) {
            if (isset($choices[$qtype])) {
                $defaults[$qtype] = 1;
            }
        }
    }
    $settings->add(new admin_setting_configmulticheckbox(
        'vocabtool_questionbank/qtypes',
        new lang_string('allowedqtypes', 'vocabtool_questionbank'),
        new lang_string('allowedqtypes_desc', 'vocabtool_questionbank'),
        $defaults,
        $choices
    ));
}
