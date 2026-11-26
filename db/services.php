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
 * db/services.php: External functions and service definitions for mod_vocab
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

$functions = [];

// The external API of Moodle 4.2 and later.
if (class_exists('\\core_external\\external_api')) {
    $functions['mod_vocab_view_vocab'] = [
        'classname' => \mod_vocab\external\view_vocab::class,
        'description' => 'Record that the user has viewed a Vocabulary activity, for logs and activity completion.',
        'type' => 'write',
        'capabilities' => 'mod/vocab:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ];

    // Manage the games of an activity, on the activity's page.
    $functions['mod_vocab_delete_game_instance'] = [
        'classname' => \mod_vocab\external\delete_game_instance::class,
        'description' => 'Remove a game from a Vocabulary activity, or hide it if it has been played.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocab:manage',
    ];
    $functions['mod_vocab_reorder_game_instances'] = [
        'classname' => \mod_vocab\external\reorder_game_instances::class,
        'description' => 'Put the games of a Vocabulary activity in a new order.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocab:manage',
    ];
    $functions['mod_vocab_set_game_instance_enabled'] = [
        'classname' => \mod_vocab\external\set_game_instance_enabled::class,
        'description' => 'Hide a game in a Vocabulary activity from students, or show it again.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocab:manage',
    ];
}
