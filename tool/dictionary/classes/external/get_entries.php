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

namespace vocabtool_dictionary\external;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service function vocabtool_dictionary_get_entries: get the dictionary entries
 * of some words in a Vocabulary activity's word list.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_entries extends entry_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'vocabid' => new external_value(PARAM_INT, 'The id of the Vocabulary activity'),
            'wordids' => new external_multiple_structure(new external_value(PARAM_INT, 'The id of a word')),
        ]);
    }

    /**
     * Get the entries.
     *
     * @param int $vocabid
     * @param int[] $wordids
     * @return array
     */
    public static function execute(int $vocabid, array $wordids): array {
        $params = self::validate_parameters(self::execute_parameters(), ['vocabid' => $vocabid, 'wordids' => $wordids]);
        $context = self::validate_vocab($params['vocabid']);
        return ['entries' => self::get_activity_entries($params['vocabid'], $context, $params['wordids'])];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['entries' => self::entries_structure()]);
    }
}
