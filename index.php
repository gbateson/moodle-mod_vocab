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
 * index.php: This page lists all the instances of mod_vocab in a particular course
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require_once('../../config.php'); // get $CFG
require_once($CFG->dirroot.'/mod/vocab/lib.php');

$id = required_param('id', PARAM_INT); // course id

$PAGE->set_url('/mod/vocab/index.php', ['id' => $id]);

if (! $course = $DB->get_record('course', ['id' => $id])) {
    throw new \moodle_exception('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

$params = ['context' => context_course::instance($id)];
$event = \mod_vocab\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strsingle = get_string('modulename', 'vocab');
$strplural = get_string('modulenameplural', 'vocab');
$PAGE->set_title($strplural);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strplural);
echo $OUTPUT->header();

if (! $instances = get_all_instances_in_course('vocab', $course)) {
    $url = new moodle_url('/course/view.php', ['id' => $course->id]);
    notice(get_string('thereareno', 'moodle', $strplural), $url);
}

$timenow = time();
$strname = get_string('name');
$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = [$strsectionname, $strname];
    $table->align = ['center', 'left'];
} else {
    $table->head  = [$strname];
}

foreach ($instances as $instance) {
    $url = new moodle_url('/mod/vocab/view.php', ['id' => $instance->coursemodule]);
    $link = html_writer::link($url, $instance->name, ($instance->visible ? null : ['class' => 'dimmed']));
    if ($usesections) {
        $table->data[] = [get_section_name($course, $instance->section), $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();

