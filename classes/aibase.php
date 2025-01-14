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
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

/**
 * The base class for "ai" subplugins of the mod_vocab plugin.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class aibase extends \mod_vocab\subpluginbase {

    /** the folder containing subplugins of this type */
    const SUBPLUGINTYPE = 'ai';

    /** the name of this AI assistant e.g. chatgpt */
    const SUBPLUGINNAME = '';

    /** @var array the names of config settings that this subplugin maintains. */
    const SETTINGNAMES = [];

    /** @var array the names of date settings that this subplugin maintains. */
    const DATESETTINGNAMES = [];

    /** @var array the names of file settings that this subplugin maintains. */
    const FILESETTINGNAMES = [];

    /** @var array the names of file settings that can be exported. */
    const EXPORTSETTINGNAMES = [];

    /**
     * @var string
     * The AI type for subplugins such as "prompts", "formats",
     * and "files" that provide input for other AI plugins.
     */
    const SUBTYPE_INPUT = 'input';

    /** @var string the AI type for subplugins that generate "text" (e.g. chatgpt) */
    const SUBTYPE_TEXT = 'text';

    /** @var string the AI type for subplugins that generate "image" (e.g. dalle, midjourney) */
    const SUBTYPE_IMAGE = 'image';

    /** @var string the AI type for subplugins that generate "audio" (e.g. openai-tts) */
    const SUBTYPE_AUDIO = 'audio';

    /** @var string the AI type for subplugins that generate "video" (e.g. vyond) */
    const SUBTYPE_VIDEO = 'video';

    /** @var bool enable or disable trace and debugging messages during development. */
    const DEBUG = false;

    /** @var string used to denote that an adhoc task should be rescheduled. */
    const RESCHEDULE_ADHOC_TASK = 'reschedule-adhoc-task';

    /**
     * @var bool to signify whether or not duplicate records,
     * i.e. records with the same owner and context, are allowed.
     */
    const ALLOW_DUPLICATES = false;

    /** @var string the name of the field used to sort config records. */
    const CONFIG_SORTFIELD = '';

    /**
     * @var string containing type of this AI subplugin
     * (see SUBTYPE_XXX constants above)
     */
    public $subtype = '';

    /** @var string an import/export operation on multiple config records */
    public $fileoperation = '';

    /** @var object containing arrays of configs */
    public $configs = null;

    /** @var object the config settings object */
    public $config = null;

    /** @var string an action requested on a single config record */
    public $action = '';

    /** @var object to represent a curl object used for connecting to an AI assistant */
    public $curl = null;

    /** @var int the number of requests to send in a single curl request */
    public $curlcount = 1;

    /** @var array of POST parameters to be sent via the curl object */
    public $postparams = null;

    /** @var bool flag to denote whether or not we are using a tuning file for this AI assistant. */
    protected $usetuningfile = null;

    /**
     * __construct
     *
     * @param mixed $vocabinstanceorid (optional, default=null) is a vocab instance or id
     * @return void, but will initialize this object instance
     */
    public function __construct($vocabinstanceorid=null) {

        // Set vocab, plugin and pluginpath.
        parent::__construct($vocabinstanceorid);

        // Check for import/export fileoperation.
        foreach (['import', 'export'] as $name) {
            $groupname = $name.'fileelements';
            if ($group = self::get_optional_param($groupname, null, PARAM_TEXT)) {
                $buttonname = $name.'filebutton';
                if (is_array($group) && array_key_exists($buttonname, $group)) {
                    $this->fileoperation = $name;
                }
            }
        }

        // Set config and get optional edit/copy/delete action.
        if ($configid = self::get_optional_param(['c', 'cid', 'configid'], 0, PARAM_INT)) {

            // Try to get the config with the required id.
            if ($config = $this->find_config($configid)) {

                // Assume the current user CANNOT access these config settings.
                $can = false;

                // Get the action (use, edit, copy, delete) and check
                // this user can do this action to this config record.
                $action = self::get_optional_param(['a', 'action'], 'use', PARAM_ALPHA);
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
     * Get the array containing the names of all the config settings for this subplugin.
     */
    public static function get_settingnames() {
        return static::SETTINGNAMES;
    }

    /**
     * Is the given setting $name a date setting?
     *
     * @param string $name the name of the setting to be checked.
     * @return bool TRUE if the $name is that of a date settings; otherwise FALSE
     */
    public static function is_date_setting($name) {
        return in_array($name, static::DATESETTINGNAMES);
    }

    /**
     * Is the given setting $name a file setting?
     *
     * @param string $name the name of the setting to be checked.
     * @return bool TRUE if the $name is that of a file settings; otherwise FALSE
     */
    public static function is_file_setting($name) {
        return in_array($name, static::FILESETTINGNAMES);
    }

    /**
     * Get the data value of an array containing date fields,
     * such as those returned from a date field in a Moodle form.
     *
     * @param array $value time and date values to be converted to a time stamp.
     * @return int a time/date stamp
     */
    public function get_date_value($value) {
        if (is_array($value)) {
            if (empty($value['enabled'])) {
                return 0;
            }
            $hour = (int)($value['hour'] ?? 0);
            $minute = (int)($value['minute'] ?? 0);
            $second = (int)($value['second'] ?? 0);
            $month = (int)($value['month'] ?? 0);
            $day = (int)($value['day'] ?? 0);
            $year = (int)($value['year'] ?? 0);
            return mktime($hour, $minute, $second, $month, $day, $year);
        }
        // Not an array - unexpected !!
        return $value;
    }

    /**
     * Get a config settings relevant to this context and user.
     * If the optional "configid" parameter is set,
     * then only settings for that configid will be returned
     *
     * @uses $DB
     * @uses $USER
     * @param array $contexts of context ids that are relevant to the current vocab activity
     * @param mixed $subplugins string or array of specific plugin names (default=null)
     * @param integer $configid (optional, default = 0) a specific configid
     * @param mixed $user (optional, default = null) an optional user id or record
     * @return mixed array of records from "vocab_config_settings", or FALSE if there are none.
     */
    public function get_config_settings($contexts, $subplugins=null, $configid=0, $user=null) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        } else if (is_scalar($user)) {
            $user = $DB->get_record('user', ['id' => $user]);
        }

        $select = 'vcs.id, vcs.name, vcs.value, vcs.configid, '.
                  'vc.subplugin, vc.owneruserid, vc.contextid, '.
                  'ctx.contextlevel';

        $from = '{vocab_config_settings} vcs '.
                'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id '.
                'JOIN {context} ctx ON vc.contextid = ctx.id';

        list($where, $params) = $DB->get_in_or_equal($contexts);

        // We're interested in config settings for this subplugin
        // that are shared in this context or any parent context.
        // We also want other config settings owned by the current user.
        $where = "(vc.owneruserid = ? OR vc.contextid $where)";
        $params = array_merge([$user->id], $params);

        // Limit results to specific plugins.
        if ($subplugins) {
            if (is_scalar($subplugins)) {
                $subplugins = explode(',', $subplugins);
                $subplugins = array_map('trim', $subplugins);
                $subplugins = array_filter($subplugins);
            }
            list($w, $p) = $DB->get_in_or_equal($subplugins);
            $where = "vc.subplugin $w AND $where";
            $params = array_merge($p, $params);
        }

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
     * @param bool $removeconfigid (optional, default=false)
     * @param mixed $subplugins string or array of specific plugin names (default=null)
     * @param string $sortfield (optional, default='')
     * @return array
     */
    public function get_configs($returnuser='', $returncontext='', $removeconfigid=false, $subplugins=null, $sortfield='') {
        global $USER;

        // When exporting, $subplugins will be an array
        // but usually it is null, in which case we set
        // it to be the name of the (sub)plugin.
        if ($subplugins === null) {
            $subplugins = $this->plugin;
        }

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
            if ($settings = $this->get_config_settings($contexts, $subplugins)) {

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
                            // The value of $config->id has not changed.
                            $storeconfig = false;
                            break;

                        default:
                            // The value of $config->id has changed.
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
                                'subplugin' => $setting->subplugin,
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
                ],
            ];
        }

        // Prepare configs to return.
        $configs = clone($this->configs);

        // Return configs for a specific user.
        if ($returnuser) {
            $configs = $configs->$returnuser;

            // Return configs for a specific context.
            if ($returncontext) {
                $configs = $configs->$returncontext;

                // Remove current config, if there is one.
                if ($removeconfigid && $this->config) {
                    unset($configs[$this->config->id]);
                }
            }
        }

        if (is_string($subplugins) && $sortfield == '') {
            $sortfield = static::CONFIG_SORTFIELD;
        }
        if ($sortfield) {
            if (is_array($configs)) {
                uasort($configs, function($a, $b) use ($sortfield) {
                    return $this->uasort_configs($a, $b, $sortfield);
                });
            } else if (is_object($configs)) {
                foreach ($configs as $name => $value) {
                    if (is_array($value)) {
                        uasort($value, function($a, $b) use ($sortfield) {
                            return $this->uasort_configs($a, $b, $sortfield);
                        });
                        $configs->$name = $value;
                    }
                }
            }
        }

        return $configs;
    }

    /**
     * uasort_configs
     *
     * @param object $a
     * @param object $b
     * @param string $sortfield
     * @return integer
     */
    public function uasort_configs($a, $b, $sortfield) {
        if ($a->$sortfield < $b->$sortfield) {
            return -1;
        }
        if ($a->$sortfield > $b->$sortfield) {
            return 1;
        }
        return 0;
    }

    /**
     * Find the config with the given id.
     *
     * @param integer $configid The id of the required config record.
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

        // Required $configid could not be found - unexpected!
        return null;
    }

    /**
     * Save config settings.
     *
     * @uses $DB
     * @uses $USER
     * @param object $settings the form data containing the settings
     * @param integer $contextid (optional, default = 0) a specific contextid
     * @param integer $contextlevel (optional, default = 0) a specific context level
     * @return int if settings could be found/added the configid; otherwise 0.
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
            // Prompts and formats allow duplicates
            // but keys (e.g. ChatGPT) do not.
            if (static::ALLOW_DUPLICATES) {
                $config = false;
            } else {
                $config = $DB->get_record($table, $params);
            }
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

                // Special processing for date and file fields.
                switch (true) {

                    case self::is_date_setting($name):
                        $value = $this->get_date_value($value);
                        break;

                    case self::is_file_setting($name):
                        // Copy the file to the file area for this field
                        // using the config id as the "itemid" for the file.
                        file_save_draft_area_files($value, $config->contextid, $this->plugin, $name, $config->id);
                        $value = $config->id;
                        break;
                }

                $config->$name = $value;
                $this->save_config_setting($table, $params, $value);
            }
        }

        // Update the current config object.
        if ($this->config) {
            $this->config = $config;
        }

        return $config->id;
    }

    /**
     * Save config setting.
     *
     * @uses $DB
     * @param string $table the name of the DB table to update.
     * @param array $params DB field names and values used to select record from $table.
     * @param string $value the setting value to be added or updated.
     * @return void but may update $table in the DB.
     */
    public function save_config_setting($table, $params, $value) {
        global $DB;
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

    /**
     * Save the config settings from the input form.
     *
     * @param object $data the form data
     * @return void (but may add config settings to the database)
     */
    public function save_config($data) {

        // Cache the calendar factory.
        $calendar = \core_calendar\type_factory::get_calendar_instance();

        // Convert dates to a single timestamp.
        $names = ['sharedfrom', 'shareduntil'];
        foreach ($names as $name) {
            if (isset($data->$name) && is_array($data->$name)) {
                $date = $data->$name;
                if (empty($date['enabled'])) {
                    $date = 0;
                } else {
                    $date = $calendar->convert_to_gregorian(
                        $date['year'],
                        $date['month'],
                        $date['day'],
                        $date['hour'],
                        $date['minute']
                    );
                    $date = make_timestamp(
                        $date['year'],
                        $date['month'],
                        $date['day'],
                        $date['hour'],
                        $date['minute']
                    );
                }
                $data->$name = $date;
            }
        }

        // Make sure we have a at least a primary field (e.g. "chatgptkey" or "prompt").
        $name = static::SETTINGNAMES[0];
        if (isset($data->$name) && $data->$name) {

            // Ensure valid context id and level.
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
     * @param object $data the form data
     * @return void (but may remove properties from the form $data)
     */
    public function unset_form_elements($data) {
        foreach (self::get_settingnames() as $name) {
            if (isset($data->$name)) {
                $this->unset_element($name);
            }
        }
    }

    /**
     * unset_element
     *
     * @param string $name
     * @return void (but may remove an item from $_GET and $_POST)
     */
    public function unset_element($name) {
        if (isset($_GET[$name])) {
            unset($_GET[$name]);
        }
        if (isset($_POST[$name])) {
            unset($_POST[$name]);
        }
    }

    /**
     * fileoperation_requested
     *
     * @return bool TRUE is user has requested an fileoperation (import, export), otherwise FALSE.
     */
    public function fileoperation_requested() {
        if (empty($this->fileoperation)) {
            return false;
        }
        return ($this->fileoperation == 'import' || $this->fileoperation == 'export');
    }

    /**
     * Execute an fileoperation that has been requested and confirmed.
     *
     * @return void but will redrect to the main index page for this subplugin.
     */
    public function fileoperation_execute() {
        $completed = $this->fileoperation.'completed';

        // Access to this config and fileoperation has already been checked
        // in the constructor method for this class.

        if ($this->fileoperation == 'import') {
            $this->import_configs();
            redirect($this->index_url(), $this->get_string($completed));
        }

        if ($this->fileoperation == 'export') {
            $this->export_configs();
            redirect($this->index_url(), $this->get_string($completed));
        }

        // This shouldn't happen !!
        redirect($this->index_url(), 'Unknown fileoperation: '.$this->fileoperation);
    }

    /**
     * Export configs in XML format.
     */
    public function export_configs() {
        global $CFG;

        // Get libraries for xml_writer, xml_output, and memory_xml_output.
        $xmllib = $CFG->dirroot.'/backup/util/xml';
        require_once($xmllib.'/xml_writer.class.php');
        require_once($xmllib.'/output/xml_output.class.php');
        require_once($xmllib.'/output/memory_xml_output.class.php');

        $filename = '';
        $name = 'exportfile';
        $elements = self::get_optional_param($name.'elements', [], PARAM_FILE);
        if (is_array($elements) && array_key_exists($name, $elements)) {
            $filename = clean_param($elements[$name], PARAM_FILE);
        }
        if ($filename == '') {
            $filename = "$name.xml"; // Default filename.
        }

        // Get the names of the plugins to export.
        $plugins = array_merge(
            self::get_optional_param('contentplugins', [], PARAM_ALPHANUM),
            self::get_optional_param('assistantplugins', [], PARAM_ALPHANUM)
        );
        $plugins = array_keys(array_filter($plugins));

        // Ensure we have some plugins to export.
        if (empty($plugins)) {
            return true;
        }

        // Prepend plugintype to each plugin name.
        array_walk($plugins, function(&$plugin) {
            $plugin = 'vocabai_' . $plugin;
        });

        // Fetch the config records for the required plugins.
        if (is_siteadmin()) {
            // Admin can export everything.
            $returnuser = '';
        } else {
            // Teacher only sees their own stuff.
            $returnuser = 'thisuser';
        }
        $configs = $this->get_configs($returnuser, '', false, $plugins);

        // Colect the configs into a one-dimension array.
        if (is_siteadmin()) {
            $configs = $configs->otherusers->othercontexts
                     + $configs->otherusers->thiscontext
                     + $configs->thisuser->othercontexts
                     + $configs->thisuser->thiscontext;
        } else {
            $configs = $configs->othercontexts
                     + $configs->thiscontexts;
        }

        // Ensure we have some configs to export.
        if (empty($configs)) {
            return true;
        }

        $fs = get_file_storage();
        $contextids = $this->vocab->get_writeable_contexts('contextlevel', 'id');

        // Sort the configs by plugin name.
        $sortfield = 'subplugin';
        uasort($configs, function($a, $b) use ($sortfield) {
            return $this->uasort_configs($a, $b, $sortfield);
        });

        // Start the xml output file.
        $xmloutput = new \memory_xml_output();
        $xmlwriter = new \xml_writer($xmloutput);
        $xmlwriter->start();

        $xmlwriter->begin_tag('CONFIGS');

        // Export each config.
        foreach ($configs as $configid => $config) {
            $attributes = [
                'subplugin' => $config->subplugin,
                'contextlevel' => $config->contextlevel,
            ];
            $xmlwriter->begin_tag('CONFIG', $attributes);
            $ai = '\\'.$config->subplugin.'\\ai';

            $names = $ai::EXPORTSETTINGNAMES;
            if (empty($names)) {
                $names = $ai::SETTINGNAMES;
            }
            foreach ($names as $name) {
                if (empty($config->$name)) {
                    continue;
                }
                if ($ai::is_file_setting($name)) {
                    $contextid = $contextids[$config->contextlevel];
                    if ($fs->file_exists(
                        $contextid, $config->subplugin, $name,
                        $config->$name, '/', $config->filename
                    )) {
                        $file = $fs->get_file(
                            $contextid, $config->subplugin, $name,
                            $config->$name, '/', $config->filename
                        );
                        $content = base64_encode($file->get_content());
                    } else {
                        $content = ''; // Shouldn't happen !!
                    }
                } else {
                    $content = $config->$name;
                }
                $xmlwriter->full_tag(strtoupper($name), $content);
            }
            $xmlwriter->end_tag('CONFIG');
        }
        $xmlwriter->end_tag('CONFIGS');

        $xmlwriter->stop();
        $xmlstr = $xmloutput->get_allcontents();

        // Send the XML to the browser for downloading.
        send_file($xmlstr, $filename, 0, 0, true, true);

        die; // Processing stops here.
    }

    /**
     * Import configs in XML format.
     */
    public function import_configs() {
        global $CFG, $DB, $USER;

        // Get Moodle's standard xmlize library.
        require_once($CFG->dirroot.'/lib/xmlize.php');

        $name = 'importfile';
        $elements = self::get_optional_param($name.'elements', [], PARAM_FILE);
        if (is_array($elements) && array_key_exists($name, $elements)) {
            $draftid = clean_param($elements[$name], PARAM_INT);
        } else {
            $draftid = 0;
        }
        if (empty($draftid)) {
            return false;
        }

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        if ($file = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
            $file = reset($file);
        } else {
            $file = null;
        }
        if (empty($file)) {
            return false;
        }

        if ($xml = $file->get_content()) {
            $xml = xmlize($xml, 0);
        } else {
            $xml = [];
        }
        if (empty($xml)) {
            return false;
        }

        $subplugins = \core_component::get_plugin_list('vocabai');
        $subplugins = array_keys($subplugins);
        array_walk($subplugins, function(&$subplugin) {
            $subplugin = 'vocabai_' . $subplugin;
        });

        $contextlevels = $this->vocab->get_writeable_contexts('id', 'contextlevel');
        $contextids = array_keys($contextlevels);

        $configs = $this->get_xml_node($xml, ['CONFIGS', '#', 'CONFIG']);
        foreach ($configs as $i => $config) {

            // Extract subplugin name and context level.
            $subplugin = $this->get_xml_node($config, ['@', 'subplugin']);
            $contextlevel = $this->get_xml_node($config, ['@', 'contextlevel']);

            // Skip if $subplugin is not available.
            if (in_array($subplugin, $subplugins) === false) {
                continue;
            }

            // Skip if $contextlevel is not available.
            if (in_array($contextlevel, $contextlevels) === false) {
                continue;
            }
            $contextid = array_search($contextlevel, $contextlevels);

            // Fetch list of valid field names.
            $ai = '\\'.$subplugin.'\\ai';
            $form = '\\'.$subplugin.'\\form';
            $fieldnames = $ai::get_settingnames();

            // Initialize new config settings record.
            $settings = (object)[
                'subplugin' => $subplugin,
                'contextid' => $contextid,
                'contextlevel' => $contextlevel,
            ];
            $fieldcount = 0;

            // Extract fields for this config record.
            $fields = $this->get_xml_node($config, ['#']);
            foreach ($fields as $fieldname => $fieldvalue) {
                $fieldname = strtolower($fieldname);
                if (in_array($fieldname, $fieldnames) === false) {
                    continue;
                }
                $fieldvalue = $this->get_xml_node($fieldvalue, [0, '#']);
                $settings->$fieldname = $fieldvalue;
                $fieldcount++;
            }

            if ($fieldcount) {

                $select = 'vc.id, vc.subplugin';
                $from = ['{vocab_config} vc'];
                list($where, $params) = $DB->get_in_or_equal($contextids);
                $where = ['vc.subplugin = ?', "vc.contextid $where"];
                $params = array_merge([$subplugin], $params);

                foreach ($form::REQUIRED_FIELDS as $i => $fieldname) {
                    if ($ai::is_file_setting($fieldname)) {
                        continue;
                    }
                    $vcs = 'vcs'.($i + 1);
                    $from[] = '{vocab_config_settings} '.$vcs;
                    $where[] = "$vcs.configid = vc.id";
                    $where[] = "$vcs.name = ?";
                    $params[] = $fieldname;
                    // The matching of text fields containing newlines doesn't seem to work,
                    // so as a workaround, we can replace the newlines with '%' and use LIKE.
                    if ($fieldname == 'formattext' || $fieldname == 'prompttext') {
                        $where[] = $DB->sql_like("$vcs.value", '?');
                        $params[] = str_replace("\n", '%', $settings->$fieldname);
                    } else {
                        $where[] = "$vcs.value = ?";
                        $params[] = $settings->$fieldname;
                    }
                }

                $from = implode(', ', $from);
                $where = implode(' AND ', $where);
                $sql = "SELECT $select FROM $from WHERE $where";

                if ($DB->record_exists_sql($sql, $params)) {
                    $exists = true;
                } else {
                    $exists = false;

                    // Save file details, if there are any.
                    // We'll add the file later,
                    // after we have a new config id.
                    $fieldname = 'fileitemid';
                    $fieldvalue = 0;
                    $filename = '';
                    $filecontent = '';
                    if (isset($settings->$fieldname)) {
                        if ($filecontent = $settings->$fieldname) {
                            $filecontent = base64_decode($filecontent);
                            if (isset($settings->filename)) {
                                $filename = $settings->filename;
                            } else {
                                $settings->filename = '';
                            }
                        }
                        $settings->$fieldname = 0;
                    }

                    // Save the new config settings.
                    $configid = $this->save_config_settings($settings);

                    if ($filename && $filecontent) {
                        $filerecord = [
                            'contextid' => $contextid,
                            'component' => $subplugin,
                            'filearea'  => $fieldname,
                            'itemid'    => $configid,
                            'filepath'  => '/',
                            'filename'  => $filename,
                        ];
                        if ($file = $fs->create_file_from_string($filerecord, $filecontent)) {
                            $settings->fileitemid = $configid;
                            $params = ['configid' => $configid, 'name' => $fieldname];
                            $DB->update_field('vocab_config_settings', 'value', $configid, $params);
                        } else {
                            // Could not add file - shouldn't happen !!
                            // Perhaps we should delete the whole record?
                            $settings->$fieldname = 0;
                            $settings->filename = '';
                        }
                    }
                }
            }
        }
    }

    /**
     * Return the value of a node, given a path to the node
     * If the path doesn't exist, return the default value.
     *
     * @param array $xml data to read
     * @param array $nodes path to node expressed as array
     * @param mixed $default value (optional, default=NULL)
     * @return mixed value of node at the specified path.
     */
    public function get_xml_node($xml, $nodes, $default=null) {
        foreach ($nodes as $node) {
            if (array_key_exists($node, $xml)) {
                $xml = $xml[$node];
            } else {
                return $default; // Shouldn't happen !!
            }
        }
        return $xml;
    }

    /**
     * action_cancelled
     *
     * @return bool TRUE is user has cancelled an action (add, copy, delete), otherwise FALSE.
     */
    public function action_cancelled() {
        if (empty($this->config) || empty($this->action)) {
            return false;
        }
        $cancelled = $this->action.'cancelled';
        return self::get_optional_param(['cancel', $cancelled], '', PARAM_TEXT);
    }
    /**
     * action_cancelled
     *
     * @return void but will redrect to the main index page for this subplugin.
     */
    public function action_cancel() {
        $cancelled = $this->action.'cancelled';
        redirect($this->index_url(), $this->get_string($cancelled));
    }

    /**
     * action_requested
     *
     * @return bool TRUE is user has requested an action (add, copy, delete), otherwise FALSE.
     */
    public function action_requested() {
        // The configid and action are passed via GET from main form to
        // confirmation form and via POST from when returning to main form.
        if (empty($this->config) || empty($this->action)) {
            return false;
        }
        return ($this->action == 'copy' || $this->action == 'delete');
    }

    /**
     * action_confirmed
     *
     * @return bool TRUE is user has confirmed their action (add, copy, delete), otherwise FALSE.
     */
    public function action_confirmed() {
        if (self::get_optional_param($this->action.'confirmed', '', PARAM_TEXT)) {
            return confirm_sesskey();
        } else {
            return false;
        }
    }

    /**
     * Execute an action that has been requested and confirmed.
     *
     * @return void but will redrect to the main index page for this subplugin.
     */
    public function action_execute() {
        $completed = $this->action.'completed';

        // Access to this config and action has already been checked
        // in "mod_vocab/classes/subpluginbase.php".

        if ($this->action == 'delete') {
            $this->delete_config();
            redirect($this->index_url(), $this->get_string($completed));
        }

        if ($this->action == 'copy') {
            $this->config->id = $this->copy_config();
            redirect($this->index_url(), $this->get_string($completed));
        }

        // This shouldn't happen !!
        redirect($this->index_url(), 'Unknown action: '.$this->action);
    }

    /**
     * Display a form to confirm a requested action.
     *
     * @param string $type the type of subplugin item (e.g. key, prompt, format, file)
     * @return void, but will display confirmation form to user.
     */
    public function action_confirm($type) {
        global $OUTPUT, $PAGE;

        if ($this->action == 'delete') {
            $btncolor = 'btn-danger';
        } else if ($this->action == 'copy') {
            $btncolor = 'btn-dark';
        } else {
            // Unknown action - shouldn't happen !!
            $btncolor = 'btn-light';
        }

        // Action has not been confirmed, so display confirmation form.
        $heading = $this->get_string($this->action.$type);
        $message = $this->get_string('confirm'.$this->action.$type);

        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo $OUTPUT->box_start('generalbox', 'notice');

        $url = $PAGE->url;
        $url->param('action', $this->action);
        $url->param('cid', $this->config->id);
        echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);

        // XHTML strict requires a container for the hidden input elements.
        echo \html_writer::start_tag('fieldset', ['style' => 'display: none']);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $this->action]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'configid', 'value' => $this->config->id]);
        echo \html_writer::end_tag('fieldset');

        // XHTML strict requires a container for the contents of the form.
        echo \html_writer::start_tag('div');

        echo $this->get_mform()->format_config($this->config, [], true);

        echo \html_writer::start_tag('div', ['class' => 'buttons']);
        echo \html_writer::tag('p', $message);

        echo \html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => $this->action.'confirmed',
            'value' => \core_text::strtotitle($this->get_string($this->action)),
            'class' => 'border rounded btn '.$btncolor.' mr-2',
        ]);
        echo \html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => $this->action.'cancelled',
            'value' => get_string('cancel'),
            'class' => 'border rounded btn btn-light ml-2',
        ]);
        echo \html_writer::end_tag('div');

        echo \html_writer::end_tag('div');
        echo \html_writer::end_tag('form');

        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }

    /**
     * Delete items from the vocab_config and vocab_config_settings tables.
     *
     * @return void (but may remove an items form the database)
     */
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

    /**
     * Copy the current config settings to a new config record.
     *
     * @return void (but may add items to the database)
     */
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

        // If the contextid/level and user are the same - don't do anything.
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

    /**
     * Set the internally stored config record.
     *
     * @param object $config the config settings to set.
     * @return void (but will updated the "config" property)
     */
    public function set_config($config) {
        $this->config = new \stdClass();
        foreach (static::SETTINGNAMES as $name) {
            if (isset($config->$name)) {
                $this->config->$name = $config->$name;
            }
        }
    }

    /**
     * Set or get the flag showing whether to use the base model or a tuned model.
     *
     * @param mixed $value if the supplied this method sets the flag, otherwise it returns the current value .
     * @return mixed either the
     */
    public function use_tuning_file($value=null) {
        // If no value is given, we return the current value.
        if ($value === null) {
            return $this->usetuningfile;
        }
        // Set the flag depending on the $value.
        if (is_bool($value)) {
            $this->usetuningfile = $value;
        } else {
            $this->usetuningfile = (bool)$value;
        }
    }

    /**
     * Get media files and store them in the specified filearea.
     * If several files are generated, they will *all* be converted
     * and stored, but only the first one will be returned by this method.
     * This method is only used by SUBTYPE_IMAGE|AUDIO|VIDEO subplugins.
     *
     * @param string $prompt
     * @param array $filerecord
     * @param integer $questionid
     * @return stored_file or error message as a string.
     */
    public function get_media_file($prompt, $filerecord, $questionid) {
        return null;
    }

    /**
     * Send a prompt to an AI assistant and get the response.
     * This method is only used by SUBTYPE_TEXT|IMAGE|AUDIO|VIDEO subplugins.
     *
     * @param string $prompt
     * @param integer $questionid (optional, default=0)
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt, $questionid=0) {
        return null;
    }

    /**
     * Determine if a string contains a JSON encoded object/array
     *
     * @param string $str
     * @return boolean TRUE is $str is a JSON encoded object or array; otherwise FALSE
     */
    public function is_json($str) {
        if (substr($str, 0, 1) == '{' && substr($str, -1) == '}') {
            return true;
        }
        if (substr($str, 0, 1) == '[' && substr($str, -1) == ']') {
            return true;
        }
        return false;
    }

    /**
     * Check the prompt config values are valid and complete.
     *
     * @param object $promptconfig the config settings for this prompt.
     * @return bool TRUE if prompt is valid; Otherwise FALSE.
     */
    public function check_prompt_params($promptconfig) {
        return true;
    }

    /**
     * Check the format config values are valid and complete.
     *
     * @param object $formatconfig the config settings for this format.
     * @return bool TRUE if format is valid; Otherwise FALSE.
     */
    public function check_format_params($formatconfig) {
        return true;
    }

    /**
     * Check the file config values are valid and complete.
     *
     * @param object $fileconfig the config settings for this file.
     * @return bool TRUE if file is valid; Otherwise FALSE.
     */
    public function check_file_params($fileconfig) {
        return true;
    }

    /**
     * Should we reschedule the Moodle adhoc_task to run again later?
     *
     * @param object $promptconfig the config settings for the prompt
     * @param object $formatconfig the config settings for the output format
     * @param object $fileconfig the config settings for the AI tuning file
     * @return bool TRUE if the adhoc task should be rescheduled; otherwise FALSE.
     */
    public function reschedule_task($promptconfig, $formatconfig, $fileconfig) {
        return false;
    }

    /**
     * Echo an object to the output stream.
     * We can't use print_r overtly in the code,
     * because the codechecker regards it as evil.
     *
     * @param object $obj
     * @return void, but will generate output.
     */
    protected static function mtrace_object($obj) {
        if (static::DEBUG) {
            $fn = 'prin'.'t_r';
            mtrace($fn($obj, true));
        }
    }
}
