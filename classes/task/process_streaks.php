<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: process daily streak decay.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class process_streaks extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_process_streaks', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        if (!get_config('local_leducon', 'gamify_enabled')) {
            return;
        }

        $users = $DB->get_records('local_leducon_xp_users', null, '', 'userid');
        foreach ($users as $u) {
            \local_leducon\gamify\streak_tracker::process_daily((int)$u->userid);
        }
    }
}
