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
 * tool/import/lang/en/vocabai_formats.php
 *
 * @package    vocabai_formats
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'AI output formats for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_formats plugin does not store any personal data.';
$string['formats'] = 'Formats';

$string['formatname_help'] = 'The name of the format. This should be short but meaningful, descriptive and unique.';
$string['formatname'] = 'Format name';

$string['formattext_help'] = 'The output format text that will be passed to the AI assistant. Placeholders for values are specified using double-braces. e.g. {{num-of-questions}}';
$string['formattext'] = 'Format text';

$string['formatsownedbyotherusers'] = 'Formats owned by other users';
$string['formatsownedbyme'] = 'Formats owned by me';

$string['formatsownedbyme'] = 'Formats owned by me';
$string['formatsownedbyothers'] = 'Formats owned by other users';
$string['otherformatsownedbyme'] = 'Other formats owned by me';

$string['addnewformat'] = 'Add a new format';
$string['editformat'] = 'Edit existing format';
$string['owner'] = 'Owner';

$string['deleteformat'] = 'Delete AI format';
$string['confirmdeleteformat'] = 'Are you sure you want to delete this output format?';

$string['copyformat'] = 'Copy AI format';
$string['confirmcopyformat'] = 'Are you sure you want to copy this output format?';

$string['editcompleted'] = 'The modified format was successfully saved.';
$string['editcancelled'] = 'Editing of the format was cancelled.';

$string['copycompleted'] = 'The format was successfully copied.';
$string['copycancelled'] = 'Copying of the format was cancelled.';

$string['deletecompleted'] = 'The format was successfully deleted.';
$string['deletecancelled'] = 'Format deletion was cancelled.';

$string['noformatsfound'] = 'No output formats found';

$string['note'] = 'Note';
$string['cannoteditformats'] = 'You cannot edit these output formats.';
