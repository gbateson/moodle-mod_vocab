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

namespace vocabtool_dictionary\task;

use mod_vocab\dictionary;
use mod_vocab\dictionary_ai;
use vocabtool_dictionary\tool;

/**
 * A background task that fills in the dictionary for one word: it sends the dictionary's
 * entry prompt to the AI, as the teacher who asked for it, checks the reply, and saves the
 * senses, definitions, glosses and samples as drafts (see docs/plan-dictionary.md, step 3).
 *
 * If the AI bridge says that an error may not happen later, e.g. after a rate limit, the task
 * fails, so that Moodle runs it again later, until the allowed number of tries have been made.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fill extends \core\task\adhoc_task {
    /**
     * Get the name of this task, for the admin pages.
     *
     * @return string
     */
    public function get_name() {
        return get_string('filltask', 'vocabtool_dictionary');
    }

    /**
     * Fill in the dictionary for the word in the task's log record.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $log = (isset($data->logid) ? tool::get_log((int) $data->logid) : false);
        if (!$log || in_array((int) $log->status, tool::TASKSTATUS_FINISHED)) {
            return; // Nothing to do.
        }
        $logid = (int) $log->id;
        $wordid = (int) $log->wordid;

        if (!$word = $DB->get_record('vocab_words', ['id' => $wordid])) {
            return $this->fail($logid, get_string('error_wordnotfound', 'vocabtool_dictionary', $wordid));
        }
        mtrace(get_string('fillingin', 'vocabtool_dictionary', $word->word), ' ');

        // Each word is sent to the AI only once for the whole site, unless the teacher asked for more.
        if ($log->skipexisting) {
            $counts = dictionary::count_senses([$wordid]);
            if (!empty($counts[$wordid])) {
                tool::update_log($logid, ['status' => tool::TASKSTATUS_SKIPPED]);
                mtrace('[' . get_string('statusskipped', 'vocabtool_dictionary') . ']');
                return;
            }
        }

        if (!\mod_vocab\aibridge::is_enabled()) {
            return $this->fail($logid, get_string('aibridgedisabled', 'vocabtool_dictionary'));
        }
        if (!$cm = get_coursemodule_from_instance('vocab', $log->vocabid)) {
            return $this->fail($logid, get_string('error_activitynotfound', 'vocabtool_dictionary', $log->vocabid));
        }
        $context = \context_module::instance($cm->id);
        $userid = (int) $log->userid;

        $prompt = \mod_vocab\aibridge::get_template((int) $log->promptid);
        if (!$prompt || \mod_vocab\aibridge::get_dictionary_prompt_kind($prompt) !== 'entry') {
            return $this->fail($logid, get_string('error_promptnotfound', 'vocabtool_dictionary', $log->promptid));
        }

        $values = [
            'vocabulary-item' => $word->word,
            'word-language' => dictionary::get_language_name($log->wordlang),
            'gloss-language' => dictionary::get_language_name($log->glosslang),
            'level-framework' => dictionary::FRAMEWORK_CEFR,
            'level' => $log->level,
            'number-of-senses' => max(1, (int) $log->sensecount),
            'number-of-samples' => max(1, (int) $log->samplecount),
        ];
        try {
            $rendering = \tool_aibridge\library::render($prompt, $context, $userid, $values);
        } catch (\moodle_exception $e) {
            return $this->fail($logid, $e->getMessage());
        }
        if (!$rendering->is_valid()) {
            return $this->fail($logid, implode(' ', $rendering->get_errors()));
        }

        $tries = (int) $log->tries + 1;
        $maxtries = max(1, min(10, (int) $log->maxtries));
        tool::update_log($logid, [
            'status' => tool::TASKSTATUS_FETCHING_RESULTS,
            'prompt' => $rendering->get_text(),
            'tries' => $tries,
        ]);

        $providerid = max(0, (int) $log->providerid);
        [$response] = \tool_aibridge\library::generate($rendering, $context, $userid, 'vocabtool_dictionary', $providerid);
        if (!$response->get_success()) {
            $error = $response->get_error();
            if ($response->is_temporary() && $tries < $maxtries) {
                tool::update_log($logid, ['status' => tool::TASKSTATUS_DELAYED, 'error' => shorten_text($error, 255)]);
                throw new \moodle_exception('error_tryagain', 'vocabtool_dictionary', '', $error);
            }
            return $this->fail($logid, $error);
        }

        $reply = $response->get_content();
        tool::update_log($logid, ['status' => tool::TASKSTATUS_SAVING_RESULTS, 'results' => $reply]);

        $result = dictionary_ai::parse_entry($reply);
        if ($result['errors']) {
            return $this->fail($logid, implode(' ', $result['errors']));
        }

        $name = $response->get_providername() . ': ' . format_string($prompt->get_name());
        $sourceid = dictionary::get_ai_source_id($response->get_providerid(), $prompt->get_id(), $name);
        $counts = dictionary::save_entry($wordid, $result['data'], [
            'wordlang' => $log->wordlang,
            'glosslang' => $log->glosslang,
            'level' => $log->level,
            'sourceid' => $sourceid,
        ]);

        tool::update_log($logid, [
            'status' => tool::TASKSTATUS_COMPLETED,
            'counts' => json_encode($counts),
            'error' => shorten_text(implode(' ', $result['warnings']), 255),
        ]);
        mtrace('[' . get_string('ok') . ']');
    }

    /**
     * Record that the task failed.
     *
     * @param int $logid
     * @param string $error
     */
    protected function fail(int $logid, string $error): void {
        tool::update_log($logid, ['status' => tool::TASKSTATUS_FAILED, 'error' => shorten_text($error, 255)]);
        mtrace('[' . get_string('statusfailed', 'vocabtool_dictionary') . '] ' . $error);
    }
}
