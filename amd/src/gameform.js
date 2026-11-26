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
 * The settings form of a game (\mod_vocab\gameform), in a popup or on a page:
 * when the stage is changed, a title that is still a suggested title, e.g.
 * "Quiz (Recognize)", is changed to the suggested title for the new stage.
 * A title that the teacher has typed is not changed.
 *
 * @module     mod_vocab/gameform
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let initialised = false;

/**
 * Listen for changes of stage in any game settings form on the page.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('change', (e) => {
        const select = e.target.closest('form[data-vocab-suggested-titles] select[name="stage"]');
        if (!select) {
            return;
        }
        const form = select.form;
        const title = form.querySelector('input[name="title"]');
        let titles = {};
        try {
            titles = JSON.parse(form.dataset.vocabSuggestedTitles);
        } catch (error) {
            return;
        }
        const value = title.value.trim();
        if ((value === '' || Object.values(titles).includes(value)) && titles[select.value]) {
            title.value = titles[select.value];
        }
    });
};
