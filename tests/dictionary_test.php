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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\dictionary::class)]
/**
 * Tests for the site-wide dictionary: levels and senses.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\dictionary
 */
final class dictionary_test extends \advanced_testcase {
    /**
     * The CEFR levels are added on install, with names in English and Japanese,
     * and adding them again changes nothing.
     */
    public function test_install_levels(): void {
        global $DB;
        $this->resetAfterTest();

        $levels = $DB->get_records('vocab_levels', ['framework' => 'CEFR'], 'sortorder', 'levelcode, sortorder');
        $this->assertSame(dictionary::CEFR_LEVELS, array_keys($levels));
        $this->assertEquals(1, $levels['A1']->sortorder);
        $this->assertEquals(6, $levels['C2']->sortorder);
        $this->assertEquals(12, $DB->count_records('vocab_levelnames'));

        $jaid = $DB->get_field('vocab_langs', 'id', ['langcode' => 'ja']);
        $a2id = $DB->get_field('vocab_levels', 'id', ['framework' => 'CEFR', 'levelcode' => 'A2']);
        $name = $DB->get_field('vocab_levelnames', 'levelname', ['levelid' => $a2id, 'langid' => $jaid]);
        $this->assertSame('A2：初級レベル', $name);

        dictionary::install_levels();
        $this->assertEquals(6, $DB->count_records('vocab_levels'));
        $this->assertEquals(12, $DB->count_records('vocab_levelnames'));
    }

    /**
     * A level that was added without a framework, e.g. by the import tool, is taken to be CEFR.
     */
    public function test_install_levels_existing_level(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('vocab_levelnames');
        $DB->delete_records('vocab_levels');
        $b1id = $DB->insert_record('vocab_levels', (object) ['framework' => '', 'levelcode' => 'B1']);

        dictionary::install_levels();
        $this->assertEquals(6, $DB->count_records('vocab_levels'));
        $level = $DB->get_record('vocab_levels', ['id' => $b1id]);
        $this->assertSame('CEFR', $level->framework);
        $this->assertEquals(3, $level->sortorder);
    }

    /**
     * A sense is found by lemma, part of speech and sense number, or created.
     */
    public function test_get_sense_id(): void {
        global $DB;
        $this->resetAfterTest();
        $langid = $DB->insert_record('vocab_langs', (object) ['langcode' => 'fr']);
        $lemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => $langid, 'lemma' => 'banque']);

        $id1 = dictionary::get_sense_id($lemmaid, 'NOUN', 1);
        $id2 = dictionary::get_sense_id($lemmaid, 'NOUN', 2);
        $this->assertNotEquals($id1, $id2);
        $this->assertEquals($id1, dictionary::get_sense_id($lemmaid, 'NOUN', 1));

        $sense = $DB->get_record('vocab_senses', ['id' => $id1]);
        $this->assertEquals($langid, $sense->langid);
        $this->assertEquals(dictionary::STATUS_DRAFT, $sense->status);
    }

    /**
     * Items that belonged to a word form are moved to a sense of its lemma. A word
     * with no lemma gets one, and words with the same lemma share one sense.
     */
    public function test_move_to_senses(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // Make a table like vocab_definitions before senses were added.
        $table = new \xmldb_table('vocab_test_items');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('wordid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('senseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_temp_table($table);

        $langid = $DB->get_field('vocab_langs', 'id', ['langcode' => 'ja'], MUST_EXIST);
        $lemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => $langid, 'lemma' => '食べる']);
        $word1 = $DB->insert_record('vocab_words', (object) ['lemmaid' => $lemmaid, 'word' => '食べる']);
        $word2 = $DB->insert_record('vocab_words', (object) ['lemmaid' => $lemmaid, 'word' => '食べた']);
        $word3 = $DB->insert_record('vocab_words', (object) ['lemmaid' => 0, 'word' => 'すみません']);
        foreach ([$word1, $word2, $word3, $word3] as $wordid) {
            $DB->insert_record('vocab_test_items', (object) ['wordid' => $wordid]);
        }

        dictionary::move_to_senses('vocab_test_items');

        $this->assertEquals(0, $DB->count_records('vocab_test_items', ['senseid' => 0]));
        $senseid = $DB->get_field('vocab_senses', 'id', ['lemmaid' => $lemmaid, 'pos' => '', 'sensenum' => 1]);
        $this->assertEquals(2, $DB->count_records('vocab_test_items', ['senseid' => $senseid]));

        $newlemmaid = $DB->get_field('vocab_words', 'lemmaid', ['id' => $word3]);
        $this->assertEquals('すみません', $DB->get_field('vocab_lemmas', 'lemma', ['id' => $newlemmaid]));
        $senseid = $DB->get_field('vocab_senses', 'id', ['lemmaid' => $newlemmaid]);
        $this->assertEquals(2, $DB->count_records('vocab_test_items', ['senseid' => $senseid]));
        $this->assertEquals(2, $DB->count_records('vocab_senses', ['status' => dictionary::STATUS_APPROVED]));

        $dbman->drop_table($table);
    }
}
