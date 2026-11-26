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
use core_external\external_value;
use mod_vocab\progress;

/**
 * Web service function mod_vocab_set_game_instance_enabled: hide a game in a
 * Vocabulary activity from students, or show it again.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_game_instance_enabled extends game_instance_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The id of the game instance'),
            'enabled' => new external_value(PARAM_BOOL, 'True to show the game to students, false to hide it'),
        ]);
    }

    /**
     * Hide or show the game.
     *
     * @param int $id
     * @param bool $enabled
     */
    public static function execute(int $id, bool $enabled): void {
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id, 'enabled' => $enabled]);
        self::validate_game_instance($params['id']);
        progress::set_game_instance_enabled($params['id'], $params['enabled']);
    }

    /**
     * Describe the result.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}
