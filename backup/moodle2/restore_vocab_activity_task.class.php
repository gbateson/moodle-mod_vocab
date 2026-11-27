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
 * The task that restores a Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/vocab/backup/moodle2/restore_vocab_stepslib.php');

/**
 * Restore a Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */
class restore_vocab_activity_task extends restore_activity_task {
    /**
     * The activity has no settings of its own for restores.
     */
    protected function define_my_settings() {
    }

    /**
     * The activity has one structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_vocab_activity_structure_step('vocab_structure', 'vocab.xml'));
    }

    /**
     * The content that may contain links to decode.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('vocab', ['intro'], 'vocab'),
        ];
    }

    /**
     * The rules to decode links encoded by backup_vocab_activity_task::encode_content_links().
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('VOCABVIEWBYID', '/mod/vocab/view.php?id=$1', 'course_module'),
            new restore_decode_rule('VOCABINDEX', '/mod/vocab/index.php?id=$1', 'course'),
        ];
    }

    /**
     * The rules to restore the activity's legacy logs.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('vocab', 'add', 'view.php?id={course_module}', '{vocab}'),
            new restore_log_rule('vocab', 'update', 'view.php?id={course_module}', '{vocab}'),
            new restore_log_rule('vocab', 'view', 'view.php?id={course_module}', '{vocab}'),
        ];
    }

    /**
     * The rules to restore the course's legacy logs about Vocabulary activities.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('vocab', 'view all', 'index.php?id={course}', null),
        ];
    }
}
