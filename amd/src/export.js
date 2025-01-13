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
 * mod/vocab/tool/wordlist/amd/src/form.EXPORT
 *
 * @module     vocabtool_wordlist
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

define(['mod_vocab/lib', 'core/str'], function(LIB, STR) {

    var EXPORT = {};

    /*
     * initialize the str object to hold language strings.
     */
    EXPORT.str = {};

    /*
     * initialize this AMD module
     */
    EXPORT.init = function() {
        LIB.setup_dimensions(
            'id_exportfileelements_exportfile'
        );
        STR.get_strings([
            {"key": "all", "component": "moodle"},
            {"key": "none", "component": "moodle"},
        ]).done(function(s) {
            var i = 0;
            EXPORT.str.all = s[i++];
            EXPORT.str.none = s[i++];
            EXPORT.setup_allnone();
        });
    };

    /*
     * Setup the All/None links
     */
    EXPORT.setup_allnone = function() {
        const fieldset = document.getElementById('id_export');
        if (fieldset === null) {
            return; // Shouldn't happen !!
        }
        const names = ['contentplugins', 'assistantplugins'];
        for (const i in names) {
            const label = fieldset.querySelector(
                '#fgroup_id_' + names[i] + '_label'
            );
            if (label === null) {
                continue; // Shouldn't happen !!
            }
            let div = Object.assign(
                document.createElement('div'),
                {class: 'toggleallnone'}
            );
            div.append(
                Object.assign(
                    document.createElement('small'), {
                        'textContent': EXPORT.str.all,
                        'className': 'selectall text-info',
                        'onclick': EXPORT.onclick_selectall
                    }
                ),
                ' / ',
                Object.assign(
                    document.createElement('small'), {
                        'textContent': EXPORT.str.none,
                        'className': 'selectnone text-info',
                        'onclick': EXPORT.onclick_selectnone
                    }
                )
            );
            label.appendChild(div);

            const selectall = div.querySelector('.selectall');
            if (selectall) {
                selectall.dispatchEvent(new Event('click'));
            }
        }
    };

    /*
     * onclick event handler for selectall
     */
    EXPORT.onclick_selectall = function(evt) {
        return EXPORT.onclick_selectallnone(evt.target, true);
    };

    /*
     * onclick event handler for selectnone
     */
    EXPORT.onclick_selectnone = function(evt) {
        return EXPORT.onclick_selectallnone(evt.target, false);
    };

    /*
     * onclick event handler for selectall/none
     */
    EXPORT.onclick_selectallnone = function(elm, checked) {
        const fgroup = elm.closest('.form-group.fitem');
        if (fgroup) {
            const s = 'input[type="checkbox"]';
            fgroup.querySelectorAll(s).forEach(function(cb){
                cb.checked = checked;
            });
        }
        return true;
    };

    return EXPORT;
});