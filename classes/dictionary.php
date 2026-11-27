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

/**
 * The site-wide dictionary: the senses of each lemma, and the definitions,
 * samples, media and other items for each sense (see docs/plan-dictionary.md).
 *
 * Nothing here assumes a language: words, definitions and samples each have
 * their own language, and levels can come from any framework.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dictionary {
    /** An item that nobody has checked yet, e.g. one made by AI. Games may use it. */
    const STATUS_DRAFT = 0;

    /** An item that a teacher wrote or approved. Games prefer it. */
    const STATUS_APPROVED = 1;

    /** An item that a teacher rejected. Games never use it. */
    const STATUS_REJECTED = 2;

    /** A definition: it explains the meaning, usually in the word's own language. */
    const DEFTYPE_DEFINITION = 0;

    /** A gloss: a short translation in another language, e.g. the students' language. */
    const DEFTYPE_GLOSS = 1;

    /** A source of items made by AI, through the AI bridge. */
    const SOURCE_AI = 'ai';

    /** A source of items that a teacher wrote. */
    const SOURCE_TEACHER = 'teacher';

    /** A source of items imported from a file. */
    const SOURCE_IMPORT = 'import';

    /** Any other source. */
    const SOURCE_OTHER = 'other';

    /** The framework of the CEFR levels. */
    const FRAMEWORK_CEFR = 'CEFR';

    /** The CEFR levels, from lowest to highest. */
    const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    /** The languages in which the names of the CEFR levels are added. */
    const CEFR_NAME_LANGS = ['en', 'ja'];

    /**
     * The tables whose items have a source and a review status.
     *
     * @return array of table names.
     */
    public static function get_item_tables(): array {
        return [
            'vocab_senses',
            'vocab_definitions',
            'vocab_samples',
            'vocab_multimedia',
            'vocab_pronunciations',
            'vocab_attributes',
            'vocab_relationships',
        ];
    }

    /**
     * Add the CEFR levels, A1 to C2, and their names in English and Japanese, if they
     * are not there yet. A level that exists without a framework is taken to be CEFR.
     *
     * The names are the ones that the question bank tool uses, e.g. "A1: Basic".
     */
    public static function install_levels(): void {
        global $DB;

        $langids = [];
        $names = [];
        foreach (self::CEFR_NAME_LANGS as $langcode) {
            $langids[$langcode] = self::get_or_create_id('vocab_langs', ['langcode' => $langcode]);
            $names[$langcode] = self::get_level_names($langcode);
        }

        foreach (self::CEFR_LEVELS as $i => $levelcode) {
            $level = $DB->get_record('vocab_levels', ['framework' => self::FRAMEWORK_CEFR, 'levelcode' => $levelcode]);
            if (!$level) {
                $level = $DB->get_record('vocab_levels', ['framework' => '', 'levelcode' => $levelcode]);
            }
            if ($level) {
                $level->framework = self::FRAMEWORK_CEFR;
                $level->sortorder = $i + 1;
                $DB->update_record('vocab_levels', $level);
            } else {
                $level = (object) [
                    'framework' => self::FRAMEWORK_CEFR,
                    'levelcode' => $levelcode,
                    'sortorder' => $i + 1,
                ];
                $level->id = $DB->insert_record('vocab_levels', $level);
            }

            $identifier = 'cefr_' . strtolower($levelcode) . '_description';
            foreach ($langids as $langcode => $langid) {
                if ($DB->record_exists('vocab_levelnames', ['levelid' => $level->id, 'langid' => $langid])) {
                    continue;
                }
                $DB->insert_record('vocab_levelnames', (object) [
                    'levelid' => $level->id,
                    'langid' => $langid,
                    'levelname' => $names[$langcode][$identifier] ?? $names['en'][$identifier],
                ]);
            }
        }
    }

    /**
     * Get the names of the CEFR levels in a language, from the question bank tool's own language
     * file. The file is read directly, as the site may not have that language pack installed.
     *
     * @param string $langcode e.g. "ja"
     * @return array string identifier => string, e.g. "cefr_a1_description" => "A1: Basic"
     */
    protected static function get_level_names(string $langcode): array {
        global $CFG;
        $string = [];
        $file = $CFG->dirroot . '/mod/vocab/tool/questionbank/lang/' . $langcode . '/vocabtool_questionbank.php';
        if (preg_match('/^[a-z_]+$/', $langcode) && file_exists($file)) {
            include($file);
        }
        return $string;
    }

    /**
     * Move the items in a table that belonged to a word form to a sense of the word's lemma.
     * Used once, when senses were added. Each word gets one sense with no part of speech,
     * and a word with no lemma gets a lemma with the same spelling.
     *
     * The table must have both a "wordid" and a "senseid" field.
     *
     * @param string $tablename e.g. "vocab_definitions"
     */
    public static function move_to_senses(string $tablename): void {
        global $DB;

        $wordids = $DB->get_fieldset_select($tablename, 'DISTINCT wordid', 'wordid > 0 AND senseid = 0');
        foreach ($wordids as $wordid) {
            if (!$word = $DB->get_record('vocab_words', ['id' => $wordid])) {
                continue; // The word is missing, so the items cannot be moved.
            }
            if (empty($word->lemmaid) || !$DB->record_exists('vocab_lemmas', ['id' => $word->lemmaid])) {
                $word->lemmaid = self::get_or_create_id('vocab_lemmas', ['langid' => 0, 'lemma' => $word->word]);
                $DB->set_field('vocab_words', 'lemmaid', $word->lemmaid, ['id' => $word->id]);
            }
            $senseid = self::get_sense_id($word->lemmaid, '', 1, self::STATUS_APPROVED);
            $DB->set_field($tablename, 'senseid', $senseid, ['wordid' => $wordid, 'senseid' => 0]);
        }
    }

    /**
     * Find a sense of a lemma, or create it.
     *
     * @param int $lemmaid
     * @param string $pos a Universal Dependencies tag (e.g. NOUN), PHRASE, or empty
     * @param int $sensenum 1, 2, 3 ... within the lemma and part of speech
     * @param int $status for a new sense, one of the STATUS_ constants
     * @param int $sourceid for a new sense, a vocab_sources record, or 0
     * @return int the id of the sense in vocab_senses.
     */
    public static function get_sense_id(
        int $lemmaid,
        string $pos,
        int $sensenum,
        int $status = self::STATUS_DRAFT,
        int $sourceid = 0
    ): int {
        global $DB;
        $params = ['lemmaid' => $lemmaid, 'pos' => $pos, 'sensenum' => $sensenum];
        if ($id = $DB->get_field('vocab_senses', 'id', $params)) {
            return (int) $id;
        }
        $langid = (int) $DB->get_field('vocab_lemmas', 'langid', ['id' => $lemmaid]);
        $time = time();
        return (int) $DB->insert_record('vocab_senses', (object) ($params + [
            'meaning' => '',
            'langid' => $langid,
            'levelid' => 0,
            'sourceid' => $sourceid,
            'status' => $status,
            'timecreated' => $time,
            'timemodified' => $time,
        ]));
    }

    /**
     * Find a record, or create it.
     *
     * @param string $table
     * @param array $fields
     * @return int the id of the record.
     */
    protected static function get_or_create_id(string $table, array $fields): int {
        global $DB;
        $id = $DB->get_field($table, 'MIN(id)', $fields);
        return (int) ($id ?: $DB->insert_record($table, (object) $fields));
    }
}
