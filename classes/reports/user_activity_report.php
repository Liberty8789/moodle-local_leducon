<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * User Activity report — deprecated, delegates to login_activity_report.
 *
 * Kept for backward compatibility. Will be removed in a future release.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @deprecated Since 2.1.0. Use login_activity_report instead.
 */
class user_activity_report extends login_activity_report {

    public function get_name(): string {
        return get_string('report_login_activity', 'local_leducon');
    }
}
