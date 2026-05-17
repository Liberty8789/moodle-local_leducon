<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\event;

defined('MOODLE_INTERNAL') || die();

use local_leducon\gamify\xp_engine;
use local_leducon\gamify\path_manager;
use local_leducon\gamify\streak_tracker;

/**
 * Event observer — hooks Moodle events to award XP and track streaks.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    public static function course_completed(\core\event\course_completed $event): void {
        $userid   = (int)$event->relateduserid;
        $courseid = (int)$event->courseid;
        $ctx      = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$ctx) {
            return;
        }
        xp_engine::award($userid, 'course_completion', $ctx->id, 'Course: ' . $courseid);
        path_manager::check_path_completion($userid, $courseid);
    }

    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $userid  = (int)$event->userid;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], 'id, quiz, sumgrades, timefinish');
        if (!$attempt) {
            return;
        }
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], 'id, grade');
        if (!$quiz) {
            return;
        }

        $ctx = $event->get_context();
        xp_engine::award($userid, 'quiz_attempt', $ctx->id, 'Quiz attempt: ' . $attempt->quiz);

        // First-pass bonus: check if this is the first submission and grade >= 50% of max score.
        $max_grade = (float)($DB->get_field('quiz', 'sumgrades', ['id' => $attempt->quiz]) ?: 0);
        if ($max_grade > 0 && (float)$attempt->sumgrades >= ($max_grade * 0.5)) {
            $prior = $DB->count_records_select(
                'quiz_attempts',
                'quiz = :qid AND userid = :uid AND state = :st AND id < :aid',
                ['qid' => $attempt->quiz, 'uid' => $userid, 'st' => 'finished', 'aid' => $attempt->id]
            );
            if ($prior === 0) {
                xp_engine::award($userid, 'quiz_pass_first', $ctx->id, 'First-pass quiz: ' . $attempt->quiz);
            }
        }
    }

    /**
     * Handles both onlinetext and file submission uploads.
     */
    public static function assign_submitted(\core\event\base $event): void {
        global $DB;

        $userid = (int)$event->userid;
        $cmid   = (int)$event->contextinstanceid;
        $ctx    = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$ctx) {
            return;
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, duedate');
        if (!$assign) {
            return;
        }

        xp_engine::award($userid, 'assign_submitted', $ctx->id, 'Assignment: ' . $cm->instance);

        // On-time bonus: submitted before or on the due date.
        if ($assign->duedate && time() <= $assign->duedate) {
            xp_engine::award($userid, 'assign_ontime', $ctx->id, 'On-time assign: ' . $cm->instance);
        }
    }

    public static function forum_post_created(\mod_forum\event\post_created $event): void {
        $userid = (int)$event->userid;
        $ctx    = $event->get_context();
        xp_engine::award($userid, 'forum_post', $ctx->id, 'Forum post: ' . $event->objectid);
    }

    public static function user_loggedin(\core\event\user_loggedin $event): void {
        streak_tracker::record_login((int)$event->objectid);
    }

    public static function badge_awarded(\core\event\badge_awarded $event): void {
        $userid  = (int)$event->relateduserid;
        $badgeid = (int)$event->objectid;
        xp_engine::award($userid, 'badge_awarded', $badgeid, 'Badge: ' . $badgeid);
    }
}
