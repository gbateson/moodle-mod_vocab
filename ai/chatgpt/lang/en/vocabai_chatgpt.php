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

$string['keysownedbyotherusers'] = 'Keys owned by other users';
$string['keysownedbyme'] = 'Keys owned by me';

$string['keysownedbyme'] = 'Keys owned by me';
$string['keysownedbyothers'] = 'Keys owned by other users';
$string['otherkeysownedbyme'] = 'Other keys owned by me';

$string['addnewkey'] = 'Add a new key';
$string['editkey'] = 'Edit existing key';
$string['key'] = 'Key';
$string['owner'] = 'Owner';

$string['chatgpturl_help'] = 'The URL of ChatGPT\'s API e.g. https://api.openai.com/v1/completions';
$string['chatgpturl'] = 'ChatGPT url';

$string['chatgptkey_help'] = 'The key required to access ChatGPT\'s API. This usually starts "sk-" followed by 48 random letters and numbers.';
$string['chatgptkey'] = 'ChatGPT key';

$string['chatgptmodel_help'] = 'The ChatGPT model to be used e.g. gpt-3.5-turbo, gpt-4';
$string['chatgptmodel'] = 'ChatGPT model';

$string['chatgptmodelid'] = 'ChatGPT tuned model';
$string['chatgptmodelid_help'] = 'The ChatGPT model that has been tuned using this tuning file.';

$string['gpt-3.5-turbo'] = 'A fast, inexpensive model for simple tasks.';
$string['gpt-4'] = 'Multilingual and better at complex reasoning.';
$string['gpt-4-turbo'] = 'Simlilar to GPT-4 but can understand images.';
$string['gpt-4o-mini'] = 'Intelligent small model for fast, lightweight tasks.';
$string['gpt-4o'] = 'High-intelligence flagship model for complex, multi-step tasks.';

$string['temperature_help'] = 'This setting controls how randomly the AI engine choses the next word. A low value (e.g. 0.2) means that only one of the most likely words will be chosen. A high value (e.g. 0.7) means that less likely words could also be chosen, resulting in more diverse and creative output.';
$string['temperature'] = 'Temperature';

$string['top_p_help'] = 'This value limits the size of the pool of words that the AI engine uses to generate each successive word. A low value (e.g. 0.1) considers only a small set of the most likely words. A high value (e.g. 0.7) increases the number of candidate words.';
$string['top_p'] = 'Top-P';

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

$string['nokeysfound'] = 'No keys found';

$string['note'] = 'Note';
$string['cannoteditkeys'] = 'You cannot edit these keys.';
