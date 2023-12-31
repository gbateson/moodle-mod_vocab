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
 * db/access.php: Capabilities for mod_vocab
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ability to add a new vocab activity to the course.
    'mod/vocab:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask' => RISK_XSS,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    // Edit the vocab activity settings.
    'mod/vocab:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_SPAM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // View the vocab activity reports.
    'mod/vocab:viewreports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_PERSONAL,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Delete attempts using the overview report.
    'mod/vocab:deleteattempts' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_DATALOSS,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Preview the activity and games to
    // check the layout and behavior.
    'mod/vocab:preview' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Ability to see that the vocab activity exists,
    // and the basic information about it,
    // for example the start date and time limit.
    'mod/vocab:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Ability to do the vocab activity as a 'Student'.
    'mod/vocab:attempt' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_SPAM,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],

    // Ability for a 'Student' to review their previous attempts.
    // Review by 'Teachers' is controlled by "mod/vocab:viewreports".
    'mod/vocab:reviewmyattempts' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/quiz:attempt',
    ],
];
