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
 * @package    vocabai_imagen
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_imagen;

/**
 * ai
 *
 * @package    vocabai_imagen
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'imagen';

    /**
     * @var array the names of config settings that this subplugin maintains.
     *
     * For details of parameter names and values, see:
     * https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/imagen-api
     */
    const SETTINGNAMES = [
        'imagenurl', 'imagenkey', 'imagenmodel',
        'mimetype', 'compressionquality', 'mimetypeconvert',
        'aspectratio', 'maxwidth', 'maxheight',
        'persongeneration', 'samplecount', 'keeporiginals',
        'sharedfrom', 'shareduntil',
        'itemcount', 'itemtype', 'timecount', 'timeunit',
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

    /** @var string the name of the field used to sort config records. */
    const CONFIG_SORTFIELD = 'imagenmodel';

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

        // Cache the filename and filetype.
        // E.g. questiontext-01-image-01.png.
        $filename = $filerecord['filename'];
        $filerecord['filename'] = '';

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

        $media = $this->get_response($prompt, $questionid);

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

            if (empty($image['content']) && empty($image['url'])) {
                $errors[] = $this->get_string('medianotcreated', $a).' empty(content/url)';
                continue; // Shouldn't happen, but we can try to continue !!
            }

            // We must give each image variation a separate file name.
            $suffix = str_pad(($i + 1), 2, '0', STR_PAD_LEFT);
            $imagefilename = \mod_vocab\activity::modify_filename($filename, '', $suffix);

            // We add random chars to the primt file name to unwanted access.
            $suffix = \mod_vocab\activity::get_random_chars();
            $promptfilename = \mod_vocab\activity::modify_filename($imagefilename, '', $suffix, 'txt');

            $filerecord['filename'] = $imagefilename;
            mtrace('['.get_string('ok').']');
            mtrace("Saving image to $imagefilename ...", ' ');

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

            // Save the prompt in a text file.
            // E.g. questiontext-01-audio-01-01-xntl.txt
            // The random string prevents guessing of the prompt filename.
            if ($prompt = $image['revised_prompt']) {
                $filerecord['filename'] = $promptfilename;

                mtrace('['.get_string('ok').']');
                mtrace("Saving image prompt to $promptfilename ...", ' ');
                $fs->create_file_from_string($filerecord, $prompt);
            }

            // Note that Imagen can return PNG or JPG.
            // Mime type can be converted using $fs->convert_image().
            // At the same time, we can also scale the image.

            // Initialize the adjustable properties.
            $filetype = $newfiletype = 'png';
            $width = $newwidth = $maxwidth = 0;
            $height = $newheight = $maxheight = 0;

            if ($imageinfo = $file->get_imageinfo()) {
                $width = (int)$imageinfo['width'];
                $height = (int)$imageinfo['height'];
                $filetype = $imageinfo['mimetype']; // E.g. "image/png".
                $filetype = str_replace('image/', '', $filetype);
            } else {
                $name = 'mimetype';
                if (! empty($this->config->$name)) {
                    $filetype = strtolower($this->config->$name);
                }
            }

            $name = 'mimetypeconvert';
            if (! empty($this->config->$name)) {
                $newfiletype = strtolower($this->config->$name);
            }

            $name = 'maxwidth';
            if (! empty($this->config->$name)) {
                $maxwidth = $this->config->$name;
            }

            $name = 'maxheight';
            if (! empty($this->config->$name)) {
                $maxheight = $this->config->$name;
            }

            // Do we need to convert this image? Usually, we do.
            $convertimage = ($filetype == $newfiletype ? false : true);

            // Adjust dimensions to fit the maximum width/height.
            $newwidth = $width;
            $newheight = $height;

            if ($maxwidth && ($maxwidth < $newwidth)) {
                $ratio = ($maxwidth / $newwidth);
                $newwidth = intval($newwidth * $ratio);
                $newheight = intval($newheight * $ratio);
                $convertimage = true;
            }
            if ($maxheight && ($maxheight < $newheight)) {
                $ratio = ($maxheight / $newheight);
                $newwidth = intval($newwidth * $ratio);
                $newheight = intval($newheight * $ratio);
                $convertimage = true;
            }

            if ($convertimage && $newwidth && $newheight) {
                mtrace('['.get_string('ok').']');
                mtrace("Converting image to $filetype ($newwidth x $newheight) ...", ' ');

                // Cache the file id, so that we can delete
                // it later if it is no longer required.
                $fileid = $file->get_id();

                // Set the suffix for the new filename.
                // Note that if the filetype has changed,
                // we don't really need a suffix.
                if ($width != $newwidth || $height != $newheight) {
                    $suffix = "{$newwidth}x{$newheight}";
                } else {
                    $suffix = '';
                }
                $filerecord['filename'] = \mod_vocab\activity::modify_filename(
                    $imagefilename, '', $suffix, $newfiletype
                );

                // Convert the image. Note that the old image is kept by default.
                $file = $fs->convert_image($filerecord, $fileid, $newwidth, $newheight);

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
     * @param integer $questionid (optional, default=0)
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt, $questionid=0) {

        // Ensure we have the basic settings.
        if (empty($this->config->imagenurl)) {
            return null;
        }
        if (empty($this->config->imagenkey)) {
            return null;
        }
        if (empty($this->config->imagenmodel)) {
            return null;
        }

        $url = $this->config->imagenurl;
        $model = $this->config->imagenmodel;
        $key = $this->config->imagenkey;
        $url = "$url/models/$model:predict?key=$key";

        // Collect the "parameters" of the request.
        // NOTE: Google API is fussy about camelCase.
        $params = [];
        $outputoptions = [];
        $names = [
            'aspectRatio' => PARAM_TEXT,
            'personGeneration' => PARAM_TEXT,
            'sampleCount' => PARAM_INT,
            'mimeType' => PARAM_TEXT, // Can be image/jpeg or image/png.
            'compressionQuality' => PARAM_INT, // Range 0-100.
            // According to the docs, the following parameters are also available:
            // language, enhanceprompt, safetysetting, sampleimagestyle
            // ... but trying to set them via the API results in errors.
        ];
        foreach ($names as $name => $type) {
            $lowercasename = strtolower($name);
            if (isset($this->config->$lowercasename)) {
                $value = $this->config->$lowercasename;
                if ($value = clean_param($value, $type)) {
                    switch ($name) {

                        case 'mimeType':
                            if ($value == 'jpg') {
                                $value = 'jpeg';
                            }
                            $outputoptions[$name] = "image/$value";
                            break;

                        case 'compressionQuality':
                            if ($outputoptions['mimeType'] == 'image/jpeg') {
                                $outputoptions[$name] = $value;
                            }
                            break;

                        default:
                            $params[$name] = $value;
                    }
                }
            }
        }

        $outputoptions = array_filter($outputoptions);
        if (count($outputoptions)) {
            $params['outputOptions'] = (object)$outputoptions;
        }

        $this->postparams = (object)[
            'instances' => [(object)['prompt' => $prompt]],
            'parameters' => (object)$params,
        ];

        if ($this->curl === null) {
            // Setup new Moodle curl object (see "lib/filelib.php").
            $this->curl = new \curl(['debug' => static::DEBUG]);
            $this->curl->setHeader(['Content-Type: application/json']);
        }

        // Send a single prompt and get a single response.
        // The response may contain more than one image,
        // depedngin on the "samplecount" value.
        $responses = [$this->curl->post(
            $url, json_encode($this->postparams)
        )];

        if ($error = $this->curl->error) {
            return (object)['error' => get_string('error').': '.$error];
        }

        if (is_string($responses)) {
            $error = 'Curl response is a string ('.shorten_text($responses).')';
            return (object)['error' => get_string('error').': '.$error];
        }

        $images = [];
        $errors = [];
        foreach ($responses as $i => $response) {
            if (is_string($response) && $this->is_json($response)) {
                // Decode JSON string (force array structure).
                $response = json_decode($response, true);
            } else if (is_object($response) || is_array($response)) {
                // Ensure all objects are converted to arrays.
                $response = json_decode(json_encode($response), true);
            }

            // Detect and report errors.
            // Message: Setting language is not supported.
            // Message: Only block_low_and_above is supported for safetySetting.
            // Message: sampleImageStyle is currently not supported.
            // Message: enhancePrompt is not adjustable.
            if (is_array($response) && array_key_exists('error', $response)) {
                $error = $response['error'];
                $msg = [];
                if (isset($error['message'])) {
                    $msg[] = 'Message: '.trim($error['message'], ' .');
                }
                if (isset($error['code'])) {
                    $msg[] = 'Code: '.$error['code'];
                }
                if (isset($error['status'])) {
                    $msg[] = 'Status: '.$error['status'];
                }
                $details = [];
                if (isset($error['details'])) {
                    foreach ($error['details'] as $detail) {
                        foreach ($detail['fieldViolations'] as $violation) {
                            $details[] = $violation['field'].': '.$violation['description'];
                        }
                    }
                }
                if ($details = implode("\n", $details)) {
                    $msg[] = 'Details: '.$details;
                }
                if ($error = implode(', ', $msg)) {
                    $errors[] = get_string('error').': '.$error;
                }
                continue;
            }

            if (isset($response['predictions'])) {
                foreach ($response['predictions'] as $prediction) {
                    $images[] = [
                        'content' => base64_decode($prediction['bytesBase64Encoded']),
                        'revised_prompt' => ($prediction['prompt'] ?? ''),
                        'mimetype' => ($prediction['mimeType'] ?? ''),
                        'url' => '',
                    ];
                }
            } else {
                $error = 'Could not find image in response from Imagen';
                $errors[] = get_string('error').': '.$error;
            }
        }
        return (object)[
            'images' => $images,
            'error' => implode("\n", $errors),
        ];
    }
}
