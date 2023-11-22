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

defined('MOODLE_INTERNAL') || die;

// Fetch the parent class.
require_once($CFG->dirroot.'/mod/vocab/classes/toolform.php');

/**
 * form
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class form extends \mod_vocab\toolform {

    // cache the plugin name
    public $tool = 'vocabtool_import';

    const ACTION_ADD_NEW_ONLY = 1;
    const ACTION_ADD_AND_UPDATE = 2;
    const ACTION_UPDATE_EXISTING = 3;
    const ACTION_ADD_UPDATE_REMOVE  = 4;

    const SELECT_NONE = 0;
    const SELECT_ALL  = 1;
    const SELECT_NEW  = 2;

    const TYPE_NONE = 0;
    const TYPE_META = 1;
    const TYPE_DATA = 2;

    const MODE_DRYRUN = 1;
    const MODE_IMPORT = 2;

    protected $formstate = '';
    protected $phpspreadsheet = null;
    protected $punctuation = null;

    // Array of cell values to ignore. e.g. "N/A".
    public $ignorevalues = null; 

    // Array to map aliases (e.g. 'ALIAS_99') to non-scalar values
    // (i.e. arrays and objects) that are passed as parameters to functions.
    protected $aliases = array();

    // Array of vocab tables that have been updated by this import tool.
    protected $totals = null;

    // Index on current sheet and row in data file.
    protected $currentsheet = 0;
    protected $currentrow = 0;

    // Names of current sheet and row in data file.
    protected $currentsheetname = 0;
    protected $currentrowname = 0;

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        global $CFG;

        // Get a valid form state.
        $states = array('upload', 'preview', 'review', 'import');
        $this->formstate = optional_param('formstate', '', PARAM_ALPHA);
        if (in_array($this->formstate, $states) == false) {
            // Use the initial state as the default state.
            $this->formstate = reset($states);
        }

        // Detect "Cancel" or "Back" button.
        if (optional_param('cancel', 0, PARAM_RAW)) {
            $i = array_search($this->formstate, $states);
            if ($i >= 2) {
                $this->formstate = $states[$i - 2];
            } else {
                $this->formstate = 'cancelled';
            }
        }

        // check for new PhpExcel (Moodle >= 3.8)
        $this->phpspreadsheet = file_exists($CFG->dirroot.'/lib/phpspreadsheet');
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
    }

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {
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
     * @todo Finish documenting this function
     */
    public function definition_upload($mform) {

        $name = 'datafile';
        $params = array('.xlsx', '.xls', '.ods'); // , '.csv', '.txt'
        $params = array('required' => 1, 'accepted_types' => $params);
        $this->add_field_filepicker($mform, $name, null, $params);

        $name = 'formatfile';
        $params = array('accepted_types' => array('.xml'));
        $this->add_field_filepicker($mform, $name, null, $params);

        $name = 'previewrows';
        $options = array(1, 2, 5, 10, 15, 20, 50, 100, 1000, 100000);
        $options = array_combine($options, $options);
        $this->add_field_select($mform, $name, $options, PARAM_INT, 5);

        return array('preview', 'cancel');
    }

    /**
     * definition_preview
     *
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     * @todo Finish documenting this function
     */
    public function definition_preview($mform) {

        // transfer values from "upload" form
        $values = array('datafile' => PARAM_INT,
                        'formatfile' => PARAM_INT,
                        'previewrows' => PARAM_INT);
        $this->transfer_incoming_values($mform, $values);

        $this->add_heading($mform, 'settings', 'moodle', true);

        // Give user (another) chance to specify ignore values.
        $name = 'ignorevalues';
        $this->add_field_text($mform, $name, PARAM_TEXT, '', 64);

        $name = 'uploadaction';
        $options = $this->get_options_uploadaction();
        $default = self::ACTION_ADD_AND_UPDATE;
        $this->add_field_select($mform, $name, $options, PARAM_INT, $default);

        return array('review', 'back');
    }

    /**
     * get_options_uploadaction
     *
     * @param string $value (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_options_uploadaction($value=null) {
        $options = array(
            self::ACTION_ADD_NEW_ONLY => get_string('actionaddnewonly', $this->tool),
            self::ACTION_ADD_AND_UPDATE => get_string('actionaddandupdate', $this->tool),
            self::ACTION_UPDATE_EXISTING => get_string('actionupdateexisting', $this->tool),
            self::ACTION_ADD_UPDATE_REMOVE => get_string('actionaddupdateremove', $this->tool)
        );
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
     * @todo Finish documenting this function
     */
    public function definition_review($mform) {
        $values = array('datafile' => PARAM_INT,
                        'formatfile' => PARAM_INT,
                        'previewrows' => PARAM_INT,
                        'ignorevalues' => PARAM_TEXT);
        $this->transfer_incoming_values($mform, $values);
        return array('import', 'back');
    }

    /**
     * definition_buttons
     *
     * @param moodleform $mform representing the Moodle form
     * @param boolean $submit
     * @param boolean $cancel
     * @todo Finish documenting this function
     */
    public function definition_buttons($mform, $submit, $cancel) {
        if ($submit && $cancel) {
            $name = 'buttons';
            $mform->addGroup(array(
                $mform->createElement('submit', 'submit', get_string($submit, $this->tool)),
                $mform->createElement('cancel', 'cancel', get_string($cancel)),
            ), $name, '', array(' '), false);
            $mform->closeHeaderBefore($name);
        } else if ($submit) {
            $mform->addElement('submit', 'submit', get_string($submit, $this->tool));
        } else if ($cancel) {
            $mform->addElement('cancel', 'cancel', get_string($cancel));
        }
    }

    /**
     * get_state
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_state() {
        return $this->formstate;
    }

    /**
     * transfer_incoming_values
     */
    public function transfer_incoming_values($mform, $values) {
        foreach ($values as $name => $type) {
            if ($type==PARAM_INT) {
                $default = 0;
            } else {
                $default = '';
            }
            $value = optional_param($name, $default, $type);
            $mform->addElement('hidden', $name, $value);
            $mform->setType($name, $type);
        }
    }

    /**
     * set_next_formstate
     */
    public function set_next_formstate($mform, $value) {
        $name = 'formstate';
        unset($_POST[$name]);
        $mform->addElement('hidden', $name, $value);
        $mform->setType($name, PARAM_ALPHA);
    }

    /**
     * validation
     */
    function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

//        $usercontext = \context_user::instance($USER->id);
//
//        $fs = get_file_storage();
//        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['datafile'], 'id', false);
//
//        if ($files) {
//            $errors['datafile'] = get_string('required');
//            return $errors;
//        } else {
//            $file = reset($files);
//            if ($file->get_mimetype() != 'application/zip') {
//                $errors['datafile'] = get_string('invalidfiletype', 'error', $file->get_filename());
//                // better delete current file, it is not usable anyway
//                $fs->delete_area_files($usercontext->id, 'user', 'draft', $data['datafile']);
//            } else {
//                if (!$chpterfiles = toolvocab_import_get_chapter_files($file, $data['type'])) {
//                    $errors['datafile'] = get_string('errornochapters', $this->tool);
//                }
//            }
//        }

        return $errors;
    }

    /**
     * render_data_table
     *
     * @uses $CFG
     * @uses $USER
     * @return xxx
     * @todo Finish documenting this function
     */
    public function render_data_table() {
        global $CFG, $USER;

        // get the path to main PHPExcel file and object
        if ($this->phpspreadsheet) {
            // Moodle >= 3.8
            $phpexcel_filepath = $CFG->dirroot.'/lib/phpspreadsheet/vendor/autoload.php';
            $phpexcel_iofactory = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';
        } else {
            // Moodle 2.5 - 3.7
            $phpexcel_filepath = $CFG->dirroot.'/lib/phpexcel/PHPExcel/IOFactory.php';
            $phpexcel_iofactory = 'PHPExcel_IOFactory';
        }
        require_once($phpexcel_filepath);

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);

        list($datafilename, $datafilepath) = $this->get_datafileinfo($fs, $context, 'datafile');
        list($formatfilename, $formatfilecontent) = $this->get_formatfileinfo($fs, $context, 'formatfile');
        $format = $this->parse_format_xml($formatfilecontent, 'datafileformat');

        if (is_string($format)) {
            $table = $format;
        } else {
            $table = new \html_table();
            $table->head = array();
            $table->data = array();

            if ($datafilepath) {

                // Add datafilename to settings so that it is accessible later.
                $format->settings['datafilename'] = $datafilename;

                $reader = $phpexcel_iofactory::createReaderForFile($datafilepath);
                $workbook = $reader->load($datafilepath);

                if ($format === null) {
                    $format = $this->create_format_xml($workbook, $datafilename);
                    $table = \html_writer::tag('p', get_string('emptyxmlfile', $this->tool).' '.
                                                    get_string('showsampleformatxml', $this->tool));
                    $params = array('class' => 'rounded bg-dark text-white px-2 py-1');
                    $table .= \html_writer::tag('pre', htmlspecialchars($format), $params);
                } else {
                    $table->tablealign = 'center';
                    $table->id = $this->tool.'_'.$this->formstate;
                    $table->attributes['class'] = 'generaltable '.$this->tool;
                    $table->summary = get_string($this->formstate, $this->tool);

                    list($sheetcount, $rowcount) = $this->get_sheetcount_rowcount($workbook);
                    $table->caption = $this->render_caption($datafilename, $sheetcount, $rowcount);

                    $populate_table = 'populate_'.$this->formstate.'_table';
                    $this->$populate_table($workbook, $format, $table);

                    if (empty($table->data)) {
                        // No data found - shouldn't happen!!
                        $table = get_string('emptydatafile', $this->tool);
                    }
                }
            }
        }

        if (is_object($table)) {
            $table = \html_writer::table($table);
            $table = \html_writer::tag('div', $table, array('class' => 'flexible-wrap'));
        } else {
            $table = \html_writer::tag('p', $table).
                     \html_writer::tag('p', get_string('tryagain', $this->tool));
            $table = \html_writer::tag('div', $table, array('class' => 'alert alert-warning'));
        }

        if ($datafilepath) {
            unlink($datafilepath);
        }

        return $table;
    }

    /**
     * get_datafileinfo
     */
    public function get_datafileinfo($fs, $context, $paramname) {
        $filename = '';
        $filepath = '';
        if ($draftid = optional_param($paramname, 0, PARAM_INT)) {
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
        return array($filename, $filepath);
    }

    /**
     * get_formatfileinfo
     */
    public function get_formatfileinfo($fs, $context, $paramname) {
        $filename = '';
        $filecontent = '';
        if ($draftid = optional_param($paramname, 0, PARAM_INT)) {
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
        return array($filename, $filecontent);
    }

    /**
     * parse_format_xml
     */
    public function parse_format_xml($formatfilecontent, $xmlroot) {
        global $CFG;

        // get XML parsing library
        require_once($CFG->dirroot.'/lib/xmlize.php');

        if (empty($formatfilecontent)) {
            return null;
            //return get_string('emptyxmlfile', $this->tool);
        }

        $xml = xmlize($formatfilecontent);
        if (empty($xml)) {
            return get_string('invalidxmlfile', $this->tool);
        }

        $name = $xmlroot;
        if (empty($xml[$name]) || empty($xml[$name]['#'])) {
            return get_string('xmltagmissing', $this->tool, htmlspecialchars("<$xmlroot>"));
        }
        if (empty($xml[$name]['#']['sheet'])) {
            return get_string('xmltagmissing', $this->tool, htmlspecialchars("<sheet>"));
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
     */
    public function parse_format_xml_initnode(&$xml, $format, $name) {

        if (empty($xml['@'])) {
            $xml['@'] = array();
        }
        if (empty($xml['#'])) {
            $xml['#'] = array();
        }

        if (empty($xml['#']['setting'])) {
            $xml['#']['setting'] = array();
        }
        if (empty($format->settings)) {
            $format->settings = array();
        }
        $this->parse_format_xml_settings($xml, $format);

        if (empty($format->$name)) {
            $format->$name = array();
        }

        if (empty($xml['#']['record'])) {
            $xml['#']['record'] = array();
        }
        if (empty($format->records)) {
            $format->records = array();
        }
        $this->parse_format_xml_records($xml, $format);
    }

    /**
     * parse_format_xml_settings
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
     */
    public function parse_format_xml_records(&$xml, $format) {

        // initialize the index on records in the $format object.
        $rindex = count($format->records);

        // Add records.
        // Add record items
        $record = &$xml['#']['record'];
        $r = 0;
        while (array_key_exists($r, $record)) {

            // Initialize the structure for this record.
            $format->records[$rindex] = (object)array(
                'table' => '',
                'fields' => array()
            );

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
     */
    public function create_format_xml($workbook, $datafilename) {
        $nl = "\n";
        $tab = str_repeat(' ', 4);
        $i = 1; // indent counter

        $coffset = ($this->phpspreadsheet ? 1 : 0); // column offset

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$nl;
        $xml .= '<datafileformat type="'.$this->get_format_type($datafilename).'">'.$nl;

        $xml .= $nl;
        $xml .= str_repeat($tab, $i).$this->get_comment('explainsettings').$nl;
        $xml .= str_repeat($tab, $i++).'<settings>'.$nl;

        // Set default form settings
        $settings = array((object)array(
            'name' => 'uploadaction',
            'value' => self::ACTION_ADD_AND_UPDATE,
            'comment' => $this->get_options_uploadaction(self::ACTION_ADD_AND_UPDATE)
        ));

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
        $xml .= str_repeat($tab, --$i).'</settings>'.$nl;

        $xml .= $nl;
        $xml .= str_repeat($tab, $i).$this->get_comment('explainmetadata', 'sheets').$nl;
        $xml .= str_repeat($tab, $i++).'<sheets type="data">'.$nl;

        $smin = 1;
        $smax = $workbook->getSheetCount();

        for ($s = $smin; $s <= $smax; $s++) {
            $worksheet = $workbook->setActiveSheetIndex($s - 1);

            $xml .= $nl;
            $xml .= str_repeat($tab, $i).$this->get_comment('explainstartend', 'sheet').$nl;
            $xml .= str_repeat($tab, $i++).'<sheet start="'.$s.'" end="'.$s.'">'.$nl;

            $xml .= $nl;
            $xml .= str_repeat($tab, $i).$this->get_comment('explainmetadata', 'rows').$nl;

            list($rmin, $rmax) = $this->get_min_max_rows($worksheet);

            $xmlmetarow = '';
            $xmldatarow = '';
            for ($r = $rmin; $r <= $rmax; $r++) {

                list($cmin, $cmax) = $this->get_min_max_cols($worksheet, $r);

                if ($cmin < $cmax) {
                    $comment = $this->get_comment('explainstartend', 'row');
                    $xmlmetarow .= $nl.
                                   str_repeat($tab, $i).$comment.$nl.
                                   str_repeat($tab, $i).'<row start="'.$r.'" end="'.$r.'">'.$nl;
                    $xmldatarow .= $nl.
                                   str_repeat($tab, $i).$comment.$nl.
                                   str_repeat($tab, $i++).'<row start="'.($r + 1).'" end="'.$rmax.'">'.$nl;

                    $comment = $this->get_comment('explainstartend', 'column');
                    $xmlmetarow .= $nl.
                                   str_repeat($tab, $i).$comment.$nl.
                                   str_repeat($tab, $i).'<cells type="meta" start="'.($cmin + $coffset).'" end="'.($cmax + $coffset).'">'.$nl;
                    $xmldatarow .= $nl.
                                   str_repeat($tab, $i).$comment.$nl.
                                   str_repeat($tab, $i++).'<cells type="data" start="'.($cmin + $coffset).'" end="'.($cmax + $coffset).'">'.$nl;

                    $cells = array();
                    for ($c = $cmin; $c <= $cmax; $c++) {
                        $value = $this->get_singleline_value($worksheet, $c, $r);
                        $cleanvalue = strtolower($this->get_clean_text($value));
                        $xmlmetarow .= str_repeat($tab, $i).'<cell>'.$value.'</cell>'.$nl;
                        $xmldatarow .= str_repeat($tab, $i).'<cell>'.$cleanvalue.'</cell>'.$nl;
                    }

                    $xmlmetarow .= str_repeat($tab, --$i).'</cells>'.$nl;
                    $xmldatarow .= str_repeat($tab, $i).'</cells>'.$nl;

                    $xmlmetarow .= str_repeat($tab, --$i).'</row>'.$nl;
                    $xmldatarow .= str_repeat($tab, $i).'</row>'.$nl;

                    break; // stop looping through the rows
                }
            }

            $xml .= str_repeat($tab, $i++).'<rows type="meta">'.$nl;
            $xml .= $xmlmetarow;
            $xml .= str_repeat($tab, --$i).'</rows>'.$nl;
            
            $xml .= str_repeat($tab, $i++).'<rows type="data">'.$nl;
            $xml .= $xmldatarow;
            $xml .= str_repeat($tab, --$i).'</rows>'.$nl;

            $xml .= str_repeat($tab, --$i).'</sheet>'.$nl;
        }

        $xml .= str_repeat($tab, --$i).'</sheets>'.$nl;
        $xml .= '</datafileformat>'.$nl;

        return $xml;
    }

    /**
     * get_comment
     */
    public function get_comment($strname, $a=null) {
        return '<!-- '.get_string($strname, $this->tool, $a).' -->';
    }

    /**
     * get_min_max_rows
     *
     * @param object $worksheet representing a sheet from the data file
     * @return xxx
     * @todo Finish documenting this function
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
        return array($rmin, $rmax);
    }

    /**
     * get_min_max_cols
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

        return array($cmin, $cmax);
    }

    /**
     * get_format_type
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
     */
    public function get_clean_text($txt) {
        // Replace all punctuation and spaces with an underscore, "_".
        return preg_replace('/([[:punct:]]|[[:blank:]])+/', '_', $txt);
    }

    /**
     * get_total_rows
     */
    public function get_sheetcount_rowcount($workbook) {
        $sheetcount = $workbook->getSheetCount();
        $rowcount = 0;
        for ($s = 0; $s < $sheetcount; $s++) {
            $rowcount += $workbook->getSheet($s)->getHighestDataRow();
        }
        return array($sheetcount, $rowcount);
    }

    /**
     * number_format
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
     */
    public function render_caption($datafilename, $sheetcount, $rowcount) {
        $a = (object)array(
            'filename' => $datafilename,
            'sheetcount' => $sheetcount,
            'rowcount' => $this->number_format($rowcount)
        );
        $caption = get_string('sheetrowcount', $this->tool, $a);

        if ($this->formstate == 'review') {
            $a = $this->get_previewrows();
            $caption .= get_string('headingsandpreviewrows', $this->tool, $a);
        }

        $params = array('class' => 'font-weight-normal');
        return \html_writer::tag('p', \html_writer::tag('small', $caption), $params);
    }

    /**
     * get_previewrows
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_previewrows() {
        return optional_param('previewrows', 10, PARAM_INT);
    }

    /**
     * populate_preview_table
     * The preview table contains the raw data deom the data file (usually a spreadsheet).
     */
    public function populate_preview_table($workbook, $format, $table) {

        // override form defaults with settings from $format file
        if (isset($format->settings)) {
            $mform = $this->_form;
            foreach ($format->settings as $name => $value) {
                if ($mform->elementExists($name)) {
                    $mform->setDefault($name, $value);
                } else {
                    // Unknown form field, so skip it.
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
                            $text = get_string('row', $this->tool);
                            $table->head = $this->get_row_cells($worksheet, $r, $cmin, $cmax, $text);
                            $table->align = array_merge(array('center'), array_fill(0, $cmax, 'left'));
                        } else {
                            $text = $this->get_row_cells($worksheet, $r, $cmin, $cmax, $r, true);
                            $table->data[] = $text;
                            $rowindex++;
                            if ($rowindex >= $previewrows) {
                                break 4;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * populate_review_table
     */
    public function populate_review_table($workbook, $format, $table, $dryrun=true) {
        $this->populate_import_table($workbook, $format, $table, self::MODE_DRYRUN);
    }

    /**
     * populate_import_table
     */
    public function populate_import_table($workbook, $format, $table, $mode=self::MODE_IMPORT) {

        // Cache frequently used strings:
        $str = (object)array(
            'sheet' => get_string('sheet', $this->tool),
            'row' => get_string('row', $this->tool),
        );

        // Initialize the row index (number of rows processed so far in all sheets).
        $rowindex = 0;
        
        // Get total number of sheets and total number of rows.
        // The is only aprroximate and does not take account the
        // start/end settings in the XML file.
        list($sheetcount, $rowcount) = $this->get_sheetcount_rowcount($workbook);

        // Initialize counter for the number of rows previewed (=displayed).
        $previewrowsindex = 0;

        // Set limit for number of preview rows (0 means "no limit").
        if ($mode == self::MODE_IMPORT) {
            $previewrows = 0;
        } else { // self::MODE_DRYRUN
            $previewrows = $this->get_previewrows();
        }

        // Get info on the "vocab" tables in the database.
        // We will only allow access to these tables
        // and the fields that they contain.
        $tableinfo = $this->get_tableinfo('vocab', array('instances', 'attempts'));

        $vars = array();
        $filevars = array();
        $sheetvars = array();
        $rowvars = array();

        if ($table->caption) {
            if (preg_match('/<small[^>]*>(.*)<\/small>/s', $table->caption, $match)) {
                if (preg_match('/"([^"]+)"/', $match[1], $match)) {
                    $filevars['filename'] = $match[1];
                }
            }
        }

        // Initialize the array of totals (for reporting purposes).
        $this->setup_totals($tableinfo, $format);

        $this->get_item_settings($format, $vars, $tableinfo, $filevars, $mode);
        $this->get_item_records($format, $vars, $tableinfo, $mode);

        if ($mode == self::MODE_IMPORT) {
            $bar = new \progress_bar('vocabtool_import_pbar', 500, true);
            // see "lib/outputcomponents.php" for details
        } else {
            $bar = false;
        }

        foreach ($format->sheets as $ss => $sheet) {

            list($smin, $smax, $stype) = $this->get_sheet_range($workbook, $sheet);
            for ($s = $smin; $s <= $smax; $s++) {

                $worksheet = $workbook->setActiveSheetIndex($s - 1);
                $sheetname = $worksheet->getTitle();
                $sheetvars = array('sheet_name' => $sheetname); 

                $vars = $filevars;
                $this->get_item_settings($sheet, $vars, $tableinfo, $sheetvars, $mode);
                $this->get_item_records($sheet, $vars, $tableinfo, $mode);

                // Set the current sheet (for reporting purposes).
                $this->currentsheet = $s;
                $this->currentsheetname = $sheetname;

                $headers = array();
                foreach ($sheet->rows as $rr => $row) {

                    // Get the minimum and maximum row and columnn numbers in this $row set.
                    list($rmin, $rmax, $rtype) = $this->get_row_range($worksheet, $row);

                    // Loop through the rows in this $row set.
                    for ($r = $rmin; $r <= $rmax; $r++) {

                        // increment row index and update progress bar.
                        if ($bar) {
                            $rowindex++;
                            $msg = "{$str->sheet}: $s, ".
                                   "{$str->row}: $r ".
                                   "($rowindex/$rowcount)";
                            $bar->update($rowindex, $rowcount, $msg);
                        }

                        if ($rtype == self::TYPE_META) {
                            foreach ($row->cells as $c => $name) {
                                $headers[$c] = $this->get_cell_value($worksheet, $c, $r);
                            }
                        } else {
                            $rowvars = array();
                            $vars = array_merge($filevars, $sheetvars);
                            foreach ($row->cells as $c => $name) {
                                $value = $this->get_cell_value($worksheet, $c, $r);
                                $vars[$name] = $rowvars[$name] = $value;
                            }

                            // Generate output depending on table/field definitions in XML.
                            if ($this->skip_row($row, $vars, $tableinfo, $rowvars)) {
                                // empty row - it happens sometimes.
                            } else {
                                // Set the current row (for reporting purposes).
                                $this->currentrow = $r;
                                $this->currentrowname = $this->get_rowname($row, $vars, $tableinfo);

                                $this->get_item_settings($row, $vars, $tableinfo, $rowvars, $mode);
                                $this->get_item_records($row, $vars, $tableinfo, $mode);

                                $table->data[] = $this->report_totals_data();
                                $previewrowsindex++;
                            }
                        }
                        if ($mode == self::MODE_DRYRUN && $previewrowsindex >= $previewrows) {
                            break 4;
                        }
                    }
                }
            }
        }

        // Indicate completion on the progress bar. 
        if ($bar) {
            $msg = array();
            if ($added = $this->totals->added) {
                $added = $this->number_format($added);
                $msg[] = get_string('recordsadded', $this->tool, $added);
            }
            if ($found = $this->totals->found) {
                $found = $this->number_format($found);
                $msg[] = get_string('recordsfound', $this->tool, $found);
            }
            if ($error = $this->totals->error) {
                $error = $this->number_format($error);
                $msg[] = get_string('errorsfound', $this->tool, $error);
            }
            $listsep = get_string('listsep', 'langconfig').' ';
            if ($msg = implode($listsep, $msg)) {
                $msg = " ($msg)";
            }
            $msg = get_string('importcompleted', $this->tool).$msg;
            $bar->update_full(100, $msg);
        }

        // add field name and descriptions to header row
        if (empty($table->head) && count($table->data)) {
            $table->head = $this->report_totals_head($headers);
        }

        // Remove empty columns from the report.
        $this->report_totals_prune($table);
    }

    /**
     * Determine whether or not the given record should be skipped.
     * This assumes the <record> tag in the XML file contains a "skip"
     * attribute that defines the conditions under which a record should
     * be skipped. e.g. <record ... skip="EMPTY(synwords)" ...>
     * If the function is missing, or evaluates to FALSE, a record
     * for the current row will be added/found.
     *
     * @param object $record
     * @param  array $vars values from the cells in this row.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @return boolean TRUE if this record should be skipped; otherwise FALSE.
     */
    public function skip_record($record, &$vars, &$tableinfo) {
        $names = array('skip', 'skiprecord', 'recordskip');
        foreach ($names as $name) {
            if (property_exists($record, $name)) {
                return $this->format_field($tableinfo, $name, $record->$name, $vars);
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
     * @param  array $vars values and settings for in this row in the data file.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param  array $rowvars (passed by reference) values for this row in the data file.
     * @return boolean TRUE if this row should be skipped; otherwise FALSE.
     */
    public function skip_row($row, &$vars, &$tableinfo, &$rowvars) {

        if (empty(array_filter($rowvars))) {
            return true; // empty row - shouldn't happen !!
        }

        $names = array('skip', 'skiprow', 'rowskip');
        foreach ($names as $name) {
            if (array_key_exists($name, $row->settings)) {
                return $this->format_field($tableinfo, $name, $row->settings[$name], $vars);
            }
        }
        return false; // Assume that we do NOT skip this row.
    }

    /**
     * Get the "rowname" value for this row (e.g. the value in the "word" column).
     *
     * @param object $row settings, cell names, record definitions.
     * @param  array $vars values from the cells in this row.
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @return boolean TRUE if this row should be skipped; otherwise FALSE.
     */
    public function get_rowname($row, &$vars, &$tableinfo) {
        $name = 'rowname';
        if (empty($row->settings[$name])) {
            $labelsep = get_string('labelsep', 'langconfig');
            return $this->currentsheet.$labelsep.$this->currentrow;
        } else {
            $rowname = $row->settings[$name]; // e.g. VALUE(word)
            $rowname = $this->format_field($tableinfo, $name, $rowname, $vars);
            return trim($rowname, ' "'); // trim leading and trailing quotes.
        }
    }

    /**
     * get_tablenames
     */
    public function get_tableinfo() {
        global $DB;
        $info = array();

        // We only allow this tool access
        // to the following "vocab" tables,
        // which together form the "dictionary":
        $dictionarytables = array(
            'vocab_antonyms',
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
            'vocab_synonyms',
            'vocab_words'
        );

        // Access to ALL other Moodle tables is disallowed,
        // including the following "vocab" tables,
        // which contain information about users'
        // interaction with the dictionary data.
        //   vocab
        //   vocab_games
        //   vocab_game_(attempts|instances)
        //   vocab_word_(attempts|instances|usages)
        $usertables = array(
            'vocab',
            'vocab_games', // not needed?
            'vocab_game_attempts',
            'vocab_game_instances',
            'vocab_word_attempts',
            'vocab_word_instances',
            'vocab_word_usages'
        );

        foreach ($DB->get_tables() as $table) {
            if (in_array($table, $dictionarytables)) {
                $info[$table] = array();
                foreach ($DB->get_columns($table) as $column) {
                    $info[$table][] = $column->name;
                }
            }
        }

        return $info;
    }

    public function setup_totals(&$tableinfo, $format) {
        $this->totals = (object)array(
            'name' => $format->settings['datafilename'],
            'total' => 0,
            'added' => 0,
            'found' => 0,
            'error' => 0,
            'tables' => array()
        );
        foreach ($format->sheets as $sheet) {
            foreach ($sheet->rows as $row) {
                foreach ($row->records as $record) {
                    if (array_key_exists($record->table, $this->totals->tables)) {
                        continue; // already added
                    }
                    $this->totals->tables[$record->table] = (object)array(
                        'name' => get_string($record->table, $this->tool),
                        'total' => 0,
                        'added' => 0,
                        'found' => 0,
                        'error' => 0,
                        'sheets' => array()
                    );
                }
            }
        }
    }

    public function update_totals($table, $type, $msg='') {

        // Check the table name is valid (it should be).
        if (array_key_exists($table, $this->totals->tables)) {

            // Shortcuts to current sheet/row number.
            $s = $this->currentsheet;
            $r = $this->currentrow;

            // Ensure we have an object for the current sheet.
            if (! array_key_exists($s, $this->totals->tables[$table]->sheets)) {
                $this->totals->tables[$table]->sheets[$s] = (object)array(
                    'name' => $this->currentsheetname,
                    'total' => 0,
                    'added' => 0,
                    'found' => 0,
                    'error' => 0,
                    'rows' => array()
                );
            }

            // Ensure we have an object for the current row.
            if (! array_key_exists($r, $this->totals->tables[$table]->sheets[$s]->rows)) {
                $this->totals->tables[$table]->sheets[$s]->rows[$r] = (object)array(
                    'name' => $this->currentrowname,
                    'total' => 0,
                    'added' => 0,
                    'found' => 0,
                    'error' => 0,
                    'msg' => array()
                );
            }

            switch (substr($type, 0, 3)) {
                case 'err': $type = 'error'; break;
                case 'add': $type = 'added'; break;
                default:    $type = 'found';
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

    public function report_totals_data() {

        // Shortcuts to current sheet/row number.
        $s = $this->currentsheet;
        $r = $this->currentrow;
        $labelsep = get_string('labelsep', 'langconfig');
        
        $cells = array();

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

            $msg = array();
            if (array_key_exists($s, $totals->sheets)) {
                if (array_key_exists($r, $totals->sheets[$s]->rows)) {
                    if ($totals->sheets[$s]->rows[$r]->total) {
                        if ($added = $totals->sheets[$s]->rows[$r]->added) {
                            $msg[] = get_string('recordsadded', $this->tool, $added);
                        }
                        if ($found = $totals->sheets[$s]->rows[$r]->found) {
                            $msg[] = get_string('recordsfound', $this->tool, $found);
                        }
                    }
                    $errors += $totals->sheets[$s]->rows[$r]->error;
                    if (count($totals->sheets[$s]->rows[$r]->msg)) {
                        $params = array('class' => 'bg-warning text-light rounded px-1 error');
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
                $params = array('class' => 'list-unstyled');
                $msg = \html_writer::alist($msg, $params);
            } else {
                $msg = '';
            }
            $cells[] = new \html_table_cell($msg);
        }

        if ($errors) {
            $text = get_string('errorsfound', $this->tool, $errors);
            $params = array('class' => 'bg-warning text-light rounded px-1 errors');
            $cells[1]->text .= ' '.\html_writer::tag('div', $text, $params);
        }

        return $cells;
    }

    public function report_totals_head(&$headers) {
        $cells = array();

        $label = get_string('sheet', $this->tool);
        $label .= get_string('labelsep', 'langconfig');
        $label .= get_string('row', $this->tool);

        $cells[] = new \html_table_cell($label);
        $cells[] = new \html_table_cell(reset($headers));

        foreach ($this->totals->tables as $tablename => $totals) {
            $cells[] = new \html_table_cell(get_string($tablename, $this->tool));
        }

        return $cells;
    }

    public function report_totals_prune($table) {
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

                // decrement column index, as this column no longer exists.
                $c--;
            }
            $c++;
        }
    }

    /**
     * get_sheet_range
     */
    public function get_sheet_range($workbook, $sheet) {
        $defaultmin = 1;
        $defaultmax = $workbook->getSheetCount();
        return $this->get_item_range($sheet, 'sheet', $defaultmin, $defaultmax);
    }

    /**
     * get_row_range
     */
    public function get_row_range($worksheet, $row) {
        $defaultmin = 1;
        $defaultmax = $worksheet->getHighestDataRow();
        return $this->get_item_range($row, 'row', $defaultmin, $defaultmax);
    }

    /**
     * get_item_range
     */
    public function get_item_range($item, $prefix, $defaultmin, $defaultmax) {

        if (isset($item->settings)) {
            $settings = $item->settings;
        } else {
            $settings = array();
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

        return array($min, $max, $type);
    }

    /**
     * get_cell_range
     * this method is NOT USED anywhere.
     * @todo remove this method from this class.
     */
    public function get_cell_range($row) {

        $cmin = 0;
        $cmax = count($row->cells);
        $ctype = self::TYPE_DATA;

        if (isset($row->settings) && is_array($row->settings)) {
            if (array_key_exists('type', $row->settings)) {
                if ($row->settings['type'] == 'meta') {
                    $rtype = self::TYPE_META;
                }
            }
        }

        return array($cmin, $cmax);
    }

    /**
     * ignore_value
     *
     * @param string $value
     * @return xxx
     * @todo Finish documenting this function
     */
    public function ignore_value($value) {
        $name = 'ignorevalues';
        if ($this->$name === null) {
            $mform = $this->_form;
            // We cannot use $mform->elementExists()
            // because $mform has not been setup yet.
            if ($this->$name = optional_param($name, '', PARAM_TEXT)) {
                $this->$name = explode(',', $this->$name);
                $this->$name = array_map('trim', $this->$name);
                $this->$name = array_filter($this->$name);
            } else {
                $this->$name = array(); // shouldn't happen !!
            }
        }
        if (strpos($value, 'storeKey') === false) {
            return in_array($value, $this->ignorevalues);
        } else {
            return true; // always remove "storeKey" values.
        }
    }

    /**
     * get_row_cells
     *
     * @param worksheet
     * @param integer $r the row number
     * @param integer $cmin the lowet column number
     * @param integer $cmax the highest column number
     * @param string $text to go in first column
     * @param boolean $isheader(optional, default=FALSE) whether or not $text is a header
     */
    public function get_row_cells($worksheet, $r, $cmin, $cmax, $text, $cellheader=false) {
        $cells = array();
        for ($c = $cmin; $c <= $cmax; $c++) {
            $cells[] = $this->get_cell_value($worksheet, $c, $r);
        }

        $cell = new \html_table_cell();
        $cell->text = ($text ? $text : '');
        $cell->header = ($cellheader ? true : false);
        $cells = array_merge(array($cell), $cells);

        return $cells;
    }

    /**
     * get_singleline_value
     */
    protected function get_singleline_value($worksheet, $c, $r) {
        $value = $this->get_cell_value($worksheet, $c, $r);
        return preg_replace('/\s+/s', ' ', $value);
    }

    /**
     * get_cell_value
     */
    protected function get_cell_value($worksheet, $c, $r) {
        $coffset = ($this->phpspreadsheet ? 1 : 0); // column offset
        $value = $worksheet->getCellByColumnAndRow($c + $coffset, $r)->getFormattedValue();
        return ($this->ignore_value($value) ? '' : $value);
    }

    /**
     * format_fields
     */
    public function format_fields(&$tableinfo, &$fields, &$vars) {
        $values = array();
        if (is_array($fields)) {
            foreach ($fields as $fieldname => $value) {
                $values[$fieldname] = $this->format_field($tableinfo, $fieldname, $value, $vars);
            }
        }
        return $values;
    }

    /**
     * format_field
     */
    public function format_field(&$tableinfo, $fieldname, $value, &$vars) {

        // These are the functions that we know about:
        $search = '/EMPTY|IDS|ID|VALUE|JOIN|SPLIT|NEWLINE|REPLACE|SUBSTRING|LOWERCASE|PROPERCASE|UPPERCASE/u';

        // search and replace function names (starting from the rightmost one)
        if (preg_match_all($search, $value, $matches, PREG_OFFSET_CAPTURE)) {

            for ($m = count($matches[0]); $m > 0; $m--) {
                // Cache the function name and start position.
                list($match, $start) = $matches[0][$m - 1];

                $mode = 0;
                // 0: find open parentheses
                // 1: find start argument
                // 2: find end unquoted argument
                // 3: find end quoted argument
                // 4: find comma or closing parenthesis
                // 5: complete
                // 6: error !!

                $args = array();
                $a = -1; // index on $args

                $i_max = strlen($value);
                $i = ($start + strlen($match));
                while ($i < $i_max && $mode < 5) {
                    switch ($mode) {

                        case 0:
                            // expecting opening parenthesis
                            switch ($value[$i]) {

                                // leading white space is ignored
                                case ' ':
                                    break;

                                // opening parenthesis - yay!
                                case '(':
                                    $mode = 1;
                                    break;

                                default:
                                    $args = "No open bracket found for $match";
                                    $mode = 6;
                            }
                            break;

                        case 1:
                            // expecting start of argument
                            switch ($value[$i]) {

                                // leading white space is ignored
                                case ' ':
                                    break;

                                case ',':
                                    $mode = 6;
                                    $args = "Comma not expected parsing $match";
                                    break;

                                // closing parenthesis (i.e. end of arguments)
                                case ')':
                                    $mode = 5;
                                    break;

                                // start of a quoted quoted argument
                                case '"':
                                    $a++;
                                    $args[$a] = '';
                                    $mode = 3;
                                    break;

                                // first char of an unquoted argument
                                default:
                                    $a++;
                                    $args[$a] = $value[$i];
                                    $mode = 2;
                            }
                            break;

                        case 2:
                            // expecting end of unquoted argument
                            switch ($value[$i]) {
                                case ',':
                                    $mode = 1;
                                    break;

                                case ')':
                                    $mode = 5;
                                    break;

                                default:
                                    // next char of an unquoted argument
                                    $args[$a] .= $value[$i];
                            }
                            break;

                        case 3:
                            // expecting end of quoted argument
                            switch ($value[$i]) {
                                case '\\':
                                    // The backslash signifies an escaped character.
                                    // Skip the slash and store the following char.
                                    $i++;
                                    $args[$a] .= $value[$i];
                                    break;

                                case '"':
                                    // end of quoted string
                                    $mode = 4;
                                    break;

                                default:
                                    // next char of an quoted argument
                                    $args[$a] .= $value[$i];
                            }
                            break;

                        case 4:
                            // expecting comma or closing parenthesis
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
                } // end while

                if ($mode == 6) {
                    $replace = $args; // error message
                } else {
                    $replace = $this->format_function($tableinfo, $match, $args, $vars);
                    if (is_array($replace)) {
                        if (empty($replace)) {
                            $replace = '';
                        } else if (count($replace) == 1) {
                            $replace = reset($replace);
                        } else {
                            // e.g. the result of SPLIT(";", "happy; joyful; merry")
                            $replace = $this->get_value_alias($replace);
                        }
                    }
                }
                $value = substr_replace($value, $replace, $start, ($i - $start));

            } // end for ($m ...; $m--)
        } // end if (preg_match_all(...))

        return $value;
    }

    /**
     * Store an array/object value in the internal cache of non-scalar values
     * and return a string that is an alias to the cached value.
     *
     * @param mixed $value an array or object that is to be cached
     * @return string the alias of the given value
     */
    protected function get_value_alias($value) {
        $alias = 'ALIAS_'.count($this->aliases);
        $this->aliases[$alias] = $value;
        return $alias;
    }

    /**
     * Determines whether or not the given string is
     * a valid alias to the non-scalar cache.
     *
     * @param string $alias
     * @return boolean TRUE if the given string is valid alias; otherwise FALSE.
     */
    protected function is_value_alias($alias) {
        if (substr($alias, 0, 6) == 'ALIAS_') {
            if (is_numeric(substr($alias, 6))) {
                if (array_key_exists($alias, $this->aliases)) {
                    return true;
                } else {
                    echo 'Oops, $alias has been deleted: '.$alias;
                    die;
                }
            }
        }
        return false; // Not an alias.
    }

    /**
     * Retrieve an non-scalar value that has been cached with an alias.
     * To reduce memory requirements, value will be removed from the cache.
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
     * format_function
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $functionname name of the function
     * @param array $args (passed by reference) arguments for the specified $functionname
     * @param array $vars (passed by reference) values for the current row in the data file
     * @return mixed $result of the specified function using the given arguments
     * @todo Finish documenting this function
     */
    public function format_function(&$tableinfo, $functionname, &$args, &$vars) {

        // Convert aliases to non-scalar values (e.g. arrays).
        $this->get_alias_values($args);

        switch ($functionname) {

            case 'EMPTY':
                // Argument is empty (or missing).
                if (empty($args[0])) {
                    return true;
                }
                // Argument could be a column/setting name.
                if (array_key_exists($args[0], $vars)) {
                    return empty($vars[$args[0]]);
                }
                // Argument is not empty.
                return false;

            case 'IDS': // (table, field1, values1, field2, values2, ...)

                $table = (isset($args[0]) ? $args[0] : '');
                if (empty($table) || ! is_string($table)) {
                    return array();
                }

                $params = array();
                $scalarvalues = array();

                $i = 1;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $name = $args[$i++];
                    $values = $args[$i++];
                    if (is_scalar($name) && $name && $values) {
                        if (is_array($values)) {
                            foreach ($values as $p => $value) {
                                if (empty($params[$p])) {
                                    $params[$p] = array();
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
                $ids = array();
                foreach (array_keys($params) as $p) {
                    $ids[] = $this->get_record_ids($tableinfo, $table, $params[$p]);
                }
                return $ids;

            case 'ID': // (table, field1, value1, ...)

                $table = (isset($args[0]) ? $args[0] : '');
                if (empty($table) || ! is_string($table)) {
                    return '';
                }

                $params = array();
                $emptyvalue = false;

                $i = 1;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $name = $value = $args[$i++];
                    $value = $value = $args[$i++];
                    $params[$name] = $value;
                    if (empty($value)) {
                        $emptyvalue = true;
                    }
                }
                if (empty($params) || $emptyvalue) {
                    return '';
                }
                return $this->get_record_ids($tableinfo, $table, $params);

            case 'VALUE': // (name, default='')
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

            case 'JOIN': // (joiner, string)
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    if (array_key_exists(1, $args) && is_string($args[1])) {
                        return implode($args[0], $args[1]);
                    }
                }
                return array();

            case 'SPLIT': // (separator, string)
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    if (array_key_exists(1, $args) && is_string($args[1])) {
                        // In case we have more than one array, we could append them to the values.
                        // $values = implode($args[0], array_slice($args, 1));
                        $values = explode($args[0], $args[1]);
                        $values = array_map('trim', $values);
                        $values = array_filter($values);
                        return $values;
                    }
                }
                return array();

            case 'NEWLINE':
                return "\n";

            case 'REPLACE': // (string, search1, replace1, ...)
                $i = 1;
                $params = array();
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $params[$args[$i++]] = $args[$i++];
                }
                if (empty($params)) {
                    return $args[0];
                }
                return strtr($args[0], $params);

            case 'SUBSTRING':
                switch (count($args)) {
                    case 0: $args[0] = ''; // intentional drop through
                    case 1: $args[1] =  1; // intentional drop through
                    case 2: $args[2] =  core_text::strlen($args[0]);
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
     * @param array $itemvars (passed by reference)
     * @return void, but may update $vars and $itemvars
     * @todo Finish documenting this function
     */
    public function get_item_settings($item, &$vars, &$tableinfo, &$itemvars) {
        foreach ($item->settings as $name => $value) {
            $vars[$name] = $itemvars[$name] = $this->format_field($tableinfo, $name, $value, $vars);
        }
    }

    /**
     * get_item_records
     *
     * @param object $item representing an item in the XML file
     * @param array $vars (passed by reference) values for the current row in the data file
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @todo Finish documenting this function
     */
    public function get_item_records($item, &$vars, &$tableinfo) {
        foreach ($item->records as $record) {

            if ($this->skip_record($record, $tableinfo, $vars)) {
                continue;
            }

            $fields = $this->format_fields($tableinfo, $record->fields, $vars);

            // Convert aliases to non-scalar values (e.g. arrays).
            $this->get_alias_values($fields);

            if (in_array('', $fields, true)) {
                // some fields are empty, so don't try to add/fetch the id.
            } else {
                $this->get_record_ids($tableinfo, $record->table, $fields);
            }
        }
    }

    /**
     * get_record_ids
     *
     * @param array $tableinfo (passed by reference) two dimensional array of accessible tables and columns
     * @param string $table name of a table in the database
     * @param array $fields of database field names (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_record_ids(&$tableinfo, $table, &$fields) {

        if (! array_key_exists($table, $tableinfo)) {
            throw new \moodle_exception('tableaccessnotallowed', $this->tool, '', $table);
        }

        foreach ($fields as $name => $value) {
            if (! in_array($name, $tableinfo[$table])) {
                $a = (object)array(
                    'tablename' => $table,
                    'fieldname' => $name,
                );
                throw new \moodle_exception('fieldaccessnotallowed', $this->tool, '', $a);
            }
            if (empty($value) || $value === '0') {
                $a = (object)array(
                    'tablename' => $table,
                    'fieldname' => $name,
                );
                unset($fields[$name]);
                //throw new \moodle_exception('idparametermissing', $this->tool, '', $a);
            }
        }

        $fieldsets = array(array());
        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                $fcount = count($fieldsets);
                $vcount = count($value);
                for ($v=0; $v<$vcount; $v++) {
                    for ($f=0; $f<$fcount; $f++) {
                        $findex = $f + ($fcount * $v);
                        if (empty($fieldsets[$findex])) {
                            $fieldsets[$findex] = $fieldsets[$f];
                        }
                        $fieldsets[$findex][$name] = $value[$v];
                    }
                }
            } else if (is_scalar($value)) {
                $fcount = count($fieldsets);
                for ($f=0; $f<$fcount; $f++) {
                    $fieldsets[$f][$name] = $value;
                }
            }
        }

        $ids = array();
        foreach ($fieldsets as $fieldset) {
            if ($id = $this->get_record_id($table, $fieldset)) {
                $ids[] = $id;
            }
        }

        switch (count($ids)) {
            case 0: return 0;
            case 1: return reset($ids);
            default: return $ids;
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
     * @return integer
     */
    public function get_record_id($table, $fields) {
        global $DB;
        $this->fix_field_values($table, $fields);

        // We want to do just the following:
        //     $id = $DB->get_field($table, 'id', $fields);
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

        if ($id === false || $id === 0 || $id === null) {
            $id = $DB->insert_record($table, $fields);
            $this->update_totals($table, 'added');
        }
        return $id;
    }

    public function fix_field_values($table, &$fields) {
        global $DB;

        $columns = $DB->get_columns($table);
        foreach ($columns as $name => $column) {
            if ($name == 'id') {
                continue;
            }
            if (empty($column->not_null)) {
                // e.g. vocab_pronunciations.fieldid
                continue;
            }
            // We don't need to report all of these.
            // e.g. vocab_synonyms.synonymwordid
            if (substr($name, -2) == 'id' && empty($fields[$name])) {
                $msg = get_string('missingfield', 'error', $name);
                $this->update_totals($table, 'error', $msg);
            }
        }

        foreach ($fields as $name => $value) {
            $fields[$name] = $value = trim($value, ' "');

            if (array_key_exists($name, $columns)) {

                $column = $columns[$name];
                switch ($column->meta_type) {
                    /**
                     * lib/dml/database_column_info.php
                     * R - counter (integer primary key)
                     * I - integers
                     * N - numbers (floats)
                     * C - characters and strings
                     * X - texts
                     * B - binary blobs
                     * L - boolean (1 bit)
                     * T - timestamp - unsupported
                     * D - date - unsupported
                     */

                    case 'C':
                    case 'X':
                        $maxlength = $column->max_length;
                        $length = \core_text::strlen($value);
                        if ($length > $maxlength) {
                            // Shorten the string, at a word boundary if possible,
                            // but with no trailing string. ("lib/moodlelib.php")
                            $fields[$name] = shorten_text($value, $maxlength, false, '');
                            $msg = get_string('valueshortened', $this->tool, (object)array(
                                'fieldname' => $name,
                                'maxlength' => $maxlength
                            ));
                            $this->update_totals($table, 'error', $msg);
                        }
                        break;

                    case 'I':
                    case 'N':
                        if (is_numeric($value)) {
                            // do nothing
                        } else if (empty($column->has_default)) {
                            $fields[$name] = 0; // No default.
                        } else if (isset($column->default_value)) {
                            $fields[$name] = $column->default_value;
                        } else {
                            $fields[$name] = 0; // No default value.
                        }
                        break;
                } // end switch
            }
        }
    }

    /**
     * get_punctuation
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function get_punctuation() {
        if ($this->punctuation === null) {
            $this->punctuation =array(
                /* 00D7 */ '×' => '*',

                // "General Punctuation"
                // https://0g0.org/category/2000-206F/1/
                /* 2014 */ '—' => '-', '‖' => '|', /* 2016 */
                /* 2018 */ '‘' => "'", '’' => "'", /* 2019 */
                /* 201C */ '“' => '"', '”' => '"', /* 201D */
                /* 2026 */ '…' => '-',

                // "CJK Symbols and Punctuation" 3000-303F
                // https://0g0.org/category/3000-303F/1/
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

                // "Halfwidth and Fullwidth Forms" FF00-FFEF
                // https://0g0.org/category/FF00-FFEF/1/
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
                /* FFE1 */ '￡' => '£', '￥' => '¥' /* FFE5 */
            );
        }
        return $this->punctuation;
    }
}
