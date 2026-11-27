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
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabtool_dictionary
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_dictionary;

/**
 * The dictionary tool: it fills in the dictionary for the words of an activity, with AI,
 * in background tasks, one for each word (see docs/plan-dictionary.md, step 3).
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'dictionary';

    /** @var string the log of the tasks that fill in the dictionary */
    const LOGTABLE = 'vocabtool_dictionary_log';

    /** @var int the task has not been queued yet */
    const TASKSTATUS_NOTSET = 0;

    /** @var int the task is waiting to run */
    const TASKSTATUS_QUEUED = 1;

    /** @var int the task will try again later, e.g. after a rate limit */
    const TASKSTATUS_DELAYED = 2;

    /** @var int the prompt has been sent to the AI */
    const TASKSTATUS_FETCHING_RESULTS = 3;

    /** @var int the reply is being saved in the dictionary */
    const TASKSTATUS_SAVING_RESULTS = 4;

    /** @var int the entry was saved */
    const TASKSTATUS_COMPLETED = 5;

    /** @var int the word already had senses, so it was not sent to the AI */
    const TASKSTATUS_SKIPPED = 6;

    /** @var int the task failed, e.g. the AI's reply could not be used */
    const TASKSTATUS_FAILED = 7;

    /** @var string[] the status codes, and the names of their strings */
    const TASKSTATUS_NAMES = [
        self::TASKSTATUS_NOTSET => 'statusnotset',
        self::TASKSTATUS_QUEUED => 'statusqueued',
        self::TASKSTATUS_DELAYED => 'statusdelayed',
        self::TASKSTATUS_FETCHING_RESULTS => 'statusfetching',
        self::TASKSTATUS_SAVING_RESULTS => 'statussaving',
        self::TASKSTATUS_COMPLETED => 'statuscompleted',
        self::TASKSTATUS_SKIPPED => 'statusskipped',
        self::TASKSTATUS_FAILED => 'statusfailed',
    ];

    /** @var int[] the statuses of tasks that have finished */
    const TASKSTATUS_FINISHED = [self::TASKSTATUS_COMPLETED, self::TASKSTATUS_SKIPPED, self::TASKSTATUS_FAILED];

    /**
     * Add a record to the log table, and queue the background task that fills in the dictionary for its word.
     *
     * @param array $params the log's fields: userid, vocabid, wordid, providerid, promptid,
     *                      wordlang, glosslang, level, sensecount, samplecount and skipexisting.
     * @return int the id of the log record.
     */
    public static function queue_task(array $params): int {
        global $DB;
        $time = time();
        $log = (object) array_merge([
            'taskid' => 0,
            'maxtries' => 3,
            'tries' => 0,
            'skipexisting' => 1,
            'error' => '',
            'counts' => '',
            'prompt' => '',
            'results' => '',
        ], $params, [
            'status' => self::TASKSTATUS_NOTSET,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
        $logid = (int) $DB->insert_record(self::LOGTABLE, $log);

        $task = new task\fill();
        $task->set_userid($log->userid);
        $task->set_custom_data(['logid' => $logid]);
        $taskid = (int) \core\task\manager::queue_adhoc_task($task);
        self::update_log($logid, [
            'taskid' => $taskid,
            'status' => ($taskid ? self::TASKSTATUS_QUEUED : self::TASKSTATUS_FAILED),
        ]);
        return $logid;
    }

    /**
     * Get a record from the log table.
     *
     * @param int $id
     * @return \stdClass|false
     */
    public static function get_log(int $id) {
        global $DB;
        return $DB->get_record(self::LOGTABLE, ['id' => $id]);
    }

    /**
     * Update a record in the log table.
     *
     * @param int $id
     * @param array $params field => value
     */
    public static function update_log(int $id, array $params): void {
        global $DB;
        $DB->update_record(self::LOGTABLE, (object) (['id' => $id, 'timemodified' => time()] + $params));
    }

    /**
     * Get the log records of an activity, newest first, with their words.
     *
     * @param int $vocabid
     * @return \stdClass[]
     */
    public static function get_logs(int $vocabid): array {
        global $DB;
        $sql = 'SELECT l.*, w.word
                  FROM {' . self::LOGTABLE . '} l
             LEFT JOIN {vocab_words} w ON w.id = l.wordid
                 WHERE l.vocabid = ?
              ORDER BY l.timecreated DESC, l.id DESC';
        return $DB->get_records_sql($sql, [$vocabid]);
    }

    /**
     * Get the words of an activity whose tasks have not finished yet.
     *
     * @param int $vocabid
     * @return int[] word ids.
     */
    public static function get_pending_wordids(int $vocabid): array {
        global $DB;
        [$select, $params] = $DB->get_in_or_equal(self::TASKSTATUS_FINISHED, SQL_PARAMS_NAMED, 'status', false);
        $params['vocabid'] = $vocabid;
        $select = "vocabid = :vocabid AND status $select";
        return array_map('intval', $DB->get_fieldset_select(self::LOGTABLE, 'DISTINCT wordid', $select, $params));
    }
}
