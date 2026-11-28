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
 * mod/vocab/tool/dictionary/index.php
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require('../../../../config.php');

$tool = \vocabtool_dictionary\tool::create();

if (empty($tool->vocab) || empty($tool->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($tool->vocab->course, false, $tool->vocab->cm);
require_capability('mod/vocab:manage', $tool->vocab->context);

// Setup page url, title, heading and attributes,
// collapse navigation and set page layout.
$tool->setup_page();

$mform = $tool->get_mform();

if ($mform->is_cancelled()) {
    redirect($tool->vocab->view_url());
}

// Queue a task for each chosen word, then show the page again, with the tasks.
if ($data = $mform->get_data()) {
    $count = $mform->fill_dictionary($data);
    $message = get_string('tasksqueued', 'vocabtool_dictionary', $count);
    redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// The words, each with a link that shows its dictionary entry. Without JavaScript,
// the link opens this page with that word's entry shown ("wordid" parameter).
$wordid = optional_param('wordid', 0, PARAM_INT);
$entries = \vocabtool_dictionary\tool::export_word_list($tool->vocab, $wordid ? [$wordid] : []);
if ($entries['haswords']) {
    $entries['regionid'] = html_writer::random_id('vocab-entries-');
    echo $OUTPUT->heading(get_string('entries', 'vocabtool_dictionary'), 3);
    echo $OUTPUT->render_from_template('vocabtool_dictionary/entries', $entries);
    $PAGE->requires->js_call_amd('vocabtool_dictionary/entries', 'init', [$entries['regionid']]);
}

$mform->display();

// The tasks of this activity: what they added, or why they failed.
echo \vocabtool_dictionary\form::get_log_table($tool->vocab->id);

echo $OUTPUT->footer();
