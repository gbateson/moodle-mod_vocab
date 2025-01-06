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
     * Get media files and store them in the specified filearea.
     * If several files are generated, they will *all* be converted
     * and stored, but only the first one will be returned by this method.
     *
     * @param string $prompt
     * @param array $filerecord
     * @param integer $questionid
     * @return stored_file or error message as a string.
     */
    public function get_media_file($prompt, $filerecord, $questionid) {

        // Initialize arguments for error strings.
        $a = (object)[
            'subplugin' => $this->plugin,
            'filearea' => $filerecord['filearea'],
            'itemid' => $filerecord['itemid'],
        ];

        $media = $this->get_response($prompt, $questionid);

        if (empty($media)) {
            return $this->get_string('medianotcreated', $a).' empty(media)';
        }

        if (! empty($media->error)) {
            return $media->error;
        }

        if (empty($media->data)) {
            return $this->get_string('medianotcreated', $a).' empty(media->data)';
        }

        $fs = get_file_storage();
        if (! $file = $fs->create_file_from_string($filerecord, $media->data)) {
            return $this->get_string('medianotcreated', $a).' empty(file)';
        }

        return $file;
    }

    /**
     * Send a prompt to an AI assistant and get the response.
     *
     * @param string $prompt
     * @param integer $questionid (optional, default=0)
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt, $questionid=0) {

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
        $this->select_voice($questionid);

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
     * Select and set voice (possibly chosen at random) for the given question.
     *
     * @param integer $questionid
     * @return void, but will update $this->config->voice once per question.
     */
    public function select_voice($questionid) {
        static $voices = [];
        if (empty($voices[$questionid])) {
            $name = 'voice';
            $form = '\\vocabai_tts\\form';
            if (empty($this->config->$name)) {
                $voice = $form::VOICE_RANDOM; // Shouldn't happen !!
            } else {
                $voice = $this->config->$name;
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
            $voices[$questionid] = $voice;
            $this->config->$name = $voice;
        }
    }
}
