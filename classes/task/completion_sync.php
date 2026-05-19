<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: safety-net completion sync.
 *
 * Scans for users who should be marked course-complete based on any signal
 * (grade, activities, SCORM, quizzes) but don't yet have a timecompleted.
 * This catches anything the real-time event observer missed.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class completion_sync extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_completion_sync', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        $synced = 0;

        // Get all courses where Moodle's own completion tracking is OFF.
        // (Courses with enablecompletion=1 are handled by Moodle natively.)
        $courses = $DB->get_records_select('course',
            'id <> :siteid AND (enablecompletion = 0 OR enablecompletion IS NULL)',
            ['siteid' => SITEID],
            '',
            'id'
        );

        if (empty($courses)) {
            mtrace('local_leducon completion_sync: no courses with completion tracking disabled.');
            return;
        }

        foreach ($courses as $course) {
            // Get all enrolled users in this course who are NOT already completed.
            $users = $DB->get_records_sql(
                "SELECT DISTINCT ue.userid
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :cid
                   JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                  WHERE NOT EXISTS (
                      SELECT 1 FROM {course_completions} cc
                       WHERE cc.userid = ue.userid
                         AND cc.course = :cid2
                         AND cc.timecompleted IS NOT NULL
                  )",
                ['cid' => $course->id, 'cid2' => $course->id]
            );

            foreach ($users as $u) {
                try {
                    $result = \local_leducon\completion_helper::check_and_complete((int)$u->userid, (int)$course->id);
                    if ($result) {
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    mtrace("  completion_sync: error for user {$u->userid} course {$course->id}: " .
                           $e->getMessage());
                }
            }
        }

        mtrace("local_leducon completion_sync: {$synced} completion(s) written.");
    }
}
