<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Post-install hook: seeds default levels, achievements, and platform roles.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_leducon_install(): void {
    global $DB;
    $now = time();

    // ── Create the three platform roles ─────────────────────────────
    local_leducon_setup_roles();

    // ── Seed default XP level thresholds ────────────────────────────
    $levels = [
        ['levelnum' => 1, 'name' => 'Learner',  'min_xp' => 0],
        ['levelnum' => 2, 'name' => 'Explorer', 'min_xp' => 500],
        ['levelnum' => 3, 'name' => 'Achiever', 'min_xp' => 1500],
        ['levelnum' => 4, 'name' => 'Champion', 'min_xp' => 3500],
        ['levelnum' => 5, 'name' => 'Legend',   'min_xp' => 7500],
    ];
    foreach ($levels as $l) {
        $l['timecreated']  = $now;
        $l['timemodified'] = $now;
        $DB->insert_record('local_leducon_levels', (object) $l);
    }

    // ── Seed default achievements ───────────────────────────────────
    $achievements = [
        ['name' => 'First Steps',      'icon' => '👣', 'rule_type' => 'xp_total',            'rule_value' => 1,    'xp_bonus' => 0,   'description' => 'Earn your very first XP.'],
        ['name' => 'Century',          'icon' => '💯', 'rule_type' => 'xp_total',            'rule_value' => 100,  'xp_bonus' => 50,  'description' => 'Reach 100 XP.'],
        ['name' => 'Overachiever',     'icon' => '🚀', 'rule_type' => 'xp_total',            'rule_value' => 5000, 'xp_bonus' => 500, 'description' => 'Earn 5,000 XP total.'],
        ['name' => 'Course Completer', 'icon' => '🎓', 'rule_type' => 'completions',         'rule_value' => 1,    'xp_bonus' => 0,   'description' => 'Complete your first course.'],
        ['name' => 'Graduate',         'icon' => '🏫', 'rule_type' => 'completions',         'rule_value' => 5,    'xp_bonus' => 100, 'description' => 'Complete 5 courses.'],
        ['name' => 'Quiz Ace',         'icon' => '🎯', 'rule_type' => 'quiz_passes',         'rule_value' => 10,   'xp_bonus' => 150, 'description' => 'Pass 10 quizzes on the first attempt.'],
        ['name' => 'On a Roll',        'icon' => '🔥', 'rule_type' => 'streak_days',         'rule_value' => 7,    'xp_bonus' => 0,   'description' => 'Maintain a 7-day learning streak.'],
        ['name' => 'Month Warrior',    'icon' => '📅', 'rule_type' => 'streak_days',         'rule_value' => 30,   'xp_bonus' => 0,   'description' => 'Maintain a 30-day learning streak.'],
        ['name' => 'Socialite',        'icon' => '💬', 'rule_type' => 'forum_posts',         'rule_value' => 25,   'xp_bonus' => 75,  'description' => 'Post 25 times in forums.'],
        ['name' => 'Badge Collector',  'icon' => '🏅', 'rule_type' => 'badges_earned',       'rule_value' => 5,    'xp_bonus' => 100, 'description' => 'Earn 5 Moodle badges.'],
        ['name' => 'Path Pioneer',     'icon' => '🗺️', 'rule_type' => 'paths_completed',     'rule_value' => 1,    'xp_bonus' => 0,   'description' => 'Complete your first learning path.'],
        ['name' => 'Recognised',       'icon' => '⭐', 'rule_type' => 'spotlights_received', 'rule_value' => 1,    'xp_bonus' => 0,   'description' => 'Receive your first spotlight recognition.'],
    ];
    foreach ($achievements as $i => $a) {
        $a['enabled']      = 1;
        $a['sortorder']    = $i + 1;
        $a['timecreated']  = $now;
        $a['timemodified'] = $now;
        $DB->insert_record('local_leducon_achievements', (object) $a);
    }
}
