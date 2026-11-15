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
 * Play the quiz game: start or continue an attempt, answer its questions,
 * and see the results.
 *
 * @package    vocabgame_quiz
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use mod_vocab\progress;

$id = required_param('id', PARAM_INT); // The course module id.
$instanceid = required_param('instanceid', PARAM_INT);
$reviewid = optional_param('review', 0, PARAM_INT); // An attempt to review.

$cm = get_coursemodule_from_id('vocab', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$vocab = $DB->get_record('vocab', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vocab:attempt', $context);

$instance = $DB->get_record('vocab_game_instances', ['id' => $instanceid, 'vocabid' => $vocab->id], '*', MUST_EXIST);
$game = \mod_vocab\gamebase::create($vocab, $cm, $instance);
if (!$game instanceof \vocabgame_quiz\game) {
    throw new moodle_exception('invalidrecord', 'error', '', 'vocab_game_instances');
}

$plugin = 'vocabgame_quiz';
$viewurl = new moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
$url = new moodle_url('/mod/vocab/game/quiz/play.php', ['id' => $cm->id, 'instanceid' => $instance->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($vocab->name) . ': ' . $game->get_label());
$PAGE->set_heading(format_string($course->fullname));

// Review a finished attempt.
if ($reviewid) {
    $attempt = $DB->get_record('vocab_game_attempts', [
        'id' => $reviewid,
        'gameinstanceid' => $instance->id,
        'userid' => $USER->id,
        'status' => progress::ATTEMPT_FINISHED,
    ], '*', MUST_EXIST);
    $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
    $options = new question_display_options();
    $options->readonly = true;
    $options->marks = question_display_options::MARK_AND_MAX;
    $options->correctness = question_display_options::VISIBLE;
    $options->feedback = question_display_options::VISIBLE;
    $options->numpartscorrect = question_display_options::VISIBLE;
    $options->generalfeedback = question_display_options::VISIBLE;
    $options->rightanswer = question_display_options::VISIBLE;

    question_engine::initialise_js();
    $head = '';
    foreach ($quba->get_slots() as $slot) {
        $head .= $quba->render_question_head_html($slot);
    }
    echo $OUTPUT->header();
    echo $head;
    echo $OUTPUT->heading(get_string('results', $plugin), 3);

    // The score for each word.
    $words = (new progress($vocab))->get_words();
    $table = new html_table();
    $table->head = [get_string('resultsword', $plugin), get_string('resultsscore', $plugin)];
    $table->attributes['class'] = 'generaltable w-auto';
    $sql = "SELECT vwu.wordinstanceid, vwa.score
              FROM {vocab_word_usages} vwu
              JOIN {vocab_word_attempts} vwa ON vwa.wordusageid = vwu.id
             WHERE vwu.gameattemptid = ?";
    foreach ($DB->get_records_sql_menu($sql, [$attempt->id]) as $wordinstanceid => $score) {
        $table->data[] = [s($words[$wordinstanceid]->word ?? ''), format_float($score, 0) . '%'];
    }
    echo html_writer::table($table);

    $number = 1;
    foreach ($quba->get_slots() as $slot) {
        echo $quba->render_question($slot, $options, $number++);
    }
    echo $OUTPUT->single_button($viewurl, get_string('returntoactivity', $plugin), 'get');
    echo $OUTPUT->footer();
    exit;
}

// Continue an unfinished attempt, or start a new one.
$progress = new progress($vocab);
$attempt = progress::get_unfinished_attempt($instance->id, $USER->id);
if (!$attempt) {
    [$playable, $reason] = $progress->get_game_state($game, $USER->id);
    if ($playable) {
        $attempt = $game->start_attempt($USER->id);
    }
    if (!$attempt) {
        $message = $reason ? get_string('game' . $reason, 'mod_vocab') : get_string('noquestionsforwords', $plugin);
        redirect($viewurl, $message, null, \core\output\notification::NOTIFY_WARNING);
    }
}

// Save the answers, and finish the attempt or leave it for later.
if (data_submitted() && confirm_sesskey()) {
    $finish = optional_param('finish', '', PARAM_RAW) !== '';
    $game->process_attempt($attempt, $finish);
    if ($finish) {
        redirect(new moodle_url($url, ['review' => $attempt->id]));
    }
    redirect($viewurl);
}

// Show the questions.
$quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
$options = new question_display_options();
$options->marks = question_display_options::HIDDEN;
$options->feedback = question_display_options::HIDDEN;
$options->generalfeedback = question_display_options::HIDDEN;
$options->rightanswer = question_display_options::HIDDEN;
$options->correctness = question_display_options::HIDDEN;
$options->flags = question_display_options::HIDDEN;

question_engine::initialise_js();
$head = '';
foreach ($quba->get_slots() as $slot) {
    $head .= $quba->render_question_head_html($slot);
}
echo $OUTPUT->header();
echo $head;
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'enctype' => 'multipart/form-data',
    'id' => 'responseform',
    'class' => 'vocabgame-quiz',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'slots', 'value' => implode(',', $quba->get_slots())]);
$number = 1;
foreach ($quba->get_slots() as $slot) {
    echo $quba->render_question($slot, $options, $number++);
}
echo html_writer::start_div('d-flex flex-wrap gap-2 my-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'finish',
    'value' => get_string('finish', $plugin),
    'class' => 'btn btn-primary me-2 mr-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'save',
    'value' => get_string('saveandleave', $plugin),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
