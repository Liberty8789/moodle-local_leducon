<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: data retention cleanup.
 *
 * Purges old XP log entries and recognition records beyond a configurable
 * retention period. Keeps XP totals intact — only removes the detailed log.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class data_retention extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_data_retention', 'local_leducon');
    }

    public function execute(): void {
        global $DB;

        $enabled = (int) get_config('local_leducon', 'data_retention_enabled');
        if (!$enabled) {
            mtrace('local_leducon data_retention: disabled, skipping.');
            return;
        }

        $months = (int) get_config('local_leducon', 'data_retention_months') ?: 24;
        $cutoff = time() - ($months * 30 * DAYSECS);

        // Purge old XP log entries (keeps xp_users totals intact).
        try {
            $deleted = $DB->count_records_select(
                'local_leducon_xp_log',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
            if ($deleted > 0) {
                $DB->delete_records_select(
                    'local_leducon_xp_log',
                    'timecreated < :cutoff',
                    ['cutoff' => $cutoff]
                );
                mtrace("local_leducon data_retention: purged {$deleted} XP log entries older than {$months} months.");
            } else {
                mtrace('local_leducon data_retention: no old XP log entries to purge.');
            }
        } catch (\dml_exception $e) {
            mtrace('local_leducon data_retention: error purging XP logs — ' . $e->getMessage());
        }

        // Purge old recognition records (not spotlights — only non-spotlight).
        try {
            $deleted2 = $DB->count_records_select(
                'local_leducon_recognition',
                'is_spotlight = 0 AND timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
            if ($deleted2 > 0) {
                $DB->delete_records_select(
                    'local_leducon_recognition',
                    'is_spotlight = 0 AND timecreated < :cutoff',
                    ['cutoff' => $cutoff]
                );
                mtrace("local_leducon data_retention: purged {$deleted2} old recognition entries.");
            }
        } catch (\dml_exception $e) {
            mtrace('local_leducon data_retention: error purging recognition — ' . $e->getMessage());
        }

        mtrace('local_leducon data_retention: cleanup complete.');
    }
}
