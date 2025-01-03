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
                // Create a default name for this section e.g. "Topic 1" or "Week 2".
                $sectionname = $sectiontype.get_string('labelsep', 'langconfig');
                $sectionname .= $DB->get_field('course_sections', 'section', ['id' => $sectionid]);
            }

            // Cache activity type (i.e. "Vocabulary activity").
            $activitytype = $this->tool->vocab->get_string('modulename');

            // Format vocab type and name.
            $activityname = $this->tool->vocab->name;
            $activityname = $this->tool->vocab->name;
            $activityname = format_string($activityname, true, ['context' => $this->tool->vocab->context]);

            // Setup arguments for the strings used to create question category names.
            $a = (object)[
                'customname' => $subcatname,
                'coursename' => $coursename,
                'sectiontype' => $sectiontype,
                'sectionname' => $sectionname,
                'activitytype' => $activitytype,
                'activityname' => $activityname,
                'word' => $word,
                'qtype' => $qtypetext, // E.g. "MC".
                'qlevel' => $qlevel, // E.g. "A2".
            ];

            // Cache tags, including the "AI-generated" tag to make
            // it obvious that these questions were generated by "AI".
            $tags = [$this->tool->get_string('ai_generated'), $word, $qtypeshort, $qlevel];

            // Ensure that we can get or create a suitable question category.
            if (! $category = $this->get_question_category($parentcategory, $subcattype, $a)) {
                return $this->report_error($log, 'missingquestioncategory', $word);
            }

            // At last, we can generate the questions from the results
            // and store them in a suitable question category.
            if ($questions = $this->parse_questions($log, $results, $qformat, $category->id, $tags)) {
                $status = $toolclass::TASKSTATUS_COMPLETED;
                $error = ''; // Unset any previous errors.

                // Create an string of comma-separated question ids.
                $questionids = array_keys($questions);
                $questionids = implode(', ', $questionids);
            } else {
                $questionids = '';
                $status = $toolclass::TASKSTATUS_FAILED;
                $error = $this->tool->get_string('resultsnotparsed', $word);
            }

            // Update the questionids, status and errors.
            $this->tool->update_log($log->id, [
                'questionids' => $questionids,
                'status' => $status,
                'error' => $error,
            ]);
        }

        if ($error) {
            return $this->report_error($log, 'generatequestions', $error);
        }

        // Mark the adhoc task as completed.
        \core\task\manager::adhoc_task_complete($this);
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
     * @param int $configid
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
     * @param int $qcount
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
        $prompt = preg_replace('/\s+/', ' ', $prompt);

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
        ];

        foreach ($types as $type => $strname) {
            if ($subcattype & $type) {
                $category = $this->get_question_subcategory(
                    $table, $strname, $a, $category
                );
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
        }
        if (empty($catinfo)) {
            $catinfo = $this->tool->get_string("catinfo_$strname", $a);
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

        // No matching subcategory found, so create a new one.
        $subcategory = (object)[
            'name' => $catname,
            'info' => $catinfo,
            'parent' => $parentcategory->id,
            'contextid' => $parentcategory->contextid,
            'stamp' => make_unique_id_code(),
            'sortorder' => 999,
        ];
        if ($subcategory->id = $DB->insert_record($table, $subcategory)) {
            return $subcategory;
        }

        // Could not find or create a subcategory - shouldn't happen !!
        return null;
    }

    /**
     * parse_questions
     *
     * @param string $log
     * @param string $text
     * @param string $qformat
     * @param string $categoryid
     * @param array $tags Moodle tags to be added for each question
     * @return object to represent the
     */
    protected function parse_questions($log, $text, $qformat, $categoryid, $tags) {
        global $CFG, $DB, $USER;

        require_once("$CFG->dirroot/lib//questionlib.php");
        require_once("$CFG->dirroot/question/format.php");

        // Get the main file for the requested question format.
        // We expect only "gift" or "xml", but in theory, anything is possible.
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

        // Initialize the array of questions that will be returned by this method.
        $questions = [];

        // Parse the text into individual questions.
        // Match $1 contains the question name.
        // Match $2 contains the question text.
        // Match $3 contains the answer details (including weighting and feedback).
        $search = '/:: *((?:.|\s)*?) *:: *((?:.|\s)*?) *\{ *((?:.|\s)*?) *\}/s';
        if (preg_match_all($search, $text, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $lines = explode("\n", $matches[0][$i]);
                if ($question = $format->readquestion($lines)) {
                    if (empty($question->name)) {
                        $question->name = clean_param($matches[1][$i], PARAM_TEXT);
                    }
                    if (empty($question->questiontext)) {
                        $question->questiontext = clean_param($matches[2][$i], PARAM_TEXT);
                    }
                    if (is_scalar($question->questiontext)) {
                        $question->questiontext = [
                            'text' => $question->questiontext,
                            'format' => FORMAT_MOODLE,
                        ];
                    }
                    $question->category = $categoryid;
                    $question->createdby = $question->modifiedby = $USER->id;
                    $question->timecreated = $question->timemodified = time();
                    $qtype = \question_bank::get_qtype($question->qtype);
                    $qtype->save_question($question, $question);
                    $questions[$question->id] = $question;
                    $questiontags = $this->create_media($log, $question, $context, $tags);

                    // Add tags for these question.
                    // e.g "AI-generated", "newword", "MC", "TOEIC-200".
                    // Note that all tags are usually displayed in lowercase
                    // even though the "rawname" field stores the uppercase.
                    \core_tag_tag::set_item_tags(
                        'core_question', 'question', $question->id, $context, $questiontags
                    );
                }
            }
        }

        // Return either the array of questions, or FALSE if there are no questions.
        return (empty($questions) ? false : $questions);
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

        // Initialize the cache of fields and fileareas for this $qtype.
        // Usually we are only generating questions for a single $qtype.
        static $tables = [];
        if (empty($tables[$qtype])) {
            $tables[$qtype] = $this->get_tables($question);
        }

        // Initialize the $filerecord that will be used
        // to store media file in Moodle's file repository.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'question',
            'filearea'  => '', // Set this later.
            'itemid'    => 0, // Set this later.
            'filepath'  => '/', // Always this value.
            'filename'  => '', // Set this later.
        ];

        $moretags = [];
        foreach ($tables[$qtype] as $table => $fields) {
            foreach ($fields as $field => $filearea) {
                $filerecord['filearea'] = $filearea;
                $this->create_media_for_field(
                    $mediatags, $question, $table,
                    $field, $filerecord, $moretags
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
    public function get_tables($question) {

        // Initialize the array of tables.
        $tables = [];

        // Cache the question type.
        $qtype = $question->qtype;

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
        if ($table = $this->get_feedback_table($qtype)) {
            list($table, $fields) = $table;
            $tables[$table] = $fields;
        }

        // Add the subquestions table, if any.
        // This is only used by "qtype_match", in which case
        // the table name is "qtype_{$qtype}_subquestions".
        if ($table = $this->get_subquestions_table($qtype)) {
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
    public function get_feedback_table($qtype) {
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
    public function get_subquestions_table($qtype) {
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
     * @param object $question
     * @param object $table
     * @param object $field
     * @param array $filerecord
     * @param array $moretags (passed by reference) Moodle tags for media used in this question.
     */
    public function create_media_for_field($mediatags, $question, $table, $field, $filerecord, &$moretags) {
        global $DB;

        // Determine the SQL search values.
        switch ($table) {
            case 'question':
                $select = 'id = ? AND '.$DB->sql_like($field, '?');
                $params = [$question->id, '%[[%]]%'];
                break;
            case 'question_answers':
                $select = 'question = ? AND '.$DB->sql_like($field, '?');
                $params = [$question->id, '%[[%]]%'];
                break;
            default: // Question hints and qtype options table.
                $select = 'questionid = ? AND '.$DB->sql_like($field, '?');
                $params = [$question->id, '%[[%]]%'];
        }
        if ($records = $DB->get_records_select($table, $select, $params)) {

            $count = 0;
            foreach ($records as $id => $record) {
                if ($table == 'question_answers' || $table == 'question_hints') {
                    $filerecord['itemid'] = $id;
                } else {
                    $filerecord['itemid'] = $question->id;
                }
                $filearea = $filerecord['filearea'];
                $number = str_pad(++$count, 2, '0', STR_PAD_LEFT);
                $filerecord['filename'] = "{$filearea}-{$number}";

                // Create the media for this field in this record.
                $this->create_media_for_record(
                    $mediatags, $table, $record, $field, $filerecord, $moretags
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
     * @param array $moretags (passed by reference) Moodle tags for media used in the current question.
     */
    public function create_media_for_record($mediatags, $table, $record, $field, $filerecord, &$moretags) {
        global $DB;

        // Sanity check on incoming parameters.
        if (empty($record) || empty($field) || empty($record->$field)) {
            return false;
        }

        $filenameprefix = $filerecord['filename'];
        $filerecord['filename'] = '';

        // Set up search string for the media tags.
        static $search = null;
        if ($search === null) {
            $search = (object)[
                // Search string to extract media tags:
                // $1: type of media (IMAGE, AUDIO or VIDEO)
                // $2: tag attributes and AI prompt.
                'tags' => '/\[\[('.implode('|', array_keys($mediatags)).')(.*?)\]\]/',
                // Search string to extract attributes of media tags:
                // $1: name of attribute (alt, width, height, class)
                // $2: value of attribute.
                'attributes' => '/(\w+) *= *"(.*?)"/',
            ];
        }

        // Initialize the counters used to generate unique filenames.
        $count = (object)array_combine(
            array_keys($mediatags),
            array_fill(0, count($mediatags), 0)
        );

        if (preg_match_all($search->tags, $record->$field, $tags, PREG_OFFSET_CAPTURE)) {

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

                // Transfer tag attributes (e.g. width, height) from $tagprompt to $tagparams.
                if (preg_match_all($search->attributes, $tagprompt, $attributes, PREG_OFFSET_CAPTURE)) {

                    $amax = count($attributes[0]) - 1;
                    for ($a = $amax; $a >= 0; $a--) {

                        list($attribute, $astart) = $attributes[0][$a];
                        $alength = strlen($attribute);
                        $aname = $attributes[1][$a][0];
                        $avalue = $attributes[2][$a][0];

                        // Check $aname is valid. Otherwise, ignore it.
                        if (array_key_exists($aname, $tagparams)) {
                            $tagparams[$aname] = $avalue;
                            $tagprompt = substr_replace($tagprompt, '', $astart, $alength);
                        }
                    }
                }

                // Trim prompt and standardize space and tabs to a single space.
                $tagprompt = trim(preg_replace('/[ \t]+/', ' ', $tagprompt));

                // Increament the count of the number of media files
                // of this tagname created in this $record->$field.
                $count->$tagname = ($count->$tagname + 1);

                // Set filename.
                if ($filename = $tagparams['filename']) {
                    $filename = clean_param($filename, PARAM_FILE);
                    $filename = preg_replace('/[ \._]+/', '_', $filename);
                    $filename = trim($filename, ' -._');
                    $filetype = pathinfo($path, PATHINFO_FILENAME);
                }
                if ($filename == '') {
                    // E.g. questiontext-01-image-01.
                    $filename = $filenameprefix.'-';
                    $filename .= strtolower($tagname);
                    $filename .= '-'.$count->$tagname;
                }
                $filetype = '';
                switch ($tagname) {
                    case 'IMAGE':
                        $filetype = '.png';
                        break;
                    case 'AUDIO':
                        $filetype = '.mp3';
                        break;
                    case 'VIDEO':
                        $filetype = '.mp4';
                        break;
                }
                $filerecord['filename'] = $filename.$filetype;

                // Send the prompt to one of the AI subplugins to generate the media file.
                // For image files, several variations maybe created, but only the
                // first one will be returned by the "create_media_file()" method.
                $file = $this->create_media_file($configid, $tagname, $tagprompt, $filerecord);

                if (is_object($file)) {

                    // Remove empty tag params, and add a value for the "src" parameter.
                    $tagparams = array_filter($tagparams);
                    $tagparams['src'] = '@@PLUGINFILE@@/'.$file->get_filename();

                    if ($tagname == 'IMAGE') {
                        if (empty($tagparams['style'])) {
                            // Add styles to make the image responsive and on its own line.
                            $tagparams['style'] = 'display: block; height: auto; max-height: 100%; max-width: 100%;';
                        }
                        $html = \html_writer::empty_tag('img', $tagparams);
                    } else {
                        // AUDIO and VIDEO.
                        $html = \html_writer::empty_tag('source', ['src' => $tagparams['src']]);
                        $html = \html_writer::tag(strtolower($tagname), $html.$tagparams['src'], ['controls' => 'true']);
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
     * create_media_file
     *
     * @param integer $configid
     * @param string $mediatype IMAGE, AUDIO, VIDEO
     * @param string $prompt
     * @param array $filerecord
     * @return object source_file or error string
     */
    public function create_media_file($configid, $mediatype, $prompt, $filerecord) {

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

        return $creator->get_media_file($filerecord, $prompt);
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

        // Mark this task as having failed.
        \core\task\manager::adhoc_task_failed($this);

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
