<?php
/**
 * Collapsed Topics Information
 *
 * A topic based format that solves the issue of the 'Scroll of Death' when a course has many topics. All topics
 * except zero have a toggle that displays that topic. One or more topics can be displayed at any given time.
 * Toggles are persistent on a per browser session per course basis but can be made to persist longer by a small
 * code change. Full installation instructions, code adaptions and credits are included in the 'Readme.txt' file.
 *
 * @package    course/format
 * @subpackage topcoll
 * @version    See the value of '$plugin->version' in version.php.
 * @copyright  &copy; 2009-onwards G J Barnard in respect to modifications of standard topics format.
 * @author     G J Barnard - gjbarnard at gmail dot com and {@link http://moodle.org/user/profile.php?id=442195}
 * @link       http://docs.moodle.org/en/Collapsed_Topics_course_format
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Polish Translation of Collapsed Topics Course Format
// Polskie tlumaczenie Collapsed golfowe Tematy Format

// Used by the Moodle Core for identifing the format and displaying in the list of formats for a course in its settings.
// Uzywane przez Moodle Core dla identifing format i wyswietlanie w liscie formatów na kurs w jego ustawienia.
$string['nametopcoll']='Zwiniety Tematy';
$string['formattopcoll']='Zwiniety Tematy';
$string['pluginname'] = 'Zwiniety Tematy';

// Used in format.php
// Uzywane w format.php
$string['topcolltoggle']='Dzwignia kolankowa';
$string['topcolltogglewidth']='width: 34px;';

// Toggle all - Moodle Tracker CONTRIB-3190
$string['topcollall']='wszystkie przełączniki.';
$string['topcollopened']='Otwórz';
$string['topcollclosed']='Zamknij';

// Everything below is pending translation...
// Layout enhancement - Moodle Tracker CONTRIB-3378
$string['setlayout'] = 'Set layout';
$string['setlayout_default'] = 'Default';
$string['setlayout_no_toggle_section_x'] = 'No toggle section x';
$string['setlayout_no_section_no'] = 'No section number';
$string['setlayout_no_toggle_section_x_section_no'] = 'No toggle section x and section number';
$string['setlayout_no_toggle_word'] = 'No toggle word';
$string['setlayout_no_toggle_word_toggle_section_x'] = 'No toggle word and toggle section x';
$string['setlayout_no_toggle_word_toggle_section_x_section_no'] = 'No toggle word, toggle section x and section number';
$string['setlayoutelements'] = 'Set elements';
$string['setlayoutstructure'] = 'Set structure';
$string['setlayoutstructuretopic']='Temat';
$string['setlayoutstructureweek']='Tydzień';
$string['setlayoutstructurelatweekfirst']='Latest Week First';
$string['setlayoutstructurecurrenttopicfirst']='Current Topic First';
?>