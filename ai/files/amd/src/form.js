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
 * mod/vocab/ai/files/amd/src/form.js
 *
 * @module     vocabai_files
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define([], function(){

    let JS = {};

    /**
     * Adds an event listener to a DOM element.
     * @param {Element} obj - The DOM element to which the event listener is added.
     * @param {string} evt - The name of the event (e.g., "click", "input").
     * @param {Function} fn - The callback function to execute when the event is triggered.
     * @param {boolean} [useCapture=false] - Specifies whether to use event capturing or bubbling.
     */
    JS.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent('on' + evt, fn);
        }
    };

    /**
     * Initializes the module by adjusting input text widths and textarea heights
     * for specific DOM elements.
     */
    JS.init = function() {
        JS.fix_inputtext_width(['id_filedescription', 'id_exportfileelements_exportfile']);
    };

    /**
     * Determines if a given element is within a collapsed fieldset.
     * @param {Element} elm - The DOM element to check.
     * @returns {boolean} `true` if the element is within a collapsed fieldset; otherwise, `false`.
     */
    JS.is_collapsed = function(elm) {
        const fieldset = elm.closest("fieldset.collapsible");
        return (fieldset && fieldset.matches(".collapsed"));
    };

    /**
     * Dynamically sets the width or height of an element based on its content.
     * @param {Element} elm - The DOM element to adjust (input or textarea).
     * @param {string} size - The dimension to adjust ("width" or "height").
     */
    JS.guess_and_set_size = function(elm, size) {
        const tmp = Object.assign(
            document.createElement(elm.tagName), {
                'type': (elm.type || ''),
                'value': (elm.value || ''),
                'className': (elm.className || ''),
                'style': 'visibility: hidden;',
            }
        );
        document.body.appendChild(tmp);
        switch (size) {
            case 'width':
                tmp.style.width = "auto";
                elm.style.width = (tmp.scrollWidth + 6) + "px";
                break;
            case 'height':
                tmp.style.height = "auto";
                elm.style.height = (tmp.scrollHeight + 6) + "px";
                break;
        }
        tmp.remove();
    };

    /**
     * Adjusts the width of input text fields dynamically.
     * @param {string[]|string} ids - An array of element IDs or a comma-separated string of IDs.
     */
    JS.fix_inputtext_width = function(ids) {
        if (typeof(ids) === 'string') {
            ids = ids.split(',').map((id) => id.trim());
        }
        ids.forEach(function(id){
            const inputtext = document.getElementById(id);
            if (inputtext && inputtext.matches('input[type="text"]')) {
                // Add event listener that adjusts width to accommodate content.
                JS.add_event_listener(inputtext, 'input', function() {
                    this.style.width = 'auto'; // Reset width temporarily
                    this.style.width = (this.scrollWidth + 6) + "px"; // Adjust width
                });
                if (JS.is_collapsed(inputtext)) {
                    JS.guess_and_set_size(inputtext, 'width');
                } else {
                    inputtext.dispatchEvent(new Event('input'));
                }
            }
        });
    };

    /**
     * Adjusts the height of textarea elements dynamically.
     * @param {string[]|string} ids - An array of element IDs or a comma-separated string of IDs.
     */
    JS.fix_textarea_height = function(ids) {
        if (typeof(ids) === 'string') {
            ids = ids.split(',').map((id) => id.trim());
        }
        ids.forEach(function(id){
            const textarea = document.getElementById(id);
            if (textarea && textarea.matches('textarea')) {
                // Add event listener that adjusts height to accommodate content.
                JS.add_event_listener(textarea, 'input', function() {
                    this.style.height = 'auto'; // Reset height temporarily
                    this.style.height = (this.scrollHeight + 6) + 'px'; // Adjust height
                });
                if (JS.is_collapsed(textarea)) {
                    JS.guess_and_set_size(textarea, 'height');
                } else {
                    textarea.dispatchEvent(new Event('input'));
                }
            }
        });
    };

    return JS;
});