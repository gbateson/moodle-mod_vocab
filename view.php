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
 * view.php
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require_once('../../config.php');

$vocab = \mod_vocab\activity::create();
if (empty($vocab)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

$vocab->require_login();
$vocab->require('view');

$PAGE->set_url($vocab->view_url());
$PAGE->set_title($vocab->name);
$PAGE->set_heading($vocab->course->fullname);

$PAGE->activityheader->set_attrs(['hidecompletion' => true]);

$vocab->collapse_navigation();
$vocab->set_pagelayout();

// Trigger module viewed event and completion.
$vocab->trigger_viewed_event_and_completion();

$renderer = $PAGE->get_renderer('mod_vocab');
$renderer->attach_activity($vocab);

echo $renderer->header();

if ($vocab->viewable) {
    echo $renderer->view_page();
} else if (isguestuser()) {
    // Guests can't view vocab activities, so
    // offer a choice of logging in or going back.
    echo $renderer->view_page_guest();
} else {
    // If user is not enrolled in this course
    // in a good enough role, tell them to enrol.
    echo $renderer->view_page_notenrolled();
}

echo $renderer->footer();
