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

/**
 * tool
 *
 * @package    vocabtool_phpdocs
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class tool extends \mod_vocab\toolbase {

    /** @var string holds the name of this plugin */
    const SUBPLUGINNAME = 'phpdocs';

    /** @var array maps known parameter names to their type and description */
    protected $paramnames = null;

    /** @var array stores errors and warnings of each type in each file */
    protected $messages = [];

    /**
     * __construct
     *
     * 
     * TODO: Finish documenting this function
     */
    public function __construct() {
        parent::__construct();

        // ToDo: put this list into a file that can be added in main form for this plugin.
        $this->paramnames = [
            '$a' => (object)['type' => 'array', 'text' => 'additional value or values required for the language string'],
            '$action' => (object)['type' => 'integer', 'text' => ''],
            '$args' => (object)['type' => 'array',   'text' => ''],
            '$attempt' => (object)[
                'type' => 'object', 'text' => 'a record form the "vocab_game_attempt" table in the Moodle database'],
            '$attributes' => (object)['type' => 'array', 'text' => ''],
            '$blockname' => (object)['type' => 'string', 'text' => ''],
            '$c' => (object)['type' => 'integer', 'text' => 'a column number'],
            '$cancel' => (object)['type' => 'boolean', 'text' => ''],
            '$capability' => (object)['type' => 'string', 'text' => ''],
            '$cellheader' => (object)['type' => 'boolean', 'text' => 'if TRUE, the 1st cell should be made a TH cell'],
            '$cm' => (object)['type' => 'string', 'text' => 'the course module object for the the current vocabulary activity'],
            '$cmax' => (object)['type' => 'integer', 'text' => 'the maximum column number'],
            '$cmin' => (object)['type' => 'integer', 'text' => 'the minimum column number'],
            '$colors' => (object)['type' => 'array', 'text' => 'string colors expressed as RGB colors'],
            '$component' => (object)['type' => 'string', 'text' => 'the frakenstyle name of a Moodle plugin'],
            '$config' => (object)['type' => 'object', 'text' => ''],
            '$contents' => (object)['type' => 'string',  'text' => ''],
            '$context' => (object)['type' => 'object', 'text' => 'a record form the "context" table in the Moodle database'],
            '$count' => (object)['type' => 'integer', 'text' => ''],
            '$course' => (object)['type' => 'object', 'text' => 'a record form the "course" table in the Moodle database'],
            '$customdata' => (object)['type' => 'array', 'text' => ''],
            '$d' => (object)['type' => 'string', 'text' => 'a definition string for a <path> tag'],
            '$data' => (object)['type' => 'stdClass', 'text' => 'submitted from the form'],
            '$datafilename' => (object)['type' => 'string', 'text' => ''],
            '$dbman' => (object)['type' => 'object', 'text' => 'the Moodle database manager'],
            '$default' => (object)['type' => 'mixed', 'text' => ''],
            '$defaultmax' => (object)['type' => 'integer', 'text' => ''],
            '$defaultmin' => (object)['type' => 'integer', 'text' => ''],
            '$delimiter' => (object)['type' => 'string', 'text' => ''],
            '$details' => (object)['type' => 'string', 'text' => ''],
            '$dryrun' => (object)['type' => 'boolean', 'text' => ''],
            '$editable' => (object)['type' => 'boolean', 'text' => ''],
            '$endcolor' => (object)['type' => 'string', 'text' => "an RGB color, e.g. '#aabbcc'"],
            '$escaped' => (object)['type' => 'boolean', 'text' => ''],
            '$excluded_fieldnames' => (object)['type' => 'array', 'text' => ''],
            '$expanded' => (object)[
                'type' => 'boolean', 'text' => 'if TRUE, the heading will be expanded; otherwise it will remain collapsed'],
            '$feature' => (object)['type' => 'string', 'text' => 'a FEATURE_xxx constant e.g. mod_intro'],
            '$fieldname' => (object)['type' => 'string', 'text' => ''],
            '$fields' => (object)['type' => 'array', 'text' => 'of database field names'],
            '$fileid' => (object)['type' => 'integer', 'text' => ''],
            '$filename' => (object)['type' => 'string',  'text' => ''],
            '$filepath' => (object)['type' => 'string',  'text' => ''],
            '$files' => (object)['type' => 'array', 'text' => ''],
            '$filetype' => (object)['type' => 'string', 'text' => 'file type/extension'],
            '$fillcolor' => (object)['type' => 'string', 'text' => 'a CSS color name or value'],
            '$format' => (object)['type' => 'object', 'text' => ''],
            '$formatfilecontent' => (object)['type' => 'string', 'text' => ''],
            '$fs' => (object)['type' => 'object', 'text' => 'reference to Moodle file storage singleton object'],
            '$game' => (object)['type' => 'object', 'text' => 'a vocab_game object'],
            '$gamecolor' => (object)['type' => 'string', 'text' => 'the game button color as an RGB color'],
            '$groupid' => (object)['type' => 'integer', 'text' => ''],
            '$height' => (object)['type' => 'integer', 'text' => 'required height (in pixels)'],
            '$id' => (object)[
                'type' => 'integer', 'text' => 'settings to be used in the <path> tag e.g. "stroke", "stroke-width"'],
            '$incorrect' => (object)['type' => 'boolean', 'text' => ''],
            '$indent' => (object)['type' => 'string', 'text' => ''],
            '$instance' => (object)[
                'type' => 'object', 'text' => 'a record form the "vocab" table in the Moodle database'],
            '$item' => (object)['type' => 'object', 'text' => 'representing an item in the XML file'],
            '$itemvars' => (object)['type' => 'array',   'text' => ''],
            '$langcode' => (object)['type' => 'string', 'text' => ''],
            '$length' => (object)['type' => 'integer', 'text' => ''],
            '$method' => (object)['type' => 'string', 'text' => ''],
            '$mform' => (object)['type' => 'moodleform', 'text' => 'representing the Moodle form'],
            '$missing' => (object)['type' => 'boolean', 'text' => ''],
            '$mode' => (object)['type' => 'string', 'text' => ''],
            '$n' => (object)['type' => 'integer', 'text' => 'number of colors to return'],
            '$name' => (object)['type' => 'string',  'text' => ''],
            '$names' => (object)['type' => 'array', 'text' => ''],
            '$newwords' => (object)['type' => 'array', 'text' => ''],
            '$node' => (object)['type' => 'string', 'text' => 'the current navigation node'],
            '$offsetx' => (object)[
                'type' => 'integer', 'text' => 'the "x" offset (in pixels) to the left hand edge of the pie chart'],
            '$offsety' => (object)[
                'type' => 'integer', 'text' => 'the "y" offset (in pixels) to the top edge of the pie chart'],
            '$oldversion' => (object)['type' => 'string', 'text' => ''],
            '$parameters' => (object)['type' => 'array', 'text' => ''],
            '$paramname' => (object)['type' => 'string', 'text' => ''],
            '$params' => (object)['type' => 'array',   'text' => ''],
            '$phpdocs' => (object)['type' => 'string', 'text' => ''],
            '$phpdocsnew' => (object)['type' => 'string', 'text' => ''],
            '$phpdocsold' => (object)['type' => 'string', 'text' => ''],
            '$plugin' => (object)[
                'type' => 'string', 'text' => 'the frankenstyle name of the plugin. e.g. mod_vocab'],
            '$prefix' => (object)['type' => 'string', 'text' => ''],
            '$r' => (object)['type' => 'integer', 'text' => 'a row number'],
            '$radius' => (object)['type' => 'integer', 'text' => 'radius of the pie-chart (in pixels)'],
            '$recordids' => (object)['type' => 'array', 'text' => 'of ids from the database'],
            '$rectparams' => (object)['type' => 'array', 'text' => ''],
            '$row' => (object)['type' => 'object', 'text' => ''],
            '$settings' => (object)[
                'type' => 'object', 'text' => 'The "settings" navigation node for this Vocabulary activity'],
            '$sheet' => (object)['type' => 'object', 'text' => ''],
            '$size' => (object)['type' => 'integer', 'text' => ''],
            '$start' => (object)['type' => 'integer', 'text' => ''],
            '$startcolor' => (object)['type' => 'string', 'text' => "an RGB color, e.g. '#ff6633'"],
            '$strname' => (object)['type' => 'string', 'text' => ''],
            '$strokecolor' => (object)['type' => 'string', 'text' => 'a css color name or value'],
            '$strokewidth' => (object)['type' => 'integer', 'text' => 'the width (in pixels)'],
            '$submit' => (object)['type' => 'boolean', 'text' => ''],
            '$table' => (object)['type' => 'string', 'text' => 'name of a table in the database'],
            '$tableinfo' => (object)[
                'type' => 'array', 'text' => 'two dimensional array of tables and columns which may be accessed'],
            '$tablename' => (object)['type' => 'string', 'text' => ''],
            '$tablenames' => (object)['type' => 'array', 'text' => ''],
            '$target' => (object)['type' => 'string', 'text' => ''],
            '$text' => (object)['type' => 'string', 'text' => ''],
            '$textcolor' => (object)['type' => 'string', 'text' => 'the text color as an RGB color'],
            '$textparams' => (object)['type' => 'array', 'text' => ''],
            '$texts' => (object)['type' => 'array', 'text' => ''],
            '$txt' => (object)['type' => 'string', 'text' => ''],
            '$type' => (object)['type' => 'mixed', 'text' => 'a PARAM_xxx constant value'],
            '$update' => (object)['type' => 'boolean', 'text' => ''],
            '$user' => (object)['type' => 'object', 'text' => 'a user on this Moodle site'],
            '$value' => (object)['type' => 'string',  'text' => ''],
            '$values' => (object)['type' => 'array', 'text' => 'numbers to be displayed as a pie-chart'],
            '$vars' => (object)['type' => 'array', 'text' => 'of values for the current row'],
            '$vocab' => (object)['type' => 'object', 'text' => 'the current Vocabulary activity'],
            '$vocabnode' => (object)['type' => 'string', 'text' => 'The navigation node for this Vocabulary activity'],
            '$width' => (object)['type' => 'integer', 'text' => 'required width (in pixels)'],
            '$words' => (object)['type' => 'array', 'text' => 'of words'],
            '$workbook' => (object)['type' => 'object', 'text' => ''],
            '$worksheet' => (object)['type' => 'object', 'text' => 'representing a sheet from the data file'],
            '$xml' => (object)['type' => 'string', 'text' => ''],
            '$xmlroot' => (object)['type' => 'string', 'text' => ''],
            '$xoffset' => (object)['type' => 'integer', 'text' => ''],
            '$xspace' => (object)['type' => 'integer', 'text' => ''],
            '$yoffset' => (object)['type' => 'integer', 'text' => ''],
            '$yspace' => (object)['type' => 'integer', 'text' => ''],
        ];
    }

    /**
     * phpdocs
     *
     * @uses $CFG
     * @param moodleform $mform representing the Moodle form
     * 
     * TODO: Finish documenting this function
     */
    public function phpdocs($mform) {
        global $CFG;
        $result = [];

        $data = $mform->get_data();

        // The folderpath is something like "/mod/vocab".
        // The filetypes are something like ["php", "js"].
        $folderpath = $data->folderpath;
        if ($filepath = $data->filepath) {
            $filepath = trim($filepath, ' ./');
            $filetypes = [];
        } else {
            $filetypes = $data->filetypes;
        }

        $caction = $data->copyrightaction;
        $paction = $data->phpdocsaction;

        // Initialize the $paths array with the path to the top folder.
        // These are relative directory paths (i.e. below $CFG->dirroot).
        $paths = [trim($folderpath, '/')];

        $path = current($paths);
        while ($path) {

            // Get file/folder items within this directory.
            $items = new \DirectoryIterator($CFG->dirroot.'/'.$path);
            foreach ($items as $item) {

                // Skip certain directories.
                if ($item->isDot() || substr($item, 0, 1) == '.' || $item == 'build' || $item == 'lang' || $item == 'pix') {
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

                $itempath = $path.'/'.$item;

                if ($item->isDir()) {
                    $paths[] = $itempath;
                } else if ($item->isFile()) {

                    $fullpath = $CFG->dirroot.'/'.$itempath;
                    $filetype = pathinfo($path, PATHINFO_EXTENSION);

                    $update = false;
                    if ($contents = @file_get_contents($fullpath)) {
                        if ($report = $this->copyright_action($mform, $data, $itempath, $caction, $contents, $update)) {
                            foreach ($report as $f => $r) {
                                if (count($r)) {
                                    if (empty($result[$f])) {
                                        $result[$f] = [$r];
                                    } else {
                                        $result[$f][] = $r;
                                    }
                                }
                            }
                        }
                        if ($report = $this->phpdocs_action($mform, $data, $itempath, $paction, $contents, $update)) {
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
                        echo "Write new content to $itempath<br>";
                        file_put_contents($fullpath, $contents);
                    }
                }
            }
            $path = next($paths);
        } // End while.

        $this->display_messages();
    }

    /**
     * get_report_remove_fix
     *
     * @param integer $action
     * @param boolean $missing
     * @param boolean $incorrect
     * @param moodleform $mform representing the Moodle form
     * @return xxx
     * 
     * TODO: Finish documenting this function
     */
    public function get_report_remove_fix($action, $missing, $incorrect, $mform) {

        $report = false;
        $remove = false;
        $fix = false;

        switch ($action) {
            case $mform::ACTION_REPORT_ALL: // 1
                $report = ($missing || $incorrect);
                break;

            case $mform::ACTION_REPORT_MISSING: // 2
                $report = $missing;
                break;

            case $mform::ACTION_REPORT_INCORRECT: // 3
                $report = $incorrect;
                break;

            case $mform::ACTION_FIX_ALL: // 4
                $fix = ($missing || $incorrect);
                break;

            case $mform::ACTION_FIX_MISSING: // 5
                $fix = $missing;
                break;

            case $mform::ACTION_FIX_INCORRECT: // 6
                $fix = $incorrect;
                break;

            case $mform::ACTION_REMOVE_ALL: // 7
                $remove = true;
                break;
        }
        return [$report, $remove, $fix];
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
     * 
     * TODO: Finish documenting this function
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

        $result = [$filepath => []];

        if ($report) {
            $this->store_copyright_message($filepath, 'missing');
        } else {
            if ($remove && $this->remove_copyright($contents, $update)) {
                $this->store_copyright_message($filepath, 'removed');
            }
            if ($fix && $this->add_copyright($contents, $update)) {
                $this->store_copyright_message($filepath, 'added');
            }
        }

        return $result;
    }

    /**
     * remove_copyright
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * 
     * TODO: Finish documenting this function
     */
    public function remove_copyright(&$contents, &$update) {
        $search = '// This file '.'is part of Moodle - http://moodle.org/';
        $search = '/\s*'.preg_quote($search, '/').'(.*?)\n+(?=[^\/])/us';
        $contents = preg_replace($search, "\n", $contents, 1, $count);
        if (empty($count)) {
            return false;
        }
        $update = true;
        return true;
    }

    /**
     * add_copyright
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * 
     * TODO: Finish documenting this function
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
        return true;
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
     * 
     * TODO: Finish documenting this function
     */
    public function phpdocs_action($mform, $data, $filepath, $action, &$contents, &$update) {

        switch (substr($filepath, strrpos($filepath, '.'))) {
            case '.js':
                // Fix js functions and methods.
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 1);
                $this->fix_file_contents($mform, $data, $filepath, $action, $contents, $update, 2);
                break;

            case '.php':
                // Fix php functions/methods and classes.
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
     * 
     * TODO: Finish documenting this function
     */
    public function fix_file_contents($mform, $data, $filepath, $action, &$contents, &$update, $type) {
        global $CFG;

        $lastline   = '((?:^|\{|\}|;|-|,)[ \t]*(?:\/\/[^\n\r]*)?[\n\r]*)';
        $comments   = '((?:[ \t]*\/\/[^\n\r]*[\n\r]+)*)';
        $phpdocs    = '([ \t]*\/\*+[\r\n]+(?:[ \t]*\*[^\r\n]*[\r\n]+)*[ \t]*\*+\/[\r\n]+)?';
        $indent     = '([ \t]*)';
        $parameters = '([^{]*)'; // Includes function return type ( *:  *\w+)? !!

        switch ($type) {
            case 1:
                // Handle Javascript functions.
                $keywords = '(function[ \t]+)';
                $blockname = '(\w+)[ \t]*';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 2:
                // Handle Javascript methods.
                $blockname = '(\w+(?:\.\w+)*[ \t]*)';
                $keywords = '([=:][ \t]*function[ \t]*)';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$blockname.$keywords.$parameters.'\{/s';
                break;

            case 3:
                // PHP functions and methods.
                $keywords = '((?:(?:abstract|public|private|protected|static)[ \t]+)*function[ \t]+)';
                $blockname = '(pie_gra\w+)[ \t]*';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 4:
                // PHP classes.
                $keywords = '((?:(?:abstract|readonly)[ \t]+)?(?:class|interface)[ \t]+)';
                $blockname = '(\w+[ \t]*)';
                $search = '/'.$lastline.$comments.$phpdocs.$indent.$keywords.$blockname.$parameters.'\{/s';
                break;

            case 5:
                // PHP class constants.
                break;

            case 6:
                // PHP class variables.
                break;

            default:
                return; // Shouldn't happen !!
        }
        unset($lastline, $comments, $indent, $parameters, $keywords, $blockname);

        // The matches should be be available as follows:
        // [0][$i][0] : the whole match (i.e. all of the following)
        // [1][$i][0] : last line of previous code block, if any
        // [2][$i][0] : single line comments, if any, preceeding PHPDocs
        // [3][$i][0] : pre-existing PHPDocs, if any
        // [4][$i][0] : indent (excluding newlines)
        // [5][$i][0] : PHP/javascript keywords
        // [6][$i][0] : code block name
        // [7][$i][0] : code block parameters (including parentheses)
        // Note: if $type is 2, then [5] and [6] switch position.

        // Locate all occurrences of this block $type.
        if (! preg_match_all($search, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        // Cache the filename.
        $filename = basename($filepath);

        $imax = count($matches[0]) - 1;
        for ($i = $imax; $i >= 0; $i--) {

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
                case 1: // JS functions.
                    $blockname = $matches[6][$i][0];
                    break;
                case 2: // JS methods.
                    $blockname = $matches[5][$i][0];
                    break;
                case 3: // PHP functions/methods.
                    $blockname = $matches[6][$i][0];
                    break;
                case 4: // PHP classes.
                    $blockname = $matches[6][$i][0];
                    break;
                default:
                    return false; // Shouldn't happen !!
            }

            // Parse the old PHPdocs so that we can extract the old comments and
            // check that the old param definitions match params for this block.
            $parseold = $this->parse_phpdocs($filepath, $blockname, $phpdocsold);

            $parameters = trim($matches[7][$i][0]);
            if (substr($parameters, 0, 1) == '(' && substr($parameters, -1) == ')') {
                $phpdocsnew = $this->get_phpdocs_parameters($filepath, $contents, $start,
                                    $indent, $blockname, $parameters, $parseold->comments);
            } else {
                // A "class" in in a PHP file.
                $phpdocsnew = $this->get_phpdocs_block($data, $indent, $blockname);
            }

            // Parse the new PHPdocs.
            $parsenew = $this->parse_phpdocs($filepath, $blockname, $phpdocsnew);

            $missing = false;
            $incorrect = false;
            if ($phpdocsold == '') {
                $missing = true;
            } else {
                $incorrect = $this->different_params($blockname, $parseold, $parsenew);
            }
            list($report, $remove, $fix) = $this->get_report_remove_fix($action, $missing, $incorrect, $mform);

            if ($remove) {
                $match = $matches[5][$i][0].$matches[6][$i][0].$matches[7][$i][0];
                $match = $lastline.$comments.$spacer.$indent.$match.'{';
                $contents = substr_replace($contents, $match, $start, $length);
                $this->store_phpdocs_message($filepath, $blockname, 'removed');
                $update = true;
            } else {
                if ($report) {
                    if ($missing) {
                        $this->store_phpdocs_message($filepath, $blockname, 'missing');
                    } else if ($incorrect) {
                        $this->store_phpdocs_message($filepath, $blockname, 'incorrect');
                    }
                } else if ($fix) {
                    $match = $matches[5][$i][0].$matches[6][$i][0].$matches[7][$i][0];
                    $match = $lastline.$comments.$spacer.$phpdocsnew.$indent.$match.'{';
                    $contents = substr_replace($contents, $match, $start, $length);

                    if ($missing) {
                        $this->store_phpdocs_message($filepath, $blockname, 'added');
                    } else if ($incorrect) {
                        $this->store_phpdocs_message($filepath, $blockname, 'fixed');
                    }
                    $update = true;
                }
            }
        }
    }

    /**
     * store_copyright_message
     *
     * @param string $filepath
     * @param mixed $type a PARAM_xxx constant value
     * 
     * TODO: Finish documenting this function
     */
    protected function store_copyright_message($filepath, $type) {
        $this->store_message($filepath, 'copyright', 'copyright'.$type);
    }

    /**
     * store_phpdocs_message
     *
     * @param string $filepath
     * @param string $blockname
     * @param mixed $type a PARAM_xxx constant value
     * 
     * TODO: Finish documenting this function
     */
    protected function store_phpdocs_message($filepath, $blockname, $type) {
        $this->store_message($filepath, $blockname, 'phpdocs'.$type);
    }

    /**
     * store_message
     *
     * @param string $filepath
     * @param string $blockname
     * @param mixed $type a PARAM_xxx constant value
     * 
     * TODO: Finish documenting this function
     */
    protected function store_message($filepath, $blockname, $type) {
        if (! array_key_exists($filepath, $this->messages)) {
            $this->messages[$filepath] = [];
        }
        if (! array_key_exists($type, $this->messages[$filepath])) {
            $this->messages[$filepath][$type] = [];
        }
        $this->messages[$filepath][$type][] = $blockname;
    }

    /**
     * display_messages
     *
     * 
     * TODO: Finish documenting this function
     */
    protected function display_messages() {

        $fileparams = ['class' => 'filelist'];
        $typeparams = ['class' => 'text-info font-weight-bold list-unstyled typelist'];
        $blockparams = ['class' => 'text-dark font-weight-normal blocklist'];

        // Initialize the string cache.
        $str = (object)[];

        // Cache the separators.
        $types = ['labelsep', 'listsep'];
        foreach ($types as $type) {
            $str->$type = get_string($type, 'langconfig');
        }

        // Cache copyright messages.
        $types = ['added', 'removed', 'missing'];
        foreach ($types as $type) {
            $type = 'copyright'.$type;
            $str->$type = get_string($type, $this->plugin);
        }

        // Cache PHPdocs messages.
        $types = ['unknownparam', 'incorrect', 'missing', 'removed', 'fixed', 'added'];
        foreach ($types as $type) {
            $type = 'phpdocs'.$type;
            $str->$type = get_string($type, $this->plugin);
        }

        // Sort the messages by filepath.
        asort($this->messages);

        $filelist = [];
        foreach ($this->messages as $filepath => $types) {

            // Sort the types by type.
            $sortedtypes = [];
            foreach (array_keys($types) as $type) {
                $sortedtypes[$type] = $str->$type;
            }
            asort($sortedtypes);

            $typelist = [];
            foreach (array_keys($sortedtypes) as $type) {

                if (substr($type, 0, 7) == 'phpdocs') {
                    // The blocknames were selected starting at the end of
                    // the file so we put them back into the original order.
                    $blocklist = array_reverse($types[$type]);
                    $blocklist = \html_writer::alist($blocklist, $blockparams, 'ul');
                    $typelist[] = $str->$type.$str->labelsep.$blocklist;
                } else if (substr($type, 0, 9) == 'copyright') {
                    // A copyright message has no "blockname".
                    $typelist[] = $str->$type;
                } else {
                    $typelist[] = $type; // Shouldn't happen !!
                }
            }
            $typelist = \html_writer::alist($typelist, $typeparams, 'ul');
            $filelist[] = $filepath.$typelist;
        }
        if (empty($filelist)) {
            $filelist = 'No messages for the selected files.';
        } else {
            $filelist = \html_writer::alist($filelist, $fileparams, 'ul');
        }
        echo $filelist;
    }

    /**
     * Parse a string containing a block of PHPdocs, and return
     * an object containing comments and tags in those PHPdocs.
     *
     * @param string $filepath the name of the file containing this block of PHP code
     * @param string $blockname the name for this block of PHP code
     * @param string $phpdocs a string containing PHPdocs
     * @return object to represent $phpdocs
     */
    public function parse_phpdocs($filepath, $blockname, $phpdocs) {

        $parse = (object)[
            'filepath' => $filepath,
            'blockname' => $blockname,
            'comments' => [],
            'tags' => [],
        ];

        $line = '[ \t]*\*[^\r\n]*[\r\n]+';
        $block = '[ \t]*\/\*+[\r\n]+'.
                 "((?:$line)*)".
                 '[ \t]*\*+\/[\r\n]+?';
        $tag = '\*\s+@(\w+)\s+(.*)';

        // See https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/
        // @uses $VARNAME [<description>]
        // @param [<Type>] [name] [<description>]
        // @return [Type] [<description>]
        // @todo [description] !!

        if (preg_match('/^'.$block.'$/us', $phpdocs, $blockmatch)) {
            if (preg_match_all('/'.$line.'/us', $blockmatch[1], $lines)) {

                $imax = count($lines[0]);
                for ($i = 0; $i < $imax; $i++) {

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
                        // * @param $mform !!
                        if (substr($type, 0, 1) === '$') {
                            $text = $name;
                            $name = $type;
                            $type = '';
                        }

                        // Create object to represent this param.
                        $t = (object)[
                            'type' => $type,
                            'name' => $name,
                            'text' => $text,
                        ];

                        // Initialize array for this type of $token.
                        if (empty($parse->tags[$token])) {
                            $parse->tags[$token] = [];
                        }

                        // Add this $token (params require a $name).
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
     * @param object $parseold parsed version of the old PHPDocs
     * @param object $parsenew parsed version of the expected PHPDocs
     * @return boolean TRUE if params are different, FALSE if they are the same.
     */
    public function different_params($blockname, $parseold, $parsenew) {
        if (array_key_exists('param', $parseold->tags)) {
            $paramsold = array_keys($parseold->tags['param']);
            sort($paramsold);
        } else {
            $paramsold = [];
        }

        if (array_key_exists('param', $parsenew->tags)) {
            $paramsnew = array_keys($parsenew->tags['param']);
            sort($paramsnew);
        } else {
            $paramsnew = [];
        }
        return ($paramsold !== $paramsnew);
    }

    /**
     * Get PHPdocs for a file, including the package and copyright
     *
     * @param stdClass $data submitted from the form
     * @param string $indent white space indentation for each line of the PHPdocs
     * @param string $filename used as the comment for this file
     * @return string containing the PHPdocs for a file
     */
    public function get_phpdocs_file($data, $indent, $filename) {
        return $this->get_phpdocs_block($data, $indent, $filename);
    }

    /**
     * Get PHPdocs for a block of PHP code, including the package and copyright
     *
     * @param stdClass $data submitted from the form
     * @param string $indent
     * @param string $blockname
     * @return xxx
     * 
     * TODO: Finish documenting this function
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
     * @param string $indent
     * @param xxx $comments
     * @param string $details
     * @return xxx
     * 
     * TODO: Finish documenting this function
     */
    public function get_phpdoc($indent, $comments, $details) {
        return $indent.'/'.'**'."\n".
               $this->get_comments_phpdoc($comments, $indent).
               $this->get_details_phpdoc($details, $indent).
               $indent.' *'.'/'."\n";
    }

    /**
     * get_comments_phpdoc
     *
     * @param xxx $comments
     * @param string $indent
     * @return xxx
     * 
     * TODO: Finish documenting this function
     */
    public function get_comments_phpdoc($comments, $indent) {
        if (is_scalar($comments)) {
            // Probably a function name on new PHPdocs.
            $comment = trim($comments);
            if ($pos = strrpos($comment, '.')) {
                $comment = substr($comment, $pos + 1);
            }
            $comments = "$indent * $comment\n";
        } else {
            // Probably old PHPdocs.
            foreach ($comments as $i => $comment) {
                $comments[$i] = "$indent * $comment\n";
            }
            $comments = implode('', $comments);
        }
        return $comments;
    }

    /**
     * get_details_phpdoc
     *
     * @param xxx $details
     * @param xxx $indent
     * @return xxx
     * 
     * TODO: Finish documenting this function
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
     * @param string $filepath relative to $CFG->dirroot
     * @param string $contents the content of the file
     * @param integer $start position of the start of this block with the file contents
     * @param string $indent white space to begin each line of PHPdocs
     * @param string $blockname the function name e.g. "xmldb_vocab_check_structure"
     * @param string $parameters the function parameters e.g. "($dbman, $tablenames=null)"
     * @param mixed $comments comments to use in the PHPdocs. If empty, then $blockname will be used.
     * @return string containing the PHPdocs for a block of PHP code
     */
    public function get_phpdocs_parameters($filepath, $contents, $start, $indent, $blockname, $parameters, $comments) {

        $details = '';
        $unknownparams = [];
        $search = '/'.'(\w*)(\&?)(\$\w+)(\s*=\s*([^,]*))?'.'/';
        // The matches should be available as follows
        // [0][$i] : type + reference + name + default value
        // [1][$i] : parameter type (optional)
        // [2][$i] : reference (optional leading "&")
        // [3][$i] : parameter name (with leading "$")
        // [4][$i] : default value expression (i.e. "=" + default $value)
        // [5][$i] : default value !!
        if (preg_match_all($search, trim($parameters, ' ()'), $matches)) {
            $imax = count($matches[0]);
            for ($i = 0; $i < $imax; $i++) {
                $name = $matches[3][$i];

                if (array_key_exists($name, $this->paramnames)) {
                    $type = $this->paramnames[$name]->type;
                    $text = $this->paramnames[$name]->text;
                } else {
                    $type = ($matches[1][$i] ? $matches[1][$i] : 'xxx');
                    $text = '';
                    $this->paramnames[$name] = (object)['type' => $type, 'text' => $text];
                    $unknownparams[] = $name;
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

        if ($unknownparams = implode(', ', $unknownparams)) {
            $this->store_phpdocs_message($filepath, "$blockname ($unknownparams)", 'unknownparam');
        }

        // Get the $pos(ition) of the end of the function.
        if ($pos = strpos($contents, "\n$indent}", $start)) {
            $substr = substr($contents, $start, $pos - $start);
            if (preg_match_all('/(?<=global )\$[^;]*(?=;)/', $substr, $matches)) {
                $globals = [];
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

        $details .= "$indent * 
 * TODO: Finish documenting this function\n";

        if (empty($comments)) {
            $comments = $blockname;
        }
        return $this->get_phpdoc($indent, $comments, $details);
    }

    /**
     * remove_phpdocs
     *
     * @param string $contents (passed by reference)
     * @param boolean $update (passed by reference)
     * @return xxx
     * 
     * TODO: Finish documenting this function
     */
    public function remove_phpdocs(&$contents, &$update) {
        $search = '/'.
                  '(^ *\/\*\*[\r\n]+)'. // Top line.
                  '(^ *\*.*?[\r\n]+)+'. // Mid line.
                  '(^ *\*\/[\r\n]+)'. // Bottom line.
                  '/um'; // Settings for unicode and multiline.
        $contents = preg_replace($search, '', $contents, -1, $count);
        return [($count > 0), $contents];
    }

    /**
     * get_copyright
     *
     * @param string $filetype file type/extension (optional, default='')
     * @return xxx
     * 
     * TODO: Finish documenting this function
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
