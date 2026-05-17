<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: send weekly team XP digest to managers.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class weekly_digest extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_weekly_digest', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        if (!get_config('local_leducon', 'gamify_enabled')) {
            return;
        }

        $week_ago  = time() - WEEKSECS;
        $site      = get_site();
        $noreply   = \core_user::get_noreply_user();
        $pluginurl = new \moodle_url('/local/leducon/manager/team.php');

        // All managers who have cohort assignments.
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lang, u.mailformat
                  FROM {local_leducon_team_managers} m
                  JOIN {user} u ON u.id = m.userid
                 WHERE u.deleted = 0 AND u.suspended = 0";
        $managers = $DB->get_records_sql($sql);

        foreach ($managers as $mgr) {
            $cohort_ids = $DB->get_fieldset_select(
                'local_leducon_team_managers', 'cohortid',
                'userid = :uid', ['uid' => $mgr->id]
            );
            if (empty($cohort_ids)) {
                continue;
            }

            [$in_sql, $params] = $DB->get_in_or_equal($cohort_ids, SQL_PARAMS_NAMED);
            $params['since'] = $week_ago;

            $team_xp = $DB->get_field_sql(
                "SELECT COALESCE(SUM(xl.points), 0)
                   FROM {local_leducon_xp_log} xl
                  WHERE xl.userid IN (
                        SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid {$in_sql}
                  )
                  AND xl.timecreated >= :since
                  AND xl.points > 0",
                $params
            );

            $subject = get_string('digest_subject', 'local_leducon', format_string($site->fullname));
            $body    = get_string('digest_body', 'local_leducon', [
                'firstname' => $mgr->firstname,
                'team_xp'   => number_format((int)$team_xp),
                'teamurl'   => $pluginurl->out(false),
                'sitename'  => format_string($site->fullname),
            ]);
            email_to_user($mgr, $noreply, $subject, $body);
        }
    }
}
