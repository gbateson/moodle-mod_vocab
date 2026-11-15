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
 * mod/vocab/util/sortstrings/classes/form.php
 *
 * @package    vocabutil_sortstrings
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabutil_sortstrings;

/**
 * Main form for vocabutil_sortstrings.
 *
 * @package    vocabutil_sortstrings
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\utilform {
    /**
     * Defines the form elements for the sortstrings tool.
     *
     * Adds checkboxes for available plugin types with language files,
     * and an option to back up the language files before sorting.
     */
    public function definition() {
        $mform = $this->_form;
        $this->set_form_id($mform);

        // Cache the label separator, usually a colon ":".
        $labelsep = get_string('labelsep', 'langconfig');

        $dirs = self::get_dirs();
        foreach ($dirs as $type => $names) {
            // Map "mod" to "sortmods",
            // and "vocabai" to "sortvocabais",
            // and "vocabtool" to "sortvocabtools".
            // and "vocabutil" to "sortvocabutils".
            $fieldname = "sort{$type}s";
            foreach ($names as $name => $dir) {
                $pluginname = \html_writer::tag('b', $name . $labelsep) . ' ';
                $pluginname .= get_string('pluginname', "{$type}_{$name}");
                $names[$name] = $pluginname;
            }
            $this->add_checkboxes(
                $mform,
                $fieldname,
                $fieldname,
                \html_writer::tag('b', $type),
                PARAM_ALPHANUM,
                $names
            );
        }

        $name = 'backuplangfiles';
        $options = [get_string('no'), get_string('yes')];
        $this->add_field_select($mform, $name, $options, PARAM_INT);

        // Use "Redo task" as the label for the submit button.
        $label = $this->get_string('sortstrings');
        $this->add_action_buttons(true, $label);
    }

    /**
     * Returns an array of plugin types and their corresponding plugin directories
     * that contain English language files.
     *
     * @return array Array in the format: [plugintype => [pluginname => directory path]]
     */
    public static function get_dirs() {
        global $CFG;
        $dirs = [];

        $types = self::get_subplugins($CFG->dirroot . '/mod/vocab');
        $types = array_merge(['mod'], array_keys($types));

        foreach ($types as $type) {
            if ($type == 'mod') {
                $name = 'vocab';
                $dir = $CFG->dirroot . "/$type/$name";
                $langfile = "$dir/lang/en/$name.php";
                if (file_exists($langfile)) {
                    $dirs[$type] = [$name => $dir];
                }
            } else {
                $plugintype = "$type";
                $plugins = \core_component::get_plugin_list($plugintype);
                foreach ($plugins as $name => $dir) {
                    $langfile = "$dir/lang/en/{$plugintype}_{$name}.php";
                    if (file_exists($langfile)) {
                        if (empty($dirs[$type])) {
                            $dirs[$type] = [];
                        }
                        $dirs[$type][$name] = $dir;
                    }
                }
            }
        }

        return $dirs;
    }

    /**
     * Retrieves subplugin types for the Vocabulary activity module by parsing
     * the subplugins.json or subplugins.php file.
     *
     * @param string $plugindir The full path to the mod/vocab plugin directory
     * @return array Associative array of subplugin types and their relative paths
     */
    public static function get_subplugins($plugindir) {

        if (file_exists("$plugindir/db/subplugins.json")) {
            $subplugins = file_get_contents($plugindir . '/db/subplugins.json');

            $subplugins = json_decode($subplugins);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return []; // Invalid JSON - shouldn't happen !!
            }

            // We expect to find the "plugintypes" property.
            if (isset($subplugins->plugintypes)) {
                return (array)$subplugins->plugintypes;
            }

            // We can manage with the "subplugintypes" property.
            if ($subplugins->subplugintypes) {
                $subplugins = (array)$subplugins->subplugintypes;
                foreach ($subplugins as $type => $subtype) {
                    $subplugins[$type] = 'mod/vocab/' . $subtype;
                }
                return $subplugins;
            }

            // Otherwise we keep trying - against the odds!
        }

        if (file_exists($plugindir . '/db/subplugins.php')) {
            $subplugins = [];
            include("$plugindir/db/subplugins.php");
        }

        // Subplugins info could not be located - shouldn't happen !!
        return [];
    }
}
