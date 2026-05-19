<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Pre-computes heavy report aggregates and stores them in cache.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class precompute_reports extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_precompute_reports', 'local_leducon');
    }

    public function execute(): void {
        $cache = \cache::make('local_leducon', 'kpi_aggregates');

        $aggregates = $this->compute_aggregates();
        $cache->set('global_kpis', $aggregates);

        mtrace('Leducon: pre-computed report aggregates for ' . count($aggregates) . ' KPIs.');
    }

    private function compute_aggregates(): array {
        global $DB;

        $now = time();
        $precomputedays = (int)(get_config('local_leducon', 'precompute_period_days') ?: 30);
        $thirtyago = $now - $precomputedays * DAYSECS;

        $totalusers = (int) $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

        $activeusers = (int) $DB->count_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND lastaccess > :cutoff',
            ['cutoff' => $thirtyago]
        );

        $totalenrolments = (int) $DB->count_records('user_enrolments');

        $totalcompletions = \local_leducon\completion_data::count_completions();

        $recentcompletions = \local_leducon\completion_data::count_completions($thirtyago);

        $totalcourses = (int) $DB->count_records_select('course', 'id <> :siteid', ['siteid' => SITEID]);

        return [
            'total_users'          => $totalusers,
            'active_users_30d'     => $activeusers,
            'total_enrolments'     => $totalenrolments,
            'total_completions'    => $totalcompletions,
            'recent_completions'   => $recentcompletions,
            'total_courses'        => $totalcourses,
            'completion_rate'      => $totalenrolments > 0
                ? round($totalcompletions / $totalenrolments * 100, 1)
                : 0,
            'computed_at'          => $now,
        ];
    }
}
