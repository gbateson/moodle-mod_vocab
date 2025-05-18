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

/**
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabai_prompts
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_prompts;

/**
 * ai
 *
 * @package    vocabai_prompts
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'prompts';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'promptname', 'prompttext',
        // Default settings used by vocabtool_questionbank.
        'prompttextid', 'promptfileid', 'promptqformat',
        'promptimageid', 'promptaudioid', 'promptvideoid',
        'promptqtypes', 'promptqcount', 'promptreview',
        'promptparentcatid', 'promptsubcattype', 'promptsubcatname',
        'prompttagtypes', 'prompttagnames',
        // Sharing settings.
        'sharedfrom', 'shareduntil',
    ];

    /** @var array the names of config settings that this subplugin maintains. */
    const CONFIGSETTINGNAMES = [
        'prompttextid', 'promptfileid', 'promptqtypes',
        'promptimageid', 'promptaudioid', 'promptvideoid',
    ];

    /**
     * @var string containing type of this AI subplugin
     * (see SUBTYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $subtype = self::SUBTYPE_INPUT;

    /** @var string the name of the field used to sort config records. */
    const CONFIG_SORTFIELD = 'promptname';

    /**
     * @var bool to signify whether or not duplicate records,
     * i.e. records with the same owner and context, are allowed.
     */
    const ALLOW_DUPLICATES = true;

    /**
     * Construct a JSON string storing the array of default qtypes for this prompt.
     *
     * @param object $settings the form data containing the settings
     * @return string containing JSON encoded array mapping qtype => formatid
     */
    public function get_config_value_promptqtypes($settings) {
        $promptqtypes = [];
        $form = '\\vocabai_prompts\\form';
        $qtypes = $form::get_question_types();
        foreach ($qtypes as $name => $text) {
            if (isset($settings->$name) && is_array($settings->$name)) {
                $values = $settings->$name;
                if (array_key_exists('enable', $values) && $values['enable']) {
                    if (array_key_exists('format', $values) && $values['format']) {
                        $promptqtypes[$name] = $values['format'];
                    }
                }
                unset($settings->$name);
            }
        }
        if (empty($promptqtypes)) {
            return ''; // No qtypes specified.
        } else {
            return json_encode($promptqtypes);
        }
    }

    /**
     * Merge the default subcat settings to a single integer value.
     * If a custom name is defined, it will be set in the $settings object.
     *
     * @param object $settings the form data containing the settings
     * @return int representing the merged sub category types.
     */
    public function get_config_value_promptsubcattype($settings) {
        $form = '\\vocabai_prompts\\form';
        $types = $form::get_subcategory_types();
        return $this->get_config_value_prompttypes(
            $settings, 'subcat', 'promptsubcatname', $types
        );
    }

    /**
     * Merge the default tag settings to a single integer value.
     * If a custom name is defined, it will be set in the $settings object.
     *
     * @param object $settings the form data containing the settings
     * @return int representing the merged tag types.
     */
    public function get_config_value_prompttagtypes($settings) {
        $form = '\\vocabai_prompts\\form';
        $types = $form::get_questiontag_types();
        return $this->get_config_value_prompttypes(
            $settings, 'qtag', 'prompttagnames', $types
        );
    }

    /**
     * Merge the default settings to a single integer value.
     * If a custom name is defined, it will be set in the $settings object.
     *
     * @param object $settings the form data containing the settings.
     * @param object $name the name of the setting to be merged.
     * @param object $customnames the name of the setting that stores the custom names.
     * @param object $types array of valid values for the $setting.
     * @return int representing the merged types.
     */
    public function get_config_value_prompttypes($settings, $name, $customnames, $types) {
        $returnvalue = 0;
        if (isset($settings->$name)) {
            if (is_array($settings->$name)) {
                foreach ($settings->$name as $type => $value) {
                    if (array_key_exists($type, $types)) {
                        if ($value = intval($value)) {
                            $returnvalue |= $type;
                        }
                    } else if ($type == 'name') {
                        $value = explode(',', $value);
                        $value = array_map('trim', $value);
                        $value = array_filter($value);
                        $value = implode(', ', $value);
                        $settings->$customnames = $value;
                    }
                }
            }
            unset($settings->$name);
        }
        return $returnvalue;
    }

    /**
     * Extract the formatids from promptqtypes and prepare them export
     *
     * @param object $vocab representing the current vocab activity.
     * @param string $value a JSON-encoded string containing an object mapping $qtype to $formatid.
     * @return string a JSON-encoded string  the subplugin type and a unique field value.
     */
    public function export_config_content_promptqtypes($vocab, $value) {
        $value = json_decode($value);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($value)) {
                foreach ($value as $qtype => $formatid) {
                    $format = $this->export_config_content($vocab, $formatid);
                    $value->$qtype = $format;
                }
                return json_encode($value);
            }
        }
        return ''; // Invalid or unexpected JSON - shouldn't happen !!
    }

    /**
     * Find the configid to match the information contained in the given $value,
     * where $value is expected to contain "subplugin.fieldname: fieldvalue".
     *
     * @param object $vocab representing the current vocab activity.
     * @param string $value a value from the import XML file.
     * @return int an ID from the vocab_config table.
     */
    public function import_config_content_promptqtypes($vocab, $value) {
        $value = json_decode($value);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($value)) {
                foreach ($value as $qtype => $format) {
                    // Get the ID of a format config with the same name and
                    // in a context that is writeable for the current $USER.
                    if ($formatid = $this->import_config_content($vocab, $format)) {
                        $value->$qtype = $formatid;
                    } else {
                        unset($value->$qtype);
                    }
                }
                if ($value->count()) {
                    return json_encode($value);
                }
            }
        }
        return ''; // Invalid or unexpected JSON, or missing config - shouldn't happen !!
    }
}
