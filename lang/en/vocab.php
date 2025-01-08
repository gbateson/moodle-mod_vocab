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

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Vocabulary activity';
$string['modulenameplural'] = 'Vocabulary activities';
$string['modulename_help'] = 'The Vocab module assists students in learning vocabulary through spaced repetition.

A single Vocabulary activity contains a list of vocabulary for student to focus on. The teacher can supply of list of target words, or can allow the software choose words for each student depending on their level.

Students familiarize themselves with the target vocabulary through a variety of game-like activities. Unknown vocabulary is recycled frequently, while known vocabulary is recycled at greater and greater intervals.';
$string['modulename_link'] = 'mod/vocab/view';
$string['pluginadministration'] = 'Vocab administration';
$string['pluginname'] = 'Vocab';

$string['guestsnotallowed'] = 'Guests are not allowed to access this Vocabulary activity. Please login and try again.';
$string['youneedtoenrol'] = 'You need to enrol in this course before you can access this Vocabulary activity.';

$string['activityname_help'] = 'This is the name of the Vocabulary actvity. The name is is displayed on the course page, and within the activity itself.';
$string['activityname'] = 'Activity Name';

$string['operationmode_help'] = 'In "Live mode", this activity displays real word lists and student data. In "demonstration mode", it displays sample wordlists and student results, in order to give an impression of how real data would be represented.';
$string['operationmode'] = 'Operation mode';

$string['demonstrationmode'] = 'Demonstration mode';
$string['livemode'] = 'Live mode';

$string['activityopen'] = 'Activity opens';
$string['activityopen_help'] = 'Students can access this activity starting from this date and time. Before this date and time, the activity will be closed.';
$string['activityclose'] = 'Activity closes';
$string['activityclose_help'] = 'Students can to access this activity up until the date and time specified here. After this date and time, the activity will be closed.';
$string['gamesopen'] = 'Games playable from';
$string['gamesopen_help'] = 'Students can view and interact with games starting from this date and time. Before this date, the games will not be accessible.';
$string['gamesclose'] = 'Games playable until';
$string['gamesclose_help'] = 'Students can view and interact with games until this date and time. After this date, students cannot access the games, but they can still view their results.';

$string['gradedesc'] = 'The grade is set as the percentage of words that have been mastered, according to the "Mastery conditions" defined below.';

$string['grademax'] = 'Maximum grade';
$string['grademax_help'] = 'This setting specifies the maximum grade for this activity. If set to 0, this activity does not appear in the grades pages.';

$string['gradecount'] = 'Minimum word count';
$string['gradecount_help'] = 'The number of words that students are required to master in order to complete this activity. If empty, or "0", or greater than the number of items in the word list, then *all* words must be mastered.';

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

$string['anywordscores'] = 'Any word scores';
$string['highestwordscores'] = 'Highest word scores';
$string['lowestwordscores'] = 'Lowest word scores';
$string['newestwordscores'] = 'Latest word scores';
$string['oldestwordscores'] = 'Earliest word scores';

$string['gradepartial'] = 'Include partially completed words';
$string['gradepartial_help'] = 'Choose whether or not the activity grade should include scores for partially completed words. A partially completed word is one that has been studied but not yet fully mastered according to the "Mastery conditions" defined below.';

$string['nowordsforyou'] = 'Sorry, this vocabulary activity does not yet contain any words for you to study. Please try again later.';
$string['nowordsfound'] = 'This vocabulary activity does not yet contain any words. Please use the tools menu to import or define a word list.';

$string['resultstitle'] = 'Vocabulary results for {$a}';
$string['resultdesc'] = '{$a->label}{$a->delimiter} {$a->number}/{$a->total} ({$a->percent}%)';
$string['resultsdesc'] = '{$a->completed}, {$a->inprogress}, {$a->notstarted}';
$string['completed'] = 'Completed';
$string['inprogress'] = 'In progress';
$string['notstarted'] = 'Not started';

$string['wordlist'] = 'Word list';
$string['wordlistcontainingnwords'] = 'Word list (containing {$a} words)';

$string['tools'] = 'Tools';
$string['subplugintype_vocabtool'] = 'Vocabulary tool';
$string['subplugintype_vocabtool_plural'] = 'Vocabulary tools';

$string['reports'] = 'Reports';
$string['subplugintype_vocabreport'] = 'Vocabulary report';
$string['subplugintype_vocabreport_plural'] = 'Vocabulary reports';

$string['games'] = 'Games';
$string['subplugintype_vocabgame'] = 'Vocabulary game';
$string['subplugintype_vocabgame_plural'] = 'Vocabulary games';

$string['ais'] = 'AI assistants';
$string['subplugintype_vocabai'] = 'AI assistant';
$string['subplugintype_vocabai_plural'] = 'AI assistants';

$string['vocab:addinstance'] = 'Add a new Vocabulary activity';
$string['vocab:attempt'] = 'Attempt a Vocabulary activity';
$string['vocab:deleteattempts'] = 'Delete attempts at a Vocabulary activity';
$string['vocab:manage'] = 'Manage a Vocabulary activity';
$string['vocab:preview'] = 'Preview a Vocabulary activity';
$string['vocab:reviewmyattempts'] = 'Review your own attempts at a Vocabulary activity';
$string['vocab:view'] = 'View a Vocabulary activity';
$string['vocab:viewreports'] = 'View reports for a Vocabulary activity';

$string['masteryconditions'] = 'Mastery conditions';

$string['attemptscore'] = 'Minimum attempt score';
$string['attemptscore_help'] = 'The minimum score required for an attempt to qualify as a successful attempt at a word.';

$string['attemptcount'] = 'Minimum attempt count';
$string['attemptcount_help'] = 'The number of successful attempts required to demonstrate mastery of a word.';

$string['attemptduration'] = 'Maximum total duration of attempts';
$string['attemptduration_help'] = 'The maximum total duration for attempts which satisfy the score and count conditions.';

$string['attempttype'] = 'Attempt type';
$string['attempttype_help'] = 'The type of attempts to be considered by the attempt count condition.

**Any attempts**
Any attempts will be considered.

**Most recent attempts**
Only the most recent attempts will be considered.

**Consecutive attempts**
Any block of consecutive attempts will be considered.';

$string['anyattempts'] = 'Any attempts';
$string['consecutiveattempts'] = 'Consecutive attempts';
$string['recentattempts'] = 'Most recent attempts';

$string['attemptdelay'] = 'Minimum delay between attempts';
$string['attemptdelay_help'] = 'The minimum delay between attempts. This setting can be used to prevent students from cramming their vocbulary study into a short period of time.';

$string['fixeddelay'] = 'Fixed delay';
$string['expandingdelay'] = 'Expanding delay (i.e. spaced repetition)';

$string['expandnavigation_help'] = 'You can use this setting to specify for whom the global navigation menu should be expanded.';
$string['expandnavigation'] = 'Expand navigation';
$string['expandforeveryone'] = 'Always expand (default)';
$string['expandforteachers'] = 'Expand for teachers';
$string['expandforstudents'] = 'Expand for students';
$string['expandfornoone'] = 'Always collapse';

$string['pagelayout_help'] = 'You can use this setting to specify the layout used in the main view page for this Vocabulary activity.';
$string['pagelayout'] = 'Page layout';
$string['pagelayouts'] = 'Page layouts';

$string['layoutbase'] = 'Base layout';
$string['layoutembedded'] = 'Embedded layout';
$string['layoutlogin'] = 'Login layout';
$string['layoutmaintenance'] = 'Maintenance layout';
$string['layoutpopup'] = 'Popup layout';
$string['layoutsecure'] = 'Secure layout';
$string['layoutstandard'] = 'Standard layout';

$string['regions'] = 'Page regions';
$string['defaultregion'] = 'Default page region';
$string['langmenu'] = 'Language menu';
$string['nofooter'] = 'No footer';
$string['nonavbar'] = 'No navigation bar';
$string['nocoursefooter'] = 'No course footer';
$string['noactivityheader'] = 'No activity header ';
$string['notitle'] = 'No title';
$string['nocompletion'] = 'No completion information';
$string['nodescription'] = 'No description';

$string['redoupgrade'] = 'Redo upgrade: {$a}';
$string['redoversiondate'] = '"Vocab activity module version set to just before {$a->version} - {$a->datetext}"';
$string['clicktocontinue'] = 'Click here to continue';

$string['import'] = 'Import';
$string['importfile_help'] = 'Here you can import a word list from a file. You can create your own text file with one word or phrase per line, or you can use a file that has been exported from another Vocabulary activity.';
$string['importfile'] = 'Import file';
$string['export'] = 'Export';
$string['exportfile_help'] = 'Here you can export this word list to a file. You can define a file name yourself, or use the default name. The exported file can be kept as a backup, or imported into another Vocabulary activity.';
$string['exportfile'] = 'Export file name';

$string['sharing'] = 'Sharing';
$string['sharingcontext'] = 'Sharing context';
$string['sharingcontext_help'] = 'The Moodle context in which this item can be shared.';

$string['sharedinusercontext'] = 'Not shared. Only accessible to you, {$a}.';
$string['sharedinsystemcontext'] = 'Shared throughout this entire site Moodle site';
$string['sharedincoursecatcontext'] = 'Shared in all courses in the current course category';
$string['sharedincoursecontext'] = 'Shared in all activities in the current course';
$string['sharedinvocabcontext'] = 'Shared only in the current Vocabulary activity';
$string['sharedinunknowncontext'] = 'Shared in unknown context: {$a}';

$string['sharedfrom'] = 'Shared from';
$string['sharedfrom_help'] = 'This item is shared starting from, and including, this date and time.';

$string['shareduntil'] = 'Shared until';
$string['shareduntil_help'] = 'This item is shared up to, and including, this date and time.';

$string['sharingperiod'] = 'Sharing period';
$string['sharedanydate'] = 'Shared forever';
$string['sharedfromdate'] = 'Shared from {$a}';
$string['shareduntildate'] = 'Shared until {$a}';
$string['sharedfromuntildate'] = 'Shared from {$a->from} until {$a->until}';

$string['addmissingvalue'] = 'Please add a value here.';

$string['medianotcreated'] = 'Oops, media could not be created by {$a->subplugin}. [filearea={$a->filearea}, itemid={$a->itemid}]';

$string['filename'] = 'File name';
$string['modeltunedbyfile'] = '{$a->model} (tuned by file "{$a->file}")';

$string['contentplugins'] = 'AI content';
$string['contentplugins_help'] = 'Select the AI content (e.g. prompts, formats, tuning files) whose settings you wish to export.';

$string['assistantplugins'] = 'AI assistants';
$string['assistantplugins_help'] = 'Select the AI assistants (e.g. ChatGPT, DALL-E, TTS) whose settings you wish to export.';

