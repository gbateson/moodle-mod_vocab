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
 * The settings of one game in a Vocabulary activity: its stage, its title and
 * the game's own settings. The same form is shown in a popup on the activity's
 * page (core_form/modalform) and, without JavaScript, on game/edit.php.
 *
 * To add a game, the form is given "cmid" and "game" (e.g. "quiz").
 * To edit a game, it is given "id", the id of the game instance.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gameform extends \core_form\dynamic_form {
    /** @var \stdClass|null the course module, game instance (null when adding), game name and class */
    protected $target = null;

    /**
     * Get the course module, the game instance, and the game's name and class,
     * from the form's parameters.
     *
     * @return \stdClass with cm, instance (null when adding a game), name and class.
     */
    protected function get_target(): \stdClass {
        global $DB;
        if ($this->target === null) {
            $instance = null;
            if ($id = $this->optional_param('id', 0, PARAM_INT)) {
                $sql = 'SELECT vgi.*, vg.name
                          FROM {vocab_game_instances} vgi
                          JOIN {vocab_games} vg ON vg.id = vgi.gameid
                         WHERE vgi.id = ?';
                $instance = $DB->get_record_sql($sql, [$id], MUST_EXIST);
                $cm = get_coursemodule_from_instance('vocab', $instance->vocabid, 0, false, MUST_EXIST);
                $name = $instance->name;
            } else {
                $cmid = $this->optional_param('cmid', 0, PARAM_INT);
                $cm = get_coursemodule_from_id('vocab', $cmid, 0, false, MUST_EXIST);
                $name = $this->optional_param('game', '', PARAM_PLUGIN);
            }
            $class = gamebase::get_class($name);
            if (!$class) {
                throw new \moodle_exception('errorunknowngame', 'mod_vocab', '', s($name));
            }
            $this->target = (object) ['cm' => $cm, 'instance' => $instance, 'name' => $name, 'class' => $class];
        }
        return $this->target;
    }

    /**
     * Get the problems with the game that is being edited, to show to the teacher.
     *
     * @return string[]
     */
    protected function get_problems(): array {
        global $DB;
        $target = $this->get_target();
        if (!$target->instance) {
            return [];
        }
        $vocab = $DB->get_record('vocab', ['id' => $target->cm->instance], '*', MUST_EXIST);
        $game = gamebase::create($vocab, $target->cm, $target->instance);
        return $game ? $game->get_problems() : [];
    }

    /**
     * Get the stages that can be chosen for this game: the stages that the game
     * supports and the activity uses, and the game's current stage, if any.
     *
     * @return string[] stage => stage name, in the order of the stages.
     */
    protected function get_stage_menu(): array {
        $target = $this->get_target();
        $class = $target->class;
        $used = progress::create($target->cm->instance)->get_stages();
        $menu = [];
        foreach (progress::STAGES as $stage) {
            $current = ($target->instance && $target->instance->stage == $stage);
            if ($current || (isset($used[$stage]) && in_array($stage, $class::get_supported_stages()))) {
                $menu[$stage] = progress::get_stage_name($stage);
            }
        }
        return $menu;
    }

    /**
     * Get the suggested titles of this game at each stage, e.g. "Quiz (Recognize)".
     *
     * @return string[] stage => title
     */
    protected function get_default_titles(): array {
        $class = $this->get_target()->class;
        $titles = [];
        foreach (progress::STAGES as $stage) {
            $titles[$stage] = $class::get_default_title($stage);
        }
        return $titles;
    }

    /**
     * Load the JavaScript that changes a suggested title when the stage is changed.
     * This is done here, when the form is rendered, and not in definition(), because
     * the JavaScript for a form in a popup is collected only while it is rendered.
     */
    public function definition_after_data() {
        global $PAGE;
        parent::definition_after_data();
        $PAGE->requires->js_call_amd('mod_vocab/gameform', 'init');
    }

    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $plugin = 'mod_vocab';
        $target = $this->get_target();
        $class = $target->class;

        foreach (['id' => PARAM_INT, 'cmid' => PARAM_INT, 'game' => PARAM_PLUGIN] as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }

        // Problems with an existing game, e.g. its question category has been deleted.
        foreach ($this->get_problems() as $problem) {
            $mform->addElement('html', \html_writer::div($problem, 'alert alert-warning'));
        }

        $stages = $this->get_stage_menu();
        if (empty($stages)) {
            $mform->addElement('static', 'nostages', '', get_string('errornostagesforgame', $plugin, $class::get_title()));
            $this->add_page_buttons(false);
            return;
        }
        $mform->addElement('select', 'stage', get_string('gamestage', $plugin), $stages);
        $mform->addHelpButton('stage', 'gamestage', $plugin);
        $mform->setType('stage', PARAM_ALPHA);

        $stage = ($target->instance ? $target->instance->stage : array_key_first($stages));
        $mform->addElement('text', 'title', get_string('gametitle', $plugin), ['size' => 40, 'maxlength' => 255]);
        $mform->addHelpButton('title', 'gametitle', $plugin);
        $mform->setType('title', PARAM_TEXT);
        $mform->setDefault('title', $class::get_default_title($stage));

        // When the stage is changed, a suggested title is changed to suit the new stage.
        $mform->updateAttributes(['data-vocab-suggested-titles' => json_encode($this->get_default_titles())]);

        $class::add_settings($mform, '', $this->get_context_for_dynamic_submission());
        $this->add_page_buttons(true);
    }

    /**
     * Add the Save and Cancel buttons when the form is shown on a page (game/edit.php).
     * In a popup, the popup has its own buttons.
     *
     * @param bool $save false to show only the Cancel button.
     */
    protected function add_page_buttons(bool $save): void {
        if ($this->_ajaxformdata === null) {
            $this->add_action_buttons(true, ($save ? get_string('savechanges') : false));
        }
    }

    /**
     * Check the stage and the game's own settings.
     *
     * @param array $data
     * @param array $files
     * @return array field name => error
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $class = $this->get_target()->class;
        if (!array_key_exists($data['stage'] ?? '', $this->get_stage_menu())) {
            $errors['stage'] = get_string('required');
        }
        return array_merge($errors, $class::validate_settings((object) $data));
    }

    /**
     * Get the context of the activity.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_module::instance($this->get_target()->cm->id);
    }

    /**
     * Check that the user can manage the activity's games.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/vocab:manage', $this->get_context_for_dynamic_submission());
    }

    /**
     * Load the game's current settings, or the defaults for a new game.
     */
    public function set_data_for_dynamic_submission(): void {
        $target = $this->get_target();
        $data = ['cmid' => $target->cm->id, 'game' => $target->name];
        if ($instance = $target->instance) {
            $data = (array) json_decode($instance->configdata ?? '', true) + $data;
            $data['id'] = $instance->id;
            $data['stage'] = $instance->stage;
            // An empty title means the suggested title, so show that.
            $data['title'] = ($instance->title === '' ? $target->class::get_default_title($instance->stage) : $instance->title);
        } else {
            $data['id'] = 0;
        }
        $this->set_data($data);
    }

    /**
     * Save the game: add it to the activity, or update it.
     *
     * @return array with the id of the game instance.
     */
    public function process_dynamic_submission() {
        $target = $this->get_target();
        $class = $target->class;
        $data = $this->get_data();
        $config = $class::get_settings($data, '');
        $title = trim($data->title ?? '');
        if (in_array($title, $this->get_default_titles())) {
            // A suggested title, maybe for another stage if JavaScript was not available:
            // store it as empty, so that the title follows the game's stage.
            $title = '';
        }
        if ($instance = $target->instance) {
            progress::update_game_instance($instance->id, $data->stage, $config, $title);
            $id = (int) $instance->id;
        } else {
            $id = progress::add_game_instance($target->cm->instance, $target->name, $data->stage, $config, $title);
        }
        return ['id' => $id];
    }

    /**
     * Get the page where the form is shown without JavaScript.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $target = $this->get_target();
        if ($target->instance) {
            return new \moodle_url('/mod/vocab/game/edit.php', ['id' => $target->instance->id]);
        }
        return new \moodle_url('/mod/vocab/game/edit.php', ['cmid' => $target->cm->id, 'game' => $target->name]);
    }
}
