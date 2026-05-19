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
 * Checks report insights for critical thresholds and sends notifications.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insight_notifier extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_insight_notifier', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        if (!get_config('local_leducon', 'insight_notifications_enabled')) {
            return;
        }

        $criticalreports = ['course_completion', 'compliance', 'atrisk'];
        $dangerinsights = [];

        foreach ($criticalreports as $type) {
            $report = \local_leducon_get_report($type);
            if (!$report) {
                continue;
            }
            $data = $report->get_data();
            $insights = $report->get_insights($data);

            foreach ($insights as $insight) {
                if ($insight['type'] === 'danger') {
                    $dangerinsights[] = [
                        'report' => $type,
                        'title'  => $insight['title'],
                        'detail' => $insight['detail'],
                    ];
                }
            }
        }

        if (empty($dangerinsights)) {
            mtrace('Leducon insight notifier: no critical insights found.');
            return;
        }

        $admins = get_admins();
        $supportuser = \core_user::get_support_user();

        $body = get_string('insight_notification_intro', 'local_leducon') . "\n\n";
        foreach ($dangerinsights as $di) {
            $body .= "- " . $di['title'] . "\n  " . $di['detail'] . "\n\n";
        }
        $body .= get_string('insight_notification_action', 'local_leducon');

        $subject = get_string('insight_notification_subject', 'local_leducon');
        foreach ($admins as $admin) {
            local_leducon_send_notification(
                $admin, $subject, $body, 'insight_alert',
                '/local/leducon/analytics/report.php',
                get_string('nav_reports', 'local_leducon')
            );
        }

        mtrace('Leducon insight notifier: sent ' . count($dangerinsights) . ' critical alerts to ' . count($admins) . ' admins.');
    }
}
