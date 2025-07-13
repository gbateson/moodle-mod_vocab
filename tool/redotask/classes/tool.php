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
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabtool_redotask
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabtool_redotask;

/**
 * tool
 *
 * @package    vocabtool_redotask
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'redotask';

    /**
     * Executes the selected adhoc task based on the form input and execution method.
     *
     * Depending on the selected task executor, this method will either:
     * - Redirect to the cron script (if TASK_EXECUTOR_CRON)
     * - Redirect to the admin tool's task runner (if TASK_EXECUTOR_ADMINTOOL)
     * - Run the task inline using this vocab tool (if TASK_EXECUTOR_VOCABTOOL)
     *
     * Optionally acquires locking mechanisms to prevent concurrent task execution.
     *
     * @param \moodleform $mform The submitted Moodle form containing task data.
     * @param bool $uselocks Whether to use record and cron locks during execution.
     *
     * @return string|false HTML output of task execution log and refresh link, or false if no task was selected.
     */
    public function redo_task($mform, $uselocks=false) {
        global $CFG, $DB, $FULLME;

        // Initialize HTML output string.
        $output = '';

        // Fetch the incoming form data.
        $data = $mform->get_data();

        /*//////////////////////
        taskuser: '2'
        taskcontext: '254'
        taskcomponent: 'vocabtool_questionbank'
        task: '1554'
        taskexecutor: '0'
        submitbutton: 'Redo task'
        //////////////////////*/

        $form = '\\vocabtool_redotask\\form';
        if ($data->taskexecutor == $form::TASK_EXECUTOR_CRON) {
            if (strpos($CFG->wwwroot, '/localhost/')) {
                if ($password = $DB->get_field('config', 'value', ['name' => 'cronremotepassword'])) {
                    $url = new moodle_url('/admin/cron.php', ['password' => $password]);
                    redirect(new moodle_url($url, $params));
                }
            }
            // Script will stop here.
        }

        // Sanity check on task id.
        if (empty($data->task)) {
            return false;
        }

        // Extract taskid.
        $taskid = intval($data->task);

        if ($data->taskexecutor == $form::TASK_EXECUTOR_ADMINTOOL) {
            $url = "/{$CFG->admin}/tool/task/run_adhoctasks.php";
            $params = ['id' => $taskid, 'confirm' => 1, 'sesskey' => sesskey()];
            redirect(new moodle_url($url, $params));
            // Script will stop here.
        }

        // Otherwise, we use this tool, i.e. TASK_EXECUTOR_VOCABTOOL.
        if ($uselocks) {
            $cronlockfactory = \core\lock\lock_config::get_lock_factory('cron');
            $lock = $cronlockfactory->get_lock('adhoc_' . $taskid, 0);
        } else {
            $cronlockfactory = false;
            $lock = true;
        }

        $msg = '';
        if ($lock) {

            $record = $DB->get_record('task_adhoc', ['id' => $taskid]);
            $task = \core\task\manager::adhoc_task_from_record($record);

            if ($uselocks) {
                $cronlock = $cronlockfactory->get_lock('core_cron', 10);
            } else {
                $cronlock = true;
            }

            // The global cron lock.
            if ($cronlock) {

                if ($uselocks) {
                    $task->set_lock($lock);
                    $task->set_cron_lock($cronlock);
                }

                $output .= \html_writer::start_tag('pre', ['class' => 'bg-dark text-light py-2 px-3']);
                ob_start();
                \core\task\manager::run_adhoc_from_cli($taskid);
                $output .= ob_get_contents();
                $output .= \html_writer::end_tag('pre');

                if ($uselocks) {
                    \core\task\manager::adhoc_task_complete($task);
                    $cronlock->release();
                } else {
                    // Mimic "adhoc_task_complete()" without locks.
                    // We only use this during development.
                    \core\task\logmanager::finalise_log();
                    $task->set_timestarted();
                    $task->set_hostname();
                    $task->set_pid();

                    // Delete the adhoc task record - it is finished.
                    $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
                }
            } else {
                $msg = ' Global lock not available.';
            }
            if ($uselocks) {
                $lock->release();
            }
        } else {
            $msg = ' Record lock not available.';
        }

        if ($msg) {
            $msg = get_string('error').get_string('labelsep', 'langconfig').$msg;
        } else {
            $msg = get_string('success');
        }
        $output .= \html_writer::tag('p', $msg);

        // Add link to refresh page.
        $msg = get_string('refresh');
        $msg = \html_writer::link($FULLME, $msg);
        $output .= \html_writer::tag('p', $msg, ['class' => 'my-2']);

        return $output;
    }
}
