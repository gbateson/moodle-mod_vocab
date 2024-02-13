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
 * mod/vocab/tool/phpdocs/index.php
 *
 * @package    vocabtool_phpdocs
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require('../../../../config.php');

$tool = \vocabtool_phpdocs\tool::create();

if (empty($tool->vocab) || empty($tool->vocab->cm)) {
    require_login($SITE);
    require_capability('moodle/site:config', context_system::instance());
} else {
    require_login($tool->vocab->course, false, $tool->vocab->cm);
    require_capability('mod/vocab:manage', $tool->vocab->context);
}

// Setup page url, title, heading and attributes,
// collapse navigation and set page layout.
$tool->setup_page();

$mform = $tool->get_mform();

// Get the form is cancelled,
// go back to where we came from.
if ($mform->is_cancelled()) {
    if (empty($tool->vocab)) {
        redirect($tool->site_url());
    }
    if ($tool->vocab->cm) {
        redirect($tool->vocab->view_url());
    } else {
        redirect($tool->vocab->course_url());
    }
}

echo $OUTPUT->header();

if ($mform->is_submitted() && $mform->is_validated()) {
    echo $OUTPUT->box_start();
    echo $tool->phpdocs($mform);
    echo $OUTPUT->box_end();
} else {
    $mform->display();
}

echo $OUTPUT->footer();
