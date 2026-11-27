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
 * tool/dictionary/lang/en/vocabtool_dictionary.php
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['added'] = 'Added';
$string['addedcounts'] = '{$a->senses} senses, {$a->definitions} definitions, {$a->glosses} glosses, {$a->samples} samples';
$string['aibridgedisabled'] = 'The AI bridge (tool_aibridge) is turned off, so the dictionary cannot be filled in with AI.';
$string['aipolicylink'] = 'the AI policy';
$string['aipolicynotice'] = 'Before you can fill in the dictionary with AI, please read and accept {$a}.';
$string['aisettings'] = 'AI settings';
$string['dictionary'] = 'Edit dictionary';
$string['entrysettings'] = 'Dictionary entries';
$string['error_activitynotfound'] = 'The Vocabulary activity (id={$a}) was not found.';
$string['error_promptnotfound'] = 'The dictionary prompt (id={$a}) was not found, or cannot be used.';
$string['error_tryagain'] = 'The AI could not reply now, so the task will try again later: {$a}';
$string['error_wordnotfound'] = 'The word (id={$a}) was not found.';
$string['fillin'] = 'Fill in the dictionary';
$string['fillingin'] = 'Filling in the dictionary for "{$a}"';
$string['filltask'] = 'Fill in the dictionary with AI';
$string['level'] = 'Level';
$string['level_help'] = 'The level of the definitions and sample sentences. The AI uses only words and grammar of this level, and estimates the level of each sense.';
$string['messages'] = 'Messages';
$string['nopromptsfound'] = 'No dictionary prompts were found. Please check the <a href="{$a}">AI bridge\'s library</a>.';
$string['nowordsfound'] = 'There are no words in this activity\'s word list yet.';
$string['nowordsselected'] = 'Please choose at least one word.';
$string['pluginname'] = 'Edit dictionary data used by Vocabulary activities';
$string['privacy:metadata:log'] = 'The tasks that teachers set up to fill in the dictionary with AI.';
$string['privacy:metadata:log:error'] = 'Any error in the task.';
$string['privacy:metadata:log:prompt'] = 'The prompt that was sent to the AI.';
$string['privacy:metadata:log:results'] = 'The reply from the AI.';
$string['privacy:metadata:log:timecreated'] = 'When the task was set up.';
$string['privacy:metadata:log:userid'] = 'The teacher who set up the task, as whom the AI is used.';
$string['privacy:metadata:log:wordid'] = 'The word whose dictionary entry is filled in.';
$string['prompt'] = 'Prompt';
$string['samplecount'] = 'Sample sentences for each sense';
$string['selectedwords'] = 'Words';
$string['selectedwords_help'] = 'The words to fill in. Words that have no senses in the dictionary yet are ticked. The dictionary is shared by the whole site, so a word may already have senses from another activity.';
$string['sensecount'] = 'Most senses';
$string['sensecount_help'] = 'The most senses (meanings) to ask for. The AI gives only the common senses, so many words get fewer.';
$string['skipexisting'] = 'Skip words that already have senses';
$string['skipexisting_help'] = 'If this is ticked, a word that already has senses in the dictionary is not sent to the AI again. Untick it to add more senses, definitions and samples, e.g. at another level.';
$string['status'] = 'Status';
$string['statuscompleted'] = 'Completed';
$string['statusdelayed'] = 'Will try again';
$string['statusfailed'] = 'Failed';
$string['statusfetching'] = 'Waiting for the AI';
$string['statusnotset'] = 'Not set';
$string['statusqueued'] = 'Queued';
$string['statussaving'] = 'Saving';
$string['statusskipped'] = 'Skipped (already has senses)';
$string['tasks'] = 'Tasks';
$string['tasksqueued'] = '{$a} task(s) were queued. They run in the background, and their progress is shown below.';
$string['textassistant'] = 'AI provider';
$string['word'] = 'Word';
$string['wordlist'] = 'Word list';
$string['wordpending'] = '{$a} (in progress)';
$string['wordsenses'] = '{$a->word} ({$a->count} senses)';
