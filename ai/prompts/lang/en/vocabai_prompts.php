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
 * tool/import/lang/en/vocabai_prompts.php
 *
 * @package    vocabai_prompts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'AI prompts for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_prompts plugin does not store any personal data.';
$string['prompts'] = 'Prompts';

$string['promptname_help'] = 'The name of the prompt. This should be short but meaningful, descriptive and unique.';
$string['promptname'] = 'Prompt name';

$string['prompttext_help'] = 'The prompt text that will be passed to the AI assistant. Placeholders for values are specified using double-braces. e.g. {{num-of-questions}}';
$string['prompttext'] = 'Prompt text';

$string['promptsownedbyotherusers'] = 'Prompts owned by other users';
$string['promptsownedbyme'] = 'Prompts owned by me';

$string['promptsownedbyme'] = 'Prompts owned by me';
$string['promptsownedbyothers'] = 'Prompts owned by other users';
$string['otherpromptsownedbyme'] = 'Other prompts owned by me';

$string['addnewprompt'] = 'Add a new prompt';
$string['editprompt'] = 'Edit existing prompt';
$string['owner'] = 'Owner';

$string['sharedfrom'] = 'Shared from';
$string['sharedfrom_help'] = 'The prompt is shared starting from, and including, this date and time.';

$string['shareduntil'] = 'Shared until';
$string['shareduntil_help'] = 'The prompt is shared up to, and including, this date and time.';

$string['sharedanydate'] = 'Shared forever';
$string['sharedfromdate'] = 'Shared from {$a}';
$string['shareduntildate'] = 'Shared until {$a}';
$string['sharedfromuntildate'] = 'Shared from {$a->from} until {$a->until}';

$string['sharingperiod'] = 'Sharing period';
$string['sharingcontext'] = 'Sharing context';
$string['sharingcontext_help'] = 'The context in which this prompt can be shared.';

$string['edit'] = 'Edit';
$string['copy'] = 'Copy';
$string['delete'] = 'Delete';

$string['addmissingvalue'] = 'Please add a value here.';

$string['deleteprompt'] = 'Delete AI prompt';
$string['confirmdeleteprompt'] = 'Are you sure you want to delete this prompt?';

$string['copyprompt'] = 'Copy AI prompt';
$string['confirmcopyprompt'] = 'Are you sure you want to copy this prompt?';

$string['editcompleted'] = 'The modified prompt was successfully saved.';
$string['editcancelled'] = 'Editing of the prompt was cancelled.';

$string['copycompleted'] = 'The prompt was successfully copied.';
$string['copycancelled'] = 'Copying of the prompt was cancelled.';

$string['deletecompleted'] = 'The prompt was successfully deleted.';
$string['deletecancelled'] = 'Prompt deletion was cancelled.';

$string['sharedinsystemcontext'] = 'Shared throughout this entire site Moodle site';
$string['sharedincoursecatcontext'] = 'Shared in all courses in the current course category';
$string['sharedincoursecontext'] = 'Shared in all activities in the current course';
$string['sharedinvocabcontext'] = 'Shared only in the current Vocabulary activity';
$string['sharedinunknowncontext'] = 'Shared in unknown context: {$a}';

$string['nopromptsfound'] = 'No prompts found';

$string['note'] = 'Note';
$string['cannoteditprompts'] = 'You cannot edit these prompts.';
