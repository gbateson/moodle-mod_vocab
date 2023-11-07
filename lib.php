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

defined('MOODLE_INTERNAL') || die();

/**
 * Specifies which Moodle features this plugin supports.
 * (for a full list of features, see "lib/moodlelib.php")
 *
 * @param string $feature a FEATURE_xxx constant e.g. mod_intro
 * @return boolean TRUE if this plugin supports this $feature; otherwise FALSE.
 * @todo Finish documenting this function
 */
function vocab_supports($feature) {
    // these constants are defined in "lib/moodlelib.php"
    $constants = array(
        'FEATURE_ADVANCED_GRADING' => false,
        'FEATURE_BACKUP_MOODLE2'   => true, // default=false
        'FEATURE_COMMENT'          => true,
        'FEATURE_COMPLETION_HAS_RULES' => true,
        'FEATURE_COMPLETION_TRACKS_VIEWS' => true,
        'FEATURE_CONTROLS_GRADE_VISIBILITY' => false,
        'FEATURE_GRADE_HAS_GRADE'  => true, // default=false
        'FEATURE_GRADE_OUTCOMES'   => true,
        'FEATURE_GROUPINGS'        => true, // default=false
        'FEATURE_GROUPMEMBERSONLY' => true, // default=false
        'FEATURE_GROUPS'           => true,
        'FEATURE_IDNUMBER'         => true, // Moodle >= 2.0
        'FEATURE_MOD_INTRO'        => true,
        'FEATURE_MODEDIT_DEFAULT_COMPLETION' => true,
        'FEATURE_NO_VIEW_LINK'     => false,
        'FEATURE_PLAGIARISM'       => false,
        'FEATURE_RATE'             => false,
        'FEATURE_DESCRIPTION' => true, // default=false
        'FEATURE_USES_QUESTIONS'   => false
    );
    if (defined('MOD_ARCHETYPE_OTHER')) {
        $constants['FEATURE_MOD_ARCHETYPE'] = MOD_ARCHETYPE_OTHER; // Moodle >= 2.x
    }
    if (defined('MOD_PURPOSE_ASSESSMENT')) {
        $constants['FEATURE_MOD_PURPOSE'] = MOD_PURPOSE_ASSESSMENT; // Moodle >= 4.x
    }
    foreach ($constants as $constant => $value) {
        if (defined($constant) && $feature == constant($constant)) {
            return $value;
        }
    }
    return false;
}

/**
 * vocab_add_instance
 *
 * @uses $DB
 * @param stdClass $data submitted from the form
 * @param moodleform $mform representing the Moodle form
 * @return xxx
 * @todo Finish documenting this function
 */
function vocab_add_instance($data, $mform) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $id = $DB->insert_record('vocab', $data);

    $time = (empty($data->completionexpected) ? null : $data->completionexpected);
    \core_completion\api::update_completion_date_event($data->coursemodule, 'vocab', $id, $time);

    return $id;
}

/**
 * vocab_update_instance
 *
 * @uses $DB
 * @param stdClass $data submitted from the form
 * @param moodleform $mform representing the Moodle form
 * @return xxx
 * @todo Finish documenting this function
 */
function vocab_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $DB->update_record('vocab', $data);

    $time = (empty($data->completionexpected) ? null : $data->completionexpected);
    \core_completion\api::update_completion_date_event($data->coursemodule, 'vocab', $data->id, $time);

    return true;
}

/**
 * vocab_delete_instance
 *
 * @uses $DB
 * @param xxx $id
 * @return xxx
 * @todo Finish documenting this function
 */
function vocab_delete_instance($id) {
    global $DB;

    if (! $vocab = $DB->get_record('vocab', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('vocab', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'vocab', $id, null);

    $params = array('vocabid', $vocab->id);
    // delete related records first (using $params), then ...
    $DB->delete_records('vocab', array('id' => $vocab->id));

    return true;
}

/**
 * Extends the global navigation tree by adding vocab nodes if there is a relevant content.
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * "vocab_extend_navigation" is called by
 * "load_activity" in "lib/navigationlib.php"
 *
 * @param navigation_node $vocabnode An object representing the navigation tree node of the vocab module instance
 * @param stdClass $course
 * @param stdClass $instance
 * @param cm_info $cm
 */
//function vocab_extend_navigation(navigation_node $node, stdclass $course, stdclass $vocab, cm_info $cm) {
//}

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

    $cm = $settings->get_page()->cm;
    $capability = 'mod/vocab:manage';
    if (has_capability($capability, $cm->context)) {

        // Locate the "Edit settings" node by its key,
        // and use the key for the node AFTER that as
        // the "beforekey" for the new nodes
        $keys = $vocabnode->get_children_key_list();
        $i = array_search('modedit', $keys);
        $i = ($i === false ? 0 : $i + 1);
        if (array_key_exists($i, $keys)) {
            $beforekey = $keys[$i];
        } else {
            $beforekey = null;
        }

        // define the order of subplugins
        $types = array(
            'report' => array(),
            'game' => array(),
            'tool' => array('wordlist', 'dictionary', 'questionbank', 'import', 'phpdocs')
        );

        foreach ($types as $type => $order) {

             // Create the "navigation_node" for this subplugin type.
            $label = get_string($type.'s', 'vocab'); // e.g. "tools"
            $node = navigation_node::create($label);
            $node->force_open();

            // Get list of mod_vocab subplugins of this type.
            $plugins = core_component::get_plugin_list("vocab{$type}");

            if (count($order)) {
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
            }

            // Add individual subplugins.
            foreach ($plugins as $name => $dir) {
                if (file_exists("$dir/lib.php")) {
                    require_once("$dir/lib.php");
                }
                $function = "vocab{$type}_{$name}_extend_settings_navigation";
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
    }
}

/**
 * vocab_extend_subplugin_navigation
 *
 * @param object $node the parent navigation node
 * @param integer $type of the navigation node (see "lib/navigationlib.php")
 * @param string $name of this subplugin e.g. phpdocs
 * @param object $cm the course module object for the the current vocabulary activity 
 */
function vocab_extend_subplugin_navigation(navigation_node $node, $type, $name, $cm) {
    $label = get_string($name, "vocab{$type}_{$name}");
    $url = "/mod/vocab/$type/$name/index.php";
    $url = new moodle_url($url, array('id' => $cm->id));
    $icon = new pix_icon($name, '', "vocab{$type}_{$name}");
    $node->add($label, $url, navigation_node::TYPE_SETTING, null, $name, $icon);
}

/**
 * Get icon mapping for font-awesome.
 * (see "https://fontawesome.com/search")
 */
function mod_vocab_get_fontawesome_icon_map() {
    // Actually this array is not used because the individual subplugins define their own icon map.
    return array(
        'mod_vocab:dictionary' => 'fa-book',
        'mod_vocab:import' => 'fa-database',
        'mod_vocab:phpdocs' => 'fa-code',
        'mod_vocab:wordlist' => 'fa-list'
    );
}
