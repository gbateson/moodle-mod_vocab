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
 * Internal library of functions for the Vocabulary activity module
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

/**
 * \mod_vocab\aiform
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
abstract class aiform extends \mod_vocab\subpluginform {

    /** var string the name of the "name" field from the config record */
    const CONFIG_NAME = '';

    /** var string the name of the "text" field in the config record */
    const CONFIG_TEXT = '';

    /** var string the name of the "key" field in the config record */
    const CONFIG_KEY = '';

    /** var string the name of the "model" field in the config record */
    const CONFIG_MODEL = '';

    /** var string a comma-delimited list of required fields */
    const REQUIRED_FIELDS = '';

    /**
     * add_configs
     *
     * @param object $mform
     * @param object $default values
     * @param string $types
     */
    public function add_configs($mform, $default, $types='keys') {

        // By default, the "Add a new xxx" section is expanded,
        // but if other usable keys exist it will be collapsed.
        $expanded = true;

        // Display the config settings that apply to this context and are
        // owned by other users. These are NOT editable by the current user.
        $configs = $this->get_subplugin()->get_configs('otherusers', 'thiscontext', $default->id);
        if (count($configs)) {

            // Collapse the section to add a new key.
            $expanded = false;

            $name = $types.'ownedbyothers';
            $this->add_heading($mform, $name, true);

            if (is_siteadmin()) {
                // Site admin can always edit, copy and delete anything.
                $actions = ['edit', 'copy', 'delete'];
            } else {
                $actions = [];

                // Display message to non-admin users (e.g. teachers)
                // explaining that they cannot edit keys owned by other people.
                $text = $this->get_string('note');
                $text = \html_writer::tag('span', $text, ['class' => 'text-danger']);
                $text = $text.$labelsep.$this->get_string('cannoteditkeys');
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
        $configs = $this->get_subplugin()->get_configs('thisuser', 'othercontexts', $default->id);
        if (count($configs)) {
            $enableexport = true;

            $name = 'other'.$types.'ownedbyme';
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
        $configs = $this->get_subplugin()->get_configs('thisuser', 'thiscontext', $default->id);
        if (count($configs)) {
            $enableexport = true;

            // Collapse the section to add a new key.
            $expanded = false;

            $name = $types.'ownedbyme';
            $this->add_heading($mform, $name, true);

            $actions = ['edit', 'delete'];
            foreach ($configs as $configid => $config) {
                if ($html = $this->format_config($config, $actions)) {
                    $mform->addElement('html', $html, "config-$configid");
                }
            }
        }

        return $expanded;
    }

    /**
     * Format config settings for an AI sunplugin.
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

        // Show the full name field, if any.
        $name = static::CONFIG_NAME;
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = \html_writer::tag('dd', htmlspecialchars($value, ENT_COMPAT), $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        // Format the text field, if any, to show only the 1st 20 chars and the final 20 chars.
        $name = static::CONFIG_TEXT;
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = \core_text::substr($value, 0, 20).' ... '.\core_text::substr($value, -20);
            $value = \html_writer::tag('dd', htmlspecialchars($value, ENT_COMPAT), $dd);
            $html .= \html_writer::tag('dl', $label.$value, $dl);
        }

        // Format the key field to show only the 1st 4 chars and the final 4 chars..
        $name = static::CONFIG_KEY;
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = \core_text::substr($value, 0, 4).' ... '.\core_text::substr($value, -4);
            $value = \html_writer::tag('dd', htmlspecialchars($value, ENT_COMPAT), $dd);
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

        // Show the full model field, if any.
        // e.g. chatgptmodel.
        $name = static::CONFIG_MODEL;
        if (isset($config->$name)) {
            $label = $this->get_string($name).$labelsep;
            $label = \html_writer::tag('dt', $label, $dt);
            $value = $config->$name;
            $value = $this->append_tuning_file($name, $value, $config);
            $value = \html_writer::tag('dd', htmlspecialchars($value, ENT_COMPAT), $dd);
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

            case ($contextlevel == CONTEXT_USER):
                $sharingcontext = $this->get_string('sharedinusercontext');
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
                    \core_text::strtotitle($this->get_string($action)),
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
     * Append tuning file name to model name.
     *
     * @param string $name of "model" field
     * @param string $value of "model" field
     * @param object $config settings for the current config record
     * @return string
     */
    public function append_tuning_file($name, $value, $config) {
        global $DB;
        $select = 'vc.id';
        $from   = '{vocab_config} vc, {vocab_config_settings} vcs';
        $where = 'vc.contextid = ? AND vc.subplugin = ? AND vc.id = vcs.configid';
        $where = "$where AND vcs.name = ? AND ".$DB->sql_like('value', '?');
        $params = [$config->contextid, 'vocabai_files', $name.'id', '%'.$value.'%'];
        if ($fileconfigid = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
            $params = ['configid' => $fileconfigid];
            if ($settings = $DB->get_records_menu('vocab_config_settings', $params, 'name', 'name,value')) {
                $params = ['model' => $value, 'file' => $settings['filedescription']];
                $value = $this->get_string('modeltunedbyfile', (object)$params);
            }
        }
        return $value;
    }

    /**
     * Add a filepicker or filemanager field to the given $mform.
     *
     * @param string $type either "filemanager" or "filepicker"
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void (but will update $mform)
     */
    public function add_field_file($type, $mform, $name, $attributes=null, $options=null) {

        // Add file picker/manager in the normal way.
        parent::add_field_file($type, $mform, $name, $attributes, $options);

        // Fetch previously existing file, if any.
        if ($config = $this->get_subplugin()->config) {
            $draftitemid = 0;
            file_prepare_draft_area(
                // The file saved in the specified filearea with the specified $itemid
                // will be copied to the draft filearea with returned $draftitemid.
                // We use the field name as the name of the fielarea (e.g. promptfile).
                $draftitemid, $config->contextid, $this->subpluginname, $name, $config->id
                // When "file_prepare_draft_area()" is called with draftitemid (the first argument)
                // set to 0 or null, then it will be assigned automatically, and the files
                // for this filearea will be transferred automatically, which is what we want.
            );
            if ($draftitemid) {
                $mform->setDefault($name, $draftitemid);
            }
        }
    }

    /**
     * Add sharing fields: context, sharedfrom shareduntil.
     *
     * @param moodleform $mform representing the Moodle form
     * @param array $default
     * @return void (but will update $mform)
     */
    public function add_sharing_fields($mform, $default) {

        $name = 'sharing';
        $this->add_heading($mform, $name, $this->get_vocab()->plugin, true);

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
    }

    /**
     * Get a list of availability options for a DALL-E key.
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

                case CONTEXT_USER:
                    $options[$level] = $this->get_string('sharedinusercontext');
                    break;

                default:
                    // Unknown context level - shouldn't happen !!
                    unset($options[$level]);
            }
        }
        return $options;
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
        foreach (explode(',', static::REQUIRED_FIELDS) as $name) {
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('addmissingvalue');
            }
        }

        return $errors;
    }
}
