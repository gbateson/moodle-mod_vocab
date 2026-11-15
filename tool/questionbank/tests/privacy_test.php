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

namespace vocabtool_questionbank;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use vocabtool_questionbank\privacy\provider;

/**
 * Tests for the privacy provider of the question bank tool.
 *
 * @package    vocabtool_questionbank
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vocabtool_questionbank\privacy\provider
 */
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /**
     * Teachers' tasks and choices are found, exported and deleted.
     */
    public function test_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $vocab = $generator->create_module('vocab', ['course' => $course->id]);
        $context = \context_module::instance($vocab->cmid);
        $words = $generator->get_plugin_generator('mod_vocab')->add_words($vocab->id, ['cat']);
        $teachers = [$generator->create_user(), $generator->create_user()];
        foreach ($teachers as $teacher) {
            tool::insert_log(['userid' => $teacher->id, 'vocabid' => $vocab->id, 'wordid' => key($words),
                'qtype' => 'multichoice', 'qlevel' => 'A2', 'qcount' => 2, 'prompt' => 'Make questions about cat.']);
        }
        set_user_preference(provider::PREFERENCE . '5', '{"questioncount":2}', $teachers[0]);

        [$first, $second] = $teachers;
        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int) $first->id)->get_contextids());
        $userlist = new userlist($context, 'vocabtool_questionbank');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $userlist->get_userids());

        $this->export_context_data_for_user((int) $first->id, $context, 'vocabtool_questionbank');
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'vocabtool_questionbank')]);
        $this->assertSame('cat', $data->tasks[0]->word);
        $this->assertSame('Make questions about cat.', $data->tasks[0]->prompt);
        provider::export_user_preferences((int) $first->id);
        $preference = writer::with_context(\context_system::instance())->get_user_preferences('vocabtool_questionbank');
        $this->assertSame('{"questioncount":2}', $preference->{provider::PREFERENCE . '5'}->value);

        provider::delete_data_for_user(new approved_contextlist($first, 'vocabtool_questionbank', [$context->id]));
        $this->assertSame(0, $DB->count_records(provider::LOGTABLE, ['userid' => $first->id]));
        $this->assertSame(1, $DB->count_records(provider::LOGTABLE, ['userid' => $second->id]));
        provider::delete_data_for_users(new approved_userlist($context, 'vocabtool_questionbank', [$second->id]));
        $this->assertSame(0, $DB->count_records(provider::LOGTABLE));

        tool::insert_log(['userid' => $first->id, 'vocabid' => $vocab->id, 'wordid' => key($words), 'qtype' => 'match']);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records(provider::LOGTABLE));
    }
}
