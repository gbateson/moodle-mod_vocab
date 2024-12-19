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
 * mod/vocab/ai/tts/amd/src/form.js
 *
 * @module     vocabai_tts
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define([], function(){

    let JS = {};

    JS.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent('on' + evt, fn);
        }
    };

    /*
     * initialize this AMD module
     */
    JS.init = function() {
        const elm = document.getElementById('id_ttsfile');
        if (elm) {
            // Add event listener that adjusts width to accommodate content.
            JS.add_event_listener(elm, 'input', function(){
                this.style.width = 'auto'; // '1px' also works
                this.style.width = (this.scrollWidth + 6) + 'px';
            });
            elm.dispatchEvent(new Event('input'));
        }
    };

    return JS;
});