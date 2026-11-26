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
 * Manage the games of a Vocabulary activity on its page (see templates/game_manager.mustache):
 * open a game's settings in a popup, reorder games by dragging them, remove a game by
 * dragging it to the trash or with its menu, and hide or show a game.
 *
 * Without JavaScript, the same actions are links to the pages in mod/vocab/game/.
 *
 * @module     mod_vocab/managegames
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {call as fetchMany} from 'core/ajax';
import Modal from 'core/modal';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import SortableList from 'core/sortable_list';
import {getString} from 'core/str';
import Templates from 'core/templates';

const SELECTORS = {
    ACTION: '[data-action]',
    LIST: '[data-region="vocab-game-tiles"]',
    TILE: '[data-instanceid]',
    TRASH: '[data-region="vocab-game-trash"]',
};

/**
 * Call a web service function of this plugin.
 *
 * @param {String} methodname
 * @param {Object} args
 * @returns {Promise}
 */
const callFunction = (methodname, args) => fetchMany([{methodname, args}])[0];

/**
 * Open a game's settings form in a popup, and reload the page when it is saved.
 *
 * @param {Object} args "id" to edit a game, or "cmid" and "game" to add one.
 * @param {String} titlekey the string for the popup's title: "addinggame" or "editinggame".
 * @param {String} gametitle the name of the game, e.g. "Quiz".
 * @param {HTMLElement} returnfocus
 */
const openForm = (args, titlekey, gametitle, returnfocus) => {
    const form = new ModalForm({
        formClass: 'mod_vocab\\gameform',
        args: args,
        modalConfig: {title: getString(titlekey, 'mod_vocab', gametitle)},
        returnFocus: returnfocus,
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => window.location.reload());
    form.show();
};

/**
 * Show the games that can be added, as tiles in a popup. Choosing a game opens
 * the settings form for a new game of that type.
 *
 * @param {String} cmid
 * @param {Object[]} games for templates/game_chooser.mustache.
 * @param {HTMLElement} returnfocus
 */
const openChooser = async(cmid, games, returnfocus) => {
    const modal = await Modal.create({
        title: getString('addgame', 'mod_vocab'),
        body: Templates.render('mod_vocab/game_chooser', {games}),
        large: true,
        removeOnClose: true,
        returnElement: returnfocus,
    });
    modal.getRoot()[0].addEventListener('click', (e) => {
        const choice = e.target.closest('[data-action="choosegame"]');
        if (!choice) {
            return;
        }
        e.preventDefault();
        modal.destroy();
        openForm({cmid: cmid, game: choice.dataset.game}, 'addinggame', choice.dataset.gametitle, returnfocus);
    });
    modal.show();
};

/**
 * Hide a game from students, or show it again, then reload the page.
 *
 * @param {HTMLElement} tile
 * @param {Boolean} enabled
 */
const setEnabled = (tile, enabled) => {
    callFunction('mod_vocab_set_game_instance_enabled', {id: tile.dataset.instanceid, enabled: enabled})
        .then(() => window.location.reload())
        .catch(Notification.exception);
};

/**
 * Ask whether to remove a game, then remove it and reload the page. A game that has
 * been played is hidden, not deleted, and the question says so.
 *
 * @param {HTMLElement} tile
 */
const confirmDelete = async(tile) => {
    const title = tile.dataset.title;
    const count = parseInt(tile.dataset.attempts) || 0;
    const question = count
        ? getString('confirmhidegame', 'mod_vocab', {title, count})
        : getString('confirmdeletegame', 'mod_vocab', title);
    try {
        await Notification.deleteCancelPromise(
            getString('deletegame', 'mod_vocab'),
            question,
            getString('delete', 'core')
        );
    } catch (e) {
        return; // Cancelled.
    }
    try {
        await callFunction('mod_vocab_delete_game_instance', {id: tile.dataset.instanceid});
        window.location.reload();
    } catch (e) {
        Notification.exception(e);
    }
};

/**
 * Is a point, e.g. where a tile was dropped, over an element?
 *
 * @param {HTMLElement} element
 * @param {Number} pagex
 * @param {Number} pagey
 * @returns {Boolean}
 */
const isOver = (element, pagex, pagey) => {
    if (!element || pagex === undefined || pagey === undefined) {
        return false;
    }
    const rect = element.getBoundingClientRect();
    const x = pagex - window.scrollX;
    const y = pagey - window.scrollY;
    return (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom);
};

/**
 * Set up the game tiles.
 *
 * @param {String} regionid the id of the element that contains the tiles.
 * @param {Object[]} games the games that can be added, for templates/game_chooser.mustache.
 */
export const init = (regionid, games) => {
    const region = document.getElementById(regionid);
    if (!region) {
        return;
    }
    const vocabid = region.dataset.vocabid;
    const cmid = region.dataset.cmid;
    const list = region.querySelector(SELECTORS.LIST);
    const trash = region.querySelector(SELECTORS.TRASH);
    trash.classList.remove('d-none');

    region.addEventListener('click', (e) => {
        const element = e.target.closest(SELECTORS.ACTION);
        if (!element || !region.contains(element)) {
            return;
        }
        const tile = element.closest(SELECTORS.TILE);
        switch (element.dataset.action) {
            case 'add':
                e.preventDefault();
                openChooser(cmid, games, element);
                break;
            case 'edit':
                e.preventDefault();
                openForm({id: tile.dataset.instanceid}, 'editinggame', tile.dataset.gametitle, element);
                break;
            case 'hide':
            case 'show':
                e.preventDefault();
                setEnabled(tile, element.dataset.action === 'show');
                break;
            case 'delete':
                e.preventDefault();
                confirmDelete(tile);
                break;
        }
    });

    // Tiles are dragged by their handles, which can also be used with the keyboard.
    const sortable = new SortableList(list, {moveHandlerSelector: '[data-drag-type="move"]'});
    sortable.getElementName = (element) => $.Deferred().resolve(element.attr('data-title'));

    $(list).on(SortableList.EVENTS.DRAGSTART, () => region.classList.add('vocab-games-dragging'));
    $(list).on(SortableList.EVENTS.DRAGEND, () => region.classList.remove('vocab-games-dragging'));
    $(list).on(SortableList.EVENTS.DROP, (e, info) => {
        const tile = info.element[0];
        if (isOver(trash, info.endX, info.endY)) {
            // Put the tile back where it was, then ask whether to remove it.
            if (info.sourceNextElement.length) {
                info.sourceNextElement.before(info.element);
            } else {
                info.sourceList.append(info.element);
            }
            confirmDelete(tile);
        } else if (info.positionChanged) {
            const ids = Array.from(list.querySelectorAll(SELECTORS.TILE)).map((t) => t.dataset.instanceid);
            callFunction('mod_vocab_reorder_game_instances', {vocabid: vocabid, ids: ids})
                .catch(Notification.exception);
        }
    });
};
