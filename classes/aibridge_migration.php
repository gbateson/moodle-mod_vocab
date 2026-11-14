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

use tool_aibridge\library;
use tool_aibridge\library\template;
use tool_aibridge\presets;
use tool_aibridge\provider;
use tool_aibridge\tuning;

/**
 * Move the settings of the old "vocabai" subplugins into the AI bridge (tool_aibridge).
 *
 * - ChatGPT, DALL-E, Imagen and TTS settings become providers.
 * - Prompts and formats become templates in the bridge's library.
 * - Tuning files become fine-tuning jobs; a file that was already used to tune
 *   a model also becomes a provider for the tuned model.
 *
 * Each item keeps its owner and the context where it was shared; items shared
 * in an activity are shared in its course. The question bank tool's log is
 * updated to point at the new items, the choices that were saved with each
 * prompt become the owner's defaults for that prompt, and then the old tables,
 * settings and files are removed.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aibridge_migration {
    /** The old table of settings, one record for each item. */
    const CONFIG_TABLE = 'vocab_config';

    /** The old table of setting names and values. */
    const SETTINGS_TABLE = 'vocab_config_settings';

    /** The old subplugins. */
    const SUBPLUGINS = ['chatgpt', 'dalle', 'files', 'formats', 'imagen', 'prompts', 'tts'];

    /** The order in which the items are moved, so that what an item refers to is moved before it. */
    const ORDER = ['chatgpt', 'dalle', 'imagen', 'tts', 'files', 'formats', 'prompts'];

    /** The role that the old ChatGPT subplugin gave the AI. */
    const CHATGPT_ROLE = 'Act as an expert creator of online language-learning materials.';

    /** The old question bank log table. */
    const LOG_TABLE = 'vocabtool_questionbank_log';

    /** @var int[] old ChatGPT, DALL-E, Imagen and TTS config id => provider id. */
    protected $providers = [];

    /** @var int[] old tuning file config id => provider id of the tuned model. */
    protected $tunedproviders = [];

    /** @var int[] old prompt and format config id => template id. */
    protected $templates = [];

    /** @var string[] old format config id => template short name. */
    protected $formatnames = [];

    /** @var \stdClass[] old prompt config id => prompt config, for the defaults. */
    protected $prompts = [];

    /**
     * Is the AI bridge installed, with its tables?
     *
     * @return bool
     */
    public static function is_bridge_ready(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists(provider::TABLE)
            && $dbman->table_exists(template::TABLE)
            && $dbman->table_exists(tuning::TABLE);
    }

    /**
     * Add the activity's templates to the bridge's library, move the old
     * settings, and remove the old subplugins. If the bridge is not installed
     * yet, e.g. when it is installed in the same upgrade, this is done when it
     * is installed: see mod_vocab_aibridge_installed().
     *
     * @return bool true if this was done.
     */
    public static function run(): bool {
        global $DB;
        if (!self::is_bridge_ready()) {
            set_config('movetoaibridge', 1, 'mod_vocab');
            return false;
        }
        library::sync_component('mod_vocab');
        if ($DB->get_manager()->table_exists(self::CONFIG_TABLE)) {
            $migration = new self();
            $migration->move_all();
            $migration->update_logs();
            $migration->save_prompt_defaults();
            $migration->remove_old_files();
            $dbman = $DB->get_manager();
            foreach ([self::SETTINGS_TABLE, self::CONFIG_TABLE] as $table) {
                $dbman->drop_table(new \xmldb_table($table));
            }
        }
        self::remove_subplugins();
        unset_config('movetoaibridge', 'mod_vocab');
        return true;
    }

    /**
     * Move every item, in order.
     */
    protected function move_all(): void {
        global $DB;
        $configs = [];
        foreach ($DB->get_records(self::CONFIG_TABLE, null, 'id') as $config) {
            $config->settings = [];
            $configs[$config->id] = $config;
        }
        foreach ($DB->get_recordset(self::SETTINGS_TABLE, null, 'id') as $setting) {
            if (isset($configs[$setting->configid])) {
                $configs[$setting->configid]->settings[$setting->name] = $setting->value;
            }
        }
        foreach (self::ORDER as $subplugin) {
            foreach ($configs as $config) {
                if ($config->subplugin !== "vocabai_$subplugin") {
                    continue;
                }
                $contextid = self::get_contextid((int) $config->contextid);
                if (!$contextid) {
                    continue;
                }
                $ownerid = self::get_ownerid((int) $config->owneruserid);
                $settings = (object) $config->settings;
                $method = "move_$subplugin";
                $this->$method((int) $config->id, $settings, $contextid, $ownerid);
            }
        }
    }

    /**
     * Get the context in which a moved item is shared. The bridge shares
     * items with users, courses, categories or the whole site.
     *
     * @param int $contextid the old context.
     * @return int the new context id, or 0 if the old context no longer exists.
     */
    public static function get_contextid(int $contextid): int {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return 0;
        }
        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
            case CONTEXT_USER:
            case CONTEXT_COURSECAT:
            case CONTEXT_COURSE:
                return $context->id;
        }
        $coursecontext = $context->get_course_context(false);
        return $coursecontext ? $coursecontext->id : SYSCONTEXTID;
    }

    /**
     * Get the owner of a moved item: the old owner, if the account still exists, else the main admin.
     *
     * @param int $userid
     * @return int
     */
    public static function get_ownerid(int $userid): int {
        global $DB;
        if ($userid && $DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return $userid;
        }
        return (int) get_admin()->id;
    }

    /**
     * Get a setting.
     *
     * @param \stdClass $settings
     * @param string $name
     * @return string the value, trimmed, or '' if it is not set.
     */
    protected static function get(\stdClass $settings, string $name): string {
        return isset($settings->$name) ? trim((string) $settings->$name) : '';
    }

    /**
     * Get the fields of a new provider, from a preset.
     *
     * @param string $preset
     * @param \stdClass $settings
     * @param string $prefix of the url, key and model settings, e.g. "chatgpt".
     * @param int $contextid
     * @param int $ownerid
     * @return \stdClass
     */
    protected static function new_provider(
        string $preset,
        \stdClass $settings,
        string $prefix,
        int $contextid,
        int $ownerid
    ): \stdClass {
        $record = (object) presets::get_presets()[$preset];
        foreach (['filetemplate', 'tokendefaults', 'uploadstyle', 'uploadurl', 'uploadprotocol'] as $field) {
            if (!isset($record->$field)) {
                $record->$field = ($field === 'uploadstyle') ? provider::UPLOAD_NONE : '';
            }
        }
        $url = self::get($settings, $prefix . 'url');
        if ($url !== '') {
            // The bridge sends the key in a header, so it need not be in the URL.
            $record->url = preg_replace('/[?&]key=[^&]*$/', '', $url);
        }
        $model = self::get($settings, $prefix . 'model');
        if ($model !== '') {
            $record->model = $model;
        }
        $record->name = $record->name . ' (' . $record->model . ')';
        $record->ownerid = $ownerid;
        $record->contextid = $contextid;
        $record->datanotice = '';
        $record->userratelimit = self::get_ratelimit($settings);
        $record->enabled = 1;
        $record->sortorder = 0;
        $record->sharedfrom = (int) self::get($settings, 'sharedfrom');
        $record->shareduntil = (int) self::get($settings, 'shareduntil');
        return $record;
    }

    /**
     * Get the most requests in an hour, from the old speed limit, e.g. 10 items in 2 days.
     *
     * @param \stdClass $settings
     * @return int 0 for no limit.
     */
    public static function get_ratelimit(\stdClass $settings): int {
        $itemcount = (int) self::get($settings, 'itemcount');
        $timecount = (int) self::get($settings, 'timecount');
        if ($itemcount <= 0 || $timecount <= 0) {
            return 0;
        }
        // The old units: 1 seconds, 2 minutes, 3 hours, 4 days, 5 weeks, 6 months.
        $units = [1 => 1, 2 => MINSECS, 3 => HOURSECS, 4 => DAYSECS, 5 => WEEKSECS, 6 => 4 * WEEKSECS];
        $timeunit = (int) self::get($settings, 'timeunit');
        $seconds = $timecount * (isset($units[$timeunit]) ? $units[$timeunit] : HOURSECS);
        return max(1, (int) floor($itemcount * HOURSECS / $seconds));
    }

    /**
     * Save a provider, and remember its id.
     *
     * @param int $oldid
     * @param \stdClass $record
     * @param string $apikey
     */
    protected function save_provider(int $oldid, \stdClass $record, string $apikey): void {
        $this->providers[$oldid] = provider::save($record, $apikey)->get_id();
    }

    /**
     * Move ChatGPT settings.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_chatgpt(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $record = self::new_provider('openai', $settings, 'chatgpt', $contextid, $ownerid);
        $request = [
            'model' => '{{MODEL}}',
            'messages' => [
                ['role' => 'system', 'content' => self::CHATGPT_ROLE],
                ['role' => 'user', 'content' => '{{PROMPT}}'],
            ],
        ];
        foreach (['temperature', 'top_p'] as $name) {
            $value = self::get($settings, $name);
            if ($value !== '' && is_numeric($value) && (float) $value > 0) {
                $request[$name] = (float) $value;
            }
        }
        $record->requesttemplate = presets::json($request);
        $this->save_provider($oldid, $record, self::get($settings, 'chatgptkey'));
    }

    /**
     * Move DALL-E settings.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_dalle(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $model = self::get($settings, 'dallemodel');
        $preset = (strpos($model, 'gpt-image') === 0) ? 'openaiimage' : 'dalle3';
        $record = self::new_provider($preset, $settings, 'dalle', $contextid, $ownerid);
        if ($preset === 'dalle3') {
            $defaults = json_decode($record->tokendefaults, true);
            $style = self::get($settings, 'style');
            if ($style !== '') {
                $defaults['STYLE'] = $style;
            }
            if ($model === 'dall-e-2') {
                // DALL-E 2 makes only square images, and has no quality or style.
                $defaults = ['SIZE_SQUARE' => '1024x1024', 'SIZE_LANDSCAPE' => '1024x1024', 'SIZE_PORTRAIT' => '1024x1024'];
                $request = json_decode($record->requesttemplate, true);
                unset($request['quality'], $request['style']);
                $record->requesttemplate = presets::json($request);
            }
            $record->tokendefaults = presets::json($defaults);
        }
        $this->save_provider($oldid, $record, self::get($settings, 'dallekey'));
    }

    /**
     * Move Imagen settings.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_imagen(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $record = self::new_provider('imagen', $settings, 'imagen', $contextid, $ownerid);
        $this->save_provider($oldid, $record, self::get($settings, 'imagenkey'));
    }

    /**
     * Move TTS settings.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_tts(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $record = self::new_provider('openaitts', $settings, 'tts', $contextid, $ownerid);
        $defaults = json_decode($record->tokendefaults, true);
        $voice = self::get($settings, 'voice');
        if ($voice !== '' && $voice !== 'random') {
            $defaults['VOICE'] = $voice;
        }
        $format = self::get($settings, 'response_format');
        if (in_array($format, ['mp3', 'wav'])) {
            $defaults['AUDIOFORMAT'] = $format;
        }
        $speed = self::get($settings, 'speed');
        if (is_numeric($speed) && (float) $speed > 0 && (float) $speed != 1) {
            $request = json_decode($record->requesttemplate, true);
            $request['speed'] = (float) $speed;
            $record->requesttemplate = presets::json($request);
        }
        $record->tokendefaults = presets::json($defaults);
        $this->save_provider($oldid, $record, self::get($settings, 'ttskey'));
    }

    /**
     * Move a tuning file: as a fine-tuning job, and as a provider if the
     * file was already used to tune a model. The job is not started again.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_files(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        global $DB;
        $base = $this->find_chatgpt_provider($ownerid);
        $name = self::get($settings, 'filedescription');
        if ($name === '') {
            $name = 'Tuning ' . $oldid;
        }
        $model = self::get($settings, 'chatgptmodelid');
        $jobid = self::get($settings, 'chatgptjobid');
        $time = time();
        $record = (object) [
            'name' => $name,
            'ownerid' => $ownerid,
            'contextid' => $contextid,
            'providerid' => $base ? $base->get_id() : 0,
            'basemodel' => $base ? tuning::get_tunable_model((string) $base->get_record()->model) : '',
            'status' => tuning::STATUS_FAILED,
            'remotefileid' => self::get($settings, 'chatgptfileid'),
            'jobid' => $jobid,
            'tunedmodel' => $model,
            'tunedproviderid' => 0,
            'error' => get_string('aibridgetuningnotstarted', 'mod_vocab'),
            'timecreated' => $time,
            'timemodified' => $time,
        ];
        if ($base && $model !== '') {
            $tuned = clone($base->get_record());
            unset($tuned->id, $tuned->apikey, $tuned->contextdepth);
            $tuned->name = $base->get_name() . ' (' . $name . ')';
            $tuned->model = $model;
            $tuned->ownerid = $ownerid;
            $tuned->contextid = $contextid;
            $tuned = provider::save($tuned, $base->get_apikey());
            $this->tunedproviders[$oldid] = $tuned->get_id();
            $record->status = tuning::STATUS_SUCCEEDED;
            $record->tunedproviderid = $tuned->get_id();
            $record->error = '';
        } else if ($base && $jobid !== '') {
            // The job may still be running: the bridge will check it.
            $record->status = tuning::STATUS_RUNNING;
            $record->error = '';
        }
        $record->id = $DB->insert_record(tuning::TABLE, $record);

        // Copy the training file.
        $fs = get_file_storage();
        $oldcontextid = $DB->get_field(self::CONFIG_TABLE, 'contextid', ['id' => $oldid]);
        $files = $fs->get_area_files($oldcontextid, 'vocabai_files', 'fileitemid', $oldid, 'id', false);
        if ($file = reset($files)) {
            $fs->create_file_from_storedfile([
                'contextid' => $contextid,
                'component' => 'tool_aibridge',
                'filearea' => tuning::FILEAREA,
                'itemid' => $record->id,
                'filepath' => '/',
                'userid' => $ownerid,
            ], $file);
        }
    }

    /**
     * Find a moved ChatGPT provider whose model can be tuned, the owner's own if there is one.
     *
     * @param int $ownerid
     * @return provider|null
     */
    protected function find_chatgpt_provider(int $ownerid): ?provider {
        $found = null;
        foreach ($this->providers as $providerid) {
            $provider = provider::get($providerid);
            if (!tuning::is_tunable($provider)) {
                continue;
            }
            if ($provider->get_record()->ownerid == $ownerid) {
                return $provider;
            }
            if (!$found) {
                $found = $provider;
            }
        }
        return $found;
    }

    /**
     * Move a format to the library.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_formats(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $body = self::get($settings, 'formattext');
        $name = self::get($settings, 'formatname');
        $template = template::save((object) [
            'type' => template::TYPE_FORMAT,
            'name' => ($name === '') ? 'Format ' . $oldid : $name,
            'description' => '',
            'component' => '',
            'ownerid' => $ownerid,
            'contextid' => $contextid,
            'parentid' => 0,
            'qformat' => self::detect_qformat($body),
            'body' => $body,
            'instructions' => '',
            'examples' => '',
            'parameters' => [],
            'formatlinks' => [],
            'enabled' => 1,
            'sortorder' => 0,
            'sharedfrom' => (int) self::get($settings, 'sharedfrom'),
            'shareduntil' => (int) self::get($settings, 'shareduntil'),
        ], $ownerid);
        $this->templates[$oldid] = $template->get_id();
        $this->formatnames[$oldid] = $template->get_shortname();
    }

    /**
     * Guess the output format of a format's text.
     *
     * @param string $text
     * @return string gift, xml or multianswer.
     */
    public static function detect_qformat(string $text): string {
        if (preg_match('/<(quiz|question)\b/i', $text)) {
            return 'xml';
        }
        if (preg_match('/\{\d*:(MULTICHOICE|MC|SHORTANSWER|SA|NUMERICAL|NM|MULTIRESPONSE|MR)/i', $text)) {
            return 'multianswer';
        }
        return 'gift';
    }

    /**
     * Move a prompt to the library.
     *
     * @param int $oldid
     * @param \stdClass $settings
     * @param int $contextid
     * @param int $ownerid
     */
    protected function move_prompts(int $oldid, \stdClass $settings, int $contextid, int $ownerid): void {
        $name = self::get($settings, 'promptname');
        $links = [];
        foreach (self::decode_qtypes(self::get($settings, 'promptqtypes')) as $qtype => $formatid) {
            if (isset($this->formatnames[$formatid])) {
                $links[$qtype] = $this->formatnames[$formatid];
            }
        }
        $template = template::save((object) [
            'type' => template::TYPE_PROMPT,
            'name' => ($name === '') ? 'Prompt ' . $oldid : $name,
            'description' => '',
            'component' => '',
            'ownerid' => $ownerid,
            'contextid' => $contextid,
            'parentid' => 0,
            'qformat' => '',
            'body' => self::get($settings, 'prompttext'),
            'instructions' => '',
            'examples' => '',
            'parameters' => self::get_prompt_parameters(),
            'formatlinks' => $links,
            'enabled' => 1,
            'sortorder' => 0,
            'sharedfrom' => (int) self::get($settings, 'sharedfrom'),
            'shareduntil' => (int) self::get($settings, 'shareduntil'),
        ], $ownerid);
        $this->templates[$oldid] = $template->get_id();
        $settings->ownerid = $ownerid;
        $this->prompts[$oldid] = $settings;
    }

    /**
     * Get the parameters of a moved prompt. The values that the old question
     * bank tool always used become their defaults.
     *
     * @return array
     */
    public static function get_prompt_parameters(): array {
        return [
            ['name' => 'target-language', 'default' => 'English', 'description' => 'The language that the students are learning.'],
            ['name' => 'student-nationality', 'default' => 'Japanese', 'description' => 'The nationality of the students.'],
            ['name' => 'school-level', 'default' => 'university', 'description' => 'The level of the school.'],
            ['name' => 'vocabulary-item', 'required' => true, 'description' => 'The word or phrase to ask about.'],
            ['name' => 'number-of-questions', 'default' => '4', 'description' => 'How many questions to make.'],
            ['name' => 'level-of-questions', 'default' => 'B1', 'description' => 'The CEFR level of the questions, e.g. A2.'],
        ];
    }

    /**
     * Read the question types saved with an old prompt.
     *
     * @param string $json e.g. {"multichoice": "12", "truefalse": "13"}
     * @return int[] question type => old format config id.
     */
    protected static function decode_qtypes(string $json): array {
        $qtypes = json_decode($json, true);
        if (!is_array($qtypes)) {
            return [];
        }
        $result = [];
        foreach ($qtypes as $qtype => $formatid) {
            if (is_string($qtype) && preg_match('/^[a-z]+$/', $qtype)) {
                $result[$qtype] = (int) $formatid;
            }
        }
        return $result;
    }

    /**
     * Get the new id of an old provider config.
     *
     * @param int $oldid
     * @param int $default for an unknown id.
     * @return int
     */
    protected function get_providerid(int $oldid, int $default): int {
        return isset($this->providers[$oldid]) ? $this->providers[$oldid] : $default;
    }

    /**
     * Point the question bank tool's log at the new providers and templates.
     */
    protected function update_logs(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists(self::LOG_TABLE)) {
            return;
        }
        $fields = 'id, textid, promptid, formatid, fileid, imageid, audioid, videoid';
        foreach ($DB->get_recordset(self::LOG_TABLE, null, 'id', $fields) as $log) {
            $new = (object) ['id' => $log->id];
            $new->textid = $this->get_providerid((int) $log->textid, aibridge::AUTOMATIC);
            if (isset($this->tunedproviders[$log->fileid])) {
                $new->textid = $this->tunedproviders[$log->fileid];
            }
            $new->promptid = isset($this->templates[$log->promptid]) ? $this->templates[$log->promptid] : 0;
            $new->formatid = isset($this->templates[$log->formatid]) ? $this->templates[$log->formatid] : 0;
            $new->fileid = 0;
            foreach (['imageid', 'audioid', 'videoid'] as $field) {
                $new->$field = $log->$field ? $this->get_providerid((int) $log->$field, aibridge::NONE) : aibridge::NONE;
            }
            $DB->update_record(self::LOG_TABLE, $new);
        }
    }

    /**
     * The choices saved with each old prompt become its owner's defaults for the new prompt.
     */
    protected function save_prompt_defaults(): void {
        foreach ($this->prompts as $oldid => $settings) {
            $defaults = [];
            $textid = (int) self::get($settings, 'prompttextid');
            $fileid = (int) self::get($settings, 'promptfileid');
            if (isset($this->tunedproviders[$fileid])) {
                $defaults['textassistant'] = $this->tunedproviders[$fileid];
            } else if ($textid) {
                $defaults['textassistant'] = $this->get_providerid($textid, aibridge::AUTOMATIC);
            }
            foreach (['image', 'audio', 'video'] as $type) {
                $id = (int) self::get($settings, "prompt{$type}id");
                if ($id) {
                    $defaults["{$type}assistant"] = $this->get_providerid($id, aibridge::NONE);
                }
            }
            $qtypes = [];
            foreach (self::decode_qtypes(self::get($settings, 'promptqtypes')) as $qtype => $formatid) {
                $qtypes[$qtype] = isset($this->templates[$formatid]) ? $this->templates[$formatid] : 0;
            }
            if ($qtypes) {
                $defaults['qtypes'] = $qtypes;
            }
            $names = [
                'promptqcount' => 'questioncount',
                'promptreview' => 'questionreview',
                'promptparentcatid' => 'parentcat[id]',
                'promptsubcattype' => 'subcattypes',
                'promptsubcatname' => 'subcatname',
                'prompttagtypes' => 'tagtypes',
                'prompttagnames' => 'tagnames',
            ];
            foreach ($names as $old => $new) {
                $value = self::get($settings, $old);
                if ($value !== '') {
                    $defaults[$new] = $value;
                }
            }
            if ($defaults) {
                aibridge::save_prompt_defaults($this->templates[$oldid], $defaults, (int) $settings->ownerid);
            }
        }
    }

    /**
     * Remove the files of the old subplugins.
     */
    protected function remove_old_files(): void {
        global $DB;
        $fs = get_file_storage();
        foreach (self::SUBPLUGINS as $name) {
            $contextids = $DB->get_fieldset_select('files', 'DISTINCT contextid', 'component = ?', ["vocabai_$name"]);
            foreach ($contextids as $contextid) {
                $fs->delete_area_files($contextid, "vocabai_$name");
            }
        }
    }

    /**
     * Remove the settings and the code of the old subplugins.
     */
    public static function remove_subplugins(): void {
        global $CFG;
        // Tests must not remove code.
        $removecode = !(defined('PHPUNIT_TEST') && PHPUNIT_TEST) && !(defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING);
        foreach (self::SUBPLUGINS as $name) {
            unset_all_config_for_plugin("vocabai_$name");
            $dir = "$CFG->dirroot/mod/vocab/ai/$name";
            if ($removecode && is_dir($dir) && is_writable($dir)) {
                remove_dir($dir);
            }
        }
        $dir = "$CFG->dirroot/mod/vocab/ai";
        if ($removecode && is_dir($dir) && is_writable($dir) && count(scandir($dir)) <= 3) {
            // Only ".", ".." and index.php are left.
            remove_dir($dir);
        }
        \core_plugin_manager::reset_caches();
    }
}
