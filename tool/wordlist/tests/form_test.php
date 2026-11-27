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

namespace vocabtool_wordlist;

#[\PHPUnit\Framework\Attributes\CoversClass(\vocabtool_wordlist\form::class)]
/**
 * Tests of the form for editing a word list.
 *
 * @package    vocabtool_wordlist
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vocabtool_wordlist\form
 */
final class form_test extends \advanced_testcase {
    /**
     * Get the HTML of the word list form for an activity.
     *
     * @param \stdClass $vocab
     * @return string
     */
    protected function render_form(\stdClass $vocab): string {
        global $PAGE;
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/mod/vocab/tool/wordlist/index.php', ['id' => $cm->id]);
        $PAGE->set_cm($cm);
        return tool::create($vocab->id)->get_mform()->render();
    }

    /**
     * "Select random words" is shown only if the dictionary has words that are not
     * already used in a Vocabulary activity in the course.
     */
    public function test_select_random_words(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $name = get_string('selectwords', 'vocabtool_wordlist');

        // No words in the dictionary.
        $this->assertStringNotContainsString($name, $this->render_form($vocab));

        // A word in the dictionary, which is its own lemma.
        $lemmaid = $DB->insert_record('vocab_lemmas', (object) ['langid' => 0, 'lemma' => 'cat']);
        $DB->insert_record('vocab_words', (object) ['lemmaid' => $lemmaid, 'word' => 'cat']);
        $this->assertStringContainsString($name, $this->render_form($vocab));

        // The word is used by another activity in the course, so it cannot be selected.
        $other = $generator->create_module('vocab', ['course' => $course->id]);
        $generator->get_plugin_generator('mod_vocab')->add_words($other->id, ['cat']);
        $this->assertStringNotContainsString($name, $this->render_form($vocab));
    }
}
