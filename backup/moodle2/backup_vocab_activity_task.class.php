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
 * The task that backs up a Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/vocab/backup/moodle2/backup_vocab_stepslib.php');

/**
 * Back up a Vocabulary activity, with the questions about its words, as mod_quiz does.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */
class backup_vocab_activity_task extends backup_activity_task {
    /**
     * The activity has no settings of its own for backups.
     */
    protected function define_my_settings() {
    }

    /**
     * Back up the activity, then work out which question categories to include.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_vocab_activity_structure_step('vocab_structure', 'vocab.xml'));

        // Use the questions annotated in the structure step to find the question
        // categories that the backup needs, then clean up those annotations.
        $this->add_step(new backup_calculate_question_categories('activity_question_categories'));
        $this->add_step(new backup_delete_temp_questions('clean_temp_questions'));
    }

    /**
     * Encode links to Vocabulary activities, so that they can be restored.
     *
     * @param string $content some HTML that may contain links.
     * @return string the content, with links encoded.
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');

        // Links to the list of Vocabulary activities in a course.
        $search = '/(' . $base . '\/mod\/vocab\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@VOCABINDEX*$2@$', $content);

        // Links to a Vocabulary activity.
        $search = '/(' . $base . '\/mod\/vocab\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@VOCABVIEWBYID*$2@$', $content);

        return $content;
    }
}
