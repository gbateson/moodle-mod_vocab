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

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\external\view_vocab::class)]
/**
 * Tests for the web service functions of the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     \mod_vocab\external\view_vocab
 */
final class externallib_test extends \advanced_testcase {
    /**
     * mod_vocab_view_vocab records a view, and checks that the user can view the activity.
     */
    public function test_view_vocab(): void {
        if (!class_exists('\core_external\external_api')) {
            $this->markTestSkipped('The web service functions need Moodle 4.2 or later.');
        }
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $result = external\view_vocab::execute($vocab->id);
        $result = \core_external\external_api::clean_returnvalue(external\view_vocab::execute_returns(), $result);
        $events = $sink->get_events();
        $sink->close();
        $this->assertTrue($result['status']);
        $this->assertSame([], $result['warnings']);
        $viewed = array_filter($events, function ($event) {
            return $event instanceof event\course_module_viewed;
        });
        $this->assertCount(1, $viewed);
        $this->assertEquals($vocab->id, reset($viewed)->objectid);

        // A user who is not in the course cannot view it.
        $this->setUser($generator->create_user());
        $this->expectException(\require_login_exception::class);
        external\view_vocab::execute($vocab->id);
    }
}
