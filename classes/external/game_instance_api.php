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

/**
 * Shared code for the web service functions that manage the games in a
 * Vocabulary activity, on the activity's page.
 *
 * This uses the external API of Moodle 4.2 and later; on earlier versions the
 * functions are not defined (see db/services.php).
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class game_instance_api extends external_api {
    /**
     * Check that the user can manage the games of an activity, and set up its context.
     *
     * @param int $vocabid
     * @return \context_module
     */
    protected static function validate_vocab(int $vocabid): \context_module {
        $cm = get_coursemodule_from_instance('vocab', $vocabid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vocab:manage', $context);
        return $context;
    }

    /**
     * Check that the user can manage a game instance's activity, and get the instance.
     *
     * @param int $id the id of the game instance.
     * @return \stdClass the game instance.
     */
    protected static function validate_game_instance(int $id): \stdClass {
        global $DB;
        $instance = $DB->get_record('vocab_game_instances', ['id' => $id], '*', MUST_EXIST);
        self::validate_vocab((int) $instance->vocabid);
        return $instance;
    }
}
