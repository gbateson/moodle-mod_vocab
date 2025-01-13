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
 * mod/vocab/ai/prompts/amd/src/form.js
 *
 * A module that provides utility functions for handling DOM elements, such as
 * dynamically adjusting input text widths and textarea heights.
 *
 * @module     vocabai_prompts
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define(['mod_vocab/lib'], function(LIB){

    let FORM = {};

    /**
     * Initializes the module by adjusting input text widths and textarea heights
     * for specific DOM elements.
     */
    FORM.init = function() {
        LIB.setup_dimensions(['id_promptname', 'id_prompttext']);
    };

    return FORM;
});
