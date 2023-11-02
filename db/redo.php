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
 * mod/vocab/db/redo.php
 *
 * @package    mod
 * @subpackage vocab
 * @copyright  2023 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.11
 */

/** Include required files */
require_once('../../../config.php');

require_login($SITE);
require_capability('moodle/site:config', context_system::instance());

$vocab = \mod_vocab\activity::create();

// set page url
$PAGE->set_url(new moodle_url('/mod/vocab/redo.php'));

// set page title
$title = get_string('pluginname', 'mod_vocab');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer($vocab->plugin);
$renderer->attach_activity($vocab);

echo $renderer->header();
echo $renderer->box_start();

$dateformat = 'jS M Y'; // for date() function

if ($version = optional_param('version', 0, PARAM_INT)) {

    // format version
    if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})/', "$version", $match)) {
        $yy = $match[1];
        $mm = $match[2];
        $dd = $match[3];
        $vv = intval($match[4]);
        $text = date($dateformat, mktime(0,0,0,$mm,$dd,$yy)).($vv==0 ? '' : " ($vv)");
    } else {
        $text = ''; // shouldn't happen !!
    }

    // reset the plugin version
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('config_plugins')) {
        // Moodle >= 2.6
        $params = array('plugin' => 'mod_vocab', 'name' => 'version');
        $DB->set_field('config_plugins', 'value', $version - 1, $params);
        // force Moodle to refetch versions
        if (isset($CFG->allversionshash)) {
            unset_config('allversionshash');
        }
    }

    // report
    echo html_writer::tag('p', "Vocab activity module version set to just before $version - $text");

    // link to upgrade page
    $href = new moodle_url('/admin/index.php', array('confirmplugincheck' => 1, 'cache'=>0));
    echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

} else { // no $version given, so offer a form to select $version

    // start form
    echo html_writer::start_tag('form', array('action' => $FULLME, 'method' => 'post'));
    echo html_writer::start_tag('div');

    $versions = array();

    // extract and format the current version
    $contents = file_get_contents($CFG->dirroot.'/mod/vocab/version.php');
    if (preg_match('/^\$plugin->version *= *(\d{4})(\d{2})(\d{2})(\d{2});/m', $contents, $matches)) {
        $yy = $matches[1];
        $mm = $matches[2];
        $dd = $matches[3];
        $vv = $matches[4];
        $version = "$yy$mm$dd$vv";
        $versions[$version] = date($dateformat, mktime(0,0,0,$mm,$dd,$yy)).(intval($vv)==0 ? '' : " ($vv)");
    }

    // extract and format versions from upgrade script
    $contents = file_get_contents($CFG->dirroot.'/mod/vocab/db/upgrade.php');
    preg_match_all('/(?<=\$newversion = )(\d{4})(\d{2})(\d{2})(\d{2})(?=;)/', $contents, $matches);
    $i_max = count($matches[0]);
    for ($i=0; $i<$i_max; $i++) {
        $version = $matches[0][$i];
        $yy = $matches[1][$i];
        $mm = $matches[2][$i];
        $dd = $matches[3][$i];
        $vv = $matches[4][$i];
        $versions[$version] = date($dateformat, mktime(0,0,0,$mm,$dd,$yy)).(intval($vv)==0 ? '' : " ($vv)");
    }
    krsort($versions);

    // add form elements
    echo get_string('version').' '.html_writer::select($versions, 'version').' ';
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));

    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

echo $renderer->box_end();
echo $renderer->footer();
