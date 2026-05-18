<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: deliver custom and built-in reports on a per-user schedule.
 *
 * Each user can schedule any report (custom or built-in) for daily, weekly,
 * or monthly email delivery as a CSV attachment.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class report_scheduler extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_report_scheduler', 'local_leducon');
    }

    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->libdir . '/moodlelib.php');
        require_once($CFG->dirroot . '/local/leducon/lib.php');

        $schedules = $DB->get_records('local_leducon_rpt_schedule', ['enabled' => 1]);
        if (empty($schedules)) {
            mtrace('local_leducon report_scheduler: no active schedules.');
            return;
        }

        $now = time();
        $sent = 0;

        foreach ($schedules as $sched) {
            if (!$this->is_due($sched, $now)) {
                continue;
            }

            try {
                $this->deliver_report($sched);
                $DB->set_field('local_leducon_rpt_schedule', 'last_sent', $now, ['id' => $sched->id]);
                $sent++;
            } catch (\Throwable $e) {
                mtrace("  report_scheduler: error delivering schedule [{$sched->id}]: " . $e->getMessage());
            }
        }

        mtrace("local_leducon report_scheduler: delivered {$sent} report(s).");
    }

    /**
     * Check if a schedule is due for delivery.
     */
    protected function is_due(\stdClass $sched, int $now): bool {
        $currenthour = (int) date('G', $now);
        $currentdow  = (int) date('N', $now); // 1=Mon...7=Sun
        $currentdom  = (int) date('j', $now);

        // Only run at the scheduled hour.
        if ($currenthour !== (int) $sched->hour) {
            return false;
        }

        // Check if already sent this period.
        if ($sched->last_sent > 0) {
            $gap = $now - $sched->last_sent;
            $dbuf = (int)(get_config('local_leducon', 'scheduler_daily_buffer') ?: 20);
            $wbuf = (int)(get_config('local_leducon', 'scheduler_weekly_buffer') ?: 5);
            $mbuf = (int)(get_config('local_leducon', 'scheduler_monthly_buffer') ?: 25);
            switch ($sched->frequency) {
                case 'daily':
                    if ($gap < $dbuf * 3600) return false;
                    break;
                case 'weekly':
                    if ($gap < $wbuf * DAYSECS) return false;
                    break;
                case 'monthly':
                    if ($gap < $mbuf * DAYSECS) return false;
                    break;
            }
        }

        switch ($sched->frequency) {
            case 'daily':
                return true;
            case 'weekly':
                return ($currentdow === (int) $sched->day_of_week);
            case 'monthly':
                return ($currentdom === (int) $sched->day_of_month);
        }

        return false;
    }

    /**
     * Generate and email the report.
     */
    protected function deliver_report(\stdClass $sched): void {
        global $DB, $CFG;

        $reportname = '';
        $csvdata = '';

        // Custom report.
        if (!empty($sched->reportid)) {
            $def = $DB->get_record('local_leducon_custom_rpts', ['id' => $sched->reportid]);
            if (!$def) {
                mtrace("  report_scheduler: custom report [{$sched->reportid}] not found, skipping.");
                return;
            }
            $report = new \local_leducon\reports\custom_report($def);
            $reportname = $def->name;
            $csvdata = $report->export_csv();
        }
        // Built-in report.
        elseif (!empty($sched->reporttype)) {
            try {
                $report = \local_leducon_get_report($sched->reporttype);
                $types = \local_leducon_get_report_types();
                $reportname = $types[$sched->reporttype] ?? $sched->reporttype;

                // Apply default date filters.
                $period = (int) (get_config('local_leducon', 'defaultperiod') ?: 30);
                $report->set_filters([
                    'datefrom' => time() - ($period * DAYSECS),
                    'dateto'   => time(),
                ]);

                $columns = $report->get_columns();
                $colkeys = array_keys($columns);
                $data = $report->get_data();

                $output = fopen('php://temp', 'r+');
                fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel.
                fputcsv($output, array_map(function($c) { return $c['label']; }, $columns));
                foreach ($data as $row) {
                    $line = [];
                    foreach ($colkeys as $key) {
                        $line[] = $row[$key] ?? '';
                    }
                    fputcsv($output, $line);
                }
                rewind($output);
                $csvdata = stream_get_contents($output);
                fclose($output);
            } catch (\Throwable $e) {
                mtrace("  report_scheduler: error generating built-in report: " . $e->getMessage());
                return;
            }
        } else {
            mtrace("  report_scheduler: schedule [{$sched->id}] has no report, skipping.");
            return;
        }

        if (empty($csvdata)) {
            mtrace("  report_scheduler: empty data for [{$reportname}], skipping.");
            return;
        }

        // Write CSV to temp file.
        $filename = clean_filename($reportname . '_' . date('Y-m-d') . '.csv');
        $tmppath = $CFG->tempdir . '/' . $filename;
        file_put_contents($tmppath, $csvdata);

        // Send to each recipient.
        $recipients = array_filter(array_map('trim', explode(',', $sched->recipients)));
        $noreplyuser = \core_user::get_noreply_user();

        $subject = get_string('pluginname', 'local_leducon') . ': ' . $reportname .
                   ' (' . date('Y-m-d') . ')';
        $body = get_string('scheduled_report_body', 'local_leducon',
                    (object)['name' => $reportname, 'date' => userdate(time())]);

        foreach ($recipients as $email) {
            if (!validate_email($email)) {
                continue;
            }

            $touser = new \stdClass();
            $touser->id = -1;
            $touser->email = $email;
            $touser->firstname = '';
            $touser->lastname = '';
            $touser->username = $email;
            $touser->auth = 'manual';
            $touser->confirmed = 1;
            $touser->deleted = 0;
            $touser->suspended = 0;
            $touser->mnethostid = $CFG->mnet_localhost_id ?? 1;
            $touser->emailstop = 0;
            $touser->lang = $CFG->lang ?? 'en';
            $touser->timezone = $CFG->timezone ?? '99';
            $touser->mailformat = 1;

            $result = email_to_user($touser, $noreplyuser, $subject, $body,
                                     nl2br(htmlspecialchars($body, ENT_QUOTES)),
                                     $tmppath, $filename);
            mtrace("  -> email to {$email}: " . ($result ? 'ok' : 'failed'));
        }

        // Cleanup temp file.
        @unlink($tmppath);
    }
}
