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
use core_external\external_single_structure;
use core_external\external_value;
use vocabtool_dictionary\tool;

/**
 * Web service function vocabtool_dictionary_set_status: approve, reject or restore an item
 * in the dictionary entry of a word in a Vocabulary activity's word list.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_status extends entry_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'vocabid' => new external_value(PARAM_INT, 'The id of the Vocabulary activity'),
            'itemtype' => new external_value(PARAM_ALPHA, 'The kind of item: sense, definition or sample'),
            'itemid' => new external_value(PARAM_INT, 'The id of the item'),
            'status' => new external_value(PARAM_INT, 'The new status: 0 draft, 1 approved, 2 rejected'),
        ]);
    }

    /**
     * Change the item's status.
     *
     * @param int $vocabid
     * @param string $itemtype
     * @param int $itemid
     * @param int $status
     * @return array the lemma of the item, so that the entries of its words can be shown again.
     */
    public static function execute(int $vocabid, string $itemtype, int $itemid, int $status): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'vocabid' => $vocabid,
            'itemtype' => $itemtype,
            'itemid' => $itemid,
            'status' => $status,
        ]);
        $context = self::validate_vocab($params['vocabid']);
        $item = tool::set_item_status($params['vocabid'], $context, $params['itemtype'], $params['itemid'], $params['status']);
        return ['lemmaid' => (int) $item->lemmaid];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'lemmaid' => new external_value(PARAM_INT, 'The id of the lemma of the item'),
        ]);
    }
}
