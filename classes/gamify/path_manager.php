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
 * Path Manager — learning path progress, completion checks, XP bonuses.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_manager {

    /**
     * Returns all paths visible to the user (unrestricted + cohort-matched).
     * Each record has an extra ->progress (0-100) and ->completed (bool).
     */
    public static function get_paths_for_user(int $userid): array {
        global $DB;

        $sql = "SELECT p.*
                  FROM {local_leducon_paths} p
                 WHERE p.enabled = 1
                   AND (p.cohortid IS NULL
                        OR EXISTS (
                            SELECT 1 FROM {cohort_members} cm
                             WHERE cm.cohortid = p.cohortid
                               AND cm.userid   = :uid
                        ))
              ORDER BY p.name ASC";
        $paths = $DB->get_records_sql($sql, ['uid' => $userid]);

        foreach ($paths as $path) {
            [$progress, $completed] = self::get_user_path_progress($userid, (int)$path->id);
            $path->progress  = $progress;
            $path->completed = $completed;
        }

        return $paths;
    }

    /**
     * Returns [percent_complete (0-100), is_completed (bool)].
     */
    public static function get_user_path_progress(int $userid, int $pathid): array {
        global $DB;

        $course_ids = $DB->get_fieldset_select(
            'local_leducon_path_courses', 'courseid',
            'pathid = :pid', ['pid' => $pathid]
        );

        if (empty($course_ids)) {
            return [0, false];
        }

        [$in_sql, $params] = $DB->get_in_or_equal($course_ids, SQL_PARAMS_NAMED);
        $params['uid'] = $userid;

        $completed = $DB->count_records_select(
            'course_completions',
            "userid = :uid AND course {$in_sql} AND timecompleted IS NOT NULL",
            $params
        );

        $total   = count($course_ids);
        $percent = (int)round($completed / $total * 100);

        return [$percent, $completed >= $total];
    }

    /**
     * Called when a course is completed. If it finishes a path, award bonus XP.
     */
    public static function check_path_completion(int $userid, int $courseid): void {
        global $DB;

        $path_ids = $DB->get_fieldset_select(
            'local_leducon_path_courses', 'pathid',
            'courseid = :cid', ['cid' => $courseid]
        );

        foreach ($path_ids as $pathid) {
            $path = $DB->get_record('local_leducon_paths', ['id' => $pathid, 'enabled' => 1]);
            if (!$path) {
                continue;
            }

            [, $completed] = self::get_user_path_progress($userid, (int)$pathid);
            if (!$completed) {
                continue;
            }

            // Only award once (dedup via all-time contextid = pathid).
            xp_engine::award($userid, 'path_completion', (int)$pathid, $path->name);
        }
    }
}
