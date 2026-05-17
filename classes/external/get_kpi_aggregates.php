<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function: get pre-computed KPI aggregates.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_kpi_aggregates extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/leducon:viewanalytics', $context);

        $cache = \cache::make('local_leducon', 'kpi_aggregates');
        $aggregates = $cache->get('global_kpis');

        if ($aggregates === false) {
            return [
                'total_users'        => 0,
                'active_users_30d'   => 0,
                'total_enrolments'   => 0,
                'total_completions'  => 0,
                'recent_completions' => 0,
                'total_courses'      => 0,
                'completion_rate'    => 0.0,
                'computed_at'        => 0,
            ];
        }

        return [
            'total_users'        => (int) $aggregates['total_users'],
            'active_users_30d'   => (int) $aggregates['active_users_30d'],
            'total_enrolments'   => (int) $aggregates['total_enrolments'],
            'total_completions'  => (int) $aggregates['total_completions'],
            'recent_completions' => (int) $aggregates['recent_completions'],
            'total_courses'      => (int) $aggregates['total_courses'],
            'completion_rate'    => (float) $aggregates['completion_rate'],
            'computed_at'        => (int) $aggregates['computed_at'],
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'total_users'        => new \external_value(PARAM_INT, 'Total active users'),
            'active_users_30d'   => new \external_value(PARAM_INT, 'Users active in last 30 days'),
            'total_enrolments'   => new \external_value(PARAM_INT, 'Total enrolments'),
            'total_completions'  => new \external_value(PARAM_INT, 'Total course completions'),
            'recent_completions' => new \external_value(PARAM_INT, 'Completions in last 30 days'),
            'total_courses'      => new \external_value(PARAM_INT, 'Total courses'),
            'completion_rate'    => new \external_value(PARAM_FLOAT, 'Overall completion rate %'),
            'computed_at'        => new \external_value(PARAM_INT, 'Unix timestamp of last computation'),
        ]);
    }
}
