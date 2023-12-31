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
$string['questionsettings'] = 'Question settings';
$string['categorysettings'] = 'Category settings';

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
