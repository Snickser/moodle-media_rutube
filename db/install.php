<?php
// This file is part of the bank paymnts module for Moodle - http://moodle.org/
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
 * media_rutube installer script.
 *
 * @package    media_rutube
 * @copyright  2024 Alex Orlov <snickser@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enabler
 *
 * @return boolean
 */
function xmldb_media_rutube_install() {
    global $CFG;

    // Enable the plugin on installation. It still needs to be configured and enabled for accounts.
    $order = (!empty($CFG->media_plugins_sortorder)) ? explode(',', $CFG->media_plugins_sortorder) : [];
    set_config('media_plugins_sortorder', join(',', array_merge($order, ['rutube'])));
}
