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

namespace vocabtool_dictionary\external;

use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use vocabtool_dictionary\tool;

/**
 * Shared code for the web service functions of the dictionary tool, which show and change
 * the dictionary entries of the words in a Vocabulary activity's word list.
 *
 * This uses the external API of Moodle 4.2 and later; on earlier versions the
 * functions are not defined (see db/services.php).
 *
 * @package    vocabtool_dictionary
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class entry_api extends external_api {
    /**
     * Check that the user can manage an activity, and set up its context.
     *
     * @param int $vocabid
     * @return \context_module
     */
    protected static function validate_vocab(int $vocabid): \context_module {
        $cm = get_coursemodule_from_instance('vocab', $vocabid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vocab:manage', $context);
        return $context;
    }

    /**
     * Get the entries of some words of an activity, leaving out words that are not in its word list.
     *
     * @param int $vocabid
     * @param \context $context
     * @param int[] $wordids
     * @return array the entries, for entries_structure().
     */
    protected static function get_activity_entries(int $vocabid, \context $context, array $wordids): array {
        $vocab = \mod_vocab\activity::create(null, null, $vocabid);
        $wordids = array_intersect(array_map('intval', $wordids), array_keys($vocab->get_wordlist_words()));
        return array_values(tool::export_entries($context, $wordids));
    }

    /**
     * Describe a list of entries, as tool::export_entries() makes them.
     *
     * @return external_multiple_structure
     */
    public static function entries_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'wordid' => new external_value(PARAM_INT, 'The id of the word'),
            'word' => new external_value(PARAM_TEXT, 'The word'),
            'lemmaid' => new external_value(PARAM_INT, 'The id of its lemma, or 0'),
            'lemma' => new external_value(PARAM_TEXT, 'Its lemma, or ""'),
            'showlemma' => new external_value(PARAM_BOOL, 'Whether the lemma is not the word itself'),
            'hassenses' => new external_value(PARAM_BOOL, 'Whether the lemma has any senses'),
            'senses' => new external_multiple_structure(new external_single_structure(self::item_fields() + [
                'pos' => new external_value(PARAM_ALPHA, 'The part of speech, e.g. NOUN'),
                'posname' => new external_value(PARAM_TEXT, 'The name of the part of speech'),
                'sensenum' => new external_value(PARAM_INT, 'The number of the sense, for its part of speech'),
                'definitions' => self::items_structure('Definitions, in the language of the word'),
                'glosses' => self::items_structure('Glosses, in other languages'),
                'samples' => new external_multiple_structure(new external_single_structure(self::item_fields() + [
                    'form' => new external_value(PARAM_TEXT, 'The form of the word in the sample, or ""'),
                    'translations' => self::items_structure('Translations of the sample'),
                    'hastranslations' => new external_value(PARAM_BOOL, 'Whether there are translations'),
                ]), 'Sample sentences'),
                'hasdefinitions' => new external_value(PARAM_BOOL, 'Whether there are definitions'),
                'hasglosses' => new external_value(PARAM_BOOL, 'Whether there are glosses'),
                'hassamples' => new external_value(PARAM_BOOL, 'Whether there are samples'),
            ]), 'The senses of the lemma'),
        ]), 'Dictionary entries');
    }

    /**
     * Describe a list of simple dictionary items.
     *
     * @param string $description
     * @return external_multiple_structure
     */
    protected static function items_structure(string $description): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure(self::item_fields()), $description);
    }

    /**
     * Describe the fields that every dictionary item has.
     *
     * @return array field name => external_value or structure
     */
    protected static function item_fields(): array {
        return [
            'id' => new external_value(PARAM_INT, 'The id of the item'),
            'itemtype' => new external_value(PARAM_ALPHA, 'The kind of item: sense, definition or sample'),
            'text' => new external_value(PARAM_TEXT, 'The text of the item, e.g. a meaning or a sentence'),
            'lang' => new external_value(PARAM_TEXT, 'The language code, or ""'),
            'langname' => new external_value(PARAM_TEXT, 'The name of the language'),
            'level' => new external_value(PARAM_TEXT, 'The level code, e.g. A2, or ""'),
            'status' => new external_value(PARAM_INT, 'The review status: 0 draft, 1 approved, 2 rejected'),
            'statusname' => new external_value(PARAM_TEXT, 'The name of the status'),
            'isdraft' => new external_value(PARAM_BOOL, 'Whether the item is a draft'),
            'isapproved' => new external_value(PARAM_BOOL, 'Whether the item is approved'),
            'isrejected' => new external_value(PARAM_BOOL, 'Whether the item is rejected'),
            'isai' => new external_value(PARAM_BOOL, 'Whether the item was made by AI'),
            'sourceid' => new external_value(PARAM_INT, 'The id of the source, or 0'),
            'sourcename' => new external_value(PARAM_TEXT, 'The name of the source, or ""'),
            'hasactions' => new external_value(PARAM_BOOL, 'Whether the user can change the status'),
            'actions' => new external_multiple_structure(new external_single_structure([
                'itemtype' => new external_value(PARAM_ALPHA, 'The kind of item'),
                'itemid' => new external_value(PARAM_INT, 'The id of the item'),
                'status' => new external_value(PARAM_INT, 'The new status'),
                'action' => new external_value(PARAM_ALPHA, 'approve, reject or restore'),
                'label' => new external_value(PARAM_TEXT, 'The label of the button'),
                'isapprove' => new external_value(PARAM_BOOL, 'Whether the action approves the item'),
                'isreject' => new external_value(PARAM_BOOL, 'Whether the action rejects the item'),
            ]), 'The changes of status that the user can make'),
        ];
    }
}
