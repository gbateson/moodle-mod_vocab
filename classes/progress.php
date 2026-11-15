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

namespace mod_vocab;

/**
 * Students' progress with the words of a Vocabulary activity, through the
 * stages of learning (see docs/games.md).
 *
 * Games give this class a score from 0 to 1 for each word that they used, and
 * it records the scores, works out each word's progress at each stage, and
 * chooses the words for the next game. Games never write progress themselves.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /** The stage at which a word is met: its meaning, sound, picture and use. */
    const STAGE_LEARN = 'learn';

    /** The stage at which a word is picked out from choices. */
    const STAGE_RECOGNIZE = 'recognize';

    /** The stage at which a word is brought to mind without choices. */
    const STAGE_RECALL = 'recall';

    /** The stage at which a word is used to communicate. */
    const STAGE_PRODUCE = 'produce';

    /** The stages, in order. */
    const STAGES = [self::STAGE_LEARN, self::STAGE_RECOGNIZE, self::STAGE_RECALL, self::STAGE_PRODUCE];

    /** A word that has not been played yet. */
    const STATE_NOTSTARTED = 0;

    /** A word that has been played, but not completed at every stage. */
    const STATE_INPROGRESS = 1;

    /** A word that has been completed at every stage, i.e. mastered. */
    const STATE_COMPLETED = 2;

    /** A game attempt that is not finished, and can be resumed. */
    const ATTEMPT_INPROGRESS = 0;

    /** A game attempt that is finished. */
    const ATTEMPT_FINISHED = 1;

    /** The first delay of an expanding delay (spaced repetition). */
    const EXPANDING_DELAY = DAYSECS;

    /** The default minimum score for a success, as a percentage. */
    const DEFAULT_SUCCESS_SCORE = 80;

    /** @var \stdClass the record of the Vocabulary activity. */
    protected $vocab;

    /** @var array|null stage => record of the activity's stages, in order. */
    protected $stages = null;

    /**
     * Constructor.
     *
     * @param \stdClass $vocab the record of the Vocabulary activity.
     */
    public function __construct(\stdClass $vocab) {
        $this->vocab = $vocab;
    }

    /**
     * Create an object for an activity.
     *
     * @param int $vocabid
     * @return progress
     */
    public static function create(int $vocabid): progress {
        global $DB;
        return new progress($DB->get_record('vocab', ['id' => $vocabid], '*', MUST_EXIST));
    }

    /**
     * Get the activity's record.
     *
     * @return \stdClass
     */
    public function get_vocab(): \stdClass {
        return $this->vocab;
    }

    /*////////////////////////////////////////
    // Stages.
    ////////////////////////////////////////*/

    /**
     * Get the stages that the activity uses, in order.
     *
     * @return \stdClass[] stage => record with passcount and completecount.
     */
    public function get_stages(): array {
        global $DB;
        if ($this->stages === null) {
            $records = $DB->get_records('vocab_stages', ['vocabid' => $this->vocab->id], '', 'stage, id, passcount, completecount');
            $this->stages = [];
            foreach (self::STAGES as $stage) {
                if (isset($records[$stage])) {
                    $this->stages[$stage] = $records[$stage];
                }
            }
        }
        return $this->stages;
    }

    /**
     * Save the stages that an activity uses.
     *
     * @param int $vocabid
     * @param array $stages stage => [passcount, completecount], for the chosen stages.
     */
    public static function save_stages(int $vocabid, array $stages): void {
        global $DB;
        $existing = $DB->get_records_menu('vocab_stages', ['vocabid' => $vocabid], '', 'stage, id');
        foreach (self::STAGES as $stage) {
            if (!isset($stages[$stage])) {
                if (isset($existing[$stage])) {
                    $DB->delete_records('vocab_stages', ['id' => $existing[$stage]]);
                }
                continue;
            }
            [$passcount, $completecount] = array_values($stages[$stage]);
            $passcount = max(1, (int) $passcount);
            $record = (object) [
                'vocabid' => $vocabid,
                'stage' => $stage,
                'passcount' => $passcount,
                'completecount' => max($passcount, (int) $completecount),
            ];
            if (isset($existing[$stage])) {
                $record->id = $existing[$stage];
                $DB->update_record('vocab_stages', $record);
            } else {
                $DB->insert_record('vocab_stages', $record);
            }
        }
    }

    /**
     * Get the localised name of a stage.
     *
     * @param string $stage
     * @return string
     */
    public static function get_stage_name(string $stage): string {
        return get_string('stage' . $stage, 'mod_vocab');
    }

    /*////////////////////////////////////////
    // Words and progress.
    ////////////////////////////////////////*/

    /**
     * Get the words in the activity's word list.
     *
     * @return \stdClass[] word instance id => record with id, wordid, word and sortorder.
     */
    public function get_words(): array {
        global $DB;
        $sql = "SELECT vwi.id, vwi.wordid, vw.word, vwi.sortorder
                  FROM {vocab_word_instances} vwi
                  JOIN {vocab_words} vw ON vw.id = vwi.wordid
                 WHERE vwi.vocabid = :vocabid
              ORDER BY vwi.sortorder, vw.word, vwi.id";
        return $DB->get_records_sql($sql, ['vocabid' => $this->vocab->id]);
    }

    /**
     * Get a student's progress with each word at each stage.
     *
     * @param int $userid
     * @return array word instance id => stage => record from vocab_word_stages.
     */
    public function get_progress(int $userid): array {
        global $DB;
        $sql = "SELECT vws.*
                  FROM {vocab_word_stages} vws
                  JOIN {vocab_word_instances} vwi ON vwi.id = vws.wordinstanceid
                 WHERE vwi.vocabid = :vocabid AND vws.userid = :userid";
        $progress = [];
        foreach ($DB->get_records_sql($sql, ['vocabid' => $this->vocab->id, 'userid' => $userid]) as $record) {
            $progress[$record->wordinstanceid][$record->stage] = $record;
        }
        return $progress;
    }

    /**
     * Is a word passed at a stage?
     *
     * @param array $progress from get_progress().
     * @param int $wordinstanceid
     * @param string $stage
     * @return bool
     */
    protected static function is_passed(array $progress, int $wordinstanceid, string $stage): bool {
        return !empty($progress[$wordinstanceid][$stage]->timepassed);
    }

    /**
     * Is a word completed at a stage?
     *
     * @param array $progress from get_progress().
     * @param int $wordinstanceid
     * @param string $stage
     * @return bool
     */
    protected static function is_completed(array $progress, int $wordinstanceid, string $stage): bool {
        return !empty($progress[$wordinstanceid][$stage]->timecompleted);
    }

    /**
     * Get the words that are available to a student at a stage: those passed at
     * every earlier stage, and not completed at this stage, most urgent first:
     * words that are due, words in progress (least recently played first), then
     * words not started (in word list order).
     *
     * @param int $userid
     * @param string $stage
     * @param int|null $time the time now.
     * @return \stdClass[] word instance id => word record, with "due" (bool).
     */
    public function get_available_words(int $userid, string $stage, ?int $time = null): array {
        $stages = $this->get_stages();
        if (!isset($stages[$stage])) {
            return [];
        }
        $time = $time ?? time();
        $earlier = array_slice(array_keys($stages), 0, array_search($stage, array_keys($stages)));
        $progress = $this->get_progress($userid);
        $groups = ['due' => [], 'inprogress' => [], 'new' => [], 'notdue' => []];
        foreach ($this->get_words() as $id => $word) {
            foreach ($earlier as $previous) {
                if (!self::is_passed($progress, $id, $previous)) {
                    continue 2;
                }
            }
            if (self::is_completed($progress, $id, $stage)) {
                continue;
            }
            $record = $progress[$id][$stage] ?? null;
            $word->due = (!$record || $record->timedue <= $time);
            $word->timelastplayed = $record ? (int) $record->timelastplayed : 0;
            if (!$record || !$record->interactions) {
                $groups['new'][$id] = $word;
            } else if (!$word->due) {
                $groups['notdue'][$id] = $word;
            } else if ($record->timedue) {
                $groups['due'][$id] = $word;
            } else {
                $groups['inprogress'][$id] = $word;
            }
        }
        foreach (['due', 'inprogress', 'notdue'] as $group) {
            uasort($groups[$group], function ($a, $b) {
                return $a->timelastplayed - $b->timelastplayed;
            });
        }
        return $groups['due'] + $groups['inprogress'] + $groups['new'] + $groups['notdue'];
    }

    /**
     * Choose the words for a game attempt.
     *
     * @param int $userid
     * @param string $stage
     * @param int $count how many words the game wants.
     * @param int[]|null $ready the word instance ids for which the game has materials, or null for all.
     * @return int[] word instance ids.
     */
    public function choose_words(int $userid, string $stage, int $count, ?array $ready = null): array {
        $words = $this->get_available_words($userid, $stage);
        if ($ready !== null) {
            $words = array_intersect_key($words, array_flip($ready));
        }
        return array_slice(array_keys($words), 0, max(1, $count));
    }

    /**
     * Get the overall state of each word for a student.
     *
     * @param int $userid
     * @return int[] word instance id => STATE_xxx.
     */
    public function get_word_states(int $userid): array {
        $stages = array_keys($this->get_stages());
        $progress = $this->get_progress($userid);
        $states = [];
        foreach ($this->get_words() as $id => $word) {
            $states[$id] = self::get_state($progress[$id] ?? [], $stages);
        }
        return $states;
    }

    /**
     * Get the overall state of a word, from its progress at each stage.
     *
     * @param \stdClass[] $records stage => record from vocab_word_stages.
     * @param string[] $stages the activity's stages.
     * @return int STATE_xxx
     */
    protected static function get_state(array $records, array $stages): int {
        if (empty($records)) {
            return self::STATE_NOTSTARTED;
        }
        foreach ($stages as $stage) {
            if (empty($records[$stage]->timecompleted)) {
                return self::STATE_INPROGRESS;
            }
        }
        return $stages ? self::STATE_COMPLETED : self::STATE_INPROGRESS;
    }

    /**
     * Count a student's words in each state, for the results chart.
     *
     * @param int $userid
     * @return int[] [total, completed, inprogress, notstarted]
     */
    public function get_counts(int $userid): array {
        $states = $this->get_word_states($userid);
        $counts = array_count_values($states);
        return [
            count($states),
            $counts[self::STATE_COMPLETED] ?? 0,
            $counts[self::STATE_INPROGRESS] ?? 0,
            $counts[self::STATE_NOTSTARTED] ?? 0,
        ];
    }

    /**
     * Get a student's grade: the percentage of words mastered, out of all the words,
     * or out of the "Minimum word count", if it is set. With "Include partially
     * completed words", words in progress count as the fraction of stages completed.
     *
     * @param int $userid
     * @return float|null the grade, from 0 to 100, or null if there are no words or stages.
     */
    public function get_grade(int $userid): ?float {
        $stages = array_keys($this->get_stages());
        $words = $this->get_words();
        if (empty($stages) || empty($words)) {
            return null;
        }
        $progress = $this->get_progress($userid);
        $total = 0.0;
        foreach ($words as $id => $word) {
            $completed = 0;
            foreach ($stages as $stage) {
                if (self::is_completed($progress, $id, $stage)) {
                    $completed++;
                }
            }
            if ($completed == count($stages)) {
                $total += 1;
            } else if (!empty($this->vocab->gradepartial)) {
                $total += $completed / count($stages);
            }
        }
        $count = count($words);
        if (!empty($this->vocab->gradecount) && $this->vocab->gradecount < $count) {
            $count = (int) $this->vocab->gradecount;
        }
        return min(100, round(100 * $total / $count, 2));
    }

    /*////////////////////////////////////////
    // Materials.
    ////////////////////////////////////////*/

    /**
     * Record which questions are about a word, e.g. questions made by the question bank tool.
     *
     * @param int $wordid from vocab_words.
     * @param int[] $questionids
     */
    public static function link_questions(int $wordid, array $questionids): void {
        global $DB;
        foreach (array_unique(array_filter(array_map('intval', $questionids))) as $questionid) {
            if (!$DB->record_exists('vocab_word_questions', ['wordid' => $wordid, 'questionid' => $questionid])) {
                $DB->insert_record('vocab_word_questions', (object) [
                    'wordid' => $wordid,
                    'questionid' => $questionid,
                    'timecreated' => time(),
                ]);
            }
        }
    }

    /**
     * Link the questions that the question bank tool made earlier to their words, from its log.
     *
     * @return int how many log records were read.
     */
    public static function link_logged_questions(): int {
        global $DB;
        $count = 0;
        $rs = $DB->get_recordset_select('vocabtool_questionbank_log', "questionids <> ''", null, 'id', 'id, wordid, questionids');
        foreach ($rs as $log) {
            $ids = array_filter(array_map('trim', explode(',', $log->questionids)));
            if ($log->wordid && $ids) {
                [$select, $params] = $DB->get_in_or_equal($ids);
                self::link_questions((int) $log->wordid, $DB->get_fieldset_select('question', 'id', "id $select", $params));
                $count++;
            }
        }
        $rs->close();
        return $count;
    }

    /*////////////////////////////////////////
    // Attempts.
    ////////////////////////////////////////*/

    /**
     * Start a game attempt, with its words.
     *
     * @param \stdClass $instance the game instance.
     * @param int $userid
     * @param int[] $wordinstanceids
     * @param int $uniqueid the attempt's question usage, if the game uses questions.
     * @param array $data the game's own data for the attempt.
     * @return \stdClass the attempt.
     */
    public function start_attempt(
        \stdClass $instance,
        int $userid,
        array $wordinstanceids,
        int $uniqueid = 0,
        array $data = []
    ): \stdClass {
        global $DB;
        $time = time();
        $attempt = (object) [
            'gameinstanceid' => $instance->id,
            'userid' => $userid,
            'score' => null,
            'status' => self::ATTEMPT_INPROGRESS,
            'timestarted' => $time,
            'timecompleted' => 0,
            'stage' => $instance->stage,
            'uniqueid' => $uniqueid,
            'data' => json_encode($data),
            'timemodified' => $time,
        ];
        $attempt->id = $DB->insert_record('vocab_game_attempts', $attempt);
        foreach (array_values(array_unique($wordinstanceids)) as $wordinstanceid) {
            $DB->insert_record('vocab_word_usages', (object) [
                'gameattemptid' => $attempt->id,
                'wordinstanceid' => $wordinstanceid,
            ]);
        }
        return $attempt;
    }

    /**
     * Get a student's unfinished attempt at a game, if there is one.
     *
     * @param int $instanceid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_unfinished_attempt(int $instanceid, int $userid): ?\stdClass {
        global $DB;
        $attempts = $DB->get_records('vocab_game_attempts', [
            'gameinstanceid' => $instanceid,
            'userid' => $userid,
            'status' => self::ATTEMPT_INPROGRESS,
        ], 'id DESC', '*', 0, 1);
        return $attempts ? reset($attempts) : null;
    }

    /**
     * Get the words of an attempt.
     *
     * @param int $attemptid
     * @return int[] word usage id => word instance id.
     */
    public static function get_attempt_words(int $attemptid): array {
        global $DB;
        return $DB->get_records_menu('vocab_word_usages', ['gameattemptid' => $attemptid], 'id', 'id, wordinstanceid');
    }

    /**
     * Finish a game attempt: record the score for each word, and update the
     * student's progress and grade.
     *
     * @param \stdClass $attempt
     * @param float[] $scores word instance id => score, from 0 to 1. Words of the
     *      attempt that have no score get 0.
     * @param int|null $time when the words were scored.
     */
    public function finish_attempt(\stdClass $attempt, array $scores, ?int $time = null): void {
        global $DB;
        $time = $time ?? time();
        $usages = self::get_attempt_words($attempt->id);
        foreach ($scores as $wordinstanceid => $score) {
            if (!in_array($wordinstanceid, $usages)) {
                // A game may score a word that it was not given, e.g. a word in a question about another word.
                $usageid = $DB->insert_record('vocab_word_usages', (object) [
                    'gameattemptid' => $attempt->id,
                    'wordinstanceid' => $wordinstanceid,
                ]);
                $usages[$usageid] = $wordinstanceid;
            }
        }
        $total = 0;
        foreach ($usages as $usageid => $wordinstanceid) {
            $score = isset($scores[$wordinstanceid]) ? max(0, min(1, (float) $scores[$wordinstanceid])) : 0;
            $total += $score;
            $DB->insert_record('vocab_word_attempts', (object) [
                'wordusageid' => $usageid,
                'score' => round(100 * $score, 2),
                'timecreated' => $time,
            ]);
        }
        $attempt->status = self::ATTEMPT_FINISHED;
        $attempt->score = $usages ? round(100 * $total / count($usages), 2) : 0;
        $attempt->timecompleted = $time;
        $attempt->timemodified = $time;
        $DB->update_record('vocab_game_attempts', $attempt);

        foreach (array_unique($usages) as $wordinstanceid) {
            $this->recalculate((int) $attempt->userid, (int) $wordinstanceid, $attempt->stage);
        }
        $this->update_grade((int) $attempt->userid);
    }

    /**
     * Update a student's grade in the gradebook.
     *
     * @param int $userid
     */
    public function update_grade(int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vocab/lib.php');
        if (!empty($this->vocab->grademax) && !empty($this->vocab->course)) {
            vocab_update_grades($this->vocab, $userid);
        }
    }

    /**
     * Work out a student's progress with a word at a stage, from the scores that
     * the word has had at that stage, and save it.
     *
     * @param int $userid
     * @param int $wordinstanceid
     * @param string $stage
     * @return \stdClass|null the record from vocab_word_stages, or null if the stage is not used.
     */
    public function recalculate(int $userid, int $wordinstanceid, string $stage): ?\stdClass {
        global $DB;
        $stages = $this->get_stages();
        if (!isset($stages[$stage])) {
            return null;
        }
        $sql = "SELECT vwa.id, vwa.score, vwa.timecreated
                  FROM {vocab_word_attempts} vwa
                  JOIN {vocab_word_usages} vwu ON vwu.id = vwa.wordusageid
                  JOIN {vocab_game_attempts} vga ON vga.id = vwu.gameattemptid
                 WHERE vga.userid = :userid AND vwu.wordinstanceid = :wordinstanceid AND vga.stage = :stage
              ORDER BY vwa.timecreated, vwa.id";
        $history = $DB->get_records_sql($sql, ['userid' => $userid, 'wordinstanceid' => $wordinstanceid, 'stage' => $stage]);
        $result = $this->count_successes(array_values($history), $stages[$stage]);

        $record = $DB->get_record('vocab_word_stages', [
            'userid' => $userid,
            'wordinstanceid' => $wordinstanceid,
            'stage' => $stage,
        ]);
        $new = (object) array_merge([
            'userid' => $userid,
            'wordinstanceid' => $wordinstanceid,
            'stage' => $stage,
            'timemodified' => time(),
        ], $result);
        if ($record) {
            // Once passed or completed, a word stays so, e.g. if later scores are lower.
            $new->timepassed = $record->timepassed ?: $new->timepassed;
            $new->timecompleted = $record->timecompleted ?: $new->timecompleted;
            $new->id = $record->id;
            $DB->update_record('vocab_word_stages', $new);
        } else {
            $new->id = $DB->insert_record('vocab_word_stages', $new);
        }
        return $new;
    }

    /**
     * Count the successes in a word's scores at a stage, as the activity's
     * mastery settings say.
     *
     * @param \stdClass[] $history scores (0 to 100) with timecreated, oldest first.
     * @param \stdClass $stage the stage's record, with passcount and completecount.
     * @return array interactions, successes, timelastplayed, timelastsuccess, timedue, timepassed, timecompleted.
     */
    public function count_successes(array $history, \stdClass $stage): array {
        $minscore = isset($this->vocab->attemptscore) && $this->vocab->attemptscore !== ''
            ? (float) $this->vocab->attemptscore : self::DEFAULT_SUCCESS_SCORE;
        $type = (int) ($this->vocab->attempttype ?? activity::ATTEMPTTYPE_ANY);
        $passcount = (int) $stage->passcount;
        $completecount = max($passcount, (int) $stage->completecount);

        $counted = [];  // For each interaction: 1 = counted success, 0 = failure, null = success that does not count.
        $successes = 0;
        $run = 0;
        $lastsuccess = null; // The time of the last success that counted, if any.
        $timepassed = 0;
        $timecompleted = 0;
        foreach ($history as $item) {
            $time = (int) $item->timecreated;
            if ((float) $item->score < $minscore) {
                $counted[] = 0;
                $run = 0;
            } else if ($lastsuccess !== null && $time < $lastsuccess + $this->get_delay($successes, $completecount, $lastsuccess)) {
                // Too soon after the last success that counted (spaced repetition).
                $counted[] = null;
            } else {
                $counted[] = 1;
                $successes++;
                $run++;
                $lastsuccess = $time;
            }
            switch ($type) {
                case activity::ATTEMPTTYPE_CONSECUTIVE:
                    $count = $run;
                    break;
                case activity::ATTEMPTTYPE_RECENT:
                    $count = count(array_filter(array_slice($counted, -$completecount), function ($value) {
                        return $value === 1;
                    }));
                    break;
                default:
                    $count = $successes;
            }
            if (!$timepassed && $count >= $passcount) {
                $timepassed = $time;
            }
            if (!$timecompleted && $count >= $completecount) {
                $timecompleted = $time;
            }
        }
        $last = $history ? (int) end($history)->timecreated : 0;
        $timedue = 0;
        if ($lastsuccess !== null && !$timecompleted) {
            $timedue = $lastsuccess + $this->get_delay($successes, $completecount, $lastsuccess);
            if ($timedue <= $lastsuccess) {
                $timedue = 0;
            }
        }
        return [
            'interactions' => count($history),
            'successes' => $count ?? 0,
            'timelastplayed' => $last,
            'timelastsuccess' => (int) $lastsuccess,
            'timedue' => $timedue,
            'timepassed' => $timepassed,
            'timecompleted' => $timecompleted,
        ];
    }

    /**
     * Get the delay after a counted success before the next success counts.
     *
     * @param int $successes the counted successes so far.
     * @param int $completecount the successes needed to complete the word.
     * @param int $lastsuccess the time of the last counted success.
     * @return int seconds.
     */
    protected function get_delay(int $successes, int $completecount, int $lastsuccess): int {
        $setting = (int) ($this->vocab->attemptdelay ?? activity::ATTEMPTDELAY_NONE);
        if ($setting > 0) {
            return $setting;
        }
        if ($setting != activity::ATTEMPTDELAY_EXPANDING || $successes < 1) {
            return 0;
        }
        // 1 day, then 2 days, then 4 days ...
        $delay = self::EXPANDING_DELAY * (2 ** ($successes - 1));
        // ... but short enough to complete the word before the games close.
        $close = (int) ($this->vocab->gamesclose ?? 0);
        $remaining = $completecount - $successes;
        if ($close > $lastsuccess && $remaining > 0) {
            $delay = min($delay, (int) floor(($close - $lastsuccess) / ($remaining + 1)));
        }
        return max(0, $delay);
    }

    /*////////////////////////////////////////
    // Games.
    ////////////////////////////////////////*/

    /**
     * Get the activity's game instances, in order, with their game names.
     *
     * @param bool $enabledonly
     * @return \stdClass[] id => instance record, with "name" (e.g. "quiz").
     */
    public function get_game_instances(bool $enabledonly = true): array {
        global $DB;
        $sql = "SELECT vgi.*, vg.name
                  FROM {vocab_game_instances} vgi
                  JOIN {vocab_games} vg ON vg.id = vgi.gameid
                 WHERE vgi.vocabid = :vocabid" . ($enabledonly ? ' AND vgi.enabled = 1' : '') . "
              ORDER BY vgi.sortorder, vgi.id";
        $instances = $DB->get_records_sql($sql, ['vocabid' => $this->vocab->id]);
        $installed = \core_component::get_plugin_list('vocabgame');
        return array_filter($instances, function ($instance) use ($installed) {
            return isset($installed[$instance->name]);
        });
    }

    /**
     * Get the id of a game in the vocab_games table, adding it if necessary.
     *
     * @param string $name e.g. "quiz".
     * @return int
     */
    public static function get_gameid(string $name): int {
        global $DB;
        $id = $DB->get_field('vocab_games', 'id', ['name' => $name]);
        if (!$id) {
            $id = $DB->insert_record('vocab_games', (object) ['name' => $name, 'enabled' => 1]);
        }
        return (int) $id;
    }

    /**
     * Save the games that an activity offers. Games that are no longer offered are
     * disabled, not deleted, so that their attempts are kept.
     *
     * @param int $vocabid
     * @param array $games each [name, stage, configdata (array)], in order.
     */
    public static function save_game_instances(int $vocabid, array $games): void {
        global $DB;
        $existing = [];
        foreach ($DB->get_records('vocab_game_instances', ['vocabid' => $vocabid], 'sortorder') as $instance) {
            $existing[$instance->gameid . '/' . $instance->stage] = $instance;
        }
        // Move the sort orders out of the way, so that they can be set again without clashing.
        $max = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {vocab_game_instances} WHERE vocabid = ?', [$vocabid]);
        foreach ($existing as $instance) {
            $DB->set_field('vocab_game_instances', 'sortorder', $instance->sortorder + $max + 1, ['id' => $instance->id]);
        }
        $sortorder = 0;
        foreach ($games as $game) {
            [$name, $stage, $configdata] = $game;
            $gameid = self::get_gameid($name);
            $key = $gameid . '/' . $stage;
            $record = (object) [
                'vocabid' => $vocabid,
                'gameid' => $gameid,
                'stage' => $stage,
                'enabled' => 1,
                'sortorder' => ++$sortorder,
                'configdata' => json_encode($configdata),
            ];
            if (isset($existing[$key])) {
                $record->id = $existing[$key]->id;
                $DB->update_record('vocab_game_instances', $record);
                unset($existing[$key]);
            } else {
                $DB->insert_record('vocab_game_instances', $record);
            }
        }
        foreach ($existing as $instance) {
            $DB->update_record('vocab_game_instances', (object) [
                'id' => $instance->id,
                'enabled' => 0,
                'sortorder' => ++$sortorder,
            ]);
        }
    }

    /**
     * Get the state of a game for a student: can it be played, and if not, why not?
     *
     * @param gamebase $game
     * @param int $userid
     * @return array [bool $playable, string $reason ('' if playable): locked, completed or notready]
     */
    public function get_game_state(gamebase $game, int $userid): array {
        $stage = $game->get_instance()->stage;
        if (self::get_unfinished_attempt($game->get_instance()->id, $userid)) {
            return [true, ''];
        }
        $words = $this->get_available_words($userid, $stage);
        if (empty($words)) {
            // Either no word has passed the earlier stages, or every word is completed at this stage.
            $progress = $this->get_progress($userid);
            foreach ($this->get_words() as $id => $word) {
                if (!self::is_completed($progress, $id, $stage)) {
                    return [false, 'locked'];
                }
            }
            return [false, 'completed'];
        }
        if (!$game->get_ready_words(array_keys($words))) {
            return [false, 'notready'];
        }
        return [true, ''];
    }
}
