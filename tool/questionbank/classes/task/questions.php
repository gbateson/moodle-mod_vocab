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

    /** @var object to represent a curl object */
    protected $curl = null;

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
            return; // Cannot continue.
        }

        // Extract settings from log.
        $vocabid = $log->vocabid;
        $wordid = $log->wordid;

        $qtype = $log->qtype;
        $qlevel = $log->qlevel;
        $qcount = $log->qcount;
        $qformat = $log->qformat;

        $accessid = $log->accessid;
        $promptid = $log->promptid;
        $formatid = $log->formatid;

        $parentcatid = $log->parentcatid;
        $subcattype = $log->subcattype;
        $subcatname = $log->subcatname;

        $maxtries = $log->maxtries;
        $tries = $log->tries;

        $status = $log->status;
        $error = $log->error;
        $results = $log->results;

        // Intialize the vocab activity tool.
        $this->tool = $toolclass::create($vocabid);

        // Check log data is valid and consistent.
        if (! $this->tool->vocab->cm) {
            return $this->report_error('invalidvocabid', $vocabid);
        }
        if (! $this->tool->vocab->can_manage()) {
            return $this->report_error('invaliduserid', $userid);
        }
        if (! $word = $DB->get_field('vocab_words', 'word', ['id' => $wordid])) {
            return $this->report_error('invalidwordid', $wordid);
        }
        $params = ['vocabid' => $vocabid, 'wordid' => $wordid];
        if (! $DB->record_exists('vocab_word_instances', $params)) {
            return $this->report_error('missingwordinstance', $word);
        }

        // Cache the course id and context.
        $courseid = $this->tool->vocab->course->id;
        $coursecontext = \context_course::instance($courseid);

        // Ensure that this user can add questions in the target course.
        if (! has_capability('moodle/question:add', $coursecontext)) {
            $a = ['userid' => $userid, 'courseid' => $courseid];
            return $this->report_error('invalidteacherid', $a);
        }

        // Get all question categories in the current course.
        $coursecategory = question_get_top_category($coursecontext->id);
        $categories = question_categorylist($coursecategory->id);

        // Ensure that the target question category is in the target course.
        if (! in_array($parentcatid, $categories)) {
            return $this->report_error('invalidquestioncategoryid', $parentcatid);
        }

        // Cache the parent category.
        $parentcategory = $DB->get_record('question_categories', ['id' => $parentcatid]);

        // Determine the human readable text for $qtype.
        $qtypetext = \vocabtool_questionbank\form::get_question_type_text($qtype);

        // Determine the human readable text for $qlevel.
        $qleveltext = \vocabtool_questionbank\form::get_question_level_text($qlevel);

        // Setup arguments for the strings used to create question category names.
        $a = (object)[
            'course' => $this->tool->vocab->course->shortname,
            'word' => $word,
            'qtype' => $qtypetext,
            'level' => $qlevel, // Just the code is enough e.g. "A2".
        ];

        // Ensure that we can get or create a suitable question category.
        if (! $category = $this->get_question_category($parentcategory, $subcattype, $subcatname, $word, $a)) {
            return $this->report_error('missingquestioncategory', $word);
        }

        $tool->update_log($log->id, [
            'status' => $toolclass::TASKSTATUS_FETCHING_RESULTS,
        ]);

        $accessconfig = $this->get_config($accessid);
        $promptconfig = $this->get_config($promptid);
        $formatconfig = $this->get_config($formatid);

        $prompt = $this->get_prompt($promptconfig, $word, $qtype, $qlevel, $qcount, $qformat);

        // Initialize the curl connection.
        $this->init_curl($ai, $prompt);

        // Initialize the error message.
        $error = '';

        // Only only one try.
        $maxtries = 1;

        // Prompt the AI assistant until either we succeed
        // or we have tried the allowed number of times.
        for ($i = $log->tries; $i <= $maxtries; $i++) {

            // Loop may finish before $maxtries
            // if results are received from AI.

            // Update tries value in the database.
            $log->tries = $i;
            $log->timemodified = time();
            $DB->update_record($table, $log);

            // Send the prompt to the AI assistant and receive the response.
            $response = curl_exec($this->curl);
            $response = json_decode($response);
            $response = (object)[
                'text' => ($response->choices[0]->message->content ?? null),
                'error' => ($response->error ?? null),
            ];

            if ($response->text) {

                $log->datemodified = time();
                $log->gift = $response->text;
                $DB->update_record($table, $log);

                // Parse the questions text.
                if ($questions = $this->parse_questions($response->text, $qformat, $category->id)) {
                    // Ignore any provious errors and leave this loop.
                    $error = '';
                } else {
                    $error = "Questions for {$word} could not be parsed.";
                }
                break;
            }

            // Prepare error message for cronoutput.
            if (! empty($response->error->message)) {
                $error = $response->error->message;
            } else if (! empty($response->error->code)) {
                $error = 'Code '.$response->error->code;
            } else {
                $error = 'Unknown error';
            }
        }

        if ($error) {
            return $this->report_error('generatequestions', $error);
        } else {
            return \core\task\manager::adhoc_task_complete($this);
        }
    }

    /**
     * get_config
     *
     * @param integer $configid
     * @return object record from the vocab_config table
     */
    protected function get_config($configid) {
        $contexts = $this->tool->vocab->get_readable_contexts('', 'id');
        return $this->tool->get_config_settings($contexts, $configid);
    }

    /**
     * get AI assistant
     *
     * @param object $config
     * @return an object representing the AI assistant.
     */
    protected function get_ai($config) {
        if (empty($config)) {
            $chatgpt = new \vocabai_chatgpt\ai(null, null, $this->tool->vocab->id);
            $contexts = $this->tool->vocab->get_readable_contexts('', 'id');
            $settings = $chatgpt->get_config_settings($contexts);
            return (empty($settings) ? null : reset($settings));
        }
        return $ai;
    }

    /**
     * get questions
     *
     * @param object $config
     * @param string $word
     * @param string $qtype
     * @param string $qlevel
     * @param integer $qcount
     * @param string $qformat
     * @return string a prompt to send to an AI assistant, such as ChatGPT.
     */
    protected function get_prompt($config, $word, $qtype, $qlevel, $qcount, $qformat) {
        if (is_array($config) && array_key_exists('prompttext', $config)) {
            $prompt = $config['prompttext'];
        } else {
            $prompt = $this->get_prompt_default();
        }
        $format = $this->get_format($qformat, $qtype);

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
     * init_curl
     *
     * @param array $config values from vocab_config_settings.
     * @param string $prompt the prompt to send to the AI assistant.
     * @param float $temperature to regulate "randomness" in the AI assistant
     * @return object of questions
     */
    protected function init_curl($config, $prompt, $temperature = 0.7) {
        if (is_array($config) && array_key_exists('chatgptkey', $config)) {
            $url = $config['chatgpturl'];
            $key = $config['chatgptkey'];
            $model = $config['chatgptmodel'];
        } else {
            $url = 'https://api.openai.com/v1/chat/completions';
            $key = ''; // Put your key here.
            $model = 'gpt-4';
        }

        // Set the maximum number of tokens.
        switch ($model) {
            case 'gpt-4':
                $maxtokens = 8192;
                break;
            case 'gpt-3.5-turbo':
                $maxtokens = 4097;
                break;
            default:
                $maxtokens = 1000;
        }

        if ($this->curl === null) {
            $this->curl = curl_init();

            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_POST, true);

            curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer '.$key,
            ]);

            // Define the role of the AI assistant.
            $systemrole = 'Act as an expert producer of online language-learning materials.';

            // Set the POST fields.
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode([
                'model' => $model,
                'messages' => [
                    (object)['role' => 'system', 'content' => $systemrole],
                    (object)['role' => 'user', 'content' => $prompt],
                ],
                // We could also set ...
                // 'max_tokens' => $maxtokens.
                'temperature' => $temperature,
            ]));
        }
    }

    /**
     * get_question_category
     *
     * @param object $category the parent category
     * @param string $subcattype
     * @param string $subcatname
     * @param string $word
     * @param object $a arguments to get strings used as question category names
     * @return integer the category into which these new questions will be put
     */
    protected function get_question_category($category, $subcattype, $subcatname, $word, $a) {
        global $DB;

        // Cache the DB table name.
        $table = 'question_categories';

        // When the cattype is "none", all the questions
        // go into the given parent category.
        if ($subcattype == \vocabtool_questionbank\form::SUBCAT_NONE) {
            return $category;
        }

        // When the cattype is "single", all the questions
        // go into a category with the given name within
        // the given parent category.
        if ($subcattype == \vocabtool_questionbank\form::SUBCAT_SINGLE) {
            return $this->get_question_subcategory($table, 'singlecategory', $a, $category, $subcatname);
        }

        // Otherwise, we treat everything else as SUBCAT_AUTOMATIC.
        // This means creating a hierarchy of question categories:
        // course -> "Vocabulary" -> word -> qtype -> qlevel.
        $strnames = ['vocab', 'vocabword', 'vocabwordtype', 'vocabwordtypelevel'];
        foreach ($strnames as $strname) {
            $category = $this->get_question_subcategory($table, $strname, $a, $category);
        }
        return $category;
    }

    /**
     * get_question_subcategory
     *
     * @param string $table the table to be search in the Moodle $DB
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
     * @param string $text
     * @param string $qformat
     * @param string $categoryid
     * @return object to represent the
     */
    protected function parse_questions($text, $qformat, $categoryid) {
        global $CFG, $USER;

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
        $classname = "qformat_$qformat";
        if (! class_exists($classname)) {
            return null;
        }

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
                        // We should clean this text.
                        $question->name = $matches[1][$i];
                    }
                    if (empty($question->questiontext)) {
                        // We should clean this text.
                        $question->questiontext = $matches[2][$i];
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
                    // We should also add tags for this question.

                    // Set tags for word, level, language.
                    $questions[] = $question;
                }
            }
        }

        // Return either the array of questions, or FALSE if there are no questions.
        return (empty($questions) ? false : $questions);
    }

    /**
     * report_error
     *
     * @param string $error message name (in lang pack)
     * @param string $a arguments required (if any) by $error string
     * @return boolean false
     */
    public function report_error($error, $a=null) {

        // Fetch the full error message.
        $error = $this->tool->get_string("error_$error", $a);

        // Format the label.
        $label = '['.$this->tool->plugin.'] '.get_string('error');
        $label .= get_string('labelsep', 'langconfig');

        // Print the label with the error.
        mtrace($label.$error);

        // Mark this task as having failed.
        $this->set_fail_delay(1);
        \core\task\manager::adhoc_task_failed($this);

        return false;
    }
}
