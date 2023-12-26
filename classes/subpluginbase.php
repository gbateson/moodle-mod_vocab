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
 * Internal library for the Vocabulary module.
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

defined('MOODLE_INTERNAL') || die();

/**
 * This base class for subplugins of the mod_vocab plugin.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class subpluginbase {

    /** The folder containing subplugins of this type e.g. tool */
    const SUBPLUGINTYPE = '';

    /** The name of the subplugin e.g. import */
    const SUBPLUGINNAME = '';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [];

    /**
     * The full frankenstyle name of this subplugin
     * e.g. vocabtool_import. This will be set automatically
     * from the SUBPLUGINTYPE and SUBPLUGINNAME.
     * It is intended for use in the get_string() method.
     */
    public $plugin = '';

    /**
     * the path to subplugin folder relative to $CFG->dirroot
     * e.g. mod/vocab/tool/import. This will be set automatically
     * from the SUBPLUGINTYPE and SUBPLUGINNAME.
     */
    public $pluginpath = '';

    /** @var object the parent vocab object */
    public $vocab = null;

    /** @var object containing arrays of configs */
    public $configs = null;

    /** @var object the config settings object */
    public $config = null;

    /** @var string the optional action to be performed on the $config settings object */
    public $action = '';

    /**
     * __construct
     *
     * @uses $USER
     * @todo Finish documenting this function
     */
    public function __construct() {
        global $USER;

        $this->vocab = \mod_vocab\activity::create();
        $this->plugin = 'vocab'.static::SUBPLUGINTYPE.'_'.static::SUBPLUGINNAME;
        $this->pluginpath = $this->vocab->pluginpath.'/'.static::SUBPLUGINTYPE.'/'.static::SUBPLUGINNAME;

        if ($configid = self::optional_param(['c', 'cid', 'configid'], 0, PARAM_INT)) {

            // Try to get the config with the required id.
            if ($config = $this->find_config($configid)) {

                // Assume the current user CANNOT access these config settings.
                $can = false;

                // Get the action (use, edit, copy, delete) and check
                // this user can do this action to this config record.
                $action = self::optional_param(['a', 'action'], 'use', PARAM_ALPHA);
                if (in_array($action, ['use', 'edit', 'copy', 'delete'])) {

                    // Site admin and owner always have full access to these settings.
                    if (is_siteadmin() || $config->owneruserid == $USER->id) {
                        $can = true;
                    } else if ($action == 'use') {
                        // User is not the owner, but can "use" the settings,
                        // if they have at least "view" capability in the given context.
                        $context = context::instance_by_id($config->contextid);
                        $can = has_capability('mod/vocab:view', $context);
                    }
                }

                if ($can) {
                    $this->config = $config;
                    $this->action = $action;
                }
            }
        }
    }

    /**
     * Return the value of an optional script parameter.
     *
     * @param array $names of possible names for the input parameter
     * @param mixed $default value
     * @param mixed $type a PARAM_xxx constant value
     * @return mixed, either an actual value from the form, or a suitable default.
     */
    public static function optional_param($names, $default, $type) {
        return \mod_vocab\activity::optional_param($names, $default, $type);
    }

    /**
     * create
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public static function create() {
        $class = get_called_class();
        return new $class();
    }

    /**
     * Creates a url for this subplugin
     *
     * @param string $filepath
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return xxx
     * @todo Finish documenting this function
     */
    public function url($filepath, $escaped=null, $params=[]) {
        if ($this->vocab && $this->vocab->cm) {
            $params['id'] = $this->vocab->cm->id;
        }
        $url = '/'.$this->pluginpath.'/'.$filepath;
        $url = new \moodle_url($url, $params);
        if (is_bool($escaped)) {
            $url = $url->out($escaped);
        }
        return $url;
    }

    /**
     * Creates a url for this tool
     *
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string the URL of the index file for this plugin
     */
    public function index_url($escaped=null, $params=[]) {
        return $this->url('index.php', $escaped, $params);
    }

    /**
     * Get the URL of the main page of this Moodle site.
     *
     * @return string the URL of the top page of this Moodle site
     */
    public function site_url() {
        return new \moodle_url('/');
    }

    /**
     * Get a new Moodle form object through which we can interact with a user.
     *
     * @return object to represent a Moodle form (derived from "moodleform").
     */
    public function get_mform() {
        global $PAGE;
        $mform = "\\$this->plugin\\form";
        $params = ['subplugin' => $this];
        return new $mform($PAGE->url->out(), $params);
    }

    /*
     * Get a string for this vocabtool plugin
     *
     * @param string $name the name of the required string
     * @param mixed $a (optional, default=null) additional value or values required for the string
     * @return string
     **/
    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }

    public function get_settingnames() {
        return static::SETTINGNAMES;
    }

    /**
     * Get a config settings relevant to this context and user.
     * If the optional "configid" parameter is set,
     * then only settings for that configid will be returned
     *
     * @uses $DB
     * @uses $USER
     * @param array $contexts of context ids that are relevant to the current vocab activity
     * @param integer $configid (optional, default = 0) a specific configid
     * @return mixed array of records from "vocab_config_settings", or FALSE if there are none.
     */
    public function get_config_settings($contexts, $configid=0) {
        global $DB, $USER;

        $select = 'vcs.id, vcs.name, vcs.value, vcs.configid, '.
                  'vc.owneruserid, vc.contextid, '.
                  'ctx.contextlevel';

        $from = '{vocab_config_settings} vcs '.
                'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id '.
                'JOIN {context} ctx ON vc.contextid = ctx.id';

        list($where, $params) = $DB->get_in_or_equal($contexts);

        // We're interested in config settings for this subplugin
        // that are shared in this context or any parent context.
        // We also want other config settings owned by the current user.
        $where = "vc.subplugin = ? AND (vc.owneruserid = ? OR vc.contextid $where)";
        $params = array_merge([$this->plugin, $USER->id], $params);

        // Limit results to a specific configid.
        if ($configid) {
            $where .= ' AND vcs.configid = ?';
            $params[] = $configid;
        }

        // Sort by owner, context level and configid.
        $sort = 'vc.owneruserid, ctx.contextlevel, vcs.configid';

        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $sort";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get config settings relevant to this context and user.
     *
     * @param string $returnuser (optional, default='') Either "otherusers" or "thisuser"
     * @param string $returncontext (optional, default='') Either "othercontexts" or "thiscontext"
     * @return array
     */
    public function get_configs($returnuser='', $returncontext='', $removeconfigid=false) {
        global $USER;

        // Setup the configs object (1st time only).
        if ($this->configs === null) {

            // Config settings owned by OTHER USERS, but
            // SHARED with this mod, course, coursecat or site.
            $currentcontexts = [];

            // Config settings owned by the CURRENT USER, but
            // NOT SHARED with this mod, course, coursecat or site.
            $myothercontexts = [];

            // Config settings owned by the the CURRENT USER, and
            // SHARED with this mod, course, coursecat or site.
            $mycurrentcontexts = [];

            // Current config record.
            $config = null;

            $contexts = $this->vocab->get_readable_contexts('', 'id');
            if ($settings = $this->get_config_settings($contexts)) {

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
                                $mycurrentcontexts[$config->id] = $config;
                            } else {
                                // A config that is owned by the current user,
                                // but is for an unrelated context.
                                $myothercontexts[$config->id] = $config;
                            }
                        } else {
                            // A config that is relevant to the current
                            // context but owned by a another user.
                            $currentcontexts[$config->id] = $config;
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

            $this->configs = (object)[
                'otherusers' => (object)[
                    'othercontexts' => [],
                    'thiscontext' => $currentcontexts,
                ],
                'thisuser' => (object)[
                    'othercontexts' => $myothercontexts,
                    'thiscontext' => $mycurrentcontexts,
                ]
            ];
        }

        $configs = $this->configs;
        if ($returnuser && $returncontext) {
            // Return configs for a specific user and context.
            $configs = $configs->$returnuser->$returncontext;
            if ($removeconfigid && $this->config) {
                unset($configs[$this->config->id]);
            }
        }
        return $configs;
    }

    /**
     * Find the config with the given id.
     *
     * @param integer The id of the required config record.
     * @return object The required config record, or NULL if it is not found.
     */
    public function find_config($configid) {

        $configs = $this->get_configs('otherusers', 'thiscontext');
        if (array_key_exists($configid, $configs)) {
            return $configs[$configid];
        }

        $configs = $this->get_configs('thisuser', 'othercontexts');
        if (array_key_exists($configid, $configs)) {
            return $configs[$configid];
        }

        $configs = $this->get_configs('thisuser', 'thiscontext');
        if (array_key_exists($configid, $configs)) {
            return $configs[$configid];
        }

        // Required could not be found - unexpected!
        return null;
    }

    /**
     * Save config settings.
     *
     * @uses $DB
     * @uses $USER
     * @param object $settings the form data containing the settings
     * @param integer $contextid (optional, default = 0) a specific contextid
     * @return integer if settings could be found/added the configid; otherwise 0.
     */
    public function save_config_settings($settings, $contextid=0, $contextlevel=0) {
        global $DB, $USER;

        if ($contextlevel == 0 && isset($settings->contextlevel)) {
            $contextlevel = $settings->contextlevel;
        }
        if ($contextid == 0 && isset($settings->contextid)) {
            $contextid = $settings->contextid;
        }
        if ($contextid == 0 || $contextlevel == 0) {
            // Generate some error?
            return 0;
        }

        // Get or create the config record.
        $table = 'vocab_config';


        if ($this->config) {
            $config = $this->config;
            $config->contextid = $contextid;
            $config->contextlevel = $contextlevel;
            $DB->update_record($table, $config);
        } else {
            $params = [
                'owneruserid' => $USER->id,
                'contextid' => $contextid,
                'subplugin' => $this->plugin,
            ];
            $config = $DB->get_record($table, $params);
            if (empty($config)) {
                // Config record does not exist, so create it.
                $params['id'] = $DB->insert_record($table, $params);
                $config = (object)$params;
            }
        }

        // Add or update the settings for this config record.
        $table = 'vocab_config_settings';
        foreach (static::SETTINGNAMES as $name) {

            $params = [
                'configid' => $config->id,
                'name' => $name,
            ];
            if (empty($settings->$name)) {
                // Remove previous value, if there was one.
                if ($DB->record_exists($table, $params)) {
                    $DB->delete_records($table, $params);
                }
            } else {
                $value = $settings->$name;
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
                $config->$name = $value;
            }
        }

        // Update the current config object.
        if ($this->config) {
            $this->config = $config;
        }

        return $config->id;
    }

    /**
     * save the config settings form the input form.
     */
    public function save_config($data) {

        // Make sure we have a at least a primary field (e.g. "chatgptkey" or "prompt").
        $name = static::SETTINGNAMES[0];
        if (isset($data->$name) && $data->$name) {

            $contexts = $this->vocab->get_writeable_contexts('contextlevel', 'id');

            $name = 'sharingcontext';
            if (isset($data->$name) && isset($contexts[$data->$name])) {
                $contextlevel = $data->$name;
                $contextid = $contexts[$contextlevel];
            } else if ($this->vocab->cm) {
                // Shouldn't happen, but we can continue.
                $contextlevel = CONTEXT_MODULE;
                $contextid = $this->vocab->context->id;
            } else {
                // Definitely shouldn't happen !!
                $contextlevel = 0;
                $contextid = 0;
            }

            if (isset($data->$name)) {
                unset($data->$name);
            }

            if ($contextlevel && $contextid) {
                $this->save_config_settings($data, $contextid, $contextlevel);
            }
        }
    }

    /**
     * unset_elements
     *
     * @todo Finish documenting this function
     */
    public function unset_form_elements($data) {
        foreach ($this->get_settingnames() as $name) {
            if (isset($data->$name)) {
                $this->unset_element($name);
            }
        }
    }

    /**
     * unset_element
     *
     * @param string $name
     * @todo Finish documenting this function
     */
    public function unset_element($name) {
        if (isset($_GET[$name])) {
            unset($_GET[$name]);
        }
        if (isset($_POST[$name])) {
            unset($_POST[$name]);
        }
    }

    public function delete_config() {
        global $DB;

        // Sanity check on the config object.
        if (empty($this->config)) {
            return false;
        }

        // Delete the config settings.
        $DB->delete_records('vocab_config_settings', [
            'configid' => $this->config->id,
        ]);

        // Delete the config record.
        $DB->delete_records('vocab_config', [
            'id' => $this->config->id,
        ]);

        // Remove the correct config object.
        $this->config = null;

        return true;
    }

    public function copy_config() {
        global $DB, $USER;

        // Clone the config current config record.
        $newconfig = clone($this->config);
        unset($newconfig->id);

        // Fetch the writeable contexts for the current user.
        $contexts = $this->vocab->get_writeable_contexts('contextlevel', 'id');

        // If the user cannot create config settings in the target context,
        // switch to the highest context that this user does have access to.
        if (empty($contexts[$newconfig->contextlevel])) {
            $newconfig->contextlevel = key($contexts);
        }

        // if the contextid/level and user are the same - don't do anything.
        if ($newconfig->contextid == $contexts[$newconfig->contextlevel]) {
            if ($newconfig->owneruserid == $USER->id) {
                return $this->config->id;
            }
        }

        // We have a new context and/or user, create a new config record
        // with the clone of the current config settings.
        $newconfig->contextid = $contexts[$newconfig->contextlevel];
        $newconfig->owneruserid = $USER->id;
        return $this->save_config_settings($newconfig);
    }
}
