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

namespace vocabtool_questionbank;

use vocabtool_questionbank\task\questions;

/**
 * Tests for the task that generates questions.
 *
 * @package    vocabtool_questionbank
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vocabtool_questionbank\task\questions
 */
final class questions_test extends \advanced_testcase {
    /**
     * Questions are generated with the AI bridge, with an image and the audio of a conversation.
     */
    public function test_execute(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');
        // The media is kept as the provider sends it (the AI bridge's tests cover converting it).
        set_config('imageformat', 'keep', 'tool_aibridge');
        set_config('imagemaxside', 0, 'tool_aibridge');
        set_config('audioformat', 'keep', 'tool_aibridge');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['report']);
        $coursecontext = \context_course::instance($course->id);
        $category = question_make_default_categories([$coursecontext]);

        $this->setUser($teacher);
        $bridge = $generator->get_plugin_generator('tool_aibridge');
        $text = $bridge->create_provider(['name' => 'Text', 'contextid' => $coursecontext->id]);
        $image = $bridge->create_provider(['name' => 'Image', 'capability' => 'image', 'contextid' => $coursecontext->id]);
        $speech = $bridge->create_provider(['name' => 'Speech', 'capability' => 'speech', 'contextid' => $coursecontext->id]);
        $prompt = \tool_aibridge\library\template::find('tool_aibridge:generate-questions');

        $logid = tool::insert_log([
            'userid' => $teacher->id,
            'vocabid' => $vocab->id,
            'wordid' => key($words),
            'qtype' => 'multichoice',
            'qlevel' => 'A2',
            'qcount' => 1,
            'qformat' => '',
            'maxtries' => 3,
            'parentcatid' => $category->id,
            'subcattype' => 0,
            'subcatname' => '',
            'tagtypes' => form::QTAG_AI,
            'tagnames' => '',
            'textid' => $text->get_id(),
            'promptid' => $prompt->get_id(),
            'formatid' => 0,
            'imageid' => \mod_vocab\aibridge::AUTOMATIC,
            'audioid' => $speech->get_id(),
            'videoid' => 0,
            'status' => tool::TASKSTATUS_QUEUED,
            'review' => 0,
        ]);

        // Replies are given last first: the image, the two lines of the conversation, then the questions.
        $png = base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
        \curl::mock_response(json_encode(['data' => [['b64_json' => $png]]]));
        $wav = \tool_aibridge\audio::pcm_to_wav(str_repeat("\x01\x00", 2400), 24000);
        \curl::mock_response($wav);
        \curl::mock_response($wav);
        $gift = "::Q1::[[IMAGE A man reading a report at a desk.]]\n" .
            "[[AUDIO A=woman (worker)\nB=man (manager)\nA: The report is ready.\nB: Thank you.]]\n" .
            "What is ready? {\n=The report #Yes.\n~The lunch #No.\n~The car #No.\n}";
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => $gift]]]]));

        $task = new questions();
        $task->set_userid($teacher->id);
        $task->set_custom_data(['logid' => $logid]);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_COMPLETED, $log->status, $output . $log->error);
        $this->assertSame('gift', $log->qformat);
        $this->assertStringContainsString('"report"', $log->prompt);
        $this->assertEquals(1, $log->tries);

        $questionid = (int) trim($log->questionids);
        $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
        $this->assertMatchesRegularExpression('/<img [^>]*src="@@PLUGINFILE@@\/[^"]+\.png"/', $question->questiontext);
        $this->assertMatchesRegularExpression('/<audio [^>]*>.*\.wav/s', $question->questiontext);
        $this->assertStringNotContainsString('[[', $question->questiontext);

        $files = [];
        $fs = get_file_storage();
        foreach ($fs->get_area_files($coursecontext->id, 'question', 'questiontext', $questionid, 'id', false) as $file) {
            $files[pathinfo($file->get_filename(), PATHINFO_EXTENSION)] = $file;
        }
        $extensions = array_keys($files);
        sort($extensions);
        $this->assertSame(['png', 'vtt', 'wav'], $extensions);
        $this->assertStringContainsString('A: The report is ready.', $files['vtt']->get_content());

        // Each request is in the AI bridge's log, for this plugin.
        $this->assertEquals(4, $DB->count_records('tool_aibridge_log', ['component' => 'vocabtool_questionbank', 'success' => 1]));
    }

    /**
     * When the provider cannot be reached, the task is tried again later,
     * until the allowed number of tries have been made.
     */
    public function test_execute_failure(): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['report']);
        $coursecontext = \context_course::instance($course->id);
        $category = question_make_default_categories([$coursecontext]);
        $this->setUser($teacher);
        $text = $generator->get_plugin_generator('tool_aibridge')->create_provider([
            'contextid' => $coursecontext->id,
            'url' => 'http://127.0.0.1:1/v1/chat/completions',
        ]);
        $logid = tool::insert_log([
            'userid' => $teacher->id, 'vocabid' => $vocab->id, 'wordid' => key($words),
            'qtype' => 'multichoice', 'qlevel' => 'A2', 'qcount' => 1, 'qformat' => '', 'maxtries' => 2,
            'parentcatid' => $category->id, 'subcattype' => 0, 'subcatname' => '', 'tagtypes' => 0, 'tagnames' => '',
            'textid' => $text->get_id(),
            'promptid' => \tool_aibridge\library\template::find('tool_aibridge:generate-questions')->get_id(),
            'formatid' => 0, 'imageid' => 0, 'audioid' => 0, 'videoid' => 0,
            'status' => tool::TASKSTATUS_QUEUED, 'review' => 0,
        ]);
        $task = new questions();
        $task->set_userid($teacher->id);
        $task->set_custom_data(['logid' => $logid]);
        ob_start();
        try {
            $task->execute();
            $this->fail('The task should be tried again later.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_aibridgetryagain', $e->errorcode);
        } finally {
            ob_end_clean();
        }
        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_DELAYED, $log->status);
        $this->assertEquals(1, $log->tries);
        $this->assertNotEmpty($log->error);

        // The second try is the last.
        ob_start();
        $task->execute();
        ob_end_clean();
        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_FAILED, $log->status);
        $this->assertEquals(2, $log->tries);
    }

    /**
     * A conversation on one line is put on several lines, so that the AI bridge can read it.
     */
    public function test_format_dialogue(): void {
        $this->assertSame(
            "A=woman (customer)\nB=man (staff)\nA: Hello. Is it broken?\nB: Yes, it is.",
            questions::format_dialogue('A=woman (customer) B=man (staff) A: Hello. Is it broken? B: Yes, it is.')
        );
        $this->assertSame("A: Hi.\nB: Hello.", questions::format_dialogue('A: Hi.<br>B: Hello.'));
        $this->assertSame('They are walking to work.', questions::format_dialogue('They are walking to work.'));
        $this->assertSame('Note: one line.', questions::format_dialogue('Note: one line.'));
    }

    /**
     * Questions of a question type that offers itself, e.g. SpeakUp, can be generated.
     */
    public function test_execute_offered_qtype(): void {
        global $CFG, $DB;
        if (!\core_component::get_component_directory('qtype_speakup')) {
            $this->markTestSkipped('SpeakUp is not installed.');
        }
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        set_config('requirepolicy', 0, 'tool_aibridge');

        $this->assertContains('speakup', \mod_vocab\subpluginform::get_offered_question_types());
        $this->assertArrayHasKey('speakup', form::get_question_types());
        set_config('qtypes', 'multichoice', 'vocabtool_questionbank');
        $this->assertSame(['multichoice'], array_keys(form::get_question_types()));

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['weekend']);
        $coursecontext = \context_course::instance($course->id);
        $category = question_make_default_categories([$coursecontext]);
        $this->setUser($teacher);
        $text = $generator->get_plugin_generator('tool_aibridge')->create_provider(['contextid' => $coursecontext->id]);
        $prompt = \tool_aibridge\library\template::find('qtype_speakup:speakup-speaking');

        $logid = tool::insert_log([
            'userid' => $teacher->id, 'vocabid' => $vocab->id, 'wordid' => key($words),
            'qtype' => 'speakup', 'qlevel' => 'A2', 'qcount' => 1, 'qformat' => '', 'maxtries' => 1,
            'parentcatid' => $category->id, 'subcattype' => 0, 'subcatname' => '', 'tagtypes' => 0, 'tagnames' => '',
            'textid' => $text->get_id(), 'promptid' => $prompt->get_id(), 'formatid' => 0,
            'imageid' => 0, 'audioid' => 0, 'videoid' => 0,
            'status' => tool::TASKSTATUS_QUEUED, 'review' => 0,
        ]);
        $gift = "::Weekend plans::[html]<p>Listen to the question, and answer it.</p>{\n" .
            "AUDIO: What are you planning to do this weekend?\n" .
            "TIMELIMIT=45\n" .
            "RUBRIC=Give a score out of 100.\\nThen give one suggestion.\n" .
            "TARGETPHRASE=(\"this weekend\", 20%, /1, partial, nocase)\n" .
            "#### <p>This weekend, I'm going to visit my grandparents.</p>\n}";
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => $gift]]]]));

        $task = new questions();
        $task->set_userid($teacher->id);
        $task->set_custom_data(['logid' => $logid]);
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $log = tool::get_log($logid);
        $this->assertEquals(tool::TASKSTATUS_COMPLETED, $log->status, $output . $log->error);
        // The prompt used SpeakUp's own output format.
        $this->assertStringContainsString('AUDIO: audio-prompt', $log->prompt);
        $question = $DB->get_record('question', ['id' => (int) trim($log->questionids)], '*', MUST_EXIST);
        $this->assertSame('speakup', $question->qtype);
        $options = $DB->get_record('qtype_speakup_options', ['questionid' => $question->id], '*', MUST_EXIST);
        $this->assertSame('What are you planning to do this weekend?', $options->audioprompttranscript);
        $this->assertEquals(45, $options->timelimitinseconds);
    }
}
