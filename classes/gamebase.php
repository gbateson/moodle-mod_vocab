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
 * The base class for games, which are "vocabgame" subplugins of the Vocabulary
 * activity, in mod/vocab/game/NAME, with a class \vocabgame_NAME\game.
 *
 * A game is given words by the activity (see progress::choose_words()), finds
 * or makes its materials for them, and when an attempt is finished, gives the
 * activity a score from 0 to 1 for each word (see progress::finish_attempt()).
 * See docs/games.md.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class gamebase {
    /** The default number of words in an attempt. */
    const DEFAULT_WORD_COUNT = 5;

    /** @var \stdClass the activity's record. */
    protected $vocab;

    /** @var \cm_info|\stdClass the activity's course module. */
    protected $cm;

    /** @var \context_module */
    protected $context;

    /** @var \stdClass the game instance, from vocab_game_instances. */
    protected $instance;

    /** @var array the instance's settings, from its configdata. */
    protected $config;

    /**
     * Constructor.
     *
     * @param \stdClass $vocab the activity's record.
     * @param \cm_info|\stdClass $cm the activity's course module.
     * @param \stdClass $instance the game instance.
     */
    public function __construct(\stdClass $vocab, $cm, \stdClass $instance) {
        $this->vocab = $vocab;
        $this->cm = $cm;
        $this->context = \context_module::instance($cm->id);
        $this->instance = $instance;
        $config = json_decode((string) ($instance->configdata ?? ''), true);
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Create the object for a game instance.
     *
     * @param \stdClass $vocab the activity's record.
     * @param \cm_info|\stdClass $cm the activity's course module.
     * @param \stdClass $instance the game instance, with "name" (see progress::get_game_instances()).
     * @return gamebase|null null if the game is not installed.
     */
    public static function create(\stdClass $vocab, $cm, \stdClass $instance): ?gamebase {
        global $DB;
        if (!isset($instance->name)) {
            $instance->name = $DB->get_field('vocab_games', 'name', ['id' => $instance->gameid]);
        }
        $class = '\\vocabgame_' . $instance->name . '\\game';
        if (!class_exists($class)) {
            return null;
        }
        return new $class($vocab, $cm, $instance);
    }

    /**
     * Get the class of an installed game.
     *
     * @param string $name e.g. "quiz".
     * @return string|null the class, or null if the game is not installed.
     */
    public static function get_class(string $name): ?string {
        $class = '\\vocabgame_' . $name . '\\game';
        return class_exists($class) ? $class : null;
    }

    /**
     * Get the installed games.
     *
     * @return string[] name => class.
     */
    public static function get_games(): array {
        $games = [];
        foreach (array_keys(\core_component::get_plugin_list('vocabgame')) as $name) {
            if ($class = self::get_class($name)) {
                $games[$name] = $class;
            }
        }
        return $games;
    }

    /**
     * Get the games of this type in all activities, or in some activities,
     * except in activities that are being deleted.
     *
     * @param int[]|null $vocabids the activities, or null for all activities.
     * @return static[]
     */
    public static function get_all(?array $vocabids = null): array {
        global $DB;
        $where = 'vg.name = :name';
        $params = ['name' => static::get_name()];
        if ($vocabids !== null) {
            if (empty($vocabids)) {
                return [];
            }
            [$vocabsql, $vocabparams] = $DB->get_in_or_equal($vocabids, SQL_PARAMS_NAMED, 'vocab');
            $where .= " AND vgi.vocabid $vocabsql";
            $params += $vocabparams;
        }
        $sql = "SELECT vgi.*, vg.name
                  FROM {vocab_game_instances} vgi
                  JOIN {vocab_games} vg ON vg.id = vgi.gameid
                 WHERE $where
              ORDER BY vgi.vocabid, vgi.sortorder";
        $games = [];
        $activities = [];
        foreach ($DB->get_records_sql($sql, $params) as $instance) {
            $vocabid = $instance->vocabid;
            if (!array_key_exists($vocabid, $activities)) {
                $activities[$vocabid] = null;
                $vocab = $DB->get_record('vocab', ['id' => $vocabid]);
                $cm = ($vocab ? get_coursemodule_from_instance('vocab', $vocabid, $vocab->course) : false);
                if ($cm && empty($cm->deletioninprogress)) {
                    $activities[$vocabid] = [$vocab, $cm];
                }
            }
            if ($activities[$vocabid]) {
                [$vocab, $cm] = $activities[$vocabid];
                $games[] = new static($vocab, $cm, $instance);
            }
        }
        return $games;
    }

    /**
     * Does the game use questions from the question bank? If so, it is told
     * when questions and question categories are deleted (see observer).
     *
     * @return bool
     */
    public static function uses_questions(): bool {
        return false;
    }

    /**
     * A question category has been deleted. Its subcategories have moved to its parent.
     * The game may change its settings, and say what happened, to tell teachers.
     *
     * @param \stdClass $category the deleted category: id, name, parent.
     * @return string|null a message for teachers, or null if the game is not affected.
     */
    public function question_category_deleted(\stdClass $category): ?string {
        return null;
    }

    /**
     * A question that is linked to a word in this activity has been deleted.
     *
     * @param \stdClass $question the deleted question: id, category, contextid.
     * @return string|null a message for teachers, or null if the game is not affected.
     */
    public function question_deleted(\stdClass $question): ?string {
        return null;
    }

    /**
     * A question bank (Moodle 5.x), and its categories, have been deleted.
     *
     * @return string|null a message for teachers, or null if the game is not affected.
     */
    public function question_bank_deleted(): ?string {
        return null;
    }

    /**
     * Get the game's name, e.g. "quiz".
     *
     * @return string
     */
    public static function get_name(): string {
        $parts = explode('\\', static::class);
        return substr($parts[0], strlen('vocabgame_'));
    }

    /**
     * Get the game's component, e.g. "vocabgame_quiz".
     *
     * @return string
     */
    public static function get_component(): string {
        return 'vocabgame_' . static::get_name();
    }

    /**
     * Get the game's localised name.
     *
     * @return string
     */
    public static function get_title(): string {
        return get_string('pluginname', static::get_component());
    }

    /**
     * Get the picture shown behind the game's tiles on the activity's page:
     * the game's pix/tile.svg or pix/tile.png (see docs/games.md).
     *
     * @return \moodle_url|null null if the game has no picture.
     */
    public static function get_tile_url(): ?\moodle_url {
        global $OUTPUT;
        $component = static::get_component();
        $dir = \core_component::get_component_directory($component);
        foreach (['svg', 'png'] as $extension) {
            if ($dir && file_exists("$dir/pix/tile.$extension")) {
                return $OUTPUT->image_url('tile', $component);
            }
        }
        return null;
    }

    /**
     * Get the stages at which the game can be used.
     *
     * @return string[] some of progress::STAGES.
     */
    public static function get_supported_stages(): array {
        return progress::STAGES;
    }

    /**
     * Add the game's own settings to the game's settings form (see gameform).
     * Each field's name must start with the prefix.
     *
     * @param \MoodleQuickForm $mform
     * @param string $prefix e.g. "game_quiz_".
     * @param \context $context the course or activity.
     */
    public static function add_settings(\MoodleQuickForm $mform, string $prefix, \context $context): void {
    }

    /**
     * Get the game's own settings from the game's settings form.
     *
     * @param \stdClass $data the form's data.
     * @param string $prefix
     * @return array name => value, saved as the instance's configdata.
     */
    public static function get_settings(\stdClass $data, string $prefix): array {
        return [];
    }

    /**
     * Check the game's own settings in the game's settings form.
     *
     * @param \stdClass $data the form's data.
     * @return array field name => error, or [] if there are no errors.
     */
    public static function validate_settings(\stdClass $data): array {
        return [];
    }

    /**
     * Get the instance.
     *
     * @return \stdClass
     */
    public function get_instance(): \stdClass {
        return $this->instance;
    }

    /**
     * Get the activity's course module.
     *
     * @return \cm_info|\stdClass
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Get the activity's context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * Change some settings of the instance, and save them.
     *
     * @param array $changes name => value.
     */
    protected function save_config(array $changes): void {
        global $DB;
        $this->config = array_merge($this->config, $changes);
        $this->instance->configdata = json_encode($this->config);
        $DB->set_field('vocab_game_instances', 'configdata', $this->instance->configdata, ['id' => $this->instance->id]);
    }

    /**
     * Get a setting of the instance.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get_config(string $name, $default = null) {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    /**
     * Get the suggested title of the game at a stage, e.g. "Quiz (Recognize)".
     *
     * @param string $stage one of progress::STAGES.
     * @return string
     */
    public static function get_default_title(string $stage): string {
        return static::get_title() . ' (' . progress::get_stage_name($stage) . ')';
    }

    /**
     * Get the label of the game's button on the activity's page: the title that
     * the teacher gave this game, or the suggested title if there is none.
     *
     * @return string
     */
    public function get_label(): string {
        $title = trim($this->instance->title ?? '');
        if ($title === '') {
            return static::get_default_title($this->instance->stage);
        }
        return $title;
    }

    /**
     * How many words does an attempt use?
     *
     * @return int
     */
    public function get_word_count(): int {
        return max(1, (int) $this->get_config('wordcount', self::DEFAULT_WORD_COUNT));
    }

    /**
     * Which of these words have the materials that the game needs?
     *
     * @param int[] $wordinstanceids
     * @return int[] the word instance ids that are ready.
     */
    public function get_ready_words(array $wordinstanceids): array {
        return $wordinstanceids;
    }

    /**
     * Can the game make missing materials in the background?
     *
     * @return bool
     */
    public function can_make_materials(): bool {
        return false;
    }

    /**
     * Which of these words have materials that are being made?
     *
     * @param int[] $wordinstanceids
     * @return int[] word instance ids.
     */
    public function get_pending_words(array $wordinstanceids): array {
        return [];
    }

    /**
     * Make the materials for some words, in the background.
     *
     * @param int[] $wordinstanceids
     * @param int $userid the teacher who asks for them, as whom they are made.
     * @return int how many words' materials are being made.
     */
    public function make_materials(array $wordinstanceids, int $userid): int {
        return 0;
    }

    /**
     * Get the state of the game's materials, for all the words in the activity.
     *
     * @return array total (count), ready (count), pending (count) and missing (word instance ids).
     */
    public function get_materials_summary(): array {
        $words = array_keys($this->get_progress()->get_words());
        $ready = $this->get_ready_words($words);
        $notready = array_values(array_diff($words, $ready));
        $pending = $notready ? $this->get_pending_words($notready) : [];
        return [
            'total' => count($words),
            'ready' => count($ready),
            'pending' => count($pending),
            'missing' => array_values(array_diff($notready, $pending)),
        ];
    }

    /**
     * Get the problems that may stop the game working as the teacher expects,
     * e.g. its question category has been deleted, to show to teachers.
     *
     * @return string[] messages, which may contain HTML.
     */
    public function get_problems(): array {
        return [];
    }

    /**
     * Get the URL where the game is played.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/vocab/game/' . static::get_name() . '/play.php', [
            'id' => $this->cm->id,
            'instanceid' => $this->instance->id,
        ]);
    }

    /**
     * Get the progress object for the activity.
     *
     * @return progress
     */
    public function get_progress(): progress {
        return new progress($this->vocab);
    }
}
