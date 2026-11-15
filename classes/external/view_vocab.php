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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Web service function mod_vocab_view_vocab: record that a user has viewed a
 * Vocabulary activity, e.g. in the Moodle app, for logs and activity completion.
 *
 * This uses the external API of Moodle 4.2 and later; on earlier versions the
 * function is not defined (see db/services.php).
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_vocab extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'vocabid' => new external_value(PARAM_INT, 'The id of the Vocabulary activity'),
        ]);
    }

    /**
     * Record that the current user has viewed the activity.
     *
     * @param int $vocabid
     * @return array status and warnings.
     */
    public static function execute(int $vocabid): array {
        global $DB;
        ['vocabid' => $vocabid] = self::validate_parameters(self::execute_parameters(), ['vocabid' => $vocabid]);
        $instance = $DB->get_record('vocab', ['id' => $vocabid], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($instance, 'vocab');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vocab:view', $context);

        $vocab = new \mod_vocab\activity($course, $cm, $instance, $context);
        $vocab->trigger_viewed_event_and_completion();
        return ['status' => true, 'warnings' => []];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the view was recorded'),
            'warnings' => new external_warnings(),
        ]);
    }
}
