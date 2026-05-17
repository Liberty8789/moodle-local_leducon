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
 * External function: get report data.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report_data extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'reporttype' => new \external_value(PARAM_ALPHANUMEXT, 'Report type identifier'),
            'filters'    => new \external_multiple_structure(
                new \external_single_structure([
                    'name'  => new \external_value(PARAM_ALPHANUMEXT, 'Filter name'),
                    'value' => new \external_value(PARAM_RAW, 'Filter value'),
                ]),
                'Optional filters',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    public static function execute(string $reporttype, array $filters = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/leducon/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'reporttype' => $reporttype,
            'filters'    => $filters,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/leducon:viewanalytics', $context);

        try {
            $report = \local_leducon_get_report($params['reporttype']);
        } catch (\coding_exception $e) {
            throw new \invalid_parameter_exception('Invalid report type: ' . $params['reporttype']);
        }

        $filtermap = [];
        foreach ($params['filters'] as $f) {
            $filtermap[$f['name']] = $f['value'];
        }
        $report->set_filters($filtermap);

        $data = $report->get_data();
        $columns = $report->get_columns();

        $result = [];
        foreach ($data as $row) {
            $cells = [];
            foreach ($columns as $key => $coldef) {
                $cells[] = [
                    'column' => $key,
                    'value'  => isset($row[$key]) ? (string) $row[$key] : '',
                ];
            }
            $result[] = ['cells' => $cells];
        }

        return ['rows' => $result, 'total' => count($result)];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'rows' => new \external_multiple_structure(
                new \external_single_structure([
                    'cells' => new \external_multiple_structure(
                        new \external_single_structure([
                            'column' => new \external_value(PARAM_ALPHANUMEXT, 'Column key'),
                            'value'  => new \external_value(PARAM_RAW, 'Cell value'),
                        ])
                    ),
                ])
            ),
            'total' => new \external_value(PARAM_INT, 'Total row count'),
        ]);
    }
}
