<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 1999 onwards  Martin Dougiamas  http://moodle.com       //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
* Index page for the visual grade book report.
*/

require_once '../../../config.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/visual/lib.php';

$courseid = required_param('id');
$visid = optional_param('visid');

/// basic access checks
if(isset($DB) && !is_null($DB)) {
    $course = $DB->get_record('course', array('id' => $courseid));
} else {
    $course = get_record('course', 'id', $courseid);
}
if (!$course) {
        print_error('nocourseid');
}
require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('gradereport/visual:view', $context);

/// get tracking object
$gpr = new grade_plugin_return(array('type'=>'report', 'plugin'=>'visual', 'courseid'=>$courseid));

/// get the visual report object.
$report = new grade_report_visual($courseid, $gpr, $context, $visid);

/// make sure the user is allowed to view the selected visualization.
require_capability(grade_report_visual::get_visualization($report->visid, $context)->capability, $context);

/// last selected report session tracking
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'visual';

/// Build navigation
$strgrades  = get_string('grades');
$reportname = get_string('modulename', 'gradereport_visual');
$navigation = grade_build_nav(__FILE__, $reportname, $courseid);

/// Print header
print_header_simple($strgrades.': '.$reportname, ': '.$strgrades, $navigation, '', '', true, null, navmenu($course), false, null);

/// Print the plugin selector at the top
print_grade_plugin_selector($courseid, 'report', 'stats');

/// Add tabs
$currenttab = 'visualreport';
require('tabs.php');

/// Make and output the html for the report.
$report->visualization_selector();
$report->adapt_html();

/// Print footer
print_footer($course);
?>