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
 * mod/vocab/ai/prompts/amd/src/form.LIB
 *
 * A module that provides utility functions for handling DOM elements, such as
 * dynamically adjusting input text widths and textarea heights.
 *
 * @module     vocabai_prompts
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define([], function() {
    let LIB = {};

    /**
     * Adds an event listener to a DOM element.
     * @param {Element} obj - The DOM element to which the event listener is added.
     * @param {string} evt - The name of the event (e.g., "click", "input").
     * @param {Function} fn - The callback function to execute when the event is triggered.
     * @param {boolean} [useCapture=false] - Specifies whether to use event capturing or bubbling.
     */
    LIB.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent('on' + evt, fn);
        }
    };

    /**
     * Determines if a given element is within a collapsed fieldset.
     * @param {Element} elm - The DOM element to check.
     * @returns {boolean} `true` if the element is within a collapsed fieldset; otherwise, `false`.
     */
    LIB.is_collapsed = function(elm) {
        const fieldset = elm.closest("fieldset.collapsible");
        return (fieldset && fieldset.matches(".collapsed"));
    };

    /**
     * Dynamically gets the width or height of an element based on its content.
     * @param {Element} elm - The DOM element to adjust (input or textarea).
     * @param {string} dimension - The dimension to adjust ("width" or "height").
     * @return {integer} the estimated size of the element.
     */
    LIB.get_dimension = function(elm, dimension) {
        if (dimension == 'width') {
            elm.style.width = "auto";
            return elm.scrollWidth;
        }
        if (dimension == 'height') {
            elm.style.height = "auto";
            return elm.scrollHeight;
        }
        return 0;
    };

    /**
     * Attempt to guess the width or height of a hidden/collapsed element based on its content.
     * This is done by creating a temporary element of the same type as the original element,
     * then copying across the content and obtaining the size of the appropriate dimension.
     * @param {Element} elm - The DOM element to adjust (input or textarea).
     * @param {string} dimension - The dimension to adjust ("width" or "height").
     * @return {integer} the estimated size of the element, if it were not hidden/collapsed.
     */
    LIB.guess_dimension = function(elm, dimension) {
        const tmp = document.createElement(elm.tagName);
        if (elm.hasAttribute('type')) {
            tmp.type = elm.type;
        }
        if (elm.hasAttribute('value')) {
            tmp.value = elm.value;
        }
        if (elm.hasAttribute('class') || elm.hasAttribute('className')) {
            tmp.className = elm.className;
        }
        tmp.style.visibility = 'hidden';

        // Add the tmp element to the document and get the dimension value.
        document.body.appendChild(tmp);
        let value = LIB.get_dimension(tmp, dimension);

        // Compensate for border and padding on the original element.
        const cs = window.getComputedStyle(elm);
        if (cs.boxSizing == 'content-box') {
            if (dimension == 'width') {
                value += parseInt(cs.borderLeft) + parseInt(cs.borderRight);
                value += parseInt(cs.paddingLeft) + parseInt(cs.paddingRight);
            } else {
                value += parseInt(cs.borderTop) + parseInt(cs.borderBottom);
                value += parseInt(cs.paddingTop) + parseInt(cs.paddingBottom);
            }
        }

        tmp.remove();
        return value;
    };

    /**
     * Dynamically sets the width or height of an element based on its content.
     * @param {Element} elm - The DOM element to adjust (input or textarea).
     * @param {string} dimension - The dimension to adjust ("width" or "height").
     * @param {integer} value - The new value for the specified dimension. If omitted, it will be set based on the content's size.
     * @return {void} - No value is returned, but the dimension value for the given element may be updated.
     */
    LIB.set_dimension = function(elm, dimension, value) {
        if (elm.value) {
            if (value === undefined) {
                if (LIB.is_collapsed(elm)) {
                    value = LIB.guess_dimension(elm, dimension);
                } else {
                    value = LIB.get_dimension(elm, dimension);
                }
            }
            if (value) {
                elm.style[dimension] = (value + 6) + 'px';
            }
        }
    };

    /**
     * Dynamically sets the width or height of one or more elements based on content dimensions.
     * @param {string[]|string} ids - An array of element IDs or a comma-separated string of IDs.
     * @return {void} - No value is returned, but the dimensions of the given elements may be updated.
     */
    LIB.setup_dimensions = function(ids) {
        if (typeof(ids) === 'string') {
            ids = ids.split(',').map((id) => id.trim());
        }
        ids.forEach(function(id){
            const elm = document.getElementById(id);
            if (elm) {
                let dimension = '';
                if (elm.tagName == 'INPUT') {
                    if (elm.type == 'text') {
                        dimension = 'width';
                    }
                } else if (elm.tagName == 'TEXTAREA') {
                    dimension = 'height';
                }
                if (dimension) {
                    LIB.add_event_listener(elm, 'input', function(evt) {
                        LIB.set_dimension(evt.target, dimension);
                    });
                    LIB.set_dimension(elm, dimension);
                }
            }
        });
    };

    return LIB;
});

