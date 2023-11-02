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

$string['pluginname'] = 'Add PHPDocs to files';
$string['privacy:metadata'] = 'The vocabtool_phpdocs plugin does not store any personal data.';

$string['phpdocs'] = 'Add PHPDocs';

$string['folderpath_help'] = 'The folder path under the base folder for this Moodle scripts on the server.';
$string['folderpath'] = 'Folder path';

$string['phpdocsaction_help'] = 'Select what action you would like to take on the PHPDocs in each file.';
$string['phpdocsaction'] = 'PHPDocs action';

$string['copyrightaction_help'] = 'Select what action you would like to take on the copyright notice in each file.';
$string['copyrightaction'] = 'Copyright action';

$string['fixall'] = 'Fix missing and incorrect items';
$string['fixincorrect'] = 'Fix incorrect items';
$string['fixmissing'] = 'Fix missing items';
$string['removeall'] = 'Remove all items';
$string['reportall'] = 'Report missing and incorrect items';
$string['reportincorrect'] = 'Report incorrect items';
$string['reportmissing'] = 'Report missing items';

$string['incorrectphpdocs'] = 'In file "{$a->filepath}", PHPDocs for function/method "{$a->functionname}" seem to be incorrect or incomplete.';
$string['missingphpdocs'] = 'In file "{$a->filepath}", PHPDocs for function/method "{$a->functionname}" are missing.';
$string['phpdocsremoved'] = 'In file "{$a->filepath}", PHPDocs were removed from function "{$a->functionname}"';
$string['phpdocsfixed'] = 'In file "{$a->filepath}", PHPDocs were fixed for function "{$a->functionname}"';
$string['phpdocsadded'] = 'In file "{$a->filepath}", PHPDocs were added to function "{$a->functionname}"';

$string['filetypes_help'] = 'Select the file types to which you would like to add PHPDocs.';
$string['filetypes'] = 'File types';

$string['phpfiles'] = 'PHP files';
$string['jsfiles'] = 'JS files';
$string['cssfiles'] = 'CSS files';
$string['xmlfiles'] = 'XML files';

$string['copyrightadded'] = 'Copyright notice added.';
$string['copyrightremoved'] = 'Copyright notice removed.';
$string['copyrightmissing'] = 'Copyright notice is missing.';

$string['filesettings'] = 'File settings';
$string['searchreplaceactions'] = 'Search and replace actions';
$string['copyrightsettings'] = 'Copyright settings';

$string['package_help'] = 'The "package name" is usually the name of the plugin or subplugin to which the file belongs. e.g. mod_vocab, vocabtool_importdata';
$string['package'] = 'Package name';

$string['startyear_help'] = 'The year in which the copyright started.';
$string['startyear'] = 'Start year';

$string['authorname_help'] = 'The name(s) of the author(s) of these files.';
$string['authorname'] = 'Author name';

$string['authorcontact_help'] = 'The email or website that can be used to contact the author(s).';
$string['authorcontact'] = 'Author contact';

$string['sinceversion_help'] = 'The earliest version of Moodle on which these files will function.';
$string['sinceversion'] = 'Since Moodle version';
