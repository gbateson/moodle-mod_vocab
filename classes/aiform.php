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
 * Internal library of functions for the Vocabulary activity module
 *
 * @package    mod_vocab
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocab;

/**
 * \mod_vocab\aiform
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
abstract class aiform extends \mod_vocab\subpluginform {
    /**
     * Add a filepicker or filemanager field to the given $mform.
     *
     * @param string $type either "filemanager" or "filepicker"
     * @param moodleform $mform representing the Moodle form
     * @param string $name
     * @param array $attributes (optional, default=null)
     * @param array $options (optional, default=null)
     * @return void (but will update $mform)
     */
    public function add_field_file($type, $mform, $name, $attributes=null, $options=null) {

        // Add file picker/manager in the normal way.
        parent::add_field_file($type, $mform, $name, $attributes, $options);

        // Fetch previously existing file, if any.
        if ($config = $this->get_subplugin()->config) {
            $draftitemid = 0;
            file_prepare_draft_area(
                // The file saved in the specified filearea with the specified $itemid
                // will be copied to the draft filearea with returned $draftitemid.
                // We use the field name as the name of the fielara (e.g. promptfile).
                $draftitemid, $config->contextid, $this->subpluginname, $name, $config->id
                // When "file_prepare_draft_area()" is called with draftitemid (the first argument)
                // set to 0 or null, then it will be assigned automatically, and the files
                // for this filearea will be transferred automatically, which is what we want.
            );
            if ($draftitemid) {
                $mform->setDefault($name, $draftitemid);
            }
        }
    }
}
