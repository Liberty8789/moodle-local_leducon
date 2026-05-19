<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observer definitions for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_leducon\event\observer::course_completed',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_leducon\event\observer::quiz_attempt_submitted',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded',
        'callback'  => '\local_leducon\event\observer::assign_submitted',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded',
        'callback'  => '\local_leducon\event\observer::assign_submitted',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\mod_forum\event\post_created',
        'callback'  => '\local_leducon\event\observer::forum_post_created',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_leducon\event\observer::user_loggedin',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\core\event\badge_awarded',
        'callback'  => '\local_leducon\event\observer::badge_awarded',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\core\event\user_graded',
        'callback'  => '\local_leducon\event\observer::user_graded',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\local_leducon\event\observer::course_module_completion_updated',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\mod_scorm\event\scoreraw_submitted',
        'callback'  => '\local_leducon\event\observer::scorm_tracks_updated',
        'internal'  => false,
        'priority'  => 200,
    ],

    [
        'eventname' => '\mod_scorm\event\status_submitted',
        'callback'  => '\local_leducon\event\observer::scorm_tracks_updated',
        'internal'  => false,
        'priority'  => 200,
    ],
];
