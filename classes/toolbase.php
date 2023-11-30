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
 * Internal library of functions for module English Central
 *
 * All the vocab specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

defined('MOODLE_INTERNAL') || die();

/**
 * toolbase
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class toolbase {

    const PLUGINTYPE = 'vocabtool';
    const PLUGINNAME = ''; // subplugins should override this.

    public $plugin = '';
    public $pluginpath = '';
    public $vocab = null;

    /**
     * __construct
     *
     * @todo Finish documenting this function
     */
    public function __construct() {
        $this->vocab = \mod_vocab\activity::create();
        $this->plugin = static::PLUGINTYPE.'_'.static::PLUGINNAME;
        $this->pluginpath = $this->vocab->pluginpath.'/tool/'.static::PLUGINNAME;
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
     * @return object moodle_url
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
     * @return object moodle_url
     */
    public function index_url($escaped=null, $params=[]) {
        return $this->url('index.php', $escaped, $params);
    }

    /**
     * site_url
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function site_url() {
        return new \moodle_url('/');
    }

    /**
     * get_mform
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_mform() {
        global $PAGE;
        $mform = "\\$this->plugin\\form";
        $params = ['tool' => $this];
        return new $mform($PAGE->url->out(), $params);
    }

    /*
     * get a string for this vocabtool plugin
     *
     * @param string $name, the name of the string
     * @param mixed $a, (optional, default=null) additional value or values required for the string
     * @return string
     **/
    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }
}
