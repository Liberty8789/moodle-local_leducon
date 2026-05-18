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
 * Streak Tracker — daily login streaks with milestone XP bonuses.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class streak_tracker {

    /**
     * Called on user_loggedin. Updates streak and fires milestone XP if warranted.
     */
    public static function record_login(int $userid): void {
        global $DB;

        $rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid]);
        $now = time();

        if (!$rec) {
            // Row created by xp_engine on first award; nothing to do yet.
            return;
        }

        $today     = (int)date('z', $now) + (int)date('Y', $now) * 366; // rough day-of-year key
        $yesterday = (int)date('z', $now - 86400) + (int)date('Y', $now - 86400) * 366;

        if ($rec->last_activity) {
            $last_day = (int)date('z', $rec->last_activity) + (int)date('Y', $rec->last_activity) * 366;
        } else {
            $last_day = 0;
        }

        if ($last_day === $today) {
            // Already logged in today — no change.
            return;
        }

        if ($last_day === $yesterday) {
            $new_streak = (int)$rec->streak_days + 1;
        } else {
            $new_streak = 1;
        }

        $DB->set_field('local_leducon_xp_users', 'streak_days',   $new_streak, ['userid' => $userid]);
        $DB->set_field('local_leducon_xp_users', 'last_activity',  $now,        ['userid' => $userid]);
        $DB->set_field('local_leducon_xp_users', 'timemodified',   $now,        ['userid' => $userid]);

        self::check_milestone_xp($userid, $new_streak);
    }

    /**
     * Scheduled task calls this for every user to decay broken streaks.
     * Returns the new streak value.
     */
    public static function process_daily(int $userid): int {
        global $DB;

        $rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid], 'id, streak_days, last_activity');
        if (!$rec || !$rec->last_activity) {
            return 0;
        }

        $yesterday = mktime(0, 0, 0, (int)date('n'), (int)date('j') - 1, (int)date('Y'));
        if ($rec->last_activity < $yesterday) {
            // Missed yesterday — reset streak.
            $DB->set_field('local_leducon_xp_users', 'streak_days', 0, ['userid' => $userid]);
            return 0;
        }

        return (int)$rec->streak_days;
    }

    private static function check_milestone_xp(int $userid, int $streak): void {
        $milestonestr = get_config('local_leducon', 'streak_milestones') ?: '7,30,90,365';
        $milestones = array_map('intval', array_filter(explode(',', $milestonestr)));
        foreach ($milestones as $days) {
            if ($streak === $days) {
                $bonus = (int)(get_config('local_leducon', 'xp_streak_' . $days) ?: 0);
                if ($bonus > 0) {
                    xp_engine::award($userid, 'streak_milestone', $days, "{$days}-day streak bonus");
                }
                break;
            }
        }
    }
}
