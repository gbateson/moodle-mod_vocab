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
 * tool/questionbank/classes/privacy/provider.php
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_questionbank\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider as userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider as preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The personal data that the question bank tool stores: the log of the tasks
 * that teachers set up to make questions with AI (with the prompts that were
 * sent, and the replies), and each teacher's choices for each prompt.
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, plugin_provider, preference_provider, userlist_provider {
    /** The user preferences that hold a teacher's choices for a prompt start with this. */
    const PREFERENCE = 'vocabtool_questionbank_prompt_';

    /** The log table. */
    const LOGTABLE = 'vocabtool_questionbank_log';

    /**
     * Describe the personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::LOGTABLE, [
            'userid' => 'privacy:metadata:log:userid',
            'wordid' => 'privacy:metadata:log:wordid',
            'prompt' => 'privacy:metadata:log:prompt',
            'results' => 'privacy:metadata:log:results',
            'error' => 'privacy:metadata:log:error',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_user_preference(self::PREFERENCE, 'privacy:metadata:preference:prompt');
        return $collection;
    }

    /**
     * Get the activities in which a teacher has set up tasks.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {" . self::LOGTABLE . "} l
                  JOIN {course_modules} cm ON cm.instance = l.vocabid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE l.userid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['modulename' => 'vocab', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Get the teachers who have set up tasks in an activity.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($vocabid = self::get_vocabid($userlist->get_context())) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {" . self::LOGTABLE . "} WHERE vocabid = :vocabid",
                ['vocabid' => $vocabid]
            );
        }
    }

    /**
     * Get the id of the Vocabulary activity of a context.
     *
     * @param \context $context
     * @return int the id, or 0 if the context is not a Vocabulary activity.
     */
    protected static function get_vocabid(\context $context): int {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return 0;
        }
        $cm = get_coursemodule_from_id('vocab', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }

    /**
     * Export a teacher's tasks in some activities.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$vocabid = self::get_vocabid($context)) {
                continue;
            }
            $sql = "SELECT l.*, w.word
                      FROM {" . self::LOGTABLE . "} l
                 LEFT JOIN {vocab_words} w ON w.id = l.wordid
                     WHERE l.vocabid = :vocabid AND l.userid = :userid
                  ORDER BY l.timecreated, l.id";
            $logs = [];
            foreach ($DB->get_records_sql($sql, ['vocabid' => $vocabid, 'userid' => $userid]) as $log) {
                $logs[] = (object) [
                    'word' => $log->word,
                    'qtype' => $log->qtype,
                    'qlevel' => $log->qlevel,
                    'qcount' => $log->qcount,
                    'prompt' => $log->prompt,
                    'results' => $log->results,
                    'error' => $log->error,
                    'questionids' => $log->questionids,
                    'timecreated' => transform::datetime($log->timecreated),
                    'timemodified' => transform::datetime($log->timemodified),
                ];
            }
            if ($logs) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'vocabtool_questionbank')],
                    (object) ['tasks' => $logs]
                );
            }
        }
    }

    /**
     * Export a teacher's choices for each prompt.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        global $DB;
        $select = 'userid = :userid AND ' . $DB->sql_like('name', ':name');
        $params = ['userid' => $userid, 'name' => $DB->sql_like_escape(self::PREFERENCE) . '%'];
        foreach ($DB->get_records_select('user_preferences', $select, $params) as $preference) {
            writer::export_user_preference(
                'vocabtool_questionbank',
                $preference->name,
                $preference->value,
                get_string('privacy:metadata:preference:prompt', 'vocabtool_questionbank')
            );
        }
    }

    /**
     * Delete every teacher's tasks in an activity.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($vocabid = self::get_vocabid($context)) {
            $DB->delete_records(self::LOGTABLE, ['vocabid' => $vocabid]);
        }
    }

    /**
     * Delete a teacher's tasks in some activities.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($vocabid = self::get_vocabid($context)) {
                $DB->delete_records(self::LOGTABLE, ['vocabid' => $vocabid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete some teachers' tasks in an activity.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (($vocabid = self::get_vocabid($userlist->get_context())) && $userlist->get_userids()) {
            [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $params['vocabid'] = $vocabid;
            $DB->delete_records_select(self::LOGTABLE, "vocabid = :vocabid AND userid $insql", $params);
        }
    }
}
