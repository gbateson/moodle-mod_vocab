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

use mod_vocab\dictionary;

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

    /** @var string[] the kinds of dictionary item that can be changed here, and their tables */
    const ITEM_TABLES = [
        'sense' => 'vocab_senses',
        'definition' => 'vocab_definitions', // Definitions and glosses.
        'sample' => 'vocab_samples', // Samples and their translations.
    ];

    /** @var string[] the review statuses, and the names of their strings in mod_vocab */
    const ITEM_STATUS_NAMES = [
        dictionary::STATUS_DRAFT => 'draft',
        dictionary::STATUS_APPROVED => 'approved',
        dictionary::STATUS_REJECTED => 'rejected',
    ];

    /**
     * Can a user change the status of a dictionary item, or edit it?
     * The dictionary is shared by the whole site, so anything that makes an item approved,
     * or changes an approved item, needs the "approve" capability. Anything else needs "edit".
     *
     * @param \context $context the activity's context.
     * @param int $oldstatus the item's status now.
     * @param int|null $newstatus the new status, or null to edit the item and keep its status.
     * @return bool
     */
    public static function can_change_item(\context $context, int $oldstatus, ?int $newstatus = null): bool {
        if ($oldstatus === dictionary::STATUS_APPROVED || $newstatus === dictionary::STATUS_APPROVED) {
            return has_capability('vocabtool/dictionary:approve', $context);
        }
        return has_capability('vocabtool/dictionary:edit', $context);
    }

    /**
     * Get a dictionary item, if it belongs to a word in an activity's word list, i.e. if its
     * sense is a sense of the lemma of one of the activity's words.
     *
     * @param int $vocabid
     * @param string $itemtype sense, definition or sample.
     * @param int $itemid
     * @return \stdClass the item's record, with its sense's lemmaid.
     * @throws \moodle_exception if there is no such item in the activity.
     */
    public static function get_activity_item(int $vocabid, string $itemtype, int $itemid): \stdClass {
        global $DB;
        if (!isset(self::ITEM_TABLES[$itemtype])) {
            throw new \moodle_exception('error_itemnotfound', 'vocabtool_dictionary', '', "$itemtype $itemid");
        }
        $table = self::ITEM_TABLES[$itemtype];
        if ($itemtype === 'sense') {
            $sql = "SELECT i.* FROM {vocab_senses} i WHERE i.id = :id";
        } else {
            $sql = "SELECT i.*, s.lemmaid FROM {{$table}} i JOIN {vocab_senses} s ON s.id = i.senseid WHERE i.id = :id";
        }
        $item = $DB->get_record_sql($sql, ['id' => $itemid]);
        if ($item && $item->lemmaid) {
            $sql = 'SELECT 1
                      FROM {vocab_word_instances} vwi
                      JOIN {vocab_words} w ON w.id = vwi.wordid
                     WHERE vwi.vocabid = ? AND w.lemmaid = ?';
            if ($DB->record_exists_sql($sql, [$vocabid, $item->lemmaid])) {
                return $item;
            }
        }
        throw new \moodle_exception('error_itemnotfound', 'vocabtool_dictionary', '', "$itemtype $itemid");
    }

    /**
     * Approve, reject or restore a dictionary item of a word in an activity's word list.
     *
     * @param int $vocabid
     * @param \context $context the activity's context.
     * @param string $itemtype sense, definition or sample.
     * @param int $itemid
     * @param int $status the new status: draft, approved or rejected.
     * @return \stdClass the item's record, before the change, with its sense's lemmaid.
     * @throws \moodle_exception if there is no such item, or the user cannot change it.
     */
    public static function set_item_status(int $vocabid, \context $context, string $itemtype, int $itemid, int $status): \stdClass {
        global $DB;
        if (!isset(self::ITEM_STATUS_NAMES[$status])) {
            throw new \invalid_parameter_exception("Unknown status: $status");
        }
        $item = self::get_activity_item($vocabid, $itemtype, $itemid);
        if (!self::can_change_item($context, (int) $item->status, $status)) {
            $approve = ((int) $item->status === dictionary::STATUS_APPROVED || $status === dictionary::STATUS_APPROVED);
            $capability = 'vocabtool/dictionary:' . ($approve ? 'approve' : 'edit');
            throw new \required_capability_exception($context, $capability, 'nopermissions', '');
        }
        if ((int) $item->status !== $status) {
            $DB->update_record(self::ITEM_TABLES[$itemtype], (object) [
                'id' => $itemid,
                'status' => $status,
                'timemodified' => time(),
            ]);
        }
        return $item;
    }

    /**
     * Get the words of an activity, for the list of entries: each word, its lemma, and how many
     * senses and draft items it has. Words whose entries are to be shown get their entries too.
     *
     * @param \mod_vocab\activity $vocab
     * @param int[] $showwordids the words whose entries are shown.
     * @return array for templates/entries.mustache.
     */
    public static function export_word_list($vocab, array $showwordids = []): array {
        $words = $vocab->get_wordlist_words();
        $wordids = array_keys($words);
        $senses = dictionary::count_senses($wordids);
        $drafts = dictionary::count_drafts($wordids);
        $pending = self::get_pending_wordids($vocab->id);
        $entries = self::export_entries($vocab->context, array_intersect($showwordids, $wordids));
        $list = [];
        foreach ($words as $wordid => $word) {
            $list[] = [
                'wordid' => $wordid,
                'word' => $word,
                'sensecount' => $senses[$wordid] ?? 0,
                'draftcount' => $drafts[$wordid] ?? 0,
                'pending' => in_array($wordid, $pending),
                'expanded' => isset($entries[$wordid]),
                'entry' => $entries[$wordid] ?? null,
            ];
        }
        return [
            'vocabid' => (int) $vocab->id,
            'cmid' => (int) $vocab->cm->id,
            'words' => $list,
            'haswords' => !empty($list),
        ];
    }

    /**
     * Get the dictionary entries of some words, for templates/entry.mustache, with the names
     * of parts of speech, languages, statuses and sources, and the actions that the user can take.
     *
     * @param \context $context the activity's context.
     * @param int[] $wordids
     * @return array word id => entry, in the order of $wordids.
     */
    public static function export_entries(\context $context, array $wordids): array {
        global $DB;
        $entries = dictionary::get_entries($wordids);

        // The sources of the items.
        $sourceids = [];
        array_walk_recursive($entries, function ($value, $key) use (&$sourceids) {
            if ($key === 'sourceid' && $value) {
                $sourceids[$value] = $value;
            }
        });
        $sources = ($sourceids ? $DB->get_records_list('vocab_sources', 'id', $sourceids) : []);

        $languages = get_string_manager()->get_list_of_languages();
        $strman = get_string_manager();
        $export = function (array $item, string $itemtype) use ($context, $sources, $languages, $strman): array {
            $status = $item['status'];
            $statusname = self::ITEM_STATUS_NAMES[$status] ?? 'draft';
            $source = $sources[$item['sourceid']] ?? null;
            $sourcename = '';
            if ($source) {
                $sourcename = $source->name;
                if ($sourcename === '' && $strman->string_exists('dictionarysource_' . $source->sourcetype, 'mod_vocab')) {
                    $sourcename = get_string('dictionarysource_' . $source->sourcetype, 'mod_vocab');
                }
            }
            $actions = [];
            $labels = [
                dictionary::STATUS_APPROVED => 'approve',
                dictionary::STATUS_REJECTED => 'reject',
                dictionary::STATUS_DRAFT => 'restore',
            ];
            foreach ($labels as $newstatus => $label) {
                if ($newstatus !== $status && self::can_change_item($context, $status, $newstatus)) {
                    $actions[] = [
                        'itemtype' => $itemtype,
                        'itemid' => $item['id'],
                        'status' => $newstatus,
                        'action' => $label,
                        'label' => get_string($label, 'vocabtool_dictionary'),
                        'isapprove' => ($newstatus === dictionary::STATUS_APPROVED),
                        'isreject' => ($newstatus === dictionary::STATUS_REJECTED),
                    ];
                }
            }
            return [
                'itemtype' => $itemtype,
                'langname' => $languages[$item['lang']] ?? $item['lang'],
                'statusname' => get_string('dictionarystatus_' . $statusname, 'mod_vocab'),
                'isdraft' => ($status === dictionary::STATUS_DRAFT),
                'isapproved' => ($status === dictionary::STATUS_APPROVED),
                'isrejected' => ($status === dictionary::STATUS_REJECTED),
                'isai' => ($source && $source->sourcetype === dictionary::SOURCE_AI),
                'sourcename' => $sourcename,
                'actions' => $actions,
                'hasactions' => !empty($actions),
            ] + $item;
        };

        foreach ($entries as $wordid => $entry) {
            foreach ($entry['senses'] as $s => $sense) {
                $pos = strtolower($sense['pos']);
                $sense = $export($sense, 'sense');
                $sense['posname'] = ($pos !== '' && $strman->string_exists("pos_$pos", 'mod_vocab'))
                    ? get_string("pos_$pos", 'mod_vocab') : $sense['pos'];
                foreach (['definitions' => 'definition', 'glosses' => 'definition', 'samples' => 'sample'] as $list => $type) {
                    foreach ($sense[$list] as $i => $item) {
                        $item = $export($item, $type);
                        if ($list === 'samples') {
                            foreach ($item['translations'] as $t => $translation) {
                                $item['translations'][$t] = $export($translation, 'sample');
                            }
                            $item['hastranslations'] = !empty($item['translations']);
                        }
                        $sense[$list][$i] = $item;
                    }
                    $sense['has' . $list] = !empty($sense[$list]);
                }
                $entry['senses'][$s] = $sense;
            }
            $entry['showlemma'] = ($entry['lemma'] !== '' && $entry['lemma'] !== $entry['word']);
            $entry['hassenses'] = !empty($entry['senses']);
            $entries[$wordid] = $entry;
        }
        return $entries;
    }

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
