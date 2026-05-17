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
 * External function: get report insights.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report_insights extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'reporttype' => new \external_value(PARAM_ALPHANUMEXT, 'Report type identifier'),
        ]);
    }

    public static function execute(string $reporttype): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/leducon/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'reporttype' => $reporttype,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/leducon:viewanalytics', $context);

        try {
            $report = \local_leducon_get_report($params['reporttype']);
        } catch (\coding_exception $e) {
            throw new \invalid_parameter_exception('Invalid report type: ' . $params['reporttype']);
        }

        $data = $report->get_data();
        $insights = $report->get_insights($data);

        $items = [];
        foreach ($insights as $insight) {
            $items[] = [
                'type'   => $insight['type'],
                'icon'   => $insight['icon'],
                'title'  => $insight['title'],
                'detail' => $insight['detail'],
            ];
        }

        return ['insights' => $items];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'insights' => new \external_multiple_structure(
                new \external_single_structure([
                    'type'   => new \external_value(PARAM_ALPHA, 'Insight type: success, warning, danger, info'),
                    'icon'   => new \external_value(PARAM_RAW, 'Emoji icon'),
                    'title'  => new \external_value(PARAM_TEXT, 'Insight title'),
                    'detail' => new \external_value(PARAM_TEXT, 'Insight detail'),
                ])
            ),
        ]);
    }
}
