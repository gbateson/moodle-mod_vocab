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
 * tool/import/lang/en/vocabai_tts.php
 *
 * @package    vocabai_tts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['pluginname'] = 'TTS (OpenAI) AI assistant for a Vocabulary activity.';
$string['privacy:metadata'] = 'The vocabai_tts plugin does not store any personal data.';
$string['tts'] = 'TTS (OpenAI)';

$string['keysownedbyotherusers'] = 'Keys owned by other users';
$string['keysownedbyme'] = 'Keys owned by me';

$string['keysownedbyme'] = 'Keys owned by me';
$string['keysownedbyothers'] = 'Keys owned by other users';
$string['otherkeysownedbyme'] = 'Other keys owned by me';

$string['addnewkey'] = 'Add a new key';
$string['editkey'] = 'Edit existing key';
$string['key'] = 'Key';
$string['owner'] = 'Owner';

$string['ttsurl_help'] = 'The URL of TTS (OpenAI)\'s API e.g. https://api.openai.com/v1/audio/speech';
$string['ttsurl'] = 'TTS url';

$string['ttskey_help'] = 'The key required to access TTS (OpenAI)\'s API. This usually starts "sk-" followed by 48 random letters and numbers.';
$string['ttskey'] = 'TTS key';

$string['ttsmodel_help'] = 'The TTS (OpenAI) model to be used e.g. tts-1, tts-1-hd';
$string['ttsmodel'] = 'TTS model';

$string['ttsmodelid'] = 'TTS tuned model';
$string['ttsmodelid_help'] = 'The TTS (OpenAI) model that has been tuned using a tuning file.';

$string['tts-1'] = 'Generates creative audio quickly with good quality.';
$string['tts-1-hd'] = 'Generates high-quality, detailed audio with better prompt accuracy.';

$string['voice'] = 'Voice';
$string['voice_help'] = 'The voice to use when generating the speech audio.';
$string['voicealloy'] = 'Alloy (male, US)';
$string['voiceecho'] = 'Echo (male, US)';
$string['voicefable'] = 'Fable (male, UK)';
$string['voiceonyx'] = 'Onyx (male, US)';
$string['voicenova'] = 'Nova (female, US)';
$string['voiceshimmer'] = 'Shimmer (female, US)';

$string['voicerandom'] = 'A randomly selected voice';
$string['voicefemale'] = 'Female (selected at random)';
$string['voicemale'] = 'Male (selected at random)';

$string['response_format'] = 'Audio format';
$string['response_format_help'] = 'The file format in which the speech audio will be returned.';
$string['response_formatmp3'] = 'MP3 format';
$string['response_formatopus'] = 'OPUS format';
$string['response_formataac'] = 'AAC format';
$string['response_formatflac'] = 'FLAC format';
$string['response_formatwav'] = 'WAV format';
$string['response_formatpcm'] = 'PCM format';

$string['speed'] = 'Speed';
$string['speed_help'] = 'The speed of the generated speech audio. Generally, it is best to leave this settings at the default value (1.0), as the speed can be adjusted during playback in an audio player.';

$string['deletekey'] = 'Delete API key for TTS (OpenAI)';
$string['confirmdeletekey'] = 'Are you sure you want to delete this key?';

$string['copykey'] = 'Copy API key for TTS (OpenAI)';
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
