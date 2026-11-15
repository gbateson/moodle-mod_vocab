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

use tool_aibridge\library\template;
use tool_aibridge\provider;
use tool_aibridge\tuning;

/**
 * Tests for moving the settings of the old "vocabai" subplugins into the AI bridge.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\aibridge_migration::class)]
final class aibridge_migration_test extends \advanced_testcase {
    /**
     * Make the old tables, as they were before the activity used the AI bridge.
     */
    protected function create_old_tables(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(aibridge_migration::CONFIG_TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('owneruserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('subplugin', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
        $table = new \xmldb_table(aibridge_migration::SETTINGS_TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('configid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('value', XMLDB_TYPE_TEXT);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }

    /**
     * Add an old config.
     *
     * @param string $subplugin e.g. "chatgpt".
     * @param int $userid
     * @param int $contextid
     * @param array $settings name => value.
     * @return int the old config id.
     */
    protected function add_config(string $subplugin, int $userid, int $contextid, array $settings): int {
        global $DB;
        $configid = $DB->insert_record(aibridge_migration::CONFIG_TABLE, (object) [
            'owneruserid' => $userid,
            'contextid' => $contextid,
            'subplugin' => "vocabai_$subplugin",
        ]);
        foreach ($settings as $name => $value) {
            $DB->insert_record(aibridge_migration::SETTINGS_TABLE, (object) [
                'configid' => $configid,
                'name' => $name,
                'value' => $value,
            ]);
        }
        return $configid;
    }

    /**
     * Get a moved provider by name.
     *
     * @param string $name
     * @return provider
     */
    protected function get_provider(string $name): provider {
        global $DB;
        return provider::get((int) $DB->get_field(provider::TABLE, 'id', ['name' => $name], MUST_EXIST));
    }

    /**
     * Every kind of item is moved, keeps its owner and is shared in the course;
     * the log and prompt defaults point at the new items; the old data is removed.
     */
    public function test_run(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_old_tables();
        set_config('version', 2024010100, 'vocabai_chatgpt');

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $course = $generator->create_course();
        $activity = $generator->create_module('forum', ['course' => $course->id]);
        $modcontext = \context_module::instance($activity->cmid);
        $coursecontext = \context_course::instance($course->id);

        $chatid = $this->add_config('chatgpt', $teacher->id, $modcontext->id, [
            'chatgpturl' => 'https://api.openai.com/v1/chat/completions',
            'chatgptkey' => 'sk-secret',
            'chatgptmodel' => 'gpt-4o-mini',
            'temperature' => '0.5',
            'top_p' => '',
            'itemcount' => '10',
            'timecount' => '2',
            'timeunit' => '3',
        ]);
        $dalleid = $this->add_config('dalle', $teacher->id, $coursecontext->id, [
            'dalleurl' => 'https://api.openai.com/v1/images/generations',
            'dallekey' => 'sk-image',
            'dallemodel' => 'dall-e-3',
            'style' => 'vivid',
        ]);
        $imagenid = $this->add_config('imagen', $teacher->id, SYSCONTEXTID, [
            'imagenurl' => 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3:predict?key=abc',
            'imagenkey' => 'gemini-key',
            'imagenmodel' => 'imagen-3',
        ]);
        $ttsid = $this->add_config('tts', $teacher->id, $coursecontext->id, [
            'ttsurl' => 'https://api.openai.com/v1/audio/speech',
            'ttskey' => 'sk-tts',
            'ttsmodel' => 'tts-1',
            'voice' => 'nova',
            'speed' => '0.8',
        ]);
        $formatid = $this->add_config('formats', $teacher->id, $coursecontext->id, [
            'formatname' => 'My MC format',
            'formattext' => "::question-name::question-text {\n=correct\n~wrong\n}",
        ]);
        $tunedid = $this->add_config('files', $teacher->id, $coursecontext->id, [
            'filedescription' => 'TOEIC samples',
            'chatgptfileid' => 'file-1',
            'chatgptjobid' => 'ftjob-1',
            'chatgptmodelid' => 'ft:gpt-4o-mini:my-org::abc',
        ]);
        $untunedid = $this->add_config('files', $teacher->id, $coursecontext->id, [
            'filedescription' => 'Not used yet',
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $coursecontext->id,
            'component' => 'vocabai_files',
            'filearea' => 'fileitemid',
            'itemid' => $untunedid,
            'filepath' => '/',
            'filename' => 'samples.jsonl',
        ], '{"messages": []}');
        $promptid = $this->add_config('prompts', $teacher->id, $modcontext->id, [
            'promptname' => 'My prompt: MC',
            'prompttext' => 'Write {{number-of-questions}} questions about "{{vocabulary-item}}". {{output-format}}',
            'promptqtypes' => json_encode(['multichoice' => (string) $formatid]),
            'prompttextid' => $chatid,
            'promptfileid' => $tunedid,
            'promptimageid' => $dalleid,
            'promptqcount' => '3',
        ]);
        $logid = $DB->insert_record('vocabtool_questionbank_log', (object) [
            'qtype' => 'multichoice', 'qlevel' => 'A2', 'qformat' => 'gift',
            'textid' => $chatid, 'promptid' => $promptid, 'formatid' => $formatid, 'fileid' => 0,
            'imageid' => $dalleid, 'audioid' => 999, 'videoid' => $ttsid,
            'subcattype' => 0, 'subcatname' => '', 'tagnames' => '', 'error' => '', 'questionids' => '',
        ]);

        $this->assertTrue(aibridge_migration::run());

        // ChatGPT: the old role, temperature and speed limit are kept; it is shared in the course.
        $chat = $this->get_provider('ChatGPT (gpt-4o-mini)');
        $record = $chat->get_record();
        $this->assertSame('sk-secret', $chat->get_apikey());
        $this->assertNotSame('sk-secret', $record->apikey);
        $this->assertEquals($teacher->id, $record->ownerid);
        $this->assertEquals($coursecontext->id, $record->contextid);
        $this->assertEquals(5, $record->userratelimit);
        $request = json_decode($record->requesttemplate, true);
        $this->assertSame(aibridge_migration::CHATGPT_ROLE, $request['messages'][0]['content']);
        $this->assertEquals(0.5, $request['temperature']);
        $this->assertArrayNotHasKey('top_p', $request);

        $dalle = $this->get_provider('DALL-E 3 (dall-e-3)');
        $this->assertSame('vivid', $dalle->get_token_defaults()['STYLE']);
        $this->assertSame(provider::CAPABILITY_IMAGE, $dalle->get_capability());

        $imagen = $this->get_provider('Imagen (imagen-3)');
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/imagen-3:predict', $imagen->get_record()->url);
        $this->assertEquals(SYSCONTEXTID, $imagen->get_record()->contextid);

        $tts = $this->get_provider('OpenAI speech (tts-1)');
        $this->assertSame('nova', $tts->get_token_defaults()['VOICE']);
        $this->assertEquals(0.8, json_decode($tts->get_record()->requesttemplate, true)['speed']);

        // Tuning files: a tuned model becomes a provider; an unused file is kept, but no job is started.
        $tuned = $this->get_provider('ChatGPT (gpt-4o-mini) (TOEIC samples)');
        $this->assertSame('ft:gpt-4o-mini:my-org::abc', $tuned->get_record()->model);
        $this->assertSame('sk-secret', $tuned->get_apikey());
        $tunings = $DB->get_records(tuning::TABLE, null, 'id');
        $this->assertCount(2, $tunings);
        [$done, $waiting] = array_values($tunings);
        $this->assertSame(tuning::STATUS_SUCCEEDED, $done->status);
        $this->assertEquals($tuned->get_id(), $done->tunedproviderid);
        $this->assertSame(tuning::STATUS_FAILED, $waiting->status);
        $this->assertEquals($chat->get_id(), $waiting->providerid);
        $file = tuning::get($waiting->id)->get_file();
        $this->assertSame('{"messages": []}', $file->get_content());

        // Formats and prompts.
        $format = template::get((int) $DB->get_field(template::TABLE, 'id', ['name' => 'My MC format']));
        $this->assertSame(template::TYPE_FORMAT, $format->get_type());
        $this->assertSame('gift', $format->get_record()->qformat);
        $prompt = template::get((int) $DB->get_field(template::TABLE, 'id', ['name' => 'My prompt: MC']));
        $this->assertSame(['multichoice' => $format->get_shortname()], $prompt->get_formatlinks());
        $this->assertEquals($coursecontext->id, $prompt->get_record()->contextid);
        $this->assertEquals($teacher->id, $prompt->get_record()->ownerid);
        $this->assertTrue($prompt->can_use($modcontext, $teacher->id));

        // The prompt still makes the same text, with the format linked to the question type.
        $rendering = \tool_aibridge\library::render(
            $prompt,
            $modcontext,
            $teacher->id,
            ['vocabulary-item' => 'apple', 'number-of-questions' => 3],
            'multichoice'
        );
        $this->assertTrue($rendering->is_valid(), implode(' ', $rendering->get_errors()));
        $this->assertStringContainsString('Write 3 questions about "apple".', $rendering->get_text());
        $this->assertStringContainsString('~wrong', $rendering->get_text());

        // The teacher's defaults for the prompt.
        $defaults = aibridge::get_prompt_defaults($prompt, $teacher->id);
        $this->assertEquals($tuned->get_id(), $defaults['textassistant']);
        $this->assertEquals($dalle->get_id(), $defaults['imageassistant']);
        $this->assertEquals(['multichoice' => $format->get_id()], $defaults['qtypes']);
        $this->assertEquals('3', $defaults['questioncount']);

        // The log points at the new items; unknown media providers become "none".
        $log = $DB->get_record('vocabtool_questionbank_log', ['id' => $logid]);
        $this->assertEquals($chat->get_id(), $log->textid);
        $this->assertEquals($prompt->get_id(), $log->promptid);
        $this->assertEquals($format->get_id(), $log->formatid);
        $this->assertEquals($dalle->get_id(), $log->imageid);
        $this->assertEquals(aibridge::NONE, $log->audioid);
        $this->assertEquals($tts->get_id(), $log->videoid);

        // The old data is gone.
        $dbman = $DB->get_manager();
        $this->assertFalse($dbman->table_exists(aibridge_migration::CONFIG_TABLE));
        $this->assertFalse($dbman->table_exists(aibridge_migration::SETTINGS_TABLE));
        $this->assertFalse(get_config('vocabai_chatgpt', 'version'));
        $this->assertFalse($DB->record_exists('files', ['component' => 'vocabai_files']));

        // The activity's own templates are in the library.
        $this->assertNotNull(template::find('mod_vocab:toeic-part1'));

        // Running again does nothing.
        $this->assertTrue(aibridge_migration::run());
    }

    /**
     * Speed limits become hourly rate limits.
     */
    public function test_get_ratelimit(): void {
        $this->assertSame(0, aibridge_migration::get_ratelimit((object) []));
        $this->assertSame(60, aibridge_migration::get_ratelimit((object) ['itemcount' => 1, 'timecount' => 1, 'timeunit' => 2]));
        $this->assertSame(1, aibridge_migration::get_ratelimit((object) ['itemcount' => 1, 'timecount' => 1, 'timeunit' => 4]));
        $this->assertSame(30, aibridge_migration::get_ratelimit((object) ['itemcount' => 60, 'timecount' => 2, 'timeunit' => 3]));
    }

    /**
     * The output format of a format's text is guessed.
     */
    public function test_detect_qformat(): void {
        $this->assertSame('xml', aibridge_migration::detect_qformat('<question type="multichoice">'));
        $this->assertSame('multianswer', aibridge_migration::detect_qformat('The {1:MULTICHOICE:=cat~dog} sat.'));
        $this->assertSame('gift', aibridge_migration::detect_qformat('::name::text {=a ~b}'));
    }

    /**
     * Items in contexts that no longer exist are not moved; activities become courses.
     */
    public function test_get_contextid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $coursecontext = \context_course::instance($course->id);
        $this->assertEquals($coursecontext->id, aibridge_migration::get_contextid(\context_module::instance($activity->cmid)->id));
        $this->assertEquals($coursecontext->id, aibridge_migration::get_contextid($coursecontext->id));
        $this->assertEquals(SYSCONTEXTID, aibridge_migration::get_contextid(SYSCONTEXTID));
        $this->assertSame(0, aibridge_migration::get_contextid(999999));
    }
}
