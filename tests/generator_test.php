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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab_generator::class)]
/**
 * Tests for the data generator of the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     \mod_vocab_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * Activities are made with names, in a course, and words are added to their word lists.
     */
    public function test_create_instance_and_add_words(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_vocab');
        $this->assertInstanceOf(\mod_vocab_generator::class, $generator);

        $vocab = $generator->create_instance(['course' => $course->id]);
        $this->assertSame('Vocabulary 1', $vocab->name);
        $this->assertTrue($DB->record_exists('vocab', ['id' => $vocab->id, 'course' => $course->id]));
        $cm = get_coursemodule_from_instance('vocab', $vocab->id);
        $this->assertEquals($vocab->cmid, $cm->id);
        $other = $generator->create_instance(['course' => $course->id, 'name' => 'Animals']);
        $this->assertSame('Animals', $other->name);

        // Words are added once to the dictionary, and to each word list that uses them.
        $words = $generator->add_words($vocab->id, ['cat', 'dog']);
        $this->assertSame(['cat', 'dog'], array_values($words));
        $generator->add_words($other->id, ['dog']);
        $this->assertSame(2, $DB->count_records('vocab_words'));
        $this->assertSame(2, $DB->count_records('vocab_word_instances', ['vocabid' => $vocab->id]));
        $this->assertSame(1, $DB->count_records('vocab_word_instances', ['vocabid' => $other->id]));
        $this->assertSame(['cat', 'dog'], array_values(array_column(progress::create($vocab->id)->get_words(), 'word')));
    }
}
