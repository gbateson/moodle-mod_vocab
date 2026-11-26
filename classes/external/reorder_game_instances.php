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
use core_external\external_multiple_structure;
use core_external\external_value;
use mod_vocab\progress;

/**
 * Web service function mod_vocab_reorder_game_instances: put the games of a
 * Vocabulary activity in a new order.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reorder_game_instances extends game_instance_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'vocabid' => new external_value(PARAM_INT, 'The id of the Vocabulary activity'),
            'ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'The id of a game instance'),
                'The game instances, in their new order'
            ),
        ]);
    }

    /**
     * Put the games in the new order. Ids of games in other activities are ignored.
     *
     * @param int $vocabid
     * @param int[] $ids
     */
    public static function execute(int $vocabid, array $ids): void {
        $params = self::validate_parameters(self::execute_parameters(), ['vocabid' => $vocabid, 'ids' => $ids]);
        self::validate_vocab($params['vocabid']);
        progress::reorder_game_instances($params['vocabid'], $params['ids']);
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
