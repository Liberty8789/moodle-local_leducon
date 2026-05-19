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
 * Achievement Manager — checks and awards achievements based on rule criteria.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievement_manager {

    /**
     * Check and award any unlocked achievements for the user.
     */
    public static function check_achievements(int $userid): void {
        global $DB;

        $achievements = $DB->get_records('local_leducon_achievements', ['enabled' => 1], 'sortorder ASC');
        if (!$achievements) {
            return;
        }

        $earned_ids = $DB->get_fieldset_select(
            'local_leducon_user_achiev', 'achievementid',
            'userid = :uid', ['uid' => $userid]
        );
        $earned_set = array_flip($earned_ids);

        foreach ($achievements as $ach) {
            if (isset($earned_set[$ach->id])) {
                continue;
            }
            if (self::rule_met($userid, $ach->rule_type, (int)$ach->rule_value)) {
                self::award_achievement($userid, $ach);
            }
        }
    }

    private static function rule_met(int $userid, string $rule_type, int $rule_value): bool {
        global $DB;

        switch ($rule_type) {
            case 'xp_total':
                $xp = (int)($DB->get_field('local_leducon_xp_users', 'total_xp', ['userid' => $userid]) ?: 0);
                return $xp >= $rule_value;

            case 'completions':
                $count = (int)$DB->count_records_sql(
                    "SELECT COUNT(*) FROM {course_completions} cc
                    LEFT JOIN {grade_items} gi_a ON gi_a.courseid = cc.course AND gi_a.itemtype = 'course'
                    LEFT JOIN {grade_grades} gg_a ON gg_a.itemid = gi_a.id AND gg_a.userid = cc.userid
                    WHERE cc.userid = :uid
                      AND (cc.timecompleted IS NOT NULL
                          OR (gg_a.finalgrade IS NOT NULL AND gi_a.grademax > 0 AND gg_a.finalgrade / gi_a.grademax * 100 >= 100))",
                    ['uid' => $userid]
                );
                return $count >= $rule_value;

            case 'quiz_passes':
                $count = $DB->count_records('local_leducon_xp_log', [
                    'userid'    => $userid,
                    'eventtype' => 'quiz_pass_first',
                ]);
                return $count >= $rule_value;

            case 'streak_days':
                $streak = (int)($DB->get_field('local_leducon_xp_users', 'streak_days', ['userid' => $userid]) ?: 0);
                return $streak >= $rule_value;

            case 'forum_posts':
                $count = $DB->count_records('local_leducon_xp_log', [
                    'userid'    => $userid,
                    'eventtype' => 'forum_post',
                ]);
                return $count >= $rule_value;

            case 'badges_earned':
                $count = $DB->count_records('badge_issued', ['userid' => $userid, 'visible' => 1]);
                return $count >= $rule_value;

            case 'paths_completed':
                $count = $DB->count_records('local_leducon_xp_log', [
                    'userid'    => $userid,
                    'eventtype' => 'path_completion',
                ]);
                return $count >= $rule_value;

            case 'spotlights_received':
                $count = $DB->count_records('local_leducon_recognition', [
                    'recipientid'  => $userid,
                    'is_spotlight' => 1,
                ]);
                return $count >= $rule_value;
        }

        return false;
    }

    private static function award_achievement(int $userid, \stdClass $ach): void {
        global $DB;

        $DB->insert_record('local_leducon_user_achiev', (object)[
            'userid'        => $userid,
            'achievementid' => $ach->id,
            'timecreated'   => time(),
        ]);

        if ((int)$ach->xp_bonus > 0) {
            xp_engine::award($userid, 'achievement_bonus', (int)$ach->id, $ach->name);
        }
    }

    /**
     * Return all achievements with earned status for a user.
     */
    public static function get_for_user(int $userid): array {
        global $DB;

        $all = $DB->get_records('local_leducon_achievements', null, 'sortorder ASC');
        $earned_ids = $DB->get_fieldset_select(
            'local_leducon_user_achiev', 'achievementid',
            'userid = :uid', ['uid' => $userid]
        );
        $earned_set = array_flip($earned_ids);

        $result = [];
        foreach ($all as $ach) {
            $ach->earned = isset($earned_set[$ach->id]);
            $result[]    = $ach;
        }
        return $result;
    }
}
