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
 * renderer.php The renderer for the vocab acitivity module.
 *
 * @package mod_vocab
 * @copyright 2023 Gordon BATESON
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Gordon BATESON https://github.com/gbateson
 * @since Moodle 3.11
 */

/**
 * mod_vocab_renderer
 *
 * @package mod_vocab
 * @copyright 2023 Gordon Bateson
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Gordon Bateson gordonbateson@gmail.com
 * @since Moodle 3.11
 */
class mod_vocab_renderer extends plugin_renderer_base {

    /**
     * @var \mod_vocab\activity object to represent current vocab activity
     */
    public $vocab = null;

    /**
     * Attach a vocab activity to this renderer
     *
     * @param \mod_vocab\activity $vocab object to represent a vocab activity.
     * @return null, but will update the "vocab" property of this renderer.
     */
    public function attach_activity($vocab) {
        $this->vocab = $vocab;
    }

    /**
     * header
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function header() {
        $header = parent::header();

        $heading = $this->vocab->name;
        if ($this->vocab->can_manage() && $this->vocab->id) {
            $heading .= $this->modedit_icon();
        }

        // Locate the position of the end of the <body...> tag.
        // Could add '<html', '>', '<head', '>', '</head>'.
        $pos = 0;
        $tags = ['<body', '>'];
        while (count($tags) && is_numeric($pos)) {
            $pos = strpos($header, array_shift($tags), $pos);
        }

        // Locate the first <h2...> or <h1...> tag.
        if (is_numeric($pos)) {
            if ($start = strpos($header, '<h2', $pos)) {
                // E.g. base, embedded, popup, secure.
                $start = strpos($header, '>', $start) + 1;
                $end = strpos($header, '</h2>', $start);
            } else if ($start = strpos($header, '<h1>', $pos)) {
                // E.g. includes maintenance, secure.
                $start = strpos($header, '>', $start) + 1;
                $end = strpos($header, '</h1', $start);
            } else {
                // E.g. the "login" page which has neither <h1> nor <h2>.
                $start = $end = 0;
            }
            if ($start === false || $end === false || $start >= $end) {
                $header .= html_writer::tag('h2', $heading);
            } else {
                $header = substr_replace($header, $heading, $start, $end - $start);
            }
        }
        return $header;
    }

    /**
     * modedit_icon
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function modedit_icon() {
        $params = [
            'update' => $this->vocab->cm->id,
            'sesskey' => sesskey(),
            'return' => 1,
        ];
        $url = new moodle_url('/course/modedit.php', $params);
        $img = $this->pix_icon('t/edit', get_string('edit'));
        // Add container to restrict width, height and font-size to 16px.
        $img = \html_writer::tag('span', $img, ['class' => 'icon-size-3']);
        return ' '.html_writer::link($url, $img);
    }

    /**
     * view_page
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_page() {
        $output = '';
        $output .= $this->view_information();
        $output .= $this->view_results_and_wordlist();
        $output .= $this->view_games();
        return $output;
    }

    /**
     * view_page_guest
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_page_guest() {
        $output = '';
        $output .= $this->view_information();
        $message = html_writer::tag('p', get_string('guestsnotallowed', 'vocab'))."\n".
                   html_writer::tag('p', get_string('liketologin'))."\n";
        $output .= $this->confirm($message, get_login_url(), get_local_referer(false));
        return $output;
    }

    /**
     * view_page_notenrolled
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_page_notenrolled() {
        $output = '';
        $output .= $this->view_information();
        $message = html_writer::tag('p', get_string('youneedtoenrol', 'vocab'))."\n".
                   html_writer::tag('p', $this->continue_button($this->vocab->course_url()))."\n";
        $output .= $this->box($message, 'generalbox', 'notice');
        return $output;
    }

    /**
     * view_intro
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_intro() {
        if (html_is_blank($this->vocab->intro)) {
            return '';
        } else {
            $intro = format_module_intro('vocab', $this->vocab, $this->vocab->cm->id);
            return $this->box($intro, 'generalbox', 'intro');
        }
    }

    /**
     * view_information
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_information() {
        $output = '';
        $output = html_writer::tag('p', 'Vocab information goes here (may add access messages later).');
        return $output;
    }

    /**
     * view_results_and_wordlist
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function view_results_and_wordlist() {
        $output = '';
        $words = $this->vocab->get_wordlist_words();
        $output .= $this->view_results($words);
        $output .= $this->view_wordlist($words);
        $params = ['class' => 'clearfix vocab-results-and-wordlist'];
        return html_writer::tag('div', $output, $params);
    }

    /**
     * view_results
     *
     * @uses $USER
     * @param array $words
     * @param xxx $user (optional, default=null)
     * @return string of HTML to display results
     *
     * TODO: Finish documenting this function
     */
    public function view_results($words, $user=null) {
        global $USER;

        if ($user === null) {
            $user = $USER;
        }

        $output = '';

        $title = $this->vocab->get_string('resultstitle', fullname($user));
        $output .= html_writer::tag('h4', $title);

        list($total, $completed, $inprogress, $notstarted) = $this->get_wordcounts($words);
        if ($total == 0) {
            if ($this->vocab->can_manage()) {
                $msg = $this->vocab->get_string('nowordsfound');
                $output .= $this->notification($msg, 'warning');
            } else {
                $msg = $this->vocab->get_string('nowordsforyou');
                $output .= $this->notification($msg, 'info');
            }
        } else {
            $completedpercent = round(100 * $completed / $total, 1);
            $inprogresspercent = round(100 * $inprogress / $total, 1);
            $notstartedpercent = round(100 * $notstarted / $total, 1);

            // Use view-box to make svg responsive. This prevents overflow on narrow screens
            // view-box: https://www.digitalocean.com/community/tutorials/svg-svg-viewbox .
            $width = '360';
            $height = '200';
            $params = [
                'xmlns' => 'http://www.w3.org/2000/svg',
                'role' => 'img', // For accessibility.
                'viewBox' => "0 0 $width $height",
                'class' => 'results-pie-chart',
            ];
            $output .= html_writer::start_tag('svg', $params);

            $output .= html_writer::tag('title', $title);

            $delimiter = get_string('labelsep', 'langconfig');
            $a = (object)[
                'completed' => $this->vocab->get_string('resultdesc', (object)[
                    'label' => $this->vocab->get_string('completed'),
                    'delimiter' => $delimiter,
                    'number' => $completed,
                    'total' => $total,
                    'percent' => $completedpercent,
                ]),
                'inprogress' => $this->vocab->get_string('resultdesc', (object)[
                    'label' => $this->vocab->get_string('inprogress'),
                    'delimiter' => $delimiter,
                    'number' => $inprogress,
                    'total' => $total,
                    'percent' => $inprogresspercent,
                ]),
                'notstarted' => $this->vocab->get_string('resultdesc', (object)[
                    'label' => $this->vocab->get_string('notstarted'),
                    'delimiter' => $delimiter,
                    'number' => $notstarted,
                    'total' => $total,
                    'percent' => $notstartedpercent,
                ]),
            ];
            $desc = $this->vocab->get_string('resultsdesc', $a);
            $output .= html_writer::tag('description', $desc);

            $values = [$completed, $inprogress, $notstarted];
            $colors = ['url(#fillcompleted)', 'url(#fillinprogress)', 'url(#fillnotstarted)'];

            // Define the fill patterns.
            $output .= $this->svg_fill_patterns();

            // Define the pie graph.
            $params = ['stroke' => '#333', 'stroke-width' => '2'];
            $output .= $this->pie_graph(98, 1, 1, $values, $colors, $params);

            // Define the pie graph key.
            $output .= $this->pie_graph_key(220, 10, 30, 20, 30, $colors,
                $delimiter, array_values((array)$a), // The delimiter and texts.
                ['font-size' => 14, 'fill' => '#333'], // The textparams.
                ['stroke' => '#333', 'stroke-width' => '1'] // The rectparams.
            );
            $output .= html_writer::end_tag('svg');
        }

        $params = ['class' => 'bg-light border rounded mt-1 mr-2 p-2 float-md-left vocab-results'];
        return html_writer::tag('div', $output, $params);
    }

    /**
     * get_wordcounts
     *
     * @param array $words
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function get_wordcounts($words) {
        $total = count($words);
        if ($this->vocab->is_demo()) {
            // Note, casting to integer is required for PHP >= 8.2.
            $completed = rand(0, (int)(0.9 * $total));
            $inprogress = rand(0, (int)(0.9 * ($total - $completed)));
        } else {
            $completed = 0;
            $inprogress = 0;
        }
        $notstarted = ($total - $completed - $inprogress);
        return [$total, $completed, $inprogress, $notstarted];
    }

    /**
     * svg_fill_patterns
     *
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function svg_fill_patterns() {
        $output = '';
        $output .= html_writer::start_tag('defs');
        $output .= $this->svg_fill_pattern('fillcompleted', 10, 10, '#3366ff', 'M1,5 H8 M5,1 V8', 'white', 1);
        $output .= $this->svg_fill_pattern('fillinprogress', 10, 10, '#ff9933', 'M2,2 L7,7', 'white', 1);
        $output .= $this->svg_fill_pattern('fillnotstarted', 10, 10, '#cccccc', 'M4,5 H7', 'white', 1);
        $output .= html_writer::end_tag('defs');
        return $output;
    }

    /**
     * svg_fill_pattern
     *
     * @param xxx $id
     * @param xxx $width
     * @param xxx $height
     * @param xxx $fillcolor
     * @param xxx $d
     * @param xxx $strokecolor
     * @param xxx $strokewidth
     * @return xxx
     *
     * TODO: Finish documenting this function
     */
    public function svg_fill_pattern($id, $width, $height, $fillcolor, $d, $strokecolor, $strokewidth) {
        $output = '';
        if ($fillcolor) {
            $output .= html_writer::tag('rect', '', [
                'width' => $width,
                'height' => $height,
                'fill' => $fillcolor,
            ]);
        }
        if ($strokecolor) {
            $output .= html_writer::tag('path', '', [
                'd' => $d,
                'stroke' => $strokecolor,
                'stroke-width' => $strokewidth,
            ]);
        }
        if ($output) {
            $output = html_writer::tag('pattern', $output, [
                'id' => $id,
                'width' => $width,
                'height' => $height,
                'patternUnits' => 'userSpaceOnUse',
            ]);
        }
        return $output;
    }

    /**
     * Build HTML to display the pie-chart as an SVG image.
     *
     * @param integer $radius radius of the pie-chart (in pixels)
     * @param integer $offsetx the "x" offset (in pixels) to the left hand edge of the pie chart
     * @param integer $offsety the "y" offset (in pixels) to the top edge of the pie chart
     * @param array $values numbers to be displayed as a pie-chart
     * @param array $colors strings colors expressed as RGB colors
     * @param array $params settings to be used in the <path> tag e.g. "stroke", "stroke-width"
     * @return string HTML code for the pie-chart as an SVG image
     */
    public function pie_graph($radius, $offsetx, $offsety, $values, $colors, $params) {
        $output = '';

        // Pie chart is drawn as a series of SVG arcs
        // each created using the following specifications:
        // M: coordinates of the starting point of the path (always the center of the pie)
        // L: coordinate of the end point of a straight line (a point on the edge of the pie)
        // A: the radii length, flags and end point of an arc
        // Z: return to start point (M) .

        // Cache the number of radians in a fullcircle.
        // We can use "deg2rad(360)" or "2 * pi() radians".
        $fullcircleradians = deg2rad(360);

        // Define the center of the circle.
        $center = ($offsetx + $radius).','.($offsety + $radius);

        // Calculate the total value.
        $total = array_sum($values);

        // Set the coordinates of the starting point of the 1st arc to be
        // the top of the circle, i.e. where the number 12 is on a clock.
        $x = ($offsetx + $radius);
        $y = $offsety;

        $subtotal = 0;
        foreach ($values as $i => $value) {
            if ($value = intval($value)) {

                // Define the end point point of the line
                // (start point is the center of the circle).
                $l = "$x,$y";

                // Calculate the angle (in radians) of this arc
                // as a proportion of the full circle.
                $subtotal += $value;
                $radians = ($fullcircleradians * ($subtotal / $total));

                // Calculate coordinates ($x, $y) of the end of the arc.
                // Remember that in an SVG image, 0,0 is the top left corner,
                // "x" goes from left to right, and "y" goes from top to bottom.
                // That's why we need a "+" for "x", and a "-" for "y".
                $x = round($offsetx + ($radius * (1 + sin($radians))), 1);
                $y = round($offsety + ($radius * (1 - cos($radians))), 1);

                // Define the arc parameters.
                if ($value == $total) {
                    // If there's only one value, then
                    // draw a (nearly) complete circle.
                    $a = "$radius,$radius 0 1 1 ".($x - 1).",$y";
                } else {
                    // The "rotation" always 0, and "sweep" is always 1.
                    // The "largearc" is set to 1 if arc covers more than half the pie.
                    $largearc = ($value < ($total / 2) ? 0 : 1);
                    $a = "$radius,$radius 0 $largearc 1 $x,$y";
                }

                // Build the path d(efinition) and add it to the tag parameters.
                $d = "M$center L$l A$a L$center Z";
                $p = ['d' => $d, 'fill' => $colors[$i]];

                $output .= html_writer::tag('path', '', array_merge($p, $params));
            }
        }

        return $output;
    }

    /**
     * Generate SVG code for the pie-graph's key.
     *
     * @param integer $xoffset
     * @param integer $xspace
     * @param integer $yoffset
     * @param integer $yspace
     * @param integer $size
     * @param array $colors strings colors expressed as RGB colors
     * @param string $delimiter
     * @param array $texts
     * @param array $textparams
     * @param array $rectparams
     * @return string SVG code to represent the pie graph key
     */
    public function pie_graph_key($xoffset, $xspace, $yoffset, $yspace,
        $size, $colors, $delimiter, $texts, $textparams, $rectparams) {

        $p = [
            'x' => $xoffset,
            'y' => 0,
            'width' => $size,
            'height' => $size,
        ];
        $rectparams = array_merge($p, $rectparams);

        $p = [
            'x' => $xoffset + $size + $xspace,
            'y' => 0,
        ];
        $textparams = array_merge($p, $textparams);

        $output = '';

        // Split each text into two lines, splitting at the delimiter.
        if ($delimiter) {
            foreach ($texts as $t => $text) {
                $lines = array_map('trim', explode($delimiter, $text, 2));
                foreach ($lines as $l => $line) {
                    $p = [
                        'x' => $textparams['x'],
                        'dy' => ($l * 1.2).'em',
                    ];
                    $lines[$l] = html_writer::tag('tspan', $line, $p);
                }
                $texts[$t] = implode('', $lines);
            }
        }

        foreach ($texts as $i => $text) {
            $y = $yoffset + ($i * ($size + $yspace));

            $rectparams['y'] = $y;
            $rectparams['fill'] = $colors[$i];
            $output .= html_writer::tag('rect', '', $rectparams);

            $textparams['y'] = ($y + ($size * 0.4));
            $output .= html_writer::tag('text', $text, $textparams);
        }
        return $output;
    }

    /**
     * display a list of words
     *
     * @param array $words array of words to be displayed.
     * @return string an HTML string.
     */
    public function view_wordlist($words) {
        $output = '';

        $wordlist = [];
        foreach ($words as $word) {
            $wordlist[] = html_writer::tag('li', $word, ['class' => 'my-0 py-0 vocab-wordlist-word']);
        }

        $title = $this->vocab->get_string('wordlistcontainingnwords', count($wordlist));
        $output .= html_writer::tag('h4', $title);

        if ($wordlist = implode('', $wordlist)) {
            $params = ['class' => 'bg-white border rounded py-1 px-2 my-0 list-unstyled vocab-wordlist-words'];
            $output .= html_writer::tag('ul', $wordlist, $params);
        }

        $params = ['class' => 'bg-light border rounded mt-1 mr-2 p-2 float-md-left vocab-wordlist'];
        return html_writer::tag('div', $output, $params);
    }

    /**
     * view_games
     *
     * @return string an HTML string.
     */
    public function view_games() {
        $output = '';

        $games = $this->get_games();
        $countgames = count($games);
        $gamecolors = $this->get_colors('#bbddff', '#1144ff', $countgames);
        $textcolors = $this->get_colors('#000000', '#ff9933', $countgames);

        foreach ($games as $i => $game) {
            // Switch the bg color from black to white
            // when half of the buttons have been generated.
            $textcolor = ($i < ($countgames / 2) ? 'black' : 'white');
            $output .= $this->game_button($game, $gamecolors[$i], $textcolor);
        }

        $params = ['class' => 'clearfix vocab-games'];
        return html_writer::tag('div', $output, $params);
    }

    /**
     * get_games
     *
     * @return string an HTML string.
     */
    public function get_games() {
        $games = [];
        if ($this->vocab->is_demo()) {
            for ($i = 0; $i < rand(1, 20); $i++) {
                $games[] = (object)[
                    'name' => "vocabgame_$i",
                    'label' => 'Vocabulary game '.($i + 1),
                    'url' => '#',
                ];
            }
        } else {
            $games = [];
            /*
                Get the list of games available for this Vocab activity.
                Each game can be hidden or shown in any Vocab activity,
                and furthermore may have it's own settings to add into
                the module settings page.
                Each game can define its own icon (as an svg) or fontawesome icon.
                vocab_games table looks like this:
                    id name siteenabled
                vocab_game_instances table looks like this:
                    vocabid gameid enabled configdata
            */
        }
        return $games;
    }

    /**
     * game_button
     *
     * @param xxx $game
     * @param xxx $gamecolor (optional, default='')
     * @param xxx $textcolor (optional, default='')
     * @return string of HTML to display a game button.
     *
     * TODO: Finish documenting this function
     */
    public function game_button($game, $gamecolor='', $textcolor='') {
        // See "single_button" method in "lib/outputrenderers.php".
        $style = [];
        if ($gamecolor) {
            $style[] = "background-color: $gamecolor;";
        }
        if ($textcolor) {
            $style[] = "color: $textcolor;";
        }
        $params = [];
        if ($style = implode('', $style)) {
            $params['style'] = $style;
        }
        $params['class'] = 'btn vocab-game-button';
        return $this->single_button($game->url, $game->label, 'get', $params);
    }

    /**
     * Accepts two RGB colors and an integer "n"
     * and returns an array containing "n" number of colors
     * that are equidistant between the two input colors.
     *
     * @param string $startcolor an RGB color, e.g. '#ff6633'.
     * @param string $endcolor an RGB color, e.g. '#aabbcc'.
     * @param integer $n number of colors to return.
     * @return array containing "n" number of RGB colors.
     */
    public function get_colors($startcolor, $endcolor, $n) {

        if ($n == 0) {
            return [];
        }
        if ($n == 1) {
            return [$startcolor];
        }
        if ($n == 2) {
            return [$startcolor, $endcolor];
        }

        $r1 = hexdec(substr($startcolor, 1, 2));
        $g1 = hexdec(substr($startcolor, 3, 2));
        $b1 = hexdec(substr($startcolor, 5, 2));

        $r2 = hexdec(substr($endcolor, 1, 2));
        $g2 = hexdec(substr($endcolor, 3, 2));
        $b2 = hexdec(substr($endcolor, 5, 2));

        $rstep = ($r2 - $r1) / ($n - 1);
        $gstep = ($g2 - $g1) / ($n - 1);
        $bstep = ($b2 - $b1) / ($n - 1);

        $colors = [];
        for ($i = 0; $i < $n; $i++) {
            // Calculate new RGB values and convert to hex.
            $r = round($r1 + $i * $rstep);
            $g = round($g1 + $i * $gstep);
            $b = round($b1 + $i * $bstep);
            $colors[] = sprintf("#%02x%02x%02x", $r, $g, $b);
        }
        return $colors;
    }

    /**
     * find_missing_files
     *
     * @param string $plugin name
     * @param bool $purgecaches
     * @return string HTML code to display results
     */
    public function find_missing_files($plugin, $purgecaches=true) {
        global $CFG, $DB, $FULLME;

        require_once($CFG->dirroot.'/lib/questionlib.php');

        // Set up the heading i.e. Find missing files.
        $heading = get_string('pluginname', $plugin)." ($plugin)";
        $heading = $this->vocab->get_string('findmissingfiles', $heading);

        $output = '';
        $output .= $this->header();
        $output .= $this->heading($heading);
        $output .= $this->box_start();

        $startedlists = false;
        $startedlist = false;

        $courseid = 4;
        $course = $DB->get_record('course', ['id' => $courseid]);
        $coursecontext = context_course::instance($courseid);

        $params = ['contextid' => $coursecontext->id, 'parent' => 0];
        $coursecategory = $DB->get_record('question_categories', $params);

        $categoryids = question_categorylist($coursecategory->id);

        // Map category ids to category records.
        $categories = [];

        // Map question ids to question records.
        $questions = [];

        // Map parent categories onto their subcategories.
        $subcategories = [];

        // Map category ids onto ids of questions with faulty files.
        $missingfiles = [];

        list($where, $params) = $DB->get_in_or_equal($categoryids);
        $select = implode(',', [
            'qv.id',
            'qv.questionid',
            'qc.id AS catid',
            'qc.name AS catname',
            'q.name AS questionname',
        ]);
        ;
        $from = implode(',', [
            '{question_bank_entries} qbe',
            '{question_versions} qv',
            '{question_categories} qc',
            '{question} q',
        ]);
        $where = implode(' AND ', [
            "qbe.questioncategoryid $where",
            'qbe.id = qv.questionbankentryid',
            'qbe.questioncategoryid = qc.id',
            'qv.questionid = q.id',
        ]);
        $sql = "SELECT $select FROM $from WHERE $where";
        if ($entries = $DB->get_records_sql($sql, $params)) {
            $filerecord = [
                'contextid' => $coursecontext->id,
                'component' => 'question',
                'filearea' => 'questiontext',
                'itemid' => 0, // Set later.
                'filepath' => '/', // Always this value.
                'filename' => '', // Set later.
            ];
            foreach ($entries as $versionid => $entry) {
                $select = 'contextid = ? AND '.
                          'component = ? AND '.
                          'filearea = ? AND '.
                          'itemid = ? AND '.
                          'filepath = ? AND '.
                          'filename <> ?';
                $params = [
                    $coursecontext->id,
                    'question',
                    'questiontext',
                    $entry->questionid,
                    '/',
                    '.',
                ];
                if ($files = $DB->get_records_select('files', $select, $params)) {
                    foreach ($files as $file) {
                        $hash = $file->contenthash;
                        $dir1 = substr($hash, 0, 2);
                        $dir2 = substr($hash, 2, 2);
                        if (! file_exists($CFG->dataroot."/filedir/$dir1/$dir2/$hash")) {
                            $questions[$entry->questionid] = $entry->questionname;

                            // Initialize the subcategory id.
                            $catid = $entry->catid;
                            $subcatid = 0;

                            // Get the next/first parent category.
                            while ($catid && ($category = $DB->get_record('question_categories', ['id' => $catid]))) {

                                // Cache the category id for convenience.
                                $catid = $category->id;

                                // Cache the category record - we may need it later.
                                if (empty($categories[$catid])) {
                                    $category->questionids = [];
                                    $categories[$catid] = $category;
                                }
                                $categories[$catid]->questionids[$entry->questionid] = 1;

                                if ($subcatid) {
                                    // Update the subcategories for the parent category.
                                    if (empty($subcategories[$catid])) {
                                        $subcategories[$catid] = [];
                                    }
                                    if (empty($subcategories[$catid][$subcatid])) {
                                        $subcategories[$catid][$subcatid] = 0;
                                    }
                                    $subcategories[$catid][$subcatid]++;
                                }

                                $subcatid = $catid;
                                $catid = $category->parent;
                            }

                            if (empty($missingfiles[$entry->catid])) {
                                $missingfiles[$entry->catid] = [];
                            }
                            if (empty($missingfiles[$entry->catid][$entry->questionid])) {
                                $missingfiles[$entry->catid][$entry->questionid] = [];
                            }
                            $missingfiles[$entry->catid][$entry->questionid][] = $file->filepath.$file->filename;
                        }
                    }
                }
            }
        }

        if (empty($categories)) {
            $output .= \html_writer::tag('p', 'No missing files found. Well done!');
        } else {
            // Fix the name of the "top" question category for this course.
            $catid = $coursecategory->id;
            if (array_key_exists($catid, $categories)) {
                $name = $coursecontext->get_context_name(false, false, true);
                $name = get_string('topfor', 'question', $name);
                $categories[$catid]->name = $name;
            }

            // Print the category tree, starting at the top course question category.
            $output .= \html_writer::start_tag('div', ['class' => 'container ml-0']);
            $output .= $this->format_question_category(
                $courseid, $coursecategory->id,
                $categories, $questions,
                $missingfiles, $subcategories
            );
            $output .= \html_writer::end_tag('div');
        }

        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Formats the output for a single question category, including its subcategories and incomplete questions.
     *
     * This method recursively builds an HTML structure that displays a category's name,
     * associated subcategories, and any questions with missing media files. It also
     * includes action links for editing or deleting faulty questions.
     *
     * @param int $courseid The ID of the course the category belongs to.
     * @param int $catid The ID of the question category to render.
     * @param array $categories Reference to the full list of categories, keyed by category ID.
     * @param array $questions Reference to the list of formatted question summaries.
     * @param array $missingfiles Reference to questions missing one or more required files, keyed by category/question ID.
     * @param array $subcategories Reference to a tree of subcategory relationships, keyed by parent category ID.
     *
     * @return string HTML output representing the category block.
     */
    public function format_question_category($courseid, $catid, &$categories, &$questions, &$missingfiles, &$subcategories) {
        $output = '';

        $category = $categories[$catid];

        $form = '\\vocabtool_questionbank\\form';

        // Note, $category->questionids could be used to delete ALL faulty questions in this category.
        $count = [];
        if (array_key_exists($catid, $subcategories)) {
            $count[] = '['.count($subcategories[$catid]).' categories]';
        }
        if (property_exists($category, 'questionids')) {
            $params = ['onclick' => "this.target = 'vocabtool_questionbank';"];

            $delete = \html_writer::link(
                $form::delete_questions_url($courseid, array_keys($category->questionids)),
                $form::action_icon('delete', 'delete', 't/delete'),
                $params
            );

            $edit = \html_writer::link(
                new \moodle_url('/question/edit.php', [
                    'courseid' => $courseid,
                    'cat' => join(',', [$category->id, $category->contextid]),
                ]),
                $form::action_icon('edit', 'editcategorythis', 't/edit'),
                $params
            );

            $count[] = '['.count($category->questionids).' questions '.$edit.$delete.']';
        }
        if ($count = join(' ', $count)) {
            $count = ' '.\html_writer::tag('small', $count, ['class' => 'dimmed']);
        }

        $output = '';
        $output .= \html_writer::start_tag('dl', ['class' => 'my-1 ml-4 mr-0']);
        $output .= \html_writer::tag('dt', 'Category: '.$category->name.$count, ['class' => 'my-0']);
        $output .= \html_writer::start_tag('dd', ['class' => 'my-0']);

        if (array_key_exists($catid, $subcategories)) {
            foreach ($subcategories[$catid] as $subcatid => $missingfilecount) {
                $output .= $this->format_question_category(
                    $courseid, $subcatid, $categories, $questions, $missingfiles, $subcategories
                );
            }
        }

        if (array_key_exists($catid, $missingfiles)) {
            foreach ($missingfiles[$catid] as $questionid => $files) {
                sort($files);
                // Get standard icons for edit, view and delete.
                $summary = $form::format_questionids($courseid, $questionid);
                // Remove leading numeric question id.
                $summary = preg_replace('/(?<=>)\d+ (?=<)/', '', $summary, 1);
                $summary = implode(' ', [
                    '['.count($files).' files]',
                    $summary,
                    $questions[$questionid],
                ]);
                $output .= $this->format_question_details($summary, $files);
            }
        }

        $output .= \html_writer::end_tag('dd');
        $output .= \html_writer::end_tag('dl');
        return $output;
    }

    /**
     * Formats a collapsible HTML details block for a single question and its missing files.
     *
     * @param string $summary A short summary describing the question and file count.
     * @param array $details An array of missing file names or paths.
     *
     * @return string HTML markup containing the summary and an ordered list of file names.
     */
    public function format_question_details($summary, $details) {
        $output = '';
        $output .= \html_writer::start_tag('details', ['name' => 'questiondetails']);
        $output .= \html_writer::tag('summary', $summary);
        $output .= \html_writer::alist($details, ['class' => 'mt-0 mb-2'], 'ol');
        $output .= \html_writer::end_tag('details');
        return $output;
    }
}
