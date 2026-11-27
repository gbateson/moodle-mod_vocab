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

namespace mod_vocab\event;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\event\course_module_viewed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\event\course_module_instance_list_viewed::class)]
/**
 * Tests for the events of the Vocabulary activity.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 * @covers     \mod_vocab\event\course_module_viewed
 * @covers     \mod_vocab\event\course_module_instance_list_viewed
 */
final class events_test extends \advanced_testcase {
    /**
     * Viewing an activity triggers an event, and marks it as viewed for activity completion.
     */
    public function test_course_module_viewed(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $instance = $generator->create_module(
            'vocab',
            ['course' => $course->id],
            ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED]
        );
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);
        $cm = get_coursemodule_from_instance('vocab', $instance->id);
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();
        $vocab = new \mod_vocab\activity($course, $cm, $instance, $context);
        $vocab->trigger_viewed_event_and_completion();
        $events = array_values(array_filter($sink->get_events(), function ($event) {
            return $event instanceof course_module_viewed;
        }));
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($instance->id, $event->objectid);
        $this->assertSame('vocab', $event->objecttable);
        $this->assertSame('r', $event->crud);
        $this->assertEquals(new \moodle_url('/mod/vocab/view.php', ['id' => $cm->id]), $event->get_url());
        $this->assertEventContextNotUsed($event);

        $completion = new \completion_info($course);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * Viewing the list of activities in a course triggers an event.
     */
    public function test_course_module_instance_list_viewed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $sink = $this->redirectEvents();
        $event = course_module_instance_list_viewed::create(['context' => $context]);
        $event->add_record_snapshot('course', $course);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(course_module_instance_list_viewed::class, $events[0]);
        $this->assertEquals($context, $events[0]->get_context());
        $this->assertEquals(new \moodle_url('/mod/vocab/index.php', ['id' => $course->id]), $events[0]->get_url());
    }
}
