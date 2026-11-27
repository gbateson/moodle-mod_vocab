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

/**
 * Where a course's questions are, in Moodle 4.x and 5.x.
 *
 * In Moodle 4.x, a course's questions are in the course's context. In Moodle 5.0
 * and later, they are in question bank activities (mod_qbank) in the course, each
 * with its own context; questions kept their ids when they moved there.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_banks {
    /**
     * Are questions kept in question bank activities, as in Moodle 5.0 and later?
     *
     * @return bool
     */
    public static function uses_bank_activities(): bool {
        return class_exists('\\core_question\\local\\bank\\question_bank_helper') &&
            method_exists('\\core_question\\local\\bank\\question_bank_helper', 'get_default_open_instance_system_type');
    }

    /**
     * Get the contexts in which a course's shared questions are kept:
     * the course (Moodle 4.x), or its question banks (Moodle 5.x).
     *
     * @param int $courseid
     * @return \context[] context id => context.
     */
    public static function get_contexts(int $courseid): array {
        global $DB;
        if (!self::uses_bank_activities()) {
            $context = \context_course::instance($courseid);
            return [$context->id => $context];
        }
        $modules = \core_question\local\bank\question_bank_helper::get_activity_types_with_shareable_questions();
        if (empty($modules)) {
            return [];
        }
        [$modsql, $params] = $DB->get_in_or_equal($modules, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND m.name $modsql
              ORDER BY cm.id";
        $contexts = [];
        foreach ($DB->get_fieldset_sql($sql, $params) as $cmid) {
            $context = \context_module::instance($cmid);
            $contexts[$context->id] = $context;
        }
        return $contexts;
    }

    /**
     * Get the default question category of a course, in which new questions are
     * put. It, and in Moodle 5.x the course's default question bank, are made if needed.
     *
     * @param \stdClass $course
     * @return \stdClass the category.
     */
    public static function get_default_category(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        if (!self::uses_bank_activities()) {
            return question_make_default_categories([\context_course::instance($course->id)]);
        }
        $cm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
        return question_get_default_category(\context_module::instance($cm->id)->id, true);
    }

    /**
     * Get the question categories of a course, for a menu: each top category is
     * shown with the name of its question bank, and the others are indented below it.
     *
     * @param int $courseid
     * @return string[] category id => name.
     */
    public static function get_category_menu(int $courseid): array {
        global $DB;
        $menu = [];
        foreach (self::get_contexts($courseid) as $context) {
            $categories = $DB->get_records(
                'question_categories',
                ['contextid' => $context->id],
                'parent, sortorder, name',
                'id, name, parent'
            );
            $children = [];
            foreach ($categories as $category) {
                $children[$category->parent][] = $category;
            }
            $add = function ($parent, $depth) use (&$add, &$menu, $children, $context) {
                foreach ($children[$parent] ?? [] as $category) {
                    if ($depth == 0) {
                        $name = get_string('topfor', 'question', $context->get_context_name(false, true));
                    } else {
                        $name = format_string($category->name, true, ['context' => $context]);
                    }
                    $menu[$category->id] = str_repeat("\u{00A0}\u{00A0}", $depth) . $name;
                    $add($category->id, $depth + 1);
                }
            };
            $add(0, 0);
        }
        return $menu;
    }

    /**
     * Get the URL of the page where teachers manage the course's question categories
     * (Moodle 4.x), or its question banks (Moodle 5.x).
     *
     * @param int $courseid
     * @return \moodle_url
     */
    public static function get_manage_url(int $courseid): \moodle_url {
        if (self::uses_bank_activities()) {
            return \core_question\local\bank\question_bank_helper::get_url_for_qbank_list($courseid);
        }
        return new \moodle_url('/question/bank/managecategories/category.php', ['courseid' => $courseid]);
    }
}
