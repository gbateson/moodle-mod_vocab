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
 * mod/vocab/ai/dalle/index.php
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

require('../../../../config.php');

$ai = \vocabai_dalle\ai::create();

if (empty($ai->vocab) || empty($ai->vocab->cm)) {
    throw new moodle_exception('missingparam', 'error', '', 'id');
}

require_login($ai->vocab->course, false, $ai->vocab->cm);
require_capability('mod/vocab:manage', $ai->vocab->context);

// Setup page url, title, heading and attributes,
// collapse navigation and set page layout.
$ai->setup_page();

// If a config action has been cancelled, return to index page without config id.
if ($ai->config && $ai->action) {
    $cancelled = $ai->action.'cancelled';
    if (\mod_vocab\activity::optional_param(['cancel', $cancelled], '', PARAM_TEXT)) {
        redirect($ai->index_url(), $ai->get_string($cancelled));
    }
}

// The configid and action are passed via GET from main form to
// confirmation form and via POST from when returning to main form.
if ($ai->config && ($ai->action == 'copy' || $ai->action == 'delete')) {

    $confirmed = $ai->action.'confirmed';
    if (optional_param($confirmed, '', PARAM_TEXT) && confirm_sesskey()) {

        $completed = $ai->action.'completed';

        // Access to this config and action has already been checked
        // in "mod_vocab/classes/subpluginbase.php".

        if ($ai->action == 'delete') {
            $ai->delete_config();
            redirect($ai->index_url(), $ai->get_string($completed));
        }

        if ($ai->action == 'copy') {
            $ai->config->id = $ai->copy_config();
            redirect($ai->index_url(), $ai->get_string($completed));
        }

        // This shouldn't happen !!
        redirect($ai->index_url(), 'Unknown action: '.$ai->action);
    }


    // Action has not been confirmed, so display confirmation form.
    $heading = $ai->get_string($ai->action.'key');
    $message = $ai->get_string('confirm'.$ai->action.'key');

    echo $OUTPUT->header();
    echo $OUTPUT->heading($heading);
    echo $OUTPUT->box_start('generalbox', 'notice');

    $url = $PAGE->url;
    $url->param('action', $ai->action);
    $url->param('cid', $ai->config->id);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);

    // XHTML strict requires a container for the hidden input elements.
    echo html_writer::start_tag('fieldset', ['style' => 'display: none']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $ai->action]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'configid', 'value' => $ai->config->id]);
    echo html_writer::end_tag('fieldset');

    // XHTML strict requires a container for the contents of the form.
    echo html_writer::start_tag('div');

    echo $ai->get_mform()->format_config($ai->config, [], true);

    echo html_writer::start_tag('div', ['class' => 'buttons']);
    echo html_writer::tag('p', $message);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => $confirmed,
        'value' => $ai->get_string($ai->action),
        'class' => 'border rounded btn btn-danger mr-2',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => $cancelled,
        'value' => get_string('cancel'),
        'class' => 'border rounded btn btn-light ml-2',
    ]);
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// Get form data, if any.
if (($data = data_submitted()) && confirm_sesskey()) {
    $ai->save_config($data);
    $ai->unset_form_elements($data);
    if ($ai->config && $ai->action == 'edit') {
        $completed = $ai->action.'completed';
        redirect($ai->index_url(), $ai->get_string($completed));
    }
}

$mform = $ai->get_mform();

if ($mform->is_cancelled()) {
    redirect($ai->vocab->view_url());
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
