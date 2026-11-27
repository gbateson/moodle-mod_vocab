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

    /** @var int the teacher who set up this task, as whom the AI bridge is used. */
    protected $userid = 0;

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
        $this->userid = (int) $userid;

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
            $logid = (isset($data->logid) ? $data->logid : 'missing');
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

        // The activity's context, in which the AI bridge chooses providers and templates.
        $context = $this->tool->vocab->context;

        // Check the prompt, format and provider are available.
        $a = [];
        $prompttemplate = \mod_vocab\aibridge::get_template($promptid);
        if (! $prompttemplate || ! $prompttemplate->can_use($context, $userid)) {
            $a[] = "promptid ($promptid)";
        }
        $formattemplate = null;
        if ($formatid) {
            $formattemplate = \mod_vocab\aibridge::get_template($formatid);
            if (! $formattemplate || ! $formattemplate->can_use($context, $userid)) {
                $a[] = "formatid ($formatid)";
            }
        }
        // A provider id of 0 or less lets the AI bridge choose a provider.
        $providerid = max(0, $textid);
        if ($providerid && ! \mod_vocab\aibridge::get_provider($providerid)) {
            $a[] = "textid ($textid)";
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
                $status = $toolclass::TASKSTATUS_IMPORTING_RESULTS;
            }
            $this->tool->update_log($log->id, ['status' => $status]);
        }

        // If the status is any of the following, something
        // is wrong so we stop here.
        if (
            $status == $toolclass::TASKSTATUS_AWAITING_REVIEW ||
            $status == $toolclass::TASKSTATUS_COMPLETED ||
            $status == $toolclass::TASKSTATUS_CANCELLED ||
            $status == $toolclass::TASKSTATUS_FAILED
        ) {
            return; // No return value is required.
        }

        /*//////////////////////////////////
        // Check parameters and make the prompt.
        //////////////////////////////////*/

        if (
            $status == $toolclass::TASKSTATUS_NOTSET ||
            $status == $toolclass::TASKSTATUS_QUEUED ||
            $status == $toolclass::TASKSTATUS_DELAYED ||
            $status == $toolclass::TASKSTATUS_CHECKING_PARAMS
        ) {
            // Report start of checking params.
            mtrace($this->tool->get_string('checkingparams'), ' ');

            $status = $toolclass::TASKSTATUS_CHECKING_PARAMS;
            $this->tool->update_log($log->id, ['status' => $status]);

            if (! \mod_vocab\aibridge::is_enabled()) {
                return $this->report_error($log, 'aibridgedisabled');
            }

            // Make the prompt from the template, unless it has been made
            // already, and perhaps edited by the teacher.
            if (empty($prompt)) {
                $rendering = $this->render_prompt(
                    $prompttemplate,
                    $formattemplate,
                    $context,
                    $userid,
                    $word,
                    $qtype,
                    $qlevel,
                    $qcount
                );
                if (! $rendering->is_valid()) {
                    $a = ['id' => $promptid, 'name' => implode(' ', $rendering->get_errors())];
                    return $this->report_error($log, 'invalidprompt', $a);
                }
                $prompt = $rendering->get_text();
                $qformat = ($rendering->get_qformat() ?: 'gift');
                $this->tool->update_log($log->id, ['prompt' => $prompt, 'qformat' => $qformat]);
            }
            if (empty($qformat)) {
                $qformat = 'gift';
            }

            // Report successful completion of checking params.
            mtrace('[' . get_string('ok') . ']');

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

            // Ensure sensible values for min/max tries.
            $maxtries = max(1, min(10, $maxtries));
            $tries = max(0, min($maxtries, $log->tries));

            // Each run of the task sends the prompt once. If the AI bridge
            // says that it may work later, e.g. after a rate limit or when the
            // provider is less busy, the task fails and Moodle runs it again
            // later, until the allowed number of tries have been made.
            $tries++;
            $this->tool->update_log($log->id, ['tries' => $tries]);
            $response = $this->send_prompt($prompt, $prompttemplate, $context, $userid, $providerid);
            $results = ($response->get_success() ? trim($response->get_content()) : '');

            if ($results === '') {
                $error = ($response->get_success() ? $this->tool->get_string('emptyresults') : $response->get_error());
                if ($response->is_temporary() && $tries < $maxtries) {
                    $this->tool->update_log($log->id, [
                        'status' => $toolclass::TASKSTATUS_DELAYED,
                        'error' => shorten_text($error, 255),
                    ]);
                    throw new \moodle_exception('error_aibridgetryagain', 'vocabtool_questionbank', '', $error);
                }
                return $this->report_error($log, 'generatequestions', $error);
            }

            if ($log->review) {
                $status = $toolclass::TASKSTATUS_AWAITING_REVIEW;
            } else {
                $status = $toolclass::TASKSTATUS_AWAITING_IMPORT;
            }

            // Store error, status and results.
            $error = '';
            $this->tool->update_log($log->id, [
                'error' => $error,
                'status' => $status,
                'results' => $results,
            ]);

            // Report successful completion to cron job.
            mtrace('[' . get_string('ok') . ']');
        }

        // Ensure that the target question category is in the target course (Moodle 4.x),
        // or in one of its question banks (Moodle 5.x).
        $parentcategory = $DB->get_record('question_categories', ['id' => $parentcatid]);
        $contexts = \mod_vocab\question_banks::get_contexts($courseid);
        if (! $parentcategory || ! array_key_exists($parentcategory->contextid, $contexts)) {
            return $this->report_error($log, 'invalidquestioncategoryid', $parentcatid);
        }

        // Determine the human readable text for $qtype e.g. "Multiple choice".
        $qtypetext = \vocabtool_questionbank\form::get_question_type_text($qtype);

        $qtypeshort = $qtype . 'short';
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
        $sectiontype = 'format_' . $this->tool->vocab->course->format;
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
            $sectionname = $sectiontype . get_string('labelsep', 'langconfig');
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
        if ($promptname = format_string($prompttemplate->get_name(), true, ['context' => $context])) {
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

        if (
            $status == $toolclass::TASKSTATUS_AWAITING_IMPORT ||
            $status == $toolclass::TASKSTATUS_IMPORTING_RESULTS
        ) {
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
                // Record which word the questions are about, for the activity's games.
                \mod_vocab\progress::link_questions($wordid, array_keys($questions));
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
                    // Check that the question exists, and if it does we load it up.
                    // If it doesn't exist, it has probably been deleted.
                    if ($DB->record_exists('question', ['id' => $questionid])) {
                        $questions[$questionid] = \question_bank::load_question($questionid);
                    }
                }
            }

            // Report status to cron job.
            $mediatags = [
                'IMAGE' => $log->imageid,
                'AUDIO' => $log->audioid,
                'VIDEO' => $log->videoid,
            ];
            $mediatags = array_filter($mediatags);
            if ($mediatags = implode(', ', array_keys($mediatags))) {
                $a = (object)['media' => $mediatags, 'word' => $word];
                mtrace($this->tool->get_string('creatingmedia', $a));
            }

            // The cache of question data will be
            // setup the first time it is required.
            $cache = null;

            foreach ($questions as $questionid => $question) {
                // Making media can take a long time, e.g. resizing and saving images.
                self::lift_time_limit();

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
                    if (count($mediatags)) {
                        if ($cache === null) {
                            $cache = \cache::make('core', 'questiondata');
                        }
                        $cache->delete($question->id);
                        if ($addmediatags) {
                            $alltags = \core_tag_tag::get_item_tags_array(
                                'core_question',
                                'question',
                                $question->id
                            );
                            $alltags = array_values($alltags); // Remove tagids.
                            $alltags = array_merge($alltags, $mediatags);
                            $alltags = array_filter($alltags); // Remove blanks.
                            \core_tag_tag::set_item_tags(
                                'core_question',
                                'question',
                                $question->id,
                                $context,
                                $alltags
                            );
                        }
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
     * Make the prompt from a template in the AI bridge's library.
     *
     * @param \tool_aibridge\library\template $prompt
     * @param \tool_aibridge\library\template|null $format the output format, or null for the one that the prompt links to.
     * @param \context $context
     * @param int $userid
     * @param string $word
     * @param string $qtype e.g. "multichoice".
     * @param string $qlevel e.g. "A2".
     * @param int $qcount
     * @return \tool_aibridge\library\rendering
     */
    protected function render_prompt($prompt, $format, $context, $userid, $word, $qtype, $qlevel, $qcount) {
        $values = [
            'vocabulary-item' => $word,
            'level-of-questions' => $qlevel,
            'number-of-questions' => $qcount,
        ];
        if ($format) {
            $values['format'] = $format->get_shortname();
        }
        return \tool_aibridge\library::render($prompt, $context, $userid, $values, $qtype);
    }

    /**
     * Send a prompt to the AI bridge.
     *
     * @param string $text the prompt.
     * @param \tool_aibridge\library\template $prompt the template that it was made from, for the log.
     * @param \context $context
     * @param int $userid
     * @param int $providerid one provider to use, or 0 for any.
     * @return \tool_aibridge\response
     */
    protected function send_prompt($text, $prompt, $context, $userid, $providerid) {
        $action = new \tool_aibridge\action\generate_text($context->id, $userid, $text);
        $action->set_purpose('vocabtool_questionbank', $prompt->get_shortname());
        $action->set_template($prompt->get_id(), $prompt->get_version());
        return \tool_aibridge\manager::process($action, $providerid);
    }

    /**
     * Lift PHP's time limit when the task runs under the web server, e.g. when cron is
     * run from a browser (admin/cron.php). That script declares CLI_SCRIPT, so Moodle's
     * core_php_time_limit::raise() does nothing, but the web server's limit (often 30
     * seconds) still applies, and to the whole cron run. On the command line, PHP has
     * no time limit, so nothing is done.
     *
     * If the site sets a maximum time limit ($CFG->maxtimelimit), e.g. for a front-end
     * server's timeout, the limit is restarted at that value instead of being lifted.
     */
    protected static function lift_time_limit(): void {
        global $CFG;
        if (PHP_SAPI === 'cli') {
            return;
        }
        $limit = (empty($CFG->maxtimelimit) ? 0 : max(1, (int) $CFG->maxtimelimit));
        @set_time_limit($limit);
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

        // The prompt's head and tail are the parts of its name before and after a colon,
        // e.g. "Head: Generate questions: Tail". If the name has no colon, they are empty,
        // and there is no subcategory for them.
        if ($a->prompthead === '') {
            $subcattype &= ~$form::SUBCAT_PROMPTHEAD;
        }
        if ($a->prompttail === '') {
            $subcattype &= ~$form::SUBCAT_PROMPTTAIL;
        }

        foreach ($types as $type => $strname) {
            if ($subcattype & $type) {
                if ($type == $form::SUBCAT_CUSTOMNAME) {
                    $subcatnames = explode(',', $a->$strname);
                    $subcatnames = array_map('trim', $subcatnames);
                    $subcatnames = array_filter($subcatnames);
                    foreach ($subcatnames as $subcatname) {
                        $a->$strname = $subcatname;
                        $category = $this->get_question_subcategory(
                            $table,
                            $strname,
                            $a,
                            $category
                        );
                    }
                    $a->$strname = implode(', ', $subcatnames);
                } else {
                    // A single category.
                    $category = $this->get_question_subcategory(
                        $table,
                        $strname,
                        $a,
                        $category
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
    protected function get_question_subcategory($table, $strname, $a, $parentcategory, $catname = '', $catinfo = '') {
        global $DB;

        // Sanity check on incoming data.
        if (empty($parentcategory)) {
            return null;
        }

        if (empty($catname)) {
            $catname = $this->tool->get_string("catname_$strname", $a);
            $catname = str_replace('()', '', $catname);
            $catname = trim(preg_replace('/\s+/', ' ', $catname));
        }
        if (empty($catinfo)) {
            $catinfo = $this->tool->get_string("catinfo_$strname", $a);
            $catinfo = str_replace('()', '', $catinfo);
            $catinfo = trim(preg_replace('/\s+/', ' ', $catinfo));
        }

        // A subcategory with the same name as its parent adds nothing, so use the parent.
        if ($catname === '' || $catname === $parentcategory->name) {
            return $parentcategory;
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
            $sortorder++;
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
            'question_categories',
            'contextid',
            ['id' => $categoryid]
        ));

        // Get an instance of the required qformat class.
        // It is this instance that will actually parse the $text
        // and create the questions in the question bank.
        $format = new $classname();

        switch ($qformat) {
            case 'gift':
                $text = $this->fix_gift($log->qtype, $text);
                $questions = $this->parse_questions_gift(
                    $log,
                    $context,
                    $format,
                    $text,
                    $categoryid,
                    $tags
                );
                break;
            case 'multianswer': // Embedded questions (a.k.a cloze).
                $questions = $this->parse_questions_multianswer(
                    $log,
                    $context,
                    $format,
                    $text,
                    $categoryid,
                    $tags
                );
                break;
            case 'xml':
                $questions = $this->parse_questions_xml(
                    $log,
                    $context,
                    $format,
                    $text,
                    $categoryid,
                    $tags
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
            [$match, $start] = $match[2];
            $length = strlen($match);
            $match = ltrim($match);
            $char = substr($match, 0, 1);
            if ($char != '=' && $char != '~') {
                $text = substr_replace($text, ' =' . $match, $start, $length);
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

        // Parse the raw text into individual questions.
        // ::question-name::question-text {=correct-answer ~wrong-answer ...}
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
                        $questions,
                        $question,
                        $name,
                        $text,
                        $log,
                        $context,
                        $categoryid,
                        $tags
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
                $search = '/(?=' . preg_quote($match[0], '/') . ')/u';
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
                        $questions,
                        $question[$q],
                        $name,
                        $text,
                        $log,
                        $context,
                        $categoryid,
                        $tags,
                        true
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
    protected function add_question(
        &$questions,
        $question,
        $name,
        $text,
        $log,
        $context,
        $categoryid,
        $tags,
        $saveoptions = false
    ) {
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
            'core_question',
            'question',
            $question->id,
            $context,
            $tags
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

        // Map each media tag to the AI bridge provider that will create the
        // media content for that tag: -1 for any provider, or 0 for none.
        $mediatags = [
            'IMAGE' => (int) $log->imageid,
            'AUDIO' => (int) $log->audioid,
            'VIDEO' => (int) $log->videoid,
        ];
        // Remove tags that are not required.
        $mediatags = array_filter($mediatags);

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
            $tables[$qtype] = self::get_tables($question, false);
        }

        // Initialize the $filerecord that will be used
        // to store media files in Moodle's file repository.
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
                    $mediatags,
                    $table,
                    $field,
                    $filerecord,
                    $question->id,
                    $moretags
                );
            }
        }

        $moretags = array_keys($moretags);
        foreach ($moretags as $i => $tagname) {
            // Use messages defined in "lang/en/message.php".
            $strname = 'messagecontent' . strtolower($tagname);
            $moretags[$i] = get_string($strname, 'message');
        }

        return array_merge($tags, $moretags);
    }

    /**
     * Get tables for the specified $qtype.
     *
     * @param object $question
     * @param boolean $returnquestionid
     * @return array [$table => [$fields]]
     */
    public static function get_tables($question, $returnquestionid) {
        global $DB;

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
            if ($returnquestionid) {
                $fields['questionid'] = 'id';
            }
            $tables[$table] = $fields;
        }

        // Add the answer table, if required.
        if (property_exists($question, 'answer')) {
            $answersexist = true;
        } else if (
            in_array($qtype, [
            // These question types use the question_answers table.
            'calculated', 'calculatedmulti', 'calculatedsimple',
            'multichoice', 'numerical', 'shortanswer', 'truefalse',
            'ordering', 'essayautograde', 'speakautograde',
            ])
        ) {
            $answersexist = true;
        } else if (property_exists($question, 'id')) {
            $answersexist = $DB->record_exists('question_answers', ['question' => $question->id]);
        } else {
            $answersexist = false;
        }
        if ($answersexist) {
            $table = 'question_answers';
            $fields = [
                'answer' => 'answer',
                'feedback' => 'feedback',
            ];
            if ($returnquestionid) {
                $fields['questionid'] = 'question';
            }
            $tables[$table] = $fields;
        }

        // Add the hint table, if required.
        if (property_exists($question, 'hint')) {
            $table = 'question_hints';
            $fields = ['hint' => 'hint'];
            if ($returnquestionid) {
                $fields['questionid'] = 'questionid';
            }
            $tables[$table] = $fields;
        }

        // Add the combined feedback table, if any.
        // Usually this is "qtype_{$qtype}_options".
        if ($table = self::get_feedback_table($qtype, $returnquestionid)) {
            [$table, $fields] = $table;
            $tables[$table] = $fields;
        }

        // Add the subquestions table, if any.
        // This is only used by "qtype_match", in which case
        // the table name is "qtype_{$qtype}_subquestions".
        if ($table = self::get_subquestions_table($qtype, $returnquestionid)) {
            [$table, $fields] = $table;
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
     * @param boolean $returnquestionid
     * @return array [$table, [$fields]] if feedback table exists, otherwise NULL.
     */
    public static function get_feedback_table($qtype, $returnquestionid) {
        global $DB;

        // We will use the DB manager to determine which tables exist.
        $dbman = $DB->get_manager();

        // These are the fields (and fileareas) we are looking for.
        $fields = [
            'correctfeedback' => 'correctfeedback',
            'incorrectfeedback' => 'incorrectfeedback',
            'partiallycorrectfeedback' => 'partiallycorrectfeedback',
        ];
        if ($returnquestionid) {
            $fields['questionid'] = 'questionid';
        }

        // A representative feedback field.
        // If this exists, we assume they all exist.
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
                // question_gapselect.
                return [$table, $fields];
            }
            return null;
        }

        $table = "question_{$qtype}_options";
        if ($dbman->table_exists($table)) {
            if ($dbman->field_exists($table, $field)) {
                // These question types:
                // question_calculated_options.
                if ($returnquestionid) {
                    $fields['questionid'] = 'question';
                }
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
     * @param boolean $returnquestionid
     * @return array [$table, [$fields]] if feedback table exists, otherwise NULL.
     */
    public static function get_subquestions_table($qtype, $returnquestionid) {
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

            if ($returnquestionid) {
                $fields['questionid'] = 'questionid';
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
                $select = 'id = ? AND ' . $DB->sql_like($field, '?');
                $params = [$questionid, '%[[%]]%'];
                break;
            case 'question_answers':
                $select = 'question = ? AND ' . $DB->sql_like($field, '?');
                $params = [$questionid, '%[[%]]%'];
                break;
            default: // Question hints and qtype options table.
                $select = 'questionid = ? AND ' . $DB->sql_like($field, '?');
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
                    $mediatags,
                    $table,
                    $record,
                    $field,
                    $filerecord,
                    $questionid,
                    $moretags
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
        $search = (object)[
            // Search string to extract media tags:
            // $1: type of media (IMAGE, AUDIO or VIDEO)
            // $2: tag attributes and AI prompt.
            'tags' => '/\[\[(' . implode('|', array_keys($mediatags)) . ')(.*?)\]\]/us',
            // Search string to extract attributes of media tags:
            // $1: name of attribute (alt, width, height, class)
            // $2: value of attribute.
            'attributes' => '/(\w+) *= *"(.*?)"/u',
        ];

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
                [$tag, $tagstart] = $tags[0][$t];
                $taglength = strlen($tag);
                $tagname = $tags[1][$t][0];
                $tagprompt = $tags[2][$t][0];

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
                        [$attribute, $astart] = $attributes[0][$a];
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

                // Set the root of the media filename e.g. questiontext-01-image-01.
                $mediafilename = \mod_vocab\activity::modify_filename($filename, '', $suffix, $filetype);
                $filerecord['filename'] = $mediafilename;

                // Ask the AI bridge to make the media file. A conversation between
                // several speakers is spoken in a different voice for each speaker.
                $file = $this->create_media_file($mediatags[$tagname], $tagname, $tagprompt, $filerecord, $questionid);

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
                    $src = '@@PLUGINFILE@@/' . $file->get_filename();

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
                        $html = \html_writer::tag(strtolower($tagname), $html . $src, $tagparams);

                        // Add wrapper to prevent mediaplayer filter from positioning this media tag
                        // in the center of the page caused by the .mediaplugin>div { margin: auto; }.
                        if ($tagname == 'AUDIO' && $usemediaplugin === true) {
                            $params = [
                                'class' => 'rounded',
                                'style' => 'background: black; ' .
                                           'display: inline-block; ' .
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
                    $fieldformat = $field . 'format';
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
                    mtrace('Oops, "create_media_file()" returned an unrecognizable result.');
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
    protected function add_html_for_subtitles($file, $srclang = 'en', $label = 'English') {

        $filename = $file->get_filename();
        $filename = \mod_vocab\activity::modify_filename($filename, '', '', 'vtt');

        $fs = get_file_storage();
        $exists = $fs->file_exists(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $filename
        );

        if ($exists) {
            $params = [
                'src' => '@@PLUGINFILE@@/' . $filename,
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
        $a = (object)['type' => $filerecord['filearea'], 'error' => $filerecord['filename']];
        return $this->tool->get_string('medianotcreated', $a);
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
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        );
    }








    /**
     * Make a media file (image or audio) with the AI bridge.
     *
     * @param int $providerid the provider to use, or -1 for any provider.
     * @param string $mediatype IMAGE, AUDIO or VIDEO.
     * @param string $prompt what to draw or say. For a conversation, the speakers and lines.
     * @param array $filerecord where to store the file. Its extension is set from the media.
     * @param int $questionid
     * @return \stored_file|string the file, or an error message.
     */
    public function create_media_file($providerid, $mediatype, $prompt, $filerecord, $questionid) {
        $providerid = max(0, (int) $providerid);
        $text = $prompt;
        $options = [
            'providerid' => $providerid,
            'component' => 'vocabtool_questionbank',
            'purpose' => strtolower($mediatype),
        ];
        if ($mediatype == 'AUDIO') {
            $text = self::format_dialogue($text);
            if (! \tool_aibridge\dialogue::is_dialogue($text)) {
                $options['voice'] = $this->choose_voice($providerid, $questionid);
            }
        }
        $item = ['type' => $mediatype, 'text' => $text, 'attributes' => '', 'marker' => ''];
        $response = \tool_aibridge\media::generate($item, $this->tool->vocab->context->id, $this->userid, $options);
        if (! $response->get_success()) {
            $a = (object)['type' => strtolower($mediatype), 'error' => $response->get_error()];
            return $this->tool->get_string('medianotcreated', $a);
        }

        $fs = get_file_storage();
        $filerecord['filename'] = \mod_vocab\activity::modify_filename(
            $filerecord['filename'],
            '',
            '',
            $response->get_extension()
        );
        $pathnamehash = $this->get_pathnamehash($fs, $filerecord);
        if ($fs->file_exists_by_hash($pathnamehash)) {
            $fs->get_file_by_hash($pathnamehash)->delete();
        }
        if ($mediatype == 'IMAGE') {
            $file = $response->save_image($filerecord);
        } else {
            $file = $response->save_audio($filerecord);
        }

        // A conversation has subtitles, with the speaker and text of each line.
        if ($captions = $response->get_captions()) {
            $filerecord['filename'] = \mod_vocab\activity::modify_filename($filerecord['filename'], '', '', 'vtt');
            $this->add_or_update_file_from_string($fs, $filerecord, $captions);
        }
        return $file;
    }

    /**
     * Choose a voice for the audio of a question, from the provider's voices, so that
     * different questions may have different voices, but each question has only one.
     *
     * @param int $providerid
     * @param int $questionid
     * @return string the voice, or '' for the provider's default voice.
     */
    protected function choose_voice($providerid, $questionid) {
        $provider = \mod_vocab\aibridge::get_provider((int) $providerid);
        if (! $provider) {
            return '';
        }
        $defaults = $provider->get_token_defaults();
        $voices = [];
        foreach (['VOICES_FEMALE', 'VOICES_MALE'] as $name) {
            if (! empty($defaults[$name])) {
                $voices = array_merge($voices, explode(',', $defaults[$name]));
            }
        }
        $voices = array_values(array_filter(array_map('trim', $voices)));
        if (empty($voices)) {
            return '';
        }
        return $voices[$questionid % count($voices)];
    }

    /**
     * Put each speaker and each line of a conversation on a line of its own,
     * if the AI has written the whole conversation on one line, e.g.
     * "A=woman (customer) B=man (staff) A: Hello. B: Hi." becomes
     * "A=woman (customer)\nB=man (staff)\nA: Hello.\nB: Hi."
     *
     * @param string $text
     * @return string
     */
    public static function format_dialogue($text) {
        $text = preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $text);
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'));
        if (strpos($text, "\n") !== false) {
            return $text;
        }
        if (! preg_match('/^(.*?)\s*([A-Z]\s*:\s*.*)$/us', $text, $match)) {
            return $text;
        }
        $script = [];
        if (preg_match_all('/([A-Z])\s*=\s*(\w+)(?:\s*\(([^)]+)\))?/u', $match[1], $speakers, PREG_SET_ORDER)) {
            foreach ($speakers as $speaker) {
                $script[] = $speaker[1] . '=' . $speaker[2] . (empty($speaker[3]) ? '' : ' (' . trim($speaker[3]) . ')');
            }
        }
        $count = 0;
        if (preg_match_all('/([A-Z])\s*:\s*(.*?)(?=\s+[A-Z]\s*:|$)/us', $match[2], $lines, PREG_SET_ORDER)) {
            foreach ($lines as $line) {
                $script[] = $line[1] . ': ' . trim($line[2]);
                $count++;
            }
        }
        if ($count < 2) {
            return $text;
        }
        return implode("\n", $script);
    }

    /**
     * report_error
     *
     * @param object $log the log record associated with this adhoc task
     * @param string $error name of an error in the in lang pack
     * @param string $a arguments required (if any) by $error string
     * @return bool false
     */
    public function report_error($log, $error, $a = null) {

        // Fetch the full error message.
        $error = $this->tool->get_string("error_$error", $a);

        // Format the label.
        $label = '[' . $this->tool->plugin . '] ' . get_string('error');
        $label .= get_string('labelsep', 'langconfig');

        // Print the label with the error.
        mtrace($label . $error);

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
