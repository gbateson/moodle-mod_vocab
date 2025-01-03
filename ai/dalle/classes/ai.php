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
        'response_format',
        'filetype', 'filetypeconvert',
        'quality', 'qualityconvert',
        'size', 'sizeconvert',
        'keeporiginals', 'style', 'n',
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
     * Get media files and store them in the specified filearea.
     * If several files are generated, they will *all* be converted
     * and stored, but only the first one will be returned by this method.
     *
     * @param array $filerecord
     * @param string $prompt
     * @return stored_file or error message as a string.
     */
    public function get_media_file($filerecord, $prompt) {

        static $fs = null;
        if ($fs === null) {
            $fs = get_file_storage();
        }

        // Initialize arguments for error strings.
        $a = (object)[
            'subplugin' => $this->plugin,
            'filearea' => $filerecord['filearea'],
            'itemid' => $filerecord['itemid'],
        ];

        $media = $this->get_response($prompt);

        if (empty($media)) {
            return $this->get_string('medianotcreated', $a).' empty(media)';
        }

        if (is_string($media)) {
            return $media; // Probably an error message.
        }

        if (! empty($media->error)) {
            return $media->error;
        }

        if (! isset($media->images)) {
            return $this->get_string('medianotcreated', $a).' ! isset(media->images)';
        }

        $files = [];
        $errors = [];

        // Main processing loop.
        foreach ($media->images as $i => $image) {

            if (! empty($image['content'])) {
                // Create file from string.
                $file = $fs->create_file_from_string($filerecord, $image['content']);
            } else if (! empty($image['url'])) {
                // Create file from URL.
                $file = $fs->create_file_from_url($filerecord, $image['url']);
            } else {
                $file = null; // Shouldn't happen !!
            }

            if (empty($file)) {
                $errors[] = $this->get_string('medianotcreated', $a).' empty(file)';
                continue; // Shouldn't happen, but we can try to continue !!
            }

            // Note that Dalle always returns PNG.
            // It can be converted using $fs->convert_image().
            // At the same time, we can also scale the image
            // and reduce quality, thereby reducing file size.

            // Initialize the adjustable properties.
            $width = $newwidth = 0;
            $height = $newheight = 0;
            $quality = $newquality = null;
            $filetype = $newfiletype = 'png';

            if ($imageinfo = $file->get_imageinfo()) {
                $width = (int)$imageinfo['width'];
                $height = (int)$imageinfo['height'];
                $filetype = $imageinfo['mimetype']; // E.g. "image/png".
                $filetype = str_replace('image/', '', $filetype);
            } else {
                $name = 'size';
                if (! empty($this->config->$name)) {
                    $size = explode('x', $this->config->$name);
                    if (count($size) == 2) {
                        $size = array_map('intval', $size);
                        list($width, $height) = $size;
                    }
                }
                $name = 'filetype';
                if (! empty($this->config->$name)) {
                    $filetype = strtolower($this->config->$name);
                }
            }

            $name = 'sizeconvert';
            if (! empty($this->config->$name)) {
                $size = explode('x', $this->config->$name);
                if (count($size) == 2) {
                    $size = array_map('intval', $size);
                    list($newwidth, $newheight) = $size;
                }
            }

            $name = 'filetypeconvert';
            if (! empty($this->config->$name)) {
                $newfiletype = strtolower($this->config->$name);
            }

            $name = 'quality';
            if (! empty($this->config->$name)) {
                $quality = $this->config->$name;
                if ($quality == 'hd') {
                    $quality = 100;
                } else {
                    $quality = 75;
                }
            }

            $name = 'qualityconvert';
            if (! empty($this->config->$name)) {
                $newquality = (int)$this->config->$name;
                // This should already be a percentage.
            }

            // Do we need to convert this image? Usually, we do.
            $convertimage = true;
            if ($filetype == $newfiletype && $quality == $newquality) {
                if ($width == $newwidth && $height == $newheight) {
                    $convertimage = false;
                }
            }

            if ($convertimage) {
                mtrace('['.get_string('ok').']');
                mtrace("Converting image to $filetype ($newwidth x $newheight) ...", ' ');

                // Cache the file id, so that we can delete
                // it later if it is no longer required.
                $fileid = $file->get_id();

                // Set the new filename.
                $filename = $filerecord['filename'];
                $filename = pathinfo($filename, PATHINFO_FILENAME);
                $filerecord['filename'] = "$filename.$newfiletype";

                // Convert the image. Note that
                // the old image is kept by default.
                $file = $fs->convert_image(
                    $filerecord, $fileid,
                    $newwidth, $newheight,
                    true, $newquality
                );

                $name = 'keeporiginals';
                if (empty($this->config->$name)) {
                    mtrace('['.get_string('ok').']');
                    mtrace('Deleting original image ...', ' ');
                    $fs->get_file_by_id($fileid)->delete();
                }

                mtrace('['.get_string('ok').']');
            }

            $files[] = $file;
        }

        if (count($files)) {
            return reset($files);
        }
        if (count($errors)) {
            return reset($errors);
        } else {
            return $this->get_string('medianotcreated', $a);
        }
    }

    /**
     * Send a prompt to an AI assistant and get the response.
     *
     * @param string $prompt
     * @return object containing "images" and "error" properties.
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
        ];

        // Set optional POST fields.
        // The "n" parameter is only allowed on dall-e-2.
        $params = [
            'response_format' => PARAM_ALPHANUMEXT,
            'quality' => PARAM_ALPHA,
            'size' => PARAM_ALPHANUM,
            'style' => PARAM_ALPHA,
        ];
        if ($model == 'dall-e-2') {
            $params['n'] = PARAM_INT;
        }
        foreach ($params as $name => $type) {
            if (empty($this->config->$name)) {
                continue;
            }
            $this->postparams[$name] = clean_param($this->config->$name, $type);
        }

        if ($this->curlcount == 1) {
            // Send the prompt and get the response.
            $response = $this->curl->post(
                $this->config->dalleurl, json_encode($this->postparams)
            );
            $responses = [$response];
        } else {
            // Send multiple requests and get multiple responses.
            for ($i = 0; $i < $this->curlcount; $i++) {
                $requests[] = ['url' => $this->config->dalleurl];
            }
            $options = [
                'CURLOPT_POST' => 1,
                'CURLOPT_POSTFIELDS' => json_encode($this->postparams),
            ];
            $responses = $this->curl->multi($requests, $options);
        }

        if ($error = $this->curl->error) {
            return (object)['error' => get_string('error').': '.$error];
        }

        $images = [];
        $errors = [];
        foreach ($responses as $response) {

            // Extract result details (force array structure).
            $response = json_decode($response, true);

            if (is_array($response) && array_key_exists('error', $response)) {
                $error = [];
                foreach (['type', 'code', 'message'] as $name) {
                    if (array_key_exists($name, $response['error'])) {
                        $error[] = $name.': '.$response['error'][$name];
                    }
                }
                if ($error = implode(', ', $error)) {
                    $errors[] = get_string('error').': '.$error;
                }
                continue;
            }

            // We expect $response['data'] to be an array of imagess,
            // each of which is an array containing "revised_prompt"
            // and either "b64_json" or "url".
    
            if (empty($response['data'])) {
                $error = 'Unexpected response from DALL-E. (no data in response)';
                $errors[] = get_string('error').': '.$error;
                continue;
            }

            // Standardize the response data, prompt and url.
            foreach ($response['data'] as $i => $data) {
                $images[] = [
                    'revised_prompt' => ($data['revised_prompt'] ?? ''),
                    'content' => base64_decode($data['b64_json'] ?? ''),
                    'url' => ($data['url'] ?? ''),
                ];
            }
        }
    
        return (object)['images' => $images, 'error' => implode("\n", $errors)];
    }
}
