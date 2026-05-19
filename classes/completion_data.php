<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Unified completion data provider.
 *
 * Provides a single API for getting accurate completion data across all courses,
 * regardless of whether Moodle's built-in completion tracking is enabled.
 *
 * - When completion tracking is ON: uses Moodle's completion_info / core_completion\progress API.
 * - When completion tracking is OFF: uses multi-signal detection (grade, activities, SCORM, quizzes).
 *
 * This replaces the need for raw SQL with grade_items/grade_grades JOINs scattered
 * across 25+ files. Report classes and analytics pages should use this instead.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/lib/completionlib.php');

class completion_data {

    /**
     * Get completion status for a single user in a single course.
     *
     * @param int $userid
     * @param int $courseid
     * @return object {completed: bool, progress: float|null, timecompleted: int|null, source: string}
     */
    public static function get_user_completion(int $userid, int $courseid): object {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return self::empty_result();
        }

        // Check if Moodle already has a timecompleted record.
        $cc = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $courseid,
        ]);
        if ($cc && !empty($cc->timecompleted)) {
            // Already marked complete in Moodle — trust it.
            $progress = self::get_activity_progress($course, $userid);
            return (object)[
                'completed'      => true,
                'progress'       => $progress ?? 100.0,
                'timecompleted'  => (int)$cc->timecompleted,
                'source'         => 'moodle_completion',
            ];
        }

        // Try Moodle's completion API first (if tracking is enabled).
        if (!empty($course->enablecompletion)) {
            $info = new \completion_info($course);
            if ($info->is_enabled()) {
                $progress = self::get_activity_progress($course, $userid);
                return (object)[
                    'completed'      => false,
                    'progress'       => $progress,
                    'timecompleted'  => null,
                    'source'         => 'moodle_tracking',
                ];
            }
        }

        // Completion tracking is OFF — use multi-signal detection.
        return self::detect_completion($userid, $courseid, $course);
    }

    /**
     * Get completion data for ALL enrolled users in a course.
     * Optimised for report pages that need bulk data.
     *
     * @param int $courseid
     * @return array userid => {completed, progress, timecompleted, source}
     */
    public static function get_course_completions(int $courseid): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return [];
        }

        // Get all enrolled users.
        $users = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :cid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0",
            ['cid' => $courseid]
        );

        // Bulk-load existing course_completions.
        $completions = $DB->get_records('course_completions', ['course' => $courseid], '', 'userid, timecompleted');

        // If Moodle completion is enabled, bulk-load activity progress.
        $activityprogress = [];
        if (!empty($course->enablecompletion)) {
            $activityprogress = self::get_bulk_activity_progress($course, array_keys($users));
        }

        $results = [];
        foreach ($users as $u) {
            $uid = (int)$u->userid;

            // Already completed in Moodle?
            if (isset($completions[$uid]) && !empty($completions[$uid]->timecompleted)) {
                $results[$uid] = (object)[
                    'completed'      => true,
                    'progress'       => $activityprogress[$uid] ?? 100.0,
                    'timecompleted'  => (int)$completions[$uid]->timecompleted,
                    'source'         => 'moodle_completion',
                ];
                continue;
            }

            // Moodle tracking is on but not yet complete?
            if (!empty($course->enablecompletion)) {
                $results[$uid] = (object)[
                    'completed'      => false,
                    'progress'       => $activityprogress[$uid] ?? 0.0,
                    'timecompleted'  => null,
                    'source'         => 'moodle_tracking',
                ];
                continue;
            }

            // Tracking OFF — detect via signals.
            $results[$uid] = self::detect_completion($uid, $courseid, $course);
        }

        return $results;
    }

    /**
     * Get completion counts for a course (efficient aggregate).
     *
     * @param int $courseid
     * @return object {enrolled: int, completed: int, inprogress: int, notstarted: int, completion_rate: float}
     */
    public static function get_course_stats(int $courseid): object {
        $data = self::get_course_completions($courseid);

        $enrolled = count($data);
        $completed = 0;
        $inprogress = 0;

        foreach ($data as $d) {
            if ($d->completed) {
                $completed++;
            } elseif ($d->progress !== null && $d->progress > 0) {
                $inprogress++;
            }
        }

        return (object)[
            'enrolled'        => $enrolled,
            'completed'       => $completed,
            'inprogress'      => $inprogress,
            'notstarted'      => max(0, $enrolled - $completed - $inprogress),
            'completion_rate'  => $enrolled > 0 ? round($completed / $enrolled * 100, 1) : 0.0,
        ];
    }

    /**
     * Bulk: get completion status for one user across multiple courses.
     * Used by My Report, dashboard, etc.
     *
     * @param int $userid
     * @param array $courseids
     * @return array courseid => {completed, progress, timecompleted, source}
     */
    public static function get_user_courses(int $userid, array $courseids): array {
        $results = [];
        foreach ($courseids as $cid) {
            $results[(int)$cid] = self::get_user_completion($userid, (int)$cid);
        }
        return $results;
    }

    /**
     * Global KPI: total completions across all courses, optionally filtered by date.
     *
     * @param int $fromtime  0 = all time
     * @param int $totime    0 = now
     * @return int
     */
    public static function count_completions(int $fromtime = 0, int $totime = 0): int {
        global $DB;

        $totime = $totime ?: time();
        $params = [];
        $where = 'cc.timecompleted IS NOT NULL';

        if ($fromtime > 0) {
            $where .= ' AND cc.timecompleted >= :from';
            $params['from'] = $fromtime;
        }
        if ($totime > 0) {
            $where .= ' AND cc.timecompleted <= :to';
            $params['to'] = $totime;
        }

        // Count from course_completions (includes both Moodle-native and our synced records).
        // Since completion_sync and the event observer write to this table,
        // all signals are already captured here.
        return (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_completions} cc WHERE {$where}",
            $params
        );
    }

    /**
     * Global KPI: total completion rate (completed / enrolled).
     *
     * @return float
     */
    public static function get_overall_completion_rate(): float {
        global $DB;

        $total = (int)$DB->count_records('user_enrolments');
        if ($total === 0) {
            return 0.0;
        }

        $done = (int)$DB->count_records_select('course_completions', 'timecompleted IS NOT NULL');
        return round($done / $total * 100, 1);
    }

    // =========================================================================
    // INTERNAL METHODS
    // =========================================================================

    /**
     * Use Moodle's core_completion\progress to get activity-level progress %.
     */
    protected static function get_activity_progress(object $course, int $userid): ?float {
        try {
            $pct = \core_completion\progress::get_course_progress_percentage($course, $userid);
            return $pct !== null ? round((float)$pct, 1) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bulk-load activity progress for multiple users (avoids N+1 queries).
     * Uses completion_info::get_progress_all() which is designed for this.
     */
    protected static function get_bulk_activity_progress(object $course, array $userids): array {
        if (empty($userids)) {
            return [];
        }

        try {
            $info = new \completion_info($course);
            if (!$info->is_enabled()) {
                return [];
            }

            $activities = $info->get_activities();
            $totalactivities = count($activities);
            if ($totalactivities === 0) {
                return [];
            }

            // get_progress_all returns an array of user progress objects.
            $progress_all = $info->get_progress_all();
            $results = [];

            foreach ($userids as $uid) {
                if (!isset($progress_all[$uid])) {
                    $results[$uid] = 0.0;
                    continue;
                }

                $userprogress = $progress_all[$uid];
                $done = 0;
                foreach ($userprogress->progress as $cmid => $data) {
                    if (!empty($data->completionstate) && $data->completionstate > 0) {
                        $done++;
                    }
                }
                $results[$uid] = round($done / $totalactivities * 100, 1);
            }

            return $results;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Multi-signal detection for courses with completion tracking OFF.
     */
    protected static function detect_completion(int $userid, int $courseid, object $course): object {
        global $DB;

        // Signal 1: 100% course grade.
        if (completion_helper::check_grade($userid, $courseid)) {
            $gg = $DB->get_record_sql(
                "SELECT gg.timemodified FROM {grade_grades} gg
                   JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.courseid = :cid AND gi.itemtype = 'course'
                  WHERE gg.userid = :uid AND gg.finalgrade IS NOT NULL",
                ['cid' => $courseid, 'uid' => $userid]
            );
            return (object)[
                'completed'      => true,
                'progress'       => 100.0,
                'timecompleted'  => $gg ? (int)$gg->timemodified : time(),
                'source'         => 'grade_100',
            ];
        }

        // Signal 2: All activities completed.
        if (completion_helper::check_all_activities($userid, $courseid)) {
            return (object)[
                'completed'      => true,
                'progress'       => 100.0,
                'timecompleted'  => time(),
                'source'         => 'all_activities',
            ];
        }

        // Signal 3: All SCORM completed/passed.
        if (completion_helper::check_all_scorm($userid, $courseid)) {
            return (object)[
                'completed'      => true,
                'progress'       => 100.0,
                'timecompleted'  => time(),
                'source'         => 'all_scorm',
            ];
        }

        // Signal 4: All quizzes passed.
        if (completion_helper::check_all_quizzes($userid, $courseid)) {
            return (object)[
                'completed'      => true,
                'progress'       => 100.0,
                'timecompleted'  => time(),
                'source'         => 'all_quizzes',
            ];
        }

        // Not completed — estimate progress.
        $progress = self::estimate_progress($userid, $courseid);

        return (object)[
            'completed'      => false,
            'progress'       => $progress,
            'timecompleted'  => null,
            'source'         => 'signals',
        ];
    }

    /**
     * Estimate progress for courses without Moodle tracking.
     * Combines activity, grade, SCORM, and quiz progress.
     */
    protected static function estimate_progress(int $userid, int $courseid): float {
        global $DB;

        $signals = [];

        // Activity progress (if any trackable).
        $totalact = (int)$DB->count_records_select('course_modules',
            'course = :cid AND visible = 1 AND completion > 0', ['cid' => $courseid]);
        if ($totalact > 0) {
            $doneact = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT cmc.coursemoduleid)
                   FROM {course_modules_completion} cmc
                   JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                  WHERE cm.course = :cid AND cm.visible = 1 AND cm.completion > 0
                    AND cmc.userid = :uid AND cmc.completionstate > 0",
                ['cid' => $courseid, 'uid' => $userid]
            );
            $signals[] = ($doneact / $totalact) * 100;
        }

        // Grade progress.
        $gi = $DB->get_record('grade_items', ['courseid' => $courseid, 'itemtype' => 'course']);
        if ($gi && $gi->grademax > 0) {
            $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $userid]);
            if ($gg && $gg->finalgrade !== null) {
                $signals[] = min(100, ($gg->finalgrade / $gi->grademax) * 100);
            }
        }

        // SCORM progress.
        try {
            $scorms = $DB->get_records('scorm', ['course' => $courseid], '', 'id');
            if (!empty($scorms)) {
                $total = count($scorms);
                $done = 0;
                foreach ($scorms as $s) {
                    if (completion_helper::is_scorm_complete_public($userid, $s->id)) {
                        $done++;
                    }
                }
                $signals[] = ($done / $total) * 100;
            }
        } catch (\Throwable $e) {}

        // Quiz progress.
        try {
            $quizzes = $DB->get_records('quiz', ['course' => $courseid], '', 'id, sumgrades');
            if (!empty($quizzes)) {
                $total = count($quizzes);
                $passed = 0;
                $passthreshold = (float)(get_config('local_leducon', 'kpi_pass_green') ?: 70) / 100;
                foreach ($quizzes as $q) {
                    if ($q->sumgrades <= 0) {
                        $total--;
                        continue;
                    }
                    $best = $DB->get_field_sql(
                        "SELECT MAX(sumgrades) FROM {quiz_attempts}
                          WHERE quiz = :qid AND userid = :uid AND state = 'finished'",
                        ['qid' => $q->id, 'uid' => $userid]
                    );
                    if ($best !== false && $best !== null && (float)$best / (float)$q->sumgrades >= $passthreshold) {
                        $passed++;
                    }
                }
                if ($total > 0) {
                    $signals[] = ($passed / $total) * 100;
                }
            }
        } catch (\Throwable $e) {}

        if (empty($signals)) {
            return 0.0;
        }

        // Return the average of all available signals.
        return round(array_sum($signals) / count($signals), 1);
    }

    /**
     * Return an empty result object.
     */
    protected static function empty_result(): object {
        return (object)[
            'completed'      => false,
            'progress'       => null,
            'timecompleted'  => null,
            'source'         => 'none',
        ];
    }
}
