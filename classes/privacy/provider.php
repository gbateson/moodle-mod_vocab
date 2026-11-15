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

namespace mod_vocab\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider as userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_vocab\progress;

/**
 * The personal data that the Vocabulary activity stores: students' attempts at
 * games (with the score for each word, and the questions they answered), and
 * their progress with each word at each stage of learning.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */
class provider implements metadata_provider, plugin_provider, userlist_provider {
    /**
     * Describe the personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('vocab_game_attempts', [
            'userid' => 'privacy:metadata:vocab_game_attempts:userid',
            'stage' => 'privacy:metadata:vocab_game_attempts:stage',
            'score' => 'privacy:metadata:vocab_game_attempts:score',
            'status' => 'privacy:metadata:vocab_game_attempts:status',
            'timestarted' => 'privacy:metadata:vocab_game_attempts:timestarted',
            'timecompleted' => 'privacy:metadata:vocab_game_attempts:timecompleted',
        ], 'privacy:metadata:vocab_game_attempts');
        $collection->add_database_table('vocab_word_attempts', [
            'score' => 'privacy:metadata:vocab_word_attempts:score',
            'timecreated' => 'privacy:metadata:vocab_word_attempts:timecreated',
        ], 'privacy:metadata:vocab_word_attempts');
        $collection->add_database_table('vocab_word_stages', [
            'userid' => 'privacy:metadata:vocab_word_stages:userid',
            'stage' => 'privacy:metadata:vocab_word_stages:stage',
            'interactions' => 'privacy:metadata:vocab_word_stages:interactions',
            'successes' => 'privacy:metadata:vocab_word_stages:successes',
            'timepassed' => 'privacy:metadata:vocab_word_stages:timepassed',
            'timecompleted' => 'privacy:metadata:vocab_word_stages:timecompleted',
        ], 'privacy:metadata:vocab_word_stages');
        $collection->add_database_table('vocab_word_states', [
            'userid' => 'privacy:metadata:vocab_word_states:userid',
            'state' => 'privacy:metadata:vocab_word_states:state',
        ], 'privacy:metadata:vocab_word_states');
        $collection->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');
        return $collection;
    }

    /**
     * Get the activities in which a user has data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                 WHERE cm.instance IN (
                    SELECT vgi.vocabid
                      FROM {vocab_game_instances} vgi
                      JOIN {vocab_game_attempts} vga ON vga.gameinstanceid = vgi.id
                     WHERE vga.userid = :userid1
                     UNION
                    SELECT vwi.vocabid
                      FROM {vocab_word_instances} vwi
                      JOIN {vocab_word_stages} vws ON vws.wordinstanceid = vwi.id
                     WHERE vws.userid = :userid2
                     UNION
                    SELECT vwi.vocabid
                      FROM {vocab_word_instances} vwi
                      JOIN {vocab_word_states} vwst ON vwst.wordinstanceid = vwi.id
                     WHERE vwst.userid = :userid3
                 )";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modulename' => 'vocab',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Get the users who have data in an activity.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$vocabid = self::get_vocabid($userlist->get_context())) {
            return;
        }
        $params = ['vocabid' => $vocabid];
        $userlist->add_from_sql('userid', "SELECT vga.userid
                  FROM {vocab_game_attempts} vga
                  JOIN {vocab_game_instances} vgi ON vgi.id = vga.gameinstanceid
                 WHERE vgi.vocabid = :vocabid", $params);
        foreach (['vocab_word_stages', 'vocab_word_states'] as $table) {
            $userlist->add_from_sql('userid', "SELECT t.userid
                      FROM {{$table}} t
                      JOIN {vocab_word_instances} vwi ON vwi.id = t.wordinstanceid
                     WHERE vwi.vocabid = :vocabid", $params);
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
     * Export a user's data in some activities.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $user = $contextlist->get_user();
        $userid = (int) $user->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$vocabid = self::get_vocabid($context)) {
                continue;
            }
            $progress = progress::create($vocabid);
            $words = $progress->get_words();
            $word = function ($wordinstanceid) use ($words) {
                return isset($words[$wordinstanceid]) ? $words[$wordinstanceid]->word : '';
            };

            // Progress with each word at each stage.
            $wordprogress = [];
            foreach ($progress->get_progress($userid) as $wordinstanceid => $stages) {
                foreach ($stages as $stage => $record) {
                    $wordprogress[] = (object) [
                        'word' => $word($wordinstanceid),
                        'stage' => progress::get_stage_name($stage),
                        'interactions' => $record->interactions,
                        'successes' => $record->successes,
                        'timepassed' => $record->timepassed ? transform::datetime($record->timepassed) : '',
                        'timecompleted' => $record->timecompleted ? transform::datetime($record->timecompleted) : '',
                    ];
                }
            }

            // Game attempts, with the score for each word, and the questions that were answered.
            $sql = "SELECT vga.*, vg.name AS game
                      FROM {vocab_game_attempts} vga
                      JOIN {vocab_game_instances} vgi ON vgi.id = vga.gameinstanceid
                      JOIN {vocab_games} vg ON vg.id = vgi.gameid
                     WHERE vgi.vocabid = :vocabid AND vga.userid = :userid
                  ORDER BY vga.timestarted, vga.id";
            $attempts = [];
            foreach ($DB->get_records_sql($sql, ['vocabid' => $vocabid, 'userid' => $userid]) as $attempt) {
                $scores = [];
                $scoresql = "SELECT vwa.id, vwu.wordinstanceid, vwa.score, vwa.timecreated
                               FROM {vocab_word_usages} vwu
                               JOIN {vocab_word_attempts} vwa ON vwa.wordusageid = vwu.id
                              WHERE vwu.gameattemptid = ?";
                foreach ($DB->get_records_sql($scoresql, [$attempt->id]) as $score) {
                    $scores[] = (object) [
                        'word' => $word($score->wordinstanceid),
                        'score' => $score->score,
                        'time' => transform::datetime($score->timecreated),
                    ];
                }
                $attempts[] = (object) [
                    'game' => $attempt->game,
                    'stage' => progress::get_stage_name($attempt->stage),
                    'score' => $attempt->score,
                    'finished' => transform::yesno($attempt->status == progress::ATTEMPT_FINISHED),
                    'timestarted' => transform::datetime($attempt->timestarted),
                    'timecompleted' => $attempt->timecompleted ? transform::datetime($attempt->timecompleted) : '',
                    'words' => $scores,
                ];
                if ($attempt->uniqueid) {
                    $options = new \question_display_options();
                    $options->context = $context;
                    $subcontext = [get_string('privacy:attempt', 'mod_vocab', $attempt->id)];
                    \core_question\privacy\provider::export_question_usage(
                        $userid,
                        $context,
                        $subcontext,
                        (int) $attempt->uniqueid,
                        $options,
                        true
                    );
                }
            }

            $data = helper::get_context_data($context, $user);
            $data->wordprogress = $wordprogress;
            $data->attempts = $attempts;
            writer::with_context($context)->export_data([], $data);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Delete every user's data in an activity.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($vocabid = self::get_vocabid($context)) {
            progress::delete_user_data($vocabid);
        }
    }

    /**
     * Delete a user's data in some activities.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($vocabid = self::get_vocabid($context)) {
                progress::delete_user_data($vocabid, [$userid]);
            }
        }
    }

    /**
     * Delete some users' data in an activity.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($vocabid = self::get_vocabid($userlist->get_context())) {
            progress::delete_user_data($vocabid, $userlist->get_userids());
        }
    }
}
