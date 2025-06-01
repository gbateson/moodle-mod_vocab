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
     */
    JS.init = function() {

        // These functions do not need strings from Moodle.
        JS.init_selectall_logs();
        JS.init_select_subset_logs();
        JS.init_select_subset_defaults();
        JS.init_textareas_logs();
        JS.init_selectall_words();
        JS.init_checkboxes_words();
        JS.init_prompt();

        STR.get_strings([
            {"key": "default", "component": "moodle"},
            {"key": "addname", "component": "vocabtool_questionbank"},
            {"key": "addtags", "component": "vocabtool_questionbank"},
        ]).done(function(s) {
            let i = 0;
            JS.str.default = s[i++];
            JS.str.addname = s[i++];
            JS.str.addtags = s[i++];
            JS.init_bulk_edit();
            JS.init_custom_names();
        });
    };

    /**
     * Initializes the  checkbox to select all logs and makes it visible if hidden.
     *
     * Attaches a click handler to toggle all checkboxes within the log fieldset.
     */
    JS.init_selectall_logs = function(){
        const s = 'input[type="checkbox"][name="logids[0]"]';
        const selectall = document.querySelector(s);
        if (selectall) {
            JS.add_event_listener(selectall, 'click', JS.onclick_selectall);
            if (selectall.classList.contains("d-none")) {
                selectall.classList.remove("d-none");
            }
        }
    };

    /**
     * Enables user to select/deselect a subset of log records.
     */
    JS.init_select_subset_logs = function(){
        JS.init_select_subset_table("logids[", "]");
    };

    /**
     * Enables user to select/deselect a subset of default values.
     */
    JS.init_select_subset_defaults = function(){
        JS.init_select_subset_table("defaultfields[", "]");
    };

    /**
     * Enables user to select/deselect a subset of checkboxes for log records.
     *
     * @param {string} prefix for checkbox elements.
     * @param {string} suffix for checkbox elements.
     */
    JS.init_select_subset_table = function(prefix, suffix){
        const table = document.getElementById("questionbanklog_table");
        if (table) {
            let s = 'input[type="checkbox"]';
            if (prefix) {
                s += '[name^="' + prefix + '"]';
            }
            if (suffix) {
                s += '[name$="' + suffix + '"]';
            }
            JS.init_select_subset(table, s);
        }
    };

    /**
     * Enables the user to select or deselect a contiguous subset of checkboxes
     * by clicking one checkbox and then Shift-clicking another. All checkboxes
     * in between will adopt the checked state of the second (Shift-clicked) checkbox.
     *
     * @param {object} elm DOM element containing the checkboxes.
     * @param {string} s Selector string to extract checkboxes.
     */
    JS.init_select_subset = function(elm, s){
        let lastClickedIndex = null;
        const checkboxes = Array.from(elm.querySelectorAll(s));
        checkboxes.forEach((checkbox, index) => {
            checkbox.addEventListener("click", function (event) {
                if (event.shiftKey && lastClickedIndex !== null) {
                    const currentIndex = index;
                    const mini = Math.min(lastClickedIndex, currentIndex);
                    const maxi = Math.max(lastClickedIndex, currentIndex);
                    const newState = this.checked;
                    for (let i = mini; i <= maxi; i++) {
                        checkboxes[i].checked = newState;
                    }
                }
                lastClickedIndex = index;
            });
        });
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

    /*
     * Setup radio buttons and checkboxes to allow bulk editing of multiple logs.
     */
    JS.init_bulk_edit = function(){
        return false;
        const logrecords = document.querySelector("#id_logrecords");
        if (logrecords === null) {
            return false;
        }
        const h3 = logrecords.querySelector("h3");
        if (h3 === null) {
            return false;
        }
        const tbl = logrecords.querySelector("#questionbanklog_table");
        if (tbl === null) {
            return false;
        }
        const thead = tbl.querySelector("thead");
        const tbody = tbl.querySelector("tbody");
        if (thead === null || tbody === null) {
            return false;
        }

        const tr1 = thead.querySelector("tr");
        if (tr1 === null) {
            return false;
        }

        const tr2 = document.createElement("tr");
        //tr2.classList.add("d-none");

        let wordcol = -1;
        tr1.querySelectorAll("th").forEach(function(th1, col) {
            const th2 = th1.cloneNode(false);
            const fieldname = (th1.dataset.fieldname || "");
            if (fieldname == "" || fieldname == "wordid") {
                // Append non-breaking space.
                th2.appendChild(document.createTextNode('\xA0'));
                if (fieldname == "wordid") {
                    wordcol = col;
                }
            } else {
                th2.classList.add("text-center");
                const cb = Object.assign(document.createElement("input"), {
                    type: "checkbox",
                    name: "bulkselect[" + fieldname + "]",
                    value: "1",
                    className: "bulk-checkbox",
                });
                th2.appendChild(cb);
            }
            tr2.appendChild(th2);
        });

        thead.appendChild(tr2);

        if (wordcol >= 0) {
            // Insert an empty <th> after wordcol in each <tr> in <thead>
            thead.querySelectorAll("tr").forEach(function(tr, i) {
                const th = document.createElement("th");
                th.appendChild(document.createTextNode(i ? "" : JS.str.default));
                tr.insertBefore(th, tr.children[wordcol + 1] || null);
            });

            // Insert an empty <td> after wordcol in each <tr> in <tbody>
            tbody.querySelectorAll("tr").forEach(function(tr) {
                const logid = tr.querySelector('input[type="checkbox"]').value;
                const radio = Object.assign(document.createElement("input"), {
                    "type": "radio",
                    "value": logid,
                    "name": "defaultlogid",
                });
                const td = Object.assign(document.createElement("td"), {
                    "className": "text-center",
                });
                td.appendChild(radio);
                tr.insertBefore(td, tr.children[wordcol + 1] || null);
            });
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
        if (table) {
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
        }
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
     * Initializes the prompt dropdown menu with default settings and event listeners.
     */
    JS.init_prompt = function(){
        const p = document.querySelector("select[name='prompt']");
        if (p) {

            // Collect names of select elements in this section of the form.
            let selectnames = ["questionreview"];
            const s = "select:not([name='prompt'])";
            p.closest(".fcontainer").querySelectorAll(s).forEach(function(select){
                selectnames.push(select.name);
            });
            p.dataset.selectnames = selectnames.join(",");

            // Set up onchange event handler.
            JS.add_event_listener(p, 'change', function(evt){
                const elm = evt.target;

                let selectnames = elm.dataset.selectnames;
                if (selectnames) {
                    selectnames = selectnames.split(",");
                }

                let option = elm.options[elm.selectedIndex];
                let defaults = option.dataset.defaults;
                if (defaults) {
                    defaults = JSON.parse(defaults);
                }

                for (let n in defaults) {
                    let v = defaults[n];
                    if (n == "qtypes") {
                        JS.set_qtypes(v);
                    } else if (n == "subcattypes") {
                        JS.set_checkboxes("subcat", v);
                    } else if (n == "subcatname") {
                        JS.set_customname("subcat", v);
                    } else if (n == "tagtypes") {
                        JS.set_checkboxes("qtag", v);
                    } else if (n == "tagnames") {
                        JS.set_customname("qtag", v);
                    } else {
                        JS.set_promptfield(n, v, selectnames);
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
                // Determine whether or not this CB should be checked.
                const i = (value & parseInt(m[1])); // eslint-disable-line no-bitwise
                let checked = (i == 0 ? false : true);

                // For the "Custom name" checkbox, we ignore the default and set the
                // checked flag depending on whether or not the custom field has a value.
                const fitem = JS.get_next_sibling(cb.closest(".fitem"), ".fitem");
                if (fitem) {
                    const input = fitem.querySelector("input[type='text']");
                    if (input) {
                        checked = (input.value ? true : false);
                    }
                }

                if (cb.checked == checked) {
                    return; // No change required.
                }

                cb.checked = checked;
                cb.dispatchEvent(new Event("click"));
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

        // Don't override any existing name in the custom name field.
        if (input.value == "") {
            input.value = value;
            if (cb.checked == false) {
                cb.checked = true;
                cb.dispatchEvent(new Event("click"));
            }
        }
    };

    /**
     * Sets the value of a prompt field based on the provided name and value.
     *
     * This function locates the target form element by its name attribute,
     * sets the value for text inputs, and selects the appropriate option
     * for select elements. If the element is a select, its name is removed
     * from the selectnames array to avoid being processed later.
     *
     * @param {string} name - The name attribute of the target form element.
     * @param {string} value - The value to set for the target element.
     * @param {string[]} selectnames - An array of select element names that still need processing.
     */
    JS.set_promptfield = function(name, value, selectnames){
        // Locate the target form element.
        const elm = document.querySelector("[name='" + name + "']");
        if (elm) {

            // The target is a <select> element.
            if (elm.tagName == "SELECT") {

                // Remove the element's name from the selectnames array.
                const i = selectnames.indexOf(name);
                if (i >= 0) {
                    selectnames.splice(i, 1);
                }

                // Locate the option with the matching value.
                const s = "option[value='" + value + "']";
                const option = elm.querySelector(s);

                // If the option exists and is not already selected, select it.
                if (option && option.selected == false) {
                    option.selected = true;
                }

            // The target is a text input element.
            } else if (elm.tagName == "INPUT" && elm.type == "text") {
                elm.value = value;
            }
        }
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
     * Retrieves the next sibling element that matches the specified selector.
     *
     * @param {Element} elm - The element to start searching from.
     * @param {string} selector - The CSS selector to match the sibling against.
     * @returns {Element|null} The matching next sibling, or null if none is found.
     */
    JS.get_next_sibling = function(elm, selector){
        if (! selector) {
            selector = "";
        }
        let sibling = elm.nextElementSibling;
        while (sibling) {
            if (selector == "" || sibling.matches(selector)) {
                return sibling;
            }
            sibling = sibling.nextElementSibling;
        }
        return null;
    };

    return JS;
});