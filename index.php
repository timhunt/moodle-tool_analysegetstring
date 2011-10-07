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
 * Use codesniffer to get all the get_string calls.
 *
 * @package    tool
 * @subpackage analysegetstring
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/codechecker/locallib.php');

$path = optional_param('path', '', PARAM_PATH);
if ($path) {
    $pageparams = array('path' => $path);
} else {
    $pageparams = array();
}

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

admin_externalpage_setup('tool_analysegetstring', '', $pageparams);

$baseurl = new moodle_url('/admin/tool/analysegetstring/index.php');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'tool_analysegetstring'));

raise_memory_limit(MEMORY_HUGE);
set_time_limit(300);

$lastcallanalysis = get_config('tool_analysegetstring', 'lastcallanalysis');
$lastdefinitionsanalysis = get_config('tool_analysegetstring', 'lastdefinitionsanalysis');

if (optional_param('dodefinitions', false, PARAM_BOOL) && data_submitted()) {
    require_sesskey();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('analysingdefinitions', 'tool_analysegetstring'));
    echo html_writer::start_tag('ul');

    $DB->delete_records('tool_analysegetstring_string');

    $sm = get_string_manager();
    foreach (get_plugin_types() as $type => $notused) {
        foreach (get_plugin_list($type) as $plugin => $notused) {
            $component = $type . '_' . $plugin;
            echo html_writer::tag('li', $component);
            flush();
            foreach ($sm->load_component_strings($component, 'en') as $identifier => $notused) {
                tool_analysegetstring_record_definition($identifier, $component);
            }
        }
    }

    foreach (get_core_subsystems() as $component => $notused) {
        echo html_writer::tag('li', $component);
        flush();
        foreach ($sm->load_component_strings($component, 'en') as $identifier => $notused) {
            tool_analysegetstring_record_definition($identifier, $component);
        }
    }
    set_config('lastdefinitionsanalysis', time(), 'tool_analysegetstring');

    echo html_writer::end_tag('ul');
    echo $OUTPUT->continue_button($baseurl);
    echo $OUTPUT->footer();
    die();
}

if (optional_param('docalls', false, PARAM_BOOL) && data_submitted()) {
    require_sesskey();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('analysingdefinitions', 'tool_analysegetstring'));
    echo html_writer::start_tag('ul');

    $DB->delete_records('tool_analysegetstring_calls');

    $phpcs = new PHP_CodeSniffer();
    $phpcs->setCli(new local_codechecker_codesniffer_cli());
    $phpcs->setIgnorePatterns(local_codesniffer_get_ignores());
    $phpcs->process(local_codechecker_clean_path($CFG->dirroot),
            local_codechecker_clean_path($CFG->dirroot . '/' . $CFG->admin .
                    '/tool/analysegetstring/getstring'));

    set_config('lastcallanalysis', time(), 'tool_analysegetstring');
    echo html_writer::end_tag('ul');
    echo $OUTPUT->continue_button($baseurl);
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();
if ($lastcallanalysis) {
    echo html_writer::tag('p', get_string('lastcallanalysis', 'tool_analysegetstring',
            userdate($lastcallanalysis)));
}
if ($lastdefinitionsanalysis) {
    echo html_writer::tag('p', get_string('lastdefinitionsanalysis', 'tool_analysegetstring',
            userdate($lastdefinitionsanalysis)));
}
echo $OUTPUT->single_button(new moodle_url($baseurl,
        array('docalls' => 1, 'sesskey' => sesskey())),
        get_string('analysecalls', 'tool_analysegetstring'));
echo $OUTPUT->single_button(new moodle_url($baseurl,
        array('dodefinitions' => 1, 'sesskey' => sesskey())),
        get_string('analysedefinitions', 'tool_analysegetstring'));
echo $OUTPUT->footer();


function tool_analysegetstring_new_file($file) {
    global $CFG;
    echo html_writer::tag('li', str_replace($CFG->dirroot . '/', '', $file));
    flush();
    set_time_limit(300);
}

function tool_analysegetstring_record_call($file, $line, $arguments) {
    global $CFG, $DB;

    list($identifier, $component, $a) = array_merge($arguments, array(null, null));

    $call = new stdClass();
    $call->sourcefile = str_replace($CFG->dirroot . '/', '', $file);
    $call->line = $line;
    $call->sourcecomponent = tool_analysegetstring_component_from_file($file);

    if (preg_match('~^[\'"]\w+[\'"]$~', $identifier)) {
        $call->identifier = trim($identifier, '\'"');
    } else {
        $call->identifier = 'EXP: ' . $identifier;
    }

    if (empty($component) || $component == "''" || $component == '""') {
        $call->stringcomponent = 'moodle';

    } else if (preg_match('~^[\'"]\w+[\'"]$~', $component)) {
        list($plugintype, $pluginname) = normalize_component(trim($component, '\'"'));
        if ($plugintype == 'core' and is_null($pluginname)) {
            $component = 'moodle';
        } else {
            $component = $plugintype . '_' . $pluginname;
        }

        $call->stringcomponent = $component;

    } else {
        $call->stringcomponent = 'EXP: ' . $component;
    }

    $call->dollara = $a;

    $DB->insert_record('tool_analysegetstring_calls', $call);
}

function tool_analysegetstring_record_definition($identifier, $component) {
    global $DB;

    $definition = new stdClass();
    $definition->identifier = $identifier;
    $definition->component = $component;

    $DB->insert_record('tool_analysegetstring_string', $definition);
}

function tool_analysegetstring_component_from_file($filepath) {
    static $plugintypes;
    static $ignored = array('CVS', '_vti_cnf', 'simpletest', 'db', 'yui', 'phpunit');

    if (is_null($plugintypes)) {
        $plugintypes = get_plugin_types();
    }

    $matchingprefix = '';
    $matchingtype = '';
    foreach ($plugintypes as $type => $pathprefix) {
        if (strpos($filepath, $pathprefix . '/') === 0 &&
        strlen($pathprefix) > strlen($matchingprefix)) {
            $matchingprefix = $pathprefix;
            $matchingtype = $type;
        }
    }

    if (!$matchingtype) {
        return '';
    }

    list($pluginname) = explode('/', substr($filepath, strlen($matchingprefix) + 1));
    if (in_array($pluginname, $ignored)) {
        return '';
    }
    return $matchingtype . '_' . $pluginname;
}
