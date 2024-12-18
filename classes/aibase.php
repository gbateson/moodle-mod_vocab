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

    /**
     * The AI type for subplugins such as "prompts", "formats",
     * and "files" that provide input for other AI plugins.
     */
    const AI_TYPE_INPUT = 'input';

    /** the AI type for subplugins that generate "text" (e.g. chatgpt) */
    const AI_TYPE_TEXT = 'text';

    /** the AI type for subplugins that generate "image" (e.g. dalle, midjourney) */
    const AI_TYPE_IMAGE = 'image';

    /** the AI type for subplugins that generate "audio" (e.g. openai-tts) */
    const AI_TYPE_AUDIO = 'audio';

    /** the AI type for subplugins that generate "video" (e.g. vyond) */
    const AI_TYPE_VIDEO = 'video';

    /** @var bool enable or disable trace and debugging messages during development. */
    const DEBUG = false;

    /** @var string used to denote that an adhoc task should be rescheduled. */
    const RESCHEDULE_ADHOC_TASK = 'reschedule-adhoc-task';

    /**
     * @var bool to signify whether or not duplicate records,
     * i.e. records with the same owner and context, are allowed.
     */
    const ALLOW_DUPLICATES = false;

    /**
     * @var string containing type of this AI subplugin
     * (see AI_TYPE_XXX constants above)
     */
    public $type = '';

    /** @var object containing arrays of configs */
    public $configs = null;

    /** @var object the config settings object */
    public $config = null;

    /** @var string the optional action to be performed on the $config settings object */
    public $action = '';

    /** @var object to represent a curl object used for connecting to an AI assistant */
    public $curl = null;

    /** @var array of POST parameters to be sent via the curl object */
    public $postparams = null;

    /**
     * Get the array containing the names of all the config settings for this subplugin.
     */
    public function get_settingnames() {
        return static::SETTINGNAMES;
    }

    /**
     * Is the given setting $name a date setting?
     *
     * @param string $name the name of the setting to be checked.
     * @return bool TRUE if the $name is that of a date settings; otherwise FALSE
     */
    public function is_date_setting($name) {
        return in_array($name, static::DATESETTINGNAMES);
    }

    /**
     * Is the given setting $name a file setting?
     *
     * @param string $name the name of the setting to be checked.
     * @return bool TRUE if the $name is that of a file settings; otherwise FALSE
     */
    public function is_file_setting($name) {
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
     * @param int $configid (optional, default = 0) a specific configid
     * @param mixed $user (optional, default = null) an optional user id or record
     * @return mixed array of records from "vocab_config_settings", or FALSE if there are none.
     */
    public function get_config_settings($contexts, $configid=0, $user=null) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        } else if (is_scalar($user)) {
            $user = $DB->get_record('user', ['id' => $user]);
        }

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
        $params = array_merge([$this->plugin, $user->id], $params);

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

        return $configs;
    }

    /**
     * Find the config with the given id.
     *
     * @param int $configid The id of the required config record.
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
     * @param int $contextid (optional, default = 0) a specific contextid
     * @param int $contextlevel (optional, default = 0) a specific context level
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

                // Special processing for data and file fields.
                switch (true) {

                    case $this->is_date_setting($name):
                        $value = $this->get_date_value($value);
                        break;

                    case $this->is_file_setting($name):
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
     * Send a prompt to an AI assistant and get the response.
     *
     * @param string $prompt
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt) {
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
}
