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
 * All the vocab specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

/**
 * activity
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class activity {

    /** @var string the type of this plugin */
    const PLUGINTYPE = 'mod';

    /** @var string the name of this plugin */
    const PLUGINNAME = 'vocab';

    /** @var int database value to represent "live" mode */
    const MODE_LIVE = 0;

    /** @var int database value to represent "demo" mode */
    const MODE_DEMO = 1;

    /** @var int database value to represent "any" word score */
    const GRADETYPE_ANY = 0;

    /** @var int database value to represent "highest" word scores */
    const GRADETYPE_HIGHEST = 1;

    /** @var int database value to represent "lowest" word scores */
    const GRADETYPE_LOWEST = 2;

    /** @var int database value to represent "newest" word scores */
    const GRADETYPE_NEWEST = 3;

    /** @var int database value to represent "oldest" word scores */
    const GRADETYPE_OLDEST = 4;

    /** @var int database value to represent "any" attempts */
    const ATTEMPTTYPE_ANY = 0;

    /** @var database value to represent "recent" attempts */
    const ATTEMPTTYPE_RECENT = 1;

    /** @var int database value to represent "consecutive" attempts */
    const ATTEMPTTYPE_CONSECUTIVE = 2;

    /** @var int database value to represent "no" delay between attempts */
    const ATTEMPTDELAY_NONE = 0;

    /** @var int database value to represent a "fixed" delay between attempts */
    const ATTEMPTDELAY_FIXED = -1;

    /** @var int database value to represent an "expanding" delay between attempts */
    const ATTEMPTDELAY_EXPANDING = -2;

    /** @var int database value to denote expanding the navigation menu for "everyone" */
    const EXPAND_EVERYONE = 0;

    /** @var int database value to denote expanding the navigation menu only for "students" (for teachers, it will be collapsed) */
    const EXPAND_STUDENTS = 1;

    /** @var int database value to denote expanding the navigation menu only for "teachers" (for students, it will be collapsed) */
    const EXPAND_TEACHERS = 2;

    /** @var int database value to denote expanding the navigation menu for "no one" */
    const EXPAND_NO_ONE = 3;

    /** @var stdclass vocab activity config settings (from "config_plugins" table) */
    public $config = null;

    /** @var stdclass user record of current user */
    public $user = null;

    /** @var stdclass course category record */
    public $coursecat = null;

    /** @var stdclass course record */
    public $course = null;

    /** @var stdclass course_modules record */
    public $cm = null;

    /** @var stdclass context object */
    public $context = null;

    /** @var stdclass vocab record */
    public $instance = null;

    /** @var int vocab instance identifier */
    public $id = 0;

    /** @var string vocab activity name */
    public $name = '';

    /** @var string vocab activity intro */
    public $intro = '';

    /** @var int vocab activity introformat */
    public $introformat = 0;

    /** @var int the "mode" of the current Vocab activity */
    public $operationmode = 0;

    /**
     * @var int denoting whether to show (=expand) or hide (=collapse) the "mycourses" navigation menu
     *      0: expand for everyone
     *      1: expand for students (collapse for teachers)
     *      2: expand for teachers (collapse for students)
     *      3: expand no one (i.e. collapse for everyone)
     */
    public $expandnavigation = 3;

    /** @var string specifying the preferred page layout, at least for the main "view" page */
    public $pagelayout = '';

    /** @var int the minimum score required for mastery */
    public $activityscore = 0;

    /** @var int the minimum number of attempts required for mastery */
    public $activitycount = 0;

    /** @var int the minimum total duration of attempts required for mastery */
    public $activityduration = 0;

    /** @var int the type of attempts considered for mastery conditions */
    public $activitytype = 0;

    /** @var int minimum interval between attempts */
    public $activityinterval = 0;

    /** @var int vocab activity open time */
    public $activityopen = 0;

    /** @var int vocab activity close time */
    public $activityclose = 0;

    /** @var int vocab games open time */
    public $gamesopen = 0;

    /** @var int vocab games close time */
    public $gamesclose = 0;

    /** @var string vocab activity timecreated */
    public $timecreated = 0;

    /** @var string vocab activity timemodified */
    public $timemodified = 0;

    /** @var array of readable contexts the include this vocab activity */
    public $contexts = null;

    /** @var string the full frankenstyle name of this plugin e.g. "mod_vocab" */
    public $plugin = '';

    /** @var string the path to this plugin e.g. "mod/vocab" */
    public $pluginpath = '';

    /** @var bool denotes whether or not the current user can view this activity */
    public $viewable = '';

    /** @var bool denotes whether or not the activity is playable by the current user */
    public $playable = '';

    /**
     * Construct an instance of a Vocabulary activity
     *
     * @uses $COURSE
     * @uses $SITE
     * @param object $course a record form the "course" table in the Moodle database (optional, default=null)
     * @param string $cm the course module object for the the current vocabulary activity (optional, default=null)
     * @param object $instance a record form the "vocab" table in the Moodle database (optional, default=null)
     * @param object $context a record form the "context" table in the Moodle database (optional, default=null)
     * @param object $user a record form the "user" table in the Moodle database (optional, default=null)
     */
    public function __construct($course=null, $cm=null, $instance=null, $context=null, $user=null) {
        global $COURSE, $DB, $SITE, $USER;

        $this->plugin = self::PLUGINTYPE.'_'.self::PLUGINNAME;
        $this->pluginpath = self::PLUGINTYPE.'/'.self::PLUGINNAME;

        if ($user === null) {
            $this->user = $USER;
        } else if (is_object($user)) {
            $this->user = $user;
        } else if (is_scalar($user)) {
            $this->user = $DB->get_record('user', ['id' => $user]);
        }

        if ($instance) {
            $this->instance = $instance;
            foreach ($instance as $field => $value) {
                if (property_exists($this, $field)) {
                    $this->$field = $value;
                }
            }
        }

        if ($cm) {
            $this->cm = $cm;
        }

        if ($course) {
            $this->course = $course;
        } else if ($COURSE) {
            $this->course = $COURSE;
        } else {
            $this->course = $SITE;
        }

        if ($context) {
            $this->context = $context;
        } else if ($cm) {
            $this->context = \context_module::instance($cm->id);;
        } else if ($course) {
            $this->context = \context_course::instance($course->id);
        } else if ($COURSE) {
            $this->context = \context_course::instance($COURSE->id);
        } else {
            $this->context = \context_system::instance();
        }

        // Cache the time stamp so that the same value is used in comparisons.
        $time = time();

        // Set "viewable" to true if user can view the first page of the activity.
        if ($this->can_manage()) {
            // A teacher/admin can always view.
            $this->viewable = true;
        } else if ($this->activityopen && $this->activityopen > $this->time) {
            // The activity is not open yet!
            $this->viewable = false;
        } else if ($this->activityclose && $this->activityclose < $this->time) {
            // The activity is already closed!
            $this->viewable = false;
        } else {
            $this->viewable = $this->can_view();
        }

        // Set "playable" to true if the user can access the games.
        if ($this->can_manage()) {
            // A teacher/admin can always play the games.
            $this->playable = true;
        } else if ($this->gamesopen && $this->gamesopen > $this->time) {
            // The games are not open yet!
            $this->playable = false;
        } else if ($this->gamesclose && $this->gamesclose < $this->time) {
            // The games are already closed!
            $this->playable = false;
        } else {
            // Otherwise, the games are playable if the activity is viewable.
            $this->playable = $this->viewable;
        }

        $this->config = get_config($this->plugin);
    }

    /**
     * Creates a new vocab activity object
     *
     * @uses $DB
     * @param object $course a record form the "course" table in the Moodle database (optional, default=null)
     * @param string $cm the course module object for the the current vocabulary activity (optional, default=null)
     * @param object $instance a record form the "vocab" table in the Moodle database (optional, default=null)
     * @param object $context a record form the "context" table in the Moodle database (optional, default=null)
     * @param object $attempt a record form the "vocab_game_attempt" table in the Moodle database (optional, default=null)
     * @return object a new \mod_vocab\activity object
     */
    public static function create($course=null, $cm=null, $instance=null, $context=null, $attempt=null) {
        global $DB;

        if (is_scalar($course)) {
            $course = $DB->get_record('course', ['id' => $course], '*', MUST_EXIST);
        }
        if (is_scalar($cm)) {
            $cm = $DB->get_record('course_modules', ['id' => $cm], '*', MUST_EXIST);
        }
        if (is_scalar($instance)) {
            $instance = $DB->get_record('vocab', ['id' => $instance], '*', MUST_EXIST);
        }

        if ($instance || $cm) {
            if ($course === null) {
                $course = ($cm ? $cm->course : ($instance ? $instance->course : 0));
                if (is_scalar($course)) {
                    $course = $DB->get_record('course', ['id' => $course], '*', MUST_EXIST);
                }
            }
            if ($cm === null) {
                $cm = get_coursemodule_from_instance(self::PLUGINNAME, $instance->id, 0, false, MUST_EXIST);
            }
            if ($instance === null) {
                $instance = $cm->instance;
                if (is_scalar($instance)) {
                    $instance = $DB->get_record(self::PLUGINNAME, ['id' => $instance], '*', MUST_EXIST);
                }
            }
            return new activity($course, $cm, $instance);
        }

        // Course module id.
        if ($cmid = self::get_optional_param(['id', 'cmid'], 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id(self::PLUGINNAME, $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $instance = $DB->get_record(self::PLUGINNAME, ['id' => $cm->instance], '*', MUST_EXIST);
            return new activity($course, $cm, $instance);
        }

        // Vocab instance id.
        if ($vocabid = self::get_optional_param(['v', 'vid', 'vocabid'], 0, PARAM_INT)) {
            $instance = $DB->get_record('vocab', ['id' => $vocabid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('vocab', $instanceid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            return new activity($course, $cm, $instance);
        }

        return new activity();
    }

    /**
     * Return the value of an optional script parameter.
     *
     * @param array $names of possible names for the input parameter
     * @param mixed $default value
     * @param mixed $type a PARAM_xxx constant value
     * @param integer $depth the maximum depth allowed for array parameters
     * @return mixed, either an actual value from the form, or a suitable default
     */
    public static function get_optional_param($names, $default, $type, $depth=1) {
        if (is_scalar($names)) {
            $names = [$names];
        }
        foreach ($names as $name) {
            if ($value = self::get_param($name, '', $type, $depth)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * get_param
     *
     * @param string $name of parameter
     * @param mixed $default value
     * @param integer $type one of PARAM_xxx constant values
     * @param integer $depth maximum allowable depth of nested arrays
     * return mixed the cleaned input parameter value, if it exists; otherwise $default value.
     */
    public static function get_param($name, $default, $type, $depth=1) {
        if (isset($_POST[$name])) {
            return self::clean_param($_POST[$name], $default, $type, $depth);
        }
        if (isset($_GET[$name])) {
            return self::clean_param($_GET[$name], $default, $type, $depth);
        }
        return $default;
    }

    /**
     * clean_param
     *
     * @param string $value of a parameter passed into this script
     * @param mixed $default value
     * @param integer $type one of PARAM_xxx constant values
     * @param integer $depth maximum allowable depth of nested arrays
     * return mixed the cleaned input parameter value, if it exists; otherwise $default value.
     */
    public static function clean_param($value, $default, $type, $depth=1) {
        if ($depth == 0) {
            return $default;
        }
        if (is_scalar($value)) {
            return clean_param($value, $type);
        }
        if (is_array($value)) {
            $array = [];
            foreach ($value as $key => $value) {
                if (preg_match('/^[a-z0-9_-]+$/i', $key)) {
                    if (is_scalar($value)) {
                        $array[$key] = clean_param($value, $type);
                    } else {
                        $array[$key] = self::clean_param($value, $default, $type, $depth - 1);
                    }
                }
            }
            if (count($array)) {
                return $array;
            }
        }
        return $default;
    }

    /*////////////////////////////////////////
    // availability API
    ////////////////////////////////////////*/

    /**
     * Determine if the current user is prevented from viewing this Vocab activity.
     *
     * @return bool TRUE if user is prevented from viewing; otherwise return FALSE
     */
    public function not_viewable() {
        return ($this->viewable ? false : true);
    }

    /**
     * Determine if the current user is prevented from playing
     * (=interacting with) games in this Vocab activity.
     *
     * @return bool TRUE if user is prevented from playing; otherwise return FALSE
     */
    public function not_playable() {
        return ($this->playable ? false : true);
    }

    /*////////////////////////////////////////
    // event and completion API
    ////////////////////////////////////////*/

    /**
     * Trigger viewed event and completion status.
     *
     * @uses $CFG
     * @return void but may initiate a Moodle event of modifiy completion status
     */
    public function trigger_viewed_event_and_completion() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/completionlib.php');

        if ($this->instance) {
            $event = \mod_vocab\event\course_module_viewed::create([
               'objectid' => $this->cm->id,
               'context' => $this->context,
            ]);
            $event->add_record_snapshot('course', $this->course);
            $event->add_record_snapshot('course_modules', $this->cm);
            $event->add_record_snapshot('vocab', $this->instance);
            $event->trigger();

            // Update 'viewed' state if required by completion system.
            $completion = new \completion_info($this->course);
            $completion->set_module_viewed($this->cm);
        }
    }

    /*////////////////////////////////////////
    // url API
    ////////////////////////////////////////*/

    /**
     * Get a URL connected to this plugin.
     *
     * @param string $filepath
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=array)
     * @return string a URL connected to this plugin
     */
    public function url($filepath, $escaped=null, $params=[]) {
        if ($this->cm) {
            $params = array_merge(['id' => $this->cm->id], $params);
        }
        $url = '/'.$this->pluginpath.'/'.$filepath;
        $url = new \moodle_url($url, $params);
        if (is_bool($escaped)) {
            $url = $url->out($escaped);
        }
        return $url;
    }

    /**
     * Get the URL of the main view page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the main view page for this plugin
     */
    public function view_url($escaped=null, $params=[]) {
        return $this->url('view.php', $escaped, $params);
    }

    /**
     * Get the URL of the report page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the report page for this plugin
     */
    public function report_url($escaped=null, $params=[]) {
        return $this->url('report.php', $escaped, $params);
    }

    /**
     * Get the URL of the attempt page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the attempt page for this plugin
     */
    public function attempt_url($escaped=null, $params=[]) {
        return $this->url('attempt.php', $escaped, $params);
    }

    /**
     * Get the URL of the submit page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the submit page for this plugin
     */
    public function submit_url($escaped=null, $params=[]) {
        return $this->url('submit.php', $escaped, $params);
    }

    /**
     * Get the URL of the attempt page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the review page for this plugin
     */
    public function review_url($escaped=null, $params=[]) {
        return $this->url('review.php', $escaped, $params);
    }

    /**
     * Get the URL of the index page for this plugin.
     *
     * @param bool $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string the URL of the index page for this plugin
     */
    public function index_url($escaped=null, $params=[]) {
        return new \moodle_url('/mod/vocab/index.php', ['id' => $this->course->id]);
    }

    /**
     * Get the URL of the course page for this Vocab activity
     *
     * @return string the URL of the course page for this Vocab activity
     */
    public function course_url() {
        $sectionnum = 0;
        if ($this->course && isset($this->course->coursedisplay) && defined('COURSE_DISPLAY_MULTIPAGE')) {
            // Available on Moodle >= 2.3.
            if ($this->course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                if ($modinfo = get_fast_modinfo($this->course)) {
                    $sections = $modinfo->get_section_info_all();
                    foreach ($sections as $section) {
                        if ($section->id == $this->cm->section) {
                            $sectionnum = $section->section;
                            break;
                        }
                    }
                }
                unset($modinfo, $sections, $section);
            }
        }
        $params = ['id' => $this->course->id];
        if ($sectionnum) {
            $params['section'] = $sectionnum;
        }
        return new \moodle_url('/course/view.php', $params);
    }

    /**
     * Get the URL of the grades page for the course page for this plugin.
     *
     * @return string the URL of the grades page for the course page for this plugin
     */
    public function grades_url() {
        return new \moodle_url('/grade/index.php', ['id' => $this->course->id]);
    }

    /**
     * require_login
     *
     * @return bool TRUE if user has required capability; otherwise FALSE.
     */
    public function require_login() {
        return require_login($this->course, false, $this->cm);
    }

    /**
     * require
     *
     * @param string $capability
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function require($capability) {
        return require_capability("{$this->pluginpath}:$capability", $this->context);
    }

    /**
     * can
     *
     * @param string $capability
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user has the specified capability in the current context. Otherwise, FALSE.
     */
    public function can($capability, $user=null) {
        return has_capability("{$this->pluginpath}:$capability", $this->context, $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:addinstance". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can add instance in the current context. Otherwise, FALSE.
     */
    public function can_addinstance($user=null) {
        return $this->can('addinstance', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:manage". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can manage the current context. Otherwise, FALSE.
     */
    public function can_manage($user=null) {
        return $this->can('manage', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:viewreports". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can view reports in the current context. Otherwise, FALSE.
     */
    public function can_viewreports($user=null) {
        return $this->can('viewreports', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:deleteattempts". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can delete attempts in the current context. Otherwise, FALSE.
     */
    public function can_deleteattempts($user=null) {
        return $this->can('deleteattempts', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:view". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can view Vocab activities in the current context. Otherwise, FALSE.
     */
    public function can_view($user=null) {
        return $this->can('view', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:attempt". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can view attempt games in the current context. Otherwise, FALSE.
     */
    public function can_attempt($user=null) {
        return $this->can('attempt', $user);
    }

    /**
     * Return TRUE is user has capability "mod/vocab:reviewmyattempts". Otherwise, FALSE.
     *
     * @param mixed $user a user id or object. By default (null) the current $USER's permissions will be checked.
     * @return bool TRUE if $user can review their own game attempts in the current context. Otherwise, FALSE.
     */
    public function can_reviewmyattempts($user=null) {
        return $this->can('reviewmyattempts', $user);
    }

    /*////////////////////////////////////////
    // strings API
    ////////////////////////////////////////*/

    /**
     * Get a string for this plugin or one of its subplugins.
     *
     * @param string $strname
     * @param array $a additional value or values required for the language string (optional, default=null)
     * @return string
     */
    public function get_string($strname, $a=null) {
        $components = [$this->plugin, 'moodle'];
        $component = $this->get_string_component($strname, $components);
        return get_string($strname, $component, $a);
    }

    /**
     * Get the name of the Moodle component that defines
     * a string used by mod_vocab (or one of its subplugins).
     *
     * @param string $strname the name of the required string
     * @param array $components array of Moodle components which may define $strname
     * @return string name of component that defines the required string.
     */
    public function get_string_component($strname, $components) {
        $strman = get_string_manager();
        foreach ($components as $component) {
            if ($strman->string_exists($strname, $component)) {
                return $component;
            }
        }
        // String could not be found, but we return the
        // first component anyway, so that an error will
        // be triggered and reported in the normal way.
        return $components[0];
    }

    /*////////////////////////////////////////
    // users API
    ////////////////////////////////////////*/

    /**
     * get_groupmode
     *
     * @return integer the groupmode of this cm or course.
     *
     * TODO: Finish documenting this function
     */
    public function get_groupmode() {
        if ($this->cm) {
            return groups_get_activity_groupmode($this->cm);
        }
        if ($this->course) {
            return groups_get_course_groupmode($this->course);
        }
        return NOGROUPS;
    }

    /**
     * get_userids that are accessible to the current user.
     *
     * @uses $DB
     * @param integer $groupid (optional, default=0)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_userids($groupid=0) {
        global $DB;
        $mode = $this->get_groupmode();
        if ($mode == NOGROUPS || $mode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $this->context)) {
            $users = get_enrolled_users($this->context, 'mod/vocab:view', $groupid, 'u.id', 'id');
            if (empty($users)) {
                return false;
            }
            return array_keys($users);
        } else {
            if ($groupid) {
                $select = 'groupid = ?';
                $params = [$groupid];
            } else {
                $groups = groups_get_user_groups($this->course->id);
                if (empty($groups)) {
                    return false;
                }
                list($select, $params) = $DB->get_in_or_equal($groups['0']);
            }
            $users = $DB->get_records_select_menu('group_members', 'groupid '.$select, $params, 'id, userid');
            if (empty($users)) {
                return false;
            }
            return array_unique($users);
        }
    }

    /**
     * is_demo
     *
     * @return bool
     *
     * TODO: Finish documenting this function
     */
    public function is_demo() {
        return ($this->operationmode == self::MODE_DEMO);
    }

    /*////////////////////////////////////////
    // words API
    ////////////////////////////////////////*/

    /**
     * Get info about words in the word list for this Vocabulary activity
     *
     * @return array of objects representing the words in this wordlist
     */
    public function get_wordlist_info() {
        global $DB;
        $wordlist = [];
        if ($this->is_demo()) {
            $count = rand(10, 50);
            for ($i = 0; $i < $count; $i++) {
                $wordlist[$i] = $this->get_random_word(rand(4, 12));
            }
            asort($wordlist); // Maintain key association.
        } else {
            $select = 'vwi.*, vw.word';
            $from = '{vocab_word_instances} vwi, {vocab_words} vw';
            $where = 'vwi.vocabid = ? AND vwi.wordid = vw.id';
            $order = 'vw.word';
            $params = ['vocabid' => $this->id];
            if ($words = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
                foreach ($words as $word) {
                    $wordlist[$word->wordid] = $word->word;
                }
            }
            $wordinfo = (object)[
                'wordid' => 0,
                'word' => '',
                'completed' => 99,
                'inprogress' => 99,
                'notstarted' => 99,
            ];
        }
        return $wordlist;
    }

    /**
     * Get list of words in the word list for this Vocabulary activity
     *
     * @return array of words
     */
    public function get_wordlist_words() {
        global $DB;
        $wordlist = [];
        if ($this->is_demo()) {
            $count = rand(10, 30);
            for ($i = 0; $i < $count; $i++) {
                $wordlist[$i] = $this->get_random_word(rand(4, 12));
            }
            asort($wordlist); // Maintain key association.
        } else {
            $select = 'vwi.*, vw.word';
            $from = '{vocab_word_instances} vwi, {vocab_words} vw';
            $where = 'vwi.vocabid = ? AND vwi.wordid = vw.id';
            $order = 'vw.word';
            $params = ['vocabid' => $this->id];
            if ($words = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
                foreach ($words as $word) {
                    $wordlist[$word->wordid] = $word->word;
                }
            }
        }
        return $wordlist;
    }

    /**
     * get_random_word
     *
     * @param xxx $length
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_random_word($length) {
        $vowels = [
            'a', 'e', 'i', 'o', 'u', 'y',
            'ai', 'ei', 'oi', 'oo', 'ou',
        ];
        $consonants = [
            'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm',
            'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',
            'ch', 'gh', 'ph', 'sh', 'th', 'wh',
        ];
        $max1 = count($vowels) - 1;
        $max2 = count($consonants) - 1;

        $string = '';
        if (rand(0, 6) > 0) {
            $string .= $consonants[rand(0, $max2)];
        }
        while (strlen($string) < $length) {
            $string .= $vowels[rand(0, $max1)];
            $string .= $consonants[rand(0, $max2)];
        }

        return $string;
    }

    /**
     * Setup page url, title, heading and attributes.
     *
     * @uses $PAGE
     * @param string $url
     * @param string $title
     * @param string $heading
     * @param array $attributes
     * @return void (but will update url, title, heading and attributes in $PAGE object)
     */
    public function setup_page($url, $title, $heading, $attributes=[]) {
        global $PAGE;

        $PAGE->set_url($url);
        $PAGE->set_title($title);
        $PAGE->set_heading($heading);

        if (count($attributes)) {
            // In Moodle >= 4.x, we can use attributes to show/hide items
            // in the header, such as the description and completion info.
            if (method_exists($PAGE, 'magic_get_activityheader')) {
                $PAGE->activityheader->set_attrs($attributes);
            }
            // In Moodle <= 3.11, we could show/hide the description ourselves.
        }

        $this->collapse_navigation();
        $this->set_pagelayout();
    }

    /**
     * collapse_navigation
     *
     * @uses $PAGE
     * @return void (but may update navigation in $PAGE object)
     *
     * TODO: Finish documenting this function
     */
    public function collapse_navigation() {
        global $PAGE;

        if ($teacher = $this->can_viewreports()) {
            $student = false;
        } else {
            $student = $this->can_view();
        }

        // The default setting for "forceopen" is to expand for all.
        $forceopen = true;
        switch ($this->expandnavigation) {
            case self::EXPAND_TEACHERS:
                // Expand for teachers (collapse for students).
                $forceopen = $teacher;
                break;
            case self::EXPAND_STUDENTS:
                // Expand for students (collapse for teachers).
                $forceopen = $student;
                break;
            case self::EXPAND_NO_ONE:
                // Expand for no one (i.e. collapse for all).
                $forceopen = false;
                break;
        }

        // Since the default setting is to expand the navigation menu,
        // we only need to do something if $forceopen is FALSE.

        if ($forceopen === false) {
            $rootnodekeys = [
                'site', 'myprofile', 'currentcourse',
                'mycourses', 'courses', 'users',
            ];
            foreach ($rootnodekeys as $nodekey) {
                if ($node = $PAGE->navigation->get($nodekey)) {
                    $node->forceopen = $forceopen;
                }
            }
        }
    }

    /**
     * Set the page layout
     *
     * @uses $PAGE
     * @return void (but may update pagelayout in $PAGE object)
     */
    public function set_pagelayout() {
        global $PAGE;
        if ($this->pagelayout) {
            $PAGE->set_pagelayout($this->pagelayout);
        }
    }

    /**
     * Get context related to the current Vocabulary activity.
     *
     * @return array of context objects indexed by contextlevel
     */
    public function get_contexts() {
        if ($this->contexts === null) {
            $this->contexts = [
                // For reference, SYSTEM is 10, USER is 30, COURSECAT is 40, COURSE is 50, MODULE is 70.
                CONTEXT_MODULE => \context_module::instance($this->cm->id),
                CONTEXT_COURSE => \context_course::instance($this->course->id),
                CONTEXT_COURSECAT => \context_coursecat::instance($this->course->category),
                CONTEXT_SYSTEM => \context_system::instance(),
                CONTEXT_USER => \context_user::instance($this->user->id),
            ];
        }
        return $this->contexts;
    }

    /**
     * Get readable contexts relevant to this vocab activity.
     *
     * @param string $keyfield (optional, default = '') the name of the field to
     *                use as keys in the return array. If blank, keys will be numeric.
     * @param string $valuefield (optional, default = '') the name of the field to
     *                use as values in the return array.
     *                If blank, complete context records will be returned.
     * @param mixed $user (optional, default=null) a user id or object
     * @return array
     */
    public function get_readable_contexts($keyfield='', $valuefield='', $user=null) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        } else if (is_scalar($user)) {
            $user = $DB->get_record('user', ['id' => $user]);
        }
        $issiteadmin = is_siteadmin();

        $readable = [];
        foreach ($this->get_contexts() as $context) {
            switch ($context->contextlevel) {
                case CONTEXT_MODULE:
                    $capability = 'mod/vocab:view';
                    break;
                case CONTEXT_COURSE:
                    $capability = 'moodle/course:view';
                    break;
                case CONTEXT_COURSECAT:
                    $capability = 'moodle/category:viewcourselist';
                    break;
                case CONTEXT_SYSTEM:
                    $capability = 'moodle/course:view'; // Hmm ...
                    break;
                case CONTEXT_USER:
                    $capability = 'moodle/user:viewdetails';
                    break;
                default:
                    // Unrecognized context - shouldn't happen !!
                    $capability = '';
            }
            if ($capability) {
                if ($issiteadmin || has_capability($capability, $context, $user)) {
                    $key = ($keyfield ? $context->$keyfield : count($readable));
                    $value = ($valuefield ? $context->$valuefield : $context);
                    $readable[$key] = $value;
                }
            }
        }
        return $readable;
    }

    /**
     * Get writeable contexts relevant to this vocab activity.
     *
     * @param string $keyfield (optional, default = '') the name of the field to use
     *               as keys in the return array. If blank, keys will be numeric.
     * @param string $valuefield (optional, default = '') the name of the field to use
     *               as values in the return array. If blank, complete context records
     *               will be returned.
     * @param mixed $user (optional, default=null) a user id or object
     * @return array
     */
    public function get_writeable_contexts($keyfield='', $valuefield='', $user=null) {
        global $DB, $USER;

        if ($user === null) {
            $user = $USER;
        } else if (is_scalar($user)) {
            $user = $DB->get_record('user', ['id' => $user]);
        }

        // Get array of contexts indexed by contextlevel (low to high).
        $contexts = $this->get_contexts();

        $writeable = [];
        foreach ($contexts as $context) {

            // For reference, SYSTEM is 10, USER is 30, COURSECAT is 40, COURSE is 50, MODULE is 70.
            switch ($context->contextlevel) {
                case CONTEXT_MODULE:
                    $capability = 'mod/vocab:manage';
                    break;
                case CONTEXT_COURSE:
                    $capability = 'moodle/course:manageactivities';
                    break;
                case CONTEXT_COURSECAT:
                    $capability = 'moodle/category:manage';
                    break;
                case CONTEXT_SYSTEM:
                    $capability = 'moodle/site:config';
                    break;
                case CONTEXT_USER:
                    $capability = 'moodle/user:manageownfiles';
                    break;
                default:
                    // Unrecognized context - shouldn't happen !!
                    $capability = '';
            }
            if ($capability && has_capability($capability, $context, $user)) {
                $key = ($keyfield ? $context->$keyfield : count($writeable));
                $value = ($valuefield ? $context->$valuefield : $context);
                // Prepend key/value so that they are returned
                // in order from high to low contextlevel.
                $writeable = [$key => $value] + $writeable;
            } else {
                // If we can't write at the current context level,
                // then we can skip any higher level contexts.
                break;
            }
        }
        return $writeable;
    }

    /**
     * get_question_categories
     *
     * @uses $CFG
     * @param xxx $toponly (optional, default=false)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_question_categories($toponly=false) {
        global $CFG;
        require_once($CFG->dirrot.'/lib/questionlib.php');

        $categories = [];
        $contexts = $this->get_writeable_contexts('contextlevel');
        if ($toponly) {
            // This will make only "top" question cateogries.
            foreach ($contexts as $level => $context) {
                $categories[$level] = question_get_top_category($context->id, true);
            }
        } else {
            // This will make "top" and "default" question cateogries.
            question_make_default_categories($contexts);
            foreach ($contexts as $level => $context) {
                $top = question_get_top_category($context->id);
                $params = ['contextid' => $context->id, 'parent' => $top->id];
                $categories[$level] = $DB->get_record('question_categories', $params);
            }
        }
        return $categories;
    }

    /**
     * Modify a file name by prepending one or more prefixes, and/or
     * appending one or more suffixes, and/or changing the file type.
     *
     * @param string $filename
     * @param mixed $prefix string containing single prefix, or array containing multiple prefixes (optional, default="")
     * @param mixed $suffix string containing single suffix, or array containing multiple suffixes (optional, default="")
     * @param string $extension without leading "." (optional, default="")
     * @param string $join the join string used to prepend $prefix or append $suffix (optional, default="-")
     * @return string the modified filename.
     */
    public static function modify_filename($filename, $prefix='', $suffix='', $extension='', $join='-') {
        $pathinfo = pathinfo($filename);

        // If necessary, fix the dirname.
        $dirname = ($pathinfo['dirname'] ?? '');
        if ($dirname == '.' || $dirname == '/') {
            $dirname = ''; // No dirname given.
        } else {
            $dirname .= '/'; // Shouldn't happen !!
        }

        $filename = ($pathinfo['filename'] ?? '');

        if (is_array($prefix)) {
            $prefix = implode($join, $prefix);
        }
        if ($prefix) {
            $filename = "{$prefix}{$join}{$filename}";
        }

        if (is_array($suffix)) {
            $suffix = implode($join, $suffix);
        }
        if ($suffix) {
            $filename = "{$filename}{$join}{$suffix}";
        }

        // Use the old extension if a new one was not specified.
        if ($extension == '') {
            $extension = ($pathinfo['extension'] ?? '');
        }
        if ($extension) {
            $filename = "{$filename}.{$extension}";
        }

        return $dirname.$filename;
    }

    /**
     * get_random_chars
     *
     * @param integer $length
     * @param string $str of chars from which to select randomly
     * @return string of $length characters selected randomly from $str
     */
    public static function get_random_chars($length=4, $str='') {
        if ($str == '') {
            $str = implode(range('a', 'z'));
        }
        return substr(str_shuffle($str), 0, $length);
    }
}
