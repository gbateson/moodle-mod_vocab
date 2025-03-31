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
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabai_tts
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_tts;

/**
 * ai
 *
 * @package    vocabai_tts
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'tts';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'ttsurl', 'ttskey', 'ttsmodel',
        'voice', 'response_format', 'speed',
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var array the names of settings that dates.
     */
    const DATESETTINGNAMES = [
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var string containing type of this AI subplugin
     * (see SUBTYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $subtype = self::SUBTYPE_AUDIO;

    /** @var string the name of the field used to sort config records. */
    const CONFIG_SORTFIELD = 'ttsmodel';

    /** @var bool enable or disable trace and debugging messages during development. */
    const DEBUG = false;

    /**
     * Generates a media file from the given prompt and stores it in the specified filearea.
     * If multiple files are generated, all will be stored, but only the first one is returned.
     *
     * @param string $prompt The input text or script to generate media from.
     * @param array $filerecord An associative array containing file metadata
     * @param int $questionid The ID of the related Moodle question.
     * @param string $speaker (Optional) The speaker identifier (used for audio prompts).
     * @param string $gender (Optional) The speaker's gender or voice style (used for audio prompts).
     *
     * @return stored_file|string The generated stored_file object on success, or an error message string on failure.
     */
    public function get_media_file($prompt, $filerecord, $questionid, $speaker='', $gender='') {

        // Initialize arguments for error strings.
        $a = (object)[
            'subplugin' => $this->plugin,
            'filearea' => $filerecord['filearea'],
            'itemid' => $filerecord['itemid'],
        ];

        // Cache the file storage object.
        $fs = get_file_storage();

        // During development, this media file may already have been created.
        $pathnamehash = $fs->get_pathname_hash(
            $filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
            $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']
        );
        if ($fs->file_exists_by_hash($pathnamehash)) {
            return $fs->get_file_by_hash($pathnamehash);
            // Alternatively, the file could be deleted
            // by adding '->delete()' to the above line.
        }

        $media = $this->get_response($prompt, $questionid, $speaker, $gender);

        if (empty($media)) {
            return $this->get_string('medianotcreated', $a).' empty(media)';
        }

        if (! empty($media->error)) {
            return $media->error;
        }

        if (empty($media->data)) {
            return $this->get_string('medianotcreated', $a).' empty(media->data)';
        }

        if (! $file = $fs->create_file_from_string($filerecord, $media->data)) {
            return $this->get_string('medianotcreated', $a).' empty(file)';
        }

        return $file;
    }

    /**
     * Sends a text prompt to the configured TTS (Text-to-Speech) service and returns the response.
     *
     * This method builds a request using the current AI configuration, optionally selecting
     * a voice model based on the question ID, and submits the request using Moodle's curl wrapper.
     *
     * @param string $prompt The input text or script to be converted to speech.
     * @param int $questionid (Optional) The ID of the question, used to consistently select a voice.
     * @param string $speaker (Optional) The speaker identifier (not currently used, reserved for future use).
     * @param string $gender (Optional) The speaker's gender or voice style (not currently used, reserved for future use).
     *
     * @return object|null An object with:
     *   - string $data: The raw audio content or API response (e.g., base64 or binary),
     *   - string $error: An error message if one occurred (empty string otherwise),
     * or `null` if configuration settings are missing.
     */
    public function get_response($prompt, $questionid=0, $speaker='', $gender='') {

        // Ensure we have the basic settings.
        if (empty($this->config->ttsurl)) {
            return null;
        }
        if (empty($this->config->ttskey)) {
            return null;
        }
        if (empty($this->config->ttsmodel)) {
            return null;
        }

        // If a base DALL-E model has been tuned,
        // a ttsmodelid will be available.
        $name = 'ttsmodelid';
        if (! empty($this->config->$name)) {
            $model = $this->config->$name;
        } else {
            // Otherwise, we use a standard model.
            $model = $this->config->ttsmodel;
        }

        if ($this->curl === null) {
            // Setup new Moodle curl object (see "lib/filelib.php").
            $this->curl = new \curl(['debug' => static::DEBUG]);
            $this->curl->setHeader([
                'Authorization: Bearer '.$this->config->ttskey,
                'Content-Type: application/json',
            ]);
        }

        // Set the required POST fields.
        $this->postparams = [
            'model' => $model,
            'input' => $prompt,
        ];

        // Select random voice, if necessary.
        // The same voice will be used for each question,
        // but different questions may use different voices.
        $this->select_voice($questionid, $speaker, $gender);

        // Set optional POST fields.
        foreach (['voice', 'response_format', 'speed'] as $name) {
            if (empty($this->config->$name)) {
                continue;
            }
            $this->postparams[$name] = $this->config->$name;
        }

        // Send the prompt and get the response.
        $response = $this->curl->post(
            $this->config->ttsurl, json_encode($this->postparams)
        );

        if ($this->curl->error) {
            return (object)['error' => get_string('error').': '.$response];
        }

        if (is_string($response) && $this->is_json($response)) {
            $response = json_decode($media, true);
        }

        if (is_array($response)) {
            return (object)[
                'data' => ($response['data'] ?? []),
                'error' => ($response['error']['message'] ?? ''),
            ];
        }

        // Usually there are no errors, and the $response is a string.
        return (object)['data' => $response, 'error' => ''];
    }

    /**
     * Selects and sets a voice for the given question and speaker.
     *
     * If a gender is provided, it takes priority. Otherwise, the method uses the default
     * configuration or a randomized choice (based on the config or fallback list).
     * The selected voice is stored statically per question and speaker, and only selected once.
     * The final voice is assigned to $this->config->voice for use in TTS requests.
     *
     * @param int $questionid The ID of the question used to maintain consistent voice assignment.
     * @param string $speaker (Optional) The speaker identifier (e.g., 'A', 'B') for multi-speaker dialogs.
     * @param string $gender (Optional) The preferred gender or voice style (e.g., 'male', 'female').
     *
     * @return void, but may update
     */
    public function select_voice($questionid, $speaker='', $gender='') {
        static $voices = [];
        if (empty($voices[$questionid])) {
            $voices[$questionid] = [];
        }
        $name = 'voice';
        $form = '\\vocabai_tts\\form';
        if (empty($voices[$questionid][$speaker])) {
            if ($gender) {
                $voice = $gender;
            } else {
                if (empty($this->config->$name)) {
                    // No voice has been set yet.
                    $voice = $form::VOICE_RANDOM;
                } else {
                    // Use the previously selected voice.
                    $voice = $this->config->$name;
                }
            }
            switch ($voice) {
                case $form::VOICE_FEMALE:
                    $voice = ['nova', 'shimmer'];
                    break;
                case $form::VOICE_MALE:
                    $voice = ['alloy', 'echo', 'fable', 'onyx'];
                    break;
                case $form::VOICE_RANDOM:
                    $voice = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
                    break;
            }
            if (is_array($voice)) {
                $voice = $voice[array_rand($voice)];
            }
            $voices[$questionid][$speaker] = $voice;
        } else {
            $voice = $voices[$questionid][$speaker];
        }
        $this->config->$name = $voice;
    }
}
