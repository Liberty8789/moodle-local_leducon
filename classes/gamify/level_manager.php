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
 * Level Manager — level lookups, progress calculation, level-up detection.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class level_manager {

    /** @var array|null Cached level records, populated on first use. */
    private static $cache = null;

    public static function get_levels(): array {
        global $DB;
        if (self::$cache === null) {
            self::$cache = $DB->get_records('local_leducon_levels', null, 'levelnum ASC');
        }
        return self::$cache;
    }

    /**
     * Return the level record that matches the given total XP.
     */
    public static function get_level_for_points(int $xp): ?\stdClass {
        $current = null;
        foreach (self::get_levels() as $lvl) {
            if ($xp >= $lvl->min_xp) {
                $current = $lvl;
            }
        }
        return $current;
    }

    /**
     * Return the next level above the given XP, or null if already at max.
     */
    public static function get_next_level(int $xp): ?\stdClass {
        // Return the first level whose min_xp is above the user's current XP.
        foreach (self::get_levels() as $lvl) {
            if ($lvl->min_xp > $xp) {
                return $lvl;
            }
        }
        return null; // Already at max level.
    }

    /**
     * Check if user has levelled up since last check; update levelid if so.
     */
    public static function check_level_up(int $userid): bool {
        global $DB;

        $gu = $DB->get_record('local_leducon_xp_users', ['userid' => $userid], 'id, total_xp, levelid');
        if (!$gu) {
            return false;
        }

        $new_level = self::get_level_for_points((int)$gu->total_xp);
        if (!$new_level || (int)$new_level->id === (int)$gu->levelid) {
            return false;
        }

        $DB->set_field('local_leducon_xp_users', 'levelid', $new_level->id, ['userid' => $userid]);

        // Award level-up XP bonus (configurable, default 0).
        $bonus = (int)(get_config('local_leducon', 'xp_level_up') ?: 0);
        if ($bonus > 0) {
            xp_engine::award($userid, 'level_up', 0, 'Level up to ' . $new_level->name);
        }

        return true;
    }

    /**
     * Returns [current_level, next_level, prev_xp] for XP bar rendering.
     */
    public static function get_progress(int $userid): array {
        global $DB;

        $total_xp   = (int)($DB->get_field('local_leducon_xp_users', 'total_xp', ['userid' => $userid]) ?: 0);
        $cur_level  = self::get_level_for_points($total_xp);
        $next_level = self::get_next_level($total_xp);

        return [
            'total_xp'     => $total_xp,
            'cur_level'    => $cur_level,
            'next_level'   => $next_level,
            'prev_xp'      => $cur_level ? (int)$cur_level->min_xp : 0,
            'next_xp'      => $next_level ? (int)$next_level->min_xp : $total_xp,
        ];
    }
}
