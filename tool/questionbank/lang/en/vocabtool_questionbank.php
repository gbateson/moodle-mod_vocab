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

$string['activityname'] = 'Activity name';
$string['addaidetails'] = 'Please use the links on the "AI assistants" menu to add the missing details.';
$string['addname'] = 'Add name(s)';
$string['addtags'] = 'Add tag(s)';
$string['adhoctaskid'] = 'Adhoc task';
$string['adhoctaskid_help'] = 'The id of the adhoc Moodle task used to generate the questions.';
$string['aisettings'] = 'AI settings';
$string['applydefaults'] = 'Apply defaults';
$string['applydefaultsresult'] = 'Default values were applied to {$a->count} log {$a->ids}';
$string['applydefaultsresults'] = 'Default values were applied to {$a->count} logs {$a->ids}';
$string['categorysettings'] = 'Question category settings';
$string['catinfo_activityname'] = 'Vocabulary questions in activity "{$a->activityname}"';
$string['catinfo_coursename'] = 'Vocabulary questions in course "{$a->coursename}"';
$string['catinfo_customname'] = 'Vocabulary questions';
$string['catinfo_prompthead'] = '{$a->prompthead} ({$a->qtype}) questions ({$a->qlevel}) for the word "{$a->word}"';
$string['catinfo_prompttail'] = '{$a->prompttail} ({$a->qtype}) questions ({$a->qlevel}) for the word "{$a->word}"';
$string['catinfo_questiontype'] = '{$a->qtype} questions for the word "{$a->word}"';
$string['catinfo_sectionname'] = 'Vocabulary questions in {$a->sectiontype} "{$a->sectionname}" of course "{$a->coursename}"';
$string['catinfo_vocablevel'] = '{$a->qtype} questions ({$a->qlevel}) for the word "{$a->word}"';
$string['catinfo_word'] = 'Vocabulary questions for the word "{$a->word}"';
$string['catname_activityname'] = '{$a->activitytype}: {$a->activityname}';
$string['catname_coursename'] = 'Course: {$a->coursename}';
$string['catname_customname'] = '{$a->customname}';
$string['catname_prompthead'] = 'Word: {$a->word} ({$a->qtype}) {$a->qlevel} ({$a->prompthead})';
$string['catname_prompttail'] = 'Word: {$a->word} ({$a->qtype}) {$a->qlevel} ({$a->prompttail})';
$string['catname_questiontype'] = 'Word: {$a->word} ({$a->qtype})';
$string['catname_sectionname'] = '{$a->sectiontype}: {$a->sectionname}';
$string['catname_vocablevel'] = 'Word: {$a->word} ({$a->qtype}) {$a->qlevel}';
$string['catname_word'] = 'Word: {$a->word}';
$string['cefr_a1_description'] = 'A1: Basic';
$string['cefr_a2_description'] = 'A2: Elementary';
$string['cefr_b1_description'] = 'B1: Intermediate';
$string['cefr_b2_description'] = 'B2: Upper-intermediate';
$string['cefr_c1_description'] = 'C1: Advanced';
$string['cefr_c2_description'] = 'C2: Proficient';
$string['checkingparams'] = '... checking parameters ...';
$string['creatingmedia'] = '... creating multimedia ({$a->media}) for "{$a->word} ..."';
$string['ddimageortextshort'] = 'DD (image/text)';
$string['defaultcustomname'] = 'Questions for {$a}';
$string['deletelog'] = 'Delete log';
$string['deletelogresult'] = '{$a->count} log record was deleted {$a->ids}';
$string['deletelogresults'] = '{$a->count} log records were deleted {$a->ids}';
$string['descriptionshort'] = 'Desc';
$string['editlog'] = 'Edit log';
$string['editlogresult'] = '{$a->count} log was updated {$a->ids}';
$string['editlogsresult'] = '{$a->count} logs were updated {$a->ids}';
$string['emptyparentcategoryelements'] = 'Select a question category.';
$string['emptyquestioncount'] = 'Number of questions should be greater than zero.';
$string['emptyquestionlevels'] = 'Select at least one level.';
$string['emptyquestiontypes'] = 'Select at least one question type.';
$string['emptyresults'] = 'Results from AI assistant were empty.';
$string['emptyselectedwords'] = 'Select at least one word.';
$string['emptysubcategorieselements'] = 'Select at least one type question subcategory.';
$string['error_emptyresults'] = 'The results from the AI assistant are empty/missing. No questions could be generated/imported.';
$string['error_failedtoconnect'] = 'Failed to connect to {$a->ai} {$a->configid}';
$string['error_generatequestions'] = 'Unable to generate questions; {$a}.';
$string['error_invalidfile'] = 'Invalid AI file (id={$a->id}): {$a->name}';
$string['error_invalidformat'] = 'Invalid AI format (id={$a->id}): {$a->name}';
$string['error_invalidlogid'] = 'Invalid log ({$a}) received from adhoc task';
$string['error_invalidprompt'] = 'Invalid AI prompt (id={$a->id}): {$a->name}';
$string['error_invalidquestioncategoryid'] = 'Invalid question category ID ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidtaskparameters'] = 'Invalid parameter(s) in adhoc task log: {$a}';
$string['error_invalidteacherid'] = 'User (id={$a->userid}) is not allowed to create questions in course (id={$a->courseid}).';
$string['error_invaliduserid'] = 'Invalid userid ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidvocabid'] = 'Invalid vocabid ({$a}) sent to adhoc task to generate questions.';
$string['error_invalidwordid'] = 'Invalid wordid ({$a}) sent to adhoc task to generate questions.';
$string['error_missingcoursecategory'] = 'Could not locate or create a question category for the word "{$a}"';
$string['error_missingwordinstance'] = 'Word "{$a->word}" is not in the word list for this Vocabulary activity.';
$string['error_recordnotadded'] = 'Could not add record to table {$a->table}: {$a->record}';
$string['essayautogradeshort'] = 'Essay (auto)';
$string['female'] = 'Female';
$string['filedescription'] = 'AI tuning file';
$string['fixquestion'] = 'Fix question';
$string['fixquestions'] = 'Fix questions';
$string['fixquestionsresult'] = 'Questions from {$a->count} task will be fixed {$a->ids}';
$string['fixquestionsresults'] = 'Questions from {$a->count} tasks will be fixed {$a->ids}';
$string['formatname'] = 'AI format name';
$string['gapselectshort'] = 'Gap';
$string['generatequestions'] = 'Generate questions';
$string['generatingquestions'] = '... generating {$a->count} questions for "{$a->word}"';
$string['importingquestions'] = '... importing {$a->count} questions for "{$a->word}" ...';
$string['invalidquestioncategory'] = 'Invalid question category (id={$a})';
$string['logrecords'] = 'Log records {$a}';
$string['male'] = 'Male';
$string['man'] = 'Male';
$string['matchshort'] = 'Match';
$string['maxtries'] = 'Maximum tries';
$string['maxtries_help'] = 'The maximum number of times to attempt to create the AI-generated questions. Usually one is sufficient.';
$string['missingaidetails'] = 'Questions cannot be generated yet because the following settings have not been defined: {$a}';
$string['missingconfigname'] = 'Config missing (id={$a->configid}, type={$a->type})';
$string['moodlequestions'] = 'Moodle questions';
$string['multianswershort'] = 'Cloze';
$string['multichoiceshort'] = 'MC';
$string['noassistantsfound'] = '{$a} Access details to an AI assistant';
$string['noaudiosfound'] = 'No audio assistants were found. They are not essential, but they are necessary if you wish to add audio to your questions.<br>{$a}';
$string['nofilesfound'] = 'No tuning files were found. They are not essential to create questions, but they can improve the quality of questions produced by the AI text assistant.<br>{$a}';
$string['noformatsfound'] = '{$a} Output formats for the prompts';
$string['noimagesfound'] = 'No image assistants were found. They are not essential, but they are necessary if you wish to add images to your questions.<br>{$a}';
$string['nopromptsfound'] = '{$a} Prompts for the AI assistants';
$string['novideosfound'] = 'No video assistants were found. They are not essential, but they are necessary if you wish to add video to your questions.<br>{$a}';
$string['nowordsfound'] = 'In order to generate questions for the question bank, you must first define a word list for this vocabulary activity. You can do that using the "Edit word list" tool.';
$string['orderingshort'] = 'Ordering';
$string['pluginname'] = 'Generate questions for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabtool_questionbank plugin does not store any personal data.';
$string['prompt'] = 'AI prompt';
$string['prompt_help'] = 'Select the AI prompt that will be sent to the selected AI assistant.';
$string['promptname'] = 'AI prompt name';
$string['prompttext'] = 'Prompt text';
$string['prompttext_help'] = 'The AI prompt used to generate the questions.';
$string['questionbank'] = 'Question bank';
$string['questioncategory'] = 'Question category';
$string['questioncategory_help'] = 'The category to which the new questions should be added.';
$string['questionlevel'] = 'Question level';
$string['questionlevel_help'] = 'The vocabulary level used in the AI-generated questions.';
$string['questionlevels'] = 'Language levels';
$string['questionlevels_help'] = 'The level of vocabulary and grammar to use in the questions.';
$string['questionsettings'] = 'Question settings';
$string['questiontypes'] = 'Question types';
$string['questiontypes_help'] = 'The types of questions to generate.';
$string['redotask'] = 'Redo task';
$string['redotaskresult'] = '{$a->count} task will be redone {$a->ids}';
$string['redotaskresults'] = '{$a->count} tasks will be redone {$a->ids}';
$string['resultsnotparsed'] = 'Results for {$a} could not be parsed.';
$string['resultstext'] = 'Results text';
$string['resultstext_help'] = 'The raw results receieved from the AI assistant.';
$string['resumetask'] = 'Resume task';
$string['resumetaskresult'] = '{$a->count} task will be resumed {$a->ids}';
$string['resumetaskresults'] = '{$a->count} tasks will be resumed {$a->ids}';
$string['sassessmentshort'] = 'Speak(assessment)';
$string['scheduletasksfailure'] = 'The following tasks could NOT be scheduled. Please try again later. {$a}';
$string['scheduletaskssuccess'] = 'The following tasks were successfully scheduled and will be run later by the Moodle cron. {$a}';
$string['sectiontype'] = 'Section type';
$string['selectedlogrecord'] = 'Selected log record';
$string['selectedwords'] = 'Selected words';
$string['selectedwords_help'] = 'Select the words for which you wish to generate questions.';
$string['shortanswershort'] = 'SA';
$string['speakautogradeshort'] = 'Speak(auto)';
$string['subcatname'] = 'Subcategory name';
$string['subcatname_help'] = 'Specify the name of the custom-named category.';
$string['subcattype'] = 'Subcategory type';
$string['subcattype_help'] = 'Define the hierarchy of subcategories within the parent question category.';
$string['taskerror'] = 'Task error';
$string['taskerror_help'] = 'The error message, if any, reported by the Moodle adhoc task.';
$string['taskexecutor'] = 'Task executor';
$string['taskexecutor_help'] = 'Select the mechanism by which you wish to run the task.';
$string['taskgeneratequestions'] = 'Task (id={$a->taskid}) to generate {$a->qcount} {$a->qtype} question(s) at level "{$a->qlevel}" for the word "{$a->word}".';
$string['taskowner'] = 'Task owner';
$string['taskowner_help'] = 'The owner of the adhoc Moodle task used to generate the questions.';
$string['taskstatus'] = 'Task status';
$string['taskstatus_addingmultimedia'] = '{$a}: Adding images, audio and video';
$string['taskstatus_awaitingimport'] = '{$a}: Waiting to import results';
$string['taskstatus_awaitingreview'] = '{$a}: Awaiting review of results';
$string['taskstatus_cancelled'] = '{$a}: Task cancelled by user';
$string['taskstatus_checkingparams'] = '{$a}: Checking parameters';
$string['taskstatus_completed'] = '{$a}: Task completed successfully';
$string['taskstatus_delayed'] = '{$a}: Task delayed';
$string['taskstatus_failed'] = '{$a}: Task failed with error';
$string['taskstatus_fetchingresults'] = '{$a}: Fetching results';
$string['taskstatus_help'] = 'The status of the Moodle adhoc task that generated the questions.';
$string['taskstatus_importingresults'] = '{$a}: Importing results';
$string['taskstatus_notset'] = '{$a}: Not set';
$string['taskstatus_queued'] = '{$a}: Task queued';
$string['timecreated'] = 'Time created';
$string['timemodified'] = 'Time modified';
$string['tries'] = 'Number of tries';
$string['tries_help'] = 'The number of attempts that were made connect to connect to the AI assistant in order to generate the questions.';
$string['truefalseshort'] = 'TF';
$string['withselected'] = 'With selected';
$string['woman'] = 'Woman';
