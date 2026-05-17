<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: send nudge emails to inactive gamification users.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class send_nudges extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_send_nudges', 'local_leducon');
    }

    public function execute(): void {
        global $DB, $CFG;

        if (!get_config('local_leducon', 'gamify_enabled')) {
            return;
        }
        if (!get_config('local_leducon', 'nudge_enabled')) {
            return;
        }

        $inactive_days = (int)(get_config('local_leducon', 'nudge_inactive_days') ?: 7);

        // Use the lowest active 'inactive_days' alert-rule threshold if one exists,
        // so analytics alerts and gamification nudges share the same definition.
        $ap_thresh = $DB->get_field_sql(
            "SELECT MIN(threshold)
               FROM {local_leducon_alertrules}
              WHERE condition_type = 'inactive_days'
                AND active = 1
                AND threshold > 0"
        );
        if ($ap_thresh !== false && $ap_thresh !== null && (int)$ap_thresh > 0) {
            $inactive_days = (int)$ap_thresh;
        }

        $cutoff = time() - ($inactive_days * DAYSECS);

        $sql = "SELECT gu.userid, u.firstname, u.lastname, u.email, u.lang, u.mailformat
                  FROM {local_leducon_xp_users} gu
                  JOIN {user} u ON u.id = gu.userid
                 WHERE u.deleted  = 0
                   AND u.suspended = 0
                   AND (gu.last_activity IS NULL OR gu.last_activity < :cutoff)";
        $users = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

        $site      = get_site();
        $noreply   = \core_user::get_noreply_user();
        $pluginurl = new \moodle_url('/local/leducon/gamify/index.php');

        foreach ($users as $u) {
            $subject = get_string('nudge_subject', 'local_leducon', format_string($site->fullname));
            $body    = get_string('nudge_body', 'local_leducon', [
                'firstname' => $u->firstname,
                'siteurl'   => $pluginurl->out(false),
                'sitename'  => format_string($site->fullname),
            ]);
            email_to_user($u, $noreply, $subject, $body);
        }
    }
}
