<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Completion helper — detects course completion from multiple signals
 * and writes the record immediately when all activities are done.
 *
 * Signals checked:
 *  1. Course grade >= 100%
 *  2. All trackable activities completed (course_modules_completion)
 *  3. All SCORM activities completed/passed
 *  4. All quizzes passed (grade >= pass threshold)
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon;

defined('MOODLE_INTERNAL') || die();

class completion_helper {

    /**
     * Check whether a user should be marked as course-complete, and if so
     * write the course_completions record and fire the event.
     *
     * Safe to call repeatedly — returns immediately if already completed.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timestamp  Optional timestamp to use as timecompleted (default: now).
     * @return bool True if a completion was written (new or updated).
     */
    public static function check_and_complete(int $userid, int $courseid, int $timestamp = 0): bool {
        global $DB;

        // Already completed? Skip.
        $existing = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $courseid,
        ]);
        if ($existing && !empty($existing->timecompleted)) {
            return false;
        }

        // Moodle's own completion tracking is active and hasn't fired yet — don't interfere.
        $course = $DB->get_record('course', ['id' => $courseid], 'id, enablecompletion');
        if (!$course) {
            return false;
        }
        if (!empty($course->enablecompletion)) {
            // Moodle handles this course natively. Don't override.
            return false;
        }

        // Check our multi-signal completion.
        if (!self::is_completed($userid, $courseid)) {
            return false;
        }

        $now = $timestamp ?: time();
        $completionid = self::write_completion($userid, $courseid, $now, $existing);

        // Fire course_completed event.
        self::fire_event($userid, $courseid, $completionid);

        return true;
    }

    /**
     * Check ALL signals to determine if a user has completed a course.
     * Returns true if ANY signal says "done".
     */
    public static function is_completed(int $userid, int $courseid): bool {
        return self::check_grade($userid, $courseid)
            || self::check_all_activities($userid, $courseid)
            || self::check_all_scorm($userid, $courseid)
            || self::check_all_quizzes($userid, $courseid);
    }

    /**
     * Signal 1: Course grade >= 100%.
     */
    public static function check_grade(int $userid, int $courseid): bool {
        global $DB;

        $gi = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'course',
        ]);
        if (!$gi || $gi->grademax <= 0) {
            return false;
        }

        $gg = $DB->get_record('grade_grades', [
            'itemid' => $gi->id,
            'userid' => $userid,
        ]);
        if (!$gg || $gg->finalgrade === null) {
            return false;
        }

        return ($gg->finalgrade / $gi->grademax) * 100 >= 100;
    }

    /**
     * Signal 2: All trackable activities in the course are completed.
     * Returns false if the course has no trackable activities.
     */
    public static function check_all_activities(int $userid, int $courseid): bool {
        global $DB;

        // Count visible modules with completion tracking enabled.
        $totaltrackable = (int)$DB->count_records_select(
            'course_modules',
            'course = :cid AND visible = 1 AND completion > 0',
            ['cid' => $courseid]
        );

        if ($totaltrackable === 0) {
            return false; // No trackable activities — this signal can't determine completion.
        }

        // Count how many the user has completed.
        $done = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT cmc.coursemoduleid)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :cid
                AND cm.visible = 1
                AND cm.completion > 0
                AND cmc.userid = :uid
                AND cmc.completionstate > 0",
            ['cid' => $courseid, 'uid' => $userid]
        );

        return ($done >= $totaltrackable);
    }

    /**
     * Signal 3: All SCORM activities in the course are completed or passed.
     * Returns false if the course has no SCORM activities.
     */
    public static function check_all_scorm(int $userid, int $courseid): bool {
        global $DB;

        try {
            $scorms = $DB->get_records('scorm', ['course' => $courseid], '', 'id');
        } catch (\dml_exception $e) {
            return false; // SCORM module not installed.
        }

        if (empty($scorms)) {
            return false;
        }

        foreach ($scorms as $scorm) {
            if (!self::is_scorm_complete($userid, $scorm->id)) {
                return false; // At least one SCORM not done.
            }
        }

        return true;
    }

    /**
     * Public alias for is_scorm_complete (used by completion_data for progress).
     */
    public static function is_scorm_complete_public(int $userid, int $scormid): bool {
        return self::is_scorm_complete($userid, $scormid);
    }

    /**
     * Check if a single SCORM is completed/passed for a user.
     */
    protected static function is_scorm_complete(int $userid, int $scormid): bool {
        global $DB;

        try {
            $statuses = $DB->get_records_sql(
                "SELECT DISTINCT value
                   FROM {scorm_scoes_track}
                  WHERE scormid = :sid
                    AND userid = :uid
                    AND element IN ('cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status')",
                ['sid' => $scormid, 'uid' => $userid]
            );
        } catch (\dml_exception $e) {
            return false;
        }

        if (empty($statuses)) {
            return false;
        }

        $vals = array_map(function($r) { return strtolower(trim($r->value)); }, $statuses);

        return in_array('completed', $vals) || in_array('passed', $vals);
    }

    /**
     * Signal 4: All quizzes in the course passed (grade >= pass threshold).
     * Returns false if the course has no quizzes.
     */
    public static function check_all_quizzes(int $userid, int $courseid): bool {
        global $DB;

        try {
            $quizzes = $DB->get_records('quiz', ['course' => $courseid], '', 'id, sumgrades');
        } catch (\dml_exception $e) {
            return false; // Quiz module not installed.
        }

        if (empty($quizzes)) {
            return false;
        }

        $passthreshold = (float)(get_config('local_leducon', 'kpi_pass_green') ?: 70) / 100;

        foreach ($quizzes as $quiz) {
            if ($quiz->sumgrades <= 0) {
                continue; // Ungraded quiz — skip.
            }

            $bestgrade = $DB->get_field_sql(
                "SELECT MAX(qa.sumgrades)
                   FROM {quiz_attempts} qa
                  WHERE qa.quiz = :qid AND qa.userid = :uid AND qa.state = 'finished'",
                ['qid' => $quiz->id, 'uid' => $userid]
            );

            if ($bestgrade === false || $bestgrade === null) {
                return false; // Never attempted this quiz.
            }

            $pct = (float)$bestgrade / (float)$quiz->sumgrades;
            if ($pct < $passthreshold) {
                return false; // Failed this quiz.
            }
        }

        return true;
    }

    /**
     * Write or update the course_completions record.
     */
    protected static function write_completion(int $userid, int $courseid, int $timestamp, ?\stdClass $existing): int {
        global $DB;

        if ($existing) {
            $existing->timecompleted = $timestamp;
            $existing->timemodified = time();
            $DB->update_record('course_completions', $existing);
            return $existing->id;
        }

        $rec = new \stdClass();
        $rec->userid        = $userid;
        $rec->course        = $courseid;
        $rec->timeenrolled  = 0;
        $rec->timestarted   = 0;
        $rec->timecompleted = $timestamp;
        $rec->reaggregate   = 0;
        return $DB->insert_record('course_completions', $rec);
    }

    /**
     * Fire course_completed event (non-fatal if it fails).
     */
    protected static function fire_event(int $userid, int $courseid, int $completionid): void {
        try {
            $event = \core\event\course_completed::create([
                'objectid'      => $completionid,
                'relateduserid' => $userid,
                'context'       => \context_course::instance($courseid),
                'courseid'      => $courseid,
            ]);
            $event->trigger();
        } catch (\Throwable $e) {
            // Non-critical.
        }
    }
}
