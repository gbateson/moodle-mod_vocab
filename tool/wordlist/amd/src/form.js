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

    JS.SELECTALL_SELECTOR = "input[type=checkbox][name^='wordlist[0]']";
    JS.CHECKBOX_SELECTOR = "input[type=checkbox][name^='wordlist']:not([name='wordlist[0]'])";
    JS.ACTION_SELECTOR = "select[name='wordlist[wordlistaction]']";
    JS.BUTTON_SELECTOR = "input[type=submit][name='wordlist[wordlistbutton]']";

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
        JS.setup_selectall();
        JS.setup_checkboxes();
        JS.setup_wordlistaction();
    };

    /*
     * Setup the selectall checkbox
     */
    JS.setup_selectall = function() {
        // Activate "Select all/none" checkbox for the word list.
        const selectall = document.querySelector(JS.SELECTALL_SELECTOR);
        if (selectall) {
            JS.set_selectall_wordtext(selectall);
            JS.add_event_listener(selectall, 'click', JS.onclick_selectall);
        }
    };

    /*
     * Setup the selectall checkbox
     */
    JS.onclick_selectall = function() {
        const selectall = this;
        JS.set_selectall_wordtext(selectall);
        document.querySelectorAll(JS.CHECKBOX_SELECTOR).forEach(function(cb){
            cb.checked = selectall.checked;
        });
        JS.set_wordlistaction();
    };

    /*
     * Setup the text for the selectall checkbox.
     */
    JS.set_selectall_wordtext = function(selectall) {
        const checked = selectall.checked;
        const dataset = selectall.dataset;
        const text = (checked ? dataset.deselectall : dataset.selectall);
        const wordtext = selectall.closest("label").querySelector(".wordtext");
        if (wordtext) {
            wordtext.innerHTML = text;
        }
    };

    /*
     * Setup the single checkbox.
     */
    JS.setup_checkboxes = function() {
        document.querySelectorAll(JS.CHECKBOX_SELECTOR).forEach(function(cb){
            JS.add_event_listener(cb, 'click', JS.set_wordlistaction);
        });
    };

    /*
     * Setup the single checkbox.
     */
    JS.setup_wordlistaction = function() {
        const wordlistaction = document.querySelector(JS.ACTION_SELECTOR);
        if (wordlistaction) {
            JS.add_event_listener(wordlistaction, 'change', JS.set_wordlistbutton);
            JS.set_wordlistaction();
        }
    };

    /*
     * Disable or enable the "with selected" menu.
     */
    JS.set_wordlistaction = function() {
        let count = 0;
        document.querySelectorAll(JS.CHECKBOX_SELECTOR).forEach(function(cb){
            if (cb.checked) {
                count++;
            }
        });
        const wordlistaction = document.querySelector(JS.ACTION_SELECTOR);
        if (wordlistaction) {
            if (count) {
                wordlistaction.removeAttribute("disabled");
            } else {
                wordlistaction.setAttribute("disabled", "disabled");
            }
            JS.set_wordlistbutton();
        }
    };

    /*
     * Disable or enable the "with selected" menu.
     */
    JS.set_wordlistbutton = function() {
        const wordlistaction = document.querySelector(JS.ACTION_SELECTOR);
        const wordlistbutton = document.querySelector(JS.BUTTON_SELECTOR);
        if (wordlistaction && wordlistbutton) {
            if (wordlistaction.getAttribute("disabled") || wordlistaction.selectedIndex < 1) {
                wordlistbutton.setAttribute("disabled", "disabled");
            } else {
                wordlistbutton.removeAttribute("disabled");
            }
        }
    };



    return JS;
});