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

namespace mod_vocab\search;

/**
 * Tests for the search area of the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     \mod_vocab\search\activity
 */
final class search_test extends \advanced_testcase {
    /**
     * Activities are indexed with their names and descriptions, and only users who can see them can find them.
     */
    public function test_activity(): void {
        $this->resetAfterTest();
        set_config('enableglobalsearch', true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id, 'name' => 'Animal words',
            'intro' => 'Words about animals.']);

        $area = \core_search\manager::get_search_area('mod_vocab-activity');
        $this->assertInstanceOf(activity::class, $area);
        $records = $area->get_document_recordset(0);
        $documents = [];
        foreach ($records as $record) {
            $documents[] = $area->get_document($record);
        }
        $records->close();
        $this->assertCount(1, $documents);
        $this->assertSame('Animal words', $documents[0]->get('title'));
        $this->assertStringContainsString('Words about animals.', $documents[0]->get('content'));

        $student = $generator->create_and_enrol($course, 'student');
        $outsider = $generator->create_user();
        $this->setUser($student);
        $this->assertSame(\core_search\manager::ACCESS_GRANTED, $area->check_access($vocab->id));
        $this->setUser($outsider);
        $this->assertSame(\core_search\manager::ACCESS_DENIED, $area->check_access($vocab->id));
    }
}
