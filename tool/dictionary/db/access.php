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
 * Capabilities for the dictionary tool.
 *
 * The dictionary is shared by the whole site, so a change made in one course is seen in
 * every course. Teachers can add items and look after drafts; approving an item, or
 * changing one that has been approved, is for managers.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Add items to the dictionary, and edit, reject or restore draft and rejected items.
    'vocabtool/dictionary:edit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_SPAM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Approve items in the dictionary, and edit, reject or restore approved items.
    'vocabtool/dictionary:approve' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask' => RISK_SPAM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
