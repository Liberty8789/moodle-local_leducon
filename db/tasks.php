<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task definitions for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [

    // Analytics: check alert rules every 4 hours.
    [
        'classname'  => '\local_leducon\task\alert_checker',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '*/4',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Analytics: email scheduled reports every Monday at 7:00 AM.
    [
        'classname'  => '\local_leducon\task\email_reports',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '7',
        'day'        => '*',
        'dayofweek'  => '1',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Gamify: process streak resets daily at 00:05.
    [
        'classname'  => '\local_leducon\task\process_streaks',
        'blocking'   => 0,
        'minute'     => '5',
        'hour'       => '0',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Gamify: send nudge emails daily at 08:30.
    [
        'classname'  => '\local_leducon\task\send_nudges',
        'blocking'   => 0,
        'minute'     => '30',
        'hour'       => '8',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Gamify: check campaign start/end dates every hour.
    [
        'classname'  => '\local_leducon\task\campaign_check',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '*',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Gamify: send weekly XP digest every Monday at 07:30.
    [
        'classname'  => '\local_leducon\task\weekly_digest',
        'blocking'   => 0,
        'minute'     => '30',
        'hour'       => '7',
        'day'        => '*',
        'dayofweek'  => '1',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Automation: compliance deadline reminders every morning at 06:00.
    [
        'classname'  => '\local_leducon\task\compliance_reminder',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '6',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Automation: send scheduled custom reports every hour at :15.
    [
        'classname'  => '\local_leducon\task\report_scheduler',
        'blocking'   => 0,
        'minute'     => '15',
        'hour'       => '*',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Automation: data retention cleanup daily at 02:00.
    [
        'classname'  => '\local_leducon\task\data_retention',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '2',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Analytics: pre-compute report aggregates nightly at 03:00.
    [
        'classname'  => '\local_leducon\task\precompute_reports',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '3',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Analytics: check insight thresholds and send notifications daily at 07:00.
    [
        'classname'  => '\local_leducon\task\insight_notifier',
        'blocking'   => 0,
        'minute'     => '0',
        'hour'       => '7',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],

    // Completion sync: safety net — catch any missed completions every cron run.
    [
        'classname'  => '\local_leducon\task\completion_sync',
        'blocking'   => 0,
        'minute'     => '*',
        'hour'       => '*',
        'day'        => '*',
        'dayofweek'  => '*',
        'month'      => '*',
        'disabled'   => 0,
    ],
];
