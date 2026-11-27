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

namespace mod_vocab\external;

use core_external\external_api;
use mod_vocab\progress;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\external\game_instance_api::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\external\delete_game_instance::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\external\reorder_game_instances::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocab\external\set_game_instance_enabled::class)]
/**
 * Tests of the web service functions that manage the games in an activity.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vocab\external\game_instance_api
 * @covers     \mod_vocab\external\delete_game_instance
 * @covers     \mod_vocab\external\reorder_game_instances
 * @covers     \mod_vocab\external\set_game_instance_enabled
 */
final class game_instances_test extends \advanced_testcase {
    /**
     * Make an activity with three games, and a teacher and a student.
     *
     * @return array [vocab id, game instance ids, teacher, student]
     */
    protected function make_activity(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        progress::save_stages($vocab->id, [progress::STAGE_RECOGNIZE => [1, 1]]);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = progress::add_game_instance($vocab->id, 'quiz', progress::STAGE_RECOGNIZE);
        }
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        return [(int) $vocab->id, $ids, $teacher, $student];
    }

    /**
     * Call a web service function as the current user, as the activity's page does.
     *
     * @param string $function e.g. "mod_vocab_delete_game_instance".
     * @param array $args
     * @return array the response: error (bool), data or exception.
     */
    protected function call(string $function, array $args): array {
        // AJAX calls check the session key of the request.
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function($function, $args, true);
    }

    /**
     * A teacher deletes, hides, shows and reorders games.
     */
    public function test_manage_games(): void {
        $this->resetAfterTest();
        [$vocabid, [$a, $b, $c], $teacher] = $this->make_activity();
        $this->setUser($teacher);
        $progress = progress::create($vocabid);

        $response = $this->call('mod_vocab_delete_game_instance', ['id' => $a]);
        $this->assertFalse($response['error']);
        $this->assertTrue($response['data']['deleted']);
        $this->assertSame([$b, $c], array_keys($progress->get_game_instances(false)));

        $response = $this->call('mod_vocab_set_game_instance_enabled', ['id' => $b, 'enabled' => false]);
        $this->assertFalse($response['error']);
        $this->assertSame([$c], array_keys($progress->get_game_instances()));
        $this->call('mod_vocab_set_game_instance_enabled', ['id' => $b, 'enabled' => true]);
        $this->assertSame([$b, $c], array_keys($progress->get_game_instances()));

        $response = $this->call('mod_vocab_reorder_game_instances', ['vocabid' => $vocabid, 'ids' => [$c, $b]]);
        $this->assertFalse($response['error']);
        $this->assertSame([$c, $b], array_keys($progress->get_game_instances()));
    }

    /**
     * Students cannot manage games.
     */
    public function test_students_cannot_manage_games(): void {
        $this->resetAfterTest();
        [$vocabid, [$a, $b], , $student] = $this->make_activity();
        $this->setUser($student);
        $calls = [
            ['mod_vocab_delete_game_instance', ['id' => $a]],
            ['mod_vocab_set_game_instance_enabled', ['id' => $a, 'enabled' => false]],
            ['mod_vocab_reorder_game_instances', ['vocabid' => $vocabid, 'ids' => [$b, $a]]],
        ];
        foreach ($calls as [$function, $args]) {
            $response = $this->call($function, $args);
            $this->assertTrue($response['error'], $function);
            $this->assertSame('nopermissions', $response['exception']->errorcode, $function);
        }
        $this->assertSame([$a, $b], array_slice(array_keys(progress::create($vocabid)->get_game_instances()), 0, 2));
    }
}
