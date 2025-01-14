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
 * ai/dalle/classes/form.php
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabai_dalle;

/**
 * Main settings form for a DALL-E AI assistant subplugin.
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\aiform {

    /** var string the name of the "key" field in the config record */
    const CONFIG_KEY = 'dallekey';

    /** var string the name of the "model" field in the config record */
    const CONFIG_MODEL = 'dallemodel';

    /** var string a comma-delimited list of required fields */
    const REQUIRED_FIELDS = 'dalleurl, dallekey, dallemodel';

    /**
     * Add fields to the main form for this subplugin.
     */
    public function definition() {
        global $DB, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // If any of this user's configs are found below,
        // export will be enabled.
        $enableexport = false;

        // Try and get current config for editing.
        if ($default = $this->get_subplugin()->config) {
            $enableexport = true;

            $name = 'cid';
            $mform->addElement('hidden', $name, $default->id);
            $mform->setType($name, PARAM_INT);

            $name = 'action';
            $mform->addElement('hidden', $name, $this->get_subplugin()->action);
            $mform->setType($name, PARAM_ALPHA);

            // Check we have expected fields.
            $ai = '\\'.$this->subpluginname.'\\ai';
            foreach ($ai::get_settingnames() as $name) {
                if (empty($default->$name)) {
                    $default->$name = null;
                }
            }

            $mainheading = 'editkey';
            $submitlabel = get_string('save');

        } else {

            $mainheading = 'addnewkey';
            $submitlabel = get_string('add');

            // Get current year, month and day.
            list($year, $month, $day) = explode(' ', date('Y m d'));

            // Define default values for new key.
            $default = (object)[
                'id' => 0,
                'dalleurl' => 'https://api.openai.com/v1/images/generations',
                'dallekey' => '',
                'dallemodel' => 'dall-e-3',
                'response_format' => 'b64_json',
                'filetype' => 'png',
                'filetypeconvert' => 'jpg',
                'quality' => 'standard',
                'qualityconvert' => '75',
                'size' => '1792x1024',
                'sizeconvert' => '420x240',
                'style' => 'natural',
                'keeporiginals' => 0,
                'n' => 1, // Number of variarions.
                'contextlevel' => CONTEXT_MODULE,
                'sharedfrom' => mktime(0, 0, 0, $month, $day, $year),
                'shareduntil' => mktime(23, 59, 59, $month, $day, $year),
            ];
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

        $name = 'dalleurl';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'dallekey';
        $this->add_field_text($mform, $name, PARAM_URL, $default->$name, ['size' => '40']);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'dallemodel';
        $options = ['dall-e-2', 'dall-e-3'];
        $options = array_flip($options);
        foreach ($options as $option => $i) {
            $options[$option] = strtoupper($option).$labelsep.$this->get_string($option);
        }
        $this->add_field_select($mform, $name, $options, PARAM_TEXT, $default->$name);
        $mform->addRule($name, $addmissingvalue, 'required');

        $name = 'settings';
        $this->add_heading($mform, $name, $expanded);

        $this->add_field_response_format($mform, $default);
        $this->add_field_filetype($mform, $default);
        $this->add_field_quality($mform, $default);
        $this->add_field_size($mform, $default);
        $this->add_field_style($mform, $default);
        $this->add_field_keeporiginals($mform, $default);
        $this->add_field_n($mform, $default);

        $this->add_sharing_fields($mform, $default);
        $this->add_action_buttons(true, $submitlabel);

        $this->add_importfile($mform);
        if ($enableexport) {
            $this->add_exportfile($mform);
        }
    }

    /**
     * add_field_response_format
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_response_format($mform, $default) {
        $name = 'response_format';
        $options = [
            'b64_json' => $this->get_string($name.'b64_json'),
            'url' => $this->get_string($name.'url'),
        ];
        $this->add_field_select($mform, $name, $options, PARAM_ALPHANUMEXT, $default->$name);
    }

    /**
     * add_field_filetype
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_filetype($mform, $default) {

        $name = 'filetype';
        $menu1 = [
            'png' => $this->get_string($name.'file', 'PNG'),
        ];
        $menu2 = [
            'png' => $this->get_string($name.'file', 'PNG'),
            'jpg' => $this->get_string($name.'file', 'JPG'),
            'gif' => $this->get_string($name.'file', 'GIF'),
        ];

        $names = [$name, $name.'convert'];
        $menus = [$menu1, $menu2];
        $labels = [
            $this->get_string('generateas'),
            $this->get_string('convertto'),
        ];
        $types = [PARAM_ALPHANUM, PARAM_ALPHA];
        $defaults = [$default->$name, $default->{$name.'convert'}];
        $this->add_group_menus($mform, $names, $menus, $labels, $types, $defaults);
    }

    /**
     * add_field_quality
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_quality($mform, $default) {

        $name = 'quality';
        $menu1 = [
            'standard' => $this->get_string($name.'standard'),
            'hd' => $this->get_string($name.'hd'),
        ];

        $menu2 = range(100, 5, -5);
        $menu2 = array_combine($menu2, $menu2);
        $menu2 = array_map(function ($num) {
            return "$num%";
        }, $menu2);

        $names = [$name, $name.'convert'];
        $menus = [$menu1, $menu2];
        $labels = [
            $this->get_string('generateas'),
            $this->get_string('convertto'),
        ];
        $types = [PARAM_ALPHANUM, PARAM_ALPHA];
        $defaults = [$default->$name, $default->{$name.'convert'}];
        $this->add_group_menus($mform, $names, $menus, $labels, $types, $defaults);
    }

    /**
     * add_field_size
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_size($mform, $default) {

        $name = 'size';
        $menu1 = [
            '1792x1024', '1024x1792', '1024x1024',
            '512x512', '256x256', // DALL-E-2.
        ];
        $menu2 = [
            '1120x640', '840x480', '630x360', '420x240',
            '640x1120', '480x840', '360x630', '240x420',
            '640x640', '480x480', '360x360', '240x240',
        ];
        $names = [$name, $name.'convert'];
        $menus = [
            $this->format_size_menu($menu1, $name),
            $this->format_size_menu($menu2, $name),
        ];
        $labels = [
            $this->get_string('generateas'),
            $this->get_string('convertto'),
        ];
        $types = [PARAM_ALPHANUM, PARAM_ALPHANUM];
        $defaults = [$default->$name, $default->{$name.'convert'}];
        $this->add_group_menus($mform, $names, $menus, $labels, $types, $defaults);
    }

    /**
     * format_size_menu
     *
     * @param array $menu of sizes formatted as WIDTH x HEIGHT.
     * @param string $name of the form element.
     * @return array of menu items [size => text]
     */
    public function format_size_menu($menu, $name) {
        $menu = array_flip($menu);
        foreach (array_keys($menu) as $size) {
            list($width, $height) = explode('x', $size, 2);
            $width = (int)$width;
            $height = (int)$height;
            $orientation = '';
            switch (true) {
                case ($width > $height):
                    $orientation = 'landscape';
                    break;
                case ($width < $height):
                    $orientation = 'portrait';
                    break;
                case ($width == $height):
                    $orientation = 'square';
                    break;
            }
            $menu[$size] = $this->get_string($name.$orientation, $size);
            if ($size == '256x256' || $size == '512x512') {
                $menu[$size] .= ' (DALL-E-2)';
            }
        }
        return $menu;
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

    /**
     * add_field_style
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_style($mform, $default) {
        $name = 'style';
        $options = [
            'vivid' => $this->get_string($name.'vivid'),
            'natural' => $this->get_string($name.'natural'),
        ];
        $this->add_field_select($mform, $name, $options, PARAM_ALPHA, $default->$name);
    }

    /**
     * Add field n (the number of image variations)
     *
     * @param object $mform
     * @param array $default values for new record.
     * @return void, but may update $mform.
     */
    public function add_field_n($mform, $default) {
        $name = 'n';
        $options = array_combine(range(1, 10), range(1, 10));
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default->$name);
    }

    /**
     * add_group_menus
     *
     * @param object $mform
     * @param array $names array of element names.
     * @param array $menus array of option arrays.
     * @param array $labels array of label strings.
     * @param array $types array of PARAM_xxx types.
     * @param object $defaults the default values.
     * @return void, but may update $mform.
     */
    public function add_group_menus($mform, $names, $menus, $labels, $types, $defaults) {
        global $OUTPUT;
        $elements = [];

        // Cache line break element, label separator and subheading style.
        $linebreak = \html_writer::tag('span', '', ['class' => 'w-100']);
        $labelsep = get_string('labelsep', 'langconfig');
        $subheadingstyle = ['style' => 'min-width: 100px;'];
        $subplugin = $this->get_subplugin()->plugin;

        $imax = count($names);
        for ($i = 0; $i < $imax; $i++) {
            if ($i) {
                // Add separator to force new line between "rows".
                $elements[] = $mform->createElement('html', $linebreak);
            }
            $name = $names[$i];
            $menu = $menus[$i];
            $label = $labels[$i];
            $helpicon = $OUTPUT->help_icon($name, $subplugin);
            $subheading = \html_writer::tag('div', $label.$labelsep, $subheadingstyle);
            $elements[] = $mform->createElement('html', $subheading);
            $elements[] = $mform->createElement('select', $name, $label, $menu);
            $elements[] = $mform->createElement('html', $helpicon);
        }

        $name = reset($names).'_elements';
        $label = $this->get_string($name);
        $mform->addGroup($elements, $name, $label, ' ', false);
        $this->add_help_button($mform, $name, $name);

        for ($i = 0; $i < $imax; $i++) {
            $name = $names[$i];
            $type = $types[$i];
            $default = $defaults[$i];
            $mform->setType($name, $type);
            $mform->setDefault($name, $default);
        }
    }
}
