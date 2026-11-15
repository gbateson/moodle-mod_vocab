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

namespace vocabgame_quiz;

use mod_vocab\progress;

/**
 * A quiz of questions about the words, from the question bank.
 *
 * Each word gets one question, chosen at random from the questions about it
 * (see progress::link_questions()), in the chosen category. The score for the
 * question is the score for its word.
 *
 * @package    vocabgame_quiz
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game extends \mod_vocab\gamebase {
    /** The most questions in an attempt. */
    const MAX_WORD_COUNT = 20;

    /**
     * Questions can recognize, recall or produce words.
     *
     * @return string[]
     */
    public static function get_supported_stages(): array {
        return [progress::STAGE_RECOGNIZE, progress::STAGE_RECALL, progress::STAGE_PRODUCE];
    }

    /**
     * Add the question category and the number of questions to the activity's settings.
     *
     * @param \MoodleQuickForm $mform
     * @param string $prefix
     * @param \context $context
     */
    public static function add_settings(\MoodleQuickForm $mform, string $prefix, \context $context): void {
        $plugin = static::get_component();
        $name = $prefix . 'categoryid';
        $mform->addElement('select', $name, get_string('category', $plugin), self::get_category_menu($context));
        $mform->addHelpButton($name, 'category', $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 0);

        $name = $prefix . 'wordcount';
        $counts = array_combine(range(1, self::MAX_WORD_COUNT), range(1, self::MAX_WORD_COUNT));
        $mform->addElement('select', $name, get_string('wordcount', $plugin), $counts);
        $mform->addHelpButton($name, 'wordcount', $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, self::DEFAULT_WORD_COUNT);
    }

    /**
     * Get the settings from the activity's settings form.
     *
     * @param \stdClass $data
     * @param string $prefix
     * @return array
     */
    public static function get_settings(\stdClass $data, string $prefix): array {
        return [
            'categoryid' => (int) ($data->{$prefix . 'categoryid'} ?? 0),
            'wordcount' => max(1, min(self::MAX_WORD_COUNT, (int) ($data->{$prefix . 'wordcount'} ?? self::DEFAULT_WORD_COUNT))),
        ];
    }

    /**
     * Get the question categories of the course, for a menu, indented by depth.
     *
     * @param \context $context the course or activity.
     * @return string[] category id => name, with 0 for any category in the course.
     */
    public static function get_category_menu(\context $context): array {
        global $DB;
        $menu = [0 => get_string('anycategory', static::get_component())];
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            return $menu;
        }
        $categories = $DB->get_records(
            'question_categories',
            ['contextid' => $coursecontext->id],
            'parent, sortorder, name',
            'id, name, parent'
        );
        $children = [];
        foreach ($categories as $category) {
            $children[$category->parent][] = $category;
        }
        $add = function ($parent, $depth) use (&$add, &$menu, $children, $coursecontext) {
            foreach ($children[$parent] ?? [] as $category) {
                $menu[$category->id] = str_repeat("\u{00A0}\u{00A0}", $depth) .
                    format_string($category->name, true, ['context' => $coursecontext]);
                $add($category->id, $depth + 1);
            }
        };
        $add(0, 0);
        return $menu;
    }

    /**
     * Get the ids of the question categories from which questions are used.
     *
     * @return int[]|null category ids, or null for any category in the course.
     */
    protected function get_category_ids(): ?array {
        global $CFG;
        $categoryid = (int) $this->get_config('categoryid', 0);
        if (!$categoryid) {
            return null;
        }
        require_once($CFG->libdir . '/questionlib.php');
        // Moodle 4.x returns an array; earlier versions return a list separated by commas.
        $list = question_categorylist($categoryid);
        return array_map('intval', is_array($list) ? $list : explode(',', $list));
    }

    /**
     * Get the questions about some words that can be used, i.e. questions that
     * exist, are ready (not hidden), and are in the right category.
     *
     * @param int[] $wordinstanceids
     * @return array word instance id => question ids.
     */
    public function get_questions(array $wordinstanceids): array {
        global $DB;
        if (empty($wordinstanceids)) {
            return [];
        }
        $coursecontext = $this->context->get_course_context();
        [$wordsql, $params] = $DB->get_in_or_equal($wordinstanceids, SQL_PARAMS_NAMED, 'wi');
        if ($DB->get_manager()->table_exists('question_versions')) {
            // Moodle 4.0 and later: questions have versions, and their categories are in question_bank_entries.
            $from = "JOIN {question_versions} qv ON qv.questionid = q.id
                     JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid";
            $where = 'qv.status = :ready';
            $params['ready'] = 'ready';
        } else {
            $from = "JOIN {question_categories} qc ON qc.id = q.category";
            $where = 'q.hidden = 0';
        }
        $where .= ' AND qc.contextid = :contextid';
        $params['contextid'] = $coursecontext->id;
        if (($categoryids = $this->get_category_ids()) !== null) {
            [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
            $where .= " AND qc.id $catsql";
            $params += $catparams;
        }
        $sql = "SELECT vwq.id, vwi.id AS wordinstanceid, q.id AS questionid
                  FROM {vocab_word_instances} vwi
                  JOIN {vocab_word_questions} vwq ON vwq.wordid = vwi.wordid
                  JOIN {question} q ON q.id = vwq.questionid
                  $from
                 WHERE vwi.id $wordsql AND $where";
        $questions = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $questions[$record->wordinstanceid][] = (int) $record->questionid;
        }
        return $questions;
    }

    /**
     * Words are ready if they have at least one question.
     *
     * @param int[] $wordinstanceids
     * @return int[]
     */
    public function get_ready_words(array $wordinstanceids): array {
        return array_keys($this->get_questions($wordinstanceids));
    }

    /**
     * Start an attempt: choose the words, and a question about each one.
     *
     * @param int $userid
     * @return \stdClass|null the attempt, or null if there are no words with questions.
     */
    public function start_attempt(int $userid): ?\stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $progress = $this->get_progress();
        $available = array_keys($progress->get_available_words($userid, $this->instance->stage));
        $questions = $this->get_questions($available);
        $words = $progress->choose_words($userid, $this->instance->stage, $this->get_word_count(), array_keys($questions));
        if (empty($words)) {
            return null;
        }
        $quba = \question_engine::make_questions_usage_by_activity('mod_vocab', $this->context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $slots = [];
        foreach ($words as $wordinstanceid) {
            $ids = $questions[$wordinstanceid];
            $question = \question_bank::load_question($ids[array_rand($ids)]);
            $slots[$quba->add_question($question, 1)] = $wordinstanceid;
        }
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        return $progress->start_attempt($this->instance, $userid, $words, $quba->get_id(), ['slots' => $slots]);
    }

    /**
     * Get the word of each question (slot) in an attempt.
     *
     * @param \stdClass $attempt
     * @return int[] slot => word instance id.
     */
    public static function get_slots(\stdClass $attempt): array {
        $data = json_decode((string) $attempt->data, true);
        return is_array($data) && isset($data['slots']) ? array_map('intval', $data['slots']) : [];
    }

    /**
     * Save the student's answers, and optionally finish the attempt: mark the
     * questions, and give each word the score of its question.
     *
     * @param \stdClass $attempt
     * @param bool $finish
     * @param array|null $postdata the answers, or null to read them from the request.
     * @return float[] word instance id => score from 0 to 1, if the attempt was finished.
     */
    public function process_attempt(\stdClass $attempt, bool $finish, ?array $postdata = null): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $time = time();
        $quba->process_all_actions($time, $postdata);
        if (!$finish) {
            \question_engine::save_questions_usage_by_activity($quba);
            return [];
        }
        $quba->finish_all_questions($time);
        \question_engine::save_questions_usage_by_activity($quba);

        // A word's score is the score of its question, or the average, if it has more than one.
        $totals = [];
        foreach (self::get_slots($attempt) as $slot => $wordinstanceid) {
            $fraction = $quba->get_question_fraction($slot);
            $totals[$wordinstanceid][] = ($fraction === null) ? 0 : (float) $fraction;
        }
        $scores = [];
        foreach ($totals as $wordinstanceid => $fractions) {
            $scores[$wordinstanceid] = array_sum($fractions) / count($fractions);
        }
        $this->get_progress()->finish_attempt($attempt, $scores, $time);
        return $scores;
    }
}
