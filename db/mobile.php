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
 * db/mobile.php: Mobile functionality for mod_vocab
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [];

$addons = [
    'mod_vocab' => [
        'handlers' => [
            'view' => [ // Note: any name is OK here.
                'displaydata' => [
                    'title' => 'pluginname',
                    'icon' => $CFG->wwwroot.'/mod/vocab/pix/icon.gif',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                // The "method" must exist in "mod/vocab/classes/output/mobile.php".
                'method' => 'mobile_view_activity',
                // Maybe we could add 'offlinefunctions' => ['mobile_view_activity' => []],
                // but I'm not sure yet how to use those.
                'styles' => [
                    'url' => $CFG->wwwroot . '/mod/vocab/mobile/styles.css',
                    'version' => 1, // Bumping this will regenerate CSS - maybe.
                ],
            ],
        ],
        // Language strings that are used in all the handlers.
        // They can be inserted into the template as follows:
        // {{ 'plugin.mod_vocab.STRINGNAME' | translate }} !!
        'lang' => [
            ['pluginname', 'vocab'],
        ],
    ],
];
