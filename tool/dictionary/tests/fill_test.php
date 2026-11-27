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

namespace vocabtool_dictionary;

use mod_vocab\dictionary;
use mod_vocab\dictionary_ai;
use vocabtool_dictionary\task\fill;

#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_dictionary\task\fill::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_dictionary\tool::class)]
/**
 * Tests for the task that fills in the dictionary for a word, with AI.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vocabtool_dictionary\task\fill
 * @covers     \vocabtool_dictionary\tool
 */
final class fill_test extends \advanced_testcase {
    /**
     * Make an activity with a Japanese word, a teacher and an AI provider, and queue a task for the word.
     *
     * @param array $params fields of the log record, to change the defaults.
     * @param array $providerfields fields of the provider, to change the defaults.
     * @return array [log id, word id, provider]
     */
    protected function setup_task(array $params = [], array $providerfields = []): array {
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $vocab = $generator->create_module('vocab', ['course' => $course->id, 'wordlang' => 'ja', 'glosslang' => 'en']);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['すみません']);
        $this->setUser($teacher);
        $provider = $generator->get_plugin_generator('tool_aibridge')->create_provider($providerfields + [
            'contextid' => \context_course::instance($course->id)->id,
        ]);
        $logid = tool::queue_task($params + [
            'userid' => $teacher->id,
            'vocabid' => $vocab->id,
            'wordid' => key($words),
            'providerid' => $provider->get_id(),
            'promptid' => \tool_aibridge\library\template::find(dictionary_ai::PROMPT_ENTRY)->get_id(),
            'wordlang' => 'ja',
            'glosslang' => 'en',
            'level' => 'A1',
            'sensecount' => 2,
            'samplecount' => 1,
        ]);
        return [$logid, (int) key($words), $provider];
    }

    /**
     * Run the task of a log record.
     *
     * @param int $logid
     * @return string the task's output.
     */
    protected function run_task(int $logid): string {
        $task = new fill();
        $task->set_custom_data(['logid' => $logid]);
        ob_start();
        try {
            $task->execute();
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    /**
     * The prompt is sent in English, for a Japanese word with English glosses,
     * and the reply is saved as drafts, from an AI source.
     */
    public function test_execute(): void {
        global $DB;
        [$logid, $wordid, $provider] = $this->setup_task();
        $this->assertEquals(tool::TASKSTATUS_QUEUED, tool::get_log($logid)->status);
        $this->assertSame([$wordid], tool::get_pending_wordids(tool::get_log($logid)->vocabid));

        $reply = json_encode(['lemma' => 'すみません', 'senses' => [[
            'pos' => 'INTJ',
            'meaning' => '人をよぶとき',
            'level' => 'A1',
            'definition' => '人をよぶときに言うことば。',
            'gloss' => 'Excuse me',
            'samples' => [['sentence' => 'すみません、だれかいますか？', 'form' => 'すみません', 'translation' => 'Excuse me, is anyone here?']],
        ]]], JSON_UNESCAPED_UNICODE);
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => $reply]]]]));
        $output = $this->run_task($logid);

        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_COMPLETED, $log->status, $output . $log->error);
        $this->assertStringContainsString('the Japanese vocabulary item "すみません"', $log->prompt);
        $this->assertStringContainsString('a gloss in English', $log->prompt);
        $this->assertStringContainsString('CEFR level A1 Japanese', $log->prompt);
        $this->assertSame($reply, $log->results);
        $this->assertSame(['senses' => 1, 'definitions' => 1, 'glosses' => 1, 'samples' => 1], json_decode($log->counts, true));
        $this->assertSame([], tool::get_pending_wordids($log->vocabid));

        $sense = $DB->get_record('vocab_senses', ['meaning' => '人をよぶとき'], '*', MUST_EXIST);
        $this->assertEquals(dictionary::STATUS_DRAFT, $sense->status);
        $source = $DB->get_record('vocab_sources', ['id' => $sense->sourceid], '*', MUST_EXIST);
        $this->assertSame(dictionary::SOURCE_AI, $source->sourcetype);
        $this->assertEquals($provider->get_id(), $source->providerid);
        $this->assertEquals($log->promptid, $source->templateid);

        // A second task for the same word is skipped, as the word has a sense now.
        [$logid2] = [tool::queue_task(['userid' => $log->userid, 'vocabid' => $log->vocabid, 'wordid' => $wordid,
            'providerid' => $log->providerid, 'promptid' => $log->promptid, 'wordlang' => 'ja', 'glosslang' => 'en',
            'level' => 'A1', 'sensecount' => 2, 'samplecount' => 1])];
        $this->run_task($logid2);
        $this->assertEquals(tool::TASKSTATUS_SKIPPED, tool::get_log($logid2)->status);
    }

    /**
     * A reply that cannot be used fails the task, with the reason, and nothing is saved.
     */
    public function test_execute_bad_reply(): void {
        global $DB;
        [$logid] = $this->setup_task();
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => 'Sorry, I cannot help with that.']]]]));
        $this->run_task($logid);
        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_FAILED, $log->status);
        $this->assertNotSame('', $log->error);
        $this->assertEquals(0, $DB->count_records('vocab_senses'));
    }

    /**
     * A prompt that is not a dictionary entry prompt cannot be used.
     */
    public function test_execute_wrong_prompt(): void {
        $promptid = \tool_aibridge\library\template::find(dictionary_ai::PROMPT_SAMPLES)->get_id();
        [$logid] = $this->setup_task(['promptid' => $promptid]);
        $this->run_task($logid);
        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_FAILED, $log->status);
        $this->assertSame(get_string('error_promptnotfound', 'vocabtool_dictionary', $promptid), $log->error);
    }

    /**
     * If the AI cannot be reached, the task is tried again later, until it has been tried enough times.
     */
    public function test_execute_try_again(): void {
        [$logid] = $this->setup_task(['maxtries' => 2], ['url' => 'http://127.0.0.1:1/v1/chat/completions']);
        try {
            $this->run_task($logid);
            $this->fail('The task should ask to be run again.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_tryagain', $e->errorcode);
        }
        $this->assertEquals(tool::TASKSTATUS_DELAYED, tool::get_log($logid)->status);

        $this->run_task($logid);
        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_FAILED, $log->status);
        $this->assertEquals(2, $log->tries);
    }
}
