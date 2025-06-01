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

    /** @var int database value to represent creating a question category for each prompt tail (after ":") */
    const SUBCAT_PROMPTHEAD = 0x80;

    /** @var int database value to represent creating a question category for each prompt tail (after ":") */
    const SUBCAT_PROMPTTAIL = 0x40;

    /** @var int database value to represent adding no new question question tags */
    const QTAG_NONE = 0x00;

    /** @var string database value to represent adding an "AI" tag */
    const QTAG_AI = 0x01;

    /** @var int database value to represent adding a question tag derivecd from the prompt name */
    const QTAG_PROMPTHEAD = 0x02;

    /** @var int database value to represent adding a question tag derivecd from the prompt tail */
    const QTAG_PROMPTTAIL = 0x80;

    /** @var int database value to represent adding a question tag for the media types, if any (e.g. image, audio) */
    const QTAG_MEDIATYPE = 0x04;

    /** @var string database value to represent adding an "AI" tag */
    const QTAG_WORD = 0x08;

    /** @var int database value to represent adding a question tag for the question type (e.g. MC) */
    const QTAG_QUESTIONTYPE = 0x10;

    /** @var int database value to represent adding a question tag for the vocab level (e.g. A2) */
    const QTAG_VOCABLEVEL = 0x20;

    /** @var int database value to represent adding one or more custom question tags */
    const QTAG_CUSTOMTAGS = 0x40;

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

        $this->add_field_textassistant($mform, $textassistants);

        $name = 'prompt';
        $this->add_field_select($mform, $name, $prompts, PARAM_ALPHANUM);

        $name = 'qformat';
        $options = self::get_question_formats();
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUM, 'gift');

        $this->add_field_tuningfile($mform, $files, $cmid);
        $this->add_field_imageassistant($mform, $imageassistants);
        $this->add_field_audioassistant($mform, $audioassistants);
        $this->add_field_videoassistant($mform, $videoassistants);

        // Add a heading for the "Question types".
        $name = 'questiontypes';
        $this->add_heading($mform, $name, true);

        // Cache some field labels.
        // If we omit the enable label completely, the vertical spacing gets messed up,
        // so to compensate, we use a non-blank space. Could also use get_string('enable').
        $enablelabel = '&nbsp;';
        $promptlabel = get_string('promptname', 'vocabai_prompts');
        $formatlabel = get_string('formatname', 'vocabai_formats');

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
        $default |= self::SUBCAT_PROMPTHEAD;
        $default |= self::SUBCAT_PROMPTTAIL;
        $this->add_subcategories($mform, 'subcat', $default);

        $name = 'questiontags';
        $this->add_heading($mform, $name, true);

        $default = self::QTAG_AI;
        $default |= self::QTAG_WORD;
        $default |= self::QTAG_QUESTIONTYPE;
        $default |= self::QTAG_VOCABLEVEL;
        $default |= self::QTAG_MEDIATYPE;
        $this->add_questiontags($mform, 'qtag', $default);

        // Use "Generate questions" as the label for the submit button.
        $label = $this->get_string('generatequestions');
        $this->add_action_buttons(true, $label);

        // Setup paramters to pass to Javscript AMD.
        $namefields = [
            'prompttextid' => 'textassistant',
            'promptfileid' => 'file',
            'promptqformat' => 'qformat',
            'promptimageid' => 'imageassistant',
            'promptaudioid' => 'audioassistant',
            'promptvideoid' => 'videoassistant',
            'promptqtypes' => 'qtypes',
            'promptqcount' => 'questioncount',
            'promptreview' => 'questionreview',
            'promptparentcatid' => 'parentcat[id]',
            'promptsubcattype' => 'subcattypes',
            'promptsubcatname' => 'subcatname',
            'prompttagtypes' => 'tagtypes',
            'prompttagnames' => 'tagnames',
        ];

        $options = $this->get_config_options('prompts', $namefields);
        foreach ($options as $configid => $defaults) {
            if (empty($defaults->qtypes)) {
                continue;
            }
            $qtypes = json_decode($defaults->qtypes);
            if (json_last_error() == JSON_ERROR_NONE) {
                $defaults->qtypes = $qtypes;
                $options[$configid] = $defaults;
            }
        }

        $prompt = $mform->getElement('prompt');
        foreach ($prompt->_options as $i => $option) {
            $configid = $option['attr']['value'];
            if (array_key_exists($configid, $options)) {
                $defaults = json_encode($options[$configid]);
                $option['attr']['data-defaults'] = $defaults;
                $prompt->_options[$i] = $option;
            }
        }

        $PAGE->requires->js_call_amd('vocabtool_questionbank/form', 'init');
    }

    /**
     * Add a message at the top of the form.
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
     * @param string $qlevel a question level e.g. "A1", "B2"
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
                  'parentcat', 'subcat', 'qtag'];

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

        $subcattype = self::SUBCAT_NONE;
        $subcatname = '';

        $tagtypes = self::QTAG_NONE;
        $tagnames = '';

        // Cache this list separator (usually a comma ",").
        $listsep = get_string('listsep', 'langconfig');

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

            $types = static::get_subcategory_types();
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
                    $subcatname = implode($listsep, $words);
                    $subcatname = $this->get_string('defaultcustomname', $subcatname);
                    $subcatname = shorten_text($subcatname); // Shorten to 30 chars.
                }
            } else if ($subcatname) {
                // Name given but not needed, so remove it.
                $subcatname = '';
            }

            unset($data->$groupname);
        }

        // Extract type and name of question subcategory.
        $groupname = 'qtag';
        if (property_exists($data, $groupname)) {

            $types = static::get_questiontag_types();
            foreach ($types as $type => $text) {
                if (! empty($data->{$groupname}[$type])) {
                    $tagtypes |= $type;
                }
            }

            if ($tagtypes & self::QTAG_CUSTOMTAGS) {
                if (array_key_exists('name', $data->$groupname)) {
                    $tagnames = trim($data->{$groupname}['name']);
                    $tagnames = explode($listsep, $tagnames);
                    $tagnames = array_filter($tagnames);
                    $tagnames = implode($listsep, $tagnames);
                }
            } else if ($tagnames) {
                // Tag names given but not needed, so remove them.
                $tagnames = '';
            }

            unset($data->$groupname);
        }

        // Cache reference to this questionbank tool object.
        // This allows easy access to the log functions.
        $tool = $this->get_subplugin();

        // Initialize arguments for "get_string()" used to report
        // the success or failure of setting up the adhoc task.
        $a = (object)[
            'taskid' => 0,
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
                        'tagtypes' => $tagtypes,
                        'tagnames' => $tagnames,
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
                    if ($a->taskid = \core\task\manager::queue_adhoc_task($task)) {
                        $tool::update_log($logid, [
                            'taskid' => $a->taskid,
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

        $defaultlogid = 0;
        $defaultfields = [];

        $logtable = '';
        $logmessage = '';

        // Get array of new log values. Use depth=2 to get array.
        if ($values = self::get_optional_param('log', null, PARAM_RAW, 2)) {

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

            // Extract the question tags.
            $tagtypes = 0;
            $tagnames = '';
            $name = 'qtag';
            if (isset($values[$name])) {
                if (is_array($values[$name])) {
                    // Remove empty values.
                    $values[$name] = array_filter($values[$name]);
                    // Extract non-empty types.
                    foreach ($values[$name] as $type => $value) {
                        if (is_numeric($type)) {
                            $tagtypes |= $type;
                        } else if ($type == 'name') {
                            $tagnames = $value;
                        }
                    }
                }
                unset($values[$name]);
            }
            $values['tagtypes'] = $tagtypes;
            $values['tagnames'] = $tagnames;

            if ($allowupdate) {

                // Define the types of the log fields that can be updated.
                // Fields that are not in this array cannot be updated.
                // The includes the folowing fields:
                // id, taskid, userid, vocabid, wordid, questionids
                // and any other fields that not in the list below.
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
                    'tagtypes' => PARAM_INT,
                    'tagnames' => PARAM_TEXT,
                    'maxtries' => PARAM_INT,
                    'tries' => PARAM_INT,
                    'status' => PARAM_INT,
                    'review' => PARAM_INT,
                    'error' => PARAM_TEXT,
                    'prompt' => PARAM_CLEANHTML,
                    'results' => PARAM_CLEANHTML,
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
                $defaultlogid = self::get_optional_param('defaultlogid', 0, PARAM_INT);
                $defaultfields = self::get_optional_param('defaultfields', [], PARAM_INT);
                $logmessage = $this->process_log_records(
                    $mform, $logaction, $logids,
                    $defaultlogid, $defaultfields
                );
            }
        }

        // Clean and process incoming form data.
        if (($data = data_submitted()) && confirm_sesskey()) {
            if (isset($data->submitbutton) && $data->submitbutton) {
                $this->generate_questions($mform, $data);
            }
        }

        // Define the strings and icons for log actions.
        $logactions = [
            'applydefaults' => 't/check',
            'editlog' => 't/edit',
            'redotask' => 't/reload',
            'resumetask' => 't/play',
            'fixquestions' => 't/preferences',
            'deletelog' => 't/delete',
        ];

        // Get table of current log records.
        $logtable = $this->get_log_records_table(
            $logactions, $logaction, $logids,
            $defaultlogid, $defaultfields
        );
        list($logtable, $logcount, $incomplete) = $logtable;

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
                $options = ['' => $this->get_string('withselected')];
                foreach ($logactions as $action => $icon) {
                    if ($action == 'editlog') {
                        continue;
                    }
                    if ($action == 'fixquestions' && empty($incomplete->log)) {
                        continue;
                    }
                    $options[$action] = $this->get_string($action);
                }
                $elements = [
                    $mform->createElement('select', 'logaction', '', $options),
                    $mform->createElement('submit', 'logbutton', get_string('go')),
                ];
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
     * @param int $defaultlogid The id of the log selected as the default log record.
     * @param array  $defaultfields The names of log fields whose values are to be applied.
     * @return void, but may update vocabtool_questionbank_log table in DB.
     */
    public function process_log_records($mform, $logaction, $logids, $defaultlogid, $defaultfields) {
        global $DB, $USER;

        // Cache reference to this questionbank tool object.
        // This allows easy access to the log functions.
        $tool = $this->get_subplugin();

        // Cache the vocabid.
        $vocabid = $tool->vocab->id;

        // Cache the siteadmin flag.
        $siteadmin = is_siteadmin();

        $defaultvalues = [];
        if ($defaultlogid && count($defaultfields)) {
            $defaultlog = $tool::get_log($defaultlogid);
            foreach ($defaultfields as $name => $checked) {
                if ($checked == 0) {
                    continue; // Shouldn't happen !!
                }
                if (property_exists($defaultlog, $name)) {
                    $defaultvalues[$name] = $defaultlog->$name;
                }
            }
        }
        if (empty($defaultvalues)) {
            $defaultvalues = null;
        }

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

                case 'applydefaults':
                    if ($defaultvalues) {
                        if ($tool::update_log_record($log, $defaultvalues)) {
                            $ids[] = $logid;
                        }
                    }
                    break;

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

                    // The subcategories group includes 'log[subcat][type]' and 'log[subcat][name]'.
                    $this->add_questiontags($mform, "log[qtag]", $log->tagtypes, $log->tagnames);

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
                    $this->add_field_select($mform, "log[$name]", $options, PARAM_ALPHANUM, $log->$name, $a);

                    $name = 'error';
                    $a = ['strname' => 'taskerror', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_TEXT, $log->$name, $a);

                    $name = 'prompt';
                    $a = ['strname' => 'prompttext', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_CLEANHTML, $log->$name, $a);

                    $name = 'results';
                    $a = ['strname' => 'resultstext', 'rows' => 1];
                    $this->add_field_textarea($mform, "log[$name]", PARAM_CLEANHTML, $log->$name, $a);

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
                case 'fixquestions':

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
                            // Redo task.
                            $tool::update_log($logid, [
                                'taskid' => $taskid,
                                'tries' => 0,
                                'error' => '',
                                'prompt' => '',
                                'results' => '',
                                'status' => $tool::TASKSTATUS_QUEUED,
                            ]);
                        } else if ($logaction == 'resumetask') {
                            // Resume task.
                            $tool::update_log($logid, [
                                'taskid' => $taskid,
                                'status' => $tool::TASKSTATUS_AWAITING_IMPORT,
                            ]);
                        } else if ($logaction == 'fixquestions') {
                            // Fix questions.
                            $tool::update_log($logid, [
                                'taskid' => $taskid,
                                'status' => $tool::TASKSTATUS_ADDING_MULTIMEDIA,
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
     * @param object $incomplete details of incomplete log ids and question ids.
     * @return string containing a list of links to previw pages of the questions.
     */
    public function format_questionids($questionids, $incomplete=null) {
        global $OUTPUT;

        if (empty($questionids)) {
            return '';
        }

        // Cache the courseid and edit/preview icons.
        $courseid = $this->get_vocab()->course->id;
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('editsettings'));
        $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
        $previewicon = $OUTPUT->pix_icon('t/preview', get_string('preview'));

        // Append, Bootstrap class to remove right (and left) margin on icons.
        $search = '/class="([^"]*?) *"/';
        $replace = 'class="$1 mx-0"';
        $editicon = preg_replace($search, $replace, $editicon, 1);
        $deleteicon = preg_replace($search, $replace, $deleteicon, 1);
        $previewicon = preg_replace($search, $replace, $previewicon, 1);

        // Cache URL of question edit page.
        // This is used as the "returnurl" when deleting questions.
        $params = ['courseid' => $courseid];
        $returnurl = new \moodle_url('/question/edit.php', $params);

        $ids = explode(',', $questionids);
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids);

        if (function_exists('array_key_last')) {
            // PHP >= 7.3
            $lastkey = array_key_last($ids);
        } else {
            // PHP <= 7.2
            end($ids);
            $lastkey = key($ids);
            reset($ids);
        }

        foreach ($ids as $i => $id) {

            // Set params for links.
            $params = ['onclick' => "this.target = 'vocabtool_questionbank';"];
            if ($incomplete && array_key_exists($id, $incomplete->question)) {
                // Use red text for links to incomplete questions.
                $params['class'] = ' text-danger';
            }

            $url = '/question/bank/editquestion/question.php';
            $url = new \moodle_url($url, ['courseid' => $courseid, 'id' => $id]);
            $ids[$i] = ' '.\html_writer::link($url, $id, $params);

            $url = '/question/bank/previewquestion/preview.php';
            $url = new \moodle_url($url, ['id' => $id]);
            $ids[$i] .= ' '.\html_writer::link($url, $previewicon, $params);

            $url = '/question/bank/deletequestion/delete.php';
            $url = new \moodle_url($url, [
                'deleteselected' => $id,
                'deleteall' => 1,
                "q$id" => 1,
                'courseid' => $courseid,
                'returnurl' => $returnurl,
            ]);
            $ids[$i] .= ' '.\html_writer::link($url, $deleteicon, $params);

            // Add comma separator, if this is not the last item in the array.
            if ($i != $lastkey) {
                $ids[$i] .= ',';
            }

            // We want to contain the links in a <span> that does not wrap.
            $ids[$i] = \html_writer::tag('span', $ids[$i], ['class' => 'text-nowrap']);
        }
        return implode(' ', $ids);
    }


    /**
     * Generates an HTML table displaying log records for a vocab activity.
     *
     * This method fetches and formats detailed log data associated with a specific vocab activity,
     * organizing it into an HTML table for display. The table includes task metadata, user info,
     * question data, AI assistant types, status, and timestamps. Checkboxes and action links are
     * also provided for each row to support bulk and individual operations.
     *
     * @param array $actions The string names and icons of actions that are avaiable for each log record.
     * @param string $logaction The current log action to highlight or process (e.g., 'editlog', 'deletelog').
     * @param array  $logids An associative array of selected log IDs for checkbox state (e.g., [3 => 1, 5 => 1]).
     * @param int $defaultlogid The id of the log selected as the default log record.
     * @param array  $defaultfields The names of log fields whose values are to be applied.
     *
     * @return array Returns a three-element array:
     *               - string: The generated HTML for the log table.
     *               - int: The number of log records affected by the log action.
     *               - object: Details of incomplete logs and questions.
     */
    public function get_log_records_table($actions, $logaction, $logids, $defaultlogid, $defaultfields) {
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

        // Cache valid types of subcategory and question tag.
        $subcategorytypes = static::get_subcategory_types();
        $questiontagtypes = static::get_questiontag_types();

        // Fetch all logs pertaining to the current vocab activity.
        if ($logs = $tool::get_logs($tool->vocab->id)) {

            $incomplete = $this->get_incomplete($tool, $logs);

            foreach ($logs as $log) {

                if (empty($users[$log->userid])) {
                    $users[$log->userid] = $DB->get_record('user', ['id' => $log->userid]);
                }

                if (empty($textnames[$log->textid])) {
                    $params = ['id' => $log->textid];
                    if ($name = $DB->get_field($configtable, 'subplugin', $params)) {
                        // The $name value is something like "vocabai_chatgpt".
                        // We want to use get_string('chatgpt', 'vocabai_chatgpt').
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

                // Format list of standard and custom subcategories.
                $subcattypes = $this->format_types(
                    $subcategorytypes, $log->subcattype, 'subcattypes'
                );
                $subcatnames = $this->format_names($log->subcatname, 'subcatnames');

                // Format list of standard and custom question tags.
                $tagtypes = $this->format_types(
                    $questiontagtypes, $log->tagtypes, 'tagtypes'
                );
                $tagnames = $this->format_names($log->tagnames, 'tagnames');

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
                $log->$name = $this->format_questionids($log->$name, $incomplete);

                if ($log->timecreated) {
                    $log->timecreated = userdate($log->timecreated, $datefmt);
                }
                if ($log->timemodified) {
                    $log->timemodified = userdate($log->timemodified, $datefmt);
                }
                if ($log->nextruntime) {
                    $log->nextruntime = get_string('nextruntime', 'tool_task');
                    $log->nextruntime += get_string('labelsep', 'langconfig');
                    $log->nextruntime += userdate($log->nextruntime, $datefmt);
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

                // Define radio btn to set this log as the default.
                $name = 'defaultlogid';
                $radiobutton = \html_writer::empty_tag('input', [
                    'type' => 'radio',
                    'value' => $log->id,
                    'name' => $name,
                    'id' => 'id_'.$name.'_'.$log->id,
                    'checked' => ($defaultlogid == $log->id ? 'checked' : null),
                ]);
                // Maybe we should add a "label" too?
                $label = \html_writer::tag(
                    'label', $tool->get_string('default', $log->id),
                    array('for' => "id_$name", 'class' => 'ms-1')
                );

                // Define actions allowed on this log record.
                $logactions = '';
                foreach ($actions as $action => $icon) {
                    if ($action == 'fixquestions') {
                        // Only allow this action if the log record has questions to fix.
                        $allowaction = array_key_exists($log->id, $incomplete->log);
                        $textparams = ['class' => 'text-danger'];
                    } else {
                        $allowaction = true;
                        $textparams = null;
                    }
                    if ($allowaction) {
                        $text = $tool->get_string($action);
                        $icon = $OUTPUT->pix_icon($icon, $text);
                        $url = $PAGE->url;
                        $url->params([
                            'logid' => $log->id,
                            'logaction' => $action,
                            'sesskey' => sesskey(),
                        ]);
                        $text = \html_writer::tag('small', $text, $textparams);
                        $logaction = \html_writer::link($url, $icon.' '.$text);
                        $logactions .= \html_writer::tag('div', $logaction, ['class' => $cssclass->logaction.' '.$action]);
                    }
                }
                $logactions = \html_writer::tag('div', $logactions, ['class' => $cssclass->logactions]);

                // Add a row of values for the current log record.
                $table->data[] = [
                    $checkbox,
                    $logactions,
                    $log->nextruntime,
                    fullname($users[$log->userid]),
                    \html_writer::tag('b', $log->word),
                    $radiobutton,
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
                    $subcattypes,
                    $subcatnames,
                    $tagtypes,
                    $tagnames,
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
            return [0, '', null];
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
            5 => 'center', // Default.
            7 => 'center', // Question count.
            8 => 'center', // Question level.
            22 => 'center', // Maxtries.
            23 => 'center', // Tries.
            25 => 'center', // Review.
        ];

        // Specify nowrap columns.
        $table->wrap = [
            18 => 'nowrap', // Sub-categories.
            20 => 'nowrap', // Question tags.
            21 => 'nowrap', // Custom tags.
        ];

        // Define strings for column headings.
        $head = [
            'logids' => get_string('select'),
            'logactions' => get_string('actions'),
            'taskid' => $this->get_string('adhoctaskid'),
            'userid' => $this->get_string('taskowner'),
            'wordid' => $this->get_string('word'),
            'defaultlogid' => get_string('default'),
            'qtype' => $this->get_string('questiontype'),
            'qcount' => $this->get_string('questioncount'),
            'qlevel' => $this->get_string('questionlevel'),
            'qformat' => $this->get_string('qformat'),
            'textid' => $this->get_string('textassistant'),
            'promptid' => $this->get_string('promptname'),
            'formatid' => $this->get_string('formatname'),
            'fileid' => $this->get_string('filedescription'),
            'imageid' => $this->get_string('imageassistant'),
            'audioid' => $this->get_string('audioassistant'),
            'videoid' => $this->get_string('videoassistant'),
            'parentcatid' => $this->get_string('parentcategory'),
            'subcattype' => $this->get_string('subcattype'),
            'subcatname' => $this->get_string('subcatname'),
            'tagtypes' => $this->get_string('questiontags'),
            'tagnames' => $this->get_string('customtags'),
            'maxtries' => $this->get_string('maxtries'),
            'tries' => $this->get_string('tries'),
            'status' => get_string('status'),
            'review' => $this->get_string('questionreview'),
            'error' => get_string('error'),
            'prompt' => $this->get_string('prompttext'),
            'results' => $this->get_string('resultstext'),
            'questionids' => $this->get_string('moodlequestions'),
            'timecreated' => $this->get_string('timecreated'),
            'timemodified' => $this->get_string('timemodified'),
        ];
        foreach ($head as $name => $text) {
            $head[$name] = new \html_table_cell($text);
        }
        $table->head = array_values($head);
        $table = \html_writer::table($table);

        $excludednames = [
            'logactions', 'taskid', 'userid', 'wordid',
            'error', 'prompt', 'results', 'questionids',
            'timecreated', 'timemodified',
        ];
        foreach ($head as $name => $cell) {
            if (in_array($name, $excludednames)) {
                $cell->text = '';
            } else if ($name == 'logids') {
                // Add the checkbox to select all logs.
                $cell->text = \html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'value' => 1,
                    'name' => $name.'[0]',
                    'id' => 'id_'.$name.'_0',
                    'class' => 'd-none',
                    'checked' => (in_array(0, $logids) ? 'checked' : null),
                ]);
            } else if ($name == 'defaultlogid') {
                // Add the radio button to select all logs.
                $cell->text = \html_writer::empty_tag('input', [
                    'type' => 'radio',
                    'value' => '0',
                    'name' => $name,
                    'id' => 'id_'.$name.'_0',
                    'checked' => ($defaultlogid == 0 ? 'checked' : null),
                ]);
            } else {
                $cell->text = \html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'value' => 1,
                    'name' => 'defaultfields['.$name.']',
                    'id' => 'id_defaultfields_'.$name,
                    'checked' => (in_array($name, $defaultfields) ? 'checked' : null),
                ]);
            }
            if ($cell->text) {
                $cell->attributes['style'] = 'text-align:center;';
            }
            $head[$name] = \html_writer::tag('th', $cell->text, $cell->attributes);
        }
        $head = \html_writer::tag('tr', implode('', array_values($head)));
        $table = substr_replace($table, $head, strpos($table, '</thead>'), 0);

        return [$table, $logcount, $incomplete];
    }

    /**
     * Formats a bitmask of type values into an HTML list of labels.
     *
     * @param array $validtypes Associative array of valid type values and their display texts.
     * @param int $types Bitmask representing selected types.
     * @param string $cssclass CSS class to apply to the <ul> wrapper.
     *
     * @return string HTML string representing the list of types.
     */
    protected function format_types($validtypes, $types, $cssclass) {
        $list = [];
        foreach ($validtypes as $type => $text) {
            if ($types & $type) {
                $params = ['data-type' => $type];
                $list[] = \html_writer::tag('li', $text, $params);
            }
        }
        return $this->format_list($list, $cssclass);
    }

    /**
     * Formats a comma-separated string of names into an HTML list.
     *
     * @param string $names Comma-separated string of names.
     * @param string $cssclass CSS class to apply to the <ul> wrapper.
     *
     * @return string|null HTML string representing the list of names, or null if input is empty.
     */
    protected function format_names($names, $cssclass) {
        if ($names = trim($names)) {
            $names = explode(',', $names);
            $names = array_map('trim', $names);
            $names = array_filter($names);
            $names = array_map(function($name) {
                return \html_writer::tag('li', $name);
            }, $names);
            $names = $this->format_list($names, $cssclass);
        }
        return $names;
    }

    /**
     * Wraps an array of list items in an unordered HTML list.
     *
     * @param array $list Array of list item strings or raw strings.
     * @param string $cssclass CSS class to apply to the <ul> element.
     *
     * @return string|null HTML unordered list, or null if input is empty.
     */
    protected function format_list($list, $cssclass) {
        if ($list = implode("\n", $list)) {
            $params = ['class' => "list-unstyled $cssclass"];
            $list = \html_writer::tag('ul', $list, $params);
        }
        return $list;
    }

    /**
     * Identifies incomplete question records that reference missing media placeholders.
     *
     * This function scans a list of log entries, extracts referenced question IDs,
     * and checks associated question data fields for media placeholders such as
     * [[AUDIO]], [[IMAGE]], or [[VIDEO]]. It returns a list of log IDs and
     * question IDs that are considered incomplete.
     *
     * @param object $tool the associated vocabtool object (i.e. vocabtool_questionbank).
     * @param array $logs Associative array of log records, keyed by log ID.
     *              Each log contains a questionids field with a comma-separated list of question IDs.
     * @return \stdClass An object with two properties:
     *                   - log: array of log IDs containing incomplete questions.
     *                   - question: array of question IDs flagged as incomplete.
     */
    protected function get_incomplete($tool, $logs) {
        global $DB;

        // Initialize the return object.
        $incomplete = (object)[
            'log' => [],
            'question' => [],
        ];

        $questionids = [];
        $name = 'questionids';
        foreach ($logs as $logid => $log) {
            if (property_exists($log, $name)) {
                $qids = explode(',', $log->$name);
                $qids = array_map('trim', $qids);
                $qids = array_filter($qids);
                if (count($qids) < $log->qcount) {
                    if ($log->status == $tool::TASKSTATUS_AWAITING_REVIEW ||
                        $log->status == $tool::TASKSTATUS_COMPLETED ||
                        $log->status == $tool::TASKSTATUS_CANCELLED ||
                        $log->status == $tool::TASKSTATUS_FAILED) {
                        // Not enough questions.
                        $incomplete->log[$logid] = true;
                    }
                }
                foreach ($qids as $qid) {
                    $questionids[$qid] = $logid;
                }
            }
        }

        ksort($questionids);
        if (count($questionids)) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($questionids));
            if ($questions = $DB->get_records_select('question', "id $select", $params)) {
                $classname = '\\vocabtool_questionbank\\task\\questions';
                $ids = [];
                $select = [];
                $params = [];
                $tables = [];
                foreach ($questions as $qid => $question) {
                    $qtype = $question->qtype;
                    if (empty($tables[$qtype])) {
                        $tables[$qtype] = $classname::get_tables($question);
                        foreach ($tables[$qtype] as $table => $fields) {
                            if (empty($ids[$table])) {
                                $ids[$table] = [];
                            }
                            if (empty($select[$table])) {
                                $select[$table] = [];
                                $params[$table] = [];
                                foreach ($fields as $field) {
                                    $sql = $DB->sql_like($field, '?');
                                    array_push($select[$table], $sql, $sql, $sql);
                                    array_push($params[$table], '%[[AUDIO%]]%', '%[[IMAGE%]]%', '%[[VIDEO%]]%');
                                }
                                $select[$table] = implode(' OR ', $select[$table]);
                            }
                        }
                    }
                    foreach ($tables[$qtype] as $table => $fields) {
                        $ids[$table][] = $qid;
                    }
                }
                foreach (array_keys($select) as $table) {
                    list($s, $p) = $DB->get_in_or_equal($ids[$table]);
                    $s = 'id '.$s.' AND ('.$select[$table].')';
                    $p = array_merge($p, $params[$table]);
                    if ($records = $DB->get_records_select($table, $s, $p)) {
                        foreach ($records as $qid => $q) {
                            $logid = $questionids[$qid];
                            $incomplete->log[$logid] = true;
                            $incomplete->question[$qid] = true;
                        }
                    }
                }
            }
        }
        return $incomplete;
    }
}
