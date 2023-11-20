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

$string['pluginname'] = 'Import dictionary and word data';
$string['privacy:metadata'] = 'The vocabtool_import plugin does not store any personal data.';

$string['actionaddandupdate'] = 'Add new words and update existing words';
$string['actionaddnewonly'] = 'Add new words (and skip existing words)';
$string['actionupdateexisting'] = 'Update existing words (and skip new words)';
$string['actionaddupdateremove'] = 'Add new, update existing and remove missing words';

$string['datafile_help'] = 'Add the file (Excel, OpenOffice, csv or plain text) that contains data about the words you wish to import into this activity.';
$string['datafile'] = 'Data file';

$string['formatfile_help'] = 'Add the XML file that specifies the format of the contents in the data file.';
$string['formatfile'] = 'Format file';

$string['preview'] = 'Preview raw data';
$string['previewrows_help'] = 'Choose the number of rows from the import file that you wish to preview.';
$string['previewrows'] = 'Preview rows';

$string['ignorevalues_help'] = 'A comma-separated list of values that should be ignored, (i.e. treated as empty) in the input file.';
$string['ignorevalues'] = 'Ignore values';

$string['import'] = 'Import data';
$string['uploadaction_help'] = 'Select the action to be taken for each row of data in the uploaded file.';
$string['uploadaction'] = 'Upload action';

$string['emptydatafile'] = 'The data file was empty, missing or unreadable.';
$string['emptyxmlfile'] = 'The XML file was empty or missing.';
$string['invalidxmlfile'] = 'The content of the XML file is invalid.';
$string['showsampleformatxml'] = 'Sample XML code for the format file is shown below:';

$string['explainmetadata'] = '"meta" {$a} contain headings and settings, whereas "data" {$a} contain the actual data values.';
$string['explainstartend'] = 'Define "start" (default=1) and "end" (default=last) {$a}.';
$string['explainsettings'] = 'Default values for form settings can be overridden by specifying the name and value.';

$string['headingsandpreviewrows'] = 'The headings and first {$a} rows of data appear below.';
$string['review'] = 'Review formatted data';

$string['tryagain'] = 'Please go back and try again.';
$string['sheetrowcount'] = 'File "{$a->filename}" has {$a->sheetcount} sheets and contains {$a->rowcount} rows of data.';
$string['row'] = 'Row';
$string['sheet'] = 'Sheet';

$string['xmltagmissing'] = 'An expected tag, {$a}, is missing from the XML file.';

$string['addedrecordtotable'] = 'Added new {$a->recordtype} to the {$a->tabletype} table.';
$string['addrecordtotable'] = 'Add new {$a->recordtype} to the {$a->tabletype} table.';

$string['recordtypeantonyms'] = 'Antonym';
$string['recordtypecorpuses'] = 'Corpus';
$string['recordtypedefinitions'] = 'Definition';
$string['recordtypefrequencies'] = 'Frequency';
$string['recordtypelangnames'] = 'Language name';
$string['recordtypelangs'] = 'Language code';
$string['recordtypelemmas'] = 'Lemma';
$string['recordtypelevelnames'] = 'Level name';
$string['recordtypelevels'] = 'Level code';
$string['recordtypemultimedia'] = 'Multimedia file';
$string['recordtypepronunciations'] = 'Word pronunciation';
$string['recordtypesynonyms'] = 'Synonym';
$string['recordtypewords'] = 'Word';

$string['vocab_antonyms'] = 'Antonyms (i.e. words with opposite meaning)';
$string['vocab_corpuses'] = 'Corpuses (i.e. collection of written texts)';
$string['vocab_definitions'] = 'Definitions';
$string['vocab_frequencies'] = 'Frequencies';
$string['vocab_langnames'] = 'Language names';
$string['vocab_langs'] = 'Language codes';
$string['vocab_lemmas'] = 'Lemmas (i.e. headwords in a dictionary)';
$string['vocab_levelnames'] = 'Level names';
$string['vocab_levels'] = 'Level codes';
$string['vocab_multimedia'] = 'Multimedia files';
$string['vocab_pronunciations'] = 'Word pronunciations';
$string['vocab_synonyms'] = 'Synonyms (i.e. words with similar meaning)';
$string['vocab_words'] = 'Words';

$string['tableaccessnotallowed'] = 'This tool is not allowed to access table "{$a}".';
$string['fieldaccessnotallowed'] = 'This tool is not allowed to access field "{$a->fieldname}" in table "{$a->tablename}".';
$string['idparametermissing'] = 'Cannot get/create ID in {$a->tablename} table: {$a->fieldname} value is missing.';
$string['valueshortened'] = 'A "{$a->fieldname}" value was shortened to fit in {$a->maxlength} characters.';
$string['recordsadded'] = '{$a} records added';
$string['recordsfound'] = '{$a} records found';
$string['errorsfound'] = '{$a} errors found';
$string['importcompleted'] = 'Import is complete';