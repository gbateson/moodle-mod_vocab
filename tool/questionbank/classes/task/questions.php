<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Adhoc task to generate questions using an AI tool such as ChatGPT.
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */

namespace vocabtool_questionbank\task;

/**
 * This class handles an adhoc task to generate questions.
 *
 * @package     vocabtool_questionbank
 * @category    admin
 */
class questions extends \core\task\adhoc_task {

    /** @var object to represent the vocabtool_questionbank object */
    protected $tool = null;

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB, $USER;

        // Fetch "/lib/questionlib.php" which is required to check question category info.
        // See "question_categorylist" below.
        require_once("$CFG->dirroot/lib/questionlib.php");

        // Get the id of the user who setup this task.
        // This user has also been set to be the "current" user (i.e. $USER)
        // by the "cron_run_inner_adhoc_task()" function in "lib/cronlib".
        if (! $userid = $this->get_userid()) {
            $userid = $USER->id;
        }

        // Cache the tool class name (it's rather long).
        $toolclass = '\\vocabtool_questionbank\\tool';

        // Get the custom data and extract values.
        $data = $this->get_custom_data();

        // Locate the log record.
        if (isset($data->logid) && is_numeric($data->logid)) {
            $log = $toolclass::get_log($data->logid);
        } else {
            $log = null; // Shouldn't happen !!
        }

        if (empty($log)) {
            $logid = (isset($data->logid) ? $log->id : 'missing');
            return $this->report_error($log, 'invalidlogid', $logid);
        }

        // Extract settings from log.
        $vocabid = (int)$log->vocabid;
        $wordid = (int)$log->wordid;

        $qtype = $log->qtype;
        $qlevel = $log->qlevel;
        $qcount = $log->qcount;
        $qformat = $log->qformat;

        $textid = (int)$log->textid;
        $promptid = (int)$log->promptid;
        $formatid = (int)$log->formatid;
        $fileid = (int)$log->fileid;

        $imageid = (int)$log->imageid;
        $audioid = (int)$log->audioid;
        $videoid = (int)$log->videoid;

        $review = $log->review;

        $parentcatid = (int)$log->parentcatid;
        $subcattype = (int)$log->subcattype;
        $subcatname = $log->subcatname;

        $tagtypes = (int)$log->tagtypes;
        $tagnames = $log->tagnames;

        $maxtries = $log->maxtries;
        $tries = $log->tries;

        // We expect the status to be TASKSTATUS_QUEUED.
        // It could also be TASKSTATUS_AWAITING_IMPORT.
        // Anything else is unexpected.
        $status = $log->status;
        $review = $log->review;

        $error = $log->error;
        $prompt = $log->prompt;
        $results = $log->results;

        if ($questionids = $log->questionids) {
            $questionids = explode(',', $questionids);
            $questionids = array_map('trim', $questionids);
            $questionids = array_filter($questionids);
        } else {
            $questionids = [];
        }

        // The questions may be created or fetched later.
        $questions = null;

        // Intialize the vocab activity tool.
        $this->tool = $toolclass::create($vocabid);

        // Check log data is valid and consistent.
        if (! $this->tool->vocab->cm) {
            return $this->report_error($log, 'invalidvocabid', $vocabid);
        }
        if (! $this->tool->vocab->can_manage()) {
            return $this->report_error($log, 'invaliduserid', $userid);
        }
        if (! $word = $DB->get_field('vocab_words', 'word', ['id' => $wordid])) {
            return $this->report_error($log, 'invalidwordid', $wordid);
        }
        $params = ['vocabid' => $vocabid, 'wordid' => $wordid];
        if (! $DB->record_exists('vocab_word_instances', $params)) {
            return $this->report_error($log, 'missingwordinstance', $word);
        }

        // Cache the course id and context.
        $courseid = $this->tool->vocab->course->id;
        $coursecontext = \context_course::instance($courseid);

        // Ensure that this user can add questions in the target course.
        if (! has_capability('moodle/question:add', $coursecontext)) {
            $a = ['userid' => $userid, 'courseid' => $courseid];
            return $this->report_error($log, 'invalidteacherid', $a);
        }

        // Shortcut to form class name.
        $form = '\\vocabtool_questionbank\\form';

        // Check the essential elements (key, prompt, format) are available.
        $a = [];
        if (! $textconfig = $this->get_config($textid)) {
            $a[] = "textid ($textid)";
        }
        if (! $promptconfig = $this->get_config($promptid)) {
            $a[] = "promptid ($promptid)";
        }
        if (! $formatconfig = $this->get_config($formatid)) {
            $a[] = "formatid ($formatid)";
        }
        // If fileid is given, it must be valid.
        // If it is null, blank or zero, we just ignore it.
        if (! $fileconfig = $this->get_config($fileid)) {
            if ($fileid) {
                $a[] = "fileid ($fileid)";
            }
        }
        if ($a = implode(', ', $a)) {
            return $this->report_error($log, 'invalidtaskparameters', $a);
        }

        // The status should never be "fetching results" at this point.
        // Perhaps, there was an error in a previous run.
        // Anyway, we reset the status and try to continue.
        if ($status == $toolclass::TASKSTATUS_FETCHING_RESULTS) {
            if ($results && $review) {
                $status = $toolclass::TASKSTATUS_AWAITING_REVIEW;
            } else if (empty($results)) {
                // Try to create results again.
                $status = $toolclass::TASKSTATUS_QUEUED;
            } else {
                // Try to create questions.
                $status = $toolclass::RESUMED;
            }
            $this->tool->update_log($log->id, ['status' => $status]);
        }

        // If the status is any of the following, something
        // is wrong so we stop here.
        if ($status == $toolclass::TASKSTATUS_AWAITING_REVIEW ||
            $status == $toolclass::TASKSTATUS_COMPLETED ||
            $status == $toolclass::TASKSTATUS_CANCELLED ||
            $status == $toolclass::TASKSTATUS_FAILED) {
            return; // No return value is required.
        }

        // Initialize variable to represent instance of AI subplugin.
        // If required, it will be setup later.
        $ai = null;

        /*//////////////////////////////////
        // Check parameters (e.g. fileid).
        //////////////////////////////////*/

        if ($status == $toolclass::TASKSTATUS_NOTSET ||
            $status == $toolclass::TASKSTATUS_QUEUED ||
            $status == $toolclass::TASKSTATUS_CHECKING_PARAMS) {

            // Report start of checking params.
            mtrace($this->tool->get_string('checkingparams'), ' ');

            $status = $toolclass::TASKSTATUS_CHECKING_PARAMS;
            $this->tool->update_log($log->id, ['status' => $status]);

            // Setup the AI assistant if required.
            if ($ai === null) {
                $ai = $this->get_ai($textconfig);
            }

            if (! $ai->check_prompt_params($promptconfig)) {
                $a = ['id' => 'promptid', 'name' => $promptconfig->promptname];
                return $this->report_error($log, 'invalidprompt', $a);
            }
            if (! $ai->check_format_params($formatconfig)) {
                $a = ['id' => 'formatid', 'name' => $formatconfig->formatname];
                return $this->report_error($log, 'invalidformat', $a);
            }
            if (! $ai->check_file_params($fileconfig)) {
                $a = ['id' => $fileid, 'name' => $fileconfig->filedescription];
                return $this->report_error($log, 'invalidfile', $a);
            }

            if ($ai->reschedule_task($promptconfig, $formatconfig, $fileconfig)) {
                mtrace(' - task has been rescheduled and will run again later.');
                \core\task\manager::reschedule_or_queue_adhoc_task($this);
                return;
            }

            // Report successful completion of checking params.
            mtrace('['.get_string('ok').']');

            // Set log status to "Fetching results".
            $status = $toolclass::TASKSTATUS_FETCHING_RESULTS;
            $this->tool->update_log($log->id, ['status' => $status]);
        }

        /*//////////////////////////////////
        // Fetch results from AI assistant.
        //////////////////////////////////*/

        if ($status == $toolclass::TASKSTATUS_FETCHING_RESULTS) {

            // Report status of this adhoc task.
            $a = (object)['count' => $qcount, 'word' => $word];
            mtrace($this->tool->get_string('generatingquestions', $a), ' ');

            // Create prompt, if necessary.
            if (empty($prompt)) {
                $prompt = $this->get_prompt(
                    $promptconfig, $formatconfig,
                    $word, $qtype, $qlevel, $qcount, $qformat
                );
                $this->tool->update_log($log->id, ['prompt' => $prompt]);
            }

            // Setup the AI assistant if required.
            if ($ai === null) {
                $ai = $this->get_ai($textconfig);
                $ai->use_tuning_file($fileid > 0);
            }

            // Ensure sensible values for min/max tries.
            $maxtries = max(1, min(10, $maxtries));
            $mintries = max(0, min($maxtries, $log->tries));

            // Prompt the AI assistant until either we succeed
            // or we have tried the allowed number of times.
            for ($i = $mintries; $i < $maxtries; $i++) {

                // Loop may finish before $maxtries
                // if results are received from AI.

                // Update tries value in the database.
                $this->tool->update_log($log->id, [
                    'tries' => ($i + 1),
                ]);

                // Send the prompt to the AI assistant
                // and receive the response.
                $response = $ai->get_response($prompt);

                if ($results = $response->text) {

                    if ($log->review) {
                        $status = $toolclass::TASKSTATUS_AWAITING_REVIEW;
                    } else {
                        $status = $toolclass::TASKSTATUS_AWAITING_IMPORT;
                    }

                    // Store error, status and results.
                    $this->tool->update_log($log->id, [
                        'error' => $response->error,
                        'status' => $status,
                        'results' => $results,
                    ]);

                    // Report successful completion to cron job.
                    mtrace('['.get_string('ok').']');

                    // We have receieved a message from the AI assistant
                    // so we can leave the FOR loop now.
                    break;
                }

                // Prepare error message for cron output.
                if (! empty($response->error)) {
                    $error = $response->error;
                }
            }
        }

        // Get all question categories in the current course.
        $coursecategory = question_get_top_category($coursecontext->id);
        $categories = question_categorylist($coursecategory->id);

        // Ensure that the target question category is in the target course.
        if (! in_array($parentcatid, $categories)) {
            return $this->report_error($log, 'invalidquestioncategoryid', $parentcatid);
        }

        // Cache the parent category.
        $parentcategory = $DB->get_record('question_categories', ['id' => $parentcatid]);

        // Determine the human readable text for $qtype e.g. "Multiple choice".
        $qtypetext = \vocabtool_questionbank\form::get_question_type_text($qtype);

        $qtypeshort = $qtype.'short';
        if (get_string_manager()->string_exists($qtypeshort, $this->tool->plugin)) {
            $qtypeshort = $this->tool->get_string($qtypeshort); // E.g. "MC".
        } else {
            $qtypeshort = $qtypetext; // E.g. "Multiple choice".
        }

        // Determine the human readable text for $qlevel e.g. "CEFR A2 (Elementary)".
        $qleveltext = \vocabtool_questionbank\form::get_question_level_text($qlevel);

        // Format course name.
        $coursename = $this->tool->vocab->course->shortname;
        $coursename = format_string($coursename, true, ['context' => $coursecontext]);

        // Format section type e.g. "Topic" or "Week".
        $sectiontype = 'format_'.$this->tool->vocab->course->format;
        $sectiontype = get_string('sectionname', $sectiontype);

        // Format section name.
        $sectionid = $this->tool->vocab->cm->section;
        if ($modinfo = get_fast_modinfo($this->tool->vocab->course)) {
            $sectionname = $modinfo->get_section_info_by_id($sectionid)->name;
        } else {
            // Shouldn't happen - but we can get the section name directly from the $DB.
            $sectionname = $DB->get_field('course_sections', 'name', ['id' => $sectionid]);
        }
        if ($sectionname) {
            $sectionname = format_string($sectionname, true, ['context' => $coursecontext]);
            $sectionname = trim($sectionname);
        } else {
            $sectionname = '';
        }
        if ($sectionname == '') {
            // Create a default name for this section e.g. "Topic: 1" or "Week: 2".
            $sectionname = $sectiontype.get_string('labelsep', 'langconfig');
            $sectionname .= $DB->get_field('course_sections', 'section', ['id' => $sectionid]);
        }

        // Cache activity type (i.e. "Vocabulary activity").
        $activitytype = $this->tool->vocab->get_string('modulename');

        // Format vocab type and name.
        $activityname = $this->tool->vocab->name;
        $activityname = format_string($activityname, true, ['context' => $this->tool->vocab->context]);

        // Format prompt name (incl. head and tail).
        $prompthead = '';
        $prompttail = '';
        if ($promptname = $promptconfig->promptname) {
            $promptname = explode(' ', $promptname);
            $promptname = array_filter($promptname);
            $promptname = array_diff($promptname, ['Generate', 'questions']);
            $promptname = implode(' ', $promptname);
            if ($pos = strpos($promptname, ':')) {
                $prompthead = trim(substr($promptname, 0, $pos));
            }
            if ($pos = strrpos($promptname, ':')) {
                $prompttail = trim(substr($promptname, $pos + 1));
            }
        }

        $tags = [];
        $addmediatags = false;
        if ($tagtypes & $form::QTAG_AI) {
            $tags[] = $this->tool->get_string('ai_generated');
        }
        if ($tagtypes & $form::QTAG_PROMPTHEAD) {
            $tags[] = $prompthead;
        }
        if ($tagtypes & $form::QTAG_PROMPTTAIL) {
            $tags[] = $prompttail;
        }
        if ($tagtypes & $form::QTAG_MEDIATYPE) {
            $addmediatags = true;
        }
        if ($tagtypes & $form::QTAG_WORD) {
            $tags[] = $word;
        }
        if ($tagtypes & $form::QTAG_QUESTIONTYPE) {
            $tags[] = $qtypeshort;
        }
        if ($tagtypes & $form::QTAG_VOCABLEVEL) {
            $tags[] = $qlevel;
        }
        if ($tagtypes & $form::QTAG_CUSTOMTAGS) {
            $tagnames = explode(',', $tagnames);
            $tagnames = array_filter($tagnames);
            if (count($tagnames)) {
                $tags = array_merge($tags, $tagnames);
            }
        }

        // Setup arguments for the strings used to create question category names.
        $a = (object)[
            'customname' => $subcatname,
            'coursename' => $coursename,
            'sectiontype' => $sectiontype, // Not used.
            'sectionname' => $sectionname,
            'activitytype' => $activitytype, // Not used.
            'activityname' => $activityname,
            'word' => $word,
            'qtype' => $qtypetext, // E.g. "MC".
            'qlevel' => $qlevel, // E.g. "A2".
            'prompthead' => $prompthead,
            'prompttail' => $prompttail,
        ];

        // Ensure that we can get or create a suitable question category.
        if (! $category = $this->get_question_category($parentcategory, $subcattype, $a)) {
            return $this->report_error($log, 'missingquestioncategory', $word);
        }

        /*//////////////////////////////////
        // Create question categories
        // and generate questions.
        //////////////////////////////////*/

        if ($status == $toolclass::TASKSTATUS_AWAITING_IMPORT ||
            $status == $toolclass::TASKSTATUS_IMPORTING_RESULTS) {

            if (empty($results)) {
                return $this->report_error($log, 'emptyresults');
            }

            // Report status to cron job.
            $a = (object)['count' => $qcount, 'word' => $word];
            mtrace($this->tool->get_string('importingquestions', $a));

            // Update the log status.
            $status = $toolclass::TASKSTATUS_IMPORTING_RESULTS;
            $this->tool->update_log($log->id, ['status' => $status]);

            // At last, we can generate the questions from the results
            // and store them in a suitable question category.
            if ($questions = $this->parse_questions($log, $results, $qformat, $category->id, $tags)) {
                $questionids = array_merge($questionids, array_keys($questions));
                $status = $toolclass::TASKSTATUS_ADDING_MULTIMEDIA;
                $error = ''; // Unset any previous errors.
            } else {
                $status = $toolclass::TASKSTATUS_FAILED;
                $error = $this->tool->get_string('resultsnotparsed', $word);
                // Note: $questionids does not change.
            }

            $this->tool->update_log($log->id, [
                'questionids' => implode(', ', $questionids),
                'status' => $status,
                'error' => $error,
            ]);
        }

        /*//////////////////////////////////
        // Add multimedia to questions
        // i.e. images, audio, video.
        //////////////////////////////////*/

        if ($status == $toolclass::TASKSTATUS_ADDING_MULTIMEDIA) {
            if ($questions === null) {
                $questions = [];
                foreach ($questionids as $questionid) {
                    $questions[$questionid] = \question_bank::load_question($questionid);
                }
            }
            foreach ($questions as $questionid => $question) {
                // Create media (images, audio, video) and add appropriate tags.
                $mediatags = [];
                if (isset($question->context)) {
                    $context = $question->context;
                } else if (isset($question->contextid)) {
                    $context = \context::instance_by_id($question->contextid);
                } else if (isset($question->category)) {
                    $context = \context_coursecat::instance($question->category);
                } else {
                    $context = null; // Shouldn't happen !!
                }
                if ($context) {
                    $mediatags = $this->create_media($log, $question, $context, $mediatags);
                    if ($addmediatags && count($mediatags)) {
                        $alltags = \core_tag_tag::get_item_tags_array(
                            'core_question', 'question', $question->id
                        );
                        $alltags = array_values($alltags); // Remove tagids.
                        $alltags = array_merge($alltags, $mediatags);
                        $alltags = array_filter($alltags); // Remove blanks.
                        \core_tag_tag::set_item_tags(
                            'core_question', 'question', $question->id, $context, $alltags
                        );
                    }
                }
            }
            // The errors from "create_media(...)" go straight to mtrace.
            $status = $toolclass::TASKSTATUS_COMPLETED;
            $this->tool->update_log($log->id, [
                'status' => $status,
                'error' => $error,
            ]);
        }

        if ($error) {
            return $this->report_error($log, 'generatequestions', $error);
        }

        // Mark the adhoc task as completed.
        if ($this->get_lock()) {
            \core\task\manager::adhoc_task_complete($this);
        } else {
            // Mimic "adhoc_task_complete()" without locks.
            // We only use this during development.
            \core\task\logmanager::finalise_log();
            $this->set_timestarted();
            $this->set_hostname();
            $this->set_pid();

            // Delete the adhoc task record - it is finished.
            $DB->delete_records('task_adhoc', ['id' => $this->get_id()]);
        }
    }

    /**
     * Create a new object to represent an AI assistant.
     * The class name will be something like \vocabai_chatgpt\ai.
     *
     * @param object $textconfig settings for an AI subplugin.
     * @return object to represent an instance of the required AI subplugin
     */
    public function get_ai($textconfig) {
        $ai = '\\'.$textconfig->subplugin.'\\ai';
        $ai = new $ai($this->tool->vocab);
        $ai->set_config($textconfig);
        return $ai;
    }

    /**
     * get_config
     *
     * @param integer $configid
     * @return object record from the vocab_config table
     */
    protected function get_config($configid) {
        global $DB;

        // Sanity check on the $configid.
        if ($configid && is_numeric($configid)) {

            // Get all relevant contexts (activity, course, coursecat, site).
            $contexts = $this->tool->vocab->get_readable_contexts('', 'id');
            list($where, $params) = $DB->get_in_or_equal($contexts);

            // Retrieve all field names and values in the required config record.
            $select = 'vcs.id, vcs.name, vcs.value, vcs.configid, vc.subplugin, vc.contextid';
            $from = '{vocab_config_settings} vcs '.
                    'LEFT JOIN {vocab_config} vc ON vcs.configid = vc.id';
            $where = "vcs.configid = ? AND vc.contextid $where";
            $params = array_merge([$configid], $params);

            $sql = "SELECT $select FROM $from WHERE $where";
            if ($settings = $DB->get_records_sql($sql, $params)) {
                $config = new \stdClass();
                foreach ($settings as $setting) {
                    $config->configid = $setting->configid;
                    $config->subplugin = $setting->subplugin;
                    $config->contextid = $setting->contextid;
                    $config->{$setting->name} = $setting->value;
                }
                return $config;
            }
        }

        // The config id was missing or invalid.
        return null;
    }

    /**
     * get questions
     *
     * @param object $promptconfig
     * @param object $formatconfig
     * @param string $word
     * @param string $qtype
     * @param string $qlevel
     * @param integer $qcount
     * @param string $qformat
     * @return string a prompt to send to an AI assistant, such as ChatGPT.
     */
    protected function get_prompt($promptconfig, $formatconfig, $word, $qtype, $qlevel, $qcount, $qformat) {
        if (empty($promptconfig->prompttext)) {
            $prompt = $this->get_prompt_default();
        } else {
            $prompt = $promptconfig->prompttext;
        }
        if (empty($formatconfig->formattext)) {
            $format = $this->get_format_default($qformat, $qtype);
        } else {
            $format = $formatconfig->formattext;
        }

        // Replace all place holders with their respective values.
        $prompt = strtr($prompt, [
            '{{target-language}}' => 'English',
            '{{student-nationality}}' => 'Japanese',
            '{{school-level}}' => 'university',
            '{{vocabulary-item}}' => $word,
            '{{type-of-questions}}' => $qtype,
            '{{level-of-questions}}' => $qlevel,
            '{{number-of-questions}}' => $qcount,
            '{{question-format}}' => $qformat,
            '{{output-format}}' => $format,
        ]);

        // Standardize all whitespace in the prompt.
        // This can mess with the UTF-8 encoding, but
        // the convert method will fix any broken UTF-8.
        // Outside Moodle, we could also use mb_convert_encoding().
        $prompt = preg_replace('/\s+/u', ' ', $prompt);
        $prompt = \core_text::convert($prompt, 'UTF-8', 'UTF-8');

        return $prompt;
    }

    /**
     * get_prompt_default
     *
     * @return string a prompt suitable for generating questions.
     */
    protected function get_prompt_default() {
        return <<<'EOD'
I am a language teacher. I teach {{target-language}}
to {{student-nationality}} students at {{school-level}}.
Your role is an expert in generating online educational content.
Help me to create questions that I can import into a Moodle LMS.
Create {{number-of-questions}} {{type-of-questions}} questions
to check students understanding of the vocabulary item "{{vocabulary-item}}".
Use only CEFR level {{level-of-questions}} {{target-language}}.
Format the questions in {{question-format}} format
using the following output format template: {{output-format}}
EOD;
    }

    /**
     * Get a format for the required question format and type.
     *
     * @param string $qformat the question format e.g. "gift" or "xml".
     * @param string $qtype the question type e.g. "multichoice" or "truefalse".
     * @return string the GIFT format, including place holders marked with {{...}}
     */
    protected function get_format($qformat, $qtype) {
        $method = "get_{$qformat}_format_{$qtype}";
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return "No $qformat format found for $qtype questions.";
    }

    /**
     * get_gift_format_multichoice
     *
     * @return string a GIFT format for a multichoice question
     */
    protected function get_gift_format_multichoice() {
        return <<<'EOD'
::question-name::question-text {
    =correct-answer #Explanation of why the answer is correct.
    ~wrong-answer-1 #Explanation of why the answer is wrong and hint about the correct answer.
    ~wrong-answer-2 #Explanation of why the answer is wrong and hint about the correct answer.
    ~wrong-answer-3 #Explanation of why the answer is wrong and hint about the correct answer.
}
EOD;
    }

    /**
     * get_gift_format_truefalse
     *
     * @return string a GIFT format for a truefalse question
     */
    protected function get_gift_format_truefalse() {
        return <<<'EOD'
::question-name::statement-that-is-true{TRUE #Explanation of why this answer is TRUE}
::question-name::statement-that-is-false{FALSE #Explanation of why this answer is FALSE}
EOD;
    }

    /**
     * get_gift_format_match
     *
     * @return string a GIFT format for a match question
     */
    protected function get_gift_format_match() {
        return <<<'EOD'
Match the following words with their corresponding meanings. {
   =target-word -> definition-of-target-word
   =similar-word-1 -> definition-of-similar-word-1
   =similar-word-2 -> definition-of-similar-word-2
   =similar-word-3 -> definition-of-similar-word-3
}
EOD;
    }

    /**
     * get_question_category
     *
     * @param object $category the parent category
     * @param string $subcattype
     * @param object $a arguments to get strings used as question category names
     * @return int the category into which these new questions will be put
     */
    protected function get_question_category($category, $subcattype, $a) {

        // Cache the DB table name.
        $table = 'question_categories';

        // Shortcut to form class name.
        $form = '\\vocabtool_questionbank\\form';

        // When the cattype is "none", all the questions
        // go into the given parent category.
        if ($subcattype == $form::SUBCAT_NONE) {
            return $category;
        }

        $types = [
            $form::SUBCAT_CUSTOMNAME => 'customname',
            $form::SUBCAT_SECTIONNAME => 'sectionname',
            $form::SUBCAT_ACTIVITYNAME => 'activityname',
            $form::SUBCAT_WORD => 'word',
            $form::SUBCAT_QUESTIONTYPE => 'questiontype',
            $form::SUBCAT_VOCABLEVEL => 'vocablevel',
            $form::SUBCAT_PROMPTHEAD => 'prompthead',
            $form::SUBCAT_PROMPTTAIL => 'prompttail',
        ];

        foreach ($types as $type => $strname) {
            if ($subcattype & $type) {
                if ($type == $form::SUBCAT_CUSTOMNAME) {
                    $subcatnames = explode(',', $a->$strname);
                    $subcatnames = array_map('trim', $subcatnames);
                    $subcatnames = array_filter($subcatnames);
                    foreach ($subcatnames as $subcatname) {
                        $a->$strname = $subcatname;
                        $category = $this->get_question_subcategory(
                            $table, $strname, $a, $category
                        );
                    }
                    $a->$strname = implode(', ', $subcatnames);
                } else {
                    // A single category.
                    $category = $this->get_question_subcategory(
                        $table, $strname, $a, $category
                    );
                }
            } else if (isset($a->$strname)) {
                // This subcat is not required, so
                // remove it from further consideration.
                $a->$strname = '';
            } else if ($strname == 'questiontype') {
                $a->qtype = '';
            } else if ($strname == 'vocablevel') {
                $a->qlevel = '';
            }
        }

        return $category;
    }

    /**
     * get_question_subcategory
     *
     * @param string $table the table to be searched in the Moodle $DB
     * @param string $strname string name used to create the subcategory name/info
     * @param object $a arguments to use to make the subcategory name/info
     * @param object $parentcategory the parent category
     * @param string $catname (optional, default="") the name of the subcategory
     * @param string $catinfo (optional, default="") the info of the subcategory
     * @return object $subcategory record from the $table in the Moodle DB.
     */
    protected function get_question_subcategory($table, $strname, $a, $parentcategory, $catname='', $catinfo='') {
        global $DB;

        // Sanity check on incoming data.
        if (empty($parentcategory)) {
            return null;
        }

        if (empty($catname)) {
            $catname = $this->tool->get_string("catname_$strname", $a);
            $catname = str_replace('()', '', $catname);
            $catname = preg_replace('/\s+/', ' ', $catname);
        }
        if (empty($catinfo)) {
            $catinfo = $this->tool->get_string("catinfo_$strname", $a);
            $catinfo = str_replace('()', '', $catinfo);
            $catinfo = preg_replace('/\s+/', ' ', $catname);
        }

        // First we check to see if the subcategory already exists.
        // Although we only expect at most one matching subcategory,
        // we allow for multiple matches, and choose the first one.
        $params = [
            'parent' => $parentcategory->id,
            'name' => $catname,
        ];
        if ($subcategory = $DB->get_records($table, $params)) {
            $subcategory = reset($subcategory);
            return $subcategory;
        }

        // Set sortorder of new subcategory to one more
        // than the highest currently in the parent category.
        $params = ['parent' => $parentcategory->id];
        if ($sortorder = $DB->get_field($table, 'MAX(sortorder)', $params)) {
            $sortorder ++;
        } else {
            $sortorder = 1;
        }

        // No matching subcategory found, so create a new one.
        $subcategory = (object)[
            'name' => $catname,
            'info' => $catinfo,
            'parent' => $parentcategory->id,
            'contextid' => $parentcategory->contextid,
            'stamp' => make_unique_id_code(),
            'sortorder' => $sortorder,
        ];
        if ($subcategory->id = $DB->insert_record($table, $subcategory)) {
            return $subcategory;
        }

        // Could not find or create a subcategory - shouldn't happen !!
        return null;
    }

    /**
     * Parses questions from a given text input based on the specified question format.
     *
     * This method dynamically loads the required question format class, processes the text input,
     * and returns an array of parsed questions ready for inclusion in the Moodle question bank.
     *
     * @param object $log An object containing log data, including question type details.
     * @param string $text The raw text containing the questions to be parsed.
     * @param string $qformat The question format identifier (e.g., "gift", "xml", "multianswer").
     * @param int $categoryid The ID of the question category in which the parsed questions should be stored.
     * @param array $tags An array of tags to be assigned to the parsed questions.     *
     * @return array|false An array of parsed questions if successful, or false if no questions were found.
     */
    protected function parse_questions($log, $text, $qformat, $categoryid, $tags) {
        global $CFG, $DB, $USER;

        require_once("$CFG->dirroot/lib//questionlib.php");
        require_once("$CFG->dirroot/question/format.php");

        // Get the main file for the requested question format. We expect
        // only "gift", "xml" or "multianswer", but anything is possible.
        $filepath = "$CFG->dirroot/question/format/$qformat/format.php";
        if (! file_exists($filepath)) {
            return null;
        }
        require_once($filepath);

        // Ensure the class of the required qformat exists - it should !!
        // E.g. "qformat_gift" class in "question/format/gift/format.php".
        $classname = "qformat_$qformat";
        if (! class_exists($classname)) {
            return null;
        }

        // Get the context for this question category.
        // We need it when we add tags.
        $context = \context::instance_by_id($DB->get_field(
            'question_categories', 'contextid', ['id' => $categoryid]
        ));

        // Get an instance of the required qformat class.
        // It is this instance that will actually parse the $text
        // and create the questions in the question bank.
        $format = new $classname();

        switch ($qformat) {
            case 'gift':
                $text = $this->fix_gift($log->qtype, $text);
                $questions = $this->parse_questions_gift(
                    $log, $context, $format, $text, $categoryid, $tags
                );
                break;
            case 'multianswer': // Embedded questions (a.k.a cloze).
                $questions = $this->parse_questions_multianswer(
                    $log, $context, $format, $text, $categoryid, $tags
                );
                break;
            case 'xml':
                $questions = $this->parse_questions_xml(
                    $log, $context, $format, $text, $categoryid, $tags
                );
                break;
        }

        // Return either the array of questions, or FALSE if there are no questions.
        return (empty($questions) ? false : $questions);
    }

    /**
     * Fixes a GIFT-formatted question by correcting missing syntax elements.
     *
     * The AI-generated content may sometimes omit crucial symbols like "=" and "~"
     * in GIFT-formatted questions. This method attempts to fix basic syntax issues
     * based on the provided question type.
     *
     * @param string $qtype The question type identifier (e.g., "multichoice", "shortanswer", "match").
     * @param string $text The raw GIFT-formatted question content that needs correction.
     * @return string The corrected GIFT-formatted question text.
     */
    public function fix_gift($qtype, $text) {

        // Escape all equal signs, "=" (important for
        // MC, SA, matching, missing word, numerical).
        $text = preg_replace('/(\w+)="([^"]*)"/u', '$1\\="$2"', $text);

        // Perform fixes specific to each question type.
        switch ($qtype) {
            case 'multichoice':
                $text = $this->fix_gift_multichoice($text);
                break;
            case 'shortanswer':
                $text = $this->fix_gift_shortanswer($text);
                break;
            case 'match':
                $text = $this->fix_gift_match($text);
                break;
        }
        return $text;
    }

    /**
     * Fix a GIFT formatted multichoice (MC) question.
     *
     * @param string $text containing gift formatted MC question.
     * @return string
     */
    public function fix_gift_multichoice($text) {
        if (preg_match('/^(.*?){(.*)}(.*?)$/us', $text, $match, PREG_OFFSET_CAPTURE)) {
            list($match, $start) = $match[2];
            $length = strlen($match);
            $match = ltrim($match);
            $char = substr($match, 0, 1);
            if ($char != '=' && $char != '~') {
                $text = substr_replace($text, ' ='.$match, $start, $length);
            }
        }
        return $text;
    }

    /**
     * Fix a GIFT formatted shortanswer (SA) question.
     *
     * @param string $text containing gift formatted SA question.
     * @return string
     */
    public function fix_gift_shortanswer($text) {
        return $text;
    }

    /**
     * Fix a GIFT formatted match question.
     *
     * @param string $text containing gift formatted match question.
     * @return string
     */
    public function fix_gift_match($text) {
        return $text;
    }

    /**
     * Parses GIFT-formatted questions and saves them to the question bank.
     *
     * This method extracts individual questions from a GIFT-formatted text block,
     * assigns metadata, and stores them in the Moodle question bank under the specified category.
     * It also adds relevant tags and media to each parsed question.
     *
     * @param object $log An object containing log data, including question type details.
     * @param \context $context The Moodle context for the specified question category.
     * @param object $format The question format parser instance responsible for processing the GIFT format.
     * @param string $rawtext The raw GIFT-formatted text containing multiple questions.
     * @param int $categoryid The ID of the question category in which the parsed questions should be stored.
     * @param array $tags An array of tags to be assigned to the parsed questions.
     * @return array The parsed questions are stored in the Moodle database.
     */
    protected function parse_questions_gift($log, $context, $format, $rawtext, $categoryid, $tags) {
        global $USER;
        $questions = [];

        // Parse the text into individual questions.
        // Match $1 contains the question name.
        // Match $2 contains the question text.
        // Match $3 contains the answer details (including weighting and feedback).
        $search = '/:: *((?:.|\s)*?) *:: *((?:.|\s)*?) *\{ *((?:.|\s)*?) *\}/us';
        if (preg_match_all($search, $rawtext, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $lines = explode("\n", $matches[0][$i]);
                if ($question = $format->readquestion($lines)) {
                    $name = $matches[1][$i];
                    $text = $matches[2][$i];
                    $this->add_question(
                        $questions, $question, $name, $text,
                        $log, $context, $categoryid, $tags
                    );
                }
            }
        }

        return $questions;
    }

    /**
     * Parses multianswer-formatted questions and saves them to the question bank.
     *
     * This method extracts individual questions from a multianswer-formatted text block,
     * assigns metadata, and stores them in the Moodle question bank under the specified category.
     * It also adds relevant tags and media to each parsed question.
     *
     * @param object $log An object containing log data, including question type details.
     * @param \context $context The Moodle context for the specified question category.
     * @param object $format The question format parser instance responsible for processing the multianswer text.
     * @param string $rawtext The raw multianswer text containing one or more multianswer questions.
     * @param int $categoryid The ID of the question category in which the parsed questions should be stored.
     * @param array $tags An array of tags to be assigned to the parsed questions.
     * @return void The parsed questions are stored in the Moodle database.
     */
    protected function parse_questions_multianswer($log, $context, $format, $rawtext, $categoryid, $tags) {
        global $USER;

        $rawquestions = [];
        if ($rawtext = trim($rawtext)) {
            $search = '/###\s*(.+?)\n([\s\S]*?)(?=###|\z)/u';
            if (preg_match_all($search, $rawtext, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $name = trim($matches[1][$i]);
                    $text = trim($matches[2][$i]);
                    if ($name == 'Explanation') {
                        continue; // Ignore the helpful explanation from AI.
                    }
                    $rawquestions[] = (object)['name' => $name, 'text' => $text];
                }
            } else if (preg_match('/^ *<[^>]*>/us', $rawtext, $match)) {
                // Escape any regex characters and convert to search string.
                // Use a lookahead to split *before* each tag.
                $search = '/(?='.preg_quote($match[0], '/').')/u';
                $rawquestions = preg_split($search, $rawtext, -1, PREG_SPLIT_NO_EMPTY);
                $rawquestions = array_map('trim', $rawquestions);
                $rawquestions = array_map(function ($text) {
                    return (object)['name' => '', 'text' => $text];
                }, $rawquestions);
            } else {
                // No question separator found, so we assume
                // $rawtext contains a single, unnamed question.
                $rawquestions[] = (object)['name' => '', 'text' => $rawtext];
            }
        }

        $questions = [];
        foreach ($rawquestions as $rawquestion) {
            $name = trim($rawquestion->name);
            $text = trim($rawquestion->text);

            // We should remove excess white space in each embedded question
            // because it can cause problems for readquestions,
            // particulary space before the first "=" or "~".

            // Regular expression to match an embedded question.
            $search = '/(\{)\s*(\d+)\s*(:)\s*([A-Z_]+)\s*(:)\s*(.*?)\s*(\})/us';
            // Matches the following items:
            // $1: leading "{"
            // $2: grade (as a positive integer)
            // $3: leading ":"
            // $4: question type (e.g. "MULTICHOICE_VS" or "MCVS")
            // $5: trailing ":"
            // $6: answers
            // $7: trailing "}".
            if (preg_match_all($search, $text, $matches, PREG_OFFSET_CAPTURE)) {
                // We go backwards from the last match to the first, so
                // that the positions of earlier matches are not affected.
                $imax = count($matches[0]) - 1;
                for ($i = $imax; $i >= 0; $i--) {
                    $match = $matches[0][$i][0];
                    $start = $matches[0][$i][1];
                    $length = strlen($match);
                    $replace = '';
                    for ($ii = 1; $ii < count($matches); $ii++) {
                        $match = $matches[$ii][$i][0];
                        if ($ii == 6) {
                            // Regular expression to match an unescaped "=" followed
                            // by a double-quoted string e.g. class="multilang".
                            $search = '/(?<!\\\\)=("[^"]*?")/us';
                            $match = preg_replace($search, '\=$1', $match);
                        }
                        $replace .= trim($match);
                    }
                    $text = substr_replace($text, $replace, $start, $length);
                }
            }

            $lines = explode("\n", $text);
            if ($question = $format->readquestions($lines)) {
                // For compatability with other import formats,
                // the readquestions method of the multianswer
                // format returns an array of questions.
                // However, it only ever contains one question.
                foreach (array_keys($question) as $q) {
                    // Clean $question[$q]->name and $question[$q]->options.
                    // Note, $name will be ignored as a result.
                    $this->clean_question_name($question[$q]);
                    $this->clean_question_options($question[$q]);
                    $this->add_question(
                        $questions, $question[$q], $name, $text,
                        $log, $context, $categoryid, $tags, true
                    );
                }
            }
        }
        return $questions;
    }

    /**
     * Cleans and shortens the question text to generate a simplified name for the question.
     *
     * Similar to a combination of "create_default_question_name()" and "clean_question_name()"
     * in "question/format.php", but additionally removes media prompt tags (e.g., [[AUDIO ... ]]).
     * Sample result: A=woman (customer calling with complaint) B=male (staff on a customer help ...
     *
     * @param object $question A question object containing a 'questiontext' property.
     * @return string A cleaned version of the question text with tags and excess whitespace removed.
     */
    protected function clean_question_name($question) {
        $search = '/(\s+|<.*?>|(?:\[\[[A-Z]+)|(\w+=".*?")|(?:\]\]))+/us';
        $name = preg_replace($search, ' ', $question->questiontext);
        $question->name = shorten_text(trim($name), 80);
    }

    /**
     * Cleans escaped equal signs in question subfields for answers and feedback.
     *
     * This method searches the answer and feedback fields of each subquestion
     * within a question's options and replaces escaped equal signs (\=) followed
     * by a quoted string (e.g., class\="multilang") with a normal equal sign (=).
     *
     * @param object $question A question object containing an 'options' property
     *                         with a 'questions' array of subquestion objects.
     * @return void This method modifies the $question object directly.
     */
    protected function clean_question_options($question) {

        // Sanity checks on the $question object.
        if (empty($question)) {
            return false;
        }
        if (empty($question->options)) {
            return false;
        }
        if (empty($question->options->questions)) {
            return false;
        }

        // These fields in each wrapped question will be searched
        // for escaped equal signs.
        $fields = ['answer', 'feedback'];

        // Regular expression to match an escaped "=" followed
        // by a double-quoted string e.g. class\="multilang".
        $search = '/\\\\=("[^"]*?")/us';

        // Clean all the "wrapped" questions. A "wrapped" question is an
        // embedded question, or subquestion, of the main cloze question.
        // It represents a "gap" in the cloze question.
        foreach ($question->options->questions as $w => $wrapped) {
            foreach ($fields as $field) {
                if (empty($wrapped->$field)) {
                    continue; // Shouldn't happen !!
                }
                foreach ($wrapped->$field as $i => $value) {
                    if (is_array($value)) {
                        $value['text'] = preg_replace($search, '=$1', $value['text']);
                    } else if (is_string($value)) {
                        $value = preg_replace($search, '=$1', $value);
                    }
                    $wrapped->{$field}[$i] = $value;
                }
            }
            $question->options->questions[$w] = $wrapped;
        }
    }

    /**
     * Adds a parsed question to the Moodle question bank.
     *
     * This method stores a parsed question in the Moodle question bank under the specified category.
     * It also adds relevant tags and media to each parsed question.
     *
     * @param array $questions An array (passed by reference) of recently added question objects.
     * @param object $question A question object derived from the parsed raw text.
     * @param string $name The question name.
     * @param string $text The question text.
     * @param object $log An object containing log data, including question type details.
     * @param \context $context The Moodle context for the specified question category.
     * @param int $categoryid The ID of the question category in which the parsed questions should be stored.
     * @param array $tags An array of tags to be assigned to the parsed questions.
     * @param boolean $saveoptions TRUE if question options are to be saved; otherwise FALSE.
     * @return void The parsed questions are stored in the Moodle database.
     */
    protected function add_question(&$questions, $question, $name, $text, $log, $context, $categoryid, $tags, $saveoptions=false) {
        global $USER;

        if (empty($question->name)) {
            $question->name = clean_param($name, PARAM_TEXT);
        }

        if (empty($question->questiontext)) {
            $question->questiontext = clean_param($text, PARAM_CLEANHTML);
        }

        // Ensure questiontext is an array that mimics editor fields.
        if (is_scalar($question->questiontext)) {
            $question->questiontext = [
                'text' => $question->questiontext,
            ];
        }

        // Ensure format of questiontext is set correctly.
        $text = $question->questiontext['text'];
        $textformat = $this->detect_text_format($text);
        $question->questiontext['format'] = $textformat;

        // We need to take a clone of the question in order to preserve
        // the "options", which can be wiped out by "save_question".
        // In particular, "multianswer" format requires this step.
        $clone = clone($question);

        $question->category = $categoryid;
        $question->createdby = $question->modifiedby = $USER->id;
        $question->timecreated = $question->timemodified = time();

        $qtype = \question_bank::get_qtype($question->qtype);
        $question = $qtype->save_question($question, $question);

        // Save options, if required (e.g. multianswer needs this).
        if ($saveoptions) {
            $question->context = $context;
            $question->options = $clone->options;
            $qtype->save_question_options($question);
        }

        // Add the new question to the $questions array.
        $questions[$question->id] = $question;

        // Add Moodle tags for this question.
        // e.g., "AI", "newword", "MC", "TOEIC-200".
        // Note that all tags are usually displayed in lowercase
        // even though the "rawname" field stores the uppercase.
        \core_tag_tag::set_item_tags(
            'core_question', 'question', $question->id, $context, $tags
        );
    }

    /**
     * Detects whether a given text is HTML, Markdown, or plain text.
     *
     * @param string $text The input text to analyze.
     * @return string Returns suitable FORMAT_XXX value.
     */
    public function detect_text_format($text) {
        $text = trim($text);

        // Check for HTML (presence of opening or closing tags).
        if (preg_match('/<\/?[a-zA-Z][\s\S]*>/us', $text)) {
            return FORMAT_HTML;
        }

        // Check for Markdown (common symbols).
        if (preg_match('/(^#{1,6}\s|\*\*|__|[*_\[\]!])/um', $text)) {
            return FORMAT_MARKDOWN;
        }

        // Default: Plain Text.
        return FORMAT_PLAIN;
        // Could also use FORMAT_MOODLE as default.
    }

    /**
     * Create media (images, audio and video) for the given question.
     *
     * @param object $log record form the "questionbank_log" table.
     * @param object $question that has just been imported and created.
     * @param object $context in which question was created.
     * @param array $tags Moodle tags to be added for each question.
     * @return void, but may add media file and updated question.
     */
    public function create_media($log, $question, $context, $tags) {

        // Map each media tag to the configid of the AI subplugin
        // that will create the media content for that tag.
        static $mediatags = null;
        if ($mediatags === null) {
            $mediatags = [
                'IMAGE' => $log->imageid,
                'AUDIO' => $log->audioid,
                'VIDEO' => $log->videoid,
            ];
            // Remove tags that are not required.
            $mediatags = array_filter($mediatags);
        }

        // If there's nothing to do, we can finish early.
        if (empty($mediatags)) {
            return $tags;
        }

        // Cache the question type (e.g. multichoice).
        $qtype = $question->qtype;
        if (is_object($qtype)) {
            $qtype = $qtype->name();
        }

        // Initialize the cache of fields and fileareas for this $qtype.
        // Usually we are only generating questions for a single $qtype.
        static $tables = [];
        if (empty($tables[$qtype])) {
            $tables[$qtype] = self::get_tables($question);
        }

        // Initialize the $filerecord that will be used
        // to store media file in Moodle's file repository.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'question',
            'filearea'  => '', // Set later.
            'itemid'    => 0, // Set later.
            'filepath'  => '/', // Always this value.
            'filename'  => '', // Set later.
        ];

        $moretags = [];
        foreach ($tables[$qtype] as $table => $fields) {
            foreach ($fields as $field => $filearea) {
                $filerecord['filearea'] = $filearea;
                $this->create_media_for_field(
                    $mediatags, $table, $field,
                    $filerecord, $question->id, $moretags
                );
            }
        }

        $moretags = array_keys($moretags);
        foreach ($moretags as $i => $tagname) {
            // Use messages defined in "lang/en/message.php".
            $strname = 'messagecontent'.strtolower($tagname);
            $moretags[$i] = get_string($strname, 'message');
        }

        return array_merge($tags, $moretags);
    }

    /**
     * Get tables for the specified $qtype.
     *
     * @param object $question
     * @return array [$table => [$fields]]
     */
    public static function get_tables($question) {

        // Initialize the array of tables.
        $tables = [];

        // Cache the question type.
        $qtype = $question->qtype;
        if (is_object($qtype)) {
            $qtype = $qtype->name();
        }

        // Add the question table, if required.
        if (property_exists($question, 'questiontext')) {
            $table = 'question';
            $fields = [
                'questiontext' => 'questiontext',
                'generalfeedback' => 'generalfeedback',
            ];
            $tables[$table] = $fields;
        }

        // Add the answer table, if required.
        if (property_exists($question, 'answer')) {
            $table = 'question_answers';
            $fields = [
                'answer' => 'answer',
                'feedback' => 'answerfeedback',
            ];
            $tables[$table] = $fields;
        }

        // Add the hint table, if required.
        if (property_exists($question, 'hint')) {
            $table = 'question_hints';
            $fields = ['hint' => 'hint'];
            $tables[$table] = $fields;
        }

        // Add the combined feedback table, if any.
        // Usually this is "qtype_{$qtype}_options".
        if ($table = self::get_feedback_table($qtype)) {
            list($table, $fields) = $table;
            $tables[$table] = $fields;
        }

        // Add the subquestions table, if any.
        // This is only used by "qtype_match", in which case
        // the table name is "qtype_{$qtype}_subquestions".
        if ($table = self::get_subquestions_table($qtype)) {
            list($table, $fields) = $table;
            $tables[$table] = $fields;
        }

        return $tables;
    }

    /**
     * Get the name of the DB table that contains the
     * combined feedback fields (e.g. correctfeedback).
     *
     * The following SQL can be used to select
     * the DB tables that we are interested in:
     *
     * SELECT TABLE_NAME, COLUMN_NAME
     * FROM information_schema.COLUMNS
     * WHERE TABLE_SCHEMA = 'mdl_401'
     *   AND (TABLE_NAME LIKE '%qtype_%' OR TABLE_NAME LIKE '%question_%')
     *   AND (COLUMN_NAME = 'correctfeedback' OR COLUMN_NAME REGEXP '^(question|answer)text$')
     * ORDER BY TABLE_NAME, COLUMN_NAME.
     *
     * @param string $qtype
     * @return array [$table, [$fields]] if feedback table exists, otherwise NULL.
     */
    public static function get_feedback_table($qtype) {
        global $DB;

        // We will use the DB manager to determine which tables exist.
        $dbman = $DB->get_manager();

        // These are the fields (and fileareas) we are looking for.
        $fields = [
            'correctfeedback' => 'correctfeedback',
            'incorrectfeedback' => 'incorrectfeedback',
            'partiallycorrectfeedback' => 'partiallycorrectfeedback',
        ];
        $field = 'correctfeedback';

        $table = "qtype_{$qtype}_options";
        if ($dbman->table_exists($table)) {
            if ($dbman->field_exists($table, $field)) {
                // These question types:
                // qtype_essayautograde_options
                // qtype_match_options
                // qtype_multichoice_options
                // qtype_ordering_options
                // qtype_randomsamatch_options
                // qtype_speakautograde_options.
                return [$table, $fields];
            }
            return null;
        }

        $table = "qtype_{$qtype}";
        if ($dbman->table_exists($table)) {
            if ($dbman->field_exists($table, $field)) {
                // These question types:
                // qtype_ddimageortext
                // qtype_ddmarker.
                return [$table, $fields];
            }
            return null;
        }

        $table = "question_{$qtype}";
        if ($dbman->table_exists($table)) {
            if ($dbman->field_exists($table, $field)) {
                // These question types:
                // question_ddwtos
                // question_gapselect
                // question_order.
                return [$table, $fields];
            }
            return null;
        }

        $table = "question_{$qtype}_options";
        if ($dbman->table_exists($table)) {
            if ($dbman->field_exists($table, $field)) {
                // These question types:
                // question_calculated_options.
                return [$table, $fields];
            }
            return null;
        }

        return null;
    }

    /**
     * Get the name of the DB table that contains subquestions
     * such as the left/right items in a qtype_match question.
     *
     * @param string $qtype
     * @return array [$table, [$fields]] if feedback table exists, otherwise NULL.
     */
    public static function get_subquestions_table($qtype) {
        global $DB;
        $dbman = $DB->get_manager();

        $table = "qtype_{$qtype}_subquestions";
        if ($dbman->table_exists($table)) {

            $fields = [];

            $field = 'questiontext';
            $filearea = 'questiontext';
            if ($dbman->field_exists($table, $field)) {
                $fields[$field] = $filearea;
            }

            $field = 'answertext';
            $filearea = 'answer';
            if ($dbman->field_exists($table, $field)) {
                $fields[$field] = $filearea;
            }

            if (empty($fields)) {
                return null;
            }

            // The following question types;
            // qtype_match_subquestions.
            return [$table, $fields];
        }

        return null;
    }

    /**
     * Create media for the given field in the given question.
     *
     * @param object $mediatags
     * @param object $table
     * @param object $field
     * @param array $filerecord
     * @param integer $questionid
     * @param array $moretags (passed by reference) Moodle tags for media used in this question.
     */
    public function create_media_for_field($mediatags, $table, $field, $filerecord, $questionid, &$moretags) {
        global $DB;

        // Determine the SQL search values.
        switch ($table) {
            case 'question':
                $select = 'id = ? AND '.$DB->sql_like($field, '?');
                $params = [$questionid, '%[[%]]%'];
                break;
            case 'question_answers':
                $select = 'question = ? AND '.$DB->sql_like($field, '?');
                $params = [$questionid, '%[[%]]%'];
                break;
            default: // Question hints and qtype options table.
                $select = 'questionid = ? AND '.$DB->sql_like($field, '?');
                $params = [$questionid, '%[[%]]%'];
        }
        if ($records = $DB->get_records_select($table, $select, $params)) {

            $index = 0;
            foreach ($records as $id => $record) {
                if ($table == 'question_answers' || $table == 'question_hints') {
                    $filerecord['itemid'] = $id;
                } else {
                    $filerecord['itemid'] = $questionid;
                }
                $filearea = $filerecord['filearea'];
                $fileindex = str_pad(++$index, 2, '0', STR_PAD_LEFT);
                $filerecord['filename'] = "{$filearea}-{$fileindex}";

                // Create the media for this field in this record.
                $this->create_media_for_record(
                    $mediatags, $table, $record, $field, $filerecord, $questionid, $moretags
                );
            }
        }
    }

    /**
     * Create media for the given field in the given question.
     *
     * @param array $mediatags
     * @param string $table
     * @param object $record
     * @param string $field
     * @param array $filerecord
     * @param integer $questionid
     * @param array $moretags (passed by reference) Moodle tags for media used in the current question.
     */
    public function create_media_for_record($mediatags, $table, $record, $field, $filerecord, $questionid, &$moretags) {
        global $DB;

        // Sanity check on incoming parameters.
        if (empty($record) || empty($field) || empty($record->$field)) {
            return false;
        }

        $filenamebase = pathinfo($filerecord['filename'], PATHINFO_FILENAME);
        $filetype = pathinfo($filerecord['filename'], PATHINFO_EXTENSION);
        $filerecord['filename'] = '';

        // Set up search string for the media tags.
        static $search = null;
        if ($search === null) {
            $search = (object)[
                // Search string to extract media tags:
                // $1: type of media (IMAGE, AUDIO or VIDEO)
                // $2: tag attributes and AI prompt.
                'tags' => '/\[\[('.implode('|', array_keys($mediatags)).')(.*?)\]\]/u',
                // Search string to extract attributes of media tags:
                // $1: name of attribute (alt, width, height, class)
                // $2: value of attribute.
                'attributes' => '/(\w+) *= *"(.*?)"/u',
                // Search string to identify dialog between two or more speakers:
                // $1: Dialog info
                // $2: Dialog lines.
                'dialog' => '/^(.*?)\s*([A-Z]\s*:\s*.*)$/ius',
                // Search string to extract info about speakers in a dialog:
                // $1: Speaker letter
                // $2: Speaker gender
                // $3: Speaker details (optional).
                'info' => '/([A-Z])\s*=\s*(\w+)(?:\s*\(([^)]+)\))?/ius',
                // Search string to extract a line of a dialog:
                // $1: Speaker letter
                // $2: Speaker line.
                'line' => '/([A-Z])\s*:\s*(.*?)(?=\s+[A-Z]\s*:|$)/ius',
            ];
        }

        /* ==========================
        Sample dialog (conveniently split into lines, but it ain't necessarily so)
        [[AUDIO
            A=woman (customer calling with complaint)
            B=male (staff on a customer help line)
            A: Hello. I'm calling about a coffee machine I purchased from your website.
               It has stopped working, even though I haven't had it for very long.
               I expected it to last much longer than this.
            B: Oh, I'm sorry to hear that. Our warranty covers products for up to a year. Do you know when you bought it?
            A: I've had it for over a year so the warranty has probably just expired. This is so disappointing.
            B: Well, I'll tell you what we can do. Although we can't replace it, since you're a valued customer,
               I can offer you a coupon for 40% off your next purchase.
        ]]
        ========================== */

        // Initialize the counters used to generate unique filenames.
        $index = (object)array_combine(
            array_keys($mediatags),
            array_fill(0, count($mediatags), 0)
        );

        // Extract all the media tags.
        if (preg_match_all($search->tags, $record->$field, $tags, PREG_OFFSET_CAPTURE)) {

            // We go backwards from the last tag to the first, so that as each tag is
            // replaced with a player, the positions of earlier tags are not affected.
            $tmax = count($tags[0]) - 1;
            for ($t = $tmax; $t >= 0; $t--) {

                list($tag, $tagstart) = $tags[0][$t];
                $taglength = strlen($tag);
                $tagname = $tags[1][$t][0];
                $tagprompt = $tags[2][$t][0];

                // Get the configid of the AI subplugin that will create the media file.
                $configid = $mediatags[$tagname];

                // Initilize the array of allowable tag parameters.
                $tagparams = [
                    'alt' => '',
                    'class' => '',
                    'width' => '',
                    'height' => '',
                    'filename' => '',
                ];

                // Initialize the array of tag specific attributes.
                $tagattributes = [];

                // Transfer tag attributes (e.g. width, height) from $tagprompt to $tagparams.
                if (preg_match_all($search->attributes, $tagprompt, $attributes, PREG_OFFSET_CAPTURE)) {

                    $amax = count($attributes[0]) - 1;
                    for ($a = $amax; $a >= 0; $a--) {

                        list($attribute, $astart) = $attributes[0][$a];
                        $alength = strlen($attribute);
                        $aname = $attributes[1][$a][0];
                        $avalue = $attributes[2][$a][0];

                        // Check $aname is recognized. Otherwise, add it to the attributes.
                        if (array_key_exists($aname, $tagparams)) {
                            $tagparams[$aname] = $avalue;
                        } else {
                            $tagattributes[$aname] = $avalue;
                        }
                        $tagprompt = substr_replace($tagprompt, '', $astart, $alength);
                    }
                }

                // Trim prompt and standardize space and tabs to a single space.
                $tagprompt = trim(preg_replace('/[ \t]+/', ' ', $tagprompt));

                // Increment the count of the number of media files
                // of this tagname created in this $record->$field.
                $index->$tagname = ($index->$tagname + 1);

                // Set filename.
                if ($filename = $tagparams['filename']) {
                    $filename = clean_param($filename, PARAM_FILE);
                    $filename = preg_replace('/[ \._]+/u', '_', $filename);
                    $filename = trim($filename, ' -._');
                }
                if ($filename == '') {
                    // E.g. questiontext-01.
                    $filename = $filenamebase;
                }

                // Set a new unique and meaningful suffix for the media file name.
                $suffix = [
                    strtolower($tagname),
                    str_pad($index->$tagname, 2, '0', STR_PAD_LEFT),
                ];

                // Set the filetype of the image file.
                if ($filetype == '') {
                    switch ($tagname) {
                        case 'IMAGE':
                            $filetype = 'png';
                            break;
                        case 'AUDIO':
                            $filetype = 'mp3';
                            break;
                        case 'VIDEO':
                            $filetype = 'mp4';
                            break;
                        default:
                            $filetype = ''; // Shouldn't happen !!
                    }
                }

                $lines = [];
                if ($tagname == 'AUDIO') {

                    if (preg_match($search->dialog, $tagprompt, $matches)) {

                        $dialoginfo = trim($matches[1]);
                        $dialoglines = trim($matches[2]);

                        $genders = [];
                        if (preg_match_all($search->info, $dialoginfo, $matches)) {
                            foreach (array_keys($matches[0]) as $i) {
                                $speaker = trim($matches[1][$i]);
                                $speaker = strtoupper($speaker);
                                $gender = trim($matches[2][$i]);
                                $gender = $this->get_valid_gender($gender);
                                $genders[$speaker] = $gender;
                            }
                        }

                        if (preg_match_all($search->line, $dialoglines, $matches)) {
                            foreach (array_keys($matches[0]) as $i) {
                                $speaker = trim($matches[1][$i]);
                                $speaker = strtoupper($speaker);
                                $line = trim($matches[2][$i]);
                                if (! array_key_exists($speaker, $genders)) {
                                    $genders[$speaker] = 'random'; // Shouldn't happen!!
                                }
                                // Store the line as [speaker, gender, prompt].
                                $lines[] = [$speaker, $genders[$speaker], $line];
                            }
                        }
                    }
                }

                // Set the root of the media filename e.g. questiontext-01-image-01.
                $mediafilename = \mod_vocab\activity::modify_filename($filename, '', $suffix, $filetype);
                $filerecord['filename'] = $mediafilename;

                if (count($lines)) {
                    $file = $this->create_dialog_file($configid, $tagname, $lines, $filerecord, $questionid);
                } else {
                    // Send the prompt to one of the AI subplugins to generate the media file.
                    // For image files, several variations maybe created, but only the
                    // first one will be returned by the "create_media_file()" method.
                    $file = $this->create_media_file($configid, $tagname, $tagprompt, $filerecord, $questionid);
                }

                if (is_object($file)) {

                    // Determine if the media player (for audio and video) will be used.
                    $usemediaplugin = true;
                    if (in_array('nomediaplugin', explode(' ', $tagparams['class']))) {
                        // The media tag has specified a class of 'nomediaplugin'
                        // and so will be ignored by the mediaplugin filter.
                        // E.g. [[AUDIO class="nomediaplugin" ...]].
                        $usemediaplugin = false;
                    }

                    // Remove empty tag params, and define value for the "src".
                    $tagparams = array_filter($tagparams);
                    $src = '@@PLUGINFILE@@/'.$file->get_filename();

                    if ($tagname == 'IMAGE') {
                        if (empty($tagparams['style'])) {
                            // Add styles to make the image responsive and on its own line.
                            $tagparams['style'] = 'display: block; height: auto; max-height: 100%; max-width: 100%;';
                        }
                        $tagparams['src'] = $src;
                        $html = \html_writer::empty_tag('img', $tagparams);
                    } else {
                        // AUDIO and VIDEO ... and anything else.

                        // Create the SOURCE tag.
                        $html = \html_writer::empty_tag('source', ['src' => $src]);

                        // Add subtitles file for audio not played in the mediaplayer.
                        if ($tagname == 'AUDIO' && $usemediaplugin === false) {
                            $html .= $this->add_html_for_subtitles($file); // Add <track> tag.
                        }

                        // Create the main AUDIO/VIDEO tag.
                        $tagparams['controls'] = 'true';
                        $tagparams['controlslist'] = 'nodownload';
                        $html = \html_writer::tag(strtolower($tagname), $html.$src, $tagparams);

                        // Add wrapper to prevent mediaplayer filter from positioning this media tag
                        // in the center of the page caused by the .mediaplugin>div { margin: auto; }.
                        if ($tagname == 'AUDIO' && $usemediaplugin === true) {
                            $params = [
                                'class' => 'rounded',
                                'style' => 'background: black; '.
                                           'display: inline-block; '.
                                           'width: min(400px, 85vw);',
                            ];
                            $html = \html_writer::tag('div', $html, $params);
                        }
                    }

                    // Update the field value in the Moodle DB.
                    $record->$field = substr_replace($record->$field, $html, $tagstart, $taglength);
                    $DB->set_field($table, $field, $record->$field, ['id' => $record->id]);

                    // Ensure that its "format" field is set to HTML,
                    // so that user can easily access embedded files.
                    $fieldformat = $field.'format';
                    if (isset($record->$fieldformat) && $record->$fieldformat != FORMAT_HTML) {
                        $record->$fieldformat = FORMAT_HTML;
                        $DB->set_field($table, $fieldformat, $record->$fieldformat, ['id' => $record->id]);
                    }

                    // Update the Moodle tags for this question.
                    $moretags[strtolower($tagname)] = true;

                } else if (is_string($file)) {
                    // Report error message?
                    mtrace($file);
                } else {
                    mtrace('Oops, "create_media_file()" returned an unrecognizeable result.');
                }
            }
        }
    }

    /**
     * Generates an HTML <track> tag for subtitles if a corresponding VTT file exists.
     *
     * This method checks whether a subtitle file (with the same base filename as the given media file,
     * but with a ".vtt" extension) exists in the same filearea. If so, it returns a <track> tag to
     * include in the <audio> or <video> element.
     *
     * @param stored_file $file The main media file (e.g., MP3 or MP4) stored in Moodle's file API.
     * @param string $srclang The language code for the subtitle track (default: 'en').
     * @param string $label The label to display in the player for this subtitle track (default: 'English').
     *
     * @return string The HTML <track> tag as a string, or an empty string if the subtitle file does not exist.
     */
    protected function add_html_for_subtitles($file, $srclang='en', $label='English') {

        $filename = $file->get_filename();
        $filename = \mod_vocab\activity::modify_filename($filename, '', '', 'vtt');

        $fs = get_file_storage();
        $exists = $fs->file_exists(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filename(),
            $filename
        );

        if ($exists) {
            $params = [
                'src' => '@@PLUGINFILE@@/'.$filename,
                'kind' => 'subtitles',
                'srclang' => $srclang,
                'label' => $label,
                'default' => 'default',
            ];
            return \html_writer::empty_tag('track', $params);
        } else {
            return '';
        }
    }

    /**
     * Determine if the given string holds "man", "woman", "male", "female"
     * or a localized version of one of those strings.
     *
     * @param string $gender to idenitify as "male" or "female"
     * @return string valid version of gender string, "male", "female" or "random"
     */
    protected function get_valid_gender($gender) {

        static $male = null;
        static $female = null;

        if ($male === null) {
            $male = ['man', $this->tool->get_string('man'),
                     'male', $this->tool->get_string('male')];
            $male = array_map('strtolower', $male);
            $male = array_unique($male);
        }

        if ($female === null) {
            $female = ['woman', $this->tool->get_string('woman'),
                       'female', $this->tool->get_string('female')];
            $female = array_map('strtolower', $female);
            $female = array_unique($female);
        }

        $gender = trim($gender);

        if (in_array($gender, $male)) {
            return 'male';
        }
        if (in_array($gender, $female)) {
            return 'female';
        }

        return 'random'; // Will be assigned later.
    }

    /**
     * Creates a media file (e.g. AUDIO) that represents a dialog between two or more speakers.
     *
     * This method uses speaker information and dialog lines to generate a media file,
     * typically via an AI subplugin identified by the given config ID.
     *
     * @param int $configid The ID of the AI subplugin configuration to use.
     * @param string $mediatype The type of media to create (e.g. 'AUDIO').
     * @param array $lines An array of dialog lines, where each item is a numeric array:
     *                     [0] = speaker ID (e.g., 'A'),
     *                     [1] = speaker gender or voice style (e.g., 'female'),
     *                     [2] = the line of dialog (e.g., 'Hello!').
     *                     Example: [['A', 'female', 'Hello!'], ['B', 'male', 'Good morning.']]
     * @param array $filerecord An array of file record information (e.g. filename, filepath, contextid).
     * @param int $questionid The ID of the Moodle question to which this media file belongs.
     *
     * @return stored_file|string|null The generated media file object on success,
     *                                 a string error message on failure,
     *                                 or null if the media could not be created.
     */
    public function create_dialog_file($configid, $mediatype, $lines, $filerecord, $questionid) {

        // Cache the file storage object.
        $fs = get_file_storage();

        // Cache the base filename.
        // E.g questiontext-01-audio-01.mp3.
        $filename = $filerecord['filename'];

        $files = [];
        foreach ($lines as $i => $line) {

            // Extract the speaker, gender and prompt for this line.
            list($speaker, $gender, $text) = $line;

            // Ensure media file name is unique by adding unique suffix.
            // E.g. questiontext-01-audio-01-01-A.mp3.
            $suffix = [str_pad($i + 1, 2, '0', STR_PAD_LEFT), $speaker];
            $filerecord['filename'] = \mod_vocab\activity::modify_filename($filename, '', $suffix);

            // Create an audio file for this line of the dialog.
            $files[] = $this->create_media_file(
                $configid, $mediatype, $text, $filerecord, $questionid, $speaker, $gender
            );
        }

        // Concatenate content of mp3 files. Note that we
        // strip any mp3 tags on the individual media files
        // and then add leading ID3v2 tag and trailing ID2v1 tag.

        $starttime = 0;
        $captions = [];
        $captions[] = 'WEBVTT';
        $captions[] = ''; // Blank line after header.
        $title = '';

        foreach ($files as $i => $file) {
            // Sample line: A, female, Hello!.
            list($speaker, $gender, $text) = $lines[$i];

            if (is_object($file)) {
                $content = $this->strip_mp3_tags($file->get_content());
                $duration = $this->get_mp3_duration_cbr($content);
                $endtime = $starttime + $duration;

                // Format milliseconds into hh:mm:ss.mmm.
                $starttext = $this->format_webvtt_time($starttime);
                $endtext = $this->format_webvtt_time($endtime);
                $starttime = $endtime;

                $captions[] = ($i + 1); // Cue identifier.
                $captions[] = $starttext.' --> '.$endtext;
                $captions[] = $speaker.': '.$text;
                $captions[] = ''; // Force exta newline.

                // Add the text from this line to the MP3 title string.
                $title .= ($title ? ' ' : '').$text;

                $files[$i] = $content;
                $file->delete();
            } else {
                $files[$i] = '';
            }
        }

        // Generate file for concatenated audio and return it.
        if ($captions = implode("\n", $captions)) {
            $filerecord['filename'] = \mod_vocab\activity::modify_filename($filename, '', '', 'vtt');
            $this->add_or_update_file_from_string($fs, $filerecord, $captions);
        }

        // Generate file for concatenated audio and return it.
        if ($files = implode('', $files)) {
            $title = shorten_text(strip_tags($title));
            $artist = $this->tool->get_string('pluginname');
            $files = $this->mp3_id3v2_tag($title, $artist).
                     $files. // The concatenated mp3 content.
                     $this->mp3_id3v1_tag($title, $artist);
            $filerecord['filename'] = $filename;
            return $this->add_or_update_file_from_string($fs, $filerecord, $files);
        }

        // Something went wrong, so abort generation of this audio file.
        return false;
    }

    /**
     * Creates or replaces a stored file in the Moodle file system from a string.
     *
     * If a file already exists with the same context, component, filearea, itemid,
     * filepath, and filename, it will be deleted before creating the new file.
     *
     * @param file_storage $fs The Moodle file storage object (from get_file_storage()).
     * @param array $filerecord An array of file record information, including:
     *   - contextid: The context ID.
     *   - component: The component name (e.g., 'mod_vocab').
     *   - filearea: The file area (e.g., 'media').
     *   - itemid: The item ID (e.g., question ID).
     *   - filepath: The file path (e.g., '/').
     *   - filename: The file name (e.g., 'questiontext.01.audio.01.mp3').
     * @param string $string The content to write into the file.
     *
     * @return stored_file|string The stored_file object on success, or an error message string on failure.
     */
    protected function add_or_update_file_from_string($fs, $filerecord, $string) {
        $pathnamehash = $this->get_pathnamehash($fs, $filerecord);
        if ($fs->file_exists_by_hash($pathnamehash)) {
            $fs->get_file_by_hash($pathnamehash)->delete();
        }
        if ($file = $fs->create_file_from_string($filerecord, $string)) {
            return $file;
        }
        // Oops, something went wrong and the file could not be created.
        $a = (object)[
            'subplugin' => $filearea['component'],
            'filearea' => $filerecord['filearea'],
            'itemid' => $filerecord['itemid'],
        ];
        return $this->tool->get_string('medianotcreated', $a).': '.$filerecord['filename'];
    }

    /**
     * Generates a pathname hash for a file based on its file record.
     *
     * This is a wrapper for file_storage::get_pathname_hash(), which creates a unique
     * identifier for locating a file in Moodle's file storage system.
     *
     * @param file_storage $fs The Moodle file storage object (usually from get_file_storage()).
     * @param array $filerecord An associative array containing the required keys:
     *   - contextid: The context ID for the file.
     *   - component: The component name (e.g., 'mod_vocab').
     *   - filearea: The file area name (e.g., 'questiontext').
     *   - itemid: The item ID associated with the file.
     *   - filepath: The file path (e.g., '/').
     *   - filename: The filename (e.g., 'audio.mp3').
     *
     * @return string The generated pathname hash.
     */
    protected function get_pathnamehash($fs, $filerecord) {
        return $fs->get_pathname_hash(
            $filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
            $filerecord['itemid'], $filerecord['filepath'], $filerecord['filename']
        );
    }

    /**
     * Generates a media file (image, audio, or video) using the specified AI configuration and prompt.
     *
     * This method retrieves the appropriate AI generator based on the media type and configuration,
     * then delegates the media creation to that generator.
     *
     * @param int $configid The ID of the AI subplugin configuration to use.
     * @param string $mediatype The type of media to generate (e.g., 'IMAGE', 'AUDIO', 'VIDEO').
     * @param string $prompt The content or script to send to the media generator.
     * @param array $filerecord File metadata used to store the generated file in Moodle.
     * @param int $questionid The ID of the Moodle question associated with the media.
     * @param string $speaker (Optional) The speaker label (used for audio prompts).
     * @param string $gender (Optional) The speaker's gender or voice style (used for audio prompts).
     *
     * @return stored_file|null The generated media file object, or null on failure.
     */
    public function create_media_file($configid, $mediatype, $prompt, $filerecord, $questionid, $speaker='', $gender='') {

        static $configs = [];
        if (! array_key_exists($configid, $configs)) {
            $configs[$configid] = $this->get_config($configid);
        }
        if (! ($config = $configs[$configid])) {
            return null; // Invalid configid - shouldn't happen !!
        }

        static $creators = [];
        if (! array_key_exists($mediatype, $creators)) {
            $creators[$mediatype] = $this->get_ai($config);
        }
        if (! ($creator = $creators[$mediatype])) {
            return null; // Invalid mediatype - shouldn't happen !!
        }

        return $creator->get_media_file($prompt, $filerecord, $questionid, $speaker, $gender);
    }

    /**
     * Estimates the duration of a CBR MP3 file by parsing the first MPEG audio frame.
     *
     * This method assumes the MP3 file uses constant bitrate (CBR) encoding. It searches for
     * the first valid MPEG frame header, extracts the bitrate and sampling rate, and calculates
     * the total duration based on the file size.
     *
     * @param string $mp3 The raw binary content of the MP3 file (without leading/trailing ID3 tags).
     *
     * @return float|null The estimated duration in seconds, or null if a valid frame header could not be found.
     */
    protected function get_mp3_duration_cbr($mp3) {

        // Find first frame sync byte.
        $offset = strpos($mp3, "\xFF");
        while ($offset !== false && (ord($mp3[$offset + 1]) & 0xE0) !== 0xE0) {
            $offset = strpos($mp3, "\xFF", $offset + 1);
        }

        // If no frame was found, we abort.
        if ($offset === false) {
            return null;
        }

        $header = substr($mp3, $offset, 4);
        $bytes = unpack('N', $header)[1];

        $bitrateindex = ($bytes >> 12) & 0xF;
        $samplingindex = ($bytes >> 10) & 0x3;

        $bitratetable = [
            // For MPEG-1 Layer III.
            null, 32, 40, 48, 56, 64,
            80, 96, 112, 128, 160, 192,
            224, 256, 320, null,
        ];
        $samplingratetable = [44100, 48000, 32000, null];

        $bitrate = ($bitratetable[$bitrateindex] ?? null);
        $samplerate = ($samplingratetable[$samplingindex] ?? null);

        if ($bitrate && $samplerate) {
            $filesize = strlen($mp3); // Bytes.
            $duration = ($filesize * 8) / ($bitrate * 1000); // Seconds.
            return (float)$duration;
        }

        // Bit rate and/or sample rate could not be determined.
        return null;
    }

    /**
     * Converts a duration in milliseconds to WebVTT timestamp format (hh:mm:ss.mmm).
     *
     * @param float|int $time Duration in milliseconds.
     * @return string Formatted time (e.g. 00:01:23.456)
     */
    protected function format_webvtt_time($time) {
        // Cast the time to an integer in order to prevent error:
        // Implicit conversion from float 12.345 to int loses precision.
        $time = (int)$time;
        $h = floor($time / 3600000);
        $m = floor(($time % 3600000) / 60000);
        $s = floor(($time % 60000) / 1000);
        $time = $time % 1000;
        return sprintf('%02d:%02d:%02d.%03d', $h, $m, $s, $time);
    }

    /**
     * Strips the ID3v1 and ID3v2 tags from the MP3 data.
     * Removes the ID3v1 tag, if present, at the end of the MP3.
     * Removes the ID3v2 tag, if present, at the start of the MP3.
     *
     * @param string $mp3 The raw binary content of an MP3 file.
     * @return string The MP3 data with ID3 tags removed.
     */
    protected function strip_mp3_tags($mp3) {
        // Remove ID3v1 tag (128 bytes at end).
        if (substr($mp3, -128, 3) === "TAG") {
            $mp3 = substr($mp3, 0, -128);
        }

        // Remove ID3v2 tag (size from bytes 6–10, synchsafe int).
        if (substr($mp3, 0, 3) === "ID3") {
            $sizebytes = substr($mp3, 6, 4);
            $size = (ord($sizebytes[0]) & 0x7F) << 21 |
                    (ord($sizebytes[1]) & 0x7F) << 14 |
                    (ord($sizebytes[2]) & 0x7F) << 7 |
                    (ord($sizebytes[3]) & 0x7F);
            $mp3 = substr($mp3, 10 + $size);
        }

        return $mp3;
    }

    /**
     * Generates an ID3v1 tag suitable for appending to the end of an MP3 file.
     *
     * @param string $title The title of the track.
     * @param string $artist The artist of the track.
     * @param string $album The album name.
     * @param int $year The year of the track.
     * @param string $comment The comment associated with the track.
     * @param int $genre The genre of the track (0–255). Use 255 for undefined.
     * @return bool True on success, false on failure.
     */
    protected function mp3_id3v1_tag($title = '', $artist = '',
                                     $album = '', $year = 0,
                                     $comment = '', $genre = 255) {
        if ($year == 0) {
            $year = date('Y');
        }
        $tag = 'TAG';
        $tag .= str_pad(substr($title, 0, 30), 30, "\0");
        $tag .= str_pad(substr($artist, 0, 30), 30, "\0");
        $tag .= str_pad(substr($album, 0, 30), 30, "\0");
        $tag .= str_pad(substr("$year", 0, 4), 4, "\0");
        $tag .= str_pad(substr($comment, 0, 30), 30, "\0");
        $tag .= chr($genre); // 1 byte
        return $tag;
    }

    /**
     * Generates a basic ID3v2.3 tag with title and artist
     * suitable for prepending to the beginning of an MP3 file.
     *
     * @param string $title The title of the track.
     * @param string $artist The artist name.
     * @return string ID3v2 tag.
     */
    protected function mp3_id3v2_tag($title = '', $artist = '') {
        $frames = '';
        if ($title) {
            $frames .= $this->build_mp3_frame('TIT2', $title);
        }
        if ($artist) {
            $frames .= $this->build_mp3_frame('TPE1', $artist);
        }

        $tagsize = strlen($frames);
        $sizebytes = pack('C4',
            ($tagsize >> 21) & 0x7F,
            ($tagsize >> 14) & 0x7F,
            ($tagsize >> 7) & 0x7F,
            ($tagsize) & 0x7F
        );

        $header = 'ID3'."\x03\x00"."\x00".$sizebytes;

        return $header.$frames;
    }

    /**
     * Builds a single ID3v2.3 text frame for an MP3 tag.
     *
     * @param string $id   The 4-character frame ID (e.g., 'TIT2', 'TPE1').
     * @param string $text The text content to encode in the frame.
     * @return string The binary frame data.
     */
    protected function build_mp3_frame($id, $text) {
        $encoded = "\x00".$text; // 0x00 = ISO-8859-1 encoding.
        $size = strlen($encoded);
        return $id.pack('N', $size)."\x00\x00".$encoded;
    }

    /**
     * report_error
     *
     * @param object $log the log record associated with this adhoc task
     * @param string $error name of an error in the in lang pack
     * @param string $a arguments required (if any) by $error string
     * @return bool false
     */
    public function report_error($log, $error, $a=null) {

        // Fetch the full error message.
        $error = $this->tool->get_string("error_$error", $a);

        // Format the label.
        $label = '['.$this->tool->plugin.'] '.get_string('error');
        $label .= get_string('labelsep', 'langconfig');

        // Print the label with the error.
        mtrace($label.$error);

        // Set log status to "Failed" and report $error.
        if ($log) {
            $toolclass = get_class($this->tool);
            $this->tool->update_log($log->id, [
                'error' => $error,
                'status' => $toolclass::TASKSTATUS_FAILED,
            ]);
        }

        return false;
    }
}
