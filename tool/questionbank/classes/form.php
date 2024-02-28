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

    /** @var string database value to represent creating no question subcategories */
    const SUBCAT_NONE = 'none';

    /** @var string database value to represent the creation of a "single" question subcategory */
    const SUBCAT_SINGLE = 'single';

    /** @var string database value to represent the "automatic" creation of question subcategories */
    const SUBCAT_AUTOMATIC = 'automatic';

    /**
     * definition
     *
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform = $this->_form;
        $this->set_form_id($mform);

        if (($data = data_submitted()) && confirm_sesskey()) {
            $this->generate_questions($mform, $data);
        }

        $words = $this->get_vocab()->get_wordlist_words();
        if (empty($words)) {
            $msg = $this->get_string('nowordsfound');
            $msg = $OUTPUT->notification($msg, 'warning');
            $mform->addElement('html', $msg);
            return;
        }

        // Ensure that we have access details, prompts and formats for AI assistants.
        $a = [];
        if (! $assistants = self::get_assistant_options()) {
            $a[] = $this->get_string('noassistantsfound');
        }
        if (! $prompts = $this->get_config_options('prompts', 'promptname', 'selectprompt')) {
            $a[] = $this->get_string('nopromptsfound');
        }
        if (! $formats = $this->get_config_options('formats', 'formatname', 'selectformat')) {
            $a[] = $this->get_string('nopromptsfound');
        }
        if (count($a)) {
            $a = \html_writer::alist($a);
            $msg = $this->get_string('missingaidetails', $a).
                   $this->get_string('addaidetails');
            $msg = $OUTPUT->notification($msg, 'warning', false);
            $mform->addElement('html', $msg);
            return;
        }

        // Cache line break for flex context.
        $br = \html_writer::tag('span', '', ['class' => 'w-100']);

        // Heading for the "Word list".
        $name = 'wordlist';
        $this->add_heading($mform, $name, 'mod_vocab', true);

        $name = 'selectedwords';
        $label = $this->get_string($name);

        $elements = [];

        $params = [
            'data-selectall' => get_string('selectall'),
            'data-deselectall' => get_string('deselectall'),
            'class' => 'd-none',
        ];
        $elements[] = $mform->createElement('checkbox', 'selectall', get_string('selectall'), '', $params);

        foreach ($words as $id => $word) {
            $elements[] = $mform->createElement('checkbox', $id, $word);
        }
        $mform->addGroup($elements, $name, $label, $br);
        $mform->addHelpButton($name, $name, $this->subpluginname);

        // Heading for the "AI settings".
        $name = 'aisettings';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $name = 'assistant';
        $this->add_field_select($mform, $name, $assistants, PARAM_INT);

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

        // Heading for the "Question types".
        $name = 'questiontypes';
        $this->add_heading($mform, $name, $this->subpluginname, true);

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

        // Heading for the "Question settings".
        $name = 'questionsettings';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $name = 'questionlevels';
        $options = self::get_question_levels();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'A2', 'multiple');

        $name = 'questioncount';
        $this->add_field_text($mform, $name, PARAM_INT, 5, 2);

        // Heading for the "Category settings".
        $name = 'categorysettings';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $this->add_parentcategory($mform);
        $this->add_subcategories($mform);

        // Use "generatequestions" as the label for the submit button.
        $label = $this->get_string('generatequestions');
        $this->add_action_buttons(true, $label);

        $PAGE->requires->js_call_amd('vocabtool_questionbank/form', 'init');
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @return array of AI assistants [config name => localized name]
     */
    public function get_assistant_options() {
        global $DB;
        $options = [];

        // Get all relevant contexts (activity, course, coursecat, site).
        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($ctxselect, $ctxparams) = $DB->get_in_or_equal($contexts);

        // Get all available AI assistants.
        $plugintype = 'vocabai';
        $plugins = \core_component::get_plugin_list($plugintype);
        unset($plugins['formats'], $plugins['prompts']);

        $prefix = $plugintype.'_';
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
        }

        return $options;
    }

    /**
     * Get a list of AI assistants that are available to the current user and context.
     *
     * @param string $type of config ("prompts" or "formats")
     * @param string $namefield name of setting that holds the name of this config
     * @param string $selectstring name of string to display as first option
     * @return array of AI assistants [config id => config name]
     */
    public function get_config_options($type, $namefield, $selectstring) {
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
            if (count($options) > 1) {
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
     * @param string plugintype
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
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public static function get_question_levels() {
        // ToDo: get these levels from the vocab_levelnames table.
        $plugin = 'vocabtool_questionbank';
        return [
            'A1' => get_string('cefr_a1_description', $plugin),
            'A2' => get_string('cefr_a2_description', $plugin),
            'B1' => get_string('cefr_b1_description', $plugin),
            'B2' => get_string('cefr_b2_description', $plugin),
            'C1' => get_string('cefr_c1_description', $plugin),
            'C2' => get_string('cefr_c2_description', $plugin),
        ];
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
     *
     * TODO: Finish documenting this function
     */
    public function add_parentcategory($mform) {

        $defaultid = 0;

        // Get the course context.
        $courseid = $this->get_vocab()->course->id;
        $context = \context_course::instance($courseid);

        // Get the name of the default question category for this course.
        $defaultname = $context->get_context_name(false, true);
        $defaultname = get_string('defaultfor', 'question', $defaultname);
        $defaultname = shorten_text($defaultname, 255);

        // Fetch the list of question categories in this course.
        $categories = $this->get_question_categories();

        // Extract the id of the default question category in this course.
        $defaultid = array_search($defaultname, $categories);
        if ($defaultid === false) {
            $defaultid = 0; // Shouldn't happen !!
        }

        $name = 'parentcategory';
        $label = $this->get_string($name);
        $groupname = $name.'elements';

        $elements = [
            $mform->createElement('select', $name, '', $categories),
            $mform->createElement('html', $this->link_to_managequestioncategories()),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);

        $mform->setType($groupname.'['.$name.']', PARAM_TEXT);
        $mform->setDefault($groupname.'['.$name.']', $defaultid);
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
     *
     * TODO: Finish documenting this function
     */
    public function add_subcategories($mform) {
        $name = 'subcategories';
        $label = $this->get_string($name);

        $groupname = $name.'elements';
        $cattype = $groupname.'[cattype]';
        $catname = $groupname.'[catname]';

        $options = $this->get_subcategory_types();
        $elements = [
            $mform->createElement('select', 'cattype', '', $options),
            $mform->createElement('text', 'catname', '', ['size' => 20]),
        ];
        $mform->addGroup($elements, $groupname, $label);
        $mform->addHelpButton($groupname, $name, $this->subpluginname);

        $mform->setType($cattype, PARAM_ALPHA);
        $mform->setDefault($cattype, self::SUBCAT_AUTOMATIC);

        $mform->setType($catname, PARAM_TEXT);
        $mform->setDefault($catname, '');
        $mform->disabledIf($catname, $cattype, 'neq', 'single');
    }

    /**
     * Get subcategory options
     *
     * @return array of subcategory options.
     */
    public function get_subcategory_types() {
        return [
            self::SUBCAT_NONE => get_string('none'),
            self::SUBCAT_SINGLE => $this->get_string('singlesubcategory'),
            self::SUBCAT_AUTOMATIC => $this->get_string('automaticsubcategories'),
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
                  'parentcategoryelements', 'subcategorieselements'];

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
        $name = 'assistant';
        $accessid = (empty($data->$name) ? 0 : $data->$name);

        // Get config id of an AI prompt.
        $name = 'prompt';
        $promptid = (empty($data->$name) ? 0 : $data->$name);

        // Get question format (GIFT or XML).
        $name = 'qformat';
        $qformat = (empty($data->$name) ? '' : $data->$name);

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
            if (empty($data->$name) ||
                empty({$data->$name}['enable']) ||
                empty({$data->$name}['format'])) {
                unset($qtypes[$name]);
            } else {
                // We should validate formatid.
                $formatid = $data->$name['format'];
                $qtypes[$name] = (object)[
                    'text' => $text,
                    'formatid' => $formatid,
                ];
            }
        }

        if (property_exists($data, 'questionlevels') && is_array($data->questionlevels)) {
            $qlevels = self::get_question_levels();
            foreach ($qlevels as $name => $text) {
                if (! in_array($name, $data->questionlevels)) {
                    unset($qlevels[$name]);
                }
            }
            unset($data->questionlevels);
        }

        // Get sensible value for number of tries.
        $mintries = 1;
        $maxtries = 10;
        $name = 'maxtries';
        if (isset($data->$name) && is_numeric($data->$name)) {
            $maxtries = min($maxtries, max($mintries, $data->$name));
        }

        if (empty($words) || empty($qtypes) || empty($qlevels)) {
            return;
        }

        $name = 'parentcategory';
        $groupname = $name.'elements';
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

        $groupname = 'subcategorieselements';
        if (property_exists($data, $groupname)) {

            $name = 'cattype';
            if (array_key_exists($name, $data->$groupname)) {
                $subcattype = $data->{$groupname}[$name];
                $types = $this->get_subcategory_types();
                if (! array_key_exists($subcattype, $types)) {
                    $subcattype = self::SUBCAT_AUTOMATIC;
                }
                unset($types);
            }

            $name = 'catname';
            if (array_key_exists($name, $data->$groupname)) {
                $subcatname = $data->{$groupname}[$name];
            }

            // Sanity check on subcat type and name.
            if ($subcattype == self::SUBCAT_SINGLE && $subcatname == '') {
                // Name is missing, so switch type to automatic.
                $subcattype = self::SUBCAT_AUTOMATIC;
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
            'type' => '',
            'level' => '',
            'count' => $qcount,
        ];

        // Set up one task for each level of
        // each question type for each word.
        foreach ($words as $wordid => $word) {
            $a->word = $word;

            foreach ($qtypes as $qtype => $qtypesettings) {
                $a->type = $qtypesettings->text;

                foreach ($qlevels as $qlevel => $qlevelname) {
                    $a->level = $qlevels[$qlevel];

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
                        'accessid' => $accessid,
                        'promptid' => $promptid,
                        'formatid' => $qtypesettings->formatid,
                        'status' => $tool::TASKSTATUS_NOTSET,
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
}
