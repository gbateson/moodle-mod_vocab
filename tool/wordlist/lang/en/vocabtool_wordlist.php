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
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'Edit a word list for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabtool_wordlist plugin does not store any personal data.';
$string['wordlist'] = 'Edit word list';

$string['currentlist'] = 'Current word list';

$string['addwords_help'] = 'Here you can specify words and phrases that you wish to add to the word list for this Vocabulary activity. The words should be separated by commas or line breaks (i.e. one word per line). Type or paste the words into the box and then press the "Add" button.';
$string['addwords'] = 'Add more words or phrases';

$string['selectwords_help'] = 'Here you can specify how many words you would like to be selected randomly from the database and added to this wordlist. Type the number of words into the box and then press the "Select" button.';
$string['selectwords'] = 'Select random words';

$string['importfile_help'] = 'Here you can import a word list from a file. You can create your own text file with one word or phrase per line, or you can use a file that has been exported from another Vocabulary activity.';
$string['importfile'] = 'Import file';

$string['exportfile_help'] = 'Here you can export this word list to a file. You can define a file name yourself, or use the default name. The exported file can be kept as a backup, or imported into another Vocabulary activity.';
$string['exportfile'] = 'Export file name';

$string['add'] = 'Add';
$string['select'] = 'Select';
$string['import'] = 'Import';
$string['export'] = 'Export';

$string['wordaddedtolist'] = 'Word "{$a}" was added to this word list.';
$string['wordexistsinlist'] = 'Word "{$a}" is already in this word list.';
