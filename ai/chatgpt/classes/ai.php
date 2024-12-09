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
 * @package    vocabai_chatgpt
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_chatgpt;

/**
 * ai
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'chatgpt';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'chatgpturl', 'chatgptkey', 'chatgptmodel',
        'temperature', 'top_p',
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var array the names of settings that dates.
     */
    const DATESETTINGNAMES = [
        'sharedfrom', 'shareduntil',
    ];

    /**
     * Setup the connection to the AI assistant.
     *
     * @param string $prompt to send to the AI assistant (ChatGPT)
     * @return void, but may update the "curl" property.
     */
    public function setup_connection($prompt) {

        // Ensure we have the basic settings.
        if (empty($this->config->chatgpturl)) {
            return null;
        }
        if (empty($this->config->chatgptkey)) {
            return null;
        }
        if (empty($this->config->chatgptmodel)) {
            return null;
        }

        // Set the maximum number of tokens.
        // Currently this is not used.
        switch ($this->config->chatgptmodel) {
            case 'gpt-4':
                $maxtokens = 8192;
                break;
            case 'gpt-3.5-turbo':
                $maxtokens = 4097;
                break;
            default:
                $maxtokens = 1000;
        }

        if ($this->curl === null) {
            $this->curl = curl_init();

            curl_setopt($this->curl, CURLOPT_URL, $this->config->chatgpturl);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_POST, true);

            curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->config->chatgptkey,
            ]);

            // Define the role of the AI assistant.
            $systemrole = 'Act as an expert producer of online language-learning materials.';

            // Set the required POST fields.
            $params = [
                'model' => $this->config->chatgptmodel,
                'messages' => [
                    (object)['role' => 'system', 'content' => $systemrole],
                    (object)['role' => 'user', 'content' => $prompt],
                ],
            ];

            // Set optional POST fields.
            foreach (['temperature', 'top_p'] as $name) {
                if (empty($this->config->$name)) {
                    continue;
                }
                if (is_numeric($this->config->$name)) {
                    $params[$name] = (float)$this->config->$name;
                }
            }

            // Add the POST fields to the CURL object.
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($params));
        }
        return ($this->curl ? true : false);
    }

    /**
     * Get response from the AI assistant.
     *
     * @return object containing "text" and "error" properties.
     */
    public function get_response() {
        $response = curl_exec($this->curl);
        $response = json_decode($response);
        return (object)[
            'text' => ($response->choices[0]->message->content ?? ''),
            'error' => ($response->error ?? null),
        ];
    }
}
