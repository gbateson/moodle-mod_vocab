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
 * tool/import/lang/en/vocabai_chatgpt.php
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'ChatGPT AI assistant for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_chatgpt plugin does not store any personal data.';
$string['chatgpt'] = 'ChatGPT';

$string['chatgpturl_help'] = 'The URL of ChatGPT\'s API e.g. https://api.openai.com/v1/completions';
$string['chatgpturl'] = 'ChatGPT url';

$string['chatgptkey_help'] = 'The key required to access ChatGPT\'s API. This usually starts "sk-" followed by 48 random letters and numbers.';
$string['chatgptkey'] = 'ChatGPT key';

$string['chatgptmodel_help'] = 'The ChatGPT model to be used e.g. gpt-3.5-turbo, gpt-4';
$string['chatgptmodel'] = 'ChatGPT model';

$string['keysownedbyotherusers'] = 'Keys owned by other users';
$string['keysownedbyme'] = 'Keys owned by me';

$string['keysownedbyme'] = 'Keys owned by me';
$string['keysownedbyothers'] = 'Keys owned by other users';
$string['otherkeysownedbyme'] = 'Other keys owned by me';

$string['addnewkey'] = 'Add a new key';
$string['editkey'] = 'Edit existing key';
$string['key'] = 'Key';
$string['owner'] = 'Owner';

$string['sharedfrom'] = 'Shared from';
$string['sharedfrom_help'] = 'The key is shared starting from, and including, this date and time.';

$string['shareduntil'] = 'Shared until';
$string['shareduntil_help'] = 'The key is shared up to, and including, this date and time.';

$string['sharedanydate'] = 'Shared forever';
$string['sharedfromdate'] = 'Shared from {$a}';
$string['shareduntildate'] = 'Shared until {$a}';
$string['sharedfromuntildate'] = 'Shared from {$a->from} until {$a->until}';

$string['sharingperiod'] = 'Sharing period';
$string['sharingcontext'] = 'Sharing context';
$string['sharingcontext_help'] = 'The context in which this key can be shared.';

$string['edit'] = 'Edit';
$string['copy'] = 'Copy';
$string['delete'] = 'Delete';

$string['addmissingvalue'] = 'Please add a value here.';

$string['deletekey'] = 'Delete API key for ChatGPT';
$string['confirmdeletekey'] = 'Are you sure you want to delete this key?';

$string['copykey'] = 'Copy API key for ChatGPT';
$string['confirmcopykey'] = 'Are you sure you want to copy this key?';

$string['editcompleted'] = 'The modified key was successfully saved.';
$string['editcancelled'] = 'Editing of the key was cancelled.';

$string['copycompleted'] = 'The key was successfully copied.';
$string['copycancelled'] = 'Copying of the key was cancelled.';

$string['deletecompleted'] = 'The key was successfully deleted.';
$string['deletecancelled'] = 'Key deletion was cancelled.';

$string['sharedinsystemcontext'] = 'Shared throughout this entire site Moodle site';
$string['sharedincoursecatcontext'] = 'Shared in all courses in the current course category';
$string['sharedincoursecontext'] = 'Shared in all activities in the current course';
$string['sharedinvocabcontext'] = 'Shared only in the current Vocabulary activity';
$string['sharedinunknowncontext'] = 'Shared in unknown context: {$a}';

$string['nokeysfound'] = 'No keys found';

$string['note'] = 'Note';
$string['cannoteditkeys'] = 'You cannot edit these keys.';

$string['temperature_help'] = 'This setting controls the randomness of tokens. A low value (e.g. 0.2) means that only the most likely tokens will be chosen. A high value (e.g. 0.7) means the output will be more diverse and creative.';
$string['temperature'] = 'Temperature';

$string['top_p_help'] = 'A percentage that limits the subset of possible tokens that the AI engine uses to generate content. A low value (e.g. 0.1) considers only a small set of tokens. A high value (e.g. 0.7) increases the number of candidate tokens.';
$string['top_p'] = 'Top-P';
