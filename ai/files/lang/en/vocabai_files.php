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
 * tool/import/lang/en/vocabai_files.php
 *
 * @package    vocabai_files
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'AI training files for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_files plugin does not store any personal data.';
$string['files'] = 'Training files';

$string['filedescription_help'] = 'A description for the training file. This should be short but meaningful, descriptive and unique.';
$string['filedescription'] = 'Description';

$string['fileitemid_help'] = 'The AI training file. This file contains samples of suitable responses to certain prompts. It is used for fine-tuning of the response from the AI assistant. This is not required, but it can greatly increase the usefulness of the AI-geneerated content.';
$string['fileitemid'] = 'File upload';

$string['filesownedbyotherusers'] = 'Training files owned by other users';
$string['filesownedbyme'] = 'Training files owned by me';

$string['filesownedbyme'] = 'AI training files owned by me';
$string['filesownedbyothers'] = 'AI training files owned by other users';
$string['otherfilesownedbyme'] = 'Other AI training files owned by me';

$string['addnewfile'] = 'Add a new AI training file';
$string['editfile'] = 'Edit existing AI training file';
$string['owner'] = 'Owner';

$string['deletefile'] = 'Delete AI training file';
$string['confirmdeletefile'] = 'Are you sure you want to delete this file?';

$string['copyfile'] = 'Copy AI training file';
$string['confirmcopyfile'] = 'Are you sure you want to copy this file?';

$string['editcompleted'] = 'The modified file was successfully saved.';
$string['editcancelled'] = 'Editing of the file was cancelled.';

$string['copycompleted'] = 'The file was successfully copied.';
$string['copycancelled'] = 'Copying of the file was cancelled.';

$string['deletecompleted'] = 'The file was successfully deleted.';
$string['deletecancelled'] = 'File deletion was cancelled.';

$string['nofilesfound'] = 'No training files found';

$string['note'] = 'Note';
$string['cannoteditfiles'] = 'You cannot edit these training files.';
