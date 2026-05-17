<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\gamify;

defined('MOODLE_INTERNAL') || die();

/**
 * Campaign Manager — retrieves active/visible XP campaigns for users.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_manager {

    /**
     * Returns all active campaigns visible to the user, each with ->courses array.
     */
    public static function get_active_campaigns(int $userid): array {
        global $DB;

        $now = time();
        $sql = "SELECT c.*
                  FROM {local_leducon_campaigns} c
                 WHERE c.enabled = 1
                   AND c.startdate <= :now1
                   AND c.enddate   >= :now2
                   AND (c.cohortid IS NULL
                        OR EXISTS (
                            SELECT 1 FROM {cohort_members} cm
                             WHERE cm.cohortid = c.cohortid
                               AND cm.userid   = :uid
                        ))
              ORDER BY c.enddate ASC";
        $campaigns = $DB->get_records_sql($sql, ['now1' => $now, 'now2' => $now, 'uid' => $userid]);

        foreach ($campaigns as $camp) {
            $camp->courses = $DB->get_records_sql(
                "SELECT co.id, co.fullname
                   FROM {course} co
                   JOIN {local_leducon_camp_courses} cc ON cc.courseid = co.id
                  WHERE cc.campaignid = :cid",
                ['cid' => $camp->id]
            );
        }

        return $campaigns;
    }

    /**
     * Returns all campaigns (active, upcoming, ended) for the campaigns page.
     */
    public static function get_all_for_user(int $userid): array {
        global $DB;

        $now = time();
        $sql = "SELECT c.*
                  FROM {local_leducon_campaigns} c
                 WHERE c.enabled = 1
                   AND (c.cohortid IS NULL
                        OR EXISTS (
                            SELECT 1 FROM {cohort_members} cm
                             WHERE cm.cohortid = c.cohortid
                               AND cm.userid   = :uid
                        ))
              ORDER BY c.startdate DESC";
        $campaigns = $DB->get_records_sql($sql, ['uid' => $userid]);

        foreach ($campaigns as $camp) {
            $camp->status = self::get_status((int)$camp->startdate, (int)$camp->enddate, $now);
            $camp->courses = $DB->get_records_sql(
                "SELECT co.id, co.fullname
                   FROM {course} co
                   JOIN {local_leducon_camp_courses} cc ON cc.courseid = co.id
                  WHERE cc.campaignid = :cid",
                ['cid' => $camp->id]
            );
        }

        return $campaigns;
    }

    private static function get_status(int $start, int $end, int $now): string {
        if ($now < $start) {
            return 'upcoming';
        }
        if ($now > $end) {
            return 'ended';
        }
        return 'active';
    }
}
