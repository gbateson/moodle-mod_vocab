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
 * tool/import/classes/form.php
 *
 * @package    vocabtool_import
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

namespace vocabtool_import;

/**
 * form
 *
 * @package    vocabtool_import
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    /** @var string the name of this plugin */
    public $subpluginname = 'vocabtool_import';

    /** @var int internal value to represent "Add new only" action */
    const ACTION_ADD_NEW_ONLY = 1;

    /** @var int internal value to represent "Add and update" action */
    const ACTION_ADD_AND_UPDATE = 2;

    /** @var int internal value to represent "Update existing" action */
    const ACTION_UPDATE_EXISTING = 3;

    /** @var int internal value to represent "Add, update and remove" action */
    const ACTION_ADD_UPDATE_REMOVE  = 4;

    /** @var int internal value to represent "Select none" */
    const SELECT_NONE = 0;

    /** @var int internal value to represent "Select all" */
    const SELECT_ALL  = 1;

    /** @var int internal value to represent "Select new" */
    const SELECT_NEW  = 2;

    /** @var int internal value to represent "Neither meta nor data row" */
    const TYPE_NONE = 0;

    /** @var int internal value to represent "Meta rows" e.g. headings */
    const TYPE_META = 1;

    /** @var int internal value to represent "Data rows" */
    const TYPE_DATA = 2;

    /** @var int internal value to represent "Dry run mode" */
    const MODE_DRYRUN = 1;

    /** @var int internal value to represent "Import mode" */
    const MODE_IMPORT = 2;

    /** @var string the corrent form state */
    protected $formstate = '';

    /** @var object representing the import data file */
    protected $phpspreadsheet = null;

    /** @var bool TRUE if branch pruning is available (Excel on Moodle >= 4.x), otherwise FALSE */
    protected $phpbranchpruning = null;

    /** @var array to hold punctuation characters */
    protected $punctuation = null;

    /** @var array of cell values to ignore. e.g. "N/A" */
    public $ignorevalues = null;

    /**
     * @var array to map aliases (e.g. 'ALIAS_ROW_99') to non-scalar values
     * (i.e. arrays and objects) that are passed as parameters to functions
     */
    protected $aliases = [];

    /** @var array of vocab tables that have been updated by this import tool */
    protected $totals = null;

    /** @var int index on current sheet data file */
    protected $currentsheet = 0;

    /** @var int Index on current row data file */
    protected $currentrow = 0;

    /** @var string name of current sheet in data file */
    protected $currentsheetname = '';

    /** @var string name of current row in data file */
    protected $currentrowname = '';

    /**
     * constructor
     *
     * @uses $CFG
     * @param integer $action (optional, default=null)
     * @param array $customdata (optional, default=null)
     * @param string $method (optional, default='post')
     * @param string $target (optional, default='')
     * @param array $attributes (optional, default=null)
     * @param bool $editable (optional, default=true)
     *
     * TODO: Finish documenting this function
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        global $CFG;

        // Get a valid form state.
        $states = ['upload', 'preview', 'review', 'import'];
        $this->formstate = self::get_optional_param('formstate', '', PARAM_ALPHA);
        if (in_array($this->formstate, $states) == false) {
            // Use the initial state as the default state.
            $this->formstate = reset($states);
        }

        // Detect "Cancel" or "Back" button.
        if (self::get_optional_param('cancel', 0, PARAM_RAW)) {
            $i = array_search($this->formstate, $states);
            if ($i >= 2) {
                $this->formstate = $states[$i - 2];
            } else {
                $this->formstate = 'cancelled';
            }
        }

        // Check for new PhpExcel (Moodle >= 3.8).
        $this->phpspreadsheet = file_exists($CFG->dirroot.'/lib/phpspreadsheet');

        // Check for branch pruning on the Excel calculation engine.
        $class = '\PhpOffice\PhpSpreadsheet\Calculation\Engine\BranchPruner';
        $this->phpbranchpruning = class_exists($class);

        // Continue with normal setup.
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * definition
     *
     *
     * TODO: Finish documenting this function
     */
    public function definition() {
        $mform = $this->_form;
        $this->set_form_id($mform);

        $submit = '';
        $cancel = '';
        switch ($this->formstate) {

            case 'upload':
                $this->set_next_formstate($mform, 'preview');
                list($submit, $cancel) = $this->definition_upload($mform);
                break;

            case 'preview':
                $this->set_next_formstate($mform, 'review');
                list($submit, $cancel) = $this->definition_preview($mform);
                break;

            case 'review':
                $this->set_next_formstate($mform, 'import');
                list($submit, $cancel) = $this->definition_review($mform);
                break;
        }

        $this->definition_buttons($mform, $submit, $cancel);
    }

    /**
     * definition_upload
     *
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function definition_upload($mform) {

        $name = 'datafile';
        $params = ['.xlsx', '.xls', '.ods']; // Could also offer '.csv', '.txt'.
        $params = ['required' => 1, 'accepted_types' => $params];
        $this->add_field_filepicker($mform, $name, null, $params);

        $name = 'formatfile';
        $params = ['accepted_types' => ['.xml']];
        $this->add_field_filepicker($mform, $name, null, $params);

        $name = 'previewrows';
        $options = [1, 2, 5, 10, 15, 20, 50, 100, 1000, 100000];
        $options = array_combine($options, $options);
        $this->add_field_select($mform, $name, $options, PARAM_INT, 5);

        return ['preview', 'cancel'];
    }

    /**
     * definition_preview
     *
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function definition_preview($mform) {

        // Transfer values from "upload" form.
        $values = [
            'datafile' => PARAM_INT,
            'formatfile' => PARAM_INT,
            'previewrows' => PARAM_INT,
        ];
        $this->transfer_incoming_values($mform, $values);

        $this->add_heading($mform, 'settings', true);

        // Give user (another) chance to specify ignore values.
        $name = 'ignorevalues';
        $this->add_field_text($mform, $name, PARAM_TEXT, '', 64);

        $name = 'uploadaction';
        $options = $this->get_options_uploadaction();
        $default = self::ACTION_ADD_AND_UPDATE;
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default);

        return ['review', 'back'];
    }

    /**
     * get_options_uploadaction
     *
     * @param string $value (optional, default=null)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_options_uploadaction($value=null) {
        $options = [
            self::ACTION_ADD_NEW_ONLY => $this->get_string('actionaddnewonly'),
            self::ACTION_ADD_AND_UPDATE => $this->get_string('actionaddandupdate'),
            self::ACTION_UPDATE_EXISTING => $this->get_string('actionupdateexisting'),
            self::ACTION_ADD_UPDATE_REMOVE => $this->get_string('actionaddupdateremove'),
        ];
        if ($value === null) {
            return $options;
        }
        if (array_key_exists($value, $options)) {
            return $options[$value];
        }
        return $value; // Unkonwn value for uploadaction.
    }

    /**
     * definition_review
     *
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function definition_review($mform) {
        $values = [
            'datafile' => PARAM_INT,
            'formatfile' => PARAM_INT,
            'previewrows' => PARAM_INT,
            'ignorevalues' => PARAM_TEXT,
        ];
        $this->transfer_incoming_values($mform, $values);
        return ['import', 'back'];
    }

    /**
     * definition_buttons
     *
     * @param moodleform $mform representing the Moodle form
     * @param bool $submit
     * @param bool $cancel
     *
     * TODO: Finish documenting this function
     */
    public function definition_buttons($mform, $submit, $cancel) {
        if ($submit && $cancel) {
            $name = 'buttons';
            $mform->addGroup([
                $mform->createElement('submit', 'submit', $this->get_string($submit)),
                $mform->createElement('cancel', 'cancel', get_string($cancel)),
            ], $name, '', [' '], false);
            $mform->closeHeaderBefore($name);
        } else if ($submit) {
            $mform->addElement('submit', 'submit', $this->get_string($submit));
        } else if ($cancel) {
            $mform->addElement('cancel', 'cancel', get_string($cancel));
        }
    }

    /**
     * get_state
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_state() {
        return $this->formstate;
    }

    /**
     * transfer_incoming_values
     *
     * @param moodleform $mform representing the Moodle form
     * @param array $values numbers to be displayed as a pie-chart
     *
     * TODO: Finish documenting this function
     */
    public function transfer_incoming_values($mform, $values) {
        foreach ($values as $name => $type) {
            if ($type == PARAM_INT) {
                $default = 0;
            } else {
                $default = '';
            }
            $value = self::get_optional_param($name, $default, $type);
            $mform->addElement('hidden', $name, $value);
            $mform->setType($name, $type);
        }
    }

    /**
     * set_next_formstate
     *
     * @param moodleform $mform representing the Moodle form
     * @param string $value
     *
     * TODO: Finish documenting this function
     */
    public function set_next_formstate($mform, $value) {
        $name = 'formstate';
        unset($_POST[$name]);
        $mform->addElement('hidden', $name, $value);
        $mform->setType($name, PARAM_ALPHA);
    }

    /**
     * validation
     *
     * @uses $USER
     * @param stdClass $data submitted from the form
     * @param array $files
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function validation($data, $files) {

        $errors = parent::validation($data, $files);

        // For an example of what to do if expected files are missing,
        // see mod/book/tool/importhtml/import_form.php.

        return $errors;
    }

    /**
     * render_data_table
     *
     * @uses $CFG
     * @uses $USER
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function render_data_table() {
        global $CFG, $USER;

        // Get the path to main PHPExcel file and object.
        if ($this->phpspreadsheet) {
            // Moodle >= 3.8.
            $filepath = $CFG->dirroot.'/lib/phpspreadsheet/vendor/autoload.php';
            $iofactory = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';
        } else {
            // Moodle 2.5 - 3.7.
            $filepath = $CFG->dirroot.'/lib/phpexcel/PHPExcel/IOFactory.php';
            $iofactory = 'PHPExcel_IOFactory';
        }
        require_once($filepath);

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);

        list($datafilename, $datafilepath) = $this->get_datafileinfo($fs, $context, 'datafile');
        list($formatfilename, $formatfilecontent) = $this->get_formatfileinfo($fs, $context, 'formatfile');
        $format = $this->parse_format_xml($formatfilecontent, 'datafileformat');

        if (is_string($format)) {
            // This is probably an error - shouldn't happen !!
            $table = $format;
        } else {
            $table = new \html_table();
            $table->head = [];
            $table->data = [];

            if ($datafilepath) {

                // Add datafilename to settings so that it is accessible later.
                if ($format) {
                    $format->settings['datafilename'] = $datafilename;
                }

                $reader = $iofactory::createReaderForFile($datafilepath);
                $workbook = $reader->load($datafilepath);

                // Disable Branch Pruning on Moodle >= 4.x.
                if ($this->phpbranchpruning) {
                    $workbook->getCalculationEngine()->disableBranchPruning();
                }

                if ($format === null) {
                    $format = $this->create_format_xml($workbook, $datafilename);
                    $table = \html_writer::tag('p', $this->get_string('emptyxmlfile').' '.
                                                    $this->get_string('showsampleformatxml'));
                    $params = ['class' => 'rounded bg-dark text-white px-2 py-1'];
                    $table .= \html_writer::tag('pre', htmlspecialchars($format, ENT_COMPAT), $params);
                } else {
                    $table->tablealign = 'center';
                    $table->id = $this->subpluginname.'_'.$this->formstate;
                    $table->attributes['class'] = 'generaltable '.$this->subpluginname;
                    $table->summary = $this->get_string($this->formstate);

                    list($totalsheets, $totalrows) = $this->get_sheetcount_rowcount($workbook);
                    list($targetsheets, $targetrows) = $this->get_sheetcount_rowcount($workbook, $format);
                    $table->caption = $this->render_caption(
                        $datafilename, $totalsheets, $totalrows,
                        $formatfilename, $targetsheets, $targetrows
                    );

                    $populate = 'populate_'.$this->formstate.'_table';
                    $this->$populate($workbook, $format, $table);

                    if (empty($table->data)) {
                        // No data found - shouldn't happen!!
                        $table = $this->get_string('emptydatafile');
                    }
                }
            }
        }

        if (is_object($table)) {
            $table = \html_writer::table($table);
            $table = \html_writer::tag('div', $table, ['class' => 'flexible-wrap']);
        } else {
            $table = \html_writer::tag('p', $table).
                     \html_writer::tag('p', $this->get_string('tryagain'));
            $table = \html_writer::tag('div', $table, ['class' => 'alert alert-warning']);
        }

        if ($datafilepath) {
            unlink($datafilepath);
        }

        return $table;
    }

    /**
     * get_datafileinfo
     *
     * @param object $fs reference to Moodle file storage singleton object
     * @param object $context a record form the "context" table in the Moodle database
     * @param string $paramname
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_datafileinfo($fs, $context, $paramname) {
        $filename = '';
        $filepath = '';
        if ($draftid = self::get_optional_param($paramname, 0, PARAM_INT)) {
            $file = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false);
            if (count($file)) {
                $file = reset($file);
                $filename = $file->get_filename();
                $filetype = substr($filename, strrpos($filename, '.'));
                if ($dir = make_temp_directory('forms')) {
                    if ($filepath = tempnam($dir, 'tempup_')) {
                        rename($filepath, $filepath.$filetype);
                        $filepath .= $filetype;
                        $file->copy_content_to($filepath);
                    }
                }
            }
        } else if (array_key_exists($name, $_FILES)) {
            $filename = $_FILES[$paramname]['name'];
            $filepath = $_FILES[$paramname]['tmp_name'];
        }
        return [$filename, $filepath];
    }

    /**
     * get_formatfileinfo
     *
     * @param object $fs reference to Moodle file storage singleton object
     * @param object $context a record form the "context" table in the Moodle database
     * @param string $paramname
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_formatfileinfo($fs, $context, $paramname) {
        $filename = '';
        $filecontent = '';
        if ($draftid = self::get_optional_param($paramname, 0, PARAM_INT)) {
            $file = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false);
            if (count($file)) {
                $file = reset($file);
                $filename = $file->get_filename();
                $filecontent = $file->get_content();
            }
        } else if (array_key_exists($paramname, $_FILES)) {
            $filename = $_FILES[$paramname]['name'];
            $filecontent = $_FILES[$paramname]['tmp_name'];
            $filecontent = file_get_contents($filecontent);
        }
        return [$filename, $filecontent];
    }

    /**
     * parse_format_xml
     *
     * @uses $CFG
     * @param string $formatfilecontent
     * @param string $xmlroot
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function parse_format_xml($formatfilecontent, $xmlroot) {
        global $CFG;

        // Get XML parsing library.
        require_once($CFG->dirroot.'/lib/xmlize.php');

        if (empty($formatfilecontent)) {
            // This shouldn't happen. We could return an error
            // message such as $this->get_string('emptyxmlfile').
            return null;
        }

        $xml = xmlize($formatfilecontent);
        if (empty($xml)) {
            return $this->get_string('invalidxmlfile');
        }

        $name = $xmlroot;
        if (empty($xml[$name]) || empty($xml[$name]['#'])) {
            return get_string('xmltagmissing', $this->subpluginname, htmlspecialchars("<$xmlroot>", ENT_COMPAT));
        }
        if (empty($xml[$name]['#']['sheet'])) {
            return get_string('xmltagmissing', $this->subpluginname, htmlspecialchars("<sheet>", ENT_COMPAT));
        }

        // Initialize the main $format object.
        $format = new \stdClass();

        // Setup sheets, settings and records for this file.
        $this->parse_format_xml_initnode($xml[$xmlroot], $format, 'sheets');

        $s = 0;
        $sheet = $xml[$xmlroot]['#']['sheet'];
        while (is_array($sheet) && array_key_exists($s, $sheet)) {

            // Setup rows, settings and records for this sheet.
            $sindex = count($format->sheets);
            $format->sheets[$sindex] = new \stdClass();
            $this->parse_format_xml_initnode($sheet[$s], $format->sheets[$sindex], 'rows');

            // Add the rows for this sheet.
            $r = 0;
            $row = &$sheet[$s]['#']['row'];
            while (array_key_exists($r, $row)) {

                // Setup cells, settings and records for this row.
                $rindex = count($format->sheets[$sindex]->rows);
                $format->sheets[$sindex]->rows[$rindex] = new \stdClass();
                $this->parse_format_xml_initnode($row[$r], $format->sheets[$sindex]->rows[$rindex], 'cells');

                $c = 0;
                $cell = &$row[$r]['#']['cell'];
                while (array_key_exists($c, $cell)) {
                    $cindex = count($format->sheets[$sindex]->rows[$rindex]->cells);
                    $format->sheets[$sindex]->rows[$rindex]->cells[$cindex] = $cell[$c]['#'];
                    $c++;
                }
                unset($c, $cell);
                $r++;
            }
            unset($r, $row);
            $s++;
        }
        unset($s, $sheet);

        return $format;
    }

    /**
     * parse_format_xml_initnode
     *
     * @param string $xml (passed by reference)
     * @param object $format
     * @param string $name
     *
     * TODO: Finish documenting this function
     */
    public function parse_format_xml_initnode(&$xml, $format, $name) {

        if (empty($xml['@'])) {
            $xml['@'] = [];
        }
        if (empty($xml['#'])) {
            $xml['#'] = [];
        }

        if (empty($xml['#']['setting'])) {
            $xml['#']['setting'] = [];
        }
        if (empty($format->settings)) {
            $format->settings = [];
        }
        $this->parse_format_xml_settings($xml, $format);

        if (empty($format->$name)) {
            $format->$name = [];
        }

        if (empty($xml['#']['record'])) {
            $xml['#']['record'] = [];
        }
        if (empty($format->records)) {
            $format->records = [];
        }
        $this->parse_format_xml_records($xml, $format);
    }

    /**
     * parse_format_xml_settings
     *
     * @param string $xml (passed by reference)
     * @param object $format
     *
     * TODO: Finish documenting this function
     */
    public function parse_format_xml_settings(&$xml, $format) {

        // Add params to the array of "settings".
        foreach ($xml['@'] as $name => $value) {
            $format->settings[$name] = $value;
        }

        // Add settings.
        $s = 0;
        $setting = &$xml['#']['setting'];
        while (array_key_exists($s, $setting)) {
            $name = $setting[$s]['#']['name'][0]['#'];
            $value = $setting[$s]['#']['value'][0]['#'];
            $format->settings[$name] = $value;
            $s++;
        }
        unset($s, $setting);
    }

    /**
     * parse_format_xml_records
     *
     * @param string $xml (passed by reference)
     * @param object $format
     *
     * TODO: Finish documenting this function
     */
    public function parse_format_xml_records(&$xml, $format) {

        // Initialize the index on records in the $format object.
        $rindex = count($format->records);

        // Add records and record items.
        $record = &$xml['#']['record'];
        $r = 0;
        while (array_key_exists($r, $record)) {

            // Initialize the structure for this record.
            $format->records[$rindex] = (object)[
                'table' => '',
                'fields' => [],
            ];

            // Add any params for this this record.
            // We expect at least the table name here.
            // We may also get a "skip" condition.
            foreach ($record[$r]['@'] as $name => $value) {
                $format->records[$rindex]->$name = $value;
            }

            $field = &$record[$r]['#']['field'];
            $f = 0;
            while (array_key_exists($f, $field)) {

                $name = $field[$f]['#']['name'][0]['#'];
                $value = $field[$f]['#']['value'][0]['#'];
                $format->records[$rindex]->fields[$name] = $value;
                $f++;
            }
            unset($f, $field);

            $rindex++;
            $r++;
        }
        unset($r, $record);
    }

    /**
     * create_format_xml
     *
     * @param object $workbook
     * @param string $datafilename
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function create_format_xml($workbook, $datafilename) {
        $nl = "\n";
        $tab = str_repeat(' ', 4);
        $i = 1; // The indent counter.

        $coffset = ($this->phpspreadsheet ? 1 : 0); // The column offset.

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$nl;
        $xml .= '<datafileformat type="'.$this->get_format_type($datafilename).'">'.$nl;

        $xml .= $nl;
        $xml .= str_repeat($tab, $i).$this->get_comment('explainsettings').$nl;

        // Set default form settings.
        $setting = (object)[
            'name' => 'uploadaction',
            'value' => self::ACTION_ADD_AND_UPDATE,
            'comment' => $this->get_options_uploadaction(self::ACTION_ADD_AND_UPDATE),
        ];
        $settings = [$setting];

        // Add XML for settings.
        foreach ($settings as $setting) {
            $xml .= str_repeat($tab, $i++).'<setting>'.$nl;
            if (isset($setting->comment)) {
                $xml .= str_repeat($tab, $i).'<!-- '.$setting->comment.' -->'.$nl;
            }
            $xml .= str_repeat($tab, $i).'<name>'.$setting->name.'</name>'.$nl;
            $xml .= str_repeat($tab, $i).'<value>'.$setting->value.'</value>'.$nl;
            $xml .= str_repeat($tab, --$i).'</setting>'.$nl;
        }

        $smin = 1;
        $smax = $workbook->getSheetCount();

        for ($s = $smin; $s <= $smax; $s++) {
            $worksheet = $workbook->setActiveSheetIndex($s - 1);

            $xml .= $nl;
            $xml .= str_repeat($tab, $i).$this->get_comment('explainmeta', 'sheet').$nl;
            $xml .= str_repeat($tab, $i).$this->get_comment('explaindata', 'sheet').$nl;
            $xml .= str_repeat($tab, $i).$this->get_comment('explainstartend', 'sheet').$nl;
            $xml .= str_repeat($tab, $i++).'<sheet sheettype="data" sheetstart="'.$s.'" sheetend="'.$s.'">'.$nl;

            list($rmin, $rmax) = $this->get_min_max_rows($worksheet);

            $xmlmetarow = '';
            $xmldatarow = '';
            for ($r = $rmin; $r <= $rmax; $r++) {

                list($cmin, $cmax) = $this->get_min_max_cols($worksheet, $r);

                if ($cmin < $cmax) {
                    $xmlmetarow .= $nl.
                       str_repeat($tab, $i).$this->get_comment('explainmeta', 'row').$nl.
                       str_repeat($tab, $i).$this->get_comment('explainstartend', 'row').$nl.
                       str_repeat($tab, $i).'<row rowtype="meta" rowstart="'.$r.'" rowend="'.$r.'">'.$nl;

                    $xmldatarow .= $nl.
                       str_repeat($tab, $i).$this->get_comment('explaindata', 'row').$nl.
                       str_repeat($tab, $i).$this->get_comment('explainstartend', 'row').$nl.
                       str_repeat($tab, $i).$this->get_comment('explainname', 'row').$nl.
                       str_repeat($tab, $i).$this->get_comment('explainskip', 'row').$nl.
                       str_repeat($tab, $i).'<row rowtype="data"'.
                                            ' rowstart="'.($r + 1).'" '.'rowend="'.$rmax.'"'.
                                            ' cellstart="'.($cmin + 1).'" cellend="'.($cmax + 1).'"'.
                                            '>'.$nl;

                    // Increase indentation for cells.
                    $i++;

                    $cells = [];
                    for ($c = $cmin; $c <= $cmax; $c++) {
                        $value = $this->get_singleline_value($worksheet, $c, $r);
                        $cleanvalue = strtolower($this->get_clean_text($value));
                        $xmlmetarow .= str_repeat($tab, $i).'<cell>'.$value.'</cell>'.$nl;
                        $xmldatarow .= str_repeat($tab, $i).'<cell>'.$cleanvalue.'</cell>'.$nl;
                    }

                    $xmlmetarow .= str_repeat($tab, --$i).'</row>'.$nl;
                    $xmldatarow .= str_repeat($tab, $i).'</row>'.$nl;

                    break; // Stop looping through the rows.
                }
            }

            // Add meta and data row.
            $xml .= $xmlmetarow;
            $xml .= $xmldatarow;

            $xml .= str_repeat($tab, --$i).'</sheet>'.$nl;
        }

        $xml .= '</datafileformat>'.$nl;

        return $xml;
    }

    /**
     * get_comment
     *
     * @param string $strname
     * @param array $a additional value or values required for the language string (optional, default=null)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_comment($strname, $a=null) {
        return '<!-- '.$this->get_string($strname, $a).' -->';
    }

    /**
     * get_min_max_rows
     *
     * @param object $worksheet representing a sheet from the data file
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_min_max_rows($worksheet) {
        $rmin = 0;
        $rmax = $worksheet->getHighestDataRow();

        for ($r = $rmin; $r <= $rmax; $r++) {
            list($cmin, $cmax) = $this->get_min_max_cols($worksheet, $r);
            if ($cmin < $cmax) {
                $rmin = $r;
                break;
            }
        }

        for ($r = $rmax; $r >= $rmin; $r--) {
            list($cmin, $cmax) = $this->get_min_max_cols($worksheet, $r);
            if ($cmin < $cmax) {
                $rmax = $r;
                break;
            }
        }
        return [$rmin, $rmax];
    }

    /**
     * get_min_max_cols
     *
     * @param object $worksheet representing a sheet from the data file
     * @param integer $r a row number
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_min_max_cols($worksheet, $r) {

        $cmin = 0;
        $cmax = $worksheet->getHighestDataColumn();
        $cmax = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cmax);

        for ($c = $cmin; $c <= $cmax; $c++) {
            if ($value = $this->get_cell_value($worksheet, $c, $r)) {
                break;
            }
        }
        $cmin = $c;

        for ($c = $cmax; $c >= $cmin; $c--) {
            if ($value = $this->get_cell_value($worksheet, $c, $r)) {
                break;
            }
        }
        $cmax = $c;

        return [$cmin, $cmax];
    }

    /**
     * get_format_type
     *
     * @param string $filename
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_format_type($filename) {
        $type = $filename;
        if ($pos = strrpos($type, '.')) {
            $type = substr($type, 0, $pos);
        }
        return $this->get_clean_text($type);
    }

    /**
     * get_clean_text
     *
     * @param string $txt
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_clean_text($txt) {
        // Replace all punctuation and spaces with an underscore, "_".
        $txt = preg_replace('/([[:punct:]]|[[:blank:]])+/', '_', $txt);
        return trim($txt, ' _');
    }

    /**
     * get_total_rows
     *
     * @param object $workbook
     * @param object $format (optional, default=null)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_sheetcount_rowcount($workbook, $format=null) {

        $sheetcount = 0;
        $rowcount = 0;

        if (empty($format)) {
            // Get the total number of sheets and rows in this workbook.
            $sheetcount = $workbook->getSheetCount();
            $rowcount = 0;
            for ($s = 0; $s < $sheetcount; $s++) {
                $rowcount += $workbook->getSheet($s)->getHighestDataRow();
            }
        } else {
            // Get the number of targeted sheets and rows in this workbook.
            foreach ($format->sheets as $ss => $sheet) {
                list($smin, $smax, $stype) = $this->get_sheet_range($workbook, $sheet);
                for ($s = $smin; $s <= $smax; $s++) {
                    $worksheet = $workbook->getSheet($s - 1);
                    foreach ($sheet->rows as $rr => $row) {
                        list($rmin, $rmax, $rtype) = $this->get_row_range($worksheet, $row);
                        $rowcount += ($rmax - $rmin + 1);
                    }
                    $sheetcount++;
                }
            }
        }

        return [$sheetcount, $rowcount];
    }

    /**
     * number_format
     *
     * @param xxx $num
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function number_format($num) {
        return number_format($num,
            0, // The number of decimal places.
            get_string('decsep', 'langconfig'),
            get_string('thousandssep', 'langconfig')
        );
    }

    /**
     * render_caption
     *
     * @param string $datafilename
     * @param integer $totalsheets
     * @param integer $totalrows
     * @param string $formatfilename
     * @param integer $targetsheets
     * @param integer $targetrows
     * @return HTML list of items to show in the table caption
     */
    public function render_caption($datafilename, $totalsheets, $totalrows,
                                   $formatfilename, $targetsheets, $targetrows) {
        $caption = [];

        $caption[] = $this->get_string('totalsheetrowcount', (object)[
            'filename' => $datafilename,
            'sheetcount' => $totalsheets,
            'rowcount' => $this->number_format($totalrows),
        ]);

        $caption[] = $this->get_string('targetsheetrowcount', (object)[
            'filename' => $formatfilename,
            'sheetcount' => $targetsheets,
            'rowcount' => $this->number_format($targetrows),
        ]);

        // Possible formstates are upload, preview, review and import.
        switch ($this->formstate) {
            case 'preview':
                $name = 'headingsandpreviewrows';
                $a = $this->get_previewrows();
                break;
            case 'review':
                $name = 'headingsandpreviewresults';
                $a = $this->get_previewrows();
                break;
            case 'import':
                $name = 'headingsandresults';
                $a = $targetrows;
                break;
            default:
                // Unknown formstate - shouldn't happen !!
                $name = '';
                $a = null;
        }
        if ($name) {
            $caption[] = $this->get_string($name, $a);
        }

        foreach ($caption as $i => $text) {
            $caption[$i] = \html_writer::tag('small', $text);
        }

        $params = ['class' => 'list-unstyled font-weight-normal'];
        return \html_writer::alist($caption, $params, 'ul');
    }

    /**
     * get_previewrows
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_previewrows() {
        return self::get_optional_param('previewrows', 10, PARAM_INT);
    }

    /**
     * populate_preview_table
     * The preview table contains the raw data from the data file (usually a spreadsheet).
     *
     * @param object $workbook
     * @param object $format
     * @param string $table name of a table in the database
     *
     * TODO: Finish documenting this function
     */
    public function populate_preview_table($workbook, $format, $table) {

        // Override the form defaults with settings from $format file.
        if (isset($format->settings)) {
            $mform = $this->_form;
            foreach ($format->settings as $name => $value) {
                if ($mform->elementExists($name)) {
                    $mform->setDefault($name, $value);
                }
            }
        }

        $rowindex = 0;
        $previewrows = $this->get_previewrows();

        foreach ($format->sheets as $ss => $sheet) {

            list($smin, $smax, $stype) = $this->get_sheet_range($workbook, $sheet);

            for ($s = $smin; $s <= $smax; $s++) {
                $worksheet = $workbook->setActiveSheetIndex($s - 1);

                foreach ($sheet->rows as $rr => $row) {

                    // Get the minimum and maximum row and columnn numbers in this $row set.
                    list($rmin, $rmax, $rtype) = $this->get_row_range($worksheet, $row);
                    list($cmin, $cmax) = $this->get_cell_range($row);

                    // Loop through the rows in this row set.
                    for ($r = $rmin; $r <= $rmax; $r++) {
                        if ($rtype == self::TYPE_META) {
                            $text = $this->get_string('row');
                            $table->head = $this->get_row_cells($worksheet, $r, $cmin, $cmax, $text);
                            $table->align = array_merge(['center'], array_fill(0, $cmax, 'left'));
                        } else {
                            $table->data[] = $this->get_row_cells($worksheet, $r, $cmin, $cmax, $r, true);
                            $rowindex++;
                            if ($rowindex >= $previewrows) {
                                break 4;
                            }
                        }
                    }
                }
            }
        }

        // Remove empty columns from the right of the $table.
        $this->prune_table($table);
    }

    /**
     * Remove empty columns from the right of the given HTML table object.
     *
     * @param object $table the HTML table to be pruned
     */
    public function prune_table($table) {

        // Get total number of cells used in each column.
        $totals = [];
        for ($r = 0; $r < count($table->data); $r++) {
            $row = $table->data[$r];
            for ($c = 0; $c < count($row); $c++) {
                if (! array_key_exists($c, $totals)) {
                    $totals[$c] = 0;
                }
                $cell = $row[$c];
                if (is_string($cell)) {
                    $totals[$c] += ($cell ? 1 : 0);
                } else {
                    $totals[$c] += ($cell->text ? 1 : 0);
                }
            }
        }

        $c = max(array_keys($totals));
        while ($c >= 0 && $totals[$c] == 0) {

            // Remove this column from the head row.
            unset($table->head[$c]);

            // Remove this column from all data rows.
            foreach (array_keys($table->data) as $r) {
                unset($table->data[$r][$c]);
            }

            unset($totals[$c]);
            $c--;
        }
    }

    /**
     * populate_review_table
     *
     * @param object $workbook
     * @param object $format
     * @param string $table name of a table in the database
     * @param bool $dryrun (optional, default=true)
     *
     * TODO: Finish documenting this function
     */
    public function populate_review_table($workbook, $format, $table, $dryrun=true) {
        $this->populate_import_table($workbook, $format, $table, self::MODE_DRYRUN);
    }

    /**
     * populate_import_table
     *
     * @param object $workbook
     * @param object $format
     * @param string $table name of a table in the database
     * @param string $mode (optional, default=self::MODE_IMPORT)
     *
     * TODO: Finish documenting this function
     */
    public function populate_import_table($workbook, $format, $table, $mode=self::MODE_IMPORT) {

        // Cache frequently used strings.
        $str = (object)[
            'sheet' => $this->get_string('sheet'),
            'row' => $this->get_string('row'),
        ];

        // Initialize the row index (number of rows processed so far in all sheets)
        // and sheetindex (number of sheets processed, inluding skipped sheets).
        $rowindex = 0;
        $sheetindex = 0;

        // Get total number of sheets and total number of rows.
        // The is only aprroximate and does not take account the
        // start/end settings in the XML file.
        list($sheetcount, $rowcount) = $this->get_sheetcount_rowcount($workbook, $format);

        // Initialize counter for the number of rows previewed (=displayed).
        $previewrowsindex = 0;

        // Set limit for number of preview rows (0 means "no limit").
        if ($mode == self::MODE_IMPORT) {
            $previewrows = 0;
        } else {
            // All other values for $mode including self::MODE_DRYRUN.
            $previewrows = $this->get_previewrows();
        }

        // Get info on the "vocab" tables in the database.
        // We will only allow access to these tables
        // and the fields that they contain.
        $tableinfo = $this->get_tableinfo('vocab', ['instances', 'attempts']);

        $vars = [];
        $filevars = [];
        $sheetvars = [];
        $rowvars = [];

        if ($table->caption) {
            if (preg_match('/<small[^>]*>(.*)<\/small>/s', $table->caption, $match)) {
                if (preg_match('/"([^"]+)"/', $match[1], $match)) {
                    $filevars['filename'] = $match[1];
                }
            }
        }

        // Initialize the array of totals (for reporting purposes).
        $this->setup_totals($tableinfo, $format);

        $this->get_item_settings($format, $vars, $tableinfo, $filevars, 'FILE');
        $this->get_item_records($format, $vars, $tableinfo, 'FILE');

        if ($mode == self::MODE_IMPORT) {
            $bar = new \progress_bar('vocabtool_import_pbar', 500, true);
            // See "lib/outputcomponents.php" for details.
        } else {
            $bar = false;
        }

        foreach ($format->sheets as $ss => $sheet) {

            list($smin, $smax, $stype) = $this->get_sheet_range($workbook, $sheet);
            for ($s = $smin; $s <= $smax; $s++) {

                $worksheet = $workbook->setActiveSheetIndex($s - 1);
                $sheetname = $worksheet->getTitle();
                $sheetvars = ['sheet_name' => $sheetname];

                $vars = $filevars;
                $this->get_item_settings($sheet, $vars, $tableinfo, $sheetvars, 'SHEET');
                $this->get_item_records($sheet, $vars, $tableinfo, 'SHEET');

                // Set the current sheet (for reporting purposes).
                $this->currentsheet = $s;
                $this->currentsheetname = $sheetname;

                $headers = [];
                foreach ($sheet->rows as $rr => $row) {

                    // Get the minimum and maximum row and columnn numbers in this $row set.
                    list($rmin, $rmax, $rtype) = $this->get_row_range($worksheet, $row);

                    // Loop through the rows in this $row set.
                    for ($r = $rmin; $r <= $rmax; $r++) {

                        // Increment the row index and update progress bar.
                        $rowindex++;
                        if ($bar) {
                            $msg = "{$str->sheet}: $s, ".
                                   "{$str->row}: $r ".
                                   "($rowindex/$rowcount) ".
                                   $this->currentrowname;
                            $bar->update($rowindex, $rowcount, $msg);
                        }

                        if ($rtype == self::TYPE_META) {
                            foreach ($row->cells as $c => $name) {
                                $headers[$c] = $this->get_cell_value($worksheet, $c, $r);
                            }
                        } else {
                            $rowvars = [];
                            $vars = array_merge($filevars, $sheetvars);
                            foreach ($row->cells as $c => $name) {
                                $value = $this->get_cell_value($worksheet, $c, $r);
                                $vars[$name] = $rowvars[$name] = $value;
                            }

                            // Generate output depending on table/field definitions in XML.
                            if (! $this->skip_row($row, $vars, $tableinfo, $rowvars)) {

                                // Set the current row (for reporting purposes).
                                $this->currentrow = $r;
                                $this->currentrowname = $this->get_rowname($row, $vars, $tableinfo);

                                $this->get_item_settings($row, $vars, $tableinfo, $rowvars, 'ROW');
                                $this->get_item_records($row, $vars, $tableinfo, 'ROW');

                                $table->data[] = $this->report_totals_data();
                                $previewrowsindex++;
                            }
                        }

                        // Remove aliases for the current row.
                        $this->clear_aliases('ROW');

                        if ($mode == self::MODE_DRYRUN && $previewrowsindex >= $previewrows) {
                            break 4;
                        }
                    }
                }

                // Remove aliases for the current sheet.
                $this->clear_aliases('SHEET');
            }
        }

        // Remove aliases for this data file.
        $this->clear_aliases('FILE');

        // Indicate completion on the progress bar.
        if ($bar) {
            $msg = [];
            if ($rowindex) {
                $rowindex = $this->number_format($rowindex);
                $msg[] = $this->get_string('rowsfound', $rowindex);
            }
            if ($added = $this->totals->added) {
                $added = $this->number_format($added);
                $msg[] = $this->get_string('recordsadded', $added);
            }
            if ($found = $this->totals->found) {
                $found = $this->number_format($found);
                $msg[] = $this->get_string('recordsfound', $found);
            }
            if ($error = $this->totals->error) {
                $error = $this->number_format($error);
                $msg[] = $this->get_string('errorsfound', $error);
            }
            $listsep = get_string('listsep', 'langconfig').' ';
            if ($msg = implode($listsep, $msg)) {
                $msg = " ($msg)";
            }
            $msg = $this->get_string('importcompleted').$msg;
            $bar->update_full(100, $msg);
        }

        // Add field name and descriptions to header row.
        if (empty($table->head) && count($table->data)) {
            $table->head = $this->report_totals_head($headers);
        }

        // Remove empty columns from the report.
        $this->prune_report_totals($table);
    }

    /**
     * Determine whether or not the given record should be skipped.
     * This assumes the <record> tag in the XML file contains a "skip"
     * attribute that defines the conditions under which a record should
     * be skipped. e.g. <record ... skip="EMPTY(synwords)" ...>
     * If the function is missing, or evaluates to FALSE, a record
     * for the current row will be added/found.
     *
     * @param object $record format definition of row in data file.
     * @param array $vars values from the cells in this row.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     * @return bool TRUE if this record should be skipped; otherwise FALSE.
     */
    public function skip_record($record, &$vars, &$tableinfo, $aliastype) {
        $names = ['skip', 'skiprecord', 'recordskip'];
        foreach ($names as $name) {
            if (property_exists($record, $name)) {
                return $this->format_field($tableinfo, $name, $record->$name, $vars, $aliastype);
            }
        }
        return false; // Assume that we do NOT skip this record.
    }

    /**
     * Determine whether or not the given row should be skipped.
     * This assumes the <row> tag in the XML file contains a "skiprow"
     * attribute that defines the conditions under which a row should
     * be skipped. e.g. <row ... rowskip="EMPTY(word)" ...>
     * If the function is missing, or evaluates to FALSE, the row will
     * be processed.
     *
     * @param object $row settings, cell names, record definitions.
     * @param array $vars values and settings for in this row in the data file.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param array $rowvars (passed by reference) values for this row in the data file.
     * @return bool TRUE if this row should be skipped; otherwise FALSE.
     */
    public function skip_row($row, &$vars, &$tableinfo, &$rowvars) {

        if (empty(array_filter($rowvars))) {
            return true; // An empty row - shouldn't happen !!
        }

        $names = ['skip', 'skiprow', 'rowskip'];
        foreach ($names as $name) {
            if (array_key_exists($name, $row->settings)) {
                return $this->format_field($tableinfo, $name, $row->settings[$name], $vars, 'ROW');
            }
        }
        return false; // Assume that we do NOT skip this row.
    }

    /**
     * Get the "rowname" value for this row (e.g. the value in the "word" column).
     *
     * @param object $row settings, cell names, record definitions.
     * @param array $vars values from the cells in this row.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @return string The string to be used as the "name" for the given row.
     */
    public function get_rowname($row, &$vars, &$tableinfo) {
        $name = 'rowname';
        if (empty($row->settings[$name])) {
            $labelsep = get_string('labelsep', 'langconfig');
            return $this->currentsheet.$labelsep.$this->currentrow;
        } else {
            $rowname = $row->settings[$name]; // E.g. VALUE(word).
            $rowname = $this->format_field($tableinfo, $name, $rowname, $vars, 'ROW');
            return trim($rowname, ' "'); // Trim leading and trailing quotes.
        }
    }

    /**
     * get_tablenames
     */
    public function get_tableinfo() {
        global $DB;
        $info = [];

        // We only allow this tool access
        // to the following "vocab" tables,
        // which together form the "dictionary".
        $dictionarytables = [
            'vocab_attribute_names',
            'vocab_attribute_values',
            'vocab_attributes',
            'vocab_corpuses',
            'vocab_definitions',
            'vocab_frequencies',
            'vocab_langnames',
            'vocab_langs',
            'vocab_lemmas',
            'vocab_levelnames',
            'vocab_levels',
            'vocab_multimedia',
            'vocab_pronunciations',
            'vocab_relationship_names',
            'vocab_relationships',
            'vocab_samples',
            'vocab_word_samples',
            'vocab_words',
        ];

        // Access to ALL other Moodle tables is disallowed,
        // including the following "vocab" tables,
        // which contain information about users'
        // interaction with the dictionary data.
        // vocab_ai_(access|prompt)
        // vocab_game_(attempts|instances)
        // vocab_word_(attempts|instances|usages).
        $usertables = [
            'vocab',
            'vocab_ai_access',
            'vocab_ai_prompt',
            'vocab_games', // Not needed?
            'vocab_game_attempts',
            'vocab_game_instances',
            'vocab_word_attempts',
            'vocab_word_instances',
            'vocab_word_usages',
        ];

        // Access to config tables is also disallowed.
        $configtables = [
            'vocab_config',
            'vocab_config_settings',
        ];

        foreach ($DB->get_tables() as $table) {
            if (in_array($table, $dictionarytables)) {
                $info[$table] = [];
                foreach ($DB->get_columns($table) as $column) {
                    $info[$table][] = $column->name;
                }
            }
        }

        return $info;
    }

    /**
     * setup_totals
     *
     * @param array $tableinfo two dimensional array of tables and columns which may be accessed (passed by reference)
     * @param object $format
     *
     * TODO: Finish documenting this function
     */
    public function setup_totals(&$tableinfo, $format) {
        $this->totals = (object)[
            'name' => $format->settings['datafilename'],
            'total' => 0,
            'added' => 0,
            'found' => 0,
            'error' => 0,
            'tables' => [],
        ];
        foreach ($format->sheets as $sheet) {
            foreach ($sheet->rows as $row) {
                foreach ($row->records as $record) {
                    if (array_key_exists($record->table, $this->totals->tables)) {
                        continue; // Table has already been added, so skip it.
                    }
                    $this->totals->tables[$record->table] = (object)[
                        'name' => $this->get_string($record->table),
                        'total' => 0,
                        'added' => 0,
                        'found' => 0,
                        'error' => 0,
                        'sheets' => [],
                    ];
                }
            }
        }
    }

    /**
     * update_totals
     *
     * @param string $table name of a table in the database
     * @param mixed $type a PARAM_xxx constant value
     * @param xxx $msg (optional, default='')
     *
     * TODO: Finish documenting this function
     */
    public function update_totals($table, $type, $msg='') {

        // Check the table name is valid (it should be).
        if (array_key_exists($table, $this->totals->tables)) {

            // Shortcuts to current sheet/row number.
            $s = $this->currentsheet;
            $r = $this->currentrow;

            // Ensure we have an object for the current sheet.
            if (! array_key_exists($s, $this->totals->tables[$table]->sheets)) {
                $this->totals->tables[$table]->sheets[$s] = (object)[
                    'name' => $this->currentsheetname,
                    'total' => 0,
                    'added' => 0,
                    'found' => 0,
                    'error' => 0,
                    'rows' => [],
                ];
            }

            // Ensure we have an object for the current row.
            if (! array_key_exists($r, $this->totals->tables[$table]->sheets[$s]->rows)) {
                $this->totals->tables[$table]->sheets[$s]->rows[$r] = (object)[
                    'name' => $this->currentrowname,
                    'total' => 0,
                    'added' => 0,
                    'found' => 0,
                    'error' => 0,
                    'msg' => [],
                ];
            }

            switch (substr($type, 0, 3)) {
                case 'err':
                    $type = 'error';
                    break;
                case 'add':
                    $type = 'added';
                    break;
                default:
                    $type = 'found';
            }

            // Update subtotals for this $type.
            $this->totals->$type += 1;
            $this->totals->tables[$table]->$type += 1;
            $this->totals->tables[$table]->sheets[$s]->$type += 1;
            $this->totals->tables[$table]->sheets[$s]->rows[$r]->$type += 1;

            // Update totals.
            if ($type == 'added' || $type == 'found') {
                $this->totals->total += 1;
                $this->totals->tables[$table]->total += 1;
                $this->totals->tables[$table]->sheets[$s]->total += 1;
                $this->totals->tables[$table]->sheets[$s]->rows[$r]->total += 1;
            }

            if ($msg) {
                $this->totals->tables[$table]->sheets[$s]->rows[$r]->msg[] = $msg;
            }
        }
    }

    /**
     * report_totals_data
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function report_totals_data() {

        // Shortcuts to current sheet/row number.
        $s = $this->currentsheet;
        $r = $this->currentrow;
        $labelsep = get_string('labelsep', 'langconfig');

        $cells = [];

        $cell = new \html_table_cell($s.$labelsep.$r);
        $cell->header = true;
        $cells[] = $cell;

        $cell = new \html_table_cell($this->currentrowname);
        $cell->header = true;
        $cells[] = $cell;

        // Count the total number of errors in this row.
        // Usually this stays at zero, but just in case ...
        $errors = 0;

        foreach ($this->totals->tables as $tablename => $totals) {

            $msg = [];
            if (array_key_exists($s, $totals->sheets)) {
                if (array_key_exists($r, $totals->sheets[$s]->rows)) {
                    if ($totals->sheets[$s]->rows[$r]->total) {
                        if ($added = $totals->sheets[$s]->rows[$r]->added) {
                            if ($this->formstate == 'import') {
                                $msg[] = $this->get_string('recordsadded', $added);
                            } else {
                                $msg[] = $this->get_string('recordswillbeadded', $added);
                            }
                        }
                        if ($found = $totals->sheets[$s]->rows[$r]->found) {
                            $msg[] = $this->get_string('recordsfound', $found);
                        }
                    }
                    $errors += $totals->sheets[$s]->rows[$r]->error;
                    if (count($totals->sheets[$s]->rows[$r]->msg)) {
                        $params = ['class' => 'bg-warning text-light rounded px-1 error'];
                        foreach ($totals->sheets[$s]->rows[$r]->msg as $i => $text) {
                            $text = \html_writer::tag('div', $text, $params);
                            $totals->sheets[$s]->rows[$r]->msg[$i] = $text;
                        }
                        $msg = array_merge($msg, $totals->sheets[$s]->rows[$r]->msg);
                    }
                }
            }
            if (count($msg)) {
                $msg = array_unique($msg);
                $params = ['class' => 'list-unstyled'];
                $msg = \html_writer::alist($msg, $params);
            } else {
                $msg = '';
            }
            $cells[] = new \html_table_cell($msg);
        }

        if ($errors) {
            $text = $this->get_string('errorsfound', $errors);
            $params = ['class' => 'bg-warning text-light rounded px-1 errors'];
            $cells[1]->text .= ' '.\html_writer::tag('div', $text, $params);
        }

        return $cells;
    }

    /**
     * report_totals_head
     *
     * @param xxx $headers (passed by reference)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function report_totals_head(&$headers) {
        $cells = [];

        $label = $this->get_string('sheet');
        $label .= get_string('labelsep', 'langconfig');
        $label .= $this->get_string('row');

        $cells[] = new \html_table_cell($label);
        $cells[] = new \html_table_cell(reset($headers));

        foreach ($this->totals->tables as $tablename => $totals) {
            $cells[] = new \html_table_cell($this->get_string($tablename));
        }

        return $cells;
    }

    /**
     * Remove unused columns from the given HTML table object.
     *
     * @param object $table the HTML table to be pruned
     */
    public function prune_report_totals($table) {
        // Initialize the column index to 2 because we always
        // show column-1 (sheet: row) and column-2 (rowname).
        $c = 2;
        foreach ($this->totals->tables as $tablename => $totals) {
            if ($totals->total == 0 && $totals->error == 0) {

                // Remove this column from the head row.
                unset($table->head[$c]);

                // Remove this column from all data rows.
                foreach (array_keys($table->data) as $r) {
                    unset($table->data[$r][$c]);
                }

                // Decrement the column index, as this column no longer exists.
                $c--;
            }
            $c++;
        }
    }

    /**
     * get_sheet_range
     *
     * @param object $workbook
     * @param object $sheet
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_sheet_range($workbook, $sheet) {
        $defaultmin = 1;
        $defaultmax = $workbook->getSheetCount();
        return $this->get_item_range($sheet, 'sheet', $defaultmin, $defaultmax);
    }

    /**
     * get_row_range
     *
     * @param object $worksheet representing a sheet from the data file
     * @param object $row
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_row_range($worksheet, $row) {
        $defaultmin = 1;
        $defaultmax = $worksheet->getHighestDataRow();
        return $this->get_item_range($row, 'row', $defaultmin, $defaultmax);
    }

    /**
     * get_item_range
     *
     * @param object $item representing an item in the XML file
     * @param string $prefix
     * @param integer $defaultmin
     * @param integer $defaultmax
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_item_range($item, $prefix, $defaultmin, $defaultmax) {

        if (isset($item->settings)) {
            $settings = $item->settings;
        } else {
            $settings = [];
        }

        // Look for sheettype, rowtype or celltype.
        $name = $prefix.'type';
        if (array_key_exists($name, $settings)) {
            $type = $settings[$name];
        } else if (array_key_exists('type', $settings)) {
            $type = $settings['type'];
        } else {
            $type = '';
        }

        // Ensure sensible values for $type ("meta" or "data").
        if ($type == 'meta') {
            $type = self::TYPE_META;
        } else {
            $type = self::TYPE_DATA;
        }

        // Look for sheetstart, rowstart or cellstart.
        $name = $prefix.'start';
        if (array_key_exists($name, $settings) && is_numeric($settings[$name])) {
            $min = $settings[$prefix.'start'];
        } else if (array_key_exists('start', $settings) && is_numeric($settings['start'])) {
            $min = $settings['start'];
        } else {
            $min = '';
        }

        // Ensure sensible values for $min.
        if (is_numeric($min) && $min >= $defaultmin) {
            $min = intval($min);
        } else {
            $min = $defaultmin;
        }

        // Look for sheetend, rowend or cellend.
        $name = $prefix.'end';
        if (array_key_exists($name, $settings) && is_numeric($settings[$name])) {
            $max = $settings[$prefix.'end'];
        } else if (array_key_exists('end', $settings) && is_numeric($settings['end'])) {
            $max = $settings['end'];
        } else {
            $max = '';
        }

        // Ensure sensible values for $max.
        if (is_numeric($max) && $max <= $defaultmax) {
            $max = intval($max);
        } else {
            $max = $defaultmax;
        }

        return [$min, $max, $type];
    }

    /**
     * get_cell_range
     * this method is NOT USED anywhere.
     *
     * @param object $row
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_cell_range($row) {

        $cmin = 0;
        $cmax = max(array_keys($row->cells));
        $ctype = self::TYPE_DATA;

        if (isset($row->settings) && is_array($row->settings)) {
            if (array_key_exists('type', $row->settings)) {
                if ($row->settings['type'] == 'meta') {
                    $rtype = self::TYPE_META;
                }
            }
        }

        return [$cmin, $cmax];
    }

    /**
     * ignore_value
     *
     * @param string $value
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function ignore_value($value) {
        $name = 'ignorevalues';
        if ($this->$name === null) {
            $mform = $this->_form;
            // We cannot use $mform->elementExists()
            // because $mform has not been setup yet.
            if ($this->$name = self::get_optional_param($name, '', PARAM_TEXT)) {
                $this->$name = explode(',', $this->$name);
                $this->$name = array_map('trim', $this->$name);
                $this->$name = array_filter($this->$name);
            } else {
                $this->$name = []; // Shouldn't happen !!
            }
        }
        // Normally, there should be no storeKey values, because
        // pruning was disabled when we created the Workbook.
        if (strpos($value, 'storeKey') === false) {
            return in_array($value, $this->ignorevalues);
        } else {
            // Always remove "storeKey" values.
            return true;
        }
    }

    /**
     * get_row_cells
     *
     * @param object $worksheet representing a sheet from the data file
     * @param integer $r a row number
     * @param integer $cmin the minimum column number
     * @param integer $cmax the maximum column number
     * @param string $text
     * @param bool $th if TRUE, the 1st cell should be made a TH cell (optional, default=false)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_row_cells($worksheet, $r, $cmin, $cmax, $text, $th=false) {
        $cells = [];
        for ($c = $cmin; $c <= $cmax; $c++) {
            $cells[] = $this->get_cell_value($worksheet, $c, $r);
        }

        $cell = new \html_table_cell();
        $cell->text = ($text ? $text : '');
        $cell->header = ($th ? true : false);
        $cells = array_merge([$cell], $cells);

        return $cells;
    }

    /**
     * get_singleline_value
     *
     * @param object $worksheet representing a sheet from the data file
     * @param integer $c a column number
     * @param integer $r a row number
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    protected function get_singleline_value($worksheet, $c, $r) {
        $value = $this->get_cell_value($worksheet, $c, $r);
        return preg_replace('/\s+/s', ' ', $value);
    }

    /**
     * get_cell_value
     *
     * @param object $worksheet representing a sheet from the data file
     * @param integer $c a column number
     * @param integer $r a row number
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    protected function get_cell_value($worksheet, $c, $r) {

        $coffset = ($this->phpspreadsheet ? 1 : 0); // The column offset.
        $cell = $worksheet->getCellByColumnAndRow($c + $coffset, $r);

        if ($cell->isFormula()) {
            if ($this->phpbranchpruning) {
                // Moodle >= 4.x has no problem with full column references.
                $cancalculate = true;
            } else if (preg_match('/(\$[0-9A-F]+):\1/', $cell->getValue())) {
                // Moodle <= 3.11 cannot handle absolute full column
                // references in Excel, such as "textbooks!$F:$F".
                $cancalculate = false;
            } else {
                $cancalculate = true;
            }
        } else {
            $cancalculate = true;
        }

        if ($cancalculate) {
            // On Moodle >= 4.x, we can always use the standard formatter.
            // On Moodle <= 3.11, we can use the standard formatter if the
            // the cell doesn't contain a formula with absolute full-column
            // references, such as "textbooks!$F:$F".
            $value = $cell->getFormattedValue();
        } else {
            // On Moodle <= 3.11 and formulas with absolute full-column referenes,
            // we mimic "getFormattedValue()", but use the "old" calculated value.
            // This avoids errors, but may use a value that is not up-to-date.
            $value = call_user_func_array([
                '\PhpOffice\PhpSpreadsheet\Style\NumberFormat',
                'toFormattedString',
            ], [
                $cell->getOldCalculatedValue(),
                $cell->getStyle()->getNumberFormat()->getFormatCode(),
            ]);
        }
        return ($this->ignore_value($value) ? '' : $value);
    }

    /**
     * format_fields
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param array $fields (passed by reference) definition of field names and values.
     * @param array $vars (passed by reference) values from the cells in this row.
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     */
    public function format_fields(&$tableinfo, &$fields, &$vars, $aliastype) {
        $values = [];
        if (is_array($fields)) {
            foreach ($fields as $fieldname => $value) {
                $values[$fieldname] = $this->format_field($tableinfo, $fieldname, $value, $vars, $aliastype);
            }
        }
        return $values;
    }

    /**
     * format_field
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $fieldname name of database field
     * @param string $value for database field.
     * @param array $vars (passed by reference) values from the cells in this row.
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     */
    public function format_field(&$tableinfo, $fieldname, $value, &$vars, $aliastype) {

        // These are the functions that we know about.
        $search = '/\b(EMPTY|IDS|ID|SET|VALUE|JOIN|SPLIT|NEWLINE|REPLACE|SUBSTRING|LOWERCASE|PROPERCASE|UPPERCASE)\b/u';

        // Search for, and replace function names (starting from the rightmost one).
        if (preg_match_all($search, $value, $matches, PREG_OFFSET_CAPTURE)) {

            for ($m = count($matches[0]); $m > 0; $m--) {
                // Cache the function name and start position.
                list($match, $start) = $matches[0][$m - 1];

                $mode = 0;
                // The possible values for $mode are:
                // 0: find open parentheses
                // 1: find start argument
                // 2: find end unquoted argument
                // 3: find end quoted argument
                // 4: find comma or closing parenthesis
                // 5: complete
                // 6: error !!

                $args = [];
                $a = -1; // The index on $args.

                $imax = strlen($value);
                $i = ($start + strlen($match));
                while ($i < $imax && $mode < 5) {
                    switch ($mode) {

                        case 0:
                            // Expecting opening parenthesis.
                            switch ($value[$i]) {

                                // Leading white space is ignored.
                                case ' ':
                                    break;

                                // Opening parenthesis - yay!
                                case '(':
                                    $mode = 1;
                                    break;

                                default:
                                    $args = "No open bracket found for $match";
                                    $mode = 6;
                            }
                            break;

                        case 1:
                            // Expecting the start of an argument.
                            switch ($value[$i]) {

                                // Leading white space is ignored.
                                case ' ':
                                    break;

                                case ',':
                                    $mode = 6;
                                    $args = "Comma not expected parsing $match";
                                    break;

                                // A closing parenthesis (i.e. end of arguments).
                                case ')':
                                    $mode = 5;
                                    break;

                                // The start of a quoted quoted argument.
                                case '"':
                                    $a++;
                                    $args[$a] = '';
                                    $mode = 3;
                                    break;

                                // The first char of an unquoted argument.
                                default:
                                    $a++;
                                    $args[$a] = $value[$i];
                                    $mode = 2;
                            }
                            break;

                        case 2:
                            // Expecting the end of an unquoted argument.
                            switch ($value[$i]) {
                                case ',':
                                    $mode = 1;
                                    break;

                                case ')':
                                    $mode = 5;
                                    break;

                                default:
                                    // The next char of an unquoted argument.
                                    $args[$a] .= $value[$i];
                            }
                            break;

                        case 3:
                            // Expecting the end of a quoted argument.
                            switch ($value[$i]) {
                                case '\\':
                                    // The backslash signifies an escaped character.
                                    // Skip the slash and store the following char.
                                    $i++;
                                    $args[$a] .= $value[$i];
                                    break;

                                case '"':
                                    // The end of a quoted string.
                                    $mode = 4;
                                    break;

                                default:
                                    // The next char of an quoted argument.
                                    $args[$a] .= $value[$i];
                            }
                            break;

                        case 4:
                            // Expecting a comma or closing parenthesis.
                            switch ($value[$i]) {
                                case ' ':
                                    break;

                                case ',':
                                    $mode = 1;
                                    break;

                                case ')':
                                    $mode = 5;
                                    break;

                                default:
                                    $mode = 6;
                                    $args = "Character '".$value[$i]."' not expected after quoted string in $match";
                            }
                            break;
                    }
                    $i++;
                } // End while.

                if ($mode == 6) {
                    $replace = $args; // An error message.
                } else {
                    $replace = $this->format_function($tableinfo, $match, $args, $vars);
                    if (is_array($replace)) {
                        if (empty($replace)) {
                            $replace = '';
                        } else if (count($replace) == 1) {
                            $replace = reset($replace);
                        } else {
                            // E.g. the result of SPLIT(";", "happy; joyful; merry").
                            $replace = $this->get_value_alias($replace, $aliastype);
                        }
                    }
                }
                $value = substr_replace($value, $replace, $start, ($i - $start));

            } // End for $m.
        } // End if preg_match_all.

        return $value;
    }

    /**
     * Store an array/object value in the internal cache of non-scalar values
     * and return a string that is an alias to the cached value.
     *
     * @param mixed $value an array or object that is to be cached
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     * @return string the alias of the given value
     */
    protected function get_value_alias($value, $aliastype) {
        $aliasname = 'ALIAS_'.$aliastype.'_';
        $aliasname .= count($this->aliases); // A unique id.
        $this->aliases[$aliasname] = $value;
        return $aliasname;
    }

    /**
     * Determines whether or not the given string is
     * a valid alias to the non-scalar cache.
     *
     * @param string $alias
     * @return bool TRUE if the given string is valid alias; otherwise FALSE.
     */
    protected function is_value_alias($alias) {
        if (preg_match('/^ALIAS_(\w+_)?(\d+)$/', $alias)) {
            if (array_key_exists($alias, $this->aliases)) {
                return true;
            }
        }
        return false; // Not an alias.
    }

    /**
     * Retrieve an non-scalar value that has been cached with an alias.
     *
     * @param string $alias
     * @return array the value that was cached with the given alias.
     */
    protected function get_alias_value($alias) {
        if ($this->is_value_alias($alias)) {
            $value = $this->aliases[$alias];
            return $value;
        } else {
            // This is NOT an alias, so assume it is
            // just a scalar value and return it.
            return $alias;
        }
    }

    /**
     * Connvert all aliases to non-scalar values, usually arrays, that have been cached.
     *
     * @param array $values (passed by reference) array of alias or value strings
     * @return void, but may update $values.
     */
    protected function get_alias_values(&$values) {
        foreach ($values as $key => $value) {
            $values[$key] = $this->get_alias_value($value);
        }
    }

    /**
     * Clear all aliases of the given type.
     *
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     * @return void, but may update $values.
     */
    protected function clear_aliases($aliastype) {
        $search = '/^ALIAS_'.$aliastype.'_/';
        $aliasnames = array_keys($this->aliases);
        $aliasnames = preg_grep($search, $aliasnames);
        foreach ($aliasnames as $aliasname) {
            unset($this->aliases[$aliasname]);
        }
    }

    /**
     * format_function
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $functionname name of the function
     * @param array $args (passed by reference) arguments for the specified $functionname
     * @param array $vars (passed by reference) values for the current row in the data file
     * @return mixed $result of the specified function using the given arguments
     *
     * TODO: Finish documenting this function
     */
    public function format_function(&$tableinfo, $functionname, &$args, &$vars) {
        global $DB;

        // Convert aliases to non-scalar values (e.g. arrays).
        $this->get_alias_values($args);

        switch ($functionname) {

            case 'EMPTY':

                // Is argument is empty (or missing)?
                if (empty($args[0])) {
                    return true;
                }
                // Argument could be a column/setting name.
                if (array_key_exists($args[0], $vars)) {
                    return empty($vars[$args[0]]);
                }
                // Argument is not empty.
                return false;

            case 'IDS':

                // Expected syntax is (table, field1, values1, field2, values2, ...).
                $table = (isset($args[0]) ? $args[0] : '');
                if (empty($table) || ! is_string($table)) {
                    return [];
                }

                $params = [];
                $scalarvalues = [];

                $i = 1;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $name = $args[$i++];
                    $values = $args[$i++];
                    if (is_scalar($name) && $name && $values) {
                        if (is_array($values)) {
                            foreach ($values as $p => $value) {
                                if (empty($params[$p])) {
                                    $params[$p] = [];
                                }
                                $params[$p][$name] = $value;
                            }
                        } else if (is_scalar($values)) {
                            $scalarvalues[$name] = $values;
                        }
                    }
                }

                // Append each scalar value to each $params element.
                if (count($scalarvalues)) {
                    foreach (array_keys($params) as $p) {
                        $params[$p] = array_merge($params[$p], $scalarvalues);
                    }
                }

                // Now add/find the records and store the ids.
                $ids = [];
                foreach (array_keys($params) as $p) {
                    $ids[] = $this->get_record_ids($tableinfo, $table, $params[$p]);
                }
                return $ids;

            case 'ID':

                // Expected syntax is (table, field1, value1, ...).
                $table = '';
                $params = [];
                $emptyvalue = false;

                $i = 0;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    if ($i == 0) {
                        $table = $args[$i++];
                    } else {
                        $name = $args[$i++];
                        $value = $args[$i++];
                        $params[$name] = $value;
                        if (empty($value)) {
                            $emptyvalue = true;
                        }
                    }
                }

                if (empty($table) || empty($params) || $emptyvalue) {
                    return '';
                }

                return $this->get_record_ids($tableinfo, $table, $params);

            case 'SET':
                // This function is not used anywhere. However, it works so let it stay.
                // It will handle the case where a new field is added to a table which
                // already contains items that do not have the field set.

                // Expected syntax is (table, newfield, newvalue, field1, value1, ...).
                $table = '';
                $newfield = '';
                $newvalue = '';
                $params = [];

                $i = 0;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    if ($i == 0) {
                        $table = $args[$i++];
                    } else if ($i == 1) {
                        $newfield = $args[$i++];
                        $newvalue = $args[$i++];
                    } else {
                        $name = $args[$i++];
                        $value = $args[$i++];
                        $params[$name] = $value;
                    }
                }

                // Ensure we have minimum number of values to work with.
                if (empty($table) || empty($newfield) || empty($newvalue) || empty($params)) {
                    return '';
                }

                // Get all exisiting records that match the OLD conditions.
                // We expect at most one, but it's possible there could be more.
                $records = $DB->get_records($table, $params);

                // Get all existing records that match the NEW conditions.
                // We expect at most one, but it's possible there could be more.
                $newparams = array_merge($params, [$newfield => $newvalue]);
                $newrecords = $DB->get_records($table, $newparams);

                // Check if we have a record matching the NEW value.
                if ($newrecords) {

                    if ($table == 'vocab_words') {
                        $tables = [];
                        // Remove all records matching the OLD conditions.
                        // ToDo: update tables that may have used this word
                        // vocab_word_samples, vocab_word_instances
                        // vocab_synonyms, vocab_pronunciations,
                        // vocab_multimedia, vocab_frequencies,
                        // vocab_definitions, vocab_antonyms
                        // If the newfield is "posid", we also need  to update all the
                        // antonyms, synonyms, conjugations that point to the same lemma.
                    }
                    $DB->delete_records_list($table, 'id', array_keys($records));

                    // Return the first record matching NEW conditions.
                    return reset($newrecords)->id;
                }

                // Check if we have a record matching the OLD conditions.
                if ($records) {

                    // Update all OLD records.
                    $DB->set_field($table, $newfield, $newvalue, $params);

                    // Return the first OLD record.
                    return reset($records)->id;
                }

                // Otherwise, there were no records matching either the new
                // or the old conditions, so we proceed as normal.
                return $this->get_record_ids($tableinfo, $table, $params);

            case 'VALUE':

                // Expected syntax is (name, default).
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    $value = $args[0];
                    if (array_key_exists($value, $vars)) {
                        // Probably a var name.
                        $value = $vars[$value];
                    }
                } else if (array_key_exists(1, $args) && is_string($args[1])) {
                    $value = $args[1]; // Use default value.
                } else {
                    $value = ''; // No default specified.
                }
                if ($value && is_numeric($value)) {
                    // Probably an id.
                    return $value;
                }
                return '"'.addslashes($value).'"';

            case 'JOIN':

                // Expected syntax is (joiner, string).
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    if (array_key_exists(1, $args) && is_string($args[1])) {
                        return implode($args[0], $args[1]);
                    }
                }
                return [];

            case 'SPLIT':
                // Expected syntax is (separator, string).
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    if (array_key_exists(1, $args) && is_string($args[1])) {
                        // In case we have more than one array, we could append them to the values
                        // using something like $values = implode($args[0], array_slice($args, 1)).
                        $values = explode($args[0], $args[1]);
                        $values = array_map('trim', $values);
                        $values = array_filter($values);
                        return $values;
                    }
                }
                return [];

            case 'NEWLINE':
                return "\n";

            case 'REPLACE': // Expecting (string, search1, replace1, ...).
                $i = 1;
                $params = [];
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $params[$args[$i++]] = $args[$i++];
                }
                if (empty($params)) {
                    return $args[0];
                }
                return strtr($args[0], $params);

            case 'SUBSTRING':
                switch (count($args)) {
                    case 0: $args[0] = ''; // An intentional drop through.
                    case 1: $args[1] = 1; // An intentional drop through.
                    case 2: $args[2] = core_text::strlen($args[0]);
                }
                return core_text::substr($args[0], $args[1] - 1, $args[2]);

            case 'LOWERCASE':
                return core_text::strtolower($args[0]);

            case 'PROPERCASE':
                return core_text::strtotitle($args[0]);

            case 'UPPERCASE':
                return core_text::strtoupper($args[0]);

            default:
                return implode(',', $args);
        }
    }

    /**
     * get_item_settings
     *
     * @param object $item representing an item in the XML file
     * @param array $vars (passed by reference) values for the current row in the data file
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param array $itemvars (passed by reference) settings and values for the current item (file, sheet or row).
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     * @return void, but may update $vars and $itemvars
     *
     * TODO: Finish documenting this function
     */
    public function get_item_settings($item, &$vars, &$tableinfo, &$itemvars, $aliastype) {
        foreach ($item->settings as $name => $value) {
            $vars[$name] = $itemvars[$name] = $this->format_field($tableinfo, $name, $value, $vars, $aliastype);
        }
    }

    /**
     * get_item_records
     *
     * @param object $item representing an item in the XML file
     * @param array $vars (passed by reference) values for the current row in the data file
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $aliastype "FILE", "SHEET" or "ROW"
     *
     * TODO: Finish documenting this function
     */
    public function get_item_records($item, &$vars, &$tableinfo, $aliastype) {
        foreach ($item->records as $record) {

            if ($this->skip_record($record, $tableinfo, $vars, $aliastype)) {
                continue;
            }

            $fields = $this->format_fields($tableinfo, $record->fields, $vars, $aliastype);

            // Convert aliases to non-scalar values (e.g. arrays).
            $this->get_alias_values($fields);

            // Skip rows with empty fields.
            if (in_array('', $fields, true)) {
                continue;
            }
            $this->get_record_ids($tableinfo, $record->table, $fields);
        }
    }

    /**
     * get_record_ids
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $table name of a table in the database
     * @param array $fields of database field names (passed by reference)
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_record_ids(&$tableinfo, $table, &$fields) {
        $debug = true;

        if (! array_key_exists($table, $tableinfo)) {
            throw new \moodle_exception('tableaccessnotallowed', $this->subpluginname, '', $table);
        }

        foreach ($fields as $name => $value) {
            if (! in_array($name, $tableinfo[$table])) {
                $a = (object)[
                    'tablename' => $table,
                    'fieldname' => $name,
                ];
                throw new \moodle_exception('fieldaccessnotallowed', $this->subpluginname, '', $a);
            }
            if (empty($value) || $value === '0') {
                if ($debug) {
                    $a = (object)[
                        'tablename' => $table,
                        'fieldname' => $name,
                    ];
                    throw new \moodle_exception('idparametermissing', $this->subpluginname, '', $a);
                }
                unset($fields[$name]);
            }
        }

        $fieldsets = [[]];
        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                $fcount = count($fieldsets);
                $vcount = count($value);
                for ($v = 0; $v < $vcount; $v++) {
                    for ($f = 0; $f < $fcount; $f++) {
                        $findex = $f + ($fcount * $v);
                        if (empty($fieldsets[$findex])) {
                            $fieldsets[$findex] = $fieldsets[$f];
                        }
                        $fieldsets[$findex][$name] = $value[$v];
                    }
                }
            } else if (is_scalar($value)) {
                $fcount = count($fieldsets);
                for ($f = 0; $f < $fcount; $f++) {
                    $fieldsets[$f][$name] = $value;
                }
            }
        }

        $ids = [];
        foreach ($fieldsets as $fieldset) {
            if ($id = $this->get_record_id($table, $fieldset)) {
                $ids[] = $id;
            }
        }

        switch (count($ids)) {
            case 0:
                return 0;
            case 1:
                return reset($ids);
            default:
                return $ids;
        }
    }

    /**
     * Get the id of the record that is uniquely identified by an array of
     * field names of values. If no such record exists it will be created.
     * Any field values that are too long for the corresponding database
     * field will be truncated to a suitable length.
     *
     * @uses $DB
     * @param string $table name of a table in the database
     * @param array $fields array of database field names and values
     * @return int
     */
    public function get_record_id($table, $fields) {
        global $DB;
        $this->fix_field_values($table, $fields);

        // We want to do just the following:
        // $id = $DB->get_field($table, 'id', $fields);
        // But this fails with an error if several records are found,
        // so instead, we get all the matching records and return only
        // the first (=lowest id).
        if ($records = $DB->get_records($table, $fields, 'id')) {
            if (count($records) == 1) {
                $this->update_totals($table, 'found');
            } else {
                $error = get_string('multiplerecordsfound', 'error');
                $this->update_totals($table, 'error', $error);
            }
            $id = reset($records)->id;
        } else {
            $id = false;
        }

        if ($id === 0) {
            // This happens sometimes during development.
            $DB->delete_records($table, ['id' => $id]);
        }
        if ($id === false || $id === 0 || $id === null) {
            if ($this->formstate == 'import') {
                $id = $DB->insert_record($table, $fields);
            } else {
                // We only actually add records during the "import" state.
                // However, we set the id here to signify no error.
                $id = 'x';
            }
            $this->update_totals($table, 'added');
        }
        return $id;
    }

    /**
     * Check the field values so that we can report missing fields,
     * truncate long values, and add default values where possible.
     *
     * @uses $DB
     * @param string $table name of a table in the database
     * @param array $fields of database field names (passed by reference)
     * @return void (but may update values in $fields array and/or report errors)
     */
    public function fix_field_values($table, &$fields) {
        global $DB;

        $columns = $DB->get_columns($table);
        foreach ($columns as $name => $column) {
            if ($name == 'id') {
                continue;
            }
            if (empty($column->not_null)) {
                // E.g. vocab_pronunciations.fieldid.
                continue;
            }
            // Check if "id" field is missing (or empty during import).
            if (substr($name, -2) == 'id') {
                if (array_key_exists($name, $fields)) {
                    if ($this->formstate == 'import') {
                        $missing = empty($fields[$name]);
                    } else {
                        $missing = false;
                    }
                } else {
                    $missing = false;
                }
                if ($missing) {
                    $msg = $this->get_string('missingfielddata', (object)[
                        'tablename' => $table,
                        'fieldname' => $name,
                    ]);
                    $this->update_totals($table, 'error', $msg);
                }
            }
        }

        foreach ($fields as $name => $value) {
            $fields[$name] = $value = trim($value, ' "');

            if (array_key_exists($name, $columns)) {

                $column = $columns[$name];
                switch ($column->meta_type) {

                    // See lib/dml/database_column_info.php
                    // R - counter (integer primary key)
                    // I - integers
                    // N - numbers (floats)
                    // C - characters and strings
                    // X - texts
                    // B - binary blobs
                    // L - boolean (1 bit)
                    // T - timestamp - unsupported
                    // D - date - unsupported !!

                    case 'C':
                    case 'X':
                        $maxlength = $column->max_length;
                        $length = \core_text::strlen($value);
                        if ($length > $maxlength) {
                            // Shorten the string, at a word boundary if possible,
                            // but with no trailing string. ("lib/moodlelib.php").
                            $fields[$name] = shorten_text($value, $maxlength, false, '');
                            $msg = $this->get_string('valueshortened', (object)[
                                'fieldname' => $name,
                                'maxlength' => $maxlength,
                            ]);
                            $this->update_totals($table, 'error', $msg);
                        }
                        break;

                    case 'I':
                    case 'N':
                        if (! is_numeric($value)) {
                            if (empty($column->has_default)) {
                                $fields[$name] = 0; // No default.
                            } else if (isset($column->default_value)) {
                                $fields[$name] = $column->default_value;
                            } else {
                                $fields[$name] = 0; // No default value.
                            }
                        }
                        break;
                } // end switch
            }
        }
    }

    /**
     * get_punctuation
     * (this function does not apepar to be used)
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    protected function get_punctuation() {
        if ($this->punctuation === null) {
            $this->punctuation = [
                /* 00D7 */ '×' => '*',

                // General Punctuation characters
                // https://0g0.org/category/2000-206F/1/ .
                /* 2014 */ '—' => '-', '‖' => '|', /* 2016 */
                /* 2018 */ '‘' => "'", '’' => "'", /* 2019 */
                /* 201C */ '“' => '"', '”' => '"', /* 201D */
                /* 2026 */ '…' => '-',

                // CJK Symbols and Punctuation 3000-303F
                // https://0g0.org/category/3000-303F/1/ .
                /* 3000 */ '　' => ' ', '、' => ',', /* 3001 */
                /* 3002 */ '。' => '.', '〃' => '"', /* 3003 */
                /* 3007 */ '〇' => 'O',
                /* 3008 */ '〈' => '<', '〉' => '>', /* 3009 */
                /* 300A */ '《' => '<', '》' => '>', /* 300B */
                /* 300C */ '「' => "'", '」' => "'", /* 300D */
                /* 300E */ '『' => '"', '』' => '"', /* 300F */
                /* 3010 */ '【' => '[', '】' => ']', /* 3011 */
                /* 3014 */ '〔' => '(', '〕' => ')', /* 3015 */
                /* 3016 */ '〖' => '[', '〗' => ']', /* 3017 */
                /* 3018 */ '〘' => '[', '〙' => ']', /* 3019 */
                /* 301A */ '〚' => '[', '〛' => ']', /* 301B */
                /* 301C */ '〜' => '~', '〝' => '"', /* 301D */
                /* 301E */ '〞' => '"', '〟' => '"', /* 301F */

                // Halfwidth and Fullwidth Forms FF00-FFEF
                // https://0g0.org/category/FF00-FFEF/1/ .
                /* FF01 */ '！' => '!', '＂' => '"', /* FF02 */
                /* FF03 */ '＃' => '#', '＄' => '$', /* FF04 */
                /* FF05 */ '％' => '%', '＆' => '&', /* FF06 */
                /* FF07 */ '＇' => "'", '（' => '(', /* FF08 */
                /* FF09 */ '）' => ')', '＊' => '*', /* FF0A */
                /* FF0B */ '＋' => '+', '，' => ',', /* FF0C */
                /* FF0D */ '－' => '-', '．' => '.', /* FF0E */
                /* FF0F */ '／' => '/', '：' => ':', /* FF1A */
                /* FF1B */ '；' => ';', '＜' => '<', /* FF1C */
                /* FF1D */ '＝' => '=', '＞' => '>', /* FF1E */
                /* FF1F */ '？' => '?', '＠' => '@', /* FF20 */
                /* FF3B */ '［' => '[', '＼' => '\\', /* FF3C */
                /* FF3D */ '］' => ']', '＾' => '^', /* FF3E */
                /* FF3F */ '＿' => '_', '｀' => "'", /* FF40 */
                /* FF5B */ '｛' => '{', '｜' => '|', /* FF5C */
                /* FF5D */ '｝' => '}', '～' => '~', /* FF5E */
                /* FF5F */ '｟' => '(', '｠' => ')', /* FF60 */
                /* FF61 */ '｡' => '. ', '｢' => '"', /* FF62 */
                /* FF63 */ '｣' => '"',  '､' => ',', /* FF64 */
                /* FF65 */ '･' => '/', '￣' => '~', /* FFE3 */
                /* FFE1 */ '￡' => '£', '￥' => '¥', /* FFE5 */
            ];
        }
        return $this->punctuation;
    }
}
