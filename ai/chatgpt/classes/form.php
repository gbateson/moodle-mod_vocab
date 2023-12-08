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
 * ai/chatgpt/classes/form.php
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_chatgpt;

defined('MOODLE_INTERNAL') || die;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    public $settings = ['chatgpturl', 'chatgptkey', 'chatgptmodel', 'sharedfrom', 'shareduntil'];

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        list($otherconfigs, $myconfigs, $configs) = $this->get_configs();

        if (count($otherconfigs)) {

            $name = 'keysownedbyothers';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            $text = \html_writer::tag('span', 'Note:', ['class' => 'text-danger']);
            $text = "$text You cannot edit these keys.";
            $text = \html_writer::tag('h5', $text, array('class' => 'cannotedit'));
            $mform->addElement('html', $text);

            // Display the config settings that apply to this context and are
            // owned by other users. These are not editable by the current user.
            foreach ($otherconfigs as $configid => $config) {
                $config = $this->format_config($mform, $config, true);
            }
        }

        if (count($myconfigs)) {

            $name = 'otherkeysownedbyme';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            // Display the config settings that owned by this user and apply to the current context.
            // These are not editable by the current user.
            foreach ($myconfigs as $configid => $config) {
                $config = $this->format_config($mform, $config, false, 'copy');
            }
        }

        if (count($configs)) {

            $name = 'keysownedbyme';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            // Display the config settings that owned by this user and apply to the current context.
            // These are not editable by the current user.
            foreach ($configs as $configid => $config) {
                $config = $this->format_config($mform, $config, false, 'edit');
            }
        }

        $name = 'addnewkey';
        $this->add_heading($mform, $name, $this->subpluginname, true);

        $name = 'chatgpturl';
        $default = ($mymodsettings->$name ?? 'https://api.openai.com/v1/chat/completions');
        $this->add_field_text($mform, $name, PARAM_URL, $default, ['size' => '40']);

        $name = 'chatgptkey';
        $default = ($mymodsettings->$name ?? 'sk2-');
        $this->add_field_text($mform, $name, PARAM_URL, $default, ['size' => '40']);

        $name = 'chatgptmodel';
        $options = ['gpt-3-turbo' => 'gpt-3-turbo', 'gpt-4' => 'gpt-4'];
        $default = ($mymodsettings->$name ?? 'gpt-4');
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default);

        $name = 'sharingcontext';
        $options = $this->get_sharingcontext_options();
        $default = ($mymodsettings->contextlevel ?? CONTEXT_MODULE);
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default);

        // Get current year, month and day.
        list($year, $month, $day) = explode(' ', date('Y m d'));

        // Shared from/until date are both optional.
        $params = ['optional' => true];

        // Shared from date and time (default is start of today).
        $params['defaulttime'] = mktime(0, 0, 0, $month, $day, $year);
        $this->add_field_datetime($mform, 'sharedfrom', $params);

        // Shared until date and time (default is end of today).
        $params['defaulttime'] = mktime(23, 59, 59, $month, $day, $year);
        $this->add_field_datetime($mform, 'shareduntil', $params);

        $this->add_action_buttons(true, get_string('add'));
    }

    /**
     * Get a list of availability options for a ChatGPT key;
     *
     * $param object $mform
     * $param object $config
     * @return array of availability options [contextlevel => availability description]
     */
    public function format_config($mform, $config, $showowner=false, $buttonname='') {
        global $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->dirroot.'/lib/outputcomponents.php');

        $html = '';

        // Cache id value. It is used to make field names unique.
        $id = $config->id;

        // Cache often-used items.
        $labelsep = get_string('labelsep', 'langconfig');
        $dl = ['class' => 'row my-0 mx-0'];
        $dt = ['class' => 'col-6 col-sm-4 col-md-3 col-xl-2 my-1 mx-0'];
        $dd = ['class' => 'col-6 col-sm-8 col-md-9 col-xl-10 my-1 mx-0'];
        $link = ['class' => 'btn btn-dark'];

        // Format the key to show only the 1st 4 chars and the final 4 chars..
        $name = 'chatgptkey';
        $label = $this->get_string($name).$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $value = $config->$name;
        $value = substr($value, 0, 4).' ... '.substr($value, -4);
        $value = \html_writer::tag('dd', $value, $dd);
        $html .= \html_writer::tag('dl', $label.$value, $dl);

        // Format the owner's name.
        if ($showowner) {
            $name = 'owner';
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $user = $DB->get_record('user', ['id' => $config->owneruserid]);
            $value = fullname($user);
            if ($showownerpic) {
                $value .= $OUTPUT->user_picture($user, ['popup' => true]);
            }
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        $name = 'chatgptmodel';
        $label = $this->get_string($name).$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $value = $config->$name;
        $value = \html_writer::tag('dd', $value, $dd);
        $html .= \html_writer::tag('dl', $label.$value, $dl);

        $name = 'sharedfrom';
        if (isset($config->$name)) {
            $value = userdate($config->$name);
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        $name = 'shareduntil';
        if (isset($config->$name)) {
            $value = userdate($config->$name);
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        // Format the context level.
        $context = $config->contextlevel;
        switch ($context) {
            case CONTEXT_MODULE:
                $context = $this->get_string('sharedinvocabcontext');
                break;
            case CONTEXT_COURSE:
                $context = $this->get_string('sharedincoursecontext');
                break;
            case CONTEXT_COURSECAT:
                $context = $this->get_string('sharedincoursecatcontext');
                break;
            case CONTEXT_SYSTEM:
                $context = $this->get_string('sharedinsystemcontext');
                break;
            default:
                $context = $this->get_string('sharedinunknowncontext', $config->contextlevel);
        }

        $name = 'sharingcontext';
        $label = $this->get_string($name).$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $value = \html_writer::tag('dd', $context, $dd);
        $html .= \html_writer::tag('dl', $label.$value, $dl);
        if ($buttonname) {
            $url = $PAGE->url;
            $url->param('cid', $config->id);
            $btn = \html_writer::link($url, get_string($buttonname), $link);
            $label = \html_writer::tag('dt', '', $dt);
            $value = \html_writer::tag('dd', $btn, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        $params = ['class' => 'chatgptkeyinfo'];
        $html = \html_writer::tag('div', $html, $params);

        $mform->addElement('html', $html);
    }

    private function format_config_old() {

        // see "userdate" function in "block_maj_submissions.php
        // for a better way to display a date range.

        $fmt = get_string('strftimedatemonthtimeshort', 'langconfig');
        $period = (object)[
            'from' => (empty($config->sharedfrom) ? '' : userdate($config->sharedfrom, $fmt)),
            'until' => (empty($config->shareduntil) ? '' : userdate($config->shareduntil, $fmt)),
        ];

        switch (true) {

            case ($period->from && $period->until):
                $period = $this->get_string('sharedfromuntildate', $period);
                break;

            case ($period->from):
                $period = $this->get_string('sharedfromdate', $period->from);
                break;

            case ($period->until):
                $period = $this->get_string('shareduntildate', $period->until);
                break;

            default:
                $period = $this->get_string('sharedanydate');
                break;
        }


        // Cache the label separator.
        $labelsep = get_string('labelsep', 'langconfig');
        $dl = ['class' => 'row my-0'];
        $dt = ['class' => 'col-4 my-0 py-0'];
        $dd = ['class' => 'col-8 my-0 py-0'];

        $key = \html_writer::tag('big', $key, ['class' => 'bg-secondary text-dark px-2 rounded']);

        $label = $this->get_string($name).$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $model = \html_writer::tag('dd', $model, $dd);
        $model = \html_writer::tag('dl', $label.$model, $dl);

        if ($owner) {
            $label = $this->get_string('owner').$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $owner = \html_writer::tag('dd', $owner, $dd);
            $owner = \html_writer::tag('dl', $label.$owner, $dl);
        }

        $label = $this->get_string('sharingperiod').$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $period = \html_writer::tag('dd', $period, $dd);
        $period = \html_writer::tag('dl', $label.$period, $dl);

        $label = $this->get_string('sharingcontext').$labelsep;
        $label = \html_writer::tag('dt', $label, $dt);
        $context = \html_writer::tag('dd', $context, $dd);
        $context = \html_writer::tag('dl', $label.$context, $dl);

        $output = $key.$model.$owner.$period.$context;
        $params = ['class' => 'container striped'];
        $output = \html_writer::tag('div', $output, $params);

        return $output;
    }

    /**
     * Get a list of availability options for a ChatGPT key.
     *
     * @return array of availability options [contextlevel => availability description]
     */
    public function get_sharingcontext_options() {

        // Get array of writeable context levels [contextlevel => contextid].
        $options = $this->get_vocab()->get_writeable_contexts('contextlevel', 'id');
        foreach ($options as $level => $id) {

            switch ($level) {

                case CONTEXT_MODULE:
                    $options[$level] = $this->get_string('sharedinvocabcontext');
                    break;

                case CONTEXT_COURSE:
                    $options[$level] = $this->get_string('sharedincoursecontext');
                    break;

                case CONTEXT_COURSECAT:
                    $options[$level] = $this->get_string('sharedincoursecatcontext');
                    break;

                case CONTEXT_SYSTEM:
                    $options[$level] = $this->get_string('sharedinsystemcontext');
                    break;

                default:
                    // Unknown context level - shouldn't happen !!
                    unset($options[$level]);
            }
        }
        return $options;
    }

    /**
     * Get config settings relevant to this context and user.
     *
     * @uses $DB
     * @uses $USER
     * @return array [$myconfigs, $configs] of settings
     */
    public function get_configs() {
        global $DB, $USER;

        $select = 'vcs.id, vcs.name, vcs.value, vcs.configid, '.
                  'vc.owneruserid, vc.contextid, '.
                  'ctx.contextlevel';

        $from = '{vocab_config_settings} vcs '.
                'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id '.
                'JOIN {context} ctx ON vc.contextid = ctx.id';

        $contexts = $this->get_vocab()->get_readable_contexts('', 'id');
        list($where, $params) = $DB->get_in_or_equal($contexts);

        // We're interested in config settings for this subplugin
        // that are shared in this context or any parent context.
        // We also want other config settings owned by the current user.
        $where = "vc.subplugin = ? AND (vc.owneruserid = ? OR vc.contextid $where)";
        $params = array_merge([$this->subpluginname, $USER->id], $params);

        // Sort by owner, context level and configid.
        $sort = 'vc.owneruserid, ctx.contextlevel, vcs.configid';

        $otherconfigs = [];
        $myconfigs = [];
        $configs = [];
        $config = null;

        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $sort";
        if ($settings = $DB->get_records_sql($sql, $params)) {

            $settingids = array_keys($settings);
            $smax = count($settingids);
            for ($s = 0; $s <= $smax; $s++) {

                if ($s == $smax) {
                    // Final iteration.
                    $settingid = 0;
                    $setting = null;
                } else {
                    // First iteration.
                    $settingid = $settingids[$s];
                    $setting = $settings[$settingid];
                }

                switch (true) {
                    case ($config === null):
                        // First iteration.
                        $storeconfig = false;
                        break;

                    case ($setting === null):
                        // Final iteration.
                        $storeconfig = true;
                        break;

                    case ($config->id == $setting->configid):
                        // $config->id has not changed.
                        $storeconfig = false;
                        break;

                    default:
                        // $config->id has changed.
                        $storeconfig = true;
                }

                if ($storeconfig) {
                    if ($config->owneruserid == $USER->id) {
                        if (in_array($config->contextid, $contexts)) {
                            // A config that is owned by the current user
                            // and is relevant to the current context.
                            $configs[$config->id] = $config;
                        } else {
                            // A config that is owned by the current user,
                            // but is for an unrelated context.
                            $myconfigs[$config->id] = $config;
                        }
                    } else {
                        // A config that is relevant to the current
                        // context but owned by a another user.
                        $otherconfigs[$config->id] = $config;
                    }
                    $config = null;
                }

                if ($setting) {
                    if ($config === null) {
                        $config = (object)[
                            'id' => $setting->configid,
                            'contextid' => $setting->contextid,
                            'contextlevel' => $setting->contextlevel,
                            'owneruserid' => $setting->owneruserid,
                        ];
                    }
                    $config->{$setting->name} = $setting->value;
                }
            }
        }

        return [$otherconfigs, $myconfigs, $configs];
    }

    /**
     * save the config settings form the input form.
     */
    public function save_config() {
        global $DB, $USER;

        if ($data = $this->get_data()) {

            $vocab = $this->get_vocab();
            $contexts = $vocab->get_writeable_contexts('contextlevel', 'id');

            $name = 'sharingcontext';
            if (isset($data->$name) && isset($contexts[$data->$name])) {
                $contextid = $contexts[$data->$name];
                $contextlevel = $data->$name;
            } else if ($vocab->cm) {
                // Shouldn't happen, but we can continue.
                $contextlevel = CONTEXT_MODULE;
                $contextid = $vocab->context->id;
            } else {
                // Definitely shouldn't happen !!
                $contextlevel = 0;
                $contextid = 0;
            }
            if (isset($data->$name)) {
                unset($data->$name);
            }

            if ($contextlevel && $contextid) {

                // Get or create the config record.
                $table = 'vocab_config';
                $params = [
                    'owneruserid' => $USER->id,
                    'contextid' => $contextid,
                    'subplugin' => $this->subpluginname,
                ];
                $config = $DB->get_record($table, $params);
                if (empty($config)) {
                    // Config record does not exist, so create it.
                    $params['id'] = $DB->insert_record($table, $params);
                    $config = (object)$params;
                }

                // Add or update the settings for this config record.
                $table = 'vocab_config_settings';
                foreach ($this->settings as $name) {

                    $params = [
                        'configid' => $config->id,
                        'name' => $name,
                    ];
                    if (empty($data->$name)) {
                        // Remove previous value, if there was one.
                        if ($DB->record_exists($table, $params)) {
                            $DB->delete_records($table, $params);
                        }
                    } else {
                        $value = $data->$name;
                        if ($setting = $DB->get_record($table, $params)) {
                            // Update previous value, if it has changed.
                            if ($setting->value != $value) {
                                $setting->value = $value;
                                $DB->set_field($table, 'value', $value, ['id' => $setting->id]);
                            }
                        } else {
                            // Add a new setting name and value.
                            $params['value'] = $value;
                            $setting = (object)$params;
                            $setting->id = $DB->insert_record($table, $setting);
                        }
                    }
                }
            }
        }
    }
}
