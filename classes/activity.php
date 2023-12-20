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

defined('MOODLE_INTERNAL') || die();

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

    /** @var integer database value to represent "live" mode */
    const MODE_LIVE = 0;

    /** @var integer database value to represent "demo" mode */
    const MODE_DEMO = 1;

    /** @var integer database value to represent "any" attempts */
    const ATTEMPTTYPE_ANY = 0;

    /** @var database value to represent "recent" attempts */
    const ATTEMPTTYPE_RECENT = 1;

    /** @var integer database value to represent "consecutive" attempts */
    const ATTEMPTTYPE_CONSECUTIVE = 2;

    /** @var integer database value to represent "no" delay between attempts */
    const ATTEMPTDELAY_NONE = 0;

    /** @var integer database value to represent a "fixed" delay between attempts */
    const ATTEMPTDELAY_FIXED = -1;

    /** @var integer database value to represent an "expanding" delay between attempts */
    const ATTEMPTDELAY_EXPANDING = -2;

    /** @var integer database value to denote expanding the navigation for "everyone" */
    const EXPAND_EVERYONE = 0;

    /** @var integer database value to denote expanding the navigation only for "students" (for teachers, it will be collapsed) */
    const EXPAND_STUDENTS = 1;

    /** @var integer database value to denote expanding the navigation only for "teachers" (for students, it will be collapsed) */
    const EXPAND_TEACHERS = 2;

    /** @var integer database value to denote expanding the navigation for "no one" */
    const EXPAND_NO_ONE = 3;

    /** @var stdclass vocab config settings */
    public $config = null;

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

    /** @var integer vocab activity introformat */
    public $introformat = 0;

    /** @var integer the "mode" of the current Vocab activity */
    public $operationmode = 0;

    /** @var integer denoting whether to show (=expand) or hide (=collapse) the "mycourses" navigation menu */
    public $expandnavigation = 3; // 0=everyone, 1=students, 2=teachers, 3=no one

    /** @var string specifying the preferred page layout, at least for the main "view" page */
    public $pagelayout = '';

    /** @var integer the minimum score required for mastery */
    public $activityscore = 0;

    /** @var integer the minimum number of attempts required for mastery */
    public $activitycount = 0;

    /** @var integer the minimum total duration of attempts required for mastery */
    public $activityduration = 0;

    /** @var integer the type of attempts considered for mastery conditions */
    public $activitytype = 0;

    /** @var integer minimum interval between attempts */
    public $activityinterval = 0;

    /** @var integer vocab activity open time */
    public $activityopen = 0;

    /** @var integer vocab activity close time */
    public $activityclose = 0;

    /** @var integer vocab games open time */
    public $gamesopen = 0;

    /** @var integer vocab games close time */
    public $gamesclose = 0;

    /** @var string vocab activity timecreated */
    public $timecreated = 0;

    /** @var string vocab activity timemodified */
    public $timemodified = 0;

    /** @var array of readable contexts the include this vocab activity */
    public $contexts = null;

    /**
     * Construct an instance of a Vocabulary activity
     *
     * @uses $COURSE
     * @uses $SITE
     * @param object $course a record form the "course" table in the Moodle database (optional, default=null)
     * @param string $cm the course module object for the the current vocabulary activity (optional, default=null)
     * @param object $instance a record form the "vocab" table in the Moodle database (optional, default=null)
     * @param object $context a record form the "context" table in the Moodle database (optional, default=null)
     */
    public function __construct($course=null, $cm=null, $instance=null, $context=null) {
        global $COURSE, $SITE;

        $this->plugin = self::PLUGINTYPE.'_'.self::PLUGINNAME;
        $this->pluginpath = self::PLUGINTYPE.'/'.self::PLUGINNAME;

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

        $this->time = time();

        // "viewable" means the user can view the first page of the activity.
        if ($this->can_manage()) {
            $this->viewable = true; // teacher/admin can always view.
        } else if ($this->activityopen && $this->activityopen > $this->time) {
            $this->viewable = false; // not open yet!
        } else if ($this->activityclose && $this->activityclose < $this->time) {
            $this->viewable = false; // already closed!
        } else {
            $this->viewable = $this->can_view();
        }

        // "playable" means the user can access the games.
        if ($this->can_manage()) {
            $this->playable = true; // teacher/admin can always play.
        } else if ($this->gamesopen && $this->gamesopen > $this->time) {
            $this->playable = false; // not open yet!
        } else if ($this->gamesclose && $this->gamesclose < $this->time) {
            $this->playable = false; // already closed!
        } else {
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
                $course = $DB->get_record('course', ['id' => $course], '*', MUST_EXIST);
            }
            if ($cm === null) {
                $cm = get_coursemodule_from_instance(self::PLUGINNAME, $instance->id, 0, false, MUST_EXIST);
            }
            if ($instance === null) {
                $instance = $DB->get_record(self::PLUGINNAME, ['id' => $cm->instance], '*', MUST_EXIST);
            }
            return new activity($course, $cm, $instance);
        }

        // Course module id.
        if ($cmid = self::optional_param(['id', 'cmid'], 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id(self::PLUGINNAME, $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $instance = $DB->get_record(self::PLUGINNAME, ['id' => $cm->instance], '*', MUST_EXIST);
            return new activity($course, $cm, $instance);
        }

        // Vocab instance id.
        if ($vocabid = self::optional_param(['v', 'vid', 'vocabid'], 0, PARAM_INT)) {
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
     * @return mixed, either an actual value from the form, or a suitable default.
     */
    public static function optional_param($names, $default, $type) {
        foreach ($names as $name) {
            if ($value = optional_param($name, '', $type)) {
                return $value;
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
     * @return boolean TRUE if user is prevented from viewing; otherwise return FALSE
     */
    public function not_viewable() {
        return ($this->viewable ? false : true);
    }

    /**
     * Determine if the current user is prevented from playing
     * (=interacting with) games in this Vocab activity.
     *
     * @return boolean TRUE if user is prevented from playing; otherwise return FALSE
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

            // Update 'viewed' state if required by completion system
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
     * @param boolean $escaped (optional, default=null)
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
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the main view page for this plugin
     */
    public function view_url($escaped=null, $params=[]) {
        return $this->url('view.php', $escaped, $params);
    }

    /**
     * Get the URL of the report page for this plugin.
     *
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the report page for this plugin
     */
    public function report_url($escaped=null, $params=[]) {
        return $this->url('report.php', $escaped, $params);
    }

    /**
     * Get the URL of the attempt page for this plugin.
     *
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the attempt page for this plugin
     */
    public function attempt_url($escaped=null, $params=[]) {
        return $this->url('attempt.php', $escaped, $params);
    }

    /**
     * Get the URL of the submit page for this plugin.
     *
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the submit page for this plugin
     */
    public function submit_url($escaped=null, $params=[]) {
        return $this->url('submit.php', $escaped, $params);
    }

    /**
     * Get the URL of the attempt page for this plugin.
     *
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=[])
     * @return string URL of the review page for this plugin
     */
    public function review_url($escaped=null, $params=[]) {
        return $this->url('review.php', $escaped, $params);
    }

    /**
     * Get the URL of the index page for this plugin.
     *
     * @param boolean $escaped (optional, default=null)
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
            // Moodle >= 2.3
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
     * @return boolean TRUE if user has required capability; otherwise FALSE.
     */
    public function require_login() {
        return require_login($this->course, false, $this->cm);
    }

    /**
     * require
     *
     * @param string $capability
     * @return xxx
     * @todo Finish documenting this function
     */
    public function require($capability) {
        return require_capability("{$this->pluginpath}:$capability", $this->context);
    }

    /**
     * can
     *
     * @param string $capability
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can($capability) {
        return has_capability("{$this->pluginpath}:$capability", $this->context);
    }

    /**
     * can_addinstance
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_addinstance() {
        return $this->can('addinstance');
    }

    /**
     * can_manage
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_manage() {
        return $this->can('manage');
    }

    /**
     * can_viewreports
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_viewreports() {
        return $this->can('viewreports');
    }

    /**
     * can_deleteattempts
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_deleteattempts() {
        return $this->can('deleteattempts');
    }

    /**
     * can_view
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_view() {
        return $this->can('view');
    }

    /**
     * can_attempt
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_attempt() {
        return $this->can('attempt');
    }

    /**
     * can_reviewmyattempts
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_reviewmyattempts() {
        return $this->can('reviewmyattempts');
    }

    /*////////////////////////////////////////
    // strings API
    ////////////////////////////////////////*/

    /**
     * get a string fro this plugin
     *
     * @param string $name
     * @param array $a additional value or values required for the language string (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }

    /*////////////////////////////////////////
    // users API
    ////////////////////////////////////////*/

    /**
     * get_groupmode
     *
     * @return xxx
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @return boolean
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * collapse_navigation
     *
     * @uses $PAGE
     * @todo Finish documenting this function
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
                // Expand for teachers (collapse for students)
                $forceopen = $teacher;
                break;
            case self::EXPAND_STUDENTS:
                // Expand for students (collapse for teachers)
                $forceopen = $student;
                break;
            case self::EXPAND_NO_ONE:
                // Expand for no one (i.e. collapse for all)
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
                // SYSTEM=10, COURSECAT=40, COURSE=50, MODULE=70
                CONTEXT_MODULE => \context_module::instance($this->cm->id),
                CONTEXT_COURSE => \context_course::instance($this->course->id),
                CONTEXT_COURSECAT => \context_coursecat::instance($this->course->category),
                CONTEXT_SYSTEM => \context_system::instance(),
            ];
        }
        return $this->contexts;
    }

    /**
     * Get readable contexts relevant to this vocab activity.
     *
     * @param string $keyfield (optional, default = '') the name of the field to use as keys in the return array. If blank, keys will be numeric.
     * @param string $valuefield (optional, default = '') the name of the field to use as values in the return array. If blank, complete context records will be returned.
     * @return array
     */
    public function get_readable_contexts($keyfield='', $valuefield='') {
        $readable = [];
        foreach ($this->get_contexts() as $context) {
            $key = ($keyfield ? $context->$keyfield : count($readable));
            $value = ($valuefield ? $context->$valuefield : $context);
            $readable[$key] = $value;
        }
        return $readable;
    }

    /**
     * Get writeable contexts relevant to this vocab activity.
     *
     * @param string $keyfield (optional, default = '') the name of the field to use as keys in the return array. If blank, keys will be numeric.
     * @param string $valuefield (optional, default = '') the name of the field to use as values in the return array. If blank, complete context records will be returned.
     * @return array
     */
    public function get_writeable_contexts($keyfield='', $valuefield='') {

        // Get array of contexts indexed by contextlevel (low to high).
        $contexts = $this->get_contexts();

        $writeable = [];
        foreach ($contexts as $context) {

            // MODULE=70, COURSE=50, COURSECAT=40, SYSTEM=10
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
                default:
                    // Unrecognized context - shouldn't happen !!
                    $capability = '';
            }
            if ($capability && has_capability($capability, $context)) {
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
     * @todo Finish documenting this function
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
}
