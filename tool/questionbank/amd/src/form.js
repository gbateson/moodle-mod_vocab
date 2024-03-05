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
 * mod/vocab/tool/questionbank/amd/src/form.js
 *
 * @module     vocabtool_questionbank
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define([], function(){

    let JS = {};

    /*
     * Add/Attach event listener (cross-browser version).
     */
    JS.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent('on' + evt, fn);
        }
    };

    /*
     * Initialize this AMD module;
     */
    JS.init = function() {
        this.init_selectall_logs();
        this.init_textareas_logs();
        this.init_selectall_words();
        this.init_checkboxes_words();
    };

    JS.init_selectall_logs = function(){
        const s = 'input[type="checkbox"][name="logids[selectall]"]';
        const selectall = document.querySelector(s);
        if (selectall) {
            JS.add_event_listener(selectall, 'click', JS.onclick_selectall);
            if (selectall.classList.contains("d-none")) {
                selectall.classList.remove("d-none");
            }
        }
    };

    JS.init_textareas_logs = function(){
        const s = '#id_log_error, #id_log_prompt, #id_log_results';
        document.querySelectorAll(s).forEach(function(textarea){
            // Add event listener that adjusts height to accommodate content.
            JS.add_event_listener(textarea, 'input', function(){
                this.style.height = 'auto'; // '1px' also works
                this.style.height = (this.scrollHeight + 6) + 'px';
            });
            textarea.dispatchEvent(new Event('input'));
        });
    };

    JS.init_selectall_words = function(){
        const s = 'input[type="checkbox"][name="selectedwords[selectall]"]';
        const selectall = document.querySelector(s);
        if (selectall) {

            JS.add_event_listener(selectall, 'click', JS.onclick_selectall);

            const label = selectall.closest('label');
            if (label) {
                if (label.classList.contains('d-none')) {
                    label.classList.remove('d-none');
                }
                label.classList.add('btn', 'btn-light', 'border-dark');
                label.classList.add('align-self-start', 'px-2', 'py-0');

                const p = label.closest('.fcontainer').querySelector('.col-form-label p');
                if (p) {
                    p.replaceWith(label);
                }
            }
        }
    };

    JS.onclick_selectall = function(){
        const checked = this.checked;

        // Check/uncheck all other checkboxes in this fieldset
        // that have the same name prefix, e.g. "selectedwords".
        const nameprefix = this.name.substr(0, this.name.indexOf('['));
        const s = 'input[type="checkbox"][name^="' + nameprefix + '"]';
        this.closest('fieldset').querySelectorAll(s).forEach(function(cb){
            cb.checked = checked;
        });

        // Set new text for this checkbox.
        let txt = '';
        if (checked) {
            txt = this.dataset.deselectall || '';
        } else {
            txt = this.dataset.selectall || '';
        }
        if (txt) {
            // Locate the label for this checkbox.
            const label = this.closest('label');
            if (label) {
                // Remove existing text nodes in this label.
                for (let i = (label.childNodes.length - 1); i >= 0 ; i--) {
                    const n = label.childNodes[i];
                    if (n.nodeType == 3) {
                        label.removeChild(n);
                    }
                }
                // Add new text in a node at the end of the label.
                label.appendChild(document.createTextNode(txt));
            }
        }
        return true;
    };

    JS.init_checkboxes_words = function(){
        const s = 'input[type="checkbox"][name^="selectedwords"]';
        document.querySelectorAll(s).forEach(function(cb){
            JS.add_event_listener(cb, 'click', JS.onclick_checkbox);
        });
    };

    JS.onclick_checkbox = function(evt){

        const felement = this.closest('.felement');
        if (felement === null) {
            return true;
        }

        const checkboxes = 'input[type="checkbox"]';
        const dataclicked = checkboxes + '[data-clicked="1"]';

        const cb_start = felement.querySelector(dataclicked);
        const cb_stop = evt.currentTarget;

        let unclickall = false;
        let clickme = true;

        if (evt.shiftKey) {
            if (cb_start) {
                let found = false;
                let startstop = false;
                felement.querySelectorAll(checkboxes).forEach(function(cb){
                    startstop = (cb == cb_start || cb == cb_stop);
                    if (found || startstop) {
                        cb.checked = evt.currentTarget.checked;
                        if (startstop) {
                            found = (found ? false : true);
                        }
                    }

                });
                unclickall = true;
            } else {
                clickme = true;
            }
        } else {
            unclickall = true;
            clickme = true;
        }

        if (unclickall) {
            felement.querySelectorAll(dataclicked).forEach(function(cb){
                cb.removeAttribute('data-clicked');
            });
        }

        if (clickme) {
            evt.currentTarget.setAttribute('data-clicked', '1');
        }
    };

    return JS;
});