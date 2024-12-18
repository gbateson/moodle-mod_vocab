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
 * Internal library of functions for mod_vocab plugin.
 *
 * @package    vocabai_chatgpt
 * @copyright  2018 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace vocabai_chatgpt;

/**
 * ai
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class ai extends \mod_vocab\aibase {
    /**
     * @var string the name of this subplugin
     */
    const SUBPLUGINNAME = 'chatgpt';

    /**
     * @var array the names of config settings that this subplugin maintains.
     */
    const SETTINGNAMES = [
        'chatgpturl', 'chatgptkey', 'chatgptmodel',
        'temperature', 'top_p',
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var array the names of settings that dates.
     */
    const DATESETTINGNAMES = [
        'sharedfrom', 'shareduntil',
    ];

    /**
     * @var string containing type of this AI subplugin
     * (see AI_TYPE_XXX constants in mod/vocab/classes/aibase.php)
     */
    public $type = self::AI_TYPE_TEXT;

    /** @var bool enable or disable trace and debugging messages during development. */
    const DEBUG = false;

    /**
     * Send a prompt to an AI assistant and get the response.
     *
     * @param string $prompt
     * @return object containing "text" and "error" properties.
     */
    public function get_response($prompt) {

        // Ensure we have the basic settings.
        if (empty($this->config->chatgpturl)) {
            return null;
        }
        if (empty($this->config->chatgptkey)) {
            return null;
        }
        if (empty($this->config->chatgptmodel)) {
            return null;
        }

        // Set the maximum number of tokens.
        // Currently this is not used.
        switch ($this->config->chatgptmodel) {
            case 'gpt-4o-mini':
            case 'gpt-4o':
            case 'gpt-4':
                $maxtokens = 8192;
                break;
            case 'gpt-3.5-turbo':
                $maxtokens = 4097;
                break;
            default:
                $maxtokens = 1000;
        }

        // If a base ChatGPT model has been tuned,
        // a chatgptmodelid will be available.
        $name = 'chatgptmodelid';
        if (! empty($this->config->$name)) {
            $model = $this->config->$name;
        } else {
            // Otherwise, we use a standard model.
            $model = $this->config->chatgptmodel;
        }

        if ($this->curl === null) {
            // Setup new Moodle curl object (see "lib/filelib.php").
            $this->curl = new \curl(['debug' => static::DEBUG]);
            $this->curl->setHeader([
                'Authorization: Bearer '.$this->config->chatgptkey,
                'Content-Type: application/json',
            ]);
        }

        if ($this->postparams === null) {

            // Define the role of the AI assistant.
            $role = 'Act as an expert creator of online language-learning materials.';

            // Set the required POST fields.
            $this->postparams = [
                'model' => $model,
                'messages' => [
                    (object)['role' => 'system', 'content' => $role],
                    (object)['role' => 'user', 'content' => $prompt],
                ],
            ];

            // Set optional POST fields.
            foreach (['temperature', 'top_p'] as $name) {
                if (empty($this->config->$name)) {
                    continue;
                }
                if (is_numeric($this->config->$name)) {
                    $this->postparams[$name] = (float)$this->config->$name;
                }
            }
        }

        // Send the prompt and get the response.
        $response = $this->curl->post(
            $this->config->chatgpturl, json_encode($this->postparams)
        );

        if ($this->curl->error) {
            return (object)['text' => '', 'error' => $response];
        } else {
            $response = json_decode($response, true); // Force array structure.
            $response = ($response['choices'][0]['message']['content'] ?? '');
            return (object)['text' => $response, 'error' => ''];
        }
    }

    /**
     * Check the prompt config and value are valid and complete.
     *
     * @param object $config the config settings for this tuning file.
     * @return bool TRUE if prompt is valid; Otherwise FALSE.
     */
    public function check_file_params($config) {

        /*
        Initially, the config object looks something like this:
        * filedescription: TOEIC Part 2: Vocabulary MC
        * fileitemid: 17
        * -------------------
        * We need to add extra fields, one by one.
        * Field names and sample values appear below.
        * -------------------
        * filename: TOEIC.Questions.Tuning.Part-2.txt
        * chatgptfileid: file-abc123xyz
        * chatgptjobid: ftjob-OXVbIQWXCFxeRPij067XmqIy
        * chatgptmodelid: gpt-4o-mini:ft-research-2024-01-01-01-23-45
        */

        if (empty($config) || empty($config->configid)) {
            return true; // No tuning file was specified.
        }

        $name = 'fileitemid';
        if (empty($config->$name)) {
            $config->$name = $config->id;

            // Add/update this config settings.
            $params = ['configid' => $config->configid, 'name' => $name];
            $this->save_config_setting($table, $params, $config->$name);
        }

        // Cache some values to make the code
        // more efficient and easier to read.
        $fs = null;
        $file = null;
        $contextid = $config->contextid;
        $component = $config->subplugin;
        $filearea = 'fileitemid';
        $itemid = $config->fileitemid;

        // Initialize error reporting string.
        $a = 'contextid = '.$contextid.', '.
             'component = '.$component.', '.
             'filearea = '.$filearea.', '.
             'itemid = '.$itemid;

        // Cache the name of the DB table
        // that stores config settings.
        $table = 'vocab_config_settings';

        $name = 'filename';
        if (empty($config->$name)) {

            $config->$name = $this->get_get_chatgpt_filename(
                $fs, $file, $contextid, $component, $filearea, $itemid
            );

            if (empty($config->$name)) {
                if (static::DEBUG) {
                    mtrace('Tuning file could no be located in the Moodle file system.');
                    mtrace("($a)");
                }
                return false;
            }

            // Add/update this config settings.
            $params = ['configid' => $config->configid, 'name' => $name];
            $this->save_config_setting($table, $params, $config->$name);
        }

        if (static::DEBUG) {
            $a .= ", {$name} = {$config->$name}";
            mtrace("{$name}: {$config->$name}");
        }

        $name = 'chatgptfileid';
        if (empty($config->$name)) {

            // Send the file to the ChatGPT file repository and get the fileid.
            $config->$name = $this->get_chatgpt_fileid(
                $fs, $file, $contextid, $component, $filearea, $itemid, $config->filename
            );

            if (empty($config->$name)) {
                if (static::DEBUG) {
                    mtrace('ChatGPT file id could not be created for tuning file.');
                    mtrace("($a)");
                }
                return false;
            }

            // Add/update this config settings.
            $params = ['configid' => $config->configid, 'name' => $name];
            $this->save_config_setting($table, $params, $config->$name);
        }

        if (static::DEBUG) {
            $a .= ", {$name} = {$config->$name}";
            mtrace("{$name}: {$config->$name}");
        }

        $name = 'chatgptjobid';
        if (empty($config->$name)) {

            // Set up a new tuning job using this tuning file.
            $config->$name = $this->get_chatgpt_jobid($config->chatgptfileid);

            if (empty($config->$name)) {
                if (static::DEBUG) {
                    mtrace('ChatGPT model id could no be created for tuning file.');
                    mtrace("($a)");
                }
                return false;
            }

            // Add/update this config settings.
            $params = ['configid' => $config->configid, 'name' => $name];
            $this->save_config_setting($table, $params, $config->$name);
        }

        if (static::DEBUG) {
            $a .= ", {$name} = {$config->$name}";
            mtrace("{$name}: {$config->$name}");
        }

        $name = 'chatgptmodelid';
        if (empty($config->$name) || $config->$name == static::RESCHEDULE_ADHOC_TASK) {

            $config->$name = $this->get_chatgpt_modelid($config->chatgptjobid);
            // NOTE: If the modelid could not be retrieved
            // because the tuning job is still in progress,
            // then the modelid will be set to "reschedule-adhoc-task".

            if (empty($config->$name)) {
                if (static::DEBUG) {
                    mtrace('ChatGPT model id could no be created for tuning file.');
                    mtrace("($a)");
                }
                return false;
            }

            // Add/update this config settings.
            $params = ['configid' => $config->configid, 'name' => $name];
            $this->save_config_setting($table, $params, $config->$name);
        }

        if (static::DEBUG) {
            $a .= ", {$name} = {$config->$name}";
            mtrace("{$name}: {$config->$name}");
        }
        return true;
    }

    /**
     * get_get_chatgpt_filename
     *
     * @param file_storage $fs
     * @param stored_file $file
     * @param int $contextid
     * @param string $component e.g. vocabai_files
     * @param string $filearea e.g. fileitemid
     * @param int $itemid
     * @return string the name of the tuning file or empty string if the file was not found.
     */
    public function get_get_chatgpt_filename($fs, $file, $contextid, $component, $filearea, $itemid) {
        if ($file === null) {
            if ($fs === null) {
                $fs = get_file_storage();
            }
            $file = $fs->get_area_files(
                $contextid, $component, $filearea, $itemid,
                'itemid, filepath, filename', // Sort fields.
                false, // Override default for $includedirs.
                0, // Use default for $updatedsince.
                0, // Use default for $limitfrom.
                1, // Override default for $limitnum.
            );
            if ($file) {
                $file = reset($file); // There should be only one.
            }
        }
        if (empty($file)) {
            return ''; // Shouldn't happen !!
        }
        return $file->get_filename();
    }

    /**
     * get_chatgpt_fileid
     *
     * @param file_storage $fs
     * @param stored_file $file
     * @param int $contextid
     * @param string $component e.g. vocabai_files
     * @param string $filearea e.g. fileitemid
     * @param int $itemid
     * @param string $filename
     * @return string ChatGPT file id string or empty string if no file id was returned.
     */
    public function get_chatgpt_fileid($fs, $file, $contextid, $component, $filearea, $itemid, $filename) {

        if ($file === null) {
            if ($fs === null) {
                $fs = get_file_storage();
            }
            $file = $fs->get_file($contextid, $component, $filearea, $itemid, '/', $filename);
        }
        if (empty($file)) {
            mtrace('Oops, the tuning file has disappeared from the Moodle file system.');
            mtrace("get_file($contextid, '$component', '$filearea', $itemid, '/$filename')");
            return '';
        }

        // Derive the "files" url from the "chat" url.
        $url = $this->get_chatgpt_endpoint_url('/files');

        // Setup new Moodle curl object (see "lib/filelib.php").
        $curl = new \curl(['debug' => static::DEBUG]);
        $curl->setHeader([
            'Authorization: Bearer '.$this->config->chatgptkey,
            'Content-Type: multipart/form-data',
        ]);

        $params = ['purpose' => 'fine-tune', 'file' => $file];
        $response = $curl->post($url, $params);

        if ($curl->error) {
            // Oops there was some kind of error.
            mtrace($curl->error);
            return '';
        }

        // We expect the response to be an json-encoded array.
        $response = json_decode($response, true);

        if (empty($response['id'])) {
            // Oops, $response does not contain the file id!
            mtrace('Oops, CURL response does not contain a file id');
            self::mtrace_object($response);
            return '';
        }

        // As expected, the response contains the fileid. Yay!
        return $response['id'];
    }

    /**
     * get_chatgpt_jobid
     *
     * @param string $fileid e.g. file-abc123xyz
     * @return string ChatGPT model id string or empty string if no model id was returned.
     */
    public function get_chatgpt_jobid($fileid) {

        // Get the URL of the "fine_tuning" endpoint.
        $url = $this->get_chatgpt_fine_tuning_url();

        // Ensure we are using only one of the models that allows fine-tuning.
        // https://platform.openai.com/docs/guides/fine-tuning.
        $model = $this->config->chatgptmodel;
        switch (true) {
            case (preg_match('/^gpt-4o-mini\b/', $model)):
                $model = 'gpt-4o-mini-2024-07-18';
                break;

            case (preg_match('/^gpt-4o\b/', $model)):
                $model = 'gpt-4o-2024-08-06';
                break;

            case (preg_match('/^gpt-4\b/', $model)):
                $model = 'gpt-4-0613';
                break;

            case (preg_match('/^gpt-3.5\b/', $model)):
                $model = 'gpt-3.5-turbo-1106';
                break;
        }

        // Setup new Moodle curl object (see "lib/filelib.php").
        $curl = new \curl(['debug' => static::DEBUG]);
        $curl->setHeader([
            'Authorization: Bearer '.$this->config->chatgptkey,
            'Content-Type: application/json',
        ]);

        $params = [
            'training_file' => $fileid,
            'model' => $model,
            'suffix' => null, // Set this to the filename?
        ];
        $response = $curl->post($url, json_encode($params));

        if ($curl->error) {
            // Oops there was some kind of error.
            mtrace($curl->error);
            return '';
        }

        // We expect the response to be an json-encoded array.
        $response = json_decode($response, true);

        // This sampleresponse is not actually required,
        // but if we comment it out, we get errors
        // from the Moodle PHP code checker.
        $sampleresponse = (object)[
            'object' => 'fine_tuning.job',
            'id' => 'ftjob-OXVbIQWXCFxeRPij067XmqIy',
            'model' => 'gpt-4o-mini-2024-07-18',
            'created_at' => 1734166903,
            'finished_at' => null,
            'fine_tuned_model' => null,
            'organization_id' => 'org-Xdt6uPOQJnpdMEWaVl85JYgX',
            'result_files' => [],
            'status' => 'validating_files',
            'validation_file' => null,
            'training_file' => 'file-6PYzDtU52fPFWzpDZEfeko',
            'hyperparameters' => (object)[
                'n_epochs' => 'auto',
                'batch_size' => 'auto',
                'learning_rate_multiplier' => 'auto',
            ],
            'trained_tokens' => null,
            'error' => (object)[],
            'user_provided_suffix' => null,
            'seed' => 1833384501,
            'estimated_finish' => null,
            'integrations' => [],
        ];

        if (empty($response['id'])) {
            // Oops, $response does not contain the job id!
            mtrace('Oops, CURL response does not contain a job id');
            self::mtrace_object($response);
            return '';
        }

        // As expected, the response contains the jobid. Yay!
        return $response['id'];
    }

    /**
     * get_chatgpt_modelid
     *
     * @param string $jobid e.g. ftjob-OXVbIQWXCFxeRPij067XmqIy
     * @return string id of the tuned ChatGPT model e.g.
     */
    public function get_chatgpt_modelid($jobid) {

        // Get the URL of the "fine_tuning" endpoint for this model.
        // If we append the job-id, the API will return the status of the job.
        $url = $this->get_chatgpt_fine_tuning_url("/$jobid");

        // Setup new Moodle curl object (see "lib/filelib.php").
        $curl = new \curl(['debug' => static::DEBUG]);
        $curl->setHeader([
            'Authorization: Bearer '.$this->config->chatgptkey,
            'Content-Type: application/json',
        ]);

        // Note: We use GET here because the job-id is passed
        // as part of the url and no POST parameters are required.
        $response = $curl->get($url);

        if ($curl->error) {
            // Oops there was some kind of error.
            mtrace($curl->error);
            return '';
        }

        // We expect the response to be an json-encoded array.
        $response = json_decode($response, true);

        // This sampleresponse is not actually required,
        // but if we comment it out, we get errors
        // from the Moodle PHP code checker.
        $sampleresponse = (object)[
            'object' => 'fine_tuning.job',
            'id' => 'ftjob-OXVbIQWXCFxeRPij067XmqIy',
            'model' => 'gpt-4o-mini-2024-07-18',
            'created_at' => 1734166903,
            'finished_at' => 1734167337,
            'fine_tuned_model' => 'ft:gpt-4o-mini-2024-07-18:research::AeIQs9fy',
            'organization_id' => 'org-Xdt6uPOQJnpdMEWaVl85JYgX',
            'result_files' => ['file-NupXuJQEpaqfGjpj2BEAdX'],
            'status' => 'succeeded',
            'validation_file' => null,
            'training_file' => 'file-6PYzDtU52fPFWzpDZEfeko',
            'hyperparameters' => (object)[
                'n_epochs' => 10,
                'batch_size' => 1,
                'learning_rate_multiplier' => 1.8,
            ],
            'trained_tokens' => 17090,
            'error' => (object)[],
            'user_provided_suffix' => null,
            'seed' => 1833384501,
            'estimated_finish' => null,
            'integrations' => [],
        ];

        // Extract status of tuning job.
        if (empty($response['status'])) {
            $status = ''; // Shouldn't happen!
        } else {
            $status = $response['status'];
        }

        switch ($status) {

            case 'succeeded':
                if (empty($response['fine_tuned_model'])) {
                    mtrace('Oops, CURL response does not contain a model id');
                    self::mtrace_object($response);
                    return '';
                }
                return $response['fine_tuned_model'];

            case 'queued':
            case 'validating_files':
            case 'running':
                // Tuning job needs more time to complete,
                // so reschedule the adhoc job to run again later.
                return static::RESCHEDULE_ADHOC_TASK;

            case 'failed':
            case 'cancelled':
                mtrace("Oops, tuning job status is $status.");
                self::mtrace_object($response);
                return '';

            default:
                mtrace('Oops, tuning job returned unknown status: '.$status);
                self::mtrace_object($response);
                return '';
        }
    }

    /**
     * Get the URL of the of a particular endpoint in the ChatGPT API.
     * We assume the chatgpturl is that of the "chat" endpoint,
     * i.e. https://api.openai.com/v1/chat/completions.
     *
     * @param string $suffix (optional, default="")
     * @return string URL of the fine-tuning endpoint
     */
    public function get_chatgpt_fine_tuning_url($suffix='') {
        $url = $this->get_chatgpt_endpoint_url('/fine_tuning/jobs');
        return $url.$suffix;
    }

    /**
     * Get the URL of the of a particular endpoint in the ChatGPT API.
     * We assume the chatgpturl is that of the "chat" endpoint,
     * i.e. https://api.openai.com/v1/chat/completions.
     *
     * @param string $endpoint whose URL we wish to know.
     * @return string URL of the specified endpoint
     */
    public function get_chatgpt_endpoint_url($endpoint) {
        $url = $this->config->chatgpturl;
        return preg_replace('|/chat/.*$|', $endpoint, $url, 1);
    }

    /**
     * Should we reschedule the Moodle adhoc_task to run again later.
     *
     * @param object $promptconfig the config settings for the prompt
     * @param object $formatconfig the config settings for the output format
     * @param object $fileconfig the config settings for the AI tuning file
     * @return bool TRUE if the adhoc task should be rescheduled; otherwise FALSE.
     */
    public function reschedule_task($promptconfig, $formatconfig, $fileconfig) {
        $name = 'chagptmodelid';
        if (empty($fileconfig->$name)) {
            return false;
        } else {
            return ($fileconfig->$name == static::RESCHEDULE_ADHOC_TASK);
        }
    }
}
