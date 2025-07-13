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
 * tool/redotask/classes/form.php
 *
 * @package    vocabtool_redotask
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_redotask;

/**
 * form
 *
 * @package    vocabtool_redotask
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var int Execute task via this vocab tool. */
    const TASK_EXECUTOR_VOCABTOOL = 0;

    /** @var int Execute task via site admin tool interface. */
    const TASK_EXECUTOR_ADMINTOOL = 1;

    /** @var int Execute task via scheduled cron process. */
    const TASK_EXECUTOR_CRON = 2;

    /** @var int Task user can be any value. */
    const TASK_USER_ANY = 0;

    /** @var int Task context can be any value. */
    const TASK_CONTEXT_ANY = 0;

    /** @var int Task component can be any value. */
    const TASK_COMPONENT_ANY = 'any';

    /**
     * Defines the form fields for selecting and redoing vocab tasks.
     *
     * Adds form headings, dropdown filters, and a submit button.
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $this->set_form_id($mform);

        $tasks = $this->get_tasks();
        if (empty($tasks)) {
            $msg = $this->get_string('notasks');
            $msg = $OUTPUT->notification($msg, 'warning');
            $mform->addElement('html', $msg);
        } else {
            $name = 'taskfilters';
            $this->add_heading($mform, $name, true);

            $name = 'taskuser';
            $options = $this->get_taskusers();
            $this->add_field_select($mform, $name, $options, PARAM_INT);

            $name = 'taskcontext';
            $options = $this->get_taskcontexts();
            $this->add_field_select($mform, $name, $options, PARAM_INT);

            $name = 'taskcomponent';
            $options = $this->get_taskcomponents();
            $this->add_field_select($mform, $name, $options, PARAM_PLUGIN);

            $name = 'tasks';
            $this->add_heading($mform, $name, true);

            $name = 'task';
            $this->add_field_select($mform, $name, $tasks, PARAM_INT);

            $name = 'taskexecutor';
            $options = $this->get_taskexecutors();
            $this->add_field_select($mform, $name, $options, PARAM_INT);

            // Use "Redo task" as the label for the submit button.
            $label = $this->get_string('redotask');
            $this->add_action_buttons(true, $label);
        }
    }

    /**
     * If there are errors return array of errors ("fieldname" => "error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname" => value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];
        if (isset($data['taskexecutor'])) {
            $taskexecutor = intval($data['taskexecutor']);
        } else {
            $taskexecutor = self::TASK_EXECUTOR_VOCABTOOL;
        }
        if ($taskexecutor == self::TASK_EXECUTOR_VOCABTOOL ||
            $taskexecutor == self::TASK_EXECUTOR_ADMINTOOL) {
            $name = 'task';
            if (empty($data[$name])) {
                $errors[$name] = $this->get_string('selecttask');
            }
        }
        if (count($errors)) {
            return $errors;
        } else {
            return true;
        }
    }

    /**
     * Gets a list of users who have created vocab-related tasks.
     *
     * @return array List of users formatted as [id] fullname.
     */
    public function get_taskusers() {
        global $DB, $USER;
        $taskusers = [];
        if (is_siteadmin()) {
            $taskusers[self::TASK_USER_ANY] = $this->get_string('taskuserany');
            $select = $DB->sql_like('component', '?');
            $params = ['vocab%'];
            if ($tasks = $DB->get_records_select_menu('task_adhoc', $select, $params, 'userid', 'id,userid')) {
                list($select, $params) = $DB->get_in_or_equal(array_unique($tasks));
                if ($users = $DB->get_records_select('user', "id $select", $params, 'id')) {
                    foreach ($users as $userid => $user) {
                        $taskusers[$userid] = "[$userid] ".fullname($user);
                    }
                }
            }
        } else {
            $taskusers[$USER->id] = '['.$USER->id.'] '.fullname($USER);
        }
        return $taskusers;
    }

    /**
     * Gets a list of context IDs and their readable names for task filtering.
     *
     * @return array Context options for the form select element.
     */
    public function get_taskcontexts() {
        $taskcontexts = [];
        if (is_siteadmin()) {
            $taskcontexts[self::TASK_CONTEXT_ANY] = $this->get_string('taskcontextany');
        }
        $contexts = $this->get_vocab()->get_writeable_contexts('id', 'contextlevel');
        arsort($contexts); // Sort by context level.
        foreach ($contexts as $contextid => $contextlevel) {
            if ($contextlevel == CONTEXT_USER) {
                unset($contexts[$contextid]); // Skip USER context.
            } else {
                $context = \context::instance_by_id($contextid);
                $taskcontexts[$contextid] = "[$contextid] ".$context->get_context_name();
            }
        }
        return $taskcontexts;
    }

    /**
     * Gets a list of components with registered vocab-related tasks.
     *
     * @return array Component options with localized plugin names.
     */
    public function get_taskcomponents() {
        global $DB;
        $taskcomponents = [];
        if (is_siteadmin()) {
            $taskcomponents[self::TASK_COMPONENT_ANY] = $this->get_string('taskcomponentany');
        }
        $select = $DB->sql_like('component', '?');
        $params = ['vocab%'];
        if ($tasks = $DB->get_records_select_menu('task_adhoc', $select, $params, 'component', 'id,component')) {
            foreach ($tasks as $taskid => $component) {
                $taskcomponents[$component] = "[$component] ".get_string('pluginname', $component);
            }
        }
        return $taskcomponents;
    }

    /**
     * Gets a list of individual vocab-related ad hoc tasks.
     *
     * @return array Task labels including plugin and custom data.
     */
    public function get_tasks() {
        global $DB;

        $select = $DB->sql_like('component', '?');
        $params = ['vocab%'];

        if ($tasks = $DB->get_records_select('task_adhoc', $select, $params)) {
            foreach ($tasks as $taskid => $task) {
                list($subplugin, $dir, $name) = explode('\\', trim($task->classname, '\\'), 3);
                $data = json_decode($task->customdata);
                foreach ($data as $name => $value) {
                    $data->$name = "$name = $value";
                }
                if ($data = implode(', ', (array)$data)) {
                    $data = " ($data)";
                }
                $tasks[$taskid] = str_replace('\\', '/', "[$taskid] ".get_string('pluginname', $subplugin).$data);
            }
        } else {
            $tasks = [];
        }

        return $tasks;
    }

    /**
     * Gets available task execution methods (e.g., tool, admin, cron).
     *
     * @return array Executor options for running the selected task.
     */
    public function get_taskexecutors() {
        $taskexecutors = [];

        // Cache the label seperator, e.g. ": ".
        $labelsep = get_string('labelsep', 'langconfig');

        $i = self::TASK_EXECUTOR_VOCABTOOL;
        $text = $this->get_string('pluginname').$labelsep.
                $this->get_string('redotask');
        $taskexecutors[$i] = $text;

        if (is_siteadmin()) {

            $i = self::TASK_EXECUTOR_ADMINTOOL;
            $text = get_string('server', 'admin').$labelsep.
                    get_string('taskadmintitle', 'admin').$labelsep.
                    get_string('adhoctasks', 'tool_task');
            $taskexecutors[$i] = $text;

            $i = self::TASK_EXECUTOR_CRON;
            $text = $this->get_string('runtaskincron');
            $taskexecutors[$i] = $text;
        }
        return $taskexecutors;
    }
}
