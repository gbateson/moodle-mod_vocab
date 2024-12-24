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
 * @package    vocabai_dalle
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_dalle;

/**
 * ai
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'dalle';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'dalleurl', 'dallekey', 'dallemodel',
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
     * (see SUBTYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $subtype = self::SUBTYPE_IMAGE;

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
        if (empty($this->config->dalleurl)) {
            return null;
        }
        if (empty($this->config->dallekey)) {
            return null;
        }
        if (empty($this->config->dallemodel)) {
            return null;
        }

        // If a base DALL-E model has been tuned,
        // a dallemodelid will be available.
        $name = 'dallemodelid';
        if (! empty($this->config->$name)) {
            $model = $this->config->$name;
        } else {
            // Otherwise, we use a standard model.
            $model = $this->config->dallemodel;
        }

        if ($this->curl === null) {
            // Setup new Moodle curl object (see "lib/filelib.php").
            $this->curl = new \curl(['debug' => static::DEBUG]);
            $this->curl->setHeader([
                'Authorization: Bearer '.$this->config->dallekey,
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
                'prompt' => $prompt,
                'n' => 1, // The number of images to created.
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
            $this->config->dalleurl, json_encode($this->postparams)
        );

        if ($this->curl->error) {
            return (object)['error' => $response];
        }

        // Extract response details (force array structure).
        $response = json_decode($response, true);

        // We expect an array of image objects, each of
        // which contains b64_json, url, revised_prompt.

        if (empty($response['data'][0])) {
            $error = 'Oops, unexpected response from DALL-E.';
            return (object)['error' => $error];
        }

        // Create shortcut to the main $response data.
        $response = $response['data'][0];

        return (object)[
            'content' => base64_decode($response['b64_json'] ?? ''),
            'prompt' => ($response['revised_prompt'] ?? ''),
            'url' => ($response['url'] ?? ''),
            'error' => '',
        ];
    }
}
