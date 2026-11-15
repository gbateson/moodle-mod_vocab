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

use tool_aibridge\library\template;

/**
 * Tests for the AI bridge's providers and templates, as the activity uses them.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\aibridge
 */
final class aibridge_test extends \advanced_testcase {
    /**
     * The menus list the teacher's own providers and those shared with the course,
     * but not other teachers' personal providers.
     */
    public function test_menus(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $bridge = $generator->get_plugin_generator('tool_aibridge');
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $other = $generator->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);

        $own = $bridge->create_provider(['name' => 'Mine', 'ownerid' => $teacher->id,
            'contextid' => \context_user::instance($teacher->id)->id]);
        $shared = $bridge->create_provider(['name' => 'Course', 'ownerid' => $other->id, 'contextid' => $context->id]);
        $bridge->create_provider(['name' => 'Theirs', 'ownerid' => $other->id,
            'contextid' => \context_user::instance($other->id)->id]);
        $speech = $bridge->create_provider(['name' => 'Speech', 'capability' => 'speech', 'contextid' => $context->id]);

        $menu = aibridge::get_text_menu($context, $teacher->id);
        $this->assertSame([aibridge::AUTOMATIC, $own->get_id(), $shared->get_id()], array_keys($menu));

        $menu = aibridge::get_media_menu('audio', $context, $teacher->id);
        $this->assertSame([aibridge::NONE, aibridge::AUTOMATIC, $speech->get_id()], array_keys($menu));
        $this->assertSame([], aibridge::get_media_menu('video', $context, $teacher->id));

        // The activity's prompts are in the library, and the format menu starts with the prompt's own format.
        $prompts = aibridge::get_prompt_menu($context, $teacher->id);
        $this->assertArrayHasKey(template::find('mod_vocab:toeic-part1')->get_id(), $prompts);
        $formats = aibridge::get_format_menu($context, $teacher->id);
        $this->assertSame(0, array_key_first($formats));
    }

    /**
     * A prompt's defaults are its question types, until a teacher saves their own.
     */
    public function test_prompt_defaults(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $prompt = template::find('mod_vocab:toeic-part3');
        $this->assertSame([
            'imageassistant' => aibridge::AUTOMATIC,
            'audioassistant' => aibridge::AUTOMATIC,
            'qtypes' => ['multichoice' => 0, 'multianswer' => 0],
        ], aibridge::get_prompt_defaults($prompt, $user->id));

        // A prompt that links no formats has the links of the prompt that it extends.
        $parent = template::find('tool_aibridge:generate-questions');
        $child = template::save((object) ['type' => template::TYPE_PROMPT, 'name' => 'Child', 'ownerid' => $user->id,
            'contextid' => \context_user::instance($user->id)->id, 'parentid' => $parent->get_id(), 'body' => ''], $user->id);
        $this->assertSame(array_keys($parent->get_formatlinks()), array_keys(aibridge::get_formatlinks($child)));

        aibridge::save_prompt_defaults($prompt->get_id(), ['questioncount' => 2], $user->id);
        $this->assertSame(['questioncount' => 2], aibridge::get_prompt_defaults($prompt, $user->id));
    }
}
