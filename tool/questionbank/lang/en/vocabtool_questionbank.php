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
 * tool/import/lang/en/vocabtool_import.php
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'Generate questions for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabtool_questionbank plugin does not store any personal data.';
$string['questionbank'] = 'Question bank';

$string['textassistant'] = 'AI text assistant';
$string['textassistant_help'] = 'Select an AI text assistant to generate the questions.';

$string['imageassistant'] = 'AI image assistant';
$string['imageassistant_help'] = 'Select an AI image assistant to generate images embedded in questions.';

$string['audioassistant'] = 'AI audio assistant';
$string['audioassistant_help'] = 'Select an AI audio assistant to generate audio embedded in questions.';

$string['videoassistant'] = 'AI video assistant';
$string['videoassistant_help'] = 'Select an AI video assistant to generate video embedded in questions.';

$string['questioncategory_help'] = 'The category to which the new questions should be added.';
$string['questioncategory'] = 'Question category';
$string['questioncount_help'] = 'The number of new questions to generate at each level.';
$string['questioncount'] = 'Question count';
$string['questionlevels_help'] = 'The CEFR levels of the questions to generate.';
$string['questionlevels'] = 'Question levels';
$string['questiontypes_help'] = 'The types of questions to generate.';
$string['questiontypes'] = 'Question types';

$string['parentcategory_help'] = 'Select the question category in which you wish to add the new questions.';
$string['parentcategory'] = 'Parent category';
$string['subcategories_help'] = 'If you wish to put the questions into a subcategories, choose either "single" or "automatic".

**None:** no subcategory will be created. All new questions will be put into the "Parent category".

**Single subcategory:** enter the name of the subcategory in the box provided. All new questions will be put into this category.

**Automatic subcategories:** the questions will be put automatically into a hierarchy of subcategories: Course -> "Vocabulary" -> Activity -> Word -> Question type.

The subcategories will be created within the "Parent category" selected above.';
$string['subcategories'] = 'Subcategories';

$string['generatequestions'] = 'Generate questions';
$string['managequestioncategories'] = 'Click here to manage question categories';
$string['aisettings'] = 'AI settings';
$string['questionsettings'] = 'Question settings';
$string['categorysettings'] = 'Question category settings';

$string['singlesubcategory'] = 'Single subcategory';
$string['automaticsubcategories'] = 'Automatic subcategories';

$string['selectedwords_help'] = 'Select the words for which you wish to generate questions.';
$string['selectedwords'] = 'Selected words';

$string['cefr_a1_description'] = 'A1: Basic';
$string['cefr_a2_description'] = 'A2: Elementary';
$string['cefr_b1_description'] = 'B1: Intermediate';
$string['cefr_b2_description'] = 'B2: Upper-intermediate';
$string['cefr_c1_description'] = 'C1: Advanced';
$string['cefr_c2_description'] = 'C2: Proficient';

$string['emptyselectedwords'] = 'Select at least one word.';
$string['emptyquestiontypes'] = 'Select at least one question type.';
$string['emptyquestionlevels'] = 'Select at least one level.';
$string['emptyquestioncount'] = 'Number of questions should be greater than zero.';
$string['emptyparentcategoryelements'] = 'Select a question category.';
$string['emptysubcategorieselements'] = 'Select at least one type question subcategory.';

$string['nowordsfound'] = 'In order to generate questions for the question bank, you must first define a word list for this vocabulary activity. You can do that using the "Edit word list" tool.';

$string['scheduletaskssuccess'] = 'The following tasks were successfully scheduled and will be run later by the Moodle cron. {$a}';
$string['scheduletasksfailure'] = 'The following tasks could NOT be scheduled. Please try again later. {$a}';
$string['taskgeneratequestions'] = 'Task to generate {$a->count} {$a->type} question(s) at level "{$a->level}" for the word "{$a->word}".';

$string['error_emptyresults'] = 'The results from the AI assistant are empty/missing. No questions could be generated/imported.';
$string['error_failedtoconnect'] = 'Failed to connect to {$a->ai} {$a->configid}';
$string['error_generatequestions'] = 'Unable to generate questions; {$a}.';
$string['error_invalidlogid'] = 'Invliad log ({$a}) received from adhoc task';
$string['error_invalidquestioncategoryid'] = 'Invalid question category ID ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidtaskparameters'] = 'Invalid parameter(s) in adhoc task log: {$a}';
$string['error_invalidteacherid'] = 'User (id={$a->userid}) is not allowed to create questions in course (id={$a->courseid}).';
$string['error_invaliduserid'] = 'Invalid userid ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidvocabid'] = 'Invalid vocabid ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidwordid'] = 'Invalid wordid ({$a}) sent to adhoc task to generate questions.';
$string['error_missingcoursecategory'] = 'Could not locate or create a question category for the word "{$a}"';
$string['error_missingwordinstance'] = 'Word "{$a->word}" is not in the word list for this Vocabulary activity.';
$string['error_recordnotadded'] = 'Could not add record to table {$a->table}: {$a->record}';

$string['error_invalidprompt'] = 'Invalid AI prompt (id={$a->id}): {$a->name}';
$string['error_invalidformat'] = 'Invalid AI format (id={$a->id}): {$a->name}';
$string['error_invalidfile'] = 'Invalid AI file (id={$a->id}): {$a->name}';

$string['catname_course'] = 'Vocabulary: {$a->coursename}';
$string['catname_section'] = '{$a->sectiontype}: {$a->sectionname}';
$string['catname_vocab'] = '{$a->vocabname}';
$string['catname_word'] = 'Word: {$a->word}';
$string['catname_wordtype'] = 'Word: {$a->word} ({$a->qtype})';
$string['catname_wordtypelevel'] = 'Word: {$a->word} ({$a->qtype}) {$a->level}';
$string['catname_singlecategory'] = 'Vocabulary questions for "{$a->word}"';

$string['catinfo_course'] = 'Vocabulary questions in course "{$a->coursename}"';
$string['catinfo_section'] = 'Vocabulary questions in section "{$a->sectionname}" of course "{$a->coursename}"';
$string['catinfo_vocab'] = 'Vocabulary questions in activity "{$a->vocabname}"';
$string['catinfo_word'] = 'Vocabulary questions for the word "{$a->word}"';
$string['catinfo_wordtype'] = '{$a->qtype} questions for the word "{$a->word}"';
$string['catinfo_wordtypelevel'] = '{$a->qtype} questions ({$a->level}) for the word "{$a->word}"';
$string['catinfo_singlecategory'] = 'Vocabulary questions for "{$a->word}"';

$string['selectprompt'] = 'Select prompt ...';
$string['selectformat'] = 'Select format ...';

$string['missingaidetails'] = 'Questions cannot be generated yet because the following settings have not been defined: {$a}';
$string['noassistantsfound'] = '{$a} Access details to an AI assistant';
$string['nopromptsfound'] = '{$a} Prompts for the AI assistants';
$string['noformatsfound'] = '{$a} Output formats for the prompts';
$string['addaidetails'] = 'Please use the links on the "AI assistants" menu to add the missing details.';

$string['nofilesfound'] = 'No tuning files were found. They are not essential to create questions, but they can improve the quality of questions produced by the AI assistant. {$a}';
$string['clicktoaddfiles'] = 'Click here to add tuning files.';

$string['noimagefound'] = '{$a} Prompts for the AI image assistants';
$string['clicktoaddimage'] = 'Click here to add an AI image assistant.';

$string['noaudiofound'] = '{$a} Prompts for the AI audio assistants';
$string['clicktoaddaudio'] = 'Click here to add an AI audio assistant.';

$string['novideofound'] = '{$a} Prompts for the AI video assistants';
$string['clicktoaddvideo'] = 'Click here to add an AI video assistant.';

$string['qformat'] = 'AI output format';
$string['qformat_help'] = 'Choose a format for the AI output that will it to be imported into the question bank.';

$string['prompt'] = 'AI prompt';
$string['prompt_help'] = 'Select the AI prompt that will be sent to the selected AI assistant.';

$string['file'] = 'AI tuning file';
$string['file_help'] = 'Select the AI tuning file that contains training data that will be sent to the selected AI assistant.';

$string['questionreview_help'] = 'If this setting is enabled, the AI results will not be imported into the question bank until they have been reviewed by the teacher.';
$string['questionreview'] = 'Question review';

$string['taskowner'] = 'Task owner';
$string['word'] = 'Word';
$string['questiontype_help'] = 'questiontype_help';
$string['questiontype'] = 'Question type';
$string['questionlevel_help'] = 'questionlevel_help';
$string['questionlevel'] = 'Question level';
$string['promptname'] = 'AI prompt name';
$string['formatname'] = 'AI format name';
$string['filedescription'] = 'AI tuning file';
$string['subcattype_help'] = 'subcattype_help';
$string['subcattype'] = 'Subcategory type';
$string['subcatname_help'] = 'subcatname_help';
$string['subcatname'] = 'Subcategory name';
$string['maxtries_help'] = 'maxtries_help';
$string['maxtries'] = 'Maximum tries';
$string['tries_help'] = 'tries_help';
$string['tries'] = 'Number of tries';
$string['prompttext_help'] = 'prompttext_help';
$string['prompttext'] = 'Prompt text';
$string['resultstext_help'] = 'resultstext_help';
$string['resultstext'] = 'Results text';
$string['timecreated'] = 'Time created';
$string['timemodified'] = 'Time modified';

$string['missingconfigname'] = 'Config missing (id={$a->configid}, type={$a->type})';
$string['invalidquestioncategory'] = 'Invalid question category (id={$a})';
$string['resultsnotparsed'] = 'Results for {$a} could not be parsed.';
$string['emptyresults'] = 'Results from AI assistant were empty.';

$string['logrecords'] = 'Log records {$a}';
$string['selectedlogrecord'] = 'Selected log record';

$string['taskstatus_help'] = 'taskstatus_help';
$string['taskstatus'] = 'Task status';
$string['taskstatus_notset'] = 'Not set';
$string['taskstatus_queued'] = 'Task queued';
$string['taskstatus_checkingparams'] = 'Checking parameters';
$string['taskstatus_fetchingresults'] = 'Fetching results';
$string['taskstatus_awaitingreview'] = 'Awaiting review of results';
$string['taskstatus_awaitingimport'] = 'Waiting to import results';
$string['taskstatus_importingresults'] = 'Importing results';
$string['taskstatus_completed'] = 'Task completed successfully';
$string['taskstatus_cancelled'] = 'Task cancelled by user';
$string['taskstatus_failed'] = 'Task failed with error';

$string['editlog'] = 'Edit log';
$string['redotask'] = 'Redo task';
$string['resumetask'] = 'Resume task';
$string['deletelog'] = 'Delete log';

$string['editlogresult'] = '{$a->count} log was updated {$a->ids}';
$string['editlogsresult'] = '{$a->count} logs were updated {$a->ids}';

$string['redotaskresult'] = '{$a->count} task will be redone {$a->ids}';
$string['resumetaskresult'] = '{$a->count} task will be resumed {$a->ids}';
$string['deletelogresult'] = '{$a->count} log record was deleted {$a->ids}';

$string['redotaskresults'] = '{$a->count} tasks will be redone {$a->ids}';
$string['resumetaskresults'] = '{$a->count} tasks will be resumed {$a->ids}';
$string['deletelogresults'] = '{$a->count} log records were deleted {$a->ids}';

$string['withselected'] = 'With selected';
$string['backgroundtask'] = 'Background task';

$string['taskerror_help'] = 'taskerror_help';
$string['taskerror'] = 'Task error';

$string['ai_generated'] = 'AI';
$string['descriptionshort'] = 'Desc';
$string['ddimageortextshort'] = 'DD(image/text)';
$string['essayautogradeshort'] = 'Essay(auto)';
$string['gapselectshort'] = 'Gap';
$string['matchshort'] = 'Match';
$string['multianswershort'] = 'Cloze';
$string['multichoiceshort'] = 'MC';
$string['orderingshort'] = 'Ordering';
$string['shortanswershort'] = 'SA';
$string['speakautogradeshort'] = 'Speak(auto)';
$string['truefalseshort'] = 'TF';
$string['sassessmentshort'] = 'Speak(assessment)';

$string['moodlequestions'] = 'Moodle questions';
$string['checkingparams'] = '... checking parameters';
$string['generatingquestions'] = '... generating {$a->count} questions for "{$a->word}"';
$string['importingquestions'] = '... importing {$a->count} questions for "{$a->word}"';

