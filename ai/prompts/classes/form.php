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
 * ai/prompts/classes/form.php
 *
 * @package    vocabai_prompts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_prompts;

/**
 * Main settings form for a ChatGPT AI assistant subplugin.
 *
 * @package    vocabai_prompts
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
        global $DB, $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // The sort field used to sort configs by alphabetically.
        $sortfield = 'promptname';

        // If any of this user's configs are found below,
        // export will be enabled.
        $enableexport = false;

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {
            $enableexport = true;

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

            $mainheading = 'editprompt';
            $submitlabel = get_string('save');

        } else {

            $mainheading = 'addnewprompt';
            $submitlabel = get_string('add');

            // Get current year, month and day.
            list($year, $month, $day) = explode(' ', date('Y m d'));

            // Define default values for new prompt.
            $default = (object)[
                'id' => 0,
                'promptname' => '',
                'prompttext' => '',
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
        }

        // By default, the "Add a new prompt" section is expanded,
        // but if other usable keys exist it will be collapsed.
        $expanded = true;

        // Display the config settings that apply to this context and are
        // owned by other users. These are NOT editable by the current user.
        $configs = $this->get_subplugin()->get_configs('otherusers', 'thiscontext', $default->id, $sortfield);
        if (count($configs)) {

            // Collapse the section to add a new key.
            $expanded = false;

            $name = 'promptsownedbyothers';
            $this->add_heading($mform, $name, true);

            if (is_siteadmin()) {
                // Site admin can always edit, copy and delete anything.
                $actions = ['edit', 'copy', 'delete'];
            } else {
                $actions = [];

                // Display message to non-admin users (e.g. teachers)
                // explaining that they cannot edit prompts owned by other people.
                $text = $this->get_string('note');
                $text = \html_writer::tag('span', $text, ['class' => 'text-danger']);
                $text = $text.get_string('labelsep', 'langconfifg');
                $text = $text.$this->get_string('cannoteditprompts');
                $text = \html_writer::tag('h5', $text, ['class' => 'cannotedit']);
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
        $configs = $this->get_subplugin()->get_configs('thisuser', 'othercontexts', $default->id, $sortfield);
        if (count($configs)) {
            $enableexport = true;

            $name = 'otherpromptsownedbyme';
            $this->add_heading($mform, $name, true);

            $actions = ['edit', 'copy', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        // Display the config settings that owned by this user and apply to
        // the current context. These are editable by the current user.
        $configs = $this->get_subplugin()->get_configs('thisuser', 'thiscontext', $default->id, $sortfield);
        if (count($configs)) {
            $enableexport = true;

            // Collapse the section to add a new key.
            $expanded = false;

            $name = 'promptsownedbyme';
            $this->add_heading($mform, $name, true);

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

        // Note, a section cannot be collapsed if it contains required fields.
        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'promptname';
        $this->add_field_text($mform, $name, PARAM_TEXT, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $name = 'prompttext';
        $this->add_field_textarea($mform, $name, PARAM_TEXT, $default->$name, ['rows' => '5', 'cols' => 40]);
        $mform->addRule($name, $addmissingvalue, 'required', null, 'client');

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }

        $PAGE->requires->js_call_amd('vocabai_prompts/form', 'init');
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

        $names = ['prompt'];
        foreach ($names as $name) {
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('addmissingvalue');
            }
        }

        return $errors;
    }

    /**
     * Format config settings for a prompt.
     *
     * @param object $config
     * @param array $actions (optional, default=[])
     * @param bool $showowner (optional, default=false)
     * @param bool $showownerpic (optional, default=false)
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
        $name = 'promptname';
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = \html_writer::tag('dd', $value, $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        // Format the promptp text to show only the 1st 20 chars and the final 20 chars.
        $name = 'prompttext';
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = substr($value, 0, 20).' ... '.substr($value, -20);
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
                // Since "this" is actually a Y_node, we could use the "set" method,
                // but "setAttribute" is compatible with normal DOM, so we use that.
                'click', 'function(){this.setAttribute("target", "CHATGPT")}'
            ));
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
                // $actions[$i] = $OUTPUT->action_link($url, $text, $action); !!
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
