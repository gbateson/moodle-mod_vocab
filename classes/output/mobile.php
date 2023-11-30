<?php
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
 * mod/vocab/classes/output/mobile.php
 * the mobile output class for the Vocab activity module
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace mod_vocab\output;

/**
 * mobile
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 4.1
 */
class mobile {

    /**
     * Returns the initial page when viewing the activity via the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $PAGE;

        $cmid = $args['cmid'];
        $courseid = $args['courseid'];
        $versioncode = $args['appversioncode'] >= 3950 ? 'latest' : 'ionic3';

        $vocab = \mod_vocab\activity::create($courseid, $cmid);

        // Check login and capabilities.
        $vocab->require_login();
        $vocab->require('view');

        // Trigger module viewed event and completion.
        $vocab->trigger_viewed_event_and_completion();

        $renderer = $PAGE->get_renderer('mod_vocab');
        $renderer->attach_activity($vocab);

        if ($vocab->viewable) {
            $html = $renderer->view_page();
        } else if (isguestuser()) {
            // Guests can't view vocab activities, so
            // offer a choice of logging in or going back.
            $html = $renderer->view_page_guest();
        } else {
            // If user is not enrolled in this course
            // in a good enough role, tell them to enrol.
            $html = $renderer->view_page_notenrolled();
        }
        // $html = $OUTPUT->render_from_template("mod_vocab/mobile_view_page_$versioncode", $data);

        return [
            'templates' => [['id' => 'main', 'html' => $html]],
            'javascript' => '',
            'otherdata' => '',
            'files' => '',
        ];
    }
}

