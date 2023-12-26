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

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            // Check we have expected fields.
            foreach ($this->get_subplugin()->get_settingnames() as $name) {
                if (empty($default->$name)) {
                    $default->$name = null;
                }
            }

            $mainheading = 'editkey';
            $submitlabel = get_string('save');

        } else {

            $mainheading = 'addnewkey';
            $submitlabel = get_string('add');
            
            // Get current year, month and day.
            list($year, $month, $day) = explode(' ', date('Y m d'));

            // Define default values for new key.
            $default = (object)[
                'id' => 0,
                'chatgpturl' => 'https://api.openai.com/v1/chat/completions',
                'chatgptkey' => '',
                'chatgptmodel' => 'gpt-4',
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
        }

        // Display the config settings that apply to this context and are
        // owned by other users. These are NOT editable by the current user.
        $configs = $this->get_subplugin()->get_configs('otherusers', 'thiscontext', $default->id);
        if (count($configs)) {

            $name = 'keysownedbyothers';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            if (is_siteadmin()) {
                // Site admin can always edit, copy and delete anything.
                $actions = ['edit', 'copy', 'delete'];
            } else {
                $actions = [];

                // Display message to non-admin users (e.g. teachers)
                // explaining that they cannot edit keys owned by other people.
                $text = $this->get_string('note');
                $text = \html_writer::tag('span', $text, ['class' => 'text-danger']);
                $text = $text.get_string('labelsep', 'langconfifg');
                $text = $text.$this->get_string('cannoteditkeys');
                $text = \html_writer::tag('h5', $text, array('class' => 'cannotedit'));
                $mform->addElement('html', $text);
            }
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions, true)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        // Display the config settings that are owned by this user but do not
        // apply to the current context. These are editable by the current user.
        $configs = $this->get_subplugin()->get_configs('thisuser', 'othercontexts', $default->id);
        if (count($configs)) {

            $name = 'otherkeysownedbyme';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            $actions = ['edit', 'copy', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        // Display the config settings that owned by this user and apply to
        // the current context. These are editable by the current user.
        $configs = $this->get_subplugin()->get_configs('thisuser', 'thiscontext', $default->id);
        if (count($configs)) {

            $name = 'keysownedbyme';
            $this->add_heading($mform, $name, $this->subpluginname, true);

            $actions = ['edit', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/
        
        $this->add_heading($mform, $mainheading, $this->subpluginname, true);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'chatgpturl';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'chatgptkey';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'chatgptmodel';
        $options = ['gpt-3-turbo' => 'gpt-3-turbo', 'gpt-4' => 'gpt-4'];
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'sharingcontext';
        $options = $this->get_sharingcontext_options();
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->contextlevel);

        // Shared from/until date are both optional.
        $params = ['optional' => true];

        // Shared from date and time (default is start of today).
        $name = 'sharedfrom';
        $params['defaulttime'] = $default->$name;
        $this->add_field_datetime($mform, $name, $params);

        // Shared until date and time (default is end of today).
        $name = 'shareduntil';
        $params['defaulttime'] = $default->$name;
        $this->add_field_datetime($mform, $name, $params);

        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * validation
     *
     * @uses $USER
     * @param stdClass $data submitted from the form
     * @param array $files
     * @return xxx
     * @todo Finish documenting this function
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $names = ['chatgpturl', 'chatgptkey', 'chatgptmodel'];
        foreach ($names as $name) {
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('addmissingvalue');
            }
        }

        return $errors;
    }

    /**
     * Format config settings for a ChatGPT key.
     *
     * $param object $config
     * @return array of availability options [contextlevel => availability description]
     */
    public function format_config($config, $actions=[], $showowner=false, $showownerpic=false) {
        global $DB, $OUTPUT, $PAGE, $USER;

        $html = '';

        // Cache id value. It is used to make field names unique.
        $id = $config->id;

        // Cache often-used items.
        $labelsep = get_string('labelsep', 'langconfig');
        $dl = ['class' => 'row my-0 mx-0'];
        $dt = ['class' => 'col-6 col-sm-4 col-md-3 col-xl-2 my-1 mx-0'];
        $dd = ['class' => 'col-6 col-sm-8 col-md-9 col-xl-10 my-1 mx-0'];

        // Format the key to show only the 1st 4 chars and the final 4 chars..
        $name = 'chatgptkey';
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = substr($value, 0, 4).' ... '.substr($value, -4);
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        // Format the owner's name.
        if ($showowner && isset($config->owneruserid)) {
            $name = 'owner';
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $user = $DB->get_record('user', ['id' => $config->owneruserid]);
            $value = fullname($user);
            if ($showownerpic) {
                $value = $OUTPUT->user_picture($user).' '.$value;
            }
            $url = new \moodle_url('/user/profile.php', ['id' => $user->id]);
            $value = $OUTPUT->action_link($url, $value, new \component_action(
                // "this" is actually a Y_node, so we could use the "set" method,
                // but "setAttribute" is compatible with normal DOM, so use that.
                'click', 'function(){this.setAttribute("target", "CHATGPT")}'
            ));
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        $name = 'chatgptmodel';
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

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


        // Extract the sharing context id and level.
        $contextid = (empty($config->contextid) ? 0 : $config->contextid);
        $contextlevel = (empty($config->contextlevel) ? 0 : $config->contextlevel);

        // Get a descriptor for the sharing context.
        switch (true) {

            case ($contextid > 0):
                $context = \context::instance_by_id($contextid);
                $sharingcontext = $context->get_context_name();
                break;

            case ($contextlevel == CONTEXT_MODULE):
                $sharingcontext = $this->get_string('sharedinvocabcontext');
                break;

            case ($contextlevel == CONTEXT_COURSE):
                $sharingcontext = $this->get_string('sharedincoursecontext');
                break;

            case ($contextlevel == CONTEXT_COURSECAT):
                $sharingcontext = $this->get_string('sharedincoursecatcontext');
                break;

            case ($contextlevel == CONTEXT_SYSTEM):
                $sharingcontext = $this->get_string('sharedinsystemcontext');
                break;

            default:
                $sharingcontext = $this->get_string('sharedinunknowncontext', $config->contextlevel);
        }

        // Format the sharing context (= context name or level).
        if ($sharingcontext) {
            $label = $this->get_string('sharingcontext').$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = \html_writer::tag('dd', $sharingcontext, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        if ($html) {
            // Convert actions to links that look like buttons.
            foreach ($actions as $i => $action) {
                $url = $PAGE->url;
                $url->param('action', $action);
                $url->param('cid', $config->id);
                switch ($action) {
                    case 'edit':
                        $btncolor = 'btn-success';
                        break;
                    case 'delete':
                        $btncolor = 'btn-danger';
                        break;
                    case 'copy':
                        $btncolor = 'btn-dark';
                        break;
                    default:
                        // This shouldn't happen !!
                        $btncolor = 'btn-light';
                }
                // ToDo: convert this to ...
                // $text = $this->get_string('confirm'.$action.'key');
                // $actionlabel = $this->get_string($action);
                // $cancellabel = get_string('cancel);
                // $action = new \confirm_action($text, $callback, $actionlabel, $cancellabel);
                // $actions[$i] = $OUTPUT->action_link($url, $text, $action);
                $actions[$i] = \html_writer::link(
                    $url,
                    $this->get_string($action),
                    ['class' => "btn $btncolor"]
                );
            }
            if ($actions = implode(' ', $actions)) {
                $label = \html_writer::tag('dt', '', $dt);
                $value = \html_writer::tag('dd', $actions, $dd);
                $html .= \html_writer::tag('dl', $label.$value, $dl);
            }

            $params = ['class' => 'configinfo'];
            $html = \html_writer::tag('div', $html, $params);
        }

        return $html;
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
}
