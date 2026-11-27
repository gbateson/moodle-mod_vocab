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

    /**
     * The parts of speech: the Universal Dependencies tags (https://universaldependencies.org/u/pos/),
     * which work for any language, and PHRASE for a multi-word expression. PUNCT, SYM and X are left out.
     */
    const POS_TAGS = [
        'ADJ', 'ADP', 'ADV', 'AUX', 'CCONJ', 'DET', 'INTJ', 'NOUN',
        'NUM', 'PART', 'PRON', 'PROPN', 'SCONJ', 'VERB', 'PHRASE',
    ];

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
     * Get the main part of a language code, e.g. "en" for "en_us", or "zh" for "zh-Hant".
     *
     * @param string $langcode
     * @return string
     */
    public static function get_base_langcode(string $langcode): string {
        $langcode = strtolower(str_replace('-', '_', trim($langcode)));
        return explode('_', $langcode)[0];
    }

    /**
     * Get the English name of a language, for prompts, e.g. "Japanese" for "ja".
     * Any language can be named, whether or not its language pack is installed.
     *
     * Moodle's ISO names are made plain: only the first name is used, without any
     * brackets, and inverted names are put back in order, e.g. "Spanish" for
     * "Spanish; Castilian", and "Modern Greek" for "Greek, Modern (1453-)".
     *
     * @param string $langcode e.g. "ja", "en_us" or "zh-Hant"
     * @return string the name, or the code itself if the language is not known.
     */
    public static function get_language_name(string $langcode): string {
        $names = get_string_manager()->get_list_of_languages('en');
        $code = strtolower(str_replace('-', '_', trim($langcode)));
        if (!isset($names[$code])) {
            $code = self::get_base_langcode($code);
        }
        if (!isset($names[$code])) {
            return $langcode;
        }
        $name = explode(';', $names[$code])[0];
        $name = trim(preg_replace('/\s*\(.*?\)/u', '', $name));
        if (preg_match('/^([^,]+),\s*([^,]+)$/u', $name, $match)) {
            $name = $match[2] . ' ' . $match[1];
        }
        return $name;
    }

    /**
     * Get the id of a language in vocab_langs, adding it if necessary.
     *
     * @param string $langcode
     * @return int
     */
    public static function get_langid(string $langcode): int {
        return self::get_or_create_id('vocab_langs', ['langcode' => $langcode]);
    }

    /**
     * Get the levels of a framework, for a menu, with their names in the current
     * language if there are any, else in English, else the level codes.
     *
     * @param string $framework
     * @return string[] level code => name, from lowest to highest.
     */
    public static function get_level_menu(string $framework = self::FRAMEWORK_CEFR): array {
        global $DB;
        $langs = array_unique([current_language(), self::get_base_langcode(current_language()), 'en']);
        $menu = [];
        foreach ($DB->get_records('vocab_levels', ['framework' => $framework], 'sortorder, levelcode') as $level) {
            $menu[$level->levelcode] = $level->levelcode;
            $sql = 'SELECT g.langcode, n.levelname
                      FROM {vocab_levelnames} n
                      JOIN {vocab_langs} g ON g.id = n.langid
                     WHERE n.levelid = ?';
            $names = $DB->get_records_sql_menu($sql, [$level->id]);
            foreach ($langs as $lang) {
                if (!empty($names[$lang])) {
                    $menu[$level->levelcode] = $names[$lang];
                    break;
                }
            }
        }
        return $menu;
    }

    /**
     * Get the id of a level in vocab_levels, e.g. "A2" in "CEFR".
     *
     * @param string $levelcode
     * @param string $framework
     * @return int the id, or 0 if there is no such level.
     */
    public static function get_levelid(string $levelcode, string $framework = self::FRAMEWORK_CEFR): int {
        global $DB;
        if ($levelcode === '') {
            return 0;
        }
        $select = 'framework = ? AND ' . $DB->sql_equal('levelcode', '?', false);
        return (int) $DB->get_field_select('vocab_levels', 'MIN(id)', $select, [$framework, $levelcode]);
    }

    /**
     * Get the source of items made by AI, with a provider and a prompt, adding it if necessary.
     *
     * @param int $providerid the provider in the AI bridge, or 0 if the bridge chose one.
     * @param int $templateid the prompt in the AI bridge's library.
     * @param string $name e.g. "Teacher ChatGPT: Dictionary: entry"
     * @return int the id of the source in vocab_sources.
     */
    public static function get_ai_source_id(int $providerid, int $templateid, string $name): int {
        global $DB;
        $params = ['sourcetype' => self::SOURCE_AI, 'providerid' => $providerid, 'templateid' => $templateid];
        if ($id = $DB->get_field('vocab_sources', 'MIN(id)', $params)) {
            return (int) $id;
        }
        return (int) $DB->insert_record('vocab_sources', (object) ($params + [
            'name' => \core_text::substr($name, 0, 255),
            'timecreated' => time(),
        ]));
    }

    /**
     * Count the senses of the lemmas of some words, leaving out rejected senses.
     *
     * @param int[] $wordids
     * @return int[] word id => number of senses.
     */
    public static function count_senses(array $wordids): array {
        global $DB;
        if (empty($wordids)) {
            return [];
        }
        [$where, $params] = $DB->get_in_or_equal($wordids, SQL_PARAMS_NAMED);
        $params['rejected'] = self::STATUS_REJECTED;
        $sql = "SELECT w.id, COUNT(s.id) AS sensecount
                  FROM {vocab_words} w
             LEFT JOIN {vocab_senses} s ON s.lemmaid = w.lemmaid AND w.lemmaid > 0 AND s.status <> :rejected
                 WHERE w.id $where
              GROUP BY w.id";
        return array_map('intval', $DB->get_records_sql_menu($sql, $params));
    }

    /**
     * Save a dictionary entry for a word, from an AI's reply (see dictionary_ai::parse_entry()).
     * Every item is saved as a draft, from the given source.
     *
     * - If the word is still its own lemma, e.g. "ran", and the entry gives another, e.g. "run",
     *   the word is moved to that lemma.
     * - A sense with the same part of speech and meaning as an existing one is used again.
     * - Each sense gets its definition (in the word's language, at the level), its gloss (in the
     *   students' language) and its samples, each with its translation. The form of the word in
     *   each sample is added to the lemma's forms, if it is new, and linked to the sample.
     * - An item that is already there, with the same text, is not added again.
     *
     * @param int $wordid
     * @param array $entry ['lemma' => string, 'senses' => [...]]
     * @param array $options wordlang, glosslang, level (e.g. "A2"), framework (optional) and sourceid.
     * @return int[] the number of senses, definitions, glosses and samples that were added.
     */
    public static function save_entry(int $wordid, array $entry, array $options): array {
        global $DB;

        $framework = $options['framework'] ?? self::FRAMEWORK_CEFR;
        $wordlangid = self::get_langid($options['wordlang']);
        $glosslangid = self::get_langid($options['glosslang']);
        $levelid = self::get_levelid($options['level'] ?? '', $framework);
        $time = time();
        $item = [
            'sourceid' => (int) ($options['sourceid'] ?? 0),
            'status' => self::STATUS_DRAFT,
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        $counts = ['senses' => 0, 'definitions' => 0, 'glosses' => 0, 'samples' => 0];

        $transaction = $DB->start_delegated_transaction();
        $word = $DB->get_record('vocab_words', ['id' => $wordid], '*', MUST_EXIST);
        $lemmaid = self::get_entry_lemmaid($word, (string) ($entry['lemma'] ?? ''), $wordlangid);
        $langid = (int) $DB->get_field('vocab_lemmas', 'langid', ['id' => $lemmaid]) ?: $wordlangid;

        foreach ($entry['senses'] as $sense) {
            [$senseid, $isnew] = self::save_sense($lemmaid, $sense, $langid, $framework, $item);
            if ($isnew) {
                $counts['senses']++;
            }
            $definitions = [
                'definitions' => [$sense['definition'], $langid, $levelid, self::DEFTYPE_DEFINITION],
                'glosses' => [$sense['gloss'], $glosslangid, 0, self::DEFTYPE_GLOSS],
            ];
            foreach ($definitions as $type => [$text, $deflangid, $deflevelid, $deftype]) {
                if ($text === '') {
                    continue;
                }
                $params = ['senseid' => $senseid, 'langid' => $deflangid, 'deftype' => $deftype, 'definition' => $text];
                if (!$DB->record_exists('vocab_definitions', $params)) {
                    $DB->insert_record('vocab_definitions', (object) ($params + ['levelid' => $deflevelid] + $item));
                    $counts[$type]++;
                }
            }
            foreach ($sense['samples'] as $sample) {
                $sampleid = self::save_sample($senseid, $sample['sentence'], $langid, $levelid, 0, $item);
                if (!$sampleid) {
                    continue; // The sample is there already.
                }
                $counts['samples']++;
                if ($sample['translation'] !== '') {
                    self::save_sample($senseid, $sample['translation'], $glosslangid, 0, $sampleid, $item);
                }
                if ($sample['form'] !== '') {
                    $formid = self::get_or_create_id('vocab_words', ['lemmaid' => $lemmaid, 'word' => $sample['form']]);
                    $DB->insert_record('vocab_sample_words', (object) ['wordid' => $formid, 'sampleid' => $sampleid]);
                }
            }
        }
        $transaction->allow_commit();
        return $counts;
    }

    /**
     * Get the lemma of a word, for an entry. If the word is still its own lemma, or has
     * none, and the entry gives another lemma, the word is moved to that lemma.
     *
     * @param \stdClass $word a record from vocab_words.
     * @param string $entrylemma the lemma that the entry gives, or "".
     * @param int $langid the language of the word, if it has no lemma yet.
     * @return int the id of the lemma.
     */
    protected static function get_entry_lemmaid(\stdClass $word, string $entrylemma, int $langid): int {
        global $DB;
        $lemma = ($word->lemmaid ? $DB->get_record('vocab_lemmas', ['id' => $word->lemmaid]) : false);
        if ($lemma && ($entrylemma === '' || $lemma->lemma !== $word->word || $lemma->lemma === $entrylemma)) {
            return (int) $lemma->id;
        }
        if ($lemma) {
            $langid = (int) $lemma->langid;
        }
        $lemmaid = self::get_or_create_id('vocab_lemmas', [
            'langid' => $langid,
            'lemma' => ($entrylemma === '' ? $word->word : $entrylemma),
        ]);
        $DB->set_field('vocab_words', 'lemmaid', $lemmaid, ['id' => $word->id]);
        return $lemmaid;
    }

    /**
     * Find a sense of a lemma with the same part of speech and meaning, or add it,
     * with the next sense number for its part of speech.
     *
     * @param int $lemmaid
     * @param array $sense pos, meaning and level.
     * @param int $langid the language of the meaning.
     * @param string $framework the framework of the level.
     * @param array $item sourceid, status, timecreated and timemodified.
     * @return array [int $senseid, bool $isnew]
     */
    protected static function save_sense(int $lemmaid, array $sense, int $langid, string $framework, array $item): array {
        global $DB;
        $meaning = \core_text::strtolower($sense['meaning']);
        $sensenum = 0;
        foreach ($DB->get_records('vocab_senses', ['lemmaid' => $lemmaid, 'pos' => $sense['pos']]) as $existing) {
            if (\core_text::strtolower($existing->meaning) === $meaning) {
                return [(int) $existing->id, false];
            }
            $sensenum = max($sensenum, (int) $existing->sensenum);
        }
        $senseid = $DB->insert_record('vocab_senses', (object) ([
            'lemmaid' => $lemmaid,
            'pos' => $sense['pos'],
            'sensenum' => $sensenum + 1,
            'meaning' => $sense['meaning'],
            'langid' => $langid,
            'levelid' => self::get_levelid($sense['level'], $framework),
        ] + $item));
        return [(int) $senseid, true];
    }

    /**
     * Add a sample sentence, or its translation, to a sense, unless it is there already.
     *
     * @param int $senseid
     * @param string $text
     * @param int $langid
     * @param int $levelid
     * @param int $originalid for a translation, the sample that it translates, or 0.
     * @param array $item sourceid, status, timecreated and timemodified.
     * @return int the id of the new sample, or 0 if it was there already.
     */
    protected static function save_sample(
        int $senseid,
        string $text,
        int $langid,
        int $levelid,
        int $originalid,
        array $item
    ): int {
        global $DB;
        $select = 'senseid = ? AND langid = ? AND ' . $DB->sql_compare_text('sample') . ' = ' . $DB->sql_compare_text('?');
        if ($DB->record_exists_select('vocab_samples', $select, [$senseid, $langid, $text])) {
            return 0;
        }
        return (int) $DB->insert_record('vocab_samples', (object) ([
            'senseid' => $senseid,
            'langid' => $langid,
            'levelid' => $levelid,
            'sample' => $text,
            'originalid' => $originalid,
        ] + $item));
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
