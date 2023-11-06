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

namespace vocabtool_phpdocs;

defined('MOODLE_INTERNAL') || die();

/**
 * tool
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {
    const PLUGINNAME = 'phpdocs';

    protected $paramnames = null;

    /**
     * __construct
     *
     * @todo Finish documenting this function
     */
    public function __construct() {
        parent::__construct();
        $this->paramnames = array(
            '$a'         => (object)array('type' => 'array', 'text' => 'additional value or values required for the language string'),
            '$action'    => (object)array('type' => 'integer', 'text' => ''),
            '$args'      => (object)array('type' => 'array',   'text' => ''),
            '$attempt'   => (object)array('type' => 'object', 'text' => 'a record form the "vocab_game_attempt" table in the Moodle database'),
            '$attributes'=> (object)array('type' => 'array', 'text' => ''),
            '$blockname' => (object)array('type' => 'string', 'text' => ''),
            '$c'         => (object)array('type' => 'integer', 'text' => 'a column number'),
            '$cancel'    => (object)array('type' => 'boolean', 'text' => ''),
            '$capability'=> (object)array('type' => 'string', 'text' => ''),
            '$cellheader' => (object)array('type' => 'boolean', 'text' => 'if TRUE, the 1st cell should be made a TH cell'),
            '$cm'        => (object)array('type' => 'string', 'text' => 'the course module object for the the current vocabulary activity'),
            '$cmax'      => (object)array('type' => 'integer', 'text' => 'the maximum column number'),
            '$cmin'      => (object)array('type' => 'integer', 'text' => 'the minimum column number'),
            '$colors'    => (object)array('type' => 'array', 'text' => ''),
            '$component' => (object)array('type' => 'string', 'text' => 'the frakenstyle name of a Moodle plugin'),
            '$config'    => (object)array('type' => 'object', 'text' => ''),
            '$contents'  => (object)array('type' => 'string',  'text' => ''),
            '$context'   => (object)array('type' => 'object', 'text' => 'a record form the "context" table in the Moodle database'),
            '$count'     => (object)array('type' => 'integer', 'text' => ''),
            '$course'    => (object)array('type' => 'object', 'text' => 'a record form the "course" table in the Moodle database'),
            '$customdata'=> (object)array('type' => 'array', 'text' => ''),
            '$d'         => (object)array('type' => 'string', 'text' => 'a definition string for a <path> tag'),
            '$data'      => (object)array('type' => 'stdClass', 'text' => 'submitted from the form'),
            '$datafilename' => (object)array('type' => 'string', 'text' => ''),
            '$dbman'     => (object)array('type' => 'object', 'text' => 'the Moodle database manager'),
            '$default'   => (object)array('type' => 'mixed', 'text' => ''),
            '$defaultmax'=> (object)array('type' => 'integer', 'text' => ''),
            '$defaultmin'=> (object)array('type' => 'integer', 'text' => ''),
            '$details'   => (object)array('type' => 'string', 'text' => ''),
            '$dryrun'    => (object)array('type' => 'boolean', 'text' => ''),
            '$editable'  => (object)array('type' => 'boolean', 'text' => ''),
            '$endcolor'  => (object)array('type' => 'string', 'text' => "an RGB color, e.g. '#aabbcc'"),
            '$escaped'   => (object)array('type' => 'boolean', 'text' => ''),
            '$excluded_fieldnames' => (object)array('type' => 'array', 'text' => ''),
            '$expanded'  => (object)array('type' => 'boolean', 'text' => 'if TRUE, the heading will be expanded; otherwise it will remain collapsed'),
            '$feature'   => (object)array('type' => 'string', 'text' => 'a FEATURE_xxx constant e.g. mod_intro'),
            '$fieldname' => (object)array('type' => 'string', 'text' => ''),
            '$fields'    => (object)array('type' => 'array', 'text' => 'of database field names'),
            '$fileid'    => (object)array('type' => 'integer', 'text' => ''),
            '$filename'  => (object)array('type' => 'string',  'text' => ''),
            '$filepath'  => (object)array('type' => 'string',  'text' => ''),
            '$files'     => (object)array('type' => 'array', 'text' => ''),
            '$filetype'  => (object)array('type' => 'string', 'text' => 'file type/extension'),
            '$fillcolor' => (object)array('type' => 'string', 'text' => 'a CSS color name or value'),
            '$format'    => (object)array('type' => 'object', 'text' => ''),
            '$formatfilecontent' => (object)array('type' => 'string', 'text' => ''),
            '$fs'        => (object)array('type' => 'object', 'text' => 'reference to Moodle file storage singleton object'),
            '$game'      => (object)array('type' => 'object', 'text' => 'a vocab_game object'),
            '$gamecolor' => (object)array('type' => 'string', 'text' => 'the game button color as an RGB color'),
            '$groupid'   => (object)array('type' => 'integer', 'text' => ''),
            '$height'    => (object)array('type' => 'integer', 'text' => 'required height (in pixels)'),
            '$id'        => (object)array('type' => 'integer', 'text' => 'settings to be used in the <path> tag e.g. "stroke", "stroke-width"'),
            '$incorrect' => (object)array('type' => 'boolean', 'text' => ''),
            '$indent'    => (object)array('type' => 'string', 'text' => ''),
            '$instance'  => (object)array('type' => 'object', 'text' => 'a record form the "vocab" table in the Moodle database'),
            '$item'      => (object)array('type' => 'object', 'text' => 'representing an item in the XML file'),
            '$itemvars'  => (object)array('type' => 'array',   'text' => ''),
            '$langcode'  => (object)array('type' => 'string', 'text' => ''),
            '$length'    => (object)array('type' => 'integer', 'text' => ''),
            '$method'    => (object)array('type' => 'string', 'text' => ''),
            '$mform'     => (object)array('type' => 'moodleform', 'text' => 'representing the Moodle form'),
            '$missing'   => (object)array('type' => 'boolean', 'text' => ''),
            '$mode'      => (object)array('type' => 'string', 'text' => ''),
            '$n'         => (object)array('type' => 'integer', 'text' => 'number of colors to return'),
            '$name'      => (object)array('type' => 'string',  'text' => ''),
            '$names'     => (object)array('type' => 'array', 'text' => ''),
            '$newwords'  => (object)array('type' => 'array', 'text' => ''),
            '$node'      => (object)array('type' => 'string', 'text' => 'the current navigation node'),
            '$offsetx'   => (object)array('type' => 'integer', 'text' => 'the "x" offset (in pixels) to the left hand edge of the pie chart'),
            '$offsety'   => (object)array('type' => 'integer', 'text' => 'the "y" offset (in pixels) to the top edge of the pie chart'),
            '$oldversion'=> (object)array('type' => 'string', 'text' => ''),
            '$parameters'=> (object)array('type' => 'array', 'text' => ''),
            '$paramname' => (object)array('type' => 'string', 'text' => ''),
            '$params'    => (object)array('type' => 'array',   'text' => ''),
            '$phpdocs'   => (object)array('type' => 'string', 'text' => ''),
            '$phpdocsnew'=> (object)array('type' => 'string', 'text' => ''),
            '$phpdocsold'=> (object)array('type' => 'string', 'text' => ''),
            '$plugin'    => (object)array('type' => 'string', 'text' => 'the frankenstyle name of the plugin. e.g. mod_vocab'),
            '$prefix'    => (object)array('type' => 'string', 'text' => ''),
            '$r'         => (object)array('type' => 'integer', 'text' => 'a row number'),
            '$radius'    => (object)array('type' => 'integer', 'text' => 'radius of the pie-chart (in pixels)'),
            '$recordids' => (object)array('type' => 'array', 'text' => 'of ids from the database'),
            '$row'       => (object)array('type' => 'object', 'text' => ''),
            '$settings'  => (object)array('type' => 'object', 'text' => 'The "settings" navigation node for this Vocabulary activity'),
            '$sheet'     => (object)array('type' => 'object', 'text' => ''),
            '$start'     => (object)array('type' => 'integer', 'text' => ''),
            '$startcolor'=> (object)array('type' => 'string', 'text' => "an RGB color, e.g. '#ff6633'"),
            '$strname'   => (object)array('type' => 'string', 'text' => ''),
            '$strokecolor' => (object)array('type' => 'string', 'text' => 'a css color name or value'),
            '$strokewidth' => (object)array('type' => 'integer', 'text' => 'the width (in pixels)'),
            '$submit'    => (object)array('type' => 'boolean', 'text' => ''),
            '$table'     => (object)array('type' => 'string', 'text' => 'name of a table in the database'),
            '$tableinfo' => (object)array('type' => 'array', 'text' => 'two dimensional array of tables and columns which may be accessed'),
            '$tablename' => (object)array('type' => 'string', 'text' => ''),
            '$tablenames'=> (object)array('type' => 'array', 'text' => ''),
            '$target'    => (object)array('type' => 'string', 'text' => ''),
            '$text'      => (object)array('type' => 'string', 'text' => ''),
            '$textcolor' => (object)array('type' => 'string', 'text' => 'the text color as an RGB color'),
            '$txt'       => (object)array('type' => 'string', 'text' => ''),
            '$type'      => (object)array('type' => 'mixed', 'text' => 'a PARAM_xxx constant value'),
            '$update'    => (object)array('type' => 'boolean', 'text' => ''),
            '$user'      => (object)array('type' => 'object', 'text' => 'a user on this Moodle site'),
            '$value'     => (object)array('type' => 'string',  'text' => ''),
            '$values'    => (object)array('type' => 'array', 'text' => 'numbers to be displayed as a pie-chart'),
            '$vars'      => (object)array('type' => 'array', 'text' => 'of values for the current row'),
            '$vocab'     => (object)array('type' => 'object', 'text' => 'the current Vocabulary activity'),
            '$vocabnode' => (object)array('type' => 'string', 'text' => 'The navigation node for this Vocabulary activity'),
            '$width'     => (object)array('type' => 'integer', 'text' => 'required width (in pixels)'),
            '$word'      => (object)array('type' => 'object', 'text' => ''),
            '$words'     => (object)array('type' => 'array', 'text' => 'default value (optional)'),
            '$words'     => (object)array('type' => 'array', 'text' => 'of words'),
            '$words'     => (object)array('type' => 'array', 'text' => 'sitewide config settings for this plugin'),
            '$words'     => (object)array('type' => 'array', 'text' => 'strings colors expressed as RGB colors'),
            '$words'     => (object)array('type' => 'array', 'text' => "an RGB color, e.g. '#aabbcc'"),
            '$workbook'  => (object)array('type' => 'object', 'text' => ''),
            '$worksheet' => (object)array('type' => 'object', 'text' => 'representing a sheet from the data file'),
            '$xml'       => (object)array('type' => 'string', 'text' => ''),
            '$xmlroot'   => (object)array('type' => 'string', 'text' => '')
        );
    }

    /**
     * phpdocs
     *
     * @uses $CFG
     * @param moodleform $mform representing the Moodle form
     * @todo Finish documenting this function
     */
    public function phpdocs($mform) {
        global $CFG;
        $result = array();

        $data = $mform->get_data();

        // folderpath is something like "/mod/vocab".
        // filetypes is something like ["php", "js"].
        $folderpath = $data->folderpath;
        if ($filepath = $data->filepath) {
            $filepath = trim($filepath, ' ./');
            $filetypes = array();
        } else {
            $filetypes = $data->filetypes;
        }

        $caction = $data->copyrightaction;
        $paction = $data->phpdocsaction;

        // Initialize the $paths array with the path to the top folder.
        // These are relative directory paths (i.e. below $CFG->dirroot).
        $paths = array(trim($folderpath, '/'));

        $path = current($paths);
        while ($path) {

            // Get file/folder items within this directory.
            $items = new \DirectoryIterator($CFG->dirroot.'/'.$path);
            foreach ($items as $item) {

                // Skip certain directories.
                if ($item->isDot() || substr($item, 0, 1)=='.' || $item=='build' || $item=='lang' || $item=='pix') {
                    continue;
                }

                // Skip files that are not one of the target $filetypes.
                if ($skip = $item->isFile()) {
                    if ($filepath) {
                        if ("/$path/$item" == "$folderpath/$filepath") {
                            $skip = false;
                        }
                    } else {
                        foreach ($filetypes as $filetype) {
                            $strlen = strlen($filetype) + 1;
                            if (substr($item, -$strlen) == ".$filetype") {
                                $skip = false;
                            }
                        }
                    }
                    if ($skip) {
                        continue;
                    }
                }

                $filepath = $path.'/'.$item;

                if ($item->isDir()) {
                    $paths[] = $filepath;
                } else if ($item->isFile()) {

                    $fullpath = $CFG->dirroot.'/'.$filepath;
                    $filetype = pathinfo($path, PATHINFO_EXTENSION);

                    $update = false;
                    if ($contents = @file_get_contents($fullpath)) {
                        if ($report = $this->copyright_action($mform, $data, $filepath, $caction, $contents, $update)) {
                            foreach ($report as $f => $r) {
                                if (count($r)) {
                                    if (empty($result[$f])) {
                                        $result[$f] = array($r);
                                    } else {
                                        $result[$f][] = $r;
                                    }
                                }
                            }
                        }
                        if ($report = $this->phpdocs_action($mform, $data, $filepath, $paction, $contents, $update)) {
                            foreach ($report as $f => $r) {
                                if (count($r)) {
                                    if (empty($result[$f])) {
                                        $result[$f] = $r;
                                    } else {
                                        $result[$f] = array_merge($result[$f], $r);
                                    }
                                }
                            }
                        }
                    }

                    if ($update) {
                        echo "Write new content to $filepath<br>";
                        file_put_contents($fullpath, $contents);
                    }
                }
            }
            $path = next($paths);
        }

        if (empty($result)) {
            echo 'No items were updated.';
        } else {
            echo 'Updated items.';
        }
    }

    /**
     * get_report_remove_fix
     *
     * @param integer $action
     * @param boolean $missing
     * @param boolean $incorrect
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_report_remove_fix($action, $missing, $incorrect, $mform) {

        $fix = false;
        $remove = false;
        $report = false;

        switch ($action) {
            case $mform::ACTION_REPORT_ALL: // 1
                $report = ($missing || $incorrect);
                break;

            case $mform::ACTION_REPORT_MISSING: // 2
                $report = $missing ;
                break;

            case $mform::ACTION_REPORT_INCORRECT: // 3
                $report = $incorrect ;
                break;

            case $mform::ACTION_FIX_ALL: // 4
                $fix = ($missing || $incorrect);
                break;

            case $mform::ACTION_FIX_MISSING: // 5
                $fix = $missing ;
                break;

            case $mform::ACTION_FIX_INCORRECT: // 6
                $fix = $incorrect ;
                break;

            case $mform::ACTION_REMOVE_ALL: // 7
                $report = ($missing || $incorrect);
                break;
        }
        return array($report, $remove, $fix);
    }

    /**
     * copyright_action
     *
     * @param moodleform $mform representing the Moodle form
     * @param stdClass $data submitted from the form
     * @param string $filepath
     * @param integer $action
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function copyright_action($mform, $data, $filepath, $action, &$contents, &$update) {

        $search = '// This file '.'is part of Moodle - http://moodle.org/';
        if (is_numeric(strpos($contents, $search))) {
            $missing = false;
            $incorrect = false;
        } else {
            $missing = true;
            $incorrect = true;
        }

        list($report, $remove, $fix) = $this->get_report_remove_fix($action, $missing, $incorrect, $mform);

        $result = array($filepath => array());

        if ($report) {
            $result[$filepath][] = $this->report_copyright();
        } else {
            if ($remove) {
                $result[$filepath][] = $this->remove_coppyright($contents, $update);
            }
            if ($fix) {
                $result[$filepath][] = $this->add_copyright($contents, $update);
            }
        }

        return $result;
    }

    /**
     * report_copyright
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function report_copyright() {
        return get_string('copyrightmissing', 'vocabtool_phpdocs');
    }

    /**
     * remove_copyright
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function remove_copyright(&$contents, &$update) {
        $search = '// This file '.'is part of Moodle - http://moodle.org/';
        $search = '/\s*'.preg_quote($search, '/').'(.*?)\n+(?=[^\/])/us';
        $contents = preg_replace($search, "\n", $contents, 1, $count);
        if ($count) {
            $update = true;
            return get_string('copyrightremoved', 'vocabtool_phpdocs');
        }
        return '';
    }

    /**
     * add_copyright
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_copyright(&$contents, &$update) {
        $copyright = $this->get_copyright()."\n";
        if (substr($contents, 0, 5) === '<?php') {
            $pos = 6;
        } else if (substr($contents, 0, 2) === '<?') {
            $pos = 4;
        } else {
            $pos = 0;
        }
        $contents = substr_replace($contents, $copyright, $pos, 0);
        $update = true;
        return get_string('copyrightadded', 'vocabtool_phpdocs');
    }

    /**
     * phpdocs_action
     *
     * @param moodleform $mform representing the Moodle form
     * @param stdClass $data submitted from the form
     * @param string $filepath
     * @param integer $action
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @todo Finish documenting this function
     */
    public function phpdocs_action($mform, $data, $filepath, $action, &$contents, &$update) {

        switch (substr($filepath, strrpos($filepath, '.'))) {
            case '.js':
                // fix js functions and methods
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 1);
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 2);
                break;

            case '.php':
                // fix php functions/methods and classes
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 3);
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 4);
                break;
        }
        
    }

    /**
     * fix_file_contents
     *
     * @uses $CFG
     * @param moodleform $mform representing the Moodle form
     * @param stdClass $data submitted from the form
     * @param string $filepath
     * @param integer $action
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @param xxx $type
     * @return xxx
     * @todo Finish documenting this function
     */
    public function fix_file_contents($mform, $data, $filepath, $action, &$contents, &$update, $type) {
        global $CFG;

        // Class
        // (^ *)(abstract|readonly +)?(class +)(\w+ *)(extends|implements +\?\w+)*\{

        // Constant
        // (^ *)(const +)(\w+ *)(= *)(.*?);

        // Property
        // (^ *)(abstract|readonly|public|protected|private|static|var +)|($\w+ *)(= *)(.*?);

        // Function/Method
        // (^ *)(abstract|public|protected|private|static +)?(function +)?($\w+ *)\{

        $lastline   = '((?:^|\{|\}|;|-|,)[ \t]*(?:\/\/[^\n\r]*)?[\n\r]*)';
        $comments   = '((?:[ \t]*\/\/[^\n\r]*[\n\r]+)*)';
        $phpdocs    = '([ \t]*\/\*+[\r\n]+(?:[ \t]*\*[^\r\n]*[\r\n]+)*[ \t]*\*+\/[\r\n]+)?';
        $indent     = '([ \t]*)';
        $parameters = '([^\n\r{]*)'; // includes function return type ( *:  *\w+)?

        switch ($type) {
            case 1:
                // javascript functions
                // e.g. function FooBar(x, y, z) {
                $keywords = '(function[ \t]+)';
                $blockname = '(\w+)[ \t]*';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 2:
                // javascript methods
                // e.g. this.FooBar = function (x, y, z) {
                // e.g. FooBar: function(x, y, z) {
                $blockname = '(\w+(?:\.\w+)*[ \t]*)';
                $keywords = '([=:][ \t]*function[ \t]*)';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$blockname.$keywords.$parameters.'\{/s';
                break;

            case 3:
                // php functions/methods
                // e.g. static public function FooBar($x, $y=0, $z="z") {
                $keywords = '((?:(?:abstract|public|private|protected|static)[ \t]+)*function[ \t]+)';
                $blockname = '(\w+)[ \t]*';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 4:
                // php classes
                // e.g. abstract class FooBar extends Foo {
                // e.g. class tool extends \mod_vocab\toolbase
                $keywords = '((?:(?:abstract|readonly)[ \t]+)?(?:class|interface)[ \t]+)';
                $blockname = '(\w+[ \t]*)';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 5:
                // PHP class constants
                //
                // e.g const CONST_NAME = CONST_VALUE (e.g. 99 'string' "string" true false null);
                break;

            case 6:
                // PHP class variables
                //
                // e.g. (protected|public|private|static|var) $VAR_NAME = DEFAULT_VALUE (e.g. 99 'string' "string" array() true false null);
                break;

            default: return; // shouldn't happen !!
        }
        unset($lastline, $comments, $indent, $parameters, $keywords, $blockname);

        // [0][$i][0] : the whole match (i.e. all of the following)
        // [1][$i][0] : last line of previous code block, if any
        // [2][$i][0] : single line comments, if any, preceeding PHPDocs
        // [3][$i][0] : pre-existing PHPDocs, if any
        // [4][$i][0] : indent (excluding newlines)
        // [5][$i][0] : PHP/javascript keywords
        // [6][$i][0] : code block name
        // [7][$i][0] : code block parameters (including parentheses)
        // Note: if $type is 2, then [5] and [6] switch position

        // locate all occurrences of this block $type
        if (! preg_match_all($search, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        // Cache the filename.
        $filename = basename($filepath);

        $i_max = count($matches[0]) - 1;
        for ($i = $i_max; $i >= 0; $i--) {

            $length = strlen($matches[0][$i][0]);
            $start = $matches[0][$i][1];

            // Tidy up last line of code block before this function,
            // as well as any comments between the previous code block
            // and (the PHPDocs of) the current code block.
            $spacer = '';
            if ($lastline = rtrim($matches[1][$i][0])) {
                $lastline .= "\n";
                $spacer = "\n";
            }
            if ($comments = rtrim($matches[2][$i][0])) {
                $comments .= "\n";
                $spacer = "\n";
            }

            $phpdocsold = $matches[3][$i][0];
            $indent = $matches[4][$i][0];

            switch ($type) {
                case 1: $blockname = $matches[6][$i][0]; break; // js functions
                case 2: $blockname = $matches[5][$i][0]; break; // js methods
                case 3: $blockname = $matches[6][$i][0]; break; // php functions/methods
                case 4: $blockname = $matches[6][$i][0]; break; // php classes
                default: return false; // shouldn't happen !!
            }

            $parameters = trim($matches[7][$i][0]);
            if (substr($parameters, 0 ,1) == '(' && substr($parameters, -1) == ')') {
                $phpdocsnew = $this->get_phpdocs_parameters($contents, $start, $indent, $blockname, $parameters);
            } else {
                // A "class" in in a PHP file.
                $phpdocsnew = $this->get_phpdocs_block($data, $indent, $blockname);
            }
            $missing = false;
            $incorrect = false;
            if ($phpdocsold == '') {
                $missing = true;
            } else {
                $incorrect = $this->different_params($blockname, $phpdocsold, $phpdocsnew);
            }
            list($report, $remove, $fix) = $this->get_report_remove_fix($action, $missing, $incorrect, $mform);

            $msg = '';
            if ($remove) {
                $match = ''
                    .$lastline
                    .$comments
                    .$spacer
                    .$indent.$matches[5][$i][0].$matches[6][$i][0].$matches[7][$i][0].'{'
                ;
                $contents = substr_replace($contents, $match, $start, $length);
                $msg = 'phpdocsremoved';
                $update = true;
            } else {
                if ($report) {
                    if ($missing) {
                        $msg = 'missingphpdocs';
                    } else if ($incorrect) {
                        $msg = 'incorrectphpdocs';
                    }
                } else if ($fix) {
                    $match = ''
                        .$lastline
                        .$comments
                        .$spacer
                        .$phpdocsnew
                        .$indent.$matches[5][$i][0].$matches[6][$i][0].$matches[7][$i][0].'{'
                    ;
                    $contents = substr_replace($contents, $match, $start, $length);

                    if ($missing) {
                        $msg = 'phpdocsadded';
                    } else if ($incorrect) {
                        $msg = 'phpdocsfixed';
                    }
                    $update = true;
                }
            }
            if ($msg) {
                $a = (object)array(
                    'filepath' => $filepath,
                    'functionname' => trim($matches[6][$i][0])
                );
                $msg = get_string($msg, $this->plugin, $a);
                echo \html_writer::tag('p', $msg, array('class' => 'my-0'));
            }
        }
    }

    /**
     * parse_phpdocs
     *
     * @param string $blockname
     * @param string $phpdocs
     * @return object to represent these $phpdocs
     * @todo Finish documenting this function
     */
    public function parse_phpdocs($blockname, $phpdocs) {

        $parse = (object)array(
            'comments' => array(),
            'tags' => array()
        );

        $line =  '[ \t]*\*[^\r\n]*[\r\n]+';
        $block = '[ \t]*\/\*+[\r\n]+'.
                 "((?:$line)*)".
                 '[ \t]*\*+\/[\r\n]+?';
        $tag = '\*\s+@(\w+)\s+(.*)';

        // https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/
        // @uses $VARNAME [<description>]
        // @param [<Type>] [name] [<description>]
        // @return [Type] [<description>]
        // @todo [description]

        if (preg_match('/^'.$block.'$/us', $phpdocs, $blockmatch)) {
            if (preg_match_all('/'.$line.'/us', $blockmatch[1], $lines)) {

                $i_max = count($lines[0]);
                for ($i=0; $i<$i_max; $i++) {

                    $line = trim($lines[0][$i]);
                    if (preg_match('/'.$tag.'/u', $line, $match)) {

                        $token = $match[1];
                        $text = $match[2];
                        $type = '';
                        $name = '';
                        switch ($token) {
                            case 'param':
                                list($type, $name, $text) = array_pad(explode(' ', $text, 3), 3, '');
                                break;
                            case 'return':
                                list($type, $text) = array_pad(explode(' ', $text, 2), 2, '');
                                break;
                            case 'uses':
                                list($name, $text) = array_pad(explode(' ', $text, 2), 2, '');
                                break;
                            default:
                        }

                        // Fix for missing $type, such as:
                        // * @param $mform
                        if (substr($type, 0, 1) === '$') {
                            $text = $name;
                            $name = $type;
                            $type = '';
                        }

                        // Create object to represent this param.
                        $t = (object)array(
                            'type' => $type,
                            'name' => $name,
                            'text' => $text
                        );

                        // Initialize array for this type of $token.
                        if (empty($parse->tags[$token])) {
                            $parse->tags[$token] = array();
                        }

                        // Add this $token (params require a $name)
                        if ($token == 'param') {
                            if ($name == '') {
                                $name = 'param_'.count($parse->tags[$token]);
                            }
                            $parse->tags[$token][$name] = $t;
                        } else {
                            $parse->tags[$token][] = $t;
                        }
                    } else if ($line = ltrim($line, '* ')) {
                        $parse->comments[] = $line;
                    }
                }
            }
        }
        return $parse;
    }

    /**
     * Detect if params in the the "old" (=current) phpdocs
     * are different from those in the "new" (=proposed) phpdocs
     *
     * @param string $blockname name of this function or code block
     * @param string $phpdocsold the old PHPDocs
     * @param string $phpdocsnew the expected PHPDocs
     * @return boolean TRUE if params are different, FALSE if they are the same.
     */
    public function different_params($blockname, $phpdocsold, $phpdocsnew) {

        $parseold = $this->parse_phpdocs($blockname, $phpdocsold);
        $parsenew = $this->parse_phpdocs($blockname, $phpdocsnew);

        if (array_key_exists('param', $parseold->tags)) {
            $paramsold = array_keys($parseold->tags['param']);
            sort($paramsold);
        } else {
            $paramsold = array();
        }

        if (array_key_exists('param', $parsenew->tags)) {
            $paramsnew = array_keys($parsenew->tags['param']);
            sort($paramsnew);
        } else {
            $paramsnew = array();
        }

        return ($paramsold !== $paramsnew);
    }

    /**
     * get_phpdocs_file
     *
     * @param stdClass $data submitted from the form
     * @param xxx $indent
     * @param string $filename
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_phpdocs_file($data, $indent, $filename) {
        return $this->get_phpdocs_block($data, $indent, $filename);
    }

    /**
     * get_phpdocs_block
     *
     * @param stdClass $data submitted from the form
     * @param xxx $indent
     * @param xxx $blockname
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_phpdocs_block($data, $indent, $blockname) {
        $details = <<<END
$indent * @package    $data->package
$indent * @copyright  $data->startyear $data->authorname
$indent * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
$indent * @author     $data->authorname $data->authorcontact
$indent * @since      Moodle $data->sinceversion
END;
        return $this->get_phpdoc($indent, $blockname, $details."\n");
    }

    /**
     * get_phpdoc
     *
     * @param xxx $indent
     * @param xxx $blockname
     * @param xxx $details
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_phpdoc($indent, $blockname, $details) {
        return ''
            .$indent.'/'.'**'."\n"
            .$this->get_name_phpdoc($blockname, $indent)
            .$this->get_details_phpdoc($details, $indent)
            .$indent.' *'.'/'."\n"
        ;
    }

    /**
     * get_name_phpdoc
     *
     * @param xxx $blockname
     * @param xxx $indent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_name_phpdoc($blockname, $indent) {
        $name = trim($blockname);
        if ($pos = strrpos($name, '.')) {
            $name = substr($name, $pos + 1);
        }
        return "$indent * $name\n";
    }

    /**
     * get_details_phpdoc
     *
     * @param xxx $details
     * @param xxx $indent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_details_phpdoc($details, $indent) {
        if ($details) {
            $details = "$indent *\n".$details;
        }
        return $details;
    }

    /**
     * get_phpdocs_parameters
     *
     * @param string $contents
     * @param xxx $start
     * @param xxx $indent
     * @param xxx $blockname
     * @param xxx $parameters
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_phpdocs_parameters($contents, $start, $indent, $blockname, $parameters) {

        $details = '';
        $search = '/'.'(\w*)(\&?)(\$\w+)(\s*=\s*([^,]*))?'.'/';
        // [0][$i] : type + reference + name + default value
        // [1][$i] : parameter type (optional)
        // [2][$i] : reference (optional leading "&")
        // [3][$i] : parameter name (with leading "$")
        // [4][$i] : default value expression (i.e. "=" + default $value)
        // [5][$i] : default value
        if (preg_match_all($search, trim($parameters, ' ()'), $matches)) {
            $i_max = count($matches[0]);
            for ($i=0; $i<$i_max; $i++) {
                $name = $matches[3][$i];

                if (array_key_exists($name, $this->paramnames)) {
                    $type = $this->paramnames[$name]->type;
                    $text = $this->paramnames[$name]->text;
                } else {
                    $type = ($matches[1] ? $matches[1] : 'xxx');
                    $text = '';
                    $this->paramnames[$name] = (object)array('type' => $type, 'text' => '');
                    echo "Unknown PARAM name: $name (type=$type)<br>";
                }

                $details .= rtrim("$indent * @param $type $name $text");
                if ($matches[2][$i]) {
                    $details .= " (passed by reference)";
                }
                if ($matches[4][$i]) {
                    $default = $matches[5][$i];
                    $details .= " (optional, default=$default)";
                }
                $details .= "\n";
            }
        }

        // get $pos(ition) of end of function
        if ($pos = strpos($contents, "\n$indent}", $start)) {
            $substr = substr($contents, $start, $pos - $start);
            if (preg_match_all('/(?<=global )\$[^;]*(?=;)/', $substr, $matches)) {
                $globals = array();
                foreach ($matches[0] as $match) {
                    $match = explode(',', $match);
                    $match = array_map('trim', $match);
                    $match = array_filter($match);
                    $globals = array_merge($globals, $match);
                }
                $globals = array_unique($globals);
                rsort($globals);
                foreach ($globals as $global) {
                    $details = "$indent * @uses $global\n".$details;
                }
            }
            if (preg_match('/\s'.'return'.'\s/s', $substr)) {
                $details .= "$indent * @return xxx\n";
            }
        }

        $details .= "$indent * @todo Finish documenting this function\n";

        return $this->get_phpdoc($indent, $blockname, $details);
    }

    /**
     * remove_phpdocs
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function remove_phpdocs(&$contents, &$update) {
        $search = '/'.
                  '(^ *\/\*\*[\r\n]+)'. // top line
                  '(^ *\*.*?[\r\n]+)+'. // mid line
                  '(^ *\*\/[\r\n]+)'. // bottom line
                  '/um'; // unicode, multiline
        $contents = preg_replace($search, '', $contents, -1, $count);
        return array(($count > 0), $contents);
    }

    /**
     * get_copyright
     *
     * @param string $filetype file type/extension (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_copyright($filetype='') {
        $header = '';
        $footer = '';
        switch ($filetype) {
            case 'css':
                $header = '/*'.str_repeat('*', 10)."\n";
                $footer = str_repeat('*', 10).'*/'."\n";
            case 'xml':
                $header = '<!'.str_repeat('-', 10)."\n";
                $footer = str_repeat('-', 10).'>'."\n";
                break;
        }

        // Single-quoted 'END' is termintaing label a "nowdoc" block.
        // Unlike "heredoc", a "nowdoc" block has no variable expansion.
        $copyright = <<<'END'
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
END;
        return $header.$copyright."\n".$footer;
    }
}
