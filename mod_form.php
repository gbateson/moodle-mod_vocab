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
 * mod_form.php: Form to add/edit settings for a mod_vocab instance.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

// Get the standard Moodle form for mod plugins.
require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * mod_vocab_mod_form
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class mod_vocab_mod_form extends moodleform_mod {

    /** size of numeric text boxes */
    const TEXT_NUM_SIZE = 4;

    /**
     * definition
     *
     * @uses $CFG
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        global $CFG;

        $this->collapse_navigation();

        $mform = $this->_form;

        $plugin = 'mod_vocab';
        $config = get_config($plugin); // Get sitewide settings.

        $dateoptions = ['optional' => true];
        $textoptions = ['size' => self::TEXT_NUM_SIZE];

        // -----------------------------------------------------------------------------
        $name = 'general';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        // -----------------------------------------------------------------------------

        $name = 'name';
        $label = get_string('activityname', $plugin);
        $mform->addElement('text', $name, $label, ['size' => '64']);
        $type = (empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->setType($name, $type);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addRule($name, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('moduleintro'));

        $name = 'operationmode';
        $label = get_string($name, $plugin);
        $options = [
            \mod_vocab\activity::MODE_LIVE => get_string('livemode', $plugin),
            \mod_vocab\activity::MODE_DEMO => get_string('demonstrationmode', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, \mod_vocab\activity::MODE_LIVE);

        // -----------------------------------------------------------------------------
        $name = 'display';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, true);
        // -----------------------------------------------------------------------------

        $name = 'expandnavigation';
        $label = get_string($name, $plugin);
        $options = [
            \mod_vocab\activity::EXPAND_EVERYONE => get_string('expandforeveryone', $plugin),
            \mod_vocab\activity::EXPAND_TEACHERS => get_string('expandforteachers', $plugin),
            \mod_vocab\activity::EXPAND_STUDENTS => get_string('expandforstudents', $plugin),
            \mod_vocab\activity::EXPAND_NO_ONE => get_string('expandfornoone', $plugin),
        ];

        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, \mod_vocab\activity::EXPAND_NO_ONE);

        $name = 'pagelayout';
        $label = get_string($name, $plugin);
        $options = $this->get_pagelayout_options($plugin);
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_ALPHA);
        $mform->setDefault($name, 'standard');

        // -----------------------------------------------------------------------------
        $name = 'pagelayouts';
        $label = get_string($name, $plugin);
        $mform->addElement('header', $name, $label);
        // -----------------------------------------------------------------------------

        $mform->addElement('html', $this->get_pagelayouts_table($plugin));

        // -----------------------------------------------------------------------------
        $name = 'timing';
        $label = get_string($name, 'form');
        $mform->addElement('header', $name, $label);
        // -----------------------------------------------------------------------------

        $name = 'activityopen'; // Old name was "viewablefrom".
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'activityclose'; // Old name was "viewableuntil".
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'gamesopen'; // Old name was "playablefrom".
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'gamesclose'; // Old name was "playableuntil".
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, $plugin);
        self::set_type_default_advanced($mform, $config, $name, PARAM_INT);

        // -----------------------------------------------------------------------------
        $name = 'wordmasteryconditions';
        $label = get_string($name, $plugin);
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, true);
        // -----------------------------------------------------------------------------

        $this->add_attemptscore($mform, $plugin);
        $this->add_attemptcount($mform, $plugin);
        $this->add_attemptdelay($mform, $plugin);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Set the default type for an advanced field.
     *
     * @param object $mform representing the Moodle form
     * @param object $config settings for this plugin
     * @param string $name of field
     * @param mixed $type PARAM_xxx constant value
     * @param mixed $default (optional, default = null)
     */
    public static function set_type_default_advanced($mform, $config, $name, $type, $default=null) {
        $mform->setType($name, $type);
        if (isset($config->$name)) {
            $mform->setDefault($name, $config->$name);
        } else if ($default) {
            $mform->setDefault($name, $default);
        }
        $advname = 'adv'.$name;
        if (isset($config->$advname)) {
            $mform->setAdvanced($name, $config->$advname);
        }
    }

    /**
     * Add the attemptscore field to the $mform.
     *
     * @param object $mform representing the Moodle form
     * @param string $plugin the name of this plugin
     * @return void, but will update the $mform object
     */
    public function add_attemptscore($mform, $plugin) {
        $name = 'attemptscore';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => '2']);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 80);
    }

    /**
     * Add the attemptcount field to the $mform.
     *
     * @param object $mform representing the Moodle form
     * @param string $plugin the name of this plugin
     * @return void, but will update the $mform object
     */
    public function add_attemptcount($mform, $plugin) {
        global $OUTPUT;

        $elements = [];

        $namecount = 'attemptcount';
        $labelcount = get_string($namecount, $plugin);
        $options = array_combine(range(1, 10), range(1, 10));
        $elements[] = $mform->createElement('select', $namecount, $labelcount, $options);

        $nametype = 'attempttype';
        $labeltype = get_string($nametype, $plugin);
        $options = [
            \mod_vocab\activity::ATTEMPTTYPE_ANY => get_string('anyattempts', $plugin),
            \mod_vocab\activity::ATTEMPTTYPE_RECENT => get_string('recentattempts', $plugin),
            \mod_vocab\activity::ATTEMPTTYPE_CONSECUTIVE => get_string('consecutiveattempts', $plugin),
        ];
        $elements[] = $mform->createElement('select', $nametype, $labeltype, $options);
        $elements[] = $mform->createElement('html', $OUTPUT->help_icon($nametype, $plugin));

        $groupname = $namecount.'elements';
        $mform->addGroup($elements, $groupname, $labelcount, [' '], false);
        $mform->addHelpButton($groupname, $namecount, $plugin);

        $mform->setType($namecount, PARAM_INT);
        $mform->setDefault($namecount, 5);

        $mform->setType($nametype, PARAM_INT);
        $mform->setDefault($nametype, \mod_vocab\activity::ATTEMPTTYPE_ANY);
    }

    /**
     * Add the attemptdelay field to the $mform.
     *
     * @param object $mform representing the Moodle form
     * @param string $plugin the name of this plugin
     * @return void, but will update the $mform object
     */
    public function add_attemptdelay($mform, $plugin) {
        $name = 'attemptdelay';
        $label = get_string($name, $plugin);

        // Cache line break element.
        $linebreak = \html_writer::tag('span', '', ['class' => 'w-100']);

        // We don't use <br> because of the following rule in Moodle CSS:
        // .mform .form-inline br+label { width: 100%; }
        // This rule forces <label> folowing <br> onto its own line which we don't want.

        $elements = [];

        $text = get_string('none');
        $value = \mod_vocab\activity::ATTEMPTDELAY_NONE;
        $elements[] = $mform->createElement('radio', $name, $text, '', $value);
        $elements[] = $mform->createElement('html', $linebreak);

        $text = get_string('fixeddelay', $plugin);
        $value = \mod_vocab\activity::ATTEMPTDELAY_FIXED;
        $elements[] = $mform->createElement('radio', $name, $text, '', $value);

        $options = ['defaultunit' => 60]; // Default units are "minutes".
        $elements[] = $mform->createElement('duration', $name.'fixed', '', $options);
        $elements[] = $mform->createElement('html', $linebreak);

        $text = get_string('expandingdelay', $plugin);
        $value = \mod_vocab\activity::ATTEMPTDELAY_EXPANDING;
        $elements[] = $mform->createElement('radio', $name, $text, '', $value);

        $groupname = $name.'elements';
        $mform->addGroup($elements, $groupname, $label, [' '], false);
        $mform->addHelpButton($groupname, $name, $plugin);

        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, \mod_vocab\activity::ATTEMPTDELAY_NONE);

        $value = \mod_vocab\activity::ATTEMPTDELAY_FIXED;
        $mform->disabledIf($name.'fixed[number]', $name, 'neq', $value);
        $mform->disabledIf($name.'fixed[timeunit]', $name, 'neq', $value);
    }

    /**
     * Add data to the form before it is displayed.
     *
     * @param array $data to be added to the form (passed by reference)
     * @return void, but may update values in the $data array
     */
    public function data_preprocessing(&$data) {
        $name = 'attemptdelay';
        $default = \mod_vocab\activity::ATTEMPTDELAY_NONE;
        $value = (isset($data[$name]) ? $data[$name] : $default);
        if ($value > 0) {
            $data[$name.'fixed'] = $value;
            $value = \mod_vocab\activity::ATTEMPTDELAY_FIXED;
        } else {
            // Default fixed delay is 30 minutes.
            $data[$name.'fixed'] = 1800;
        }
        $data[$name] = $value;
    }

    /**
     * Add data to the form after it has been submitted.
     *
     * @param object $data from the form (passed by reference)
     * @return void, but may update values in the $data array
     */
    public function data_postprocessing($data) {
        $name = 'attemptdelay';
        $default = \mod_vocab\activity::ATTEMPTDELAY_NONE;
        $value = (isset($data->$name) ? $data->$name : $default);
        if ($value == \mod_vocab\activity::ATTEMPTDELAY_FIXED) {
            $namefixed = $name.'fixed';
            $value = (isset($data->$namefixed) ? $data->$namefixed : $default);
        }
        $data->$name = $value;
    }

    /**
     * Add completion rules
     *
     * @return array
     */
    public function add_completion_rules() {
        return [];
    }

    /**
     * Determine whether or not a completion rule is enabled.
     *
     * @param stdClass $data submitted from the form
     * @return boolean
     */
    public function completion_rule_enabled($data) {
        return false;
    }

    /**
     * Get an array of available page layouts.
     *
     * @uses $PAGE
     * @return array of page layouts.
     */
    public function get_pagelayouts() {
        global $PAGE;
        $layouts = [];
        $duplicates = [
            // Duplicates of the "base" layout.
            'course', 'coursecategory', 'incourse', 'frontpage',
            'admin', 'mycourses', 'mydashboard', 'mypublic', 'report',
            // Duplicates of the "popup" layouts.
            'print', 'redirect',
            // Duplicate of the "embedded" layout.
            'frametop',
        ];
        foreach ($PAGE->theme->layouts as $name => $layout) {
            if (in_array($name, $duplicates)) {
                continue;
            }
            $layouts[$name] = $layout;
        }
        return $layouts;
    }

    /**
     * Fetch the list of pagelayout options.
     *
     * @param string $plugin name
     * @return array of page layout names
     */
    public function get_pagelayout_options($plugin) {
        $strman = get_string_manager();
        $options = array_keys($this->get_pagelayouts());
        $options = array_combine($options, $options);
        foreach ($options as $name => $value) {
            if ($strman->string_exists("layout$name", $plugin)) {
                $options[$name] = get_string("layout$name", $plugin);
            } else if ($strman->string_exists($name, 'moodle')) {
                $options[$name] = get_string($name, 'moodle');
            } else {
                $options[$name] = core_text::strtotitle($name);
            }
        }
        asort($options);
        return $options;
    }

    /**
     * Display table of information about page layouts.
     *
     * @param string $plugin
     * @return string of HTML to display table of page layouts
     */
    public function get_pagelayouts_table($plugin) {
        $rows = [];
        foreach ($this->get_pagelayouts() as $name => $layout) {
            $row = [
                'name' => $name,
                'file' => '',
                'regions' => '',
                'defaultregion' => '',

                'option_langmenu' => '',
                'option_nofooter' => '',
                'option_nonavbar' => '',
                'option_nocoursefooter' => '',
                'option_noactivityheader' => '',

                'option_activityheader_notitle' => '',
                'option_activityheader_nocompletion' => '',
                'option_activityheader_nodescription' => '',
            ];
            if (isset($layout['file'])) {
                $row['file'] = $layout['file'];
            }
            if (isset($layout['regions'])) {
                $row['regions'] = implode(', ', $layout['regions']);
            }
            if (isset($layout['defaultregion'])) {
                $row['defaultregion'] = $layout['defaultregion'];
            }
            if (isset($layout['options'])) {
                foreach ($layout['options'] as $optionname => $optionvalue) {
                    if (is_scalar($optionvalue)) {
                        $row['option_'.$optionname] = $optionvalue;
                    } else {
                        // The "activityheader" is an array of options.
                        foreach ($optionvalue as $n => $v) {
                            $row['option_'.$optionname.'_'.$n] = $v;
                        }
                    }
                }
            }
            $rows[] = $row;
        }
        $table = new \html_table();
        $table->id = 'pagelayouts_table';
        $table->head = [];
        $table->data = [];
        $table->align = [];

        $table->rowclasses = [];
        $table->colclasses = [];

        $strman = get_string_manager();
        foreach ($rows as $r => $row) {

            if ($r == 0) {
                // Set up headings.
                $keys = array_keys($row);
                foreach ($keys as $c => $key) {

                    // Extract the the last "word" in the key.
                    $i = substr_count($key, '_');
                    $key = explode('_', $key)[$i];

                    $cell = new html_table_cell();
                    $cell->header = true;
                    if ($strman->string_exists($key, $plugin)) {
                        $cell->text = get_string($key, $plugin);
                    } else if ($strman->string_exists($key, 'moodle')) {
                        $cell->text = get_string($key, 'moodle');
                    } else {
                        $cell->text = $key;
                    }
                    $table->head[$c] = $cell;

                    $table->align[$c] = 'center';

                    // We could implement alternate dark-light striping of columns
                    // using $table->colclasses[$c] = ($c % 2 ? '' : 'bg-light');
                    // but this is not necessary because the table is not very large.
                }
            }

            foreach ($row as $key => $value) {
                $cell = new \html_table_cell();
                if ($key == 'name') {
                    $cell->header = true;
                }
                if ($value == '1') {
                    $value = get_string('yes');
                    $params = ['title' => $value, 'class' => 'fa fa-check-circle text-info'];
                    $cell->text = \html_writer::tag('big', '', $params);
                } else {
                    $cell->text = $value;
                }
                $row[$key] = $cell;
            }

            $table->data[] = new \html_table_row(array_values($row));

            // We could implement alternate dark-light striping of columns
            // using $table->rowclasses[$r] = ($r % 2 ? '' : 'bg-light')
            // but this is not necessary because this table is created
            // with the "generaltable" CSS class, which includes stripes.
        }

        return \html_writer::table($table);
    }

    /**
     * collapse_navigation
     *
     *
     * TODO: Finish documenting this function
     */
    public function collapse_navigation() {
        \mod_vocab\activity::create(
            $this->get_course(),
            $this->get_coursemodule(),
            ($this->get_instance() ? $this->get_current() : null),
            $this->get_context()
        )->collapse_navigation();
    }
}
