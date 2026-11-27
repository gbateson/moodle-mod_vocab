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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\dictionary_ai::class)]
/**
 * Tests for the dictionary's AI prompts, and the checking of their replies.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\dictionary_ai
 */
final class dictionary_ai_test extends \advanced_testcase {
    /**
     * Each dictionary prompt renders without errors, in JSON, for a word in any language,
     * and is listed with the dictionary's prompts, not with the prompts for questions.
     */
    public function test_prompts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \context_system::instance();
        $userid = get_admin()->id;
        $values = [
            'vocabulary-item' => 'すみません',
            'word-language' => 'Japanese',
            'gloss-language' => 'English',
            'level' => 'A2',
            'part-of-speech' => 'interjection',
            'sense-meaning' => 'だれかいますか？',
        ];
        $prompts = [
            dictionary_ai::PROMPT_ENTRY,
            dictionary_ai::PROMPT_DEFINITION,
            dictionary_ai::PROMPT_SAMPLES,
            dictionary_ai::PROMPT_RELATED,
        ];
        $dictionarymenu = aibridge::get_prompt_menu($context, $userid, true);
        $questionmenu = aibridge::get_prompt_menu($context, $userid);
        foreach ($prompts as $shortname) {
            $rendering = \tool_aibridge\library::render($shortname, $context, $userid, $values);
            $this->assertSame([], $rendering->get_errors(), $shortname);
            $this->assertSame([], $rendering->get_warnings(), $shortname);
            $this->assertSame('json', $rendering->get_qformat(), $shortname);
            $this->assertStringContainsString('すみません', $rendering->get_text(), $shortname);
            $this->assertStringNotContainsString('{{', $rendering->get_text(), $shortname);

            $id = template::find($shortname)->get_id();
            $this->assertArrayHasKey($id, $dictionarymenu, $shortname);
            $this->assertArrayNotHasKey($id, $questionmenu, $shortname);
        }
        $text = \tool_aibridge\library::render(dictionary_ai::PROMPT_SAMPLES, $context, $userid, $values)->get_text();
        $this->assertStringContainsString(
            'Create 2 CEFR level A2 sample sentences in Japanese for the interjection "すみません" in the sense of "だれかいますか？".',
            $text
        );
        $this->assertArrayHasKey(template::find('mod_vocab:toeic-part1')->get_id(), $questionmenu);
    }

    /**
     * A good entry is read, even with markdown and chatter around it.
     */
    public function test_parse_entry(): void {
        $this->resetAfterTest();
        $fence = str_repeat(chr(96), 3); // Three backticks, as in markdown.
        $reply = "Here is the entry:\n{$fence}json\n" . json_encode([
            'lemma' => 'bank',
            'senses' => [
                [
                    'pos' => 'noun',
                    'meaning' => 'money',
                    'level' => 'a2',
                    'definition' => 'A place that keeps <b>money</b> safe for people.',
                    'gloss' => '銀行',
                    'samples' => [
                        ['sentence' => 'I went to the bank.', 'form' => 'bank', 'translation' => '私は銀行に行きました。'],
                        ['sentence' => 'Banks open at nine.', 'form' => 'Banks', 'translation' => '銀行は9時に開きます。'],
                    ],
                ],
                [
                    'pos' => 'NOUN',
                    'meaning' => 'river',
                    'level' => 'B1',
                    'definition' => 'The land at the side of a river.',
                    'gloss' => '土手',
                    'samples' => [],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE) . "\n{$fence}\nI hope this helps!";

        $result = dictionary_ai::parse_entry($reply);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame('bank', $result['data']['lemma']);
        $this->assertCount(2, $result['data']['senses']);

        $sense = $result['data']['senses'][0];
        $this->assertSame('NOUN', $sense['pos']);
        $this->assertSame('A2', $sense['level']);
        $this->assertSame('A place that keeps money safe for people.', $sense['definition']);
        $this->assertSame('銀行', $sense['gloss']);
        $this->assertSame('Banks', $sense['samples'][1]['form']);
        $this->assertSame([], $result['data']['senses'][1]['samples']);
    }

    /**
     * Values that cannot be used are fixed or left out, with warnings.
     */
    public function test_parse_entry_problems(): void {
        $this->resetAfterTest();
        $reply = json_encode([
            'lemma' => 'run',
            'senses' => [
                [
                    'pos' => 'VERBAL',
                    'meaning' => 'move fast',
                    'level' => 'Z9',
                    'definition' => str_repeat('a', 300),
                    'samples' => [
                        ['sentence' => 'She ran home.', 'form' => 'runs'],
                        ['form' => 'run'],
                    ],
                ],
                ['pos' => 'VERB', 'meaning' => 'Move fast'],
                ['pos' => 'NOUN'],
                'not a sense',
            ],
        ]);
        $result = dictionary_ai::parse_entry($reply);
        $this->assertSame([], $result['errors']);
        $this->assertCount(5, $result['warnings']);
        $this->assertCount(2, $result['data']['senses']);

        $sense = $result['data']['senses'][0];
        $this->assertSame('', $sense['pos']);
        $this->assertSame('', $sense['level']);
        $this->assertSame(255, \core_text::strlen($sense['definition']));
        $this->assertCount(1, $sense['samples']);
        $this->assertSame('', $sense['samples'][0]['form']);
        $this->assertSame('VERB', $result['data']['senses'][1]['pos']);
    }

    /**
     * A reply that is not JSON, or has no senses, cannot be used.
     */
    public function test_parse_entry_errors(): void {
        $this->resetAfterTest();
        $this->assertCount(1, dictionary_ai::parse_entry('')['errors']);
        $this->assertCount(1, dictionary_ai::parse_entry('{"lemma": "bank", "senses": [')['errors']);
        $this->assertCount(1, dictionary_ai::parse_entry('{"lemma": "bank"}')['errors']);
        $this->assertCount(1, dictionary_ai::parse_entry('{"lemma": "bank", "senses": [{"pos": "NOUN"}]}')['errors']);
    }

    /**
     * Definitions, samples and related words are read, also when an object is in a list, or a list in an object.
     */
    public function test_parse_others(): void {
        $this->resetAfterTest();

        $result = dictionary_ai::parse_definition('[{"definition": "Ein Möbel zum Sitzen.", "gloss": "bench"}]');
        $this->assertSame([], $result['errors']);
        $this->assertSame(['definition' => 'Ein Möbel zum Sitzen.', 'gloss' => 'bench'], $result['data']);
        $this->assertCount(1, dictionary_ai::parse_definition('{"meaning": "bench"}')['errors']);

        $reply = '{"samples": [{"sentence": "すみません、だれかいますか？", "form": "すみません", "translation": "Excuse me, is anyone here?"}]}';
        $result = dictionary_ai::parse_samples($reply);
        $this->assertSame([], $result['errors']);
        $this->assertSame('すみません', $result['data'][0]['form']);
        $this->assertCount(1, dictionary_ai::parse_samples('[]')['errors']);

        $reply = json_encode([
            ['relation' => 'Synonym', 'item' => 'choice', 'pos' => 'NOUN', 'meaning' => 'something chosen'],
            ['relation' => 'synonym', 'item' => 'Choice', 'pos' => 'NOUN'],
            ['relation' => 'collocation', 'item' => 'make a decision', 'pos' => 'PHRASE'],
            ['relation' => 'cousin', 'item' => 'decisive'],
        ]);
        $result = dictionary_ai::parse_related($reply);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame(['synonym', 'collocation'], array_column($result['data'], 'relation'));
        $this->assertSame('PHRASE', $result['data'][1]['pos']);
    }
}
