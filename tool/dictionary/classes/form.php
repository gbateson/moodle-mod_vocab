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

/**
 * tool/dictionary/classes/form.php
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_dictionary;

use mod_vocab\dictionary;

/**
 * The form to fill in the dictionary for the words of an activity, with AI.
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {
    /** The level of definitions and samples, if the teacher has not chosen one. */
    const DEFAULT_LEVEL = 'A2';

    /** The most senses to ask for, if the teacher has not chosen a number. */
    const DEFAULT_SENSECOUNT = 3;

    /** The samples to ask for, for each sense, if the teacher has not chosen a number. */
    const DEFAULT_SAMPLECOUNT = 2;

    /** The largest number of senses or samples that can be asked for. */
    const MAX_COUNT = 5;

    /**
     * Define the fields of the form: the words, the AI provider and prompt, and the languages,
     * level and numbers for the entries. If the AI bridge is off, the AI policy has not been
     * accepted, or there are no words or prompts, a message is shown instead.
     */
    public function definition() {
        global $PAGE, $USER;

        $mform = $this->_form;
        $this->set_form_id($mform);
        $plugin = 'vocabtool_dictionary';

        $vocab = $this->get_vocab();
        $context = $vocab->context;
        $userid = (int) $USER->id;

        $words = $vocab->get_wordlist_words();
        if (empty($words)) {
            $this->add_message($mform, get_string('nowordsfound', $plugin));
            return;
        }
        if (!\mod_vocab\aibridge::is_enabled()) {
            $this->add_message($mform, get_string('aibridgedisabled', $plugin));
            return;
        }
        // The tasks run in the background, where the teacher cannot be asked
        // to accept the AI policy, so they must accept it here first.
        if (!\tool_aibridge\policy::has_accepted($userid)) {
            $url = \tool_aibridge\policy::get_url($context, $PAGE->url);
            $link = \html_writer::link($url, get_string('aipolicylink', $plugin));
            $this->add_message($mform, get_string('aipolicynotice', $plugin, $link));
            return;
        }
        $prompts = \mod_vocab\aibridge::get_dictionary_prompt_menu($context, $userid, 'entry');
        if (empty($prompts)) {
            $url = \mod_vocab\aibridge::get_url('library', $context);
            $this->add_message($mform, get_string('nopromptsfound', $plugin, $url->out()));
            return;
        }

        // The words, with the number of senses that each one has already.
        // Words with no senses, and no task waiting, are ticked.
        $mform->addElement('header', 'wordlist', get_string('wordlist', $plugin));
        $mform->setExpanded('wordlist', true);
        $counts = dictionary::count_senses(array_keys($words));
        $pending = tool::get_pending_wordids($vocab->id);
        $elements = [];
        foreach ($words as $id => $word) {
            if (in_array($id, $pending)) {
                $label = get_string('wordpending', $plugin, $word);
            } else {
                $label = get_string('wordsenses', $plugin, (object) ['word' => $word, 'count' => $counts[$id] ?? 0]);
            }
            $elements[] = $mform->createElement('advcheckbox', $id, '', $label);
            $mform->setDefault("words[$id]", (empty($counts[$id]) && !in_array($id, $pending)) ? 1 : 0);
        }
        $br = \html_writer::tag('span', '', ['class' => 'w-100']);
        $mform->addGroup($elements, 'words', get_string('selectedwords', $plugin), $br);
        $mform->addHelpButton('words', 'selectedwords', $plugin);

        // The AI provider and prompt.
        $mform->addElement('header', 'aisettings', get_string('aisettings', $plugin));
        $mform->setExpanded('aisettings', true);

        $providers = \mod_vocab\aibridge::get_text_menu($context, $userid);
        $mform->addElement('select', 'providerid', get_string('textassistant', $plugin), $providers);
        $mform->setType('providerid', PARAM_INT);
        $mform->setDefault('providerid', \mod_vocab\aibridge::AUTOMATIC);

        $mform->addElement('select', 'promptid', get_string('prompt', $plugin), $prompts);
        $mform->setType('promptid', PARAM_INT);
        $entry = \tool_aibridge\library\template::find(\mod_vocab\dictionary_ai::PROMPT_ENTRY);
        if ($entry && isset($prompts[$entry->get_id()])) {
            $mform->setDefault('promptid', $entry->get_id());
        }

        // The languages, level and numbers for the entries.
        $mform->addElement('header', 'entrysettings', get_string('entrysettings', $plugin));
        $mform->setExpanded('entrysettings', true);

        $wordlang = ($vocab->wordlang ?: 'en');
        $mform->addElement('static', 'wordlang', get_string('wordlang', 'mod_vocab'), dictionary::get_language_name($wordlang));
        $mform->addHelpButton('wordlang', 'wordlang', 'mod_vocab');

        $languages = get_string_manager()->get_list_of_languages();
        \core_collator::asort($languages);
        $mform->addElement('select', 'glosslang', get_string('glosslang', 'mod_vocab'), $languages);
        $mform->addHelpButton('glosslang', 'glosslang', 'mod_vocab');
        $mform->setType('glosslang', PARAM_ALPHANUMEXT);
        $mform->setDefault('glosslang', self::get_glosslang($vocab));

        $levels = dictionary::get_level_menu();
        $mform->addElement('select', 'level', get_string('level', $plugin), $levels);
        $mform->addHelpButton('level', 'level', $plugin);
        $mform->setType('level', PARAM_TEXT);
        $mform->setDefault('level', self::DEFAULT_LEVEL);

        $numbers = array_combine(range(1, self::MAX_COUNT), range(1, self::MAX_COUNT));
        $mform->addElement('select', 'sensecount', get_string('sensecount', $plugin), $numbers);
        $mform->addHelpButton('sensecount', 'sensecount', $plugin);
        $mform->setType('sensecount', PARAM_INT);
        $mform->setDefault('sensecount', self::DEFAULT_SENSECOUNT);

        $mform->addElement('select', 'samplecount', get_string('samplecount', $plugin), $numbers);
        $mform->setType('samplecount', PARAM_INT);
        $mform->setDefault('samplecount', self::DEFAULT_SAMPLECOUNT);

        $mform->addElement('advcheckbox', 'skipexisting', get_string('skipexisting', $plugin));
        $mform->addHelpButton('skipexisting', 'skipexisting', $plugin);
        $mform->setDefault('skipexisting', 1);

        $this->add_action_buttons(true, get_string('fillin', $plugin));
    }

    /**
     * Check that at least one word was chosen.
     *
     * @param array $data
     * @param array $files
     * @return array field name => error
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['words']) || !array_filter($data['words'])) {
            $errors['words'] = get_string('nowordsselected', 'vocabtool_dictionary');
        }
        return $errors;
    }

    /**
     * Queue a task for each chosen word of the activity.
     *
     * @param \stdClass $data from get_data()
     * @return int the number of tasks that were queued.
     */
    public function fill_dictionary(\stdClass $data): int {
        global $USER;
        $vocab = $this->get_vocab();
        $words = $vocab->get_wordlist_words();
        $count = 0;
        foreach (array_keys(array_filter((array) $data->words)) as $wordid) {
            if (!isset($words[$wordid])) {
                continue; // Not a word of this activity.
            }
            tool::queue_task([
                'userid' => (int) $USER->id,
                'vocabid' => (int) $vocab->id,
                'wordid' => (int) $wordid,
                'providerid' => (int) $data->providerid,
                'promptid' => (int) $data->promptid,
                'wordlang' => ($vocab->wordlang ?: 'en'),
                'glosslang' => $data->glosslang,
                'level' => $data->level,
                'sensecount' => max(1, min(self::MAX_COUNT, (int) $data->sensecount)),
                'samplecount' => max(1, min(self::MAX_COUNT, (int) $data->samplecount)),
                'skipexisting' => empty($data->skipexisting) ? 0 : 1,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Get the students' language of an activity: its setting, or else the site's language.
     *
     * @param \mod_vocab\activity $vocab
     * @return string
     */
    public static function get_glosslang($vocab): string {
        global $CFG;
        return ($vocab->glosslang ?: dictionary::get_base_langcode($CFG->lang));
    }

    /**
     * Add a message to the form, instead of its fields.
     *
     * @param \MoodleQuickForm $mform
     * @param string $message
     */
    protected function add_message($mform, string $message): void {
        $mform->addElement('html', \html_writer::div($message, 'alert alert-warning'));
    }

    /**
     * Get a table of the tasks of an activity: word, status, what was added, and any error.
     *
     * @param int $vocabid
     * @return string HTML, or "" if there are no tasks.
     */
    public static function get_log_table(int $vocabid): string {
        $logs = tool::get_logs($vocabid);
        if (empty($logs)) {
            return '';
        }
        $plugin = 'vocabtool_dictionary';
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable vocabtool_dictionary_log';
        $table->head = [
            get_string('word', $plugin),
            get_string('status', $plugin),
            get_string('added', $plugin),
            get_string('messages', $plugin),
            get_string('lastmodified'),
        ];
        foreach ($logs as $log) {
            $added = '';
            if ($counts = json_decode((string) $log->counts, true)) {
                $added = get_string('addedcounts', $plugin, (object) $counts);
            }
            $status = tool::TASKSTATUS_NAMES[(int) $log->status] ?? 'statusnotset';
            $table->data[] = [
                s($log->word),
                get_string($status, $plugin),
                $added,
                s($log->error),
                userdate($log->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            ];
        }
        return \html_writer::tag('h3', get_string('tasks', $plugin)) . \html_writer::table($table);
    }
}
