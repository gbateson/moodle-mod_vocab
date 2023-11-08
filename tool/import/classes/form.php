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
        $options = array(1, 2, 5, 10, 20, 100, 1000, 100000);
        $options = array_combine($options, $options);
        $this->add_field_select($mform, $name, $options, PARAM_INT, 5);

        $name = 'ignorevalues';
        $this->add_field_text($mform, $name, PARAM_TEXT, '', 64);

        // Store the course module id.
        $name = 'id';
        $mform->addElement('hidden', $name, optional_param($name, 0, PARAM_INT));
        $mform->setType($name, PARAM_INT);

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
        $values = array('id' => PARAM_INT,
                        'datafile' => PARAM_INT,
                        'formatfile' => PARAM_INT,
                        'previewrows' => PARAM_INT,
                        'ignorevalues' => PARAM_TEXT);
        $this->transfer_incoming_values($mform, $values);

        $this->add_heading($mform, 'settings', 'moodle', true);

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
        $values = array('id' => PARAM_INT,
                        'datafile' => PARAM_INT,
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
                    $table->caption = $this->render_caption($datafilename, $workbook);

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

        // Initialize the main $format object.
        $format = new \stdClass();

        // Setup sheets, settings and records for this file.
        $this->parse_format_xml_initnode($xml[$xmlroot], $format, 'sheets');

        $s = 0;
        $sheet = &$xml[$xmlroot]['#']['sheet'];
        while (array_key_exists($s, $sheet)) {

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

        // initizlize the index on records in the $format object.
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
     * render_caption
     */
    public function render_caption($datafilename, $workbook) {
        $previewrows = $this->get_previewrows();
        $sheetcount = $workbook->getSheetCount();
        $rowcount = 0;
        for ($s = 0; $s < $sheetcount; $s++) {
            $rowcount += $workbook->getSheet($s)->getHighestDataRow();
        }
        $rowcount = number_format($rowcount,
            0, // The number of decimal places.
            get_string('decsep', 'langconfig'),
            get_string('thousandssep', 'langconfig')
        );
        $a = (object)array(
            'filename' => $datafilename,
            'sheetcount' => $sheetcount,
            'rowcount' => $rowcount
        );
        return \html_writer::tag('p', \html_writer::tag('small',
            get_string('sheetrowcount', $this->tool, $a).' '.
            get_string('headingsandpreviewrows', $this->tool, $previewrows)
        ), array('class' => 'font-weight-normal'));
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

        $rowcount = 0;
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
                            $rowcount++;
                            if ($rowcount >= $previewrows) {
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

        $rowcount = 0;
        if ($mode == self::MODE_IMPORT) {
            $previewrows = 0;
        } else {
            $previewrows = $this->get_previewrows();
        }

        // Get info on the "vocab" tables in the database.
        // We will only allow access to these tables and
        // the fields that they contain.
        $tableinfo = $this->get_tableinfo('vocab', array('instances', 'attempts'));

        $vars = array();
        $filevars = array();
        $sheetvars = array();
        $rowvars = array();
        $recordids = array();

        if ($table->caption) {
            if (preg_match('/<small[^>]*>(.*)<\/small>/s', $table->caption, $match)) {
                if (preg_match('/"([^"]+)"/', $match[1], $match)) {
                    $filevars['filename'] = $match[1];
                }
            }
        }

        $this->get_item_settings($format, $vars, $tableinfo, $filevars);
        $this->get_item_records($format, $vars, $tableinfo, $recordids);

        $separator = get_string('labelsep', 'langconfig');
        foreach ($format->sheets as $ss => $sheet) {

            list($smin, $smax, $stype) = $this->get_sheet_range($workbook, $sheet);

            for ($s = $smin; $s <= $smax; $s++) {
                $worksheet = $workbook->setActiveSheetIndex($s - 1);

                $sheetname = $worksheet->getTitle();
                $sheetvars = array('sheet_name' => $sheetname); 

                $vars = $filevars;
                $this->get_item_settings($sheet, $vars, $tableinfo, $sheetvars);
                $this->get_item_records($sheet, $vars, $tableinfo, $recordids);

                $headers = array();
                foreach ($sheet->rows as $rr => $row) {

                    // Get the minimum and maximum row and columnn numbers in this $row set.
                    list($rmin, $rmax, $rtype) = $this->get_row_range($worksheet, $row);
                    list($cmin, $cmax) = $this->get_cell_range($row);

                    // Loop through the rows in this row set.
                    for ($r = $rmin; $r <= $rmax; $r++) {


                        if ($rtype == self::TYPE_META) {
                            foreach ($row->cells as $c => $name) {
                                $headers[$c] = $this->get_cell_value($worksheet, $c, $r);
                            }
                        } else {
                            $rowvars = array();
                            $vars = array_merge($filevars, $sheetvars);
                            foreach ($row->cells as $c => $name) {
                                $vars[$name] = $rowvars[$name] = $this->get_cell_value($worksheet, $c, $r);
                            }

                            // Generate output depending on table/field definitions in XML.
                            if (empty(array_filter($rowvars))) {
                                // empty row - shouldn't happen !!
                            } else {
                                $this->get_item_settings($row, $vars, $tableinfo, $rowvars, $mode);
                                $this->get_item_records($row, $vars, $tableinfo, $recordids, $mode);

                                $data = array();
                                foreach ($recordids as $tablename => $ids) {
                                    $data[] = count($ids).' records were checked/added in the '.$tablename.' table.';
                                }
                                $cell = new \html_table_cell($s.$separator.$r);
                                $cell->header = true;
                                $table->data[] = array_merge(array('row' => $cell), $data);
                                $rowcount++;
                            }
                        }
                        if ($mode==self::MODE_DRYRUN && $rowcount >= $previewrows) {
                            break 4;
                        }
                    }
                }
            }
        }

        // add field name and descriptions to header row
        if (empty($table->head) && count($table->data)) {
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
        $usertables = array(
            'vocab',
            'vocab_game_attempts',
            'vocab_game_instances',
            'vocab_games', // not needed?
            'vocab_word_attempts',
            'vocab_word_instances',
            'vocab_word_usages'
        );

        // Access to ALL other tables is disallowed,
        // including the following "vocab" tables,
        // which contain information about users'
        // interaction with the dictionary data.
        //   vocab
        //   vocab_games
        //   vocab_game_(attempts|instances)
        //   vocab_word_(attempts|instances|usages)

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

        $name = $prefix.'type';
        if (array_key_exists($name, $settings)) {
            $type = $settings[$name];
        } else if (array_key_exists('type', $settings)) {
            $type = $settings['type'];
        } else {
            $type = '';
        }
        if ($type == 'meta') {
            $type = self::TYPE_META;
        } else {
            $type = self::TYPE_DATA;
        }

        $name = $prefix.'start';
        if (array_key_exists($name, $settings) && is_numeric($settings[$name])) {
            $min = $settings[$prefix.'start'];
        } else if (array_key_exists('start', $settings) && is_numeric($settings['start'])) {
            $min = $settings['start'];
        } else {
            $min = '';
        }
        if (is_numeric($min) && $min >= $defaultmin) {
            $min = intval($min);
        } else {
            $min = $defaultmin;
        }

        $name = $prefix.'end';
        if (array_key_exists($name, $settings) && is_numeric($settings[$name])) {
            $max = $settings[$prefix.'end'];
        } else if (array_key_exists('end', $settings) && is_numeric($settings['end'])) {
            $max = $settings['end'];
        } else {
            $max = '';
        }
        if (is_numeric($max) && $max <= $defaultmax) {
            $max = intval($max);
        } else {
            $max = $defaultmax;
        }

        return array($min, $max, $type);
    }

    /**
     * get_cell_range
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
            if ($mform->elementExists($name)) {
                $this->$name = $mform->getElement($name)->getValue();
                $this->$name = explode(',', $this->$name);
                $this->$name = array_map('trim', $this->$name);
                $this->$name = array_filter($this->$name);
            } else {
                $this->$name = array(); // shouldn't happen !!
            }
        }
        if (strpos($value, 'storeKey')) {
            return true;
        }
        return in_array($value, $this->ignorevalues);
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
    public function format_fields(&$tableinfo, $fields, &$vars) {
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

        $search = '/IDS|ID|VALUE|JOIN|SPLIT|NEWLINE|REPLACE|SUBSTRING|LOWERCASE|PROPERCASE|UPPERCASE/u';

        // search and replace function names (starting from the last one)
        if (preg_match_all($search, $value, $matches, PREG_OFFSET_CAPTURE)) {

            for ($m = count($matches[0]); $m > 0; $m--) {
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

                $i = ($start + strlen($match));
                if (is_string($value)) {
                    $i_max = strlen($value);
                } else {
                    debugging('STOP - array $value detected');
                    print_object($value);
                    die;
                    $i_max = 0; // this is not enough to prevent errors later !!
                }
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
                                case '"':
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
                }

                if ($mode == 6) {
                    $replace = $args; // error message
                } else {
                    // We no longer substitute var names with values.
                    // If you want to do that, use the VALUE(varname) function.
                    //foreach ($args as $a => $arg) {
                    //    if (array_key_exists($arg, $vars)) {
                    //        $args[$a] = $vars[$arg];
                    //    }
                    //}
                    $replace = $this->format_function($tableinfo, $match, $args, $vars);
                    if (is_array($replace)) {
                        if (empty($replace) || in_array('N/A', $replace)) {
                            $replace = '';
                        } else if (count($replace) == 1) {
                            $replace = reset($replace);
                        }
                    }
                }
                if (is_array($replace)) {
                    $value = $replace;
                } else {
                    $value = substr_replace($value, $replace, $start, ($i - $start));
                }
            }
        }

        return $value;
    }

    /**
     * format_function
     *
     * @param array $tableinfo two dimensional array of tables and columns which may be accessed (passed by reference)
     * @param string $name
     * @param array $args (passed by reference)
     * @param array $vars of values for the current row (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function format_function(&$tableinfo, $name, &$args, &$vars) {

        switch ($name) {

            case 'IDS': // (table, field, values)

                $ids = array();
                $table = (isset($args[0]) ? $args[0] : '');
                $field = (isset($args[1]) ? $args[1] : '');
                $values = (isset($args[2]) ? $args[2] : array());

                if (is_string($table) && $table) {
                    if (is_string($field) && $field) {
                        if (is_array($values) && count($values)) {

                            foreach ($values as $value) {
                                $params = array($field => $value);
                                $ids[] = $this->get_record_ids($tableinfo, $table, $params);
                            }
                        }
                    }
                }

                return $ids;

            case 'ID': // (table, field1, value1, ...)

                $id = 0;

                $params = array();
                $tablename = (isset($args[0]) ? $args[0] : '');

                $i = 1;
                while (array_key_exists($i, $args) && array_key_exists($i + 1, $args)) {
                    $params[$args[$i++]] = $args[$i++];
                }

                if (is_string($tablename) && $tablename && count($params)) {
                    $id = $this->get_record_ids($tableinfo, $tablename, $params);
                }
                return $id;


            case 'VALUE': // (name, default='')
                if (array_key_exists(0, $args) && is_string($args[0])) {
                    if (is_numeric($args[0])) {
                        // Probably an id.
                        return $args[0];
                    }
                    if (array_key_exists($args[0], $vars)) {
                        // A var name.
                        return $vars[$args[0]];
                    }
                    
                    // A text value.
                    return $args[0];
                }
                if (array_key_exists(1, $args) && is_string($args[1])) {
                    return $args[1]; // default
                }
                return ''; // No default specified.

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
                        $value = implode(',', array_slice($args, 1));
                        $value = explode($args[0], $value);
                        $value = array_map('trim', $value);
                        $value = array_filter($value);
                        return $value;
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
     * @param array $vars of values for the current row (passed by reference)
     * @param array $tableinfo two dimensional array of tables and columns which may be accessed (passed by reference)
     * @param array $itemvars (passed by reference)
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
     * @param array $vars of values for the current row (passed by reference)
     * @param array $tableinfo two dimensional array of tables and columns which may be accessed (passed by reference)
     * @param array $recordids of ids from the database (passed by reference)
     * @todo Finish documenting this function
     */
    public function get_item_records($item, &$vars, &$tableinfo, &$recordids) {
        foreach ($item->records as $record) {
            $fields = $this->format_fields($tableinfo, $record->fields, $vars);
            if (in_array("", $fields, true)) {
                // some fields are empty, so don't try to add/fetch the id.
            } else if ($ids = $this->get_record_ids($tableinfo, $record->table, $fields)) {
                if (empty($recordids[$record->table])) {
                    $recordids[$record->table] = array();
                }
                if (is_scalar($ids)) {
                    $recordids[$record->table][$ids] = true;
                } else {
                    foreach ($ids as $id) {
                        $recordids[$record->table][$id] = true;
                    }
                }
            }
        }
    }

    /**
     * get_record_ids
     *
     * @param array $tableinfo two dimensional array of tables and columns which may be accessed (passed by reference)
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
                throw new \moodle_exception('idparametersmissing', $this->tool, '', $table);
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
            $ids[] = $this->get_record_id($table, $fieldset);
        }

        if (count($ids) == 1) {
            return reset($ids);
        } else {
            return $ids;
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
