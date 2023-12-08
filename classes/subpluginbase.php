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

    /** the parent vocab object */
    public $vocab = null;

    /**
     * __construct
     *
     * @todo Finish documenting this function
     */
    public function __construct() {
        $this->vocab = \mod_vocab\activity::create();
        $this->plugin = 'vocab'.static::SUBPLUGINTYPE.'_'.static::SUBPLUGINNAME;
        $this->pluginpath = $this->vocab->pluginpath.'/'.static::SUBPLUGINTYPE.'/'.static::SUBPLUGINNAME;
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
     * Creates a url for this tool
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
}
