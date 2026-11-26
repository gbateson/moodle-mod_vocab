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

namespace mod_vocab\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_vocab\progress;

/**
 * Web service function mod_vocab_delete_game_instance: remove a game from a
 * Vocabulary activity. A game that has been played is hidden, not deleted, so
 * that students' scores are kept.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_game_instance extends game_instance_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The id of the game instance'),
        ]);
    }

    /**
     * Delete or hide the game.
     *
     * @param int $id
     * @return array whether the game was deleted (true) or hidden (false).
     */
    public static function execute(int $id): array {
        ['id' => $id] = self::validate_parameters(self::execute_parameters(), ['id' => $id]);
        self::validate_game_instance($id);
        return ['deleted' => progress::delete_game_instance($id)];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'True if the game was deleted, false if it was hidden'),
        ]);
    }
}
