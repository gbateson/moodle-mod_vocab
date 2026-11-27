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

    /**
     * Languages are named in English, for prompts, whether or not their language packs are installed.
     */
    public function test_get_language_name(): void {
        $this->assertSame('Japanese', dictionary::get_language_name('ja'));
        $this->assertSame('German', dictionary::get_language_name('de'));
        $this->assertSame('English', dictionary::get_language_name('en_us'));
        $this->assertSame('Chinese', dictionary::get_language_name('zh-Hant'));
        $this->assertSame('xx_yy', dictionary::get_language_name('xx_yy'));
        $this->assertSame('Spanish', dictionary::get_language_name('es'));
        $this->assertSame('Modern Greek', dictionary::get_language_name('el'));
        $this->assertSame('North Ndebele', dictionary::get_language_name('nd'));
        $this->assertSame('en', dictionary::get_base_langcode('EN-us'));
    }

    /**
     * The level menu has the CEFR levels in order, with their names.
     */
    public function test_get_level_menu(): void {
        $this->resetAfterTest();
        $menu = dictionary::get_level_menu();
        $this->assertSame(dictionary::CEFR_LEVELS, array_keys($menu));
        $this->assertSame('A2: Elementary', $menu['A2']);
        $this->assertEquals(0, dictionary::get_levelid('Z9'));
        $this->assertNotEquals(0, dictionary::get_levelid('b1'));
    }

    /**
     * An entry from the AI is saved as drafts from its source: the word moves to its lemma,
     * and each sense gets its definition, gloss and samples, with their translations and the
     * forms of the word that they use. Saving the same entry again adds nothing.
     */
    public function test_save_entry(): void {
        global $DB;
        $this->resetAfterTest();

        // The word was added in a word list, as its own lemma.
        $jaid = dictionary::get_langid('ja');
        $enid = dictionary::get_langid('en');
        $oldlemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => $jaid, 'lemma' => '食べた']);
        $wordid = $DB->insert_record('vocab_words', (object) ['lemmaid' => $oldlemmaid, 'word' => '食べた']);
        $sourceid = dictionary::get_ai_source_id(5, 7, 'Provider: Dictionary entry');
        $this->assertEquals($sourceid, dictionary::get_ai_source_id(5, 7, 'Another name'));
        $this->assertSame([$wordid => 0], dictionary::count_senses([$wordid]));

        $entry = [
            'lemma' => '食べる',
            'senses' => [[
                'pos' => 'VERB',
                'meaning' => '食事をする',
                'level' => 'A1',
                'definition' => '食べ物を口に入れて、のみこむこと。',
                'gloss' => 'to eat',
                'samples' => [
                    ['sentence' => '毎日、くだものを食べます。', 'form' => '食べます', 'translation' => 'I eat fruit every day.'],
                    ['sentence' => 'もう食べた？', 'form' => '食べた', 'translation' => ''],
                ],
            ]],
        ];
        $options = ['wordlang' => 'ja', 'glosslang' => 'en', 'level' => 'A2', 'sourceid' => $sourceid];
        $counts = dictionary::save_entry($wordid, $entry, $options);
        $this->assertSame(['senses' => 1, 'definitions' => 1, 'glosses' => 1, 'samples' => 2], $counts);

        // The word was moved to its lemma, which now has one sense.
        $lemmaid = $DB->get_field('vocab_words', 'lemmaid', ['id' => $wordid]);
        $this->assertNotEquals($oldlemmaid, $lemmaid);
        $this->assertSame('食べる', $DB->get_field('vocab_lemmas', 'lemma', ['id' => $lemmaid]));
        $this->assertSame([$wordid => 1], dictionary::count_senses([$wordid]));

        $sense = $DB->get_record('vocab_senses', ['lemmaid' => $lemmaid]);
        $this->assertSame('VERB', $sense->pos);
        $this->assertEquals(1, $sense->sensenum);
        $this->assertEquals(dictionary::get_levelid('A1'), $sense->levelid);
        $this->assertEquals(dictionary::STATUS_DRAFT, $sense->status);
        $this->assertEquals($sourceid, $sense->sourceid);

        $gloss = $DB->get_record('vocab_definitions', ['senseid' => $sense->id, 'deftype' => dictionary::DEFTYPE_GLOSS]);
        $this->assertSame('to eat', $gloss->definition);
        $this->assertEquals($enid, $gloss->langid);
        $definition = $DB->get_record('vocab_definitions', ['senseid' => $sense->id, 'deftype' => dictionary::DEFTYPE_DEFINITION]);
        $this->assertEquals($jaid, $definition->langid);
        $this->assertEquals(dictionary::get_levelid('A2'), $definition->levelid);

        // Two samples, one translation, and the forms of the word that the samples use.
        $samples = $DB->get_records('vocab_samples', ['senseid' => $sense->id, 'originalid' => 0], 'id');
        $this->assertCount(2, $samples);
        $first = reset($samples);
        $translation = $DB->get_record('vocab_samples', ['originalid' => $first->id]);
        $this->assertSame('I eat fruit every day.', $translation->sample);
        $this->assertEquals($enid, $translation->langid);
        $formid = $DB->get_field('vocab_sample_words', 'wordid', ['sampleid' => $first->id]);
        $this->assertSame('食べます', $DB->get_field('vocab_words', 'word', ['id' => $formid, 'lemmaid' => $lemmaid]));
        $this->assertEquals($wordid, $DB->get_field('vocab_sample_words', 'wordid', ['sampleid' => end($samples)->id]));

        // Saving again adds nothing, and a new sense of the same part of speech gets the next number.
        $counts = dictionary::save_entry($wordid, $entry, $options);
        $this->assertSame(['senses' => 0, 'definitions' => 0, 'glosses' => 0, 'samples' => 0], $counts);
        $entry['senses'][0]['meaning'] = '生活する';
        $entry['senses'][0]['samples'] = [];
        dictionary::save_entry($wordid, $entry, $options);
        $this->assertEquals(2, $DB->get_field('vocab_senses', 'MAX(sensenum)', ['lemmaid' => $lemmaid, 'pos' => 'VERB']));
    }

    /**
     * A word whose lemma is not the word itself keeps its lemma, whatever the AI says.
     */
    public function test_save_entry_keeps_lemma(): void {
        global $DB;
        $this->resetAfterTest();
        $enid = dictionary::get_langid('en');
        $lemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => $enid, 'lemma' => 'run']);
        $wordid = $DB->insert_record('vocab_words', (object) ['lemmaid' => $lemmaid, 'word' => 'ran']);
        $entry = ['lemma' => 'ran', 'senses' => [[
            'pos' => 'VERB', 'meaning' => 'move fast', 'level' => '', 'definition' => '', 'gloss' => '走る', 'samples' => [],
        ]]];
        dictionary::save_entry($wordid, $entry, ['wordlang' => 'en', 'glosslang' => 'ja', 'level' => 'A2']);
        $this->assertEquals($lemmaid, $DB->get_field('vocab_words', 'lemmaid', ['id' => $wordid]));
        $this->assertEquals(1, $DB->count_records('vocab_senses', ['lemmaid' => $lemmaid]));
    }
}
