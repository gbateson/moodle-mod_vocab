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
 * lib.php: The main lib file for mod_vocab.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * Specify which Moodle features this plugin supports.
 * (For a full list of features, see "lib/moodlelib.php".)
 *
 * @param string $feature a FEATURE_xxx constant, e.g. FEATURE_MOD_INTRO
 * @return mixed true or false if the feature is (not) supported, a value for some features
 *     (e.g. the archetype or purpose), or null if the feature is unknown.
 */
function vocab_supports($feature) {
    // These constants are defined in "lib/moodlelib.php".
    $constants = [
        'FEATURE_ADVANCED_GRADING' => false,
        'FEATURE_BACKUP_MOODLE2' => true, // Default is FALSE.
        'FEATURE_COMMENT' => true,
        'FEATURE_COMPLETION_HAS_RULES' => true,
        'FEATURE_COMPLETION_TRACKS_VIEWS' => true,
        'FEATURE_CONTROLS_GRADE_VISIBILITY' => false,
        'FEATURE_GRADE_HAS_GRADE' => true, // Default is FALSE.
        'FEATURE_GRADE_OUTCOMES' => true,
        'FEATURE_GROUPINGS' => true, // Default is FALSE.
        'FEATURE_GROUPS' => true,
        'FEATURE_IDNUMBER' => true, // Available in Moodle >= 2.0.
        'FEATURE_MOD_INTRO' => true,
        'FEATURE_MODEDIT_DEFAULT_COMPLETION' => true,
        'FEATURE_NO_VIEW_LINK' => false,
        'FEATURE_PLAGIARISM' => false,
        'FEATURE_RATE' => false,
        'FEATURE_DESCRIPTION' => true, // Default is FALSE.
        'FEATURE_USES_QUESTIONS' => true, // The quiz game's attempts use the question engine.
        'FEATURE_CAN_DISPLAY' => true, // Available in Moodle >= 5.0.
    ];
    if (defined('MOD_ARCHETYPE_OTHER')) {
        $constants['FEATURE_MOD_ARCHETYPE'] = MOD_ARCHETYPE_OTHER; // Available in Moodle >= 2.x.
    }
    if (! class_exists('\\core_availability\\info')) {
        $constants['FEATURE_GROUPMEMBERSONLY'] = true; // Moodle <= 2.6
        // Moodle >= 2.7 uses the availability API (\core_availability\info)
        // Moodle >= 5.x treats any plugin that declares this feature as broken.
    }
    if (defined('MOD_PURPOSE_INTERACTIVECONTENT')) {
        $constants['FEATURE_MOD_PURPOSE'] = MOD_PURPOSE_INTERACTIVECONTENT; // Available in Moodle >= 4.x.
    }
    foreach ($constants as $constant => $value) {
        if (defined($constant) && $feature == constant($constant)) {
            return $value;
        }
    }
    return null; // The default value for the FEATURE will be used.
}

/**
 * Add a new Vocabulary activity, with its stages and its grade item. Games are
 * added later, on the activity's page.
 *
 * @param stdClass $data submitted from the settings form
 * @param moodleform $mform the settings form
 * @return int the id of the new activity
 */
function vocab_add_instance($data, $mform) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $id = $DB->insert_record('vocab', $data);
    $data->id = $id;
    vocab_save_stages($data);

    // Add the gradebook item for this Vocab acitivity.
    vocab_grade_item_update($data);

    $time = (empty($data->completionexpected) ? null : $data->completionexpected);
    \core_completion\api::update_completion_date_event($data->coursemodule, 'vocab', $id, $time);

    return $id;
}

/**
 * Update a Vocabulary activity, with its stages and its grade item. Its games are
 * managed on the activity's page, and are not changed here.
 *
 * @param stdClass $data submitted from the settings form
 * @param moodleform $mform the settings form
 * @return bool true
 */
function vocab_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $DB->update_record('vocab', $data);
    vocab_save_stages($data);

    // Add the gradebook item for this Vocab acitivity.
    vocab_grade_item_update($data);

    $time = (empty($data->completionexpected) ? null : $data->completionexpected);
    \core_completion\api::update_completion_date_event($data->coursemodule, 'vocab', $data->id, $time);

    return true;
}

/**
 * Save the stages of an activity, from its settings form.
 *
 * @param stdClass $data from the settings form, with the activity's id.
 */
function vocab_save_stages($data) {
    $stages = [];
    foreach (\mod_vocab\progress::STAGES as $stage) {
        $name = 'stage_' . $stage;
        if (!empty($data->$name)) {
            $stages[$stage] = [$data->{$name . '_pass'} ?? 1, $data->{$name . '_complete'} ?? 1];
        }
    }
    if ($stages) {
        \mod_vocab\progress::save_stages($data->id, $stages);
    }
}

/**
 * Delete a Vocabulary activity: its calendar events, its stages, games and word list,
 * students' attempts and progress, and its grade item. The words themselves are kept.
 *
 * @param int $id the id of the activity
 * @return bool true
 */
function vocab_delete_instance($id) {
    global $CFG, $DB;

    $vocab = $DB->get_record('vocab', ['id' => $id]);

    $params = ['modulename' => 'vocab', 'instance' => $id];
    if ($events = $DB->get_records('event', $params)) {
        foreach ($events as $event) {
            calendar_event::load($event)->delete();
        }
    }

    // Students' attempts and progress, and the activity's stages, games and word list.
    \mod_vocab\progress::delete_activity_data($id);
    if ($vocab) {
        // Delete the activity's grade item, and its grades.
        require_once($CFG->libdir . '/gradelib.php');
        grade_update('mod/vocab', $vocab->course, 'mod', 'vocab', $id, 0, null, ['deleted' => 1]);
    }

    // Finally delete the vocab record.
    $DB->delete_records('vocab', ['id' => $id]);

    return true;
}


/*////////////////////*/
// Gradebook API.
/*////////////////////*/

/**
 * Creates or updates grade items for the given vocab instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php.
 * Also used by vocab_update_grades().
 *
 * @param object $vocab object with extra cmidnumber and modname property
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function vocab_grade_item_update($vocab, $grades = null) {
    global $CFG;
    require_once($CFG->dirroot . '/lib/gradelib.php');

    // Sanity check on $vocab->id.
    if (empty($vocab->id) || empty($vocab->course)) {
        return;
    }

    // Set up params for grade_update().
    $params = ['itemname' => $vocab->name];
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    if (isset($vocab->cmidnumber)) {
        // The "cmidnumber" property may not be always present.
        $params['idnumber'] = $vocab->cmidnumber;
    }
    if (empty($vocab->grademax)) {
        $params['gradetype'] = GRADE_TYPE_NONE;
        // Note: when adding a new activity, a gradeitem will *not*
        // be created in the grade book if gradetype==GRADE_TYPE_NONE
        // A gradeitem will be created later if gradetype changes to GRADE_TYPE_VALUE
        // However, the gradeitem will *not* be deleted if the activity's
        // gradetype changes back from GRADE_TYPE_VALUE to GRADE_TYPE_NONE
        // Therefore, we force the removal of empty gradeitems.
        $params['deleted'] = true;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $vocab->grademax;
        $params['grademin']  = 0;
    }
    return grade_update('mod/vocab', $vocab->course, 'mod', 'vocab', $vocab->id, 0, $grades, $params);
}

/**
 * vocab_get_grades
 *
 * @param stdclass $vocab object with extra cmidnumber and modname property
 * @param integer $userid >0 update grade of specific user only, 0 means all participants
 * @param string $timefield (optional, default="timefinish")
 * @return array of grades
 */
function vocab_get_grades($vocab, $userid, $timefield = 'timefinish') {
    global $DB;
    $progress = new \mod_vocab\progress($vocab);
    if ($userid) {
        $userids = [$userid];
    } else {
        // Every student who has played a game in this activity.
        $sql = "SELECT DISTINCT vga.userid
                  FROM {vocab_game_attempts} vga
                  JOIN {vocab_game_instances} vgi ON vgi.id = vga.gameinstanceid
                 WHERE vgi.vocabid = ?";
        $userids = $DB->get_fieldset_sql($sql, [$vocab->id]);
    }
    $grades = [];
    foreach ($userids as $id) {
        $percent = $progress->get_grade((int) $id);
        if ($percent !== null) {
            $grades[$id] = (object) [
                'userid' => $id,
                'rawgrade' => $percent * $vocab->grademax / 100,
            ];
        }
    }
    return $grades;
}

/**
 * Update vocab grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdclass $vocab instance object with extra cmidnumber and modname property
 * @param integer $userid >0 update grade of specific user only, 0 means all participants
 * @param bool $nullifnone TRUE = force creation of NULL grade if this user has no grade
 * @return bool TRUE if successful, FALSE otherwise
 */
function vocab_update_grades($vocab = null, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;

    if ($vocab === null) {
        // Update/create grades for all vocabs.

        // Set up sql strings.
        $strupdating = get_string('updatinggrades', 'mod_vocab');
        $select = 'h.*, cm.idnumber AS cmidnumber';
        $from   = '{vocab} h, {course_modules} cm, {modules} m';
        $where  = 'h.id = cm.instance AND cm.module = m.id AND m.name = ?';
        $params = ['vocab'];

        // Get previous record index (if any).
        $configname = 'update_grades';
        $configvalue = get_config('mod_vocab', $configname);
        if (is_numeric($configvalue)) {
            $imin = intval($configvalue);
        } else {
            $imin = 0;
        }

        if ($imax = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where", $params)) {
            if ($rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
                if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                    $bar = false;
                } else {
                    $bar = new progress_bar('vocabupgradegrades', 500, true);
                }
                $i = 0;
                foreach ($rs as $vocab) {
                    // Update grade.
                    if ($i >= $imin) {
                        upgrade_set_timeout(); // Apply for more time (3 mins).
                        vocab_update_grades($vocab, $userid, $nullifnone);
                    }

                    // Update progress bar.
                    $i++;
                    if ($bar) {
                        $bar->update($i, $imax, $strupdating . ": ($i/$imax)");
                    }

                    // Update record index.
                    if ($i > $imin) {
                        set_config($configname, $i, 'mod_vocab');
                    }
                }
                $rs->close();
            }
        }

        // Delete the record index.
        unset_config($configname, 'mod_vocab');

        return; // Finish here.
    }

    // Sanity check on $vocab->id.
    if (! isset($vocab->id)) {
        return false;
    }

    $grades = vocab_get_grades($vocab, $userid);

    if (count($grades)) {
        vocab_grade_item_update($vocab, $grades);
    } else if ($userid && $nullifnone) {
        // No grades for this user, but we must force the creation of a "null" grade record.
        vocab_grade_item_update($vocab, (object)['userid' => $userid, 'rawgrade' => null]);
    } else {
        // No grades and no userid.
        vocab_grade_item_update($vocab);
    }
}

/*////////////////////*/
// Navigation API.
/*////////////////////*/

/**
 * Adds module specific settings to the settings block
 *
 * This function is called when the context for the page is a vocab module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * "vocab_extend_settings_navigation" is called by
 * "load_module_settings" in "lib/navigationlib.php"
 *
 * @param navigation_node $node An object representing the navigation tree node of the vocab module instance
 * @param stdClass $course A record from the "course" table in the database.
 * @param stdClass $vocab A record form the "vocab" table in the database.
 * @param cm_info $cm An object representing the current Vocab course module.
 * @return void (but may add items to $node)
 */
function vocab_extend_navigation(navigation_node $node, stdClass $course, stdClass $vocab, cm_info $cm) {
}

/**
 * Adds module specific settings to the settings block
 *
 * This function is called when the context for the page is a vocab module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * "vocab_extend_settings_navigation" is called by
 * "load_module_settings" in "lib/navigationlib.php"
 *
 * @param settings_navigation $settings The settings navigation node
 * @param navigation_node $vocabnode The node to add module settings to
 * @return void (but may add items to $vocabnode)
 */
function vocab_extend_settings_navigation(settings_navigation $settings, navigation_node $vocabnode) {
    global $CFG, $PAGE;

    // In Moodle >= 4.x we can use $cm = $settings->get_page()->cm
    // but for compatability with Moodle 3.11, we use the line below.
    $cm = $PAGE->cm;
    $capability = 'mod/vocab:manage';
    if (has_capability($capability, $cm->context)) {
        // Locate the "Edit settings" node by its key,
        // and use the key for the node AFTER that as
        // the "beforekey" for the new nodes.
        $keys = $vocabnode->get_children_key_list();
        $i = array_search('modedit', $keys);
        $i = ($i === false ? 0 : $i + 1);
        if (array_key_exists($i, $keys)) {
            $beforekey = $keys[$i];
        } else {
            $beforekey = null;
        }

        // Define the order of subplugins. Other subplugins will
        // be added alphabetically after the ones defined here.
        $types = [
            // Games are played from the activity's page, so they are not in this menu.
            'report' => [],
            'tool' => ['dictionary', 'wordlist', 'questionbank', 'import'],
            'util' => [],
        ];

        foreach ($types as $type => $order) {
            // Cache the plugin type.
            $plugintype = "vocab{$type}";

             // Create the "navigation_node" for this subplugin type.
            $label = get_string($type . 's', 'vocab'); // E.g. "tools".
            $node = navigation_node::create($label);
            $node->force_open();

            // Get list of mod_vocab subplugins of this type.
            $plugins = core_component::get_plugin_list($plugintype);

            // Sort the unordered items alphabetically.
            $labels = [];
            foreach ($plugins as $name => $dir) {
                if (in_array($name, $order)) {
                    continue; // Item's position is fixed in $order.
                }
                $labels[$name] = get_string($name, "vocab{$type}_{$name}");
            }
            asort($labels);

            // Append the sorted labels to the fixed items in $order.
            $order = array_merge($order, array_keys($labels));

            // Reorder the $plugins accoding to the $order array.
            $order = array_flip($order);
            foreach (array_keys($order) as $name) {
                if (array_key_exists($name, $plugins)) {
                    $order[$name] = $plugins[$name];
                    unset($plugins[$name]);
                } else {
                    unset($order[$name]);
                }
            }
            $plugins = $order + $plugins;

            // Add individual subplugins.
            foreach ($plugins as $name => $dir) {
                if (file_exists("$dir/lib.php")) {
                    require_once("$dir/lib.php");
                }
                $function = "{$plugintype}_{$name}_extend_settings_navigation";
                if (function_exists($function)) {
                    $function($settings, $node);
                } else {
                    $function = 'vocab_extend_subplugin_navigation';
                    $function($node, $type, $name, $cm);
                }
            }

            if ($node->has_children()) {
                $vocabnode->add_node($node, $beforekey);
            }
        }

        // Links to the AI bridge, where teachers set up the AI providers,
        // prompts and output formats that are used to generate questions.
        $node = navigation_node::create(get_string('ais', 'vocab'));
        $node->force_open();
        require_once($CFG->dirroot . '/admin/tool/aibridge/lib.php');
        $coursecontext = context_course::instance($cm->course);
        tool_aibridge_extend_navigation_course($node, $PAGE->course, $coursecontext);
        if ($node->has_children()) {
            $vocabnode->add_node($node, $beforekey);
        }
    }
}

/**
 * Add a navigation node for the specified subplugin $type and $name.
 *
 * @param navigation_node $node the parent navigation node
 * @param string $type of this plugin e.g. "tool"
 * @param string $name of this subplugin e.g. "phpdocs"
 * @param object $cm the course module object for the the current vocabulary activity
 * @return void but adds a navigation node for the specified subplugin $type and $name
 */
function vocab_extend_subplugin_navigation(navigation_node $node, $type, $name, $cm) {
    $label = get_string($name, "vocab{$type}_{$name}");
    $url = "/mod/vocab/$type/$name/index.php";
    $url = new moodle_url($url, ['id' => $cm->id]);
    $icon = new pix_icon($name, '', "vocab{$type}_{$name}");
    $node->add($label, $url, navigation_node::TYPE_SETTING, null, $name, $icon);
}

/**
 * Adds a navigation node if the current user is the module developer.
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to which the node for this tool will be added.
 * @param string $type of the subplugin, e.g. "tool".
 * @param string $name of the subplugin, e.g. "phpdocs".
 */
function vocab_extend_navigation_for_developer(settings_navigation $settings, navigation_node $node, $type, $name) {
    global $PAGE;
    // Restrict this tool to use by the main developer in the development environment.
    if (\mod_vocab\activity::is_developer()) {
        $function = 'vocab_extend_subplugin_navigation';
        $function($node, $type, $name, $PAGE->cm);
    }
}

/**
 * Get icon mapping for font-awesome.
 * (see "https://fontawesome.com/search")
 */
function mod_vocab_get_fontawesome_icon_map() {
    // Actually this array is not used because the individual subplugins define their own icon map.
    return [];
}

/**
 * Called by the AI bridge (tool_aibridge) when it is installed. If the
 * bridge was installed in the same upgrade as this plugin, the old AI
 * settings could not be moved into it then, so they are moved now.
 */
function mod_vocab_aibridge_installed() {
    if (get_config('mod_vocab', 'movetoaibridge')) {
        \mod_vocab\aibridge_migration::run();
    }
}

/**
 * Are any of these questions used in students' attempts at the activities' games,
 * e.g. the quiz game? Moodle hides questions that are in use, instead of deleting
 * them, so that the attempts can still be reviewed.
 *
 * @param int[] $questionids
 * @return bool
 */
function mod_vocab_questions_in_use($questionids) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    return question_engine::questions_in_use(
        $questionids,
        // The component is a constant: parameters here would be lost when core merges them with its own.
        new qubaid_join('{question_usages} quba', 'quba.id', "quba.component = 'mod_vocab'")
    );
}

/**
 * Serve the files of questions in the activity's games, e.g. the images and
 * audio in the questions of the quiz game. Students can see the files of their
 * own attempts, and teachers can see the files of every attempt.
 *
 * @param stdClass $course
 * @param context $context the question's context.
 * @param string $component e.g. "question".
 * @param string $filearea e.g. "questiontext".
 * @param int $qubaid the question usage of the attempt.
 * @param int $slot
 * @param array $args the rest of the file's path.
 * @param bool $forcedownload
 * @param array $options
 */
function mod_vocab_question_pluginfile(
    $course,
    $context,
    $component,
    $filearea,
    $qubaid,
    $slot,
    $args,
    $forcedownload,
    array $options = []
) {
    global $DB, $USER;
    $sql = "SELECT vga.*, vgi.vocabid
              FROM {vocab_game_attempts} vga
              JOIN {vocab_game_instances} vgi ON vgi.id = vga.gameinstanceid
             WHERE vga.uniqueid = ?";
    $attempt = $DB->get_record_sql($sql, [$qubaid]);
    if (!$attempt) {
        send_file_not_found();
    }
    $cm = get_coursemodule_from_instance('vocab', $attempt->vocabid, $course->id, false, MUST_EXIST);
    require_login($course, false, $cm);
    if ($attempt->userid != $USER->id) {
        require_capability('mod/vocab:viewreports', context_module::instance($cm->id));
    }
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
