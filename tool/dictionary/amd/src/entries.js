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
 * Show the dictionary entries of the words in a Vocabulary activity (see templates/entries.mustache):
 * a word's entry is fetched when it is first shown, and shown again after one of its items has
 * been approved, rejected or restored.
 *
 * Without JavaScript, the link of each word opens the page with that word's entry shown.
 *
 * @module     vocabtool_dictionary/entries
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';

const SELECTORS = {
    WORD: '[data-wordid]',
    TOGGLE: '[data-action="toggle"]',
    ENTRY: '[data-region="entry"]',
    LEMMA: '[data-lemmaid]',
};

/**
 * Show the entries of some words in their panels.
 *
 * @param {HTMLElement} region
 * @param {Object[]} entries from vocabtool_dictionary_get_entries.
 * @returns {Promise}
 */
const renderEntries = (region, entries) => Promise.all(entries.map(async(entry) => {
    const word = region.querySelector(`${SELECTORS.WORD}[data-wordid="${entry.wordid}"]`);
    if (!word) {
        return;
    }
    const {html, js} = await Templates.renderForPromise('vocabtool_dictionary/entry', entry);
    Templates.replaceNodeContents(word.querySelector(SELECTORS.ENTRY), html, js);
}));

/**
 * Fetch the entries of some words, and show them.
 *
 * @param {HTMLElement} region
 * @param {Number[]} wordids
 * @returns {Promise}
 */
const loadEntries = async(region, wordids) => {
    if (!wordids.length) {
        return;
    }
    const args = {vocabid: region.dataset.vocabid, wordids: wordids};
    const result = await fetchMany([{methodname: 'vocabtool_dictionary_get_entries', args}])[0];
    await renderEntries(region, result.entries);
};

/**
 * Show or hide the entry of a word, fetching it first if it has not been fetched.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} word
 * @param {Boolean} show
 * @returns {Promise}
 */
const setExpanded = async(region, word, show) => {
    const entry = word.querySelector(SELECTORS.ENTRY);
    if (show && !entry.children.length) {
        await loadEntries(region, [Number(word.dataset.wordid)]);
    }
    entry.hidden = !show;
    word.querySelector(SELECTORS.TOGGLE).setAttribute('aria-expanded', show ? 'true' : 'false');
};

/**
 * Change the status of an item, then show again the entries of every shown word with the same lemma.
 * Both requests are sent together.
 *
 * @param {HTMLElement} region
 * @param {HTMLElement} button
 * @returns {Promise}
 */
const setStatus = async(region, button) => {
    const body = button.closest(SELECTORS.LEMMA);
    const lemmaid = body ? body.dataset.lemmaid : '';
    const wordids = [...region.querySelectorAll(SELECTORS.WORD)]
        .filter((word) => word.querySelector(`${SELECTORS.ENTRY} [data-lemmaid="${lemmaid}"]`))
        .map((word) => Number(word.dataset.wordid));
    const vocabid = region.dataset.vocabid;
    button.disabled = true;
    const requests = fetchMany([
        {
            methodname: 'vocabtool_dictionary_set_status',
            args: {
                vocabid: vocabid,
                itemtype: button.dataset.itemtype,
                itemid: button.dataset.itemid,
                status: button.dataset.status,
            },
        },
        {methodname: 'vocabtool_dictionary_get_entries', args: {vocabid, wordids}},
    ]);
    try {
        const [, result] = await Promise.all(requests);
        await renderEntries(region, result.entries);
    } finally {
        button.disabled = false; // In case the entry was not shown again.
    }
};

/**
 * Set up the list of entries.
 *
 * @param {String} regionid
 */
export const init = (regionid) => {
    const region = document.getElementById(regionid);
    if (!region) {
        return;
    }
    region.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (!target || !region.contains(target)) {
            return;
        }
        const words = [...region.querySelectorAll(SELECTORS.WORD)];
        let promise = null;
        switch (target.dataset.action) {
            case 'toggle': {
                e.preventDefault();
                const word = target.closest(SELECTORS.WORD);
                promise = setExpanded(region, word, target.getAttribute('aria-expanded') !== 'true');
                break;
            }
            case 'showall': {
                // Fetch all the missing entries in one request, then show them all.
                const missing = words.filter((word) => !word.querySelector(SELECTORS.ENTRY).children.length);
                promise = loadEntries(region, missing.map((word) => Number(word.dataset.wordid)))
                    .then(() => Promise.all(words.map((word) => setExpanded(region, word, true))));
                break;
            }
            case 'hideall':
                promise = Promise.all(words.map((word) => setExpanded(region, word, false)));
                break;
            case 'setstatus':
                promise = setStatus(region, target);
                break;
        }
        if (promise) {
            promise.catch(Notification.exception);
        }
    });
};
