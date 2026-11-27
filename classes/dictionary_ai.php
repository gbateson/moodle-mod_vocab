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

use core_text;
use tool_aibridge\library\parser;

/**
 * The dictionary's AI prompts, and the checking of their replies.
 *
 * The prompts are templates in the AI bridge's library (see db/aibridge_templates.php).
 * They are written in English, and work for vocabulary in any language: the language of
 * the word and the students' language are parameters. Each reply is JSON, which is
 * read here into plain arrays, with any values that cannot be used fixed or left out,
 * so that they can be saved field by field (see docs/plan-dictionary.md).
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dictionary_ai {
    /** A dictionary entry: senses, with a definition, gloss and samples for each. The other prompts extend it. */
    const PROMPT_ENTRY = 'mod_vocab:dictionary-entry';

    /** A definition and gloss, for one sense. */
    const PROMPT_DEFINITION = 'mod_vocab:dictionary-definition';

    /** Sample sentences, for one sense. */
    const PROMPT_SAMPLES = 'mod_vocab:dictionary-samples';

    /** Related words, for one sense. */
    const PROMPT_RELATED = 'mod_vocab:dictionary-related';

    /** The relations that the "related words" prompt asks for. */
    const RELATIONS = ['synonym', 'antonym', 'collocation', 'family'];

    /** The most characters in a text field of the dictionary. */
    const MAX_LENGTH = 255;

    /**
     * Read the reply to the "entry" prompt.
     *
     * @param string $reply
     * @param string $framework the framework of the levels, e.g. "CEFR".
     * @return array ['data' => ['lemma' => string, 'senses' => [...]], 'errors' => [], 'warnings' => []].
     *               Each sense has pos, meaning, level, definition, gloss and samples. Each sample
     *               has sentence, form and translation.
     */
    public static function parse_entry(string $reply, string $framework = dictionary::FRAMEWORK_CEFR): array {
        $result = self::decode($reply);
        $json = $result['data'];
        $result['data'] = ['lemma' => '', 'senses' => []];
        if ($result['errors']) {
            return $result;
        }
        if (!is_array($json) || !isset($json['senses']) || !is_array($json['senses'])) {
            $result['errors'][] = get_string('dictionaryreplynosenses', 'mod_vocab');
            return $result;
        }

        $result['data']['lemma'] = self::text($json, 'lemma', $result);
        $levels = self::get_levelcodes($framework);
        $seen = [];
        foreach (self::items($json['senses']) as $i => $item) {
            $sense = [
                'pos' => self::pos($item, $result),
                'meaning' => self::text($item, 'meaning', $result),
                'level' => self::level($item, $levels, $result),
                'definition' => self::text($item, 'definition', $result),
                'gloss' => self::text($item, 'gloss', $result),
                'samples' => self::samples($item['samples'] ?? [], $result),
            ];
            if ($sense['meaning'] === '' && $sense['definition'] === '' && $sense['gloss'] === '') {
                $result['warnings'][] = get_string('dictionaryreplyemptysense', 'mod_vocab', $i + 1);
                continue;
            }
            $key = $sense['pos'] . '|' . core_text::strtolower($sense['meaning'] ?: $sense['definition']);
            if (isset($seen[$key])) {
                $result['warnings'][] = get_string('dictionaryreplysamesense', 'mod_vocab', $i + 1);
                continue;
            }
            $seen[$key] = true;
            $result['data']['senses'][] = $sense;
        }
        if (empty($result['data']['senses'])) {
            $result['errors'][] = get_string('dictionaryreplynosenses', 'mod_vocab');
        }
        return $result;
    }

    /**
     * Read the reply to the "definition" prompt.
     *
     * @param string $reply
     * @return array ['data' => ['definition' => string, 'gloss' => string], 'errors' => [], 'warnings' => []].
     */
    public static function parse_definition(string $reply): array {
        $result = self::decode($reply);
        $json = $result['data'];
        $result['data'] = ['definition' => '', 'gloss' => ''];
        if ($result['errors']) {
            return $result;
        }
        if (is_array($json) && array_keys($json) === [0] && is_array($json[0])) {
            $json = $json[0]; // The object, inside a list.
        }
        if (is_array($json)) {
            $result['data']['definition'] = self::text($json, 'definition', $result);
            $result['data']['gloss'] = self::text($json, 'gloss', $result);
        }
        if ($result['data']['definition'] === '' && $result['data']['gloss'] === '') {
            $result['errors'][] = get_string('dictionaryreplynodefinition', 'mod_vocab');
        }
        return $result;
    }

    /**
     * Read the reply to the "samples" prompt.
     *
     * @param string $reply
     * @return array ['data' => [['sentence' => string, 'form' => string, 'translation' => string], ...],
     *               'errors' => [], 'warnings' => []].
     */
    public static function parse_samples(string $reply): array {
        $result = self::decode($reply);
        $json = $result['data'];
        $result['data'] = [];
        if ($result['errors']) {
            return $result;
        }
        if (is_array($json) && isset($json['samples'])) {
            $json = $json['samples']; // The list, inside an object.
        }
        $result['data'] = self::samples($json, $result);
        if (empty($result['data'])) {
            $result['errors'][] = get_string('dictionaryreplynosamples', 'mod_vocab');
        }
        return $result;
    }

    /**
     * Read the reply to the "related words" prompt.
     *
     * @param string $reply
     * @return array ['data' => [['relation' => string, 'item' => string, 'pos' => string, 'meaning' => string], ...],
     *               'errors' => [], 'warnings' => []]. The relation is one of RELATIONS.
     */
    public static function parse_related(string $reply): array {
        $result = self::decode($reply);
        $json = $result['data'];
        $result['data'] = [];
        if ($result['errors']) {
            return $result;
        }
        $seen = [];
        foreach (self::items($json) as $item) {
            $relation = core_text::strtolower(self::text($item, 'relation', $result));
            $related = [
                'relation' => $relation,
                'item' => self::text($item, 'item', $result),
                'pos' => self::pos($item, $result),
                'meaning' => self::text($item, 'meaning', $result),
            ];
            if (!in_array($relation, self::RELATIONS) || $related['item'] === '') {
                $result['warnings'][] = get_string('dictionaryreplybadrelation', 'mod_vocab', $relation . ': ' . $related['item']);
                continue;
            }
            $key = $relation . '|' . core_text::strtolower($related['item']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result['data'][] = $related;
        }
        if (empty($result['data'])) {
            $result['errors'][] = get_string('dictionaryreplynorelated', 'mod_vocab');
        }
        return $result;
    }

    /**
     * Decode the JSON in a reply, without any markdown or chatter around it.
     *
     * @param string $reply
     * @return array ['data' => mixed, 'errors' => [], 'warnings' => []].
     */
    protected static function decode(string $reply): array {
        $result = ['data' => null, 'errors' => [], 'warnings' => []];
        $text = parser::clean($reply, 'json');
        if ($text === '') {
            $result['errors'][] = get_string('dictionaryreplyempty', 'mod_vocab');
            return $result;
        }
        $result['data'] = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = get_string('dictionaryreplynotjson', 'mod_vocab', json_last_error_msg());
        }
        return $result;
    }

    /**
     * Get the items of a list that are objects, i.e. arrays.
     *
     * @param mixed $list
     * @return array[]
     */
    protected static function items($list): array {
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * Read the sample sentences of a sense. A sample without a sentence is left out.
     * The form of the word must appear in the sentence, or it is left empty.
     *
     * @param mixed $list
     * @param array $result the result, for warnings (passed by reference).
     * @return array of ['sentence' => string, 'form' => string, 'translation' => string].
     */
    protected static function samples($list, array &$result): array {
        $samples = [];
        foreach (self::items($list) as $item) {
            $sample = [
                'sentence' => self::text($item, 'sentence', $result),
                'form' => self::text($item, 'form', $result),
                'translation' => self::text($item, 'translation', $result),
            ];
            if ($sample['sentence'] === '') {
                continue;
            }
            $sentence = core_text::strtolower($sample['sentence']);
            $form = core_text::strtolower($sample['form']);
            if ($form !== '' && core_text::strpos($sentence, $form) === false) {
                $result['warnings'][] = get_string('dictionaryreplyformnotfound', 'mod_vocab', (object) $sample);
                $sample['form'] = '';
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    /**
     * Read a part of speech: one of the Universal Dependencies tags, PHRASE, or empty.
     *
     * @param array $item
     * @param array $result the result, for warnings (passed by reference).
     * @return string
     */
    protected static function pos(array $item, array &$result): string {
        $pos = core_text::strtoupper(self::text($item, 'pos', $result));
        if ($pos !== '' && !in_array($pos, dictionary::POS_TAGS)) {
            $result['warnings'][] = get_string('dictionaryreplybadpos', 'mod_vocab', $pos);
            $pos = '';
        }
        return $pos;
    }

    /**
     * Read a level, which must be one of the levels of the framework, or else empty.
     *
     * @param array $item
     * @param string[] $levels the level codes of the framework, or empty if the site has none.
     * @param array $result the result, for warnings (passed by reference).
     * @return string
     */
    protected static function level(array $item, array $levels, array &$result): string {
        $level = self::text($item, 'level', $result);
        if ($level === '' || empty($levels)) {
            return $level;
        }
        foreach ($levels as $code) {
            if (core_text::strtolower($code) === core_text::strtolower($level)) {
                return $code;
            }
        }
        $result['warnings'][] = get_string('dictionaryreplybadlevel', 'mod_vocab', $level);
        return '';
    }

    /**
     * Get the codes of the levels in a framework, e.g. A1 to C2 for CEFR.
     *
     * @param string $framework
     * @return string[]
     */
    protected static function get_levelcodes(string $framework): array {
        global $DB;
        $sql = 'SELECT levelcode FROM {vocab_levels} WHERE framework = ? ORDER BY sortorder';
        return array_values($DB->get_fieldset_sql($sql, [$framework]));
    }

    /**
     * Read a text value as plain text on one line, shortened to fit in the database if necessary.
     *
     * @param array $item
     * @param string $name
     * @param array $result the result, for warnings (passed by reference).
     * @return string
     */
    protected static function text(array $item, string $name, array &$result): string {
        if (!isset($item[$name]) || !is_scalar($item[$name])) {
            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item[$name])));
        if (core_text::strlen($text) > self::MAX_LENGTH) {
            $result['warnings'][] = get_string('dictionaryreplyshortened', 'mod_vocab', $name);
            $text = core_text::substr($text, 0, self::MAX_LENGTH);
        }
        return $text;
    }
}
