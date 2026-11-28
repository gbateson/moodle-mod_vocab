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
 * Behat data generator for the Vocabulary activity, e.g.
 *
 *     Given the following "mod_vocab > game instances" exist:
 *       | vocab  | game | stage     | title     |
 *       | vocab1 | quiz | recognize | Food quiz |
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_vocab_generator extends behat_generator_base {
    /**
     * Get the things that this generator can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'game instances' => [
                'singular' => 'game instance',
                'datagenerator' => 'game_instance',
                'required' => ['vocab', 'stage'],
                'switchids' => ['vocab' => 'vocabid'],
            ],
            'words' => [
                'singular' => 'word',
                'datagenerator' => 'word',
                'required' => ['vocab', 'word'],
                'switchids' => ['vocab' => 'vocabid'],
            ],
            'dictionary senses' => [
                'singular' => 'dictionary sense',
                'datagenerator' => 'dictionary_sense',
                'required' => ['word', 'meaning'],
            ],
        ];
    }

    /**
     * Get the id of a Vocabulary activity from its idnumber or name.
     *
     * @param string $vocab
     * @return int
     */
    protected function get_vocab_id(string $vocab): int {
        $cm = $this->get_cm_by_activity_name('vocab', $vocab);
        return (int) $cm->instance;
    }
}
