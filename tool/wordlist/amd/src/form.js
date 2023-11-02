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
 * mod/vocab/tool/wordlist/amd/src/form.js
 *
 * @module     block_ungraded_activities
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define([], function(){

    var JS = {};

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

        // Expand the textarea to accommodate any newly entered text.
        const textarea = document.getElementById('id_addwordselements_addwords');
        if (textarea) {
            JS.add_event_listener(textarea, 'input', function(){
                this.style.height = 'auto'; // '1px' also works
                this.style.height = (this.scrollHeight + 6) + 'px';
            });
            if (textarea.scrollHeight) {
                textarea.dispatchEvent(new Event('input'));
            } else {
                // Element is hidden, so trigger this event
                // handler when the element becomes visible.
                JS.trigger_on_toggle(textarea, 500, 'input');
            }
        }

        // Position the button at the bottom of the textarea
        const btn = document.getElementById('id_addwordselements_addwordsbutton');
        if (btn) {
            btn.closest(".fitem").style.setProperty('align-self', 'end');
        }

        // Expand the text box to accommodate any newly entered text.
        const input = document.getElementById('id_exportfileelements_exportfile');
        if (input) {
            JS.add_event_listener(input, 'input', function(){
                this.style.width = 'auto'; // '1px' also works
                this.style.width = (this.scrollWidth + 6) + 'px';
            });
            if (input.scrollWidth) {
                input.dispatchEvent(new Event('input'));
            } else {
                // Element is hidden, so trigger this event
                // handler when the element becomes visible.
                JS.trigger_on_toggle(input, 500, 'input');
            }
        }
    };

    /**
     * trigger_on_toggle
     *
     * @param {object} elm
     * @param {integer} delay
     * @param {string} eventType
     */
    JS.trigger_on_toggle = function(elm, delay, eventType) {
        const fieldset = elm.closest("fieldset.collapsible.collapsed");
        if (fieldset) {
            const toggler = fieldset.querySelector('.ftoggler');
            if (toggler) {
                JS.add_event_listener(toggler, 'click', function(){
                    setTimeout(function(){
                        elm.dispatchEvent(new Event(eventType));
                    }, delay);
                });
            }
        }

    };

    return JS;
});