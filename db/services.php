<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Web service definitions for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_leducon_get_report_data' => [
        'classname'     => 'local_leducon\external\get_report_data',
        'methodname'    => 'execute',
        'description'   => 'Get report data for a specified report type with optional filters.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/leducon:viewanalytics',
    ],
    'local_leducon_get_report_summary' => [
        'classname'     => 'local_leducon\external\get_report_summary',
        'methodname'    => 'execute',
        'description'   => 'Get summary KPIs for a specified report type.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/leducon:viewanalytics',
    ],
    'local_leducon_get_report_insights' => [
        'classname'     => 'local_leducon\external\get_report_insights',
        'methodname'    => 'execute',
        'description'   => 'Get dynamic insights for a specified report type.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/leducon:viewanalytics',
    ],
    'local_leducon_get_kpi_aggregates' => [
        'classname'     => 'local_leducon\external\get_kpi_aggregates',
        'methodname'    => 'execute',
        'description'   => 'Get pre-computed global KPI aggregates.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/leducon:viewanalytics',
    ],
];

$services = [
    'Leducon Analytics API' => [
        'functions' => [
            'local_leducon_get_report_data',
            'local_leducon_get_report_summary',
            'local_leducon_get_report_insights',
            'local_leducon_get_kpi_aggregates',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'leducon_analytics',
    ],
];
