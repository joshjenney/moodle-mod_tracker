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
 * Version details.
 *
 * @package     mod_tracker
 * @category    mod
 * @author      Clifford Tham till 1.8
 * @author      Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright   2009 onwards Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2026072700;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2022112800;  // Moodle 4.1 (Build: 20221128).
$plugin->component = 'mod_tracker';   // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '4.0.0 (Build 2023060400)';
$plugin->supported = [401, 405];

// N2NCU 2026-08-03: the local_vflibs dependency is removed. It existed only for
// jqplot charting in the reports view, which now uses \core\chart_line. That was
// the last consumer of local_vflibs on this site, so the plugin can be
// uninstalled - 214MB, 68MB of which is Windows .exe and .dll under xpdf.
//
// Deliberately NOT bumping $plugin->version: nothing here needs a database
// upgrade, and a bump would make every environment run an upgrade step for a
// change that is purely code. Moodle reads this file live for dependency checks,
// so the uninstall is unblocked without one.
//
// See TECH_DEBT 6. The vendored tree itself was never touched, which was the
// whole point - trimming it would have diverged from upstream forever.

// Non Moodle attributes.
$plugin->codeincrement = '4.0.0009';
$plugin->privacy = 'dualrelease';
$plugin->prolocations = array(
    'classes/trackercategorytype/autourl',
    'classes/trackercategorytype/constant',
    'classes/trackercategorytype/constant',
    'classes/trackercategorytype/checkboxhoriz',
    'classes/trackercategorytype/radiohoriz',
    'classes/trackercategorytype/captcha',
);