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

$string['wordmasteryconditions'] = 'Word mastery conditions';

$string['attemptscore'] = 'Minimum attempt score';
$string['attemptscore_help'] = 'The minimum score required for an attempt to qualify as a successful attempt at a word.';

$string['attemptcount'] = 'Minimum attempt count';
$string['attemptcount_help'] = 'The minimum number of successful attempts required to demonstrate mastery of a word.';

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
$string['expandingdelay'] = 'Expanding delay (i.e. spaced repitition)';

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

