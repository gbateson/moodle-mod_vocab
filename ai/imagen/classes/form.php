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
 * ai/imagen/classes/form.php
 *
 * @package    vocabai_imagen
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_imagen;

/**
 * Main settings form for a DALL-E AI assistant subplugin.
 *
 * @package    vocabai_imagen
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "key" field in the config record */
    const CONFIG_KEY = 'imagenkey';

    /** var string the name of the "model" field in the config record */
    const CONFIG_MODEL = 'imagenmodel';

    /** var array containing the names of required fields */
    const REQUIRED_FIELDS = ['imagenurl', 'imagenkey', 'imagenmodel'];

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // Get current year, month and day.
        list($year, $month, $day) = explode(' ', date('Y m d'));

        // For details of API parameters, see:
        // https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/imagen-api#parameter_list
        // https://cloud.google.com/use-cases/text-to-image-ai?hl=en
        // aiplatform.googleapis.com.

        // Define default values for new and incomplete keys.
        $default = (object)[
            // Basic settings.
            'id' => 0,
            'imagenurl' => 'https://generativelanguage.googleapis.com/v1beta',
            'imagenkey' => '',
            'imagenmodel' => 'imagen-3.0-generate-002',
            // Video: veo-2.0-generate-001.
            // Detailed settings.
            'mimetype' => 'png',
            'mimetypeconvert' => 'jpg',
            'compressionquality' => 75, // Range 0-100.
            'aspectratio' => '4:3', // Width:height.
            'maxwidth' => 480, // Actually, 460 better.
            'maxheight' => 320,
            'persongeneration' => 'allow_adult',
            'samplecount' => 2, // Number of image variarions.
            'keeporiginals' => 0,
            // Sharing settings.
            'contextlevel' => CONTEXT_MODULE,
            'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
            'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            // Speed limit settings.
            'itemcount' => '', // Unlimited.
            'itemtype' => static::ITEMTYPE_REQUESTS,
            'timecount' => 1,
            'timeunit' => static::TIMEUNIT_HOURS,
        ];

        // If any of this user's configs are found below,
        // export will be enabled.
        $enableexport = false;

        // Try and get current config for editing.
        if ($config = $this->get_subplugin()->config) {
            $enableexport = true;

            // Transfer values form $config record.
            foreach ($default as $name => $value) {
                if (isset($config->$name)) {
                    $default->$name = $config->$name;
                }
            }

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            $mainheading = 'editkey';
            $submitlabel = get_string('save');

        } else {
            $mainheading = 'addnewkey';
            $submitlabel = get_string('add');
        }

        // Cache the label separator, e.g. ": ".
        $labelsep = get_string('labelsep', 'langconfig');

        // Add configs that are related to this user and/or context.
        list($expanded, $enableexport) = $this->add_configs($mform, $default, 'keys');

        /*////////////////////////////
        // Main form starts here.
        ////////////////////////////*/

        $this->add_heading($mform, $mainheading, $expanded);

        // Cache message that is used for missing form values.
        $addmissingvalue = $this->get_string('addmissingvalue');

        $name = 'imagenurl';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'imagenkey';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'imagenmodel';
        $options = $this->get_imagenmodel_options($default);
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'settings';
        $this->add_heading($mform, $name, true);

        $this->add_field_mimetype($mform, $default);
        $this->add_field_aspectratio($mform, $default);
        $this->add_field_dimensions($mform, $default);
        $this->add_field_persongeneration($mform, $default);

        $this->add_field_samplecount($mform, $default);
        $this->add_field_keeporiginals($mform, $default);

        $this->add_speedlimit_fields($mform, $default);
        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }
    }

    /**
     * Retrieves available Imagen model options from a remote API.
     *
     * Attempts to fetch a list of available Imagen models via an HTTP request
     * using the provided default settings. If the request fails or no models
     * are returned, a fallback option is provided.
     *
     * @param \stdClass $default  An object containing the Imagen service URL and API key.
     *                             - $default->imagenurl (string): The API base URL.
     *                             - $default->imagenkey (string): The API access key.
     *
     * @return array Associative array of model options, with model IDs as keys and display names as values.
     */
    public function get_imagenmodel_options($default) {
        $options = [];
        $descriptions = [];
        if ($default->imagenurl && $default->imagenkey) {
            $curl = new \curl();
            $curl->setHeader(['Content-Type: application/json']);
            $url = $default->imagenurl;
            $key = $default->imagenkey;
            $response = $curl->get("$url/models?key=$key");
            $response = json_decode($response);
            $labelsep = get_string('labelsep', 'langconfig');
            foreach ($response->models as $model) {
                $name = $model->name;
                if (strpos($name, 'models/imagen') === 0) {
                    $name = substr($name, 7);
                    $options[$name] = $model->displayName;
                    $descriptions[$name] = $model->description;
                }
            }
        }
        if (empty($options)) {
            $options = [
                'imagen-3.0-generate-002',
            ];
            $options = array_flip($options);
            foreach ($options as $option => $i) {
                $text = \core_text::strtotitle($option);
                // Struture: <model>-<generation>-<variation>-<version>.
                if (preg_match('/(Imagen)-(\d+\.\d+)-(.*?)-(\d+)$/', $text, $match)) {
                    $text = $match[1].'-'.$match[2].' '.$match[3].' [release '.intval($match[4]).']';
                }
                $options[$option] = $text;
            }
        }
        return $options;
    }

    /**
     * add_field_mimetype
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_mimetype($mform, $default) {
        $name1 = 'mimetype';
        $name2 = 'compressionquality';
        $name3 = 'mimetypeconvert';

        $menu1 = [
            'png' => $this->get_string('mimetypefile', 'PNG'),
            'jpg' => $this->get_string('mimetypefile', 'JPG'),
        ];

        $menu2 = range(100, 5, -5);
        $menu2 = array_combine($menu2, $menu2);
        $menu2 = array_map(function ($num) {
            return "$num%";
        }, $menu2);

        $menu3 = [
            'png' => $this->get_string('mimetypefile', 'PNG'),
            'jpg' => $this->get_string('mimetypefile', 'JPG'),
            'gif' => $this->get_string('mimetypefile', 'GIF'),
        ];

        $names = [$name1, $name2, $name3];
        $menus = [$menu1, $menu2, $menu3];
        $labels = [
            $this->get_string('generateas'),
            $this->get_string($name2),
            $this->get_string('convertto'),
        ];
        $types = [
            PARAM_ALPHANUM,
            PARAM_INT,
            PARAM_ALPHA,
        ];
        $defaults = [
            $default->$name1,
            $default->$name2,
            $default->$name3,
        ];

        $this->add_group_menus($mform, $names, $menus, $labels, $types, $defaults);
        $mform->disabledIf($name2, $name1, 'ne', 'jpg');
    }

    /**
     * Add field aspectratio
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_aspectratio($mform, $default) {
        $name = 'aspectratio';
        $options = ['1:1', '3:4', '4:3', '9:16', '16:9'];
        $options = array_combine($options, $options);
        foreach ($options as $option => $text) {
            list($width, $height) = explode(':', $text);
            if ($width == '16' || $height == '16') {
                $size = $this->get_string('ratiowide');
            } else {
                $size = $this->get_string('rationormal');
            }
            if ($width > $height) {
                $orientation = $this->get_string('ratiolandscape');
            } else if ($width < $height) {
                $orientation = $this->get_string('ratioportrait');
            } else if ($width == $height) {
                $orientation = $this->get_string('ratiosquare');
            }
            $a = (object)[
                'ratio' => $text,
                'size' => $size,
                'orientation' => $orientation,
            ];
            $options[$option] = $this->get_string('ratiodescription', $a);
        }
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
    }

    /**
     * add_field_dimensions
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_dimensions($mform, $default) {
        global $OUTPUT;

        $linebreak = \html_writer::tag('span', '', ['class' => 'w-100']);
        $labelsep = get_string('labelsep', 'langconfig');
        $subheadingstyle = ['style' => 'min-width: 120px;'];
        $subplugin = $this->get_subplugin()->plugin;

        $elements = [];
        $names = ['maxwidth', 'maxheight'];
        foreach ($names as $name) {
            $label = $this->get_string($name);
            $helpicon = $OUTPUT->help_icon($name, $subplugin);
            $subheading = \html_writer::tag('div', $label.$labelsep, $subheadingstyle);
            if (count($elements)) {
                // Add separator to force new line between "rows".
                $elements[] = $mform->createElement('html', $linebreak);
            }
            $elements[] = $mform->createElement('html', $subheading);
            $elements[] = $mform->createElement('text', $name, $label, ['size' => 4]);
            $elements[] = $mform->createElement('html', $helpicon);
        }

        $name = 'dimensions';
        $label = $this->get_string($name);
        $mform->addGroup($elements, $name, $label, ' ', false);
        $this->add_help_button($mform, $name, $name);

        foreach ($names as $name) {
            $mform->setType($name, PARAM_INT);
            $mform->setDefault($name, $default->$name);
        }
    }

    /**
     * add_field_persongeneration (personGeneration)
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_persongeneration($mform, $default) {
        $name = 'persongeneration';
        $options = [
            '' => $this->get_string($name.'_default'),
            'dont_allow' => $this->get_string($name.'_dontallow'),
            'allow_adult' => $this->get_string($name.'_allowadult'),
            'allow_all' => $this->get_string($name.'_allowall'),
        ];
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
    }

    /**
     * Add field samplecount
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_samplecount($mform, $default) {
        $name = 'samplecount';
        $options = array_combine(range(1, 4), range(1, 4));
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name);
    }

    /**
     * add_field_keeporiginals
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_keeporiginals($mform, $default) {
        $name = 'keeporiginals';
        $options = [get_string('no'), get_string('yes')];
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name);
    }
}
