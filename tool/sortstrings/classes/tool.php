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
 * @package    vocabtool_sortstrings
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_sortstrings;

/**
 * tool
 *
 * @package    vocabtool_sortstrings
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'sortstrings';

    /**
     * Sorts language string files for selected plugins and languages.
     *
     * This method reads the selected lang files, removes extra strings, adds missing strings,
     * reorders all entries alphabetically, and optionally backs up and rewrites the files.
     * It returns a formatted HTML report summarizing changes.
     *
     * @param \moodleform $mform The submitted form containing selected plugins and options.
     * @param bool $purgecaches Whether to purge the string cache after updating files.
     * @return string HTML output summarizing the sorting process.
     */
    public function sort_strings($mform, $purgecaches=true) {
        global $CFG, $DB;
        $prefixlen = strlen($CFG->dirroot);

        // Initialize array of selected dirs.
        $selected = [];

        // Fetch the incoming form data.
        $data = $mform->get_data();

        $form = '\\vocabtool_sortstrings\\form';
        $dirs = $form::get_dirs();
        foreach ($dirs as $type => $names) {
            $fieldname = "sort{$type}s";
            if (property_exists($data, $fieldname)) {
                foreach ($names as $name => $dir) {
                    if (array_key_exists($name, $data->$fieldname)) {
                        if (intval($data->{$fieldname}[$name]) == 1) {
                            $selected["{$type}_{$name}"] = $dir;
                        }
                    }
                }
            }
        }

        if (isset($data->backuplangfiles)) {
            $backuplangfiles = intval($data->backuplangfiles);
        } else {
            $backuplangfiles = 0; // Default.
        }

        // Map chars to their escape/unescape equivalents.
        $escape = [
            '\\' => "\\\\",
            "'" => "\\'",
        ];
        $unescape = [
            '\\\\' => "\\",
            "\\'" => "'",
            '\\"' => '"',
        ];

        // Extra (=unwanted) strings that were removed.
        $extra = [];

        // Strings that were missing (and added).
        $missing = [];

        // Strings that were updated.
        $updated = [];

        // Strings that were unchanged.
        $unchanged = [];

        foreach ($selected as $pluginname => $dir) {

            list($plugintype, $name) = explode('_', $pluginname, 2);
            if ($plugintype == 'mod') {
                $langdirpath = "$dir/lang";
                $langfilename = "$name.php";
            } else {
                $langdirpath = "$dir/lang";
                $langfilename = "{$plugintype}_{$name}.php";
            }

            $langdirs = array_filter(glob("$langdirpath/*"), 'is_dir');

            // Ensure that the "en" lang always comes first.
            usort($langdirs, function($a, $b){
                $alang = substr($a, strrpos($a, '/') + 1);
                $blang = substr($b, strrpos($b, '/') + 1);
                if ($alang == 'en') {
                    return -1;
                }
                if ($blang == 'en') {
                    return 1;
                }
                // Sort other langs alphabetically.
                if ($alang < $blang) {
                    return -1;
                }
                if ($alang > $blang) {
                    return 1;
                }
                // Same language - shouldn't happen !!
                return 0;
            });

            $mainkeys = [];
            $mainstrings = [];
            foreach ($langdirs as $langdir) {
                $lang = substr($langdir, strrpos($langdir, '/') + 1);
                $langfile = "$langdir/$langfilename";
                if (is_writable($langfile)) {

                    // Get the curent content of the langfile.
                    $oldcontent = file_get_contents($langfile);

                    // Make a backup of the langfile, if requested and required.
                    if ($backuplangfiles) {
                        $langfilebackup = str_replace('.php', '.backup.php', $langfile);
                        if (! file_exists($langfilebackup)) {
                            file_put_contents($langfilebackup, $oldcontent);
                        }
                    }

                    // Locate the first occurrence of '$string'.
                    $pos = \core_text::strpos($oldcontent, '$string');
                    if ($pos == false) {
                        continue; // Shouldn't happen !!
                    }
                    // Remove all the $string definitions, leaving just the header.
                    $newcontent = \core_text::substr($oldcontent, 0, $pos);
                    $newcontent = trim($newcontent)."\n\n";

                    // Get all the strings in the langfile, and sort them.
                    $string = [];
                    include($langfile);

                    if ($lang == 'en') {
                        // As a result of the "usort" done earlier,
                        // "en" should be the first lang pack.
                        $extrakeys = [];
                        $missingkeys = [];
                        $mainstrings = $string;
                        $mainkeys = array_keys($string);
                    } else if (count($mainstrings)) {
                        $keys = array_keys($string);
                        $extrakeys = array_diff($keys, $mainkeys);
                        $missingkeys = array_diff($mainkeys, $keys);
                    } else {
                        $extrakeys = $missingkeys = []; // Shouldn't happen !!
                    }

                    // Remove any "extra" strings.
                    foreach ($extrakeys as $key) {
                        if (empty($extra[$pluginname])) {
                            $extra[$pluginname] = [];
                        }
                        if (empty($extra[$pluginname][$lang])) {
                            $extra[$pluginname][$lang] = [];
                        }
                        $extra[$pluginname][$lang][$key] = $string[$key];
                        unset($string[$key]); // Remove the extra string.
                    }

                    // Sort by string name.
                    ksort($string);

                    foreach ($string as $strname => $strvalue) {
                        $strvalue = strtr($strvalue, $unescape);
                        $strvalue = strtr($strvalue, $escape);
                        $newcontent .= '$'."string['".$strname."'] = '".$strvalue."';\n";
                    }

                    // Add any "missing" strings.
                    foreach ($missingkeys as $key) {
                        $text = $mainstrings[$key];
                        if (empty($missing[$pluginname])) {
                            $missing[$pluginname] = [];
                        }
                        if (empty($missing[$pluginname][$lang])) {
                            $missing[$pluginname][$lang] = [];
                            $newcontent .= '/*/////////////////'.";\n";
                            $newcontent .= '// Missing strings.'.";\n";
                            $newcontent .= '/////////////////*/'.";\n";
                        }
                        $missing[$pluginname][$lang][$key] = $text;
                        $text = strtr(strtr($text, $unescape), $escape);
                        $newcontent .= '$'."string['".$key."'] = '".$text."';\n";
                    }

                    if ($newcontent == $oldcontent) {
                        if (empty($unchanged[$pluginname])) {
                            $unchanged[$pluginname] = [];
                        }
                        $unchanged[$pluginname][$lang] = substr($langfile, $prefixlen);
                    } else {
                        file_put_contents($langfile, $newcontent);
                        if (empty($updated[$pluginname])) {
                            $updated[$pluginname] = [];
                        }
                        $updated[$pluginname][$lang] = substr($langfile, $prefixlen);
                    }
                    $newcontent = $oldcontent = ''; // Reclaim memory.
                }
            }
        }

        return $this->format_results(
            $extra, $missing, $updated, $unchanged, $purgecaches
        );
    }

    /**
     * Formats the results of the string sorting operation into structured HTML output.
     *
     * Displays unchanged and updated language files, and optionally purges string caches.
     * Also calls `format_special_strings()` to show details of extra and missing strings.
     *
     * @param array $extra Extra strings that were removed.
     * @param array $missing Missing strings that were added.
     * @param array $unchanged Language files that remained unchanged.
     * @param array $updated Language files that were updated.
     * @param bool $purgecaches Whether to purge the language string cache.
     *
     * @return string HTML output summarizing the results.
     */
    public function format_results($extra, $missing, $unchanged, $updated, $purgecaches) {
        // Initialize HTML output string.
        $output = '';

        // Cache attributes classes for output elements.
        $dl = ['class' => 'row'];
        $dt = ['class' => 'col-md-3'];
        $dd = ['class' => 'col-md-9'];
        $span = ['class' => 'font-weight-normal'];

        if (count($unchanged)) {
            $text = $this->get_string('unchangedlangfiles');
            $params = ['class' => 'alert alert-info'];
            $output .= \html_writer::tag('h5', $text, $params);

            $output .= \html_writer::start_tag('dl', $dl);
            foreach ($unchanged as $pluginname => $langs) {
                $strpluginname = get_string('pluginname', $pluginname);
                $langs = '['.implode(', ', array_keys($langs)).']';
                $langs = \html_writer::tag('span', $langs, $span);
                $output .= \html_writer::tag('dt', "$langs $pluginname", $dt);
                $output .= \html_writer::tag('dd', "$strpluginname", $dd);
            }
            $output .= \html_writer::end_tag('dl');
        }

        if (count($updated)) {
            $text = $this->get_string('updatedlangfiles');
            $params = ['class' => 'alert alert-info'];
            $output .= \html_writer::tag('h5', $text, $params);
            $output .= \html_writer::start_tag('dl', $dl);
            foreach ($updated as $pluginname => $langs) {
                $strpluginname = get_string('pluginname', $pluginname);
                $langcodes = '['.implode(', ', array_keys($langs)).']';
                $langcodes = \html_writer::tag('span', $langcodes, $span);
                $output .= \html_writer::tag('dt', "$langcodes $pluginname", $dt);
                $output .= \html_writer::tag('dd', "$strpluginname", $dd);
                $output .= $this->format_special_strings($pluginname, $missing, 'missingstrings', $dt, $dd);
                $output .= $this->format_special_strings($pluginname, $extra, 'extrastrings', $dt, $dd);
            }
            $output .= \html_writer::end_tag('dl');

            if ($purgecaches) {
                $text = $this->get_string('stringcachesreset');
                $params = ['class' => 'alert alert-success'];
                $output .= \html_writer::tag('p', $text, $params);
                get_string_manager()->reset_caches(true);
            }
        }

        return $output;
    }

    /**
     * Formats and displays special language strings (extra or missing) in a definition list.
     *
     * Outputs a heading for each language and an ordered list of string key-value pairs.
     *
     * @param string $pluginname The full plugin name (e.g. mod_vocab, vocabtool_x).
     * @param array $strings A nested array of strings to display, grouped by language.
     * @param string $strname The string identifier for the section heading (e.g. 'extrastrings').
     * @param array $dt Attributes to apply to <dt> elements.
     * @param array $dd Attributes to apply to <dd> elements.
     *
     * @return string HTML output representing the formatted string entries.
     */
    public function format_special_strings($pluginname, $strings, $strname, $dt, $dd) {
        $output = '';
        if (array_key_exists($pluginname, $strings)) {
            foreach ($strings[$pluginname] as $lang => $texts) {
                asort($texts);
                foreach ($texts as $key => $text) {
                    $texts[$key] = \html_writer::tag('b', $key).' => '.$text;
                }
                $a = (object)['count' => count($texts), 'lang' => $lang];
                $output .= \html_writer::tag('dt', $this->get_string($strname, $a), $dt);
                $output .= \html_writer::tag('dd', \html_writer::alist($texts, null, 'ol'), $dd);
            }
        }
        return $output;
    }
}
