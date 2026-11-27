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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\question_banks::class)]
/**
 * Tests for finding a course's questions in Moodle 4.x (the course) and 5.x (its question banks).
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\question_banks
 */
final class question_banks_test extends \advanced_testcase {
    /**
     * The course's questions are in the course (4.x), or in its question banks (5.x).
     * The default category is in one of those contexts, and so are the categories in the menu.
     */
    public function test_question_banks(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $default = question_banks::get_default_category($course);
        $contexts = question_banks::get_contexts($course->id);
        $this->assertArrayHasKey($default->contextid, $contexts);
        $this->assertSame((int) $default->id, (int) question_banks::get_default_category($course)->id);
        if (question_banks::uses_bank_activities()) {
            $this->assertSame(CONTEXT_MODULE, $contexts[$default->contextid]->contextlevel);
            // A second question bank in the course is included too.
            $qbank = $generator->create_module('qbank', ['course' => $course->id]);
            $this->assertArrayHasKey(\context_module::instance($qbank->cmid)->id, question_banks::get_contexts($course->id));
        } else {
            $this->assertSame([\context_course::instance($course->id)->id], array_keys($contexts));
        }

        $questions = $generator->get_plugin_generator('core_question');
        $category = $questions->create_question_category(['parent' => $default->id, 'name' => 'Fruit']);
        $other = question_banks::get_default_category($othercourse);
        $menu = question_banks::get_category_menu($course->id);
        $this->assertArrayHasKey($default->id, $menu);
        $this->assertArrayHasKey($category->id, $menu);
        $this->assertArrayNotHasKey($other->id, $menu);
        $this->assertStringContainsString(get_string('topfor', 'question', ''), reset($menu));
        $this->assertStringEndsWith('Fruit', $menu[$category->id]);

        $this->assertInstanceOf(\moodle_url::class, question_banks::get_manage_url($course->id));
    }
}
