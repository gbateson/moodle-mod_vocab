// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * mod/vocab/tool/questionbank/amd/src/form.js
 *
 * @module vocabtool_questionbank
 * @copyright 2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.11
 */

define(['core/str'], function(STR){

    let JS = {};
    window.JS = JS;

    /*
     * Initialize the str object to hold language strings.
     */
    JS.str = {};

    /**
     * Adds a cross-browser event listener to the specified element.
     *
     * @param {Element} obj The DOM element to attach the event to.
     * @param {string} evt The event type (e.g., 'click', 'input').
     * @param {Function} fn The callback function to execute when the event fires.
     * @param {boolean} [useCapture=false] Whether to use capture phase (optional).
     */
    JS.add_event_listener = function(obj, evt, fn, useCapture) {
        if (obj.addEventListener) {
            obj.addEventListener(evt, fn, (useCapture || false));
        } else if (obj.attachEvent) {
            obj.attachEvent('on' + evt, fn);
        }
    };

    /**
     * Initializes all custom JavaScript enhancements on page load.
     *
     * Runs setup functions for log selection checkboxes,
     * dynamic textareas, word selection, and custom name helpers.
     *
     * @param {object} defaults Default values for prompts.
     */
    JS.init = function(defaults) {

        // These functions do not need strings from Moodle.
        this.init_selectall_logs();
        this.init_textareas_logs();
        this.init_selectall_words();
        this.init_checkboxes_words();

        if (defaults) {
            JS.init_prompt(defaults);
        }

        STR.get_strings([
            {"key": "addname", "component": "vocabtool_questionbank"},
            {"key": "addtags", "component": "vocabtool_questionbank"},
        ]).done(function(s) {
            let i = 0;
            JS.str.addname = s[i++];
            JS.str.addtags = s[i++];
            JS.init_custom_names();
        });
    };

    /**
     * Initializes the "select all logs" checkbox and makes it visible if hidden.
     *
     * Attaches a click handler to toggle all checkboxes within the log fieldset.
     */
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

    /**
     * Automatically resizes textarea elements for log fields as the user types.
     *
     * Targets fields such as error, prompt, and results textareas in the log editing form.
     */
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

    /**
     * Initializes the "select all words" checkbox and repositions its label.
     *
     * Attaches a click handler and restyles the label to enhance appearance and usability.
     */
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

    /**
     * Handles toggling of all checkboxes in a group when "select all" is clicked.
     *
     * Updates the state of checkboxes with the same name prefix and adjusts the label text
     * based on data attributes (`data-selectall` and `data-deselectall`).
     *
     * @returns {boolean} Always returns true.
     */
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

    /**
     * Attaches click event listeners to individual word selection checkboxes.
     *
     * Enables shift-click selection and click tracking for batch operations.
     */
    JS.init_checkboxes_words = function(){
        const s = 'input[type="checkbox"][name^="selectedwords"]';
        document.querySelectorAll(s).forEach(function(cb){
            JS.add_event_listener(cb, 'click', JS.onclick_checkbox);
        });
    };

    /**
     * Handles checkbox click events for word selection, including shift-click support.
     *
     * Supports selecting a range of checkboxes when holding Shift, and tracks the
     * last clicked checkbox using a `data-clicked` attribute.
     *
     * @param {MouseEvent} evt The click event object.
     */
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

    /**
     * Sets up custom name buttons for both subcategory and question tag inputs.
     *
     * Calls init_custom_name() with appropriate selectors to initialize UI enhancements
     * for subcategory and tag entry fields.
     */
    JS.init_custom_names = function(){
        JS.init_custom_name("[name='subcat[name]']", ".subcatnames", "addname");
        JS.init_custom_name("[name='qtag[name]']", ".tagnames", "addtags");
    };

    /**
     * Enhances a custom name input by displaying a list of previously used names with a button.
     *
     * This function searches the log table for matching name entries, builds a summary string,
     * and injects a button + label near the specified input field. Clicking the button inserts
     * the names into the input and ensures the associated checkbox is checked.
     *
     * @param {string} sourceselector CSS selector for the input[type="text"] element to target.
     * @param {string} targetselector CSS selector for the <ul> elements containing <li> items with previous names.
     * @param {string} strname the name of the STR item to use as button text.
     */
    JS.init_custom_name = function(sourceselector, targetselector, strname){
        let elm = document.querySelector(sourceselector);
        let fitem = elm.closest(".fitem");
        let table = document.querySelector("#questionbanklog_table");
        let allnames = []; // Cache of customnames.
        table.querySelectorAll(targetselector).forEach(function(ul){
            let names = [];
            ul.querySelectorAll("li").forEach(function(li){
                names.push(li.innerText);
            });
            if (names.length) {
                names = names.join(", ");
                if (allnames.indexOf(names) < 0) {
                    allnames.push(names);

                    let separator = Object.assign(document.createElement("span"), {
                        "className": "w-100",
                    });
                    let div = Object.assign(document.createElement("div"), {
                        "className": "rounded border border-warning bg-light ml-4 my-1 pr-2 customnames",
                    });
                    div.appendChild(
                        Object.assign(document.createElement("button"), {
                            "className": "btn btn-warning ml-0 py-1 px-2",
                            "onclick": JS.onclick_add_tags,
                            "textContent": JS.str[strname],
                        })
                    );
                    div.appendChild(
                        Object.assign(document.createElement("span"), {
                            "className": "ml-2",
                            "textContent": names,
                        })
                    );
                    fitem.parentNode.insertBefore(separator, fitem.nextSibling);
                    separator.parentNode.insertBefore(div, separator.nextSibling);
                }
            }
        });
    };

    /**
     * Handles click event on the "Add tags" button.
     *
     * Transfers a list of names from the adjacent <span> into the associated text input field
     * in the same .fitem block. Also locates the previous .fitem containing a checkbox
     * and ensures it is checked. Finally, the button is blurred to remove focus.
     *
     * @param {MouseEvent} evt The click event object.
     */
    JS.onclick_add_tags = function(evt) {
        evt.preventDefault();

        let btn = evt.currentTarget;
        let div = btn.closest(".customnames");
        let span = btn.nextElementSibling;
        if (div && span) {

            let inputfitem = JS.get_previous_sibling(div, ".fitem");
            if (inputfitem) {

                let input = inputfitem.querySelector("input[type='text']");
                if (input) {

                    // Transfer previously used names to the input element.
                    input.value = span.textContent.trim();

                    // Switch off the disabled flag.
                    if (input.disabled) {
                        input.disabled = false;
                    }

                    // Locate previous inputfitem sibling.
                    let checkboxfitem = JS.get_previous_sibling(inputfitem, ".fitem");
                    if (checkboxfitem) {
                        let checkbox = checkboxfitem.querySelector("input[type='checkbox']");
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    }
                }
            }
        }

        btn.blur();
        return false;
    };

    /**
     * Retrieves the previous sibling element that matches the specified selector.
     *
     * @param {Element} elm - The element to start searching from.
     * @param {string} selector - The CSS selector to match the sibling against.
     * @returns {Element|null} The matching previous sibling, or null if none is found.
     */
    JS.get_previous_sibling = function(elm, selector){
        if (! selector) {
            selector = "";
        }
        let sibling = elm.previousElementSibling;
        while (sibling) {
            if (selector == "" || sibling.matches(selector)) {
                return sibling;
            }
            sibling = sibling.previousElementSibling;
        }
        return null;
    };

    /**
     * Initializes the prompt dropdown menu with default settings and event listeners.
     *
     * @param {object} defaults - An object containing default settings for the prompt.
     */
    JS.init_prompt = function(defaults){
        const p = document.querySelector("select[name='prompt']");
        if (p) {

            // Collect names of select elements in this section of the form.
            let selectnames = [];
            const s = "select:not([name='prompt'])";
            p.closest(".fcontainer").querySelectorAll(s).forEach(function(select){
                selectnames.push(select.name);
            });
            p.dataset.selectnames = selectnames.join(",");

            // Set up onchange event handler.
            JS.add_event_listener(p, 'change', function(evt){
                const elm = evt.target;
                let selectnames = elm.dataset.selectnames.split(",");
                const promptid = elm.options[elm.selectedIndex].value;
                if (promptid && defaults[promptid]) {
                    let settings = defaults[promptid];
                    for (let n in settings) {
                        if (n == "qtypes") {
                            JS.set_qtypes(settings[n]);
                        } else if (n == "subcattypes") {
                            JS.set_checkboxes("subcat", settings[n]);
                        } else if (n == "subcatname") {
                            JS.set_customname("subcat", settings[n]);
                        } else if (n == "tagtypes") {
                            JS.set_checkboxes("qtag", settings[n]);
                        } else if (n == "tagnames") {
                            JS.set_customname("qtag", settings[n]);
                        } else {
                            // Locate the target form element.
                            const elm = document.querySelector("[name='" + n + "']");
                            if (elm) {

                                // The target is a <select> element.
                                if (elm.tagName == "SELECT") {

                                    // Remove the element's name from the selectnames array.
                                    const i = selectnames.indexOf(n);
                                    if (i >= 0) {
                                        selectnames.splice(i, 1);
                                    }

                                    // Locate the option with the matching value.
                                    const s = "option[value='" + settings[n] + "']";
                                    const option = elm.querySelector(s);

                                    // If the option exists and is not already selected, select it.
                                    if (option && option.selected == false) {
                                        option.selected = true;
                                    }

                                // The target is a text input element.
                                } else if (elm.tagName == "INPUT" && elm.type == "text") {
                                    elm.value = settings[n];
                                }
                            }
                        }
                    }

                    // Unset any select elements that were not set above.
                    selectnames.forEach(function(name){
                        const s = "select[name='" + name + "']";
                        const select = document.querySelector(s);
                        if (select) {
                            select.options[0].selected = true;
                        }
                    });
                }
            });
            p.dispatchEvent(new Event("change"));
        }
    };

    /**
     * Sets the state of question type checkboxes based on the provided configuration.
     *
     * @param {object} qtypes - An object mapping question type names to format IDs.
     */
    JS.set_qtypes = function(qtypes){
        const s = "#id_questiontypescontainer .felement";
        document.querySelectorAll(s).forEach(function(felement){
            const enable = felement.querySelector(
                "input[type='checkbox'][name$='[enable]']"
            );
            const format = felement.querySelector(
                "select[name$='[format]']"
            );
            if (enable && format) {
                const i = enable.name.indexOf("[");
                const t = enable.name.substr(0, i);
                if (qtypes[t]) {
                    // The qtype "n" is required.
                    if (enable.checked == false) {
                        enable.checked = true;
                        enable.dispatchEvent(new Event("click"));
                    }
                    const formatid = qtypes[t];
                    const option = format.querySelector("option[value='" + formatid + "']");
                    if (option && option.selected == false) {
                        option.selected = true;
                    }
                } else {
                    // The qtype "n" is NOT required.
                    if (enable.checked == true) {
                        enable.checked = false;
                        enable.dispatchEvent(new Event("click"));
                        format.options[0].selected = true;
                    }
                }
            }
        });
    };

    /**
     * Sets the checked state of checkboxes matching the specified name prefix.
     *
     * @param {string} name - The name prefix to match checkboxes against.
     * @param {number} value - The bitwise value to use for determining the checked state.
     */
    JS.set_checkboxes = function(name, value){
        const r = new RegExp("^" + name + "\\[(\\d+)\\]$");
        const s = "input[type='checkbox'][name^='" + name + "['][name$=']']";
        document.querySelectorAll(s).forEach(function(cb){
            const m = cb.name.match(r);
            if (m && m[0]) {
                const i = (value & parseInt(m[1])); // eslint-disable-line no-bitwise
                cb.checked = (i ? true : false);
            }
        });
    };


    /**
     * Sets the value of a custom name input and checks the corresponding checkbox.
     *
     * @param {string} name - The name prefix for the input and checkbox elements.
     * @param {string} value - The value to set in the input field.
     * @returns {boolean} True if the custom name was successfully set, false otherwise.
     */
    JS.set_customname = function(name, value){
        const s = {
            "checkbox": "input[type='checkbox'][name^='" + name + "']",
            "input": "input[type='text'][name='" + name + "[name]']",
            "fitem": ".fitem",
        };
        const input = document.querySelector(s.input);
        if (input === null) {
            return false;
        }

        const fitem = JS.get_previous_sibling(input.closest(s.fitem), s.fitem);
        if (fitem === null) {
            return false;
        }

        const cb = fitem.querySelector(s.checkbox);
        if (cb === null) {
            return false;
        }

        if (cb.checked == false) {
            cb.checked = true;
            cb.dispatchEvent(new Event("click"));
        }
        input.value = value;
    };

    return JS;
});