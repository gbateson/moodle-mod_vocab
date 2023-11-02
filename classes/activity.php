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

    const PLUGINTYPE = 'mod';
    const PLUGINNAME = 'vocab';

    const MODE_LIVE = 0;
    const MODE_DEMO = 1;

    const ATTEMPTTYPE_ANY = 0;
    const ATTEMPTTYPE_RECENT = 1;
    const ATTEMPTTYPE_CONSECUTIVE = 2;

    const ATTEMPTDELAY_NONE = 0;
    const ATTEMPTDELAY_FIXED = -1;
    const ATTEMPTDELAY_EXPANDING = -2;

    const MYCOURSES_EVERYONE = 0;
    const MYCOURSES_STUDENTS = 1; // expand for students (collapse for teachers)
    const MYCOURSES_TEACHERS = 2; // expand for teachers (collapse for students)
    const MYCOURSES_NO_ONE = 3;

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

    /**
     * construct an instance of a Vocabulary activity
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
     * @param stdclass $vocab a row from the vocab table
     * @param stdclass $cm a row from the course_modules table
     * @param stdclass $course a row from the course table
     * @return vocab the new vocab object
     */
    static public function create($course=null, $cm=null, $instance=null, $context=null, $attempt=null) {
        global $DB;

        if ($instance || $cm) {
            if ($course === null) {
                $course = ($cm ? $cm->course : ($instance ? $instance->course : 0));
                $course = $DB->get_record('course', array('id' => $course), '*', MUST_EXIST);
            }
            if ($cm === null) {
echo '$cm';
print_object($cm);
echo '$instance';
print_object($instance);
die;
                $cm = get_coursemodule_from_instance(self::PLUGINNAME, $instance->id, 0, false, MUST_EXIST);
            }
            if ($instance === null) {
                $instance = $DB->get_record(self::PLUGINNAME, array('id' => $cm->instance), '*', MUST_EXIST);
            }
            return new activity($course, $cm, $instance);
        }

        // Course module id.
        if ($cmid = self::optional_param(['id', 'cmid'], 0, PARAM_INT)) {
            $cm = get_coursemodule_from_id(self::PLUGINNAME, $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
            $instance = $DB->get_record(self::PLUGINNAME, array('id'=>$cm->instance), '*', MUST_EXIST);
            return new activity($course, $cm, $instance);
        }

        // Vocab instance id.
        if ($vocabid = self::optional_param(['v', 'vid', 'vocabid'], 0, PARAM_INT)) {
            $instance = $DB->get_record('vocab', array('id' => $vocabid), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('vocab', $instanceid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
            return new activity($course, $cm, $instance);
        }

        return new activity();
    }

    /**
     * Return the value of an optional script parameter.
     *
     * @param array of possible $names for the parameter.
     * @return mixed the param $value
     */
    static protected function optional_param($names, $default, $type) {
        foreach ($names as $name) {
            if ($value = optional_param($name, $default, $type)) {
                return $value;
            }
        }
        return $default;
    }
    ////////////////////////////////////////////////////////////////////////////////
    // availability API
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * not_viewable
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function not_viewable() {
        return ($this->viewable ? false : true);
    }

    /**
     * not_playable
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function not_playable() {
        return ($this->playable ? false : true);
    }
    ////////////////////////////////////////////////////////////////////////////////
    // event and completion API
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * trigger_viewed_event_and_completion
     *
     * @uses $CFG
     * @todo Finish documenting this function
     */
    public function trigger_viewed_event_and_completion() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/completionlib.php');

        if ($this->instance) {
            $event = \mod_vocab\event\course_module_viewed::create(array(
               'objectid' => $this->cm->id,
               'context' => $this->context
            ));
            $event->add_record_snapshot('course', $this->course);
            $event->add_record_snapshot('course_modules', $this->cm);
            $event->add_record_snapshot('vocab', $this->instance);
            $event->trigger();

            // Update 'viewed' state if required by completion system
            $completion = new \completion_info($this->course);
            $completion->set_module_viewed($this->cm);
        }
    }
    ////////////////////////////////////////////////////////////////////////////////
    // url API
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * url
     *
     * @param string $filepath
     * @param boolean $escaped (optional, default=null)
     * @param array $params (optional, default=array)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function url($filepath, $escaped=null, $params=array()) {
        if ($this->cm) {
            $params = array_merge(array('id' => $this->cm->id), $params);
        }
        $url = '/'.$this->pluginpath.'/'.$filepath;
        $url = new \moodle_url($url, $params);
        if (is_bool($escaped)) {
            $url = $url->out($escaped);
        }
        return $url;
    }

    /**
     * @return moodle_url of this vocab's view page
     */
    public function view_url($escaped=null, $params=array()) {
        return $this->url('view.php', $escaped, $params);
    }

    /**
     * @return moodle_url of this vocab's report page
     */
    public function report_url($escaped=null, $params=array()) {
        return $this->url('report.php', $escaped, $params);
    }

    /**
     * @return moodle_url of this vocab's attempt page
     */
    public function attempt_url($escaped=null, $params=array()) {
        return $this->url('attempt.php', $escaped, $params);
    }

    /**
     * @return moodle_url of this vocab's attempt page
     */
    public function submit_url($escaped=null, $params=array()) {
        return $this->url('submit.php', $escaped, $params);
    }

    /**
     * @return moodle_url of the review page for an attempt at this vocab
     */
    public function review_url($escaped=null, $params=array()) {
        return $this->url('review.php', $escaped, $params);
    }

    /**
     * @return moodle_url of this course's vocab index page
     */
    public function index_url($escaped=null, $params=array()) {
        return new \moodle_url('/mod/vocab/index.php', array('id' => $this->course->id));
    }

    /**
     * course_url
     *
     * @return xxx
     * @todo Finish documenting this function
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
        $params = array('id' => $this->course->id);
        if ($sectionnum) {
            $params['section'] = $sectionnum;
        }
        return new \moodle_url('/course/view.php', $params);
    }

    /**
     * grades_url
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function grades_url() {
        return new \moodle_url('/grade/index.php', array('id' => $this->course->id));
    }

    /**
     * require
     *
     * @return boolean TRUE if user has required capability; otherwise FALSE.
     */
    public function require($capability){
        return require_capability("{$this->pluginpath}:$capability", $this->context);
    }

    /**
     * can
     *
     * @return boolean TRUE if user has required capability; otherwise FALSE.
     */
    public function can($capability){
        return has_capability("{$this->pluginpath}:$capability", $this->context);
    }

    /**
     * can_addinstance
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_addinstance(){
        return $this->can('addinstance');
    }

    /**
     * can_manage
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_manage(){
        return $this->can('manage');
    }

    /**
     * can_viewreports
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_viewreports(){
        return $this->can('viewreports');
    }

    /**
     * can_deleteattempts
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_deleteattempts(){
        return $this->can('deleteattempts');
    }

    /**
     * can_view
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_view(){
        return $this->can('view');
    }

    /**
     * can_attempt
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_attempt(){
        return $this->can('attempt');
    }

    /**
     * can_reviewmyattempts
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function can_reviewmyattempts(){
        return $this->can('reviewmyattempts');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // strings API
    ////////////////////////////////////////////////////////////////////////////////

    /*
     * get a string fro this plugin
     *
     * @param string $name, the name of the string
     * @param mixed $a, additional value or values required for the string
     * @return string
     **/
    public function get_string($name, $a=null) {
        return get_string($name, $this->plugin, $a);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // users API
    ////////////////////////////////////////////////////////////////////////////////

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

    /*
     * get_userids that are accessible to the current user.
     *
     * @return array of visible userids, if any; otherwise FALSE.
     **/
    public function get_userids($groupid=0) {
        global $DB;
        $mode = $this->get_groupmode();
        if ($mode==NOGROUPS || $mode==VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $this->context)) {
            $users = get_enrolled_users($this->context, 'mod/vocab:view', $groupid, 'u.id', 'id');
            if (empty($users)) {
                return false;
            }
            return array_keys($users);
        } else {
            if ($groupid) {
                $select = 'groupid = ?';
                $params = array($groupid);
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
        $demomode = \mod_vocab\activity::MODE_DEMO;
        return ($this->operationmode == $demomode);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // words API
    ////////////////////////////////////////////////////////////////////////////////

    /*
     * get_wordlist_info
     *
     * @return array of objects containing info about each word in the worlist for this Vocabulary activity; otherwise FALSE.
     */
    public function get_wordlist_info() {
        global $DB;
        $wordlist = array();
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
            $params = array('vocabid' => $this->id);
            if ($words = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
                foreach ($words as $word) {
                    $wordlist[$word->wordid] = $word->word;
                }
            }
            $wordinfo = (object)array(
                'wordid' => 0,
                'word' => '',
                'completed' => 99,
                'inprogress' => 99,
                'notstarted' => 99,
            );
        }
        return $wordlist;
    }

    /*
     * get_wordlist_words
     *
     * @return array of words, if any, in the word list for this Vocabulary activity; otherwise FALSE.
     */
    public function get_wordlist_words() {
        global $DB;
        $wordlist = array();
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
            $params = array('vocabid' => $this->id);
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
        $vowels = array('a', 'e', 'i', 'o', 'u', 'y',
                        'ai', 'ei', 'oi', 'oo', 'ou');  
        $consonants = array(
            'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 
            'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z',
            'ch', 'gh', 'ph', 'sh', 'th', 'wh', 
        );  
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
            case \mod_vocab\activity::MYCOURSES_TEACHERS:
                // Expand for teachers (collapse for students)
                $forceopen = $teacher;
                break;
            case \mod_vocab\activity::MYCOURSES_STUDENTS:
                // Expand for students (collapse for teachers)
                $forceopen = $student;
                break;
            case \mod_vocab\activity::MYCOURSES_NO_ONE:
                // Expand for no one (i.e. collapse for all)
                $forceopen = false;
                break;
        }

        if ($forceopen === false) {
            $rootnodekeys = array('site', 'myprofile','currentcourse',
                                  'mycourses', 'courses', 'users');
            foreach ($rootnodekeys as $nodekey) {
                if ($node = $PAGE->navigation->get($nodekey)) {
                    $node->forceopen = $forceopen;
                }
            }
        }
    }

    public function set_pagelayout() {
        global $PAGE;
        if ($this->pagelayout) {
            $PAGE->set_pagelayout($this->pagelayout);
        }
    }
}
