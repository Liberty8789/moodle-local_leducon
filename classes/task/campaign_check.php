<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: auto-disable expired XP campaigns.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class campaign_check extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_campaign_check', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        if (!get_config('local_leducon', 'gamify_enabled')) {
            return;
        }

        // Auto-disable campaigns whose end date has passed.
        $DB->execute(
            "UPDATE {local_leducon_campaigns} SET enabled = 0 WHERE enabled = 1 AND enddate < :now",
            ['now' => time()]
        );
    }
}
