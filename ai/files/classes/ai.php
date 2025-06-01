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
 * @package    vocabai_files
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_files;

/**
 * ai
 *
 * @package    vocabai_files
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'files';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'filedescription', 'fileitemid',
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var array the names of config settings that can be exported.
     */
    const EXPORTSETTINGNAMES = [
        'filedescription', 'fileitemid', 'filename',
        // ChatGPT file tuning settings.
        'chatgptfileid', 'chatgptjobid', 'chatgptmodelid',
        // Sharing settings.
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var array the names of settings that are files.
     */
    const FILESETTINGNAMES = ['fileitemid'];

    /**
     * @var string containing type of this AI subplugin
     * (see SUBTYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $subtype = self::SUBTYPE_INPUT;

    /** @var string the name of the field used to sort config records. */
    const CONFIG_SORTFIELD = 'filedescription';

    /**
     * @var bool to signify whether or not duplicate records,
     * i.e. records with the same owner and context, are allowed.
     */
    const ALLOW_DUPLICATES = true;
}
