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
     * @var string The full frankenstyle name of this subplugin
     * e.g. vocabtool_import. This will be set automatically
     * from the SUBPLUGINTYPE and SUBPLUGINNAME.
     * It is intended for use in the get_string() method.
     */
    public $plugin = '';

    /**
     * @var string the path to subplugin folder relative to $CFG->dirroot
     * e.g. mod/vocab/tool/import. This will be set automatically
     * from the SUBPLUGINTYPE and SUBPLUGINNAME.
     */
    public $pluginpath = '';

    /** @var object the parent vocab object */
    public $vocab = null;

    /**
     * __construct
     *
     * @uses $USER
     * @param mixed $vocabinstanceorid (optional, default=null) is a vocab instance or id
     * @return void, but will initialize this object instance
     */
    public function __construct($vocabinstanceorid=null) {
        global $USER;

        $this->vocab = \mod_vocab\activity::create(null, null, $vocabinstanceorid);
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
     * Create a new instance of the current class
     *
     * @param mixed $vocabinstanceorid the vocab instance id or object
     * @return object a new instance of the current subplugin class
     */
    public static function create($vocabinstanceorid=null) {
        $class = get_called_class();
        return new $class($vocabinstanceorid);
    }

    /**
     * Setup page url, title, heading and attributes.
     *
     * @return void (but will update url, title, heading and attributes in $PAGE object)
     */
    public function setup_page() {
        $pluginname = $this->get_string('pluginname');
        $this->vocab->setup_page(
            $this->index_url(),
            $pluginname, // The <title> tag for the page.
            $pluginname, // The <h1> heading for the page.
            ['hidecompletion' => true, 'description' => '']
        );
    }

    /**
     * Creates a url for this subplugin
     *
     * @param string $filepath
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return xxx
     *
     * TODO: Finish documenting this function
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

    /**
     * Get a string for this vocabtool plugin
     *
     * @param string $name the name of the required string
     * @param mixed $a (optional, default=null) additional value or values required for the string
     * @return string
     */
    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }
}
