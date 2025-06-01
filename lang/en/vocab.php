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
 * lang/en/vocab.php: English strings for mod_vocab.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['activityclose'] = 'Activity closes';
$string['activityclose_help'] = 'Students can to access this activity up until the date and time specified here. After this date and time, the activity will be closed.';
$string['activityname'] = 'Activity Name';
$string['activityname_help'] = 'This is the name of the Vocabulary activity. The name is displayed on the course page, and within the activity itself.';
$string['activityopen'] = 'Activity opens';
$string['activityopen_help'] = 'Students can access this activity starting from this date and time. Before this date and time, the activity will be closed.';
$string['addmissingvalue'] = 'Please add a value here.';
$string['addnewkey'] = 'Add a new key';
$string['ai_generated'] = 'AI';
$string['ais'] = 'AI assistants';
$string['anyattempts'] = 'Any attempts';
$string['anywordscores'] = 'Any word scores';
$string['assistantplugins'] = 'AI assistants';
$string['assistantplugins_help'] = 'Select the AI assistants (e.g. ChatGPT, DALL-E, TTS) whose settings you wish to export.';
$string['attemptcount'] = 'Minimum attempt count';
$string['attemptcount_help'] = 'The number of successful attempts required to demonstrate mastery of a word.';
$string['attemptdelay'] = 'Minimum delay between attempts';
$string['attemptdelay_help'] = 'The minimum delay between attempts. This setting can be used to prevent students from cramming their vocabulary study into a short period of time.';
$string['attemptduration'] = 'Maximum total duration of attempts';
$string['attemptduration_help'] = 'The maximum total duration for attempts which satisfy the score and count conditions.';
$string['attemptscore'] = 'Minimum attempt score';
$string['attemptscore_help'] = 'The minimum score required for an attempt to qualify as a successful attempt at a word.';
$string['attempttype'] = 'Attempt type';
$string['attempttype_help'] = 'The type of attempts to be considered by the attempt count condition.

**Any attempts**
Any attempts will be considered.

**Most recent attempts**
Only the most recent attempts will be considered.

**Consecutive attempts**
Any block of consecutive attempts will be considered.';
$string['audioassistant'] = 'AI audio assistant';
$string['audioassistant_help'] = 'Select an AI audio assistant to generate audio embedded in questions.';
$string['backuplangfiles'] = 'Backup lang files';
$string['backuplangfiles_help'] = 'Set to YES to create a backup of each language file before it is sorted.';
$string['clicktoaddaudio'] = 'Click here to add an AI audio assistant.';
$string['clicktoaddfiles'] = 'Click here to add tuning files.';
$string['clicktoaddimage'] = 'Click here to add an AI image assistant.';
$string['clicktoaddvideo'] = 'Click here to add an AI video assistant.';
$string['clicktocontinue'] = 'Click here to continue';
$string['completed'] = 'Completed';
$string['consecutiveattempts'] = 'Consecutive attempts';
$string['contentplugins'] = 'AI content';
$string['contentplugins_help'] = 'Select the AI content (e.g. prompts, formats, tuning files) whose settings you wish to export.';
$string['convertto'] = 'Convert to';
$string['coursename'] = 'Course name';
$string['customname'] = 'Custom name';
$string['customtags'] = 'Custom tags';
$string['defaultregion'] = 'Default page region';
$string['demonstrationmode'] = 'Demonstration mode';
$string['editkey'] = 'Edit existing key';
$string['expandforeveryone'] = 'Always expand (default)';
$string['expandfornoone'] = 'Always collapse';
$string['expandforstudents'] = 'Expand for students';
$string['expandforteachers'] = 'Expand for teachers';
$string['expandingdelay'] = 'Expanding delay (i.e. spaced repetition)';
$string['expandnavigation'] = 'Expand navigation';
$string['expandnavigation_help'] = 'You can use this setting to specify for whom the global navigation menu should be expanded.';
$string['export'] = 'Export';
$string['exportcompleted'] = 'Export is complete';
$string['exportcontext'] = 'Export context';
$string['exportcontext_help'] = 'Specify the context from which you wish to export.';
$string['exportfile'] = 'Export file name';
$string['exportfile_help'] = 'Here you can export this word list to a file. You can define a file name yourself, or use the default name. The exported file can be kept as a backup, or imported into another Vocabulary activity.';
$string['file'] = 'AI tuning file';
$string['file_help'] = 'Select the AI tuning file that contains training data that will be sent to the selected AI assistant.';
$string['filename'] = 'File name';
$string['fixeddelay'] = 'Fixed delay';
$string['games'] = 'Games';
$string['gamesclose'] = 'Games playable until';
$string['gamesclose_help'] = 'Students can view and interact with games until this date and time. After this date, students cannot access the games, but they can still view their results.';
$string['gamesopen'] = 'Games playable from';
$string['gamesopen_help'] = 'Students can view and interact with games starting from this date and time. Before this date, the games will not be accessible.';
$string['generateas'] = 'Generate as';
$string['gradecount'] = 'Minimum word count';
$string['gradecount_help'] = 'The number of words that students are required to master in order to complete this activity. If empty, or "0", or greater than the number of items in the word list, then *all* words must be mastered.';
$string['gradedesc'] = 'The grade is set as the percentage of words that have been mastered, according to the "Mastery conditions" defined below.';
$string['grademax'] = 'Maximum grade';
$string['grademax_help'] = 'This setting specifies the maximum grade for this activity. If set to 0, this activity does not appear in the grades pages.';
$string['gradepartial'] = 'Include partially completed words';
$string['gradepartial_help'] = 'Choose whether or not the activity grade should include scores for partially completed words. A partially completed word is one that has been studied but not yet fully mastered according to the "Mastery conditions" defined below.';
$string['gradetype'] = 'Word score type';
$string['gradetype_help'] = 'The type of word scores to be considered when calculating the activity grade.

**Highest word scores**
The scores of all attempted words will be examined and the highest will be used to calculate the activity grade.

**Lowest word scores**
The scores of all attempted words will be examined and the lowest will be used to calculate the activity grade.

**Latest word scores**
Only scores for the most recently completed words will be considered.

**Earliest word scores**
Only scores for the earliest completed words will be considered.';
$string['guestsnotallowed'] = 'Guests are not allowed to access this Vocabulary activity. Please login and try again.';
$string['highestwordscores'] = 'Highest word scores';
$string['imageassistant'] = 'AI image assistant';
$string['imageassistant_help'] = 'Select an AI image assistant to generate images embedded in questions.';
$string['import'] = 'Import';
$string['importcompleted'] = 'Import is complete';
$string['importfile'] = 'Import file';
$string['importfile_help'] = 'Here you can import a word list from a file. You can create your own text file with one word or phrase per line, or you can use a file that has been exported from another Vocabulary activity.';
$string['inprogress'] = 'In progress';
$string['itemtypeaudios'] = 'audios';
$string['itemtypeimages'] = 'images';
$string['itemtyperequests'] = 'requests';
$string['itemtypetokens'] = 'tokens';
$string['itemtypevideos'] = 'videos';
$string['key'] = 'Key';
$string['keysownedbyme'] = 'Keys owned by me';
$string['keysownedbyothers'] = 'Keys owned by other users';
$string['keysownedbyotherusers'] = 'Keys owned by other users';
$string['langmenu'] = 'Language menu';
$string['layoutbase'] = 'Base layout';
$string['layoutembedded'] = 'Embedded layout';
$string['layoutlogin'] = 'Login layout';
$string['layoutmaintenance'] = 'Maintenance layout';
$string['layoutpopup'] = 'Popup layout';
$string['layoutsecure'] = 'Secure layout';
$string['layoutstandard'] = 'Standard layout';
$string['livemode'] = 'Live mode';
$string['lowestwordscores'] = 'Lowest word scores';
$string['managequestioncategories'] = 'Click here to manage question categories';
$string['masteryconditions'] = 'Mastery conditions';
$string['medianotcreated'] = 'Oops, media could not be created by {$a->subplugin}. [filearea={$a->filearea}, itemid={$a->itemid}]';
$string['mediatype'] = 'Media type';
$string['modeltunedbyfile'] = '{$a->model} (tuned by file "{$a->file}")';
$string['modulename'] = 'Vocabulary activity';
$string['modulename_help'] = 'The Vocab module assists students in learning vocabulary through spaced repetition.

A single Vocabulary activity contains a list of vocabulary for student to focus on. The teacher can supply a list of target words, or can allow the software choose words for each student depending on their level.

Students familiarize themselves with the target vocabulary through a variety of game-like activities. Unknown vocabulary is recycled frequently, while known vocabulary is recycled at greater and greater intervals.';
$string['modulename_link'] = 'mod/vocab/view';
$string['modulenameplural'] = 'Vocabulary activities';
$string['newestwordscores'] = 'Latest word scores';
$string['noactivityheader'] = 'No activity header ';
$string['nocompletion'] = 'No completion information';
$string['nocoursefooter'] = 'No course footer';
$string['nodescription'] = 'No description';
$string['nofooter'] = 'No footer';
$string['nonavbar'] = 'No navigation bar';
$string['notasks'] = 'There are currently no adhoc tasks awaiting execution.';
$string['notitle'] = 'No title';
$string['notstarted'] = 'Not started';
$string['nowordsforyou'] = 'Sorry, this vocabulary activity does not yet contain any words for you to study. Please try again later.';
$string['nowordsfound'] = 'This vocabulary activity does not yet contain any words. Please use the tools menu to import or define a word list.';
$string['oldestwordscores'] = 'Earliest word scores';
$string['operationmode'] = 'Operation mode';
$string['operationmode_help'] = 'In "Live mode", this activity displays real word lists and student data. In "demonstration mode", it displays sample wordlists and student results, in order to give an impression of how real data would be represented.';
$string['otherkeysownedbyme'] = 'Other keys owned by me';
$string['owner'] = 'Owner';
$string['pagelayout'] = 'Page layout';
$string['pagelayout_help'] = 'You can use this setting to specify the layout used in the main view page for this Vocabulary activity.';
$string['pagelayouts'] = 'Page layouts';
$string['parentcategory'] = 'Parent category';
$string['parentcategory_help'] = 'Select the question category in which you wish to add the new questions.';
$string['pluginadministration'] = 'Vocab administration';
$string['pluginname'] = 'Vocab';
$string['prompthead'] = 'Prompt name (head)';
$string['prompttail'] = 'Prompt name (tail)';
$string['qformat'] = 'AI output format';
$string['qformat_help'] = 'Choose a format for the AI output that will it to be imported into the question bank.';
$string['questioncount'] = 'Question count';
$string['questioncount_help'] = 'The number of new questions to generate at each level.';
$string['questionreview'] = 'Question review';
$string['questionreview_help'] = 'If this setting is enabled, the AI results will not be imported into the question bank until they have been reviewed by the teacher.';
$string['questiontags'] = 'Question tags';
$string['questiontags_help'] = 'If required, you can specify one or more custom tags. When adding more than one tag, use a comma to separate tags.';
$string['questiontype'] = 'Question type';
$string['questiontype_help'] = 'The type of questions that are to be generated by the AI.';
$string['recentattempts'] = 'Most recent attempts';
$string['redotask'] = 'Redo an adhoc task';
$string['redotaskincron'] = 'Run task in cron';
$string['redoupgrade'] = 'Redo upgrade: {$a}';
$string['redoversiondate'] = '"Vocab activity module version set to just before {$a->version} - {$a->datetext}"';
$string['regions'] = 'Page regions';
$string['reports'] = 'Reports';
$string['resultdesc'] = '{$a->label}{$a->delimiter} {$a->number}/{$a->total} ({$a->percent}%)';
$string['resultsdesc'] = '{$a->completed}, {$a->inprogress}, {$a->notstarted}';
$string['resultstitle'] = 'Vocabulary results for {$a}';
$string['sectionname'] = 'Section name';
$string['selectformat'] = 'Select format ...';
$string['selectprompt'] = 'Select prompt ...';
$string['sharedanydate'] = 'Shared forever';
$string['sharedfrom'] = 'Shared from';
$string['sharedfrom_help'] = 'This item is shared starting from, and including, this date and time.';
$string['sharedfromdate'] = 'Shared from {$a}';
$string['sharedfromuntildate'] = 'Shared from {$a->from} until {$a->until}';
$string['sharedincoursecatcontext'] = 'Shared in all courses in the current course category';
$string['sharedincoursecontext'] = 'Shared in all activities in the current course';
$string['sharedinsystemcontext'] = 'Shared throughout this entire Moodle site';
$string['sharedinunknowncontext'] = 'Shared in unknown context: {$a}';
$string['sharedinusercontext'] = 'Not shared. Only accessible to you, {$a}.';
$string['sharedinvocabcontext'] = 'Shared only in the current Vocabulary activity';
$string['shareduntil'] = 'Shared until';
$string['shareduntil_help'] = 'This item is shared up to, and including, this date and time.';
$string['shareduntildate'] = 'Shared until {$a}';
$string['sharing'] = 'Sharing';
$string['sharingcontext'] = 'Sharing context';
$string['sharingcontext_help'] = 'The Moodle context in which this item can be shared.';
$string['sharingperiod'] = 'Sharing period';
$string['sortstrings'] = 'Sort strings for selected plugins';
$string['speeditemcount'] = 'Item count';
$string['speeditemcount_help'] = 'The maximum number of items that can be generated within the specified time period.';
$string['speeditemtype'] = 'Item type';
$string['speeditemtype_help'] = 'The type of items which are counted within the specified time period.';
$string['speedlimit'] = 'Maximum rate';
$string['speedlimit_help'] = 'These settings define the maximum rate at which content such as text, images, audio and video may be generated by this AI assistant.';
$string['speedlimitafter'] = '';
$string['speedlimitbefore'] = 'Up to';
$string['speedlimitduring'] = 'in';
$string['speedtimecount'] = 'Time count';
$string['speedtimecount_help'] = 'The number of time units that make up a single time period.';
$string['speedtimeunit'] = 'Time unit';
$string['speedtimeunit_help'] = 'The type of time units used to measure a single time period.';
$string['stringcachesreset'] = 'String caches were reset.';
$string['subcategories'] = 'Subcategories';
$string['subcategories_help'] = 'Use these checkboxes to specify a hierarchy of subcategories within the parent category into which you wish to put the AI-generated questions.

**None:** No subcategories will be created. All new questions will be put directly into the "Parent category".

**Custom name:** If this checkbox is selected, enter the custom name of the question subcategory in the box provided.

**Section name:** A question category for the course section (e.g. "topic" or "week") in which the current Vocabulary activity appears.

**Activity name:** A question category for the current Vocabulary activity.

**Word name:** A question category for each vocabulary item, or "word".

**Question type:** A question category for each type of question (e.g. "MC", "SA" or "Match").

**Vocabulary level:** A question category for each vocabulary level (e.g. "A1", "TOEFL-30", "TOEIC-300" or "300L").

**Prompt name (head):** The "head" of the prompt name, i.e. the part before the first colon in a name such as "TOEIC R&L (Part 1): Describe an image".

**Prompt name (tail):** The "tail" of the prompt name, i.e. the part after the last colon in a name such as "TOEIC R&L (Part 1): Describe an image".

If any of the specified subcategories do not exist, they will be created automatically as the questions are imported into the question bank.';
$string['subplugintype_vocabai'] = 'AI assistant';
$string['subplugintype_vocabai_plural'] = 'AI assistants';
$string['subplugintype_vocabgame'] = 'Vocabulary game';
$string['subplugintype_vocabgame_plural'] = 'Vocabulary games';
$string['subplugintype_vocabreport'] = 'Vocabulary report';
$string['subplugintype_vocabreport_plural'] = 'Vocabulary reports';
$string['subplugintype_vocabtool'] = 'Vocabulary tool';
$string['subplugintype_vocabtool_plural'] = 'Vocabulary tools';
$string['textassistant'] = 'AI text assistant';
$string['textassistant_help'] = 'Select an AI text assistant to generate the questions.';
$string['throttling'] = 'Throttling';
$string['timeunitdays'] = 'day(s)';
$string['timeunithours'] = 'hour(s)';
$string['timeunitminutes'] = 'minute(s)';
$string['timeunitmonths'] = 'month(s)';
$string['timeunitseconds'] = 'second(s)';
$string['timeunitweeks'] = 'week(s)';
$string['tools'] = 'Tools';
$string['unchangedlangfiles'] = 'The language file(s) for the following plugins were NOT updated:';
$string['updatedlangfiles'] = 'The language file(s) for the following plugins were updated:';
$string['videoassistant'] = 'AI video assistant';
$string['videoassistant_help'] = 'Select an AI video assistant to generate video embedded in questions.';
$string['vocab:addinstance'] = 'Add a new Vocabulary activity';
$string['vocab:attempt'] = 'Attempt a Vocabulary activity';
$string['vocab:deleteattempts'] = 'Delete attempts at a Vocabulary activity';
$string['vocab:manage'] = 'Manage a Vocabulary activity';
$string['vocab:preview'] = 'Preview a Vocabulary activity';
$string['vocab:reviewmyattempts'] = 'Review your own attempts at a Vocabulary activity';
$string['vocab:view'] = 'View a Vocabulary activity';
$string['vocab:viewreports'] = 'View reports for a Vocabulary activity';
$string['vocablevel'] = 'Vocabulary level';
$string['word'] = 'Word';
$string['word_help'] = 'The target word or vocabulary item for which the questions were generated.';
$string['wordlist'] = 'Word list';
$string['wordlistcontainingnwords'] = 'Word list (containing {$a} words)';
$string['youneedtoenrol'] = 'You need to enrol in this course before you can access this Vocabulary activity.';
