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
 * Vocabulary tool to generate questions for the question bank.
 *
 * @package    vocabtool_questionbank
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_questionbank;

/**
 * tool
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'questionbank';

    /** @var string holds the name of the log table */
    const LOGTABLE = 'vocabtool_questionbank_log';

    /** @var int database value signifying the task status is not set yet */
    const TASKSTATUS_NOTSET = 0;

    /** @var int database value signifying the task has been queued */
    const TASKSTATUS_QUEUED = 1;

    /** @var int database value signifying that task parameters are being checked */
    const TASKSTATUS_CHECKING_PARAMS = 2;

    /** @var int database value signifying the task results are being fetched */
    const TASKSTATUS_FETCHING_RESULTS = 3;

    /** @var int database value signifying the task results are awaiting review by a teacher or admin */
    const TASKSTATUS_AWAITING_REVIEW = 4;

    /** @var int database value signifying the task results are ready to be imported into Moodle */
    const TASKSTATUS_AWAITING_IMPORT = 5;

    /** @var int database value signifying the task results are being imported into Moodle */
    const TASKSTATUS_IMPORTING_RESULTS = 6;

    /** @var int database value signifying the task has been completed */
    const TASKSTATUS_COMPLETED = 7;

    /** @var int database value signifying the task was cancelled after being reviewed */
    const TASKSTATUS_CANCELLED = 8;

    /** @var int database value signifying the task failed for some reason, e.g. a program error or unexpected setting */
    const TASKSTATUS_FAILED = 9;

    /**
     * Return a default log record with values initialized to 0 or "".
     *
     * @return object the default log record.
     */
    public static function get_default_log() {
        $time = time();
        return (object)[
            'taskid' => 0,
            'userid' => 0,
            'vocabid' => 0,
            'wordid' => 0,
            'qtype' => '',
            'qlevel' => '',
            'qcount' => 0,
            'qformat' => '',
            'accessid' => 0,
            'promptid' => 0,
            'formatid' => 0,
            'fileid' => 0,
            'parentcatid' => 0,
            'subcattype' => '',
            'subcatname' => '',
            'maxtries' => 0,
            'tries' => 0,
            'status' => 0,
            'review' => 0,
            'error' => '',
            'prompt' => '',
            'results' => '',
            'questionids' => '',
            'timecreated' => $time,
            'timemodified' => $time,
        ];
    }

    /**
     * Add the given $param(eter)s to the given $log.
     *
     * @param object $log
     * @param array $params
     * @return bool TRUE if new values were supplied in $params, otherwise FALSE.
     */
    public static function add_log_params($log, $params) {
        $update = false;
        foreach ($params as $name => $value) {
            if (property_exists($log, $name) && $log->$name != $value) {
                $log->$name = $value;
                $update = true;
            }
        }
        return $update;
    }

    /**
     * Insert a record into the log table.
     * The record will then be used to supply values to the
     * adhoc task that prompts an AI assistant and receives results.
     *
     * @param array $params
     * @return int of newly created record (or FALSE on failure)
     */
    public static function insert_log($params) {
        global $DB;
        $log = self::get_default_log();
        self::add_log_params($log, $params);
        return $DB->insert_record(self::LOGTABLE, $log);
    }

    /**
     * Get a record from the log table using the given $id.
     *
     * @param int $id
     * @return object the record form the log table
     */
    public static function get_log($id) {
        global $DB;
        return $DB->get_record(self::LOGTABLE, ['id' => $id]);
    }

    /**
     * Update a record in the log table.
     *
     * @param int $id
     * @param array $params
     * @return bool, TRUE if log was updated, otherwise FALSE
     */
    public static function update_log($id, $params) {
        global $DB;
        $update = null;
        if ($log = $DB->get_record(self::LOGTABLE, ['id' => $id])) {
            if ($update = self::add_log_params($log, $params)) {
                $log->timemodified = time();
                $update = $DB->update_record(self::LOGTABLE, $log);
            }
        }
        return ($update === null ? false : $update);
    }

    /**
     * Delete records from the log table.
     *
     * @param array $params used to select records for deletion.
     * @return void, but may delete records from the log table
     */
    public static function delete_logs($params) {
        global $DB;
        return $DB->delete_records(self::LOGTABLE, $params);
    }

    /**
     * Get records from the log table using the given $vocabid.
     *
     * @param int $vocabid
     * @return array of records from the log table
     */
    public static function get_logs($vocabid) {
        global $DB;
        $select = 'qbl.*, ta.nextruntime, vw.word';
        $from = '{'.self::LOGTABLE.'} qbl '.
                'LEFT JOIN {task_adhoc} ta ON qbl.taskid = ta.id '.
                'LEFT JOIN {vocab_words} vw ON qbl.wordid = vw.id';
        $where = 'qbl.vocabid = ?';
        $params = [$vocabid];
        return $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
    }
}
