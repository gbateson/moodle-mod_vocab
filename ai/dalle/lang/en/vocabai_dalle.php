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
 * tool/import/lang/en/vocabai_dalle.php
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'DALL-E AI assistant for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_dalle plugin does not store any personal data.';
$string['dalle'] = 'DALL-E';

$string['keysownedbyotherusers'] = 'Keys owned by other users';
$string['keysownedbyme'] = 'Keys owned by me';

$string['keysownedbyme'] = 'Keys owned by me';
$string['keysownedbyothers'] = 'Keys owned by other users';
$string['otherkeysownedbyme'] = 'Other keys owned by me';

$string['addnewkey'] = 'Add a new key';
$string['editkey'] = 'Edit existing key';
$string['key'] = 'Key';
$string['owner'] = 'Owner';

$string['dalleurl_help'] = 'The URL of DALL-E\'s API e.g. https://api.openai.com/v1/images/generations';
$string['dalleurl'] = 'DALL-E url';

$string['dallekey_help'] = 'The key required to access DALL-E\'s API. This usually starts "sk-" followed by 48 random letters and numbers.';
$string['dallekey'] = 'DALL-E key';

$string['dallemodel_help'] = 'The DALL-E model to be used e.g. dall-e-2, dall-e-3';
$string['dallemodel'] = 'DALL-E model';

$string['dallemodelid'] = 'DALL-E tuned model';
$string['dallemodelid_help'] = 'The DALL-E model that has been tuned using this tuning file.';

$string['dall-e-2'] = 'Generates creative images quickly with good quality.';
$string['dall-e-3'] = 'Generates high-quality, detailed images with better prompt accuracy.';

$string['quality'] = 'Image quality';
$string['quality_help'] = 'The quality of the image that will be generated. A value of "High definition" creates images with finer details and greater consistency across the image. This param is only supported for DALL-E-3.';
$string['qualityhd'] = 'High definition';
$string['qualitystandard'] = 'Standard definition';

$string['response_format'] = 'Image format';
$string['response_format_help'] = 'The format in which the generated images are returned. This must be either "url" or "b64_json". URLs are only valid for 60 minutes after the image has been generated.';
$string['response_formatb64_json'] = 'Base64 (json) format';
$string['response_formaturl'] = 'URL';

$string['size'] = 'Image size';
$string['size_help'] = 'The size of the generated images. For DALL-E-2, this must be one of 256x256, 512x512, or 1024x1024. For DALL-E-3, this must be one of 1024x1024, 1792x1024, or 1024x1792.';
$string['size256x256'] = '■ 256 x 256 pixels (DALL-E-2 only)';
$string['size512x512'] = '■ 512 x 512 pixels (DALL-E-2 only)';
$string['size1024x1024'] = '■ 1024 x 1024 pixels';
$string['size1792x1024'] = '▬ 1792 x 1024 pixels (DALL-E-3 only)';
$string['size1024x1792'] = '▮ 1024 x 1792 pixels (DALL-E-3 only)';

$string['style'] = 'Image style';
$string['style_help'] = 'The style of the generated images. Must be either "vivid" or "natural". Vivid causes the model to lean towards generating hyper-real and dramatic images. Natural causes the model to produce more natural, less hyper-real looking images. This param is only supported for DALL-E-3.';
$string['stylevivid'] = 'Vivid style';
$string['stylenatural'] = 'Natural style';

$string['deletekey'] = 'Delete API key for DALL-E';
$string['confirmdeletekey'] = 'Are you sure you want to delete this key?';

$string['copykey'] = 'Copy API key for DALL-E';
$string['confirmcopykey'] = 'Are you sure you want to copy this key?';

$string['editcompleted'] = 'The modified key was successfully saved.';
$string['editcancelled'] = 'Editing of the key was cancelled.';

$string['copycompleted'] = 'The key was successfully copied.';
$string['copycancelled'] = 'Copying of the key was cancelled.';

$string['deletecompleted'] = 'The key was successfully deleted.';
$string['deletecancelled'] = 'Key deletion was cancelled.';

$string['nokeysfound'] = 'No keys found';

$string['note'] = 'Note';
$string['cannoteditkeys'] = 'You cannot edit these keys.';
