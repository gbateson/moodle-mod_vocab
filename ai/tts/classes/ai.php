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
        'quality', 'response_format', 'size', 'style',
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
     * (see AI_TYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $type = self::AI_TYPE_AUDIO;

    /** @var bool enable or disable trace and debugging messages during development. */
    const DEBUG = false;

    /**
     * Send a prompt to an AI assistant and get the response.
     *
     * @param string $prompt
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt) {

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

        if ($this->postparams === null) {

            // Define the role of the AI assistant.
            $role = 'Act as an expert creator of images for web-based learning materials.';

            // Shorten the prompt if necessary.
            // Note: shorten_text() is defined in "lib/moodlelib.php".
            switch ($model) {
                case 'dall-e-2':
                    // For dall-e-2, the maximum length of prompt is 1000 chars.
                    $prompt = shorten_text($prompt, 1000, true);
                    break;
                case 'dall-e-3':
                    // For dall-e-3, the maximum length of prompt is 4000 chars.
                    $prompt = shorten_text($prompt, 4000, true);
                    break;
            }

            // Set the required POST fields.
            $this->postparams = [
                'model' => $model,
                'messages' => [
                    (object)['role' => 'system', 'content' => $role],
                    (object)['role' => 'user', 'content' => $prompt],
                ],
            ];

            // Set optional POST fields.
            foreach (['quality', 'response_format', 'size', 'style'] as $name) {
                if (empty($this->config->$name)) {
                    continue;
                }
                $this->postparams[$name] = $this->config->$name;
            }
        }

        // Send the prompt and get the response.
        $response = $this->curl->post(
            $this->config->ttsurl, json_encode($this->postparams)
        );

        if ($this->curl->error) {
            return (object)['image' => '', 'url' => '', 'prompt' => '', 'error' => $response];
        }

        $response = json_decode($response, true); // Force array structure.

        // We expect an array of image objects,
        // each of which contains b64_json, url, revised_prompt.

        if (empty($response['data'][0])) {
            $error = 'Oops, unexpected response from DALL-E.';
            return (object)['image' => '', 'url' => '', 'prompt' => '', 'error' => $error];
        }

        $response = $response['data'][0];
        if ($image = ($response['b64_json'] ?? '')) {
            $image = base64_decode($image);
        }
        $url = ($response['url'] ?? '');
        $prompt = ($response['revised_prompt'] ?? '');

        return (object)[
            'image' => $image, 'url' => $url,
            'prompt' => $prompt, 'error' => '',
        ];
    }
}
