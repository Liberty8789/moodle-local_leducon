<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: sync grade-based completions into course_completions.
 *
 * Finds users who achieved 100% course grade but have no timecompleted
 * in course_completions, and writes the missing completion record.
 * This ensures Moodle's built-in completion system stays in sync even
 * when completion tracking is disabled for a course.
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

        $now = time();
        $synced = 0;
        $updated = 0;

        // Find all users with 100% course grade but no timecompleted.
        // Case 1: No course_completions record at all.
        // Case 2: Record exists but timecompleted IS NULL.
        $sql = "SELECT gg.userid,
                       gi.courseid,
                       gg.timemodified AS gradedate
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                                       AND gi.itemtype = 'course'
                                       AND gi.grademax > 0
                 WHERE gg.finalgrade IS NOT NULL
                   AND gg.finalgrade / gi.grademax * 100 >= 100
                   AND NOT EXISTS (
                       SELECT 1 FROM {course_completions} cc
                        WHERE cc.userid = gg.userid
                          AND cc.course = gi.courseid
                          AND cc.timecompleted IS NOT NULL
                   )";

        $records = $DB->get_records_sql($sql);

        foreach ($records as $rec) {
            // Verify the course and user still exist.
            if (!$DB->record_exists('course', ['id' => $rec->courseid])) {
                continue;
            }
            if (!$DB->record_exists('user', ['id' => $rec->userid, 'deleted' => 0])) {
                continue;
            }

            // Check if a course_completions row already exists (without timecompleted).
            $existing = $DB->get_record('course_completions', [
                'userid' => $rec->userid,
                'course' => $rec->courseid,
            ]);

            if ($existing) {
                // Update: set timecompleted to the grade timestamp.
                $existing->timecompleted = $rec->gradedate ?: $now;
                $existing->timemodified = $now;
                $DB->update_record('course_completions', $existing);
                $updated++;
            } else {
                // Insert new completion record.
                $completion = new \stdClass();
                $completion->userid        = $rec->userid;
                $completion->course        = $rec->courseid;
                $completion->timeenrolled  = 0;
                $completion->timestarted   = 0;
                $completion->timecompleted = $rec->gradedate ?: $now;
                $completion->reaggregate   = 0;
                $DB->insert_record('course_completions', $completion);
                $synced++;
            }

            // Fire Moodle's completion event so other plugins react.
            try {
                $course = $DB->get_record('course', ['id' => $rec->courseid], 'id,category');
                if ($course) {
                    $event = \core\event\course_completed::create([
                        'objectid'  => $existing->id ?? $DB->get_field('course_completions', 'id', [
                            'userid' => $rec->userid,
                            'course' => $rec->courseid,
                        ]),
                        'relateduserid' => $rec->userid,
                        'context'       => \context_course::instance($rec->courseid),
                        'courseid'      => $rec->courseid,
                    ]);
                    $event->trigger();
                }
            } catch (\Throwable $e) {
                // Non-critical — log but don't fail the task.
                mtrace("  completion_sync: event error for user {$rec->userid} course {$rec->courseid}: " .
                       $e->getMessage());
            }
        }

        mtrace("local_leducon completion_sync: {$synced} new records inserted, {$updated} existing records updated.");
    }
}
