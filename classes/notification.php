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

namespace mod_vocab;

/**
 * Tell the teachers of a Vocabulary activity about a problem with one of its games,
 * e.g. its question category has been deleted (see observer).
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification {
    /**
     * Send a message about a game to the users enrolled in the course who can manage the activity.
     *
     * @param gamebase $game
     * @param string $text what happened, and what the game did about it.
     * @return int how many messages were sent.
     */
    public static function send(gamebase $game, string $text): int {
        $cm = $game->get_cm();
        $context = $game->get_context();
        $url = new \moodle_url('/mod/vocab/view.php', ['id' => $cm->id]);
        $activity = format_string($cm->name, true, ['context' => $context]);
        $subject = get_string('gameproblemsubject', 'mod_vocab', (object) [
            'activity' => $activity,
            'game' => $game->get_label(),
        ]);
        $users = get_enrolled_users($context, 'mod/vocab:manage', 0, 'u.*', null, 0, 0, true);
        $count = 0;
        foreach ($users as $user) {
            $message = new \core\message\message();
            $message->component = 'mod_vocab';
            $message->name = 'gameproblem';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = $text . "\n\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s($text)) .
                \html_writer::tag('p', \html_writer::link($url, s($activity)));
            $message->smallmessage = $text;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $activity;
            $message->courseid = $cm->course;
            if (message_send($message)) {
                $count++;
            }
        }
        return $count;
    }
}
