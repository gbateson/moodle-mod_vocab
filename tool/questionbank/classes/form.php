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
 * tool/questionbank/classes/form.php
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_questionbank;

/**
 * form
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var string the name of this plugin */
    public $subpluginname = 'vocabtool_questionbank';

    /** @var int database value to represent creating no new question subcategories */
    const SUBCAT_NONE = 0x00;

    /** @var string database value to represent creating a question category with a given name */
    const SUBCAT_CUSTOMNAME = 0x01;

    /** @var int database value to represent creating a question category for the current course section */
    const SUBCAT_SECTIONNAME = 0x02;

    /** @var int database value to represent creating a question category for the current vocab activity */
    const SUBCAT_ACTIVITYNAME = 0x04;

    /** @var int database value to represent creating a question category for each word */
    const SUBCAT_WORD = 0x08;

    /** @var int database value to represent creating a question category for each question type (e.g. "MC") */
    const SUBCAT_QUESTIONTYPE = 0x10;

    /** @var int database value to represent creating a question category for each vocabulary level (e.g. "A2") */
    const SUBCAT_VOCABLEVEL = 0x20;

    /**
     * definition
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $this->display_log_records($mform);

        // Make sure we have some words to generate questions for.
        $words = $this->get_vocab()->get_wordlist_words();
        if (empty($words)) {
            $msg = $this->get_string('nowordsfound');
            $this->add_message($mform, $msg);
            return;
        }

        // Cache cmid and edit icon used for links to add missing settings.
        $cmid = $this->get_vocab()->cm->id;
        $icon = $OUTPUT->pix_icon('t/edit', get_string('editsettings'));

        // Ensure that we have access details, prompts and formats for AI assistants.
        $a = [];
        $textassistants = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_TEXT);
        if (empty($textassistants)) {
            $url = new \moodle_url('/mod/vocab/ai/chatgpt/index.php', ['id' => $cmid]);
            $a[] = $this->get_string('noassistantsfound', \html_writer::link($url, $icon));
        }
        if (! $prompts = $this->get_config_options('prompts', 'promptname', 'selectprompt')) {
            $url = new \moodle_url('/mod/vocab/ai/prompts/index.php', ['id' => $cmid]);
            $a[] = $this->get_string('nopromptsfound', \html_writer::link($url, $icon));
        }
        if (! $formats = $this->get_config_options('formats', 'formatname', 'selectformat')) {
            $url = new \moodle_url('/mod/vocab/ai/formats/index.php', ['id' => $cmid]);
            $a[] = $this->get_string('noformatsfound', \html_writer::link($url, $icon));
        }
        if (count($a)) {
            $a = \html_writer::alist($a, ['class' => 'list-unstyled']);
            $msg = $this->get_string('missingaidetails', $a).
                   $this->get_string('addaidetails');
            $this->add_message($mform, $msg);
            return;
        }

        // The training files are not essential so we allow them to be empty.
        $files = $this->get_config_options('files', 'filedescription', 'selectfile', true);

        $imageassistants = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_IMAGE, true);
        if (is_array($imageassistants) && count($imageassistants)) {
            $imageassistants = [0 => get_string('none')] + $imageassistants;
        }
        $audioassistants = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_AUDIO, true);
        if (is_array($audioassistants) && count($audioassistants)) {
            $audioassistants = [0 => get_string('none')] + $audioassistants;
        }
        $videoassistants = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_VIDEO, true);
        if (is_array($videoassistants) && count($videoassistants)) {
            $videoassistants = [0 => get_string('none')] + $videoassistants;
        }

        // Cache line break for flex context.
        $br = \html_writer::tag('span', '', ['class' => 'w-100']);

        // Add a heading for the "Word list".
        $name = 'wordlist';
        $this->add_heading($mform, $name, true);

        $name = 'selectedwords';
        $label = $this->get_string($name);

        // Initialize the array of word elements.
        $elements = [];

        // Add the "Select all" line - not a real word ;-).
        $params = [
            'data-selectall' => get_string('selectall'),
            'data-deselectall' => get_string('deselectall'),
            'class' => 'd-none',
        ];
        $elements[] = $mform->createElement('checkbox', 'selectall', get_string('selectall'), '', $params);

        // Add individual word elements.
        foreach ($words as $id => $word) {
            $elements[] = $mform->createElement('checkbox', $id, $word);
        }
        $mform->addGroup($elements, $name, $label, $br);
        $mform->addHelpButton($name, $name, $this->subpluginname);

        // Add a heading for the "AI settings".
        $name = 'aisettings';
        $this->add_heading($mform, $name, true);

        $name = 'textassistant';
        $this->add_field_select($mform, $name, $textassistants, PARAM_INT);

        // Cache some field labels.
        // If we omit the enable label completely, the vertical spacing gets messed up,
        // so to compensate, we use a non-blank space. Could also use get_string('enable').
        $enablelabel = '&nbsp;';
        $promptlabel = get_string('promptname', 'vocabai_prompts');
        $formatlabel = get_string('formatname', 'vocabai_formats');

        $name = 'prompt';
        $this->add_field_select($mform, $name, $prompts, PARAM_ALPHANUM);

        $name = 'qformat';
        $options = self::get_question_formats();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'gift');

        $name = 'file';
        if (empty($files)) {
            $url = new \moodle_url('/mod/vocab/ai/files/index.php', ['id' => $cmid]);
            $msg = \html_writer::link($url, $this->get_string('clicktoaddfiles'));
            $msg = $this->get_string('nofilesfound', $msg);
            $this->add_field_static($mform, $name, $msg, 'showhelp');
        } else {
            $this->add_field_select($mform, $name, $files, PARAM_INT);
        }

        $name = 'imageassistant';
        if (empty($imageassistants)) {
            $url = new \moodle_url('/mod/vocab/ai/dalle/index.php', ['id' => $cmid]);
            $msg = \html_writer::link($url, $this->get_string('clicktoaddimage'));
            $msg = $this->get_string('noimagesfound', $msg);
            $this->add_field_static($mform, $name, $msg, 'showhelp');
        } else {
            $this->add_field_select($mform, $name, $imageassistants, PARAM_INT);
        }

        $name = 'audioassistant';
        if (empty($audioassistants)) {
            $url = new \moodle_url('/mod/vocab/ai/tts/index.php', ['id' => $cmid]);
            $msg = \html_writer::link($url, $this->get_string('clicktoaddaudio'));
            $msg = $this->get_string('noaudiofound', $msg);
            $this->add_field_static($mform, $name, $msg, 'showhelp');
        } else {
            $this->add_field_select($mform, $name, $audioassistants, PARAM_INT);
        }

        $name = 'videoassistant';
        if (empty($videoassistants)) {
            $url = new \moodle_url('/mod/vocab/ai/tts/index.php', ['id' => $cmid]);
            $msg = \html_writer::link($url, $this->get_string('clicktoaddvideo'));
            $msg = $this->get_string('novideofound', $msg);
            $this->add_field_static($mform, $name, $msg, 'showhelp');
        } else {
            $this->add_field_select($mform, $name, $videoassistants, PARAM_INT);
        }

        // Add a heading for the "Question types".
        $name = 'questiontypes';
        $this->add_heading($mform, $name, true);

        $qtypes = self::get_question_types();
        foreach ($qtypes as $qtype => $label) {

            // Add the checkbox, prompt menu and format menu for this question type.
            $elements = [];
            $elements[] = $mform->createElement('checkbox', 'enable', $enablelabel);
            $elements[] = $mform->createElement('select', 'format', $formatlabel, $formats);
            $mform->addGroup($elements, $qtype, $label);
            $mform->addHelpButton($qtype, 'pluginname', "qtype_$qtype");

            // Set the default format to be the first of any that contain
            // the question type in their name.
            if ($defaults = preg_grep('/'.preg_quote($label, '/').'/', $formats)) {
                $mform->setDefault($qtype.'[format]', key($defaults));
            }

            // Disable the format menu until the question type becomes checked.
            $mform->hideIf($qtype.'[format]', $qtype.'[enable]', 'notchecked');
        }

        // Add a heading for the "Question settings".
        $name = 'questionsettings';
        $this->add_heading($mform, $name, true);

        $name = 'questionlevels';
        $options = self::get_question_levels(true);
        if (is_scalar($options[key($options)])) {
            $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'A2', 'multiple');
        } else {
            $default = $options[key($options)];
            $default = array_keys($default);
            $default = next($default); // Get the 2nd key.
            $this->add_field_selectgroups($mform, $name, $options, PARAM_ALPHANUM, $default, 'multiple');
        }

        $name = 'questioncount';
        $this->add_field_text($mform, $name, PARAM_INT, 5, 2);

        $name = 'questionreview';
        $options = [get_string('no'), get_string('yes')];
        $this->add_field_select($mform, $name, $options, PARAM_INT, 1);

        // Add a heading for the "Category settings".
        $name = 'categorysettings';
        $this->add_heading($mform, $name, true);

        // Add the parent questions category.
        $this->add_parentcategory($mform, 'parentcat');

        // Add the question subcategories.
        $default = self::SUBCAT_ACTIVITYNAME;
        $default |= self::SUBCAT_WORD;
        $default |= self::SUBCAT_QUESTIONTYPE;
        $default |= self::SUBCAT_VOCABLEVEL;
        $this->add_subcategories($mform, 'subcat', $default);

        // Use "Generate questions" as the label for the submit button.
        $label = $this->get_string('generatequestions');
        $this->add_action_buttons(true, $label);

        $PAGE->requires->js_call_amd('vocabtool_questionbank/form', 'init');
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param object $mform the Moodle form
     * @param string $msg the message to be displayed
     * @param string $type of message to be displayed (optional, default='warning')
     * @param bool $closebutton should a "close" button be added to the message (optional, default=false)
     * @param string $closeafter the name of previous section, if any (optional, default='logrecords')
     * @return void, but update $mform settings and fields
     */
    public function add_message($mform, $msg, $type='warning', $closebutton=false, $closeafter='logrecords') {
        global $OUTPUT;
        if ($mform->elementExists($closeafter)) {
            $name = 'closebeforeme';
            $mform->addElement('static', $name, '');
            $mform->closeHeaderBefore($name);
        }
        $msg = $OUTPUT->notification($msg, $type, $closebutton);
        $mform->addElement('html', $msg);
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param string $type
     * @param string $subtype
     * @return array of AI assistants [config name => path]
     */
    public function get_subplugins($type, $subtype) {
        $plugins = \core_component::get_plugin_list($type);
        foreach ($plugins as $name => $path) {
            $ai = "\\vocabai_$name\\ai";
            if ($ai::create()->subtype == $subtype) {
                continue;
            }
            unset($plugins[$name]);
        }
        return $plugins;
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param string $subtype on of the \mod_vocab\aibase::SUBTYPE_XXX constants.
     * @param boolean $optional TRUE if this field is optional, otherwise FALSE
     * @return array of AI assistants [config name => localized name]
     */
    public function get_assistant_options($subtype, $optional=false) {
        global $DB;
        $options = [];

        // Get all relevant contexts (activity, course, coursecat, site).
        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($ctxselect, $ctxparams) = $DB->get_in_or_equal($contexts);

        // Get all available AI text assistants.
        $type = 'vocabai';
        $plugins = $this->get_subplugins($type, $subtype);

        if (empty($plugins)) {
            return null;
        }

        $prefix = $type.'_';
        $prefixlen = strlen($prefix);

        // Prefix all the plugin names with the $prefix string
        // and get create the sql conditions.
        $plugins = array_keys($plugins);

        $plugins = substr_replace($plugins, $prefix, 0, 0);
        list($select, $params) = $DB->get_in_or_equal($plugins);

        $select = "contextid $ctxselect AND subplugin $select";
        $params = array_merge($ctxparams, $params);

        if ($options = $DB->get_records_select_menu('vocab_config', $select, $params, 'id', 'id, subplugin')) {
            $options = array_unique($options); // Remove duplicates.
            foreach ($options as $id => $subplugin) {
                $name = substr($subplugin, $prefixlen);
                $options[$id] = get_string($name, $subplugin);
            }
            $options = array_filter($options);
            asort($options);
            if ($optional) {
                $options = ([0 => get_string('none')] + $options);
            }
        }
        return $options;
    }

    /**
     * Get a list of AI config options that are available to the current user and context.
     *
     * @param string $type of config ("prompts" or "formats")
     * @param string $namefield name of setting that holds the name of this config
     * @param string $selectstring name of string to display as first option
     * @param boolean $optional TRUE if this field is optional, otherwise FALSE.
     * @return array of AI config options [config id => config name]
     */
    public function get_config_options($type, $namefield, $selectstring, $optional=false) {
        global $DB;
        $options = [];

        // Get all relevant contexts (activity, course, coursecat, site).
        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($where, $params) = $DB->get_in_or_equal($contexts);

        // Although the "get_records_sql_menu" method is clean and quick,
        // it may be slightly risky because if the settings get messed up,
        // there's a chance that configid + $namefield may not be unique.
        $select = 'vcs.configid, vcs.value';
        $from = '{vocab_config_settings} vcs '.
                'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id';
        $where = "vc.contextid $where AND vc.subplugin = ? AND vcs.name = ?";
        $params = array_merge($params, ["vocabai_$type", $namefield]);

        $sql = "SELECT $select FROM $from WHERE $where";
        if ($options = $DB->get_records_sql_menu($sql, $params)) {
            asort($options);
            if ($optional) {
                $options = ([0 => get_string('none')] + $options);
            } else if (count($options) > 1) {
                $selectstring = $this->get_string($selectstring);
                $options = ([0 => $selectstring] + $options);
            }
        }
        return $options;
    }

    /**
     * get_question_formats
     *
     * @return array $formats of question formats for which we can generate questions.
     */
    public static function get_question_formats() {
        // ToDo: Could include aiken, hotpot, missingword, multianswer.
        return self::get_question_plugins('qformat', ['gift', 'xml']);
    }

    /**
     * get_question_types
     *
     * @return array $types of question types for which we can generate questions.
     */
    public static function get_question_types() {
        // ToDo: Could include ordering, essayautograde, speakautograde and sassessment.
        $include = ['match', 'multianswer', 'multichoice', 'shortanswer', 'truefalse'];
        $order = ['multichoice', 'truefalse', 'match', 'shortanswer', 'multianswer'];
        return self::get_question_plugins('qtype', $include, $order);
    }

    /**
     * Get question plugins ("qtype" or "qformat")
     *
     * @param string $plugintype
     * @param array $include (optional, default=null)
     * @param array $order (optional, default=[])
     * @return array $plugins of question formats for which we can generate questions.
     */
    public static function get_question_plugins($plugintype, $include=null, $order=[]) {

        // Get the full list of plugins of the required type.
        $plugins = \core_component::get_plugin_list($plugintype);

        // Remove items that are not in the $include array.
        foreach (array_keys($plugins) as $name) {
            if ($include === null || in_array($name, $include)) {
                $plugins[$name] = get_string('pluginname', $plugintype.'_'.$name);
            } else {
                unset($plugins[$name]);
            }
        }

        // Sort items alphabetically (maintain key association).
        asort($plugins);

        // Ensure first few items are the common ones.
        $order = array_flip($order);
        foreach (array_keys($order) as $name) {
            if (array_key_exists($name, $plugins)) {
                $order[$name] = $plugins[$name];
            } else {
                unset($order[$name]);
            }
        }
        $plugins = $order + $plugins;

        return $plugins;
    }

    /**
     * get_question_type_text
     *
     * @param string $qtype a question type e.g. "multichoice", "truefalse"
     * @return string human readable text version of the given $qtype
     */
    public static function get_question_type_text($qtype) {
        $qtypes = self::get_question_types();
        if (array_key_exists($qtype, $qtypes)) {
            return $qtypes[$qtype];
        } else {
            // Illegal value - shouldn't happen !!
            return $qtype;
        }
    }

    /**
     * get_question_levels
     *
     * @param bool $sortbyprefix (optional, default=FALSE)
     *                If TRUE, return a two-dimensional array [prefix => [code => name]].
     *                If FALSE return a one-dimensional array [code => name].
     * @return array of vocabulary levels.
     */
    public static function get_question_levels($sortbyprefix=false) {
        global $DB;

        // The $levels array is the return value for this function.
        $levels = [];

        // Define the languages we are interested in.
        $langs = [current_language()];
        if ($pos = strpos($langs[0], '_')) {
            $langs[] = substr($langs[0], 0, $pos);
        }
        if (in_array('en', $langs) == false) {
            $langs[] = 'en';
        }

        $select = 'n.*, lvl.levelcode, lng.langcode';
        $from = '{vocab_levelnames} n '.
                'JOIN {vocab_levels} lvl ON n.levelid = lvl.id  '.
                'JOIN {vocab_langs} lng  ON n.langid = lng.id';
        list($where, $params) = $DB->get_in_or_equal($langs);
        $where = "lng.langcode $where";

        $sql = "SELECT $select FROM $from WHERE $where";
        if ($names = $DB->get_records_sql($sql, $params)) {

            // Sort by langcode: child, parent, "en".
            uasort($names, function ($a, $b) {

                $acode = $a->langcode;
                $bcode = $b->langcode;

                // Put parent language last.
                $aparent = (strpos($acode, '_') == false);
                $bparent = (strpos($bcode, '_') == false);
                if ($aparent && $bparent == false) {
                    return 1; // Put $a after $b.
                }
                if ($aparent == false && $bparent) {
                    return -1; // Put $a before $b.
                }

                // Put English language last.
                $aenglish = ($acode == 'en');
                $benglish = ($bcode == 'en');
                if ($aenglish && $benglish == false) {
                    return 1; // Put $a after $b.
                }
                if ($aenglish == false && $benglish) {
                    return -1; // Put $a before $b.
                }

                // Otherwise, do "natural" sort by levelcode
                // so that "1200L" comes after "300L".
                return strnatcmp($a->levelcode, $b->levelcode);
            });

            // Extract $levels from the the $names array.
            // Note that we don't overwrite items.
            foreach ($names as $id => $level) {

                $code = $level->levelcode;
                $name = $level->levelname;

                if ($sortbyprefix && preg_match('/^\w+/iu', $name, $prefix)) {
                    $prefix = $prefix[0]; // E.g. "CEFR" or "Lexile".
                    if (empty($levels[$prefix])) {
                        $levels[$prefix] = [];
                    }
                    if (empty($levels[$prefix][$code])) {
                        $levels[$prefix][$code] = $name;
                    }
                } else {
                    if (empty($levels[$code])) {
                        $levels[$code] = $name;
                    }
                }
            }
        }

        // If there is only one prefix, remove the prefix hierarchy.
        if ($sortbyprefix) {
            if (count($levels) == 1) {
                $prefix = key($levels);
                if (is_array($levels[$prefix])) {
                    $levels = $levels[$prefix];
                }
            }
        }

        if (count($levels)) {
            return $levels;
        } else {
            // Default value an array of CEFR levels.
            $plugin = 'vocabtool_questionbank';
            $cefr = [
                'A1' => get_string('cefr_a1_description', $plugin),
                'A2' => get_string('cefr_a2_description', $plugin),
                'B1' => get_string('cefr_b1_description', $plugin),
                'B2' => get_string('cefr_b2_description', $plugin),
                'C1' => get_string('cefr_c1_description', $plugin),
                'C2' => get_string('cefr_c2_description', $plugin),
            ];
        }
    }

    /**
     * get_question_level_text
     *
     * @param string $qlevel a question level e.g. "multichoice", "truefalse"
     * @return string human readable text version of the given $qlevel
     */
    public static function get_question_level_text($qlevel) {
        $qlevels = self::get_question_levels();
        if (array_key_exists($qlevel, $qlevels)) {
            return $qlevels[$qlevel];
        } else {
            // Illegal value - shouldn't happen !!
            return $qlevel;
        }
    }

    /**
     * add_parentcategory
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param integer $defaultid (optional, default=0)
     *
     * TODO: Finish documenting this function
     */
    public function add_parentcategory($mform, $name, $defaultid=0) {

        $strname = 'parentcategory';

        // Get the course context.
        $courseid = $this->get_vocab()->course->id;
        $context = \context_course::instance($courseid);

        // Fetch the list of question categories in this course.
        $categories = $this->get_question_categories();

        // Get the name of the default question category for this course.
        if ($defaultid == 0) {
            $defaultname = $context->get_context_name(false, true);
            $defaultname = get_string('defaultfor', 'question', $defaultname);
            $defaultname = shorten_text($defaultname, 255);

            // Extract the id of the default question category in this course.
            $defaultid = array_search($defaultname, $categories);
            if ($defaultid === false) {
                $defaultid = 0; // Shouldn't happen !!
            }
        }

        $label = $this->get_string($strname);

        $elements = [
            $mform->createElement('select', 'id', '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories()),
        ];
        $mform->addGroup($elements, $name, $label);
        $this->add_help_button($mform, $name, $strname);

        $elementname = $name.'[id]';
        $mform->setType($elementname, PARAM_INT);
        $mform->setDefault($elementname, $defaultid);
    }

    /**
     * link_to_managequestioncategories
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function link_to_managequestioncategories() {
        $link = '/question/bank/managecategories/category.php';
        $params = ['courseid' => $this->get_vocab()->course->id];
        $link = new \moodle_url($link, $params);

        $text = $this->get_string('managequestioncategories');
        $params = ['onclick' => "this.target='VOCAB'"];
        $link = \html_writer::link($link, $text, $params);

        $params = ['class' => 'w-100 pl-1'];
        return \html_writer::tag('small', $link, $params);
    }

    /**
     * get_question_categories
     *
     * @uses $CFG
     * @uses $DB
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_question_categories() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/questionlib.php');

        $courseid = $this->get_vocab()->course->id;
        $coursecontext = \context_course::instance($courseid);
        $coursecategory = question_get_top_category($coursecontext->id, true); // Create if necessary.

        $categories = question_categorylist($coursecategory->id);
        list($select, $params) = $DB->get_in_or_equal($categories);
        if ($categories = $DB->get_records_select_menu('question_categories', "id $select", $params, 'sortorder', 'id, name')) {

            if ($coursecategory->name == 'top') {
                $name = $coursecontext->get_context_name(false, false, true);
                $name = get_string('topfor', 'question', $name);
                if (array_key_exists($coursecategory->id, $categories)) {
                    $categories[$coursecategory->id] = $name;
                }
            }
            return $categories;
        } else {
            return [];
        }
    }

    /**
     * add_subcategories
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $name either "subcat" or "log[subcat]"
     * @param integer $defaulttypes
     * @param string $defaultname the default custom name (optional, default='')
     * @return void, but will add elements to $mform
     */
    public function add_subcategories($mform, $name, $defaulttypes, $defaultname='') {

        // The name of the form field containing the "Custom name" string.
        $catname = $name.'[name]';

        $strname = 'subcategories';
        $label = $this->get_string($strname);

        // Cache line break element.
        $linebreak = \html_writer::tag('span', '', ['class' => 'w-100']);

        $options = $this->get_subcategory_types();
        foreach ($options as $value => $text) {
            $elements[] = $mform->createElement('checkbox', $value, $text);
            if ($value == self::SUBCAT_CUSTOMNAME) {
                $elements[] = $mform->createElement('text', 'name', '', ['size' => 20]);
            }
            $elements[] = $mform->createElement('html', $linebreak);
        }
        $mform->addGroup($elements, $name, $label);
        $this->add_help_button($mform, $name, $strname);
        $elementnone = $name.'['.self::SUBCAT_NONE.']';
        foreach ($options as $value => $text) {
            $elementname = $name.'['.$value.']';
            $mform->setType($elementname, PARAM_INT);
            if ($value == self::SUBCAT_CUSTOMNAME) {
                $mform->setType($catname, PARAM_TEXT);
                $mform->setDefault($catname, $defaultname);
                $mform->disabledIf($catname, $elementname, 'notchecked');
            }
            if ($defaulttypes & $value) {
                $mform->setDefault($elementname, 1);
            }
            if ($value > 0) {
                $mform->disabledIf($elementname, $elementnone, 'checked');
            }
        }
    }

    /**
     * Get subcategory types
     *
     * @return array of subcategory types.
     */
    public function get_subcategory_types() {
        return [
            self::SUBCAT_NONE => get_string('none'),
            self::SUBCAT_CUSTOMNAME => $this->get_string('customname'),
            self::SUBCAT_SECTIONNAME => $this->get_string('sectionname'),
            self::SUBCAT_ACTIVITYNAME => $this->get_string('activityname'),
            self::SUBCAT_WORD => $this->get_string('word'),
            self::SUBCAT_QUESTIONTYPE => $this->get_string('questiontype'),
            self::SUBCAT_VOCABLEVEL => $this->get_string('vocablevel'),
        ];
    }

    /**
     * Get status types
     *
     * @return array of status types.
     */
    public function get_status_types() {
        $tool = $this->get_subplugin();
        return [
            $tool::TASKSTATUS_NOTSET => $this->get_string('taskstatus_notset'),
            $tool::TASKSTATUS_QUEUED => $this->get_string('taskstatus_queued'),
            $tool::TASKSTATUS_CHECKING_PARAMS => $this->get_string('taskstatus_checkingparams'),
            $tool::TASKSTATUS_FETCHING_RESULTS => $this->get_string('taskstatus_fetchingresults'),
            $tool::TASKSTATUS_AWAITING_REVIEW => $this->get_string('taskstatus_awaitingreview'),
            $tool::TASKSTATUS_AWAITING_IMPORT => $this->get_string('taskstatus_awaitingimport'),
            $tool::TASKSTATUS_IMPORTING_RESULTS => $this->get_string('taskstatus_importingresults'),
            $tool::TASKSTATUS_COMPLETED => $this->get_string('taskstatus_completed'),
            $tool::TASKSTATUS_CANCELLED => $this->get_string('taskstatus_cancelled'),
            $tool::TASKSTATUS_FAILED => $this->get_string('taskstatus_failed'),
        ];
    }

    /**
     * validation
     *
     * @uses $USER
     * @param stdClass $data submitted from the form
     * @param array $files
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $names = ['selectedwords', 'questiontypes',
                  'questionlevels', 'questioncount',
                  'parentcat', 'subcat'];

        foreach ($names as $name) {
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('empty'.$name);
            }
        }

        return $errors;
    }

    /**
     * generate_questions
     *
     * @uses $DB
     * @uses $OUTPUT
     * @uses $USER
     * @param moodleform $mform representing the Moodle form
     * @param object $data submitted from the Moodle form
     * @return void, but may add records to the "task_adhoc" table in the Moodle database.
     *
     * TODO: Finish documenting this function
     */
    public function generate_questions($mform, $data) {
        global $DB, $OUTPUT, $USER;

        // Intialize arrays for messages to report success or failure
        // when setting up adhoc tasks to generate questions.
        $success = [];
        $failure = [];

        $words = false;
        $qtypes = false;
        $qlevels = false;
        $qcount = $data->questioncount;

        $parentcatid = 0;
        $parentcatname = '';

        $subcattype = 0;
        $subcatname = '';

        // Cache the vocabid.
        $vocabid = $this->get_vocab()->id;

        // Get config id of an AI access.
        $name = 'textassistant';
        $textid = (empty($data->$name) ? 0 : $data->$name);

        // Get config id of an AI prompt.
        $name = 'prompt';
        $promptid = (empty($data->$name) ? 0 : $data->$name);

        $name = 'imageassistant';
        $imageid = (empty($data->$name) ? 0 : $data->$name);

        $name = 'audioassistant';
        $audioid = (empty($data->$name) ? 0 : $data->$name);

        $name = 'videoassistant';
        $videoid = (empty($data->$name) ? 0 : $data->$name);

        // Get question format (GIFT or XML).
        $name = 'qformat';
        $qformat = (empty($data->$name) ? '' : $data->$name);

        $name = 'file';
        $fileid = (empty($data->$name) ? 0 : $data->$name);

        if (property_exists($data, 'selectedwords')) {
            unset($data->selectedwords['selectall']);

            $select = 'vwi.wordid, vw.word';
            $from = '{vocab_word_instances} vwi, {vocab_words} vw';
            list($where, $params) = $DB->get_in_or_equal(array_keys($data->selectedwords));
            $where = 'vwi.vocabid = ? AND vwi.wordid = vw.id AND vw.id '.$where;
            $params = array_merge([$vocabid], $params);
            $order = 'vwi.sortorder, vw.word';

            $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
            $words = $DB->get_records_sql_menu($sql, $params);

            unset($data->selectedwords);
        }

        $qtypes = self::get_question_types();
        foreach ($qtypes as $name => $text) {
            if (isset($data->$name) && is_array($data->$name)) {
                $values = $data->$name;
                if (empty($values['enable']) || empty($values['format'])) {
                    $values = null;
                } else {
                    $values = (object)[
                        'text' => $text,
                        'formatid' => $values['format'],
                    ];
                }
            } else {
                $values = null;
            }
            if ($values === null) {
                unset($qtypes[$name]);
            } else {
                $qtypes[$name] = $values;
            }
        }

        if (property_exists($data, 'questionlevels')) {
            if (is_array($data->questionlevels)) {
                $qlevels = self::get_question_levels();
                foreach ($qlevels as $name => $text) {
                    if (! in_array($name, $data->questionlevels)) {
                        unset($qlevels[$name]);
                    }
                }
            }
            unset($data->questionlevels);
        }

        if (empty($words) || empty($qtypes) || empty($qlevels)) {
            return;
        }

        // Get sensible value for number of tries.
        $mintries = 1;
        $maxtries = 5;
        $name = 'maxtries';
        if (isset($data->$name) && is_numeric($data->$name)) {
            $maxtries = min($maxtries, max($mintries, $data->$name));
        }

        // Get teacher review flag.
        $name = 'questionreview';
        $review = (empty($data->$name) ? 0 : $data->$name);

        // Extract id of parent question category.
        $name = 'id';
        $groupname = 'parentcat';
        if (property_exists($data, $groupname)) {
            if (array_key_exists($name, $data->$groupname)) {
                $parentcatid = $data->{$groupname}[$name];
                $categories = $this->get_question_categories();
                if (! array_key_exists($parentcatid, $categories)) {
                    $parentcatid = key($categories);
                }
                unset($categories);
            }
            unset($data->$groupname);
        }

        // Extract type and name of question subcategory.
        $groupname = 'subcat';
        if (property_exists($data, $groupname)) {

            $types = $this->get_subcategory_types();
            foreach ($types as $type => $text) {
                if (! empty($data->{$groupname}[$type])) {
                    $subcattype |= $type;
                }
            }

            if ($subcattype & self::SUBCAT_CUSTOMNAME) {
                if (array_key_exists('name', $data->$groupname)) {
                    $subcatname = trim($data->{$groupname}['name']);
                }
                if ($subcatname == '') {
                    $subcatname = get_string('listsep', 'langconfig');
                    $subcatname = implode($subcatname, $words);
                    $subcatname = $this->get_string('defaultcustomname', $subcatname);
                    $subcatname = shorten_text($subcatname); // Shorten to 30 chars.
                }
            } else if ($subcatname) {
                // Name given but not needed, so remove it.
                $subcatname = '';
            }

            unset($data->$groupname);
        }

        // Cache reference to this questionbank tool object.
        // This allows easy access to the log functions.
        $tool = $this->get_subplugin();

        // Initialize arguments for "get_string()" used to report
        // the success or failure of setting up the adhoc task.
        $a = (object)[
            'word' => '',
            'qtype' => '',
            'qlevel' => '',
            'qcount' => $qcount,
        ];

        // Set up one task for each level of
        // each question type for each word.
        foreach ($words as $wordid => $word) {
            $a->word = $word;

            foreach ($qtypes as $qtype => $qtypesettings) {
                $a->qtype = $qtypesettings->text;

                foreach ($qlevels as $qlevel => $qlevelname) {
                    $a->qlevel = $qlevels[$qlevel];
                    $logid = $tool::insert_log([
                        'userid' => $USER->id,
                        'vocabid' => $vocabid,
                        'wordid' => $wordid,
                        'qtype' => $qtype,
                        'qlevel' => $qlevel,
                        'qcount' => $qcount,
                        'qformat' => $qformat,
                        'maxtries' => $maxtries,
                        'parentcatid' => $parentcatid,
                        'subcattype' => $subcattype,
                        'subcatname' => $subcatname,
                        'textid' => $textid,
                        'promptid' => $promptid,
                        'formatid' => $qtypesettings->formatid,
                        'fileid' => $fileid,
                        'imageid' => $imageid,
                        'audioid' => $audioid,
                        'videoid' => $videoid,
                        'status' => $tool::TASKSTATUS_NOTSET,
                        'review' => $review,
                    ]);

                    // Create the adhoc task object, see
                    // "/lib/classes/task/task_base.php".
                    $task = new \vocabtool_questionbank\task\questions();
                    $task->set_userid($USER->id);
                    $task->set_custom_data(['logid' => $logid]);

                    // If successful, the "queue_adhoc_task()" method
                    // returns a record id from the "task_adhoc" table.
                    if ($taskid = \core\task\manager::queue_adhoc_task($task)) {
                        $tool::update_log($logid, [
                            'taskid' => $taskid,
                            'status' => $tool::TASKSTATUS_QUEUED,
                        ]);
                        $success[] = $this->get_string('taskgeneratequestions', $a);
                    } else {
                        $failure[] = $this->get_string('taskgeneratequestions', $a);
                    }
                }
            }
        }

        // Report back on the success or failure of setting up the adhoc tasks.
        if (count($success)) {
            $success = \html_writer::alist($success);
            $success = $this->get_string('scheduletaskssuccess', $success);
            $strsuccess = get_string('success').get_string('labelsep', 'langconfig');
            $strsuccess = \html_writer::tag('b', $strsuccess, ['class' => 'text-success']);
            $success = $OUTPUT->notification($strsuccess.$success, 'success', false);
            $mform->addElement('html', $success);
        }
        if (count($failure)) {
            $failure = \html_writer::alist($failure);
            $failure = $this->get_string('scheduletasksfailure', $failure);
            $strfailure = get_string('error').get_string('labelsep', 'langconfig');
            $strfailure = \html_writer::tag('b', $strfailure, ['class' => 'text-danger']);
            $failure = $OUTPUT->notification($strfailure.$failure, 'warning', false);
            $mform->addElement('html', $failure);
        }
    }

    /**
     * display_log_records
     *
     * @param moodleform $mform representing the Moodle form
     * @return array $logs of records vocabtool_questionbank_log table.
     */
    public function display_log_records($mform) {
        global $OUTPUT, $USER;

        $logids = [];
        $logaction = '';

        $logtable = '';
        $logmessage = '';

        // Get array of new log values. Use depth=2 to get array.
        if ($values = self::get_optional_param('log', null, PARAM_TEXT, 2)) {

            $tool = $this->get_subplugin();
            $siteadmin = is_siteadmin();

            $logid = (int)$values['id'];
            $log = $tool::get_log($logid);

            if (empty($values['savechanges'])) {
                // Some other button was pressed
                // probably the cancel button.
                $allowupdate = false;
            } else {
                $allowupdate = $siteadmin;
                if ($log->userid == $USER->id) {
                    if ($log->vocabid == $tool->vocab->id) {
                        $allowupdate = true;
                    }
                }
            }

            // Extract the id of the parent question category.
            $parentcatid = 0;
            $name = 'parentcat';
            if (isset($values[$name])) {
                if (is_array($values[$name])) {
                    if (array_key_exists('id', $values[$name])) {
                        $parentcatid = $values[$name]['id'];
                    }
                }
                unset($values[$name]);
            }
            $values['parentcatid'] = $parentcatid;

            // Extract the hierarchy of question subcategories.
            $subcattype = 0;
            $subcatname = '';
            $name = 'subcat';
            if (isset($values[$name])) {
                if (is_array($values[$name])) {
                    // Remove empty values.
                    $values[$name] = array_filter($values[$name]);
                    // Extract non-empty types.
                    foreach ($values[$name] as $type => $value) {
                        if (is_numeric($type)) {
                            $subcattype |= $type;
                        } else if ($type == 'name') {
                            $subcatname = $value;
                        }
                    }
                }
                unset($values[$name]);
            }
            $values['subcattype'] = $subcattype;
            $values['subcatname'] = $subcatname;

            if ($allowupdate) {

                // Define the types of the log fields that can be updated.
                // Fields that are not in this array cannot be updated.
                // The includes the folowing fields:
                // id, taskid, userid, vocabid, wordid, questionids
                // any new fields that are added to the log table.
                $types = [
                    'qtype' => PARAM_TEXT,
                    'qlevel' => PARAM_TEXT,
                    'qcount' => PARAM_INT,
                    'qformat' => PARAM_TEXT,
                    'textid' => PARAM_INT,
                    'promptid' => PARAM_INT,
                    'formatid' => PARAM_INT,
                    'fileid' => PARAM_INT,
                    'imageid' => PARAM_INT,
                    'audioid' => PARAM_INT,
                    'videoid' => PARAM_INT,
                    'parentcatid' => PARAM_INT,
                    'subcattype' => PARAM_INT,
                    'subcatname' => PARAM_TEXT,
                    'maxtries' => PARAM_INT,
                    'tries' => PARAM_INT,
                    'status' => PARAM_INT,
                    'review' => PARAM_INT,
                    'error' => PARAM_TEXT,
                    'prompt' => PARAM_TEXT,
                    'results' => PARAM_TEXT,
                ];

                // The $updated array holds the names
                // and values of fields that have changed.
                $updated = [];

                // We only allow fields that exist in the
                // record that has just come from the $DB.
                foreach ($log as $name => $value) {
                    if (array_key_exists($name, $values)) {
                        if (array_key_exists($name, $types)) {
                            $value = clean_param($values[$name], $types[$name]);
                            if ($value != $log->$name) {
                                $updated[$name] = $value;
                            }
                        }
                    }
                }

                if (count($updated)) {
                    $log = $tool::update_log($logid, $updated);
                    $a = (object)['count' => 1, 'ids' => ''];
                    if ($siteadmin) {
                        $a->ids = " (log id: $logid)";
                    }
                    $logmessage = $tool->get_string('editlogresult', $a);
                }
            }

            // Remove all the log fields so that the moodleform does not get confused later.
            unset($_POST['log']);
        }

        if ($logaction = self::get_optional_param('logactionelements', '', PARAM_ALPHA)) {
            if (empty($logaction['logaction'])) {
                $logaction = ''; // Shouldn't happen !!
            } else {
                $logaction = $logaction['logaction'];
            }
        }
        if ($logaction == '') {
            $logaction = self::get_optional_param('logaction', '', PARAM_ALPHA);
        }
        if ($logaction) {
            if ($logids = self::get_optional_param('logid', 0, PARAM_INT)) {
                $logids = [$logids => $logids];
            } else {
                $logids = self::get_optional_param('logids', [], PARAM_INT);
            }
            if (count($logids) && confirm_sesskey()) {
                $logmessage = $this->process_log_records($mform, $logaction, $logids);
            }
        }

        // Clean and process incoming form data.
        if (($data = data_submitted()) && confirm_sesskey()) {
            if (isset($data->submitbutton) && $data->submitbutton) {
                $this->generate_questions($mform, $data);
            }
        }

        // Get table of current log records.
        list($logcount, $logtable) = $this->get_log_records_table($logaction, $logids);

        if ($logtable || $logmessage) {

            // If there is a log message, we expand the logrecords section.
            // If there are any log records, we append how many there are.
            $this->add_heading(
                $mform, 'logrecords',
                (strlen($logmessage) == 0 ? false : true),
                ($logcount == 0 ? '' : " ($logcount)")
            );

            // Display log messages about any log action that was just taken.
            if ($logmessage) {
                $logmessage = $OUTPUT->notification($logmessage, 'info', false);
                $mform->addElement('html', $logmessage);
            }

            // Display table of adhoc tasks to generate questions.
            if ($logtable) {
                $mform->addElement('html', $logtable);

                // Add menu for actions on multiple selected logs.
                $elements = [];
                $options = [
                    '' => $this->get_string('withselected'),
                    'redotask' => $this->get_string('redotask'),
                    'resumetask' => $this->get_string('resumetask'),
                    'deletelog' => $this->get_string('deletelog'),
                ];
                $elements[] = $mform->createElement('select', 'logaction', '', $options);
                $elements[] = $mform->createElement('submit', 'logbutton', get_string('go'));

                $mform->addGroup($elements, 'logactionelements', get_string('action'), '');
            }
        }
    }

    /**
     * process_log_records
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $logaction
     * @param array $logids
     * @return void, but may update vocabtool_questionbank_log table in DB.
     */
    public function process_log_records($mform, $logaction, $logids) {
        global $DB, $USER;

        // Cache reference to this questionbank tool object.
        // This allows easy access to the log functions.
        $tool = $this->get_subplugin();

        // Cache the vocabid.
        $vocabid = $tool->vocab->id;

        // Cache the siteadmin flag.
        $siteadmin = is_siteadmin();

        $ids = [];
        foreach ($logids as $logid => $value) {

            // Ensure the the checkbox was actually checked.
            if (empty($value)) {
                continue;
            }

            // Ensure the logid is valid.
            if (! $log = $tool->get_log($logid)) {
                continue;
            }

            // Ensure that this user is allowed to access this log in this context.
            if ($log->userid == $USER->id && $log->vocabid == $vocabid) {
                $skip = false; // Valid userid and vocabid.
            } else if ($siteadmin) {
                $skip = false; // Site admin always has access.
            } else {
                $skip = true; // Invalid userid and/or vocabid.
            }
            if ($skip) {
                continue;
            }

            // Fetch the adhoc task (usually it's already been deleted)
            // and ensure the main values match what we expect.
            if ($task = $DB->get_record('task_adhoc', ['id' => $log->taskid])) {
                if ($task->classname == '\\vocabtool_questionbank\\task\\questions') {
                    if ($task->component == 'vocabtool_questionbank') {
                        if ($task->customdata == '{"logid":'.$logid.'}') { // JSON.
                            $task = \core\task\manager::adhoc_task_from_record($task);
                        }
                    }
                }
                // Unset the task if the fields didn't match.
                if (get_class($task) == 'stdClass') {
                    $task = false;
                }
            }

            // Now we are ready to perform the requested action.
            switch ($logaction) {

                case 'editlog':
                    $this->add_heading($mform, 'selectedlogrecord', true);

                    // Add the log id as a hidden field.
                    $mform->addElement('hidden', 'log[id]', $log->id);
                    $mform->setType('log[id]', PARAM_INT);

                    $name = 'taskid';
                    $a = ['strname' => 'adhoctaskid', 'showhelp' => true];
                    $this->add_field_static($mform, "log[$name]", $log->taskid, $a);

                    $name = 'userid';
                    $a = ['strname' => 'taskowner', 'showhelp' => true];
                    $log->$name = $DB->get_record('user', ['id' => $log->userid]);
                    $log->$name = fullname($log->$name);
                    $this->add_field_static($mform, "log[$name]", $log->$name, $a);

                    $name = 'wordid';
                    $a = ['strname' => 'word', 'showhelp' => true];
                    $log->$name = $DB->get_field('vocab_words', 'word', ['id' => $log->wordid]);
                    $log->$name = \html_writer::tag('b', $log->$name);
                    $this->add_field_static($mform, "log[$name]", $log->$name, $a);

                    $name = 'qtype';
                    $a = ['strname' => 'questiontype'];
                    $options = self::get_question_types();
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_ALPHANUM, $log->$name, $a);

                    $name = 'qlevel';
                    $a = ['strname' => 'questionlevel'];
                    $options = self::get_question_levels(true);
                    $this->add_field_selectgroups($mform, "log[$name]", $options, PARAM_ALPHANUM, $log->$name, $a);

                    $name = 'qcount';
                    $a = ['strname' => 'questioncount', 'size' => 2];
                    $this->add_field_text($mform, "log[$name]", PARAM_INT, $log->$name, $a);

                    $name = 'qformat';
                    $a = ['strname' => $name];
                    $options = self::get_question_formats();
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_ALPHANUM, $log->$name, $a);

                    $name = 'textid';
                    $a = ['strname' => 'textassistant'];
                    $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_TEXT);
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'promptid';
                    $a = ['strname' => 'prompt'];
                    $options = $this->get_config_options('prompts', 'promptname', 'selectprompt');
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'formatid';
                    $a = ['strname' => 'qformat'];
                    $options = $this->get_config_options('formats', 'formatname', 'selectformat');
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'fileid';
                    $a = ['strname' => 'file'];
                    $options = $this->get_config_options('files', 'filedescription', 'selectfile', true);
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'imageid';
                    $a = ['strname' => 'imageassistant'];
                    $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_IMAGE, true);
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'audioid';
                    $a = ['strname' => 'audioassistant'];
                    $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_AUDIO, true);
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    $name = 'videoid';
                    $a = ['strname' => 'videoassistant'];
                    $options = self::get_assistant_options(\mod_vocab\aibase::SUBTYPE_VIDEO, true);
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_INT, $log->$name, $a);

                    // The parent category group includes 'log[parentcat][id]'.
                    $this->add_parentcategory($mform, "log[parentcat]", $log->parentcatid);

                    // The subcategories group includes 'log[subcat][type]' and 'log[subcat][name]'.
                    $this->add_subcategories($mform, "log[subcat]", $log->subcattype, $log->subcatname);

                    $name = 'maxtries';
                    $a = ['strname' => $name, 'size' => 2];
                    $this->add_field_text($mform, "log[$name]", PARAM_INT, $log->$name, $a);

                    $name = 'tries';
                    $a = ['strname' => $name, 'size' => 2];
                    $this->add_field_text($mform, "log[$name]", PARAM_INT, $log->$name, $a);

                    $name = 'status';
                    $a = ['strname' => 'taskstatus'];
                    $options = $this->get_status_types();
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_ALPHA, $log->$name, $a);

                    $name = 'review';
                    $a = ['strname' => 'questionreview'];
                    $options = [get_string('no'), get_string('yes')];
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_ALPHA, $log->$name, $a);

                    $name = 'error';
                    $a = ['strname' => 'taskerror', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_TEXT, $log->$name, $a);

                    $name = 'prompt';
                    $a = ['strname' => 'prompttext', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_TEXT, $log->$name, $a);

                    $name = 'results';
                    $a = ['strname' => 'resultstext', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_TEXT, $log->$name, $a);

                    $name = 'questionids';
                    $a = ['strname' => 'moodlequestions'];
                    if (empty($log->$name)) {
                        $log->$name = get_string('noquestions', 'quiz');
                    } else {
                        $log->$name = $this->format_questionids($log->$name);
                    }
                    $this->add_field_static($mform, "log[$name]", $log->$name, $a);

                    $name = 'savechanges';
                    $mform->addGroup([
                        $mform->createElement('submit', "log[$name]", get_string($name)),
                        $mform->createElement('cancel'),
                    ], $name, '', [' '], false);

                    break;

                case 'redotask':
                case 'resumetask':
                    if ($task) {
                        // The "reschedule" method has no return value,
                        // so we just try it and hope that it works.
                        \core\task\manager::reschedule_or_queue_adhoc_task($task);
                        $taskid = $task->get_id();
                    } else {
                        // Create a new task to generate questions.
                        $task = new \vocabtool_questionbank\task\questions();
                        $task->set_userid($log->userid);
                        $task->set_custom_data(['logid' => $logid]);
                        // The "queue" method returns an "id", if successful.
                        $taskid = \core\task\manager::queue_adhoc_task($task);
                    }
                    if ($taskid) {
                        if ($logaction == 'redotask') {
                            $tool::update_log($logid, [
                                'taskid' => $taskid,
                                'tries' => 0,
                                'error' => '',
                                'prompt' => '',
                                'results' => '',
                                'status' => $tool::TASKSTATUS_QUEUED,
                            ]);
                        } else {
                            // Resume task.
                            $tool::update_log($logid, [
                                'taskid' => $taskid,
                                'status' => $tool::TASKSTATUS_AWAITING_IMPORT,
                            ]);
                        }
                        $ids[] = $logid;
                    }
                    break;

                case 'deletelog':
                    if ($task) {
                        if ($task->get_lock()) {
                            // If the task has a lock, we mark it as "complete".
                            // This will delete the task and release any locks.
                            \core\task\manager::adhoc_task_complete($task);
                        } else {
                            // There's no "lock", so we just delete the DB task record.
                            $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
                        }
                        $task = null;
                    }
                    $tool::delete_logs(['id' => $logid]);
                    $ids[] = $logid;
                    break;

                default:
                    return "Unknown log action: $logaction";
            }
        }

        if (empty($ids)) {
            return ''; // Shouldn't happen !!
        }

        // Format results for display.
        $count = count($ids);
        if ($count == 1) {
            $strname = $logaction.'result';
        } else {
            $strname = $logaction.'results';
        }
        $a = (object)['count' => $count, 'ids' => ''];
        if ($siteadmin && ($ids = implode(', ', $ids))) {
            if ($count == 1) {
                $a->ids = " (log id: $ids)";
            } else {
                $a->ids = " (log ids: $ids)";
            }
        }
        return $tool->get_string($strname, $a);
    }

    /**
     * format_questionids
     *
     * @param string $questionids comma-separated list of question ids.
     * @return string containing a list of links to previw pages of the questions.
     */
    public function format_questionids($questionids) {
        global $OUTPUT;

        if (empty($questionids)) {
            return '';
        }

        // Cache the courseid and edit/preview icons.
        $courseid = $this->get_vocab()->course->id;
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('editsettings'));
        $previewicon = $OUTPUT->pix_icon('t/preview', get_string('preview'));

        $ids = explode(',', $questionids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids);
        foreach ($ids as $i => $id) {

            $url = '/question/bank/editquestion/question.php';
            $url = new \moodle_url($url, ['courseid' => $courseid, 'id' => $id]);
            $params = ['onclick' => "this.target = 'vocabtool_questionbank';"];
            $ids[$i] = ' '.\html_writer::link($url, $id, $params);

            $url = '/question/bank/previewquestion/preview.php';
            $url = new \moodle_url($url, ['id' => $id]);
            $params = ['onclick' => "this.target = 'vocabtool_questionbank';"];
            $ids[$i] .= ' '.\html_writer::link($url, $previewicon, $params);
        }
        return implode(', ', $ids);
    }


    /**
     * get_log_records_table
     *
     * @param string $logaction
     * @param array $logids of selected log records.
     * @return array [$logcount, $html] HTML table of log records from vocabtool_questionbank_log table.
     */
    public function get_log_records_table($logaction, $logids) {
        global $DB, $OUTPUT, $PAGE;

        // Specify a short date/time format.
        $datefmt = get_string('strftimedatetimeshort', 'langconfig');
        // The "strftimerecent" is slightly more readable,
        // but includes "." after abbreviated months and days.

        // Cache the admin flag.
        $siteadmin = is_siteadmin();

        // Initialize the log counter.
        $logcount = 0;

        // Initialize the HTML table.
        $table = new \html_table();
        $table->id = 'questionbanklog_table';
        $table->head = [];
        $table->data = [];
        $table->align = [];

        // Cache reference to this questionbank tool object.
        // This allows easy access to the log functions.
        $tool = $this->get_subplugin();

        // Cache the DB table names.
        $configtable = 'vocab_config';
        $settingstable = 'vocab_config_settings';
        $categoriestable = 'question_categories';

        // Cache the action strings and icons.
        $actions = [
            'editlog' => 't/edit',
            'redotask' => 't/reload',
            'resumetask' => 't/play',
            'deletelog' => 't/delete',
        ];

        $cssclass = (object)[
            'logactions' => 'd-inline-block border rounded mx-1 my-0 p-1 bg-light logactions',
            'logaction' => 'd-inline-block border-light mx-0 my-0 px-1 py-0 text-nowrap logaction',
        ];

        // Cache status strings.
        $statusnames = $this->get_status_types();

        // Initialize arrays that cache commonly used values in the main loop.
        $users = [];
        $qformats = [];
        $textnames = [];
        $promptnames = [];
        $formatnames = [];
        $filedescriptions = [];
        $imagenames = [];
        $audionames = [];
        $videonames = [];
        $categorynames = [];
        $subcattypes = $this->get_subcategory_types();

        // Fetch all logs pertaining to the current vocab activity.
        if ($logs = $tool::get_logs($tool->vocab->id)) {

            foreach ($logs as $log) {

                if (empty($users[$log->userid])) {
                    $users[$log->userid] = $DB->get_record('user', ['id' => $log->userid]);
                }

                if (empty($textnames[$log->textid])) {
                    $params = ['id' => $log->textid];
                    if ($name = $DB->get_field($configtable, 'subplugin', $params)) {
                        // The $name value is something like "vocabai_chatgpt".
                        // We want to the get_string('chatgpt', 'vocabai_chatgpt').
                        $name = get_string(substr($name, strpos($name, '_') + 1), $name);
                    } else {
                        $a = ['configid' => $log->textid, 'type' => 'subplugin'];
                        $name = $this->get_string('missingconfigname', $a);
                    }
                    $textnames[$log->textid] = $name;
                }

                if (empty($promptnames[$log->promptid])) {
                    $params = ['configid' => $log->promptid, 'name' => 'promptname'];
                    if (! $name = $DB->get_field($settingstable, 'value', $params)) {
                        $a = ['configid' => $log->promptid, 'type' => 'promptname'];
                        $name = $this->get_string('missingconfigname', $a);
                    }
                    $promptnames[$log->promptid] = $name;
                }

                if (empty($formatnames[$log->formatid])) {
                    $params = ['configid' => $log->formatid, 'name' => 'formatname'];
                    if (! $name = $DB->get_field($settingstable, 'value', $params)) {
                        $a = ['configid' => $log->formatid, 'type' => 'formatname'];
                        $name = $this->get_string('missingconfigname', $a);
                    }
                    $formatnames[$log->formatid] = $name;
                }

                if (empty($filedescriptions[$log->fileid])) {
                    if (empty($log->fileid)) {
                        $name = ''; // Optional file, not specified.
                    } else {
                        $params = ['configid' => $log->fileid, 'name' => 'filedescription'];
                        if (! $name = $DB->get_field($settingstable, 'value', $params)) {
                            $a = ['configid' => $log->fileid, 'type' => 'filedescription'];
                            $name = $this->get_string('missingconfigname', $a);
                        }
                    }
                    $filedescriptions[$log->fileid] = $name;
                }

                if (empty($imagenames[$log->imageid])) {
                    if (empty($log->imageid)) {
                        $name = ''; // Optional image AI assistant, not specified.
                    } else {
                        $params = ['id' => $log->imageid];
                        if ($name = $DB->get_field($configtable, 'subplugin', $params)) {
                            // The $name value is something like "vocabai_chatgpt".
                            // We want to the get_string('chatgpt', 'vocabai_chatgpt').
                            $name = get_string(substr($name, strpos($name, '_') + 1), $name);
                        } else {
                            $a = ['configid' => $log->imageid, 'type' => 'subplugin'];
                            $name = $this->get_string('missingconfigname', $a);
                        }
                    }
                    $imagenames[$log->imageid] = $name;
                }

                if (empty($audionames[$log->audioid])) {
                    if (empty($log->audioid)) {
                        $name = ''; // Optional image AI assistant, not specified.
                    } else {
                        $params = ['id' => $log->audioid];
                        if ($name = $DB->get_field($configtable, 'subplugin', $params)) {
                            // The $name value is something like "vocabai_chatgpt".
                            // We want to the get_string('chatgpt', 'vocabai_chatgpt').
                            $name = get_string(substr($name, strpos($name, '_') + 1), $name);
                        } else {
                            $a = ['configid' => $log->audioid, 'type' => 'subplugin'];
                            $name = $this->get_string('missingconfigname', $a);
                        }
                    }
                    $audionames[$log->audioid] = $name;
                }

                if (empty($videonames[$log->videoid])) {
                    if (empty($log->videoid)) {
                        $name = ''; // Optional image AI assistant, not specified.
                    } else {
                        $params = ['id' => $log->videoid];
                        if ($name = $DB->get_field($configtable, 'subplugin', $params)) {
                            // The $name value is something like "vocabai_chatgpt".
                            // We want to the get_string('chatgpt', 'vocabai_chatgpt').
                            $name = get_string(substr($name, strpos($name, '_') + 1), $name);
                        } else {
                            $a = ['configid' => $log->videoid, 'type' => 'subplugin'];
                            $name = $this->get_string('missingconfigname', $a);
                        }
                    }
                    $videonames[$log->videoid] = $name;
                }

                if (empty($categorynames[$log->parentcatid])) {
                    $params = ['id' => $log->parentcatid];
                    if (! $name = $DB->get_field($categoriestable, 'name', $params)) {
                        $name = $this->get_string('invalidquestioncategory', $log->parentcatid);
                    }
                    $categorynames[$log->parentcatid] = $name;
                }

                if (empty($qformats[$log->qformat])) {
                    if (empty($log->qformat)) {
                        $name = ''; // Shouldn't happen !!
                    } else {
                        $name = get_string('pluginname', 'qformat_'.$log->qformat);
                    }
                    $qformats[$log->qformat] = $name;
                }

                if ($log->subcattype && array_key_exists($log->subcattype, $subcattypes)) {
                    $log->subcattype = $subcattypes[$log->subcattype];
                }

                if (array_key_exists($log->status, $statusnames)) {
                    $log->status = $statusnames[$log->status];
                }

                if (empty($log->review)) {
                    $log->review = get_string('no');
                } else {
                    $log->review = get_string('yes');
                }

                $names = ['error', 'prompt', 'results'];
                foreach ($names as $name) {
                    if ($log->$name && strlen($log->$name) > 10) {
                        $log->$name = substr($log->$name, 0, 10);
                    }
                }

                $name = 'questionids';
                $log->$name = $this->format_questionids($log->$name);

                if ($log->timecreated) {
                    $log->timecreated = userdate($log->timecreated, $datefmt);
                }
                if ($log->timemodified) {
                    $log->timemodified = userdate($log->timemodified, $datefmt);
                }
                if ($log->nextruntime) {
                    $log->nextruntime = get_string('nextruntime', 'tool_task').
                                        get_string('labelsep', 'langconfig').
                                        userdate($log->nextruntime, $datefmt);
                } else {
                    $log->nextruntime = get_string('completed');
                    if ($log->timemodified) {
                        $log->nextruntime .= get_string('labelsep', 'langconfig');
                        $log->nextruntime .= $log->timemodified;
                    }
                    if ($siteadmin) {
                        $msg = get_string('adhoctaskid', 'tool_task', $log->taskid);
                        $log->nextruntime .= \html_writer::tag('small', " ($msg)", ['class' => 'text-nowrap']);
                    }
                }

                // Define checkbox to select this log record.
                $name = 'logids';
                $checked = array_key_exists($log->id, $logids);
                $checkbox = \html_writer::checkbox($name.'['.$log->id.']', $log->id, $checked);

                // Define actions allowed on this log record.
                $logactions = '';
                foreach ($actions as $action => $icon) {
                    $text = $tool->get_string($action);
                    $icon = $OUTPUT->pix_icon($icon, $text);
                    $url = $PAGE->url;
                    $url->params([
                        'logid' => $log->id,
                        'logaction' => $action,
                        'sesskey' => sesskey(),
                    ]);
                    $text = \html_writer::tag('small', $text);
                    $logaction = \html_writer::link($url, $icon.' '.$text);
                    $logactions .= \html_writer::tag('div', $logaction, ['class' => $cssclass->logaction]);
                }
                $logactions = \html_writer::tag('div', $logactions, ['class' => $cssclass->logactions]);

                // Add a row of values for the current log record.
                $table->data[] = [
                    $checkbox,
                    $logactions,
                    $log->nextruntime,
                    fullname($users[$log->userid]),
                    \html_writer::tag('b', $log->word),
                    self::get_question_type_text($log->qtype),
                    $log->qcount,
                    $log->qlevel,
                    $qformats[$log->qformat],
                    $textnames[$log->textid],
                    $promptnames[$log->promptid],
                    $formatnames[$log->formatid],
                    $filedescriptions[$log->fileid],
                    $imagenames[$log->imageid],
                    $audionames[$log->audioid],
                    $videonames[$log->videoid],
                    $categorynames[$log->parentcatid],
                    $log->subcattype,
                    $log->subcatname,
                    $log->maxtries,
                    $log->tries,
                    $log->status,
                    $log->review,
                    $log->error,
                    $log->prompt,
                    $log->results,
                    $log->questionids,
                    $log->timecreated,
                    $log->timemodified,
                ];

                // Update the log count.
                $logcount++;
            }
        }

        if (empty($table->data)) {
            return [0, ''];
        }

        // Define the "Select all" checkbox for the log records.
        // It is initially hidden and then unhidden by Javascript.
        if (count($table->data) == 1) {
            $checkbox = '';
        } else {
            $checked = array_key_exists(0, $logids);
            $checkbox = \html_writer::checkbox('logids[selectall]', 0, $checked, '', ['class' => 'd-none']);
            $checkbox = \html_writer::tag('div', $checkbox);
        }

        // Specify centrally aligned columns.
        $table->align = [
            0 => 'center', // Select.
            6 => 'center', // Question count.
            7 => 'center', // Question level.
            15 => 'center', // Maxtries.
            16 => 'center', // Tries.
            18 => 'center', // Review.
        ];

        // Specify nowrap columns.
        $table->wrap = [
            19 => 'nowrap', // Error.
            20 => 'nowrap', // Prompt.
            21 => 'nowrap', // Results.
        ];

        // Define strings for column headings.
        $table->head = [
            get_string('select').$checkbox,
            get_string('actions'),
            $this->get_string('adhoctaskid'),
            $this->get_string('taskowner'),
            $this->get_string('word'),
            $this->get_string('questiontype'),
            $this->get_string('questioncount'),
            $this->get_string('questionlevel'),
            $this->get_string('qformat'),
            $this->get_string('textassistant'),
            $this->get_string('promptname'),
            $this->get_string('formatname'),
            $this->get_string('filedescription'),
            $this->get_string('imageassistant'),
            $this->get_string('audioassistant'),
            $this->get_string('videoassistant'),
            $this->get_string('parentcategory'),
            $this->get_string('subcattype'),
            $this->get_string('subcatname'),
            $this->get_string('maxtries'),
            $this->get_string('tries'),
            get_string('status'),
            $this->get_string('questionreview'),
            get_string('error'),
            $this->get_string('prompttext'),
            $this->get_string('resultstext'),
            $this->get_string('moodlequestions'),
            $this->get_string('timecreated'),
            $this->get_string('timemodified'),
        ];
        return [$logcount, \html_writer::table($table)];
    }
}
