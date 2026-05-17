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
 * XP Engine — awards, deduplicates and deducts experience points.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xp_engine {

    /**
     * Award XP for an event, enforcing deduplication rules.
     * Returns the points actually awarded (0 if deduplicated).
     */
    public static function award(int $userid, string $eventtype, int $contextid = 0, string $note = ''): int {
        global $DB;

        if (!get_config('local_leducon', 'gamify_enabled')) {
            return 0;
        }

        if (self::is_duplicate($userid, $eventtype, $contextid)) {
            return 0;
        }

        $base_points = self::get_event_points($eventtype);
        if ($base_points <= 0) {
            return 0;
        }

        $multiplier = self::get_campaign_multiplier($userid, $contextid);
        $points     = (int)round($base_points * $multiplier);

        $DB->insert_record('local_leducon_xp_log', (object)[
            'userid'      => $userid,
            'points'      => $points,
            'eventtype'   => $eventtype,
            'contextid'   => $contextid ?: null,
            'campaignid'  => null,
            'note'        => $note,
            'timecreated' => time(),
        ]);

        self::update_user_total($userid, $points);

        achievement_manager::check_achievements($userid);
        level_manager::check_level_up($userid);

        return $points;
    }

    /**
     * Returns true if this event should be skipped for this user.
     * Rules:
     *  - course_completion / path_completion / badge_awarded: all-time per contextid
     *  - quiz_pass_first: all-time per contextid (first-pass bonus)
     *  - quiz_attempt / assign_ontime / assign_submitted: once per contextid per day
     *  - forum_post: daily cap (max-per-day setting)
     *  - spotlight_received: no dedup (multiple spotlights allowed)
     *  - streak_milestone / level_up: handled by their own logic
     */
    public static function is_duplicate(int $userid, string $eventtype, int $contextid): bool {
        global $DB;

        $alltime_once = ['course_completion', 'path_completion', 'badge_awarded', 'quiz_pass_first'];
        $daily_once   = ['quiz_attempt', 'assign_ontime', 'assign_submitted'];
        $no_dedup     = ['spotlight_received', 'streak_milestone', 'level_up', 'manual'];

        if (in_array($eventtype, $no_dedup)) {
            return false;
        }

        if (in_array($eventtype, $alltime_once)) {
            return $DB->record_exists('local_leducon_xp_log', [
                'userid'    => $userid,
                'eventtype' => $eventtype,
                'contextid' => $contextid,
            ]);
        }

        if (in_array($eventtype, $daily_once)) {
            $daystart = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
            return $DB->record_exists_select(
                'local_leducon_xp_log',
                'userid = :uid AND eventtype = :evt AND contextid = :ctx AND timecreated >= :ds',
                ['uid' => $userid, 'evt' => $eventtype, 'ctx' => $contextid, 'ds' => $daystart]
            );
        }

        if ($eventtype === 'forum_post') {
            $maxday   = (int)(get_config('local_leducon', 'xp_forum_post_maxday') ?: 3);
            $daystart = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
            $count    = $DB->count_records_select(
                'local_leducon_xp_log',
                'userid = :uid AND eventtype = :evt AND timecreated >= :ds',
                ['uid' => $userid, 'evt' => 'forum_post', 'ds' => $daystart]
            );
            return $count >= $maxday;
        }

        return false;
    }

    public static function get_event_points(string $eventtype): int {
        $cfg = get_config('local_leducon', 'xp_' . $eventtype);
        return $cfg !== false ? (int)$cfg : 0;
    }

    /**
     * Returns the highest active campaign multiplier for this user + course.
     */
    public static function get_campaign_multiplier(int $userid, int $contextid): float {
        global $DB;

        if (!$contextid) {
            return 1.0;
        }

        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return 1.0;
        }

        // Resolve courseid from context.
        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;
        } elseif ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $context->instanceid);
            $courseid = $cm ? (int)$cm->course : 0;
        } else {
            return 1.0;
        }

        if (!$courseid) {
            return 1.0;
        }

        $now = time();
        // Active campaigns that include this course.
        $sql = "SELECT c.multiplier, c.cohortid
                  FROM {local_leducon_campaigns} c
                  JOIN {local_leducon_camp_courses} cc ON cc.campaignid = c.id
                 WHERE c.enabled = 1
                   AND c.startdate <= :now1
                   AND c.enddate   >= :now2
                   AND cc.courseid = :cid
              ORDER BY c.multiplier DESC";
        $campaigns = $DB->get_records_sql($sql, ['now1' => $now, 'now2' => $now, 'cid' => $courseid]);

        foreach ($campaigns as $camp) {
            if (!$camp->cohortid) {
                return (float)$camp->multiplier;
            }
            if ($DB->record_exists('cohort_members', ['cohortid' => $camp->cohortid, 'userid' => $userid])) {
                return (float)$camp->multiplier;
            }
        }

        return 1.0;
    }

    public static function update_user_total(int $userid, int $delta): void {
        global $DB;

        $rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid]);
        $now = time();
        if ($rec) {
            $rec->total_xp      += $delta;
            $rec->last_activity  = $now;
            $rec->timemodified   = $now;
            $DB->update_record('local_leducon_xp_users', $rec);
        } else {
            $DB->insert_record('local_leducon_xp_users', (object)[
                'userid'        => $userid,
                'total_xp'      => max(0, $delta),
                'levelid'       => 0,
                'streak_days'   => 0,
                'last_activity' => $now,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ]);
        }
    }

    /**
     * Deduct XP (for redemptions). Floors at 0.
     */
    public static function deduct(int $userid, int $points, string $note = ''): void {
        global $DB;

        $rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid]);
        if (!$rec) {
            return;
        }
        $new_xp = max(0, $rec->total_xp - $points);
        $DB->set_field('local_leducon_xp_users', 'total_xp', $new_xp, ['userid' => $userid]);

        $DB->insert_record('local_leducon_xp_log', (object)[
            'userid'      => $userid,
            'points'      => -$points,
            'eventtype'   => 'redemption',
            'contextid'   => null,
            'note'        => $note,
            'timecreated' => time(),
        ]);
    }
}
