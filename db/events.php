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
 * The events that mod_vocab observes: questions and question categories that
 * games use are deleted (see classes/observer.php). The observers run after
 * the database transaction, if any, is committed.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\question_category_deleted',
        'callback' => '\\mod_vocab\\observer::question_category_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\question_deleted',
        'callback' => '\\mod_vocab\\observer::question_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_module_deleted',
        'callback' => '\\mod_vocab\\observer::course_module_deleted',
        'internal' => false,
    ],
];
