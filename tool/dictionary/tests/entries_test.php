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

use core_external\external_api;
use mod_vocab\dictionary;
use vocabtool_dictionary\external\get_entries;
use vocabtool_dictionary\external\set_status;

#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_dictionary\tool::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_dictionary\external\get_entries::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_dictionary\external\set_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\dictionary::class)]
/**
 * Tests for showing the dictionary entries of an activity's words, and approving or rejecting their items.
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vocabtool_dictionary\tool
 * @covers     \vocabtool_dictionary\external\get_entries
 * @covers     \vocabtool_dictionary\external\set_status
 * @covers     \mod_vocab\dictionary
 */
final class entries_test extends \advanced_testcase {
    /** @var \stdClass the activity */
    protected $vocab;

    /** @var \context_module the activity's context */
    protected $context;

    /** @var \stdClass a teacher in the activity's course */
    protected $teacher;

    /** @var \stdClass a manager in the activity's course */
    protected $manager;

    /** @var int the id of the word "apples" */
    protected $wordid;

    /** @var int the id of the word "pear", which has no entry */
    protected $pearid;

    /**
     * Make an activity with the word "apples", whose entry has one sense of "apple",
     * with a definition, a Japanese gloss, and a sample with its translation.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->manager = $generator->create_and_enrol($course, 'manager');
        $this->vocab = $generator->create_module('vocab', ['course' => $course->id, 'wordlang' => 'en', 'glosslang' => 'ja']);
        $this->context = \context_module::instance($this->vocab->cmid);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($this->vocab->id, ['apples', 'pear']);
        $this->wordid = (int) array_search('apples', $words);
        $this->pearid = (int) array_search('pear', $words);

        $sourceid = dictionary::get_ai_source_id(3, 4, 'ChatGPT: Dictionary entry');
        dictionary::save_entry($this->wordid, ['lemma' => 'apple', 'senses' => [[
            'pos' => 'NOUN',
            'meaning' => 'a round fruit',
            'level' => 'A1',
            'definition' => 'A round fruit with red or green skin.',
            'gloss' => 'りんご',
            'samples' => [['sentence' => 'I eat an apple every day.', 'form' => 'apple', 'translation' => '私は毎日りんごを食べます。']],
        ]]], ['wordlang' => 'en', 'glosslang' => 'ja', 'level' => 'A2', 'sourceid' => $sourceid]);
    }

    /**
     * Get the id of an item of the entry.
     *
     * @param string $table
     * @param array $conditions
     * @return int
     */
    protected function get_item_id(string $table, array $conditions = []): int {
        global $DB;
        return (int) $DB->get_field($table, 'MIN(id)', $conditions);
    }

    /**
     * The entry of a word has its lemma's senses and their items; a word with no entry has no senses.
     */
    public function test_get_entries(): void {
        $pearid = $this->pearid;
        $entries = dictionary::get_entries([$pearid, $this->wordid]);
        $this->assertSame([$pearid, $this->wordid], array_keys($entries));
        $this->assertSame([], $entries[$pearid]['senses']);

        $entry = $entries[$this->wordid];
        $this->assertSame('apples', $entry['word']);
        $this->assertSame('apple', $entry['lemma']);
        $this->assertCount(1, $entry['senses']);
        $sense = $entry['senses'][0];
        $expected = ['NOUN', 1, 'a round fruit', 'en', 'A1', dictionary::STATUS_DRAFT];
        $actual = [$sense['pos'], $sense['sensenum'], $sense['text'], $sense['lang'], $sense['level'], $sense['status']];
        $this->assertSame($expected, $actual);
        $this->assertSame('A round fruit with red or green skin.', $sense['definitions'][0]['text']);
        $this->assertSame('A2', $sense['definitions'][0]['level']);
        $this->assertSame(['りんご', 'ja'], [$sense['glosses'][0]['text'], $sense['glosses'][0]['lang']]);
        $this->assertCount(1, $sense['samples']);
        $this->assertSame('apple', $sense['samples'][0]['form']);
        $this->assertSame('私は毎日りんごを食べます。', $sense['samples'][0]['translations'][0]['text']);

        // A sense, a definition, a gloss, a sample and a translation are all drafts.
        $this->assertSame([$this->wordid => 5, $pearid => 0], dictionary::count_drafts([$this->wordid, $pearid]));
    }

    /**
     * A teacher can reject and restore drafts, but only a manager can approve them,
     * or change them once they are approved.
     */
    public function test_set_item_status(): void {
        $senseid = $this->get_item_id('vocab_senses');
        $this->setUser($this->teacher);
        $this->assertTrue(tool::can_change_item($this->context, dictionary::STATUS_DRAFT, dictionary::STATUS_REJECTED));
        $this->assertFalse(tool::can_change_item($this->context, dictionary::STATUS_DRAFT, dictionary::STATUS_APPROVED));

        tool::set_item_status($this->vocab->id, $this->context, 'sense', $senseid, dictionary::STATUS_REJECTED);
        $this->assertSame([$this->wordid => 0], dictionary::count_senses([$this->wordid]));
        tool::set_item_status($this->vocab->id, $this->context, 'sense', $senseid, dictionary::STATUS_DRAFT);

        try {
            tool::set_item_status($this->vocab->id, $this->context, 'sense', $senseid, dictionary::STATUS_APPROVED);
            $this->fail('A teacher should not be able to approve an item.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('dictionary:approve', 'vocabtool_dictionary'), $e->getMessage());
        }

        $this->setUser($this->manager);
        $item = tool::set_item_status($this->vocab->id, $this->context, 'sense', $senseid, dictionary::STATUS_APPROVED);
        $this->assertEquals(dictionary::STATUS_DRAFT, $item->status);
        $entry = tool::export_entries($this->context, [$this->wordid])[$this->wordid];
        $this->assertTrue($entry['senses'][0]['isapproved']);
        $this->assertSame(['reject', 'restore'], array_column($entry['senses'][0]['actions'], 'action'));

        // Now the teacher can do nothing with it.
        $this->setUser($this->teacher);
        $entry = tool::export_entries($this->context, [$this->wordid])[$this->wordid];
        $this->assertFalse($entry['senses'][0]['hasactions']);
        $this->assertSame(['reject'], array_column($entry['senses'][0]['definitions'][0]['actions'], 'action'));
        $this->expectException(\required_capability_exception::class);
        tool::set_item_status($this->vocab->id, $this->context, 'sense', $senseid, dictionary::STATUS_REJECTED);
    }

    /**
     * Only the items of the words in the activity's word list can be changed in the activity.
     */
    public function test_set_item_status_other_activity(): void {
        $other = $this->getDataGenerator()->create_module('vocab', ['course' => $this->vocab->course]);
        $this->setUser($this->manager);
        $definitionid = $this->get_item_id('vocab_definitions');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_itemnotfound', 'vocabtool_dictionary', "definition $definitionid"));
        tool::set_item_status($other->id, \context_module::instance($other->cmid), 'definition', $definitionid, 1);
    }

    /**
     * The web service functions get the entries of the activity's words only, and change an item's status.
     */
    public function test_external(): void {
        $this->setUser($this->teacher);
        $sampleid = $this->get_item_id('vocab_samples', ['originalid' => 0]);
        $other = $this->getDataGenerator()->create_module('vocab', ['course' => $this->vocab->course]);
        $otherword = $this->getDataGenerator()->get_plugin_generator('mod_vocab')->add_words($other->id, ['orange']);

        $result = get_entries::execute($this->vocab->id, [$this->wordid, key($otherword)]);
        $result = external_api::clean_returnvalue(get_entries::execute_returns(), $result);
        $this->assertCount(1, $result['entries']);
        $this->assertSame('noun', $result['entries'][0]['senses'][0]['posname']);
        $this->assertTrue($result['entries'][0]['senses'][0]['isai']);
        $this->assertSame('ChatGPT: Dictionary entry', $result['entries'][0]['senses'][0]['sourcename']);

        $result = set_status::execute($this->vocab->id, 'sample', $sampleid, dictionary::STATUS_REJECTED);
        $result = external_api::clean_returnvalue(set_status::execute_returns(), $result);
        $entry = dictionary::get_entries([$this->wordid])[$this->wordid];
        $this->assertSame($entry['lemmaid'], $result['lemmaid']);
        $this->assertSame(dictionary::STATUS_REJECTED, $entry['senses'][0]['samples'][0]['status']);
    }

    /**
     * The page shows the words, with the entry of the chosen word.
     */
    public function test_export_word_list(): void {
        global $PAGE;
        $this->setUser($this->teacher);
        $vocab = \mod_vocab\activity::create(null, null, $this->vocab->id);
        $list = tool::export_word_list($vocab, [$this->wordid]);
        $this->assertSame(['apples', 'pear'], array_column($list['words'], 'word'));
        $this->assertSame([1, 0], array_column($list['words'], 'sensecount'));
        $this->assertSame([true, false], array_column($list['words'], 'expanded'));

        $PAGE->set_context($this->context);
        $html = $PAGE->get_renderer('core')->render_from_template('vocabtool_dictionary/entries', $list + ['regionid' => 'x']);
        $this->assertStringContainsString('私は毎日りんごを食べます。', $html);
        $this->assertStringContainsString('data-action="setstatus"', $html);
    }
}
