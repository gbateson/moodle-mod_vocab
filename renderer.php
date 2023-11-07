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
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_vocab_renderer
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson gordonbateson@gmail.com
 * @since      Moodle 3.11
 */
class mod_vocab_renderer extends plugin_renderer_base {

    /** @var \mod_vocab\activity object to represent current vocab activity */
    var $vocab = null;

    /**
     * Attach a vocab activity to this renderer
     *
     * @param stdClass \mod_vocab\activity object to represent a vocab activity.
     * @return null, but will update the "vocab" property of this renderer.
     */
    public function attach_activity($vocab) {
        $this->vocab = $vocab;
    }

    /**
     * header
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function header() {
        $header = parent::header();
        
        $heading = $this->vocab->name;
        if ($this->vocab->can_manage() && $this->vocab->id) {
            $heading .= $this->modedit_icon();
        }

        // Locate the position of the end of the <body...> tag.
        $pos = 0;
        $tags = array('<body', '>'); // '<html', '>', '<head', '>', '</head>', 
        while (count($tags) && is_numeric($pos)) {
            $pos = strpos($header, array_shift($tags), $pos);
        }

        // Locate the first <h2...> or <h1...> tag.
        if (is_numeric($pos)) {
            if ($start = strpos($header, '<h2', $pos)) {
                // base, embedded, popup, secure
                $start = strpos($header, '>', $start) + 1;
                $end = strpos($header, '</h2>', $start);
            } else if ($start = strpos($header, '<h1>', $pos)) {
                // maintenance, secure
                $start = strpos($header, '>', $start) + 1;
                $end = strpos($header, '</h1', $start);
            } else {
                // "login" page has neither <h1> nor <h2>.
                $start = $end = 0;
            }
            if ($start === false || $end === false || $start >= $end) {
                $header .= \html_writer::tag('h2', $heading);
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
     * @todo Finish documenting this function
     */
    public function modedit_icon() {
        $params = array('update' => $this->vocab->cm->id,
                        'return' => 1,
                        'sesskey' => sesskey());
        $url = new moodle_url('/course/modedit.php', $params);
        $img = $this->pix_icon('t/edit', get_string('edit'));
        return ' '.html_writer::link($url, $img);
    }

    /**
     * view_page
     *
     * @return xxx
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
     */
    public function view_results_and_wordlist() {
        $output = '';
        $words = $this->vocab->get_wordlist_words();
        $output .= $this->view_results($words);
        $output .= $this->view_wordlist($words);
        $params = array('class' => 'clearfix vocab-results-and-wordlist');
        return html_writer::tag('div', $output, $params);
    }

    /**
     * view_results
     *
     * @uses $USER
     * @param array $words
     * @param xxx $user (optional, default=null)
     * @return string of HTML to display results
     * @todo Finish documenting this function
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

            // Use view-box to make svg responsive. This prevents overflow on narrow screens.
            // view-box: https://www.digitalocean.com/community/tutorials/svg-svg-viewbox
            $width = '360';
            $height = '200';
            $params = array('xmlns' => 'http://www.w3.org/2000/svg',
                            'role' => 'img', // for accessibility
                            'viewBox' => "0 0 $width $height",
                            'class' => 'results-pie-chart');
            $output .= html_writer::start_tag('svg', $params);

            $output .= html_writer::tag('title', $title);

            $delimiter = get_string('labelsep', 'langconfig');
            $a = (object)array(
                'completed' => $this->vocab->get_string('resultdesc', (object)array(
                    'label' => $this->vocab->get_string('completed'),
                    'delimiter' => $delimiter,
                    'number' => $completed,
                    'total' => $total,
                    'percent' => $completedpercent,
                )),
                'inprogress' => $this->vocab->get_string('resultdesc', (object)array(
                    'label' => $this->vocab->get_string('inprogress'),
                    'delimiter' => $delimiter,
                    'number' => $inprogress,
                    'total' => $total,
                    'percent' => $inprogresspercent,
                )),
                'notstarted' => $this->vocab->get_string('resultdesc', (object)array(
                    'label' => $this->vocab->get_string('notstarted'),
                    'delimiter' => $delimiter,
                    'number' => $notstarted,
                    'total' => $total,
                    'percent' => $notstartedpercent,
                )),
            );
            $desc = $this->vocab->get_string('resultsdesc', $a);
            $output .= html_writer::tag('description', $desc);

            $values = array($completed, $inprogress, $notstarted);
            $colors = array('url(#fillcompleted)', 'url(#fillinprogress)', 'url(#fillnotstarted)');

            // Define the fill patterns.
            $output .= $this->svg_fill_patterns();

            // Define the pie graph.
            $params = array('stroke' => '#333', 'stroke-width' => '2');
            $output .= $this->pie_graph(98, 1, 1, $values, $colors, $params);

            // Define the pie graph key.
            $output .= $this->pie_graph_key(220, 10, 30, 20, 30, $colors,
                $delimiter, array_values((array)$a), // delimiter & texts
                array('font-size' => 14, 'fill' => '#333'), // textparams
                array('stroke' => '#333', 'stroke-width' => '1') // rectparams
            );
            $output .= html_writer::end_tag('svg');
        }

        $params = array('class' => 'bg-light border rounded mt-1 mr-2 p-2 float-md-left vocab-results');
        return html_writer::tag('div', $output, $params);
    }

    /**
     * get_wordcounts
     *
     * @param array $words
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_wordcounts($words) {
        $total = count($words);
        if ($this->vocab->is_demo()) {
            $completed = rand(0, $total * 0.9);
            $inprogress = rand(0, ($total - $completed) * 0.9);
        } else {
            $completed = 0;
            $inprogress = 0;
        }
        $notstarted = ($total - $completed - $inprogress);
        return array($total, $completed, $inprogress, $notstarted);
    }

    /**
     * svg_fill_patterns
     *
     * @return xxx
     * @todo Finish documenting this function
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
     * @todo Finish documenting this function
     */
    public function svg_fill_pattern($id, $width, $height, $fillcolor, $d, $strokecolor, $strokewidth) {
        $output = '';
        if ($fillcolor) {
            $output .= html_writer::tag('rect', '', array(
                'width' => $width,
                'height' => $height,
                'fill' => $fillcolor
            ));
        }
        if ($strokecolor) {
            $output .= html_writer::tag('path', '', array(
                'd' => $d,
                'stroke' => $strokecolor,
                'stroke-width' => $strokewidth
            ));
        }
        if ($output) {
            $output = html_writer::tag('pattern', $output, array(
                'id' => $id,
                'width' => $width,
                'height' => $height,
                'patternUnits' => 'userSpaceOnUse'
            ));
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
        // L: coordinate of the end point of a straight line (some point on the edge of the pie)
        // A: the radii length, flags and end point of an arc
        // Z: return to start point (M)

        // Cache the number of radians in a fullcircle.
        $fullcircleradians = deg2rad(360); // = 2 * pi() radians

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
                    // "rotation" is always 0, and "sweep" is always 1.
                    // "largearc" is set to 1 if arc covers more than half the chart.
                    $largearc = ($value < ($total / 2) ? 0 : 1);
                    $a = "$radius,$radius 0 $largearc 1 $x,$y";
                }

                // Build the path d(efinition) and add it to the tag parameters.
                $d = "M$center L$l A$a L$center Z";
                $p = array('d' => $d, 'fill' => $colors[$i]);

                $output .= html_writer::tag('path', '', array_merge($p, $params));
            }
        }

        return $output;
    }

    public function pie_graph_key($xoffset, $xspace, $yoffset, $yspace,
        $size, $colors, $delimiter, $texts, $textparams, $rectparams) {

        $p = array('x' => $xoffset, 'y' => 0,
                   'width' => $size, 'height' => $size);
        $rectparams = array_merge($p, $rectparams);

        $p = array('x' => $xoffset + $size + $xspace, 'y' => 0);
        $textparams = array_merge($p, $textparams);

        $output = '';

        // Split each text into two lines, splitting at the delimiter.
        if ($delimiter) {
            foreach ($texts as $t => $text) {
                $lines = array_map('trim', explode($delimiter, $text, 2));
                foreach ($lines as $l => $line) {
                    $p = array('x' => $textparams['x'],
                               'dy' => ($l * 1.2).'em');
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
     * view_wordlist
     *
     * @param array $words.
     * @return string an HTML string.
     */
    public function view_wordlist($words) {
        $output = '';

        $wordlist = array();
        foreach ($words as $word) {
            $wordlist[] = html_writer::tag('li', $word, array('class' => 'my-0 py-0 vocab-wordlist-word'));
        }

        $title = $this->vocab->get_string('wordlistcontainingnwords', count($wordlist));
        $output .= html_writer::tag('h4', $title);

        if ($wordlist = implode('', $wordlist)) {
            $params = array('class' => 'bg-white border rounded py-1 px-2 my-0 list-unstyled vocab-wordlist-words');
            $output .= html_writer::tag('ul', $wordlist, $params);
        }

        $params = array('class' => 'bg-light border rounded mt-1 mr-2 p-2 float-md-left vocab-wordlist');
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
            $textcolor = ($i < ($countgames / 2) ? 'black' : 'white');
            $output .= $this->game_button($game, $gamecolors[$i], $textcolor);
        }

        $params = array('class' => 'clearfix vocab-games');
        return html_writer::tag('div', $output, $params);
    }

    /**
     * get_games
     *
     * @return string an HTML string.
     */
    public function get_games() {
        $games = array();
        if ($this->vocab->is_demo()) {
            for ($i = 0; $i < rand(1, 20); $i++) {
                $games[] = (object)array(
                    'name' => "vocabgame_$i",
                    'label' => 'Vocabulary game '.($i + 1),
                    'url' => '#'
                );
            }
        } else {
            // Get the list of games available for this Vocab activity.
            // Each game can be hidden or shown in any Vocab activity,
            // and furthermore may have it's own settings to add into
            // the module settings page.
            // Each game can define its own icon (as an svg)
            // vocab_games table looks like this:
            // id name siteenabled
            // vocab_game_instances table looks like this:
            // vocabid gameid enabled configdata
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
     * @todo Finish documenting this function
     */
    public function game_button($game, $gamecolor='', $textcolor='') {
        // see "single_button" method in "lib/outputrenderers.php".
        // single_button($url, $label, $method='post', array $options=null)
        $style = array();
        if ($gamecolor) {
            $style[] = "background-color: $gamecolor;";
        }
        if ($textcolor) {
            $style[] = "color: $textcolor;";
        }
        $params = array();
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
     * @param integer number of colors to return.
     * @return array containing "n" number of RGB colors.
     */
    public function get_colors($startcolor, $endcolor, $n) {

        if ($n == 0) {
            return array();
        }
        if ($n == 1) {
            return array($startcolor);
        }
        if ($n == 2) {
            return array($startcolor, $endcolor);
        }

        $r1 = hexdec(substr($startcolor, 1, 2));
        $g1 = hexdec(substr($startcolor, 3, 2));
        $b1 = hexdec(substr($startcolor, 5, 2));

        $r2 = hexdec(substr($endcolor, 1, 2));
        $g2 = hexdec(substr($endcolor, 3, 2));
        $b2 = hexdec(substr($endcolor, 5, 2));

        $step_r = ($r2 - $r1) / ($n - 1);
        $step_g = ($g2 - $g1) / ($n - 1);
        $step_b = ($b2 - $b1) / ($n - 1);

        $colors = array();
        for ($i = 0; $i < $n; $i++) {
            // Calculate new RGB values and convert to hex.
            $r = round($r1 + $i * $step_r);
            $g = round($g1 + $i * $step_g);
            $b = round($b1 + $i * $step_b);
            $colors[] = sprintf("#%02x%02x%02x", $r, $g, $b);
        }
        return $colors;
    }

}
