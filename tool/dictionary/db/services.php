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
 * Web service functions of the dictionary tool.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [];

// The external API of Moodle 4.2 and later.
if (class_exists('\\core_external\\external_api')) {
    $functions['vocabtool_dictionary_get_entries'] = [
        'classname' => \vocabtool_dictionary\external\get_entries::class,
        'description' => 'Get the dictionary entries of some words in a Vocabulary activity\'s word list.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vocab:manage',
    ];
    $functions['vocabtool_dictionary_set_status'] = [
        'classname' => \vocabtool_dictionary\external\set_status::class,
        'description' => 'Approve, reject or restore an item in the dictionary entry of a word in a Vocabulary activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'vocabtool/dictionary:edit, vocabtool/dictionary:approve',
    ];
}
