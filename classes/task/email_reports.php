<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: send summary email reports.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends a weekly summary email to configured recipients.
 *
 * Runs every Monday at 07:00 by default (see db/tasks.php).
 */
class email_reports extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('nav_reports', 'local_leducon');
    }

    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->libdir . '/moodlelib.php');

        $config = get_config('local_leducon');

        $recipients = [];
        if (!empty($config->email_recipients)) {
            foreach (preg_split('/[\r\n]+/', $config->email_recipients) as $line) {
                $line = trim($line);
                if ($line !== '' && validate_email($line)) {
                    $recipients[] = $line;
                }
            }
        }

        if (empty($recipients)) {
            mtrace('local_leducon email_reports: no recipients configured, skipping.');
            return;
        }

        $reportkeys = [];
        if (!empty($config->email_reports_list)) {
            foreach (explode(',', $config->email_reports_list) as $key) {
                $key = trim($key);
                if ($key !== '') {
                    $reportkeys[] = $key;
                }
            }
        }

        $period   = (int) ($config->defaultperiod ?? 30);
        $fromtime = time() - ($period * DAYSECS);
        $totime   = time();

        $subject = get_string('pluginname', 'local_leducon') . ': ' .
                   get_string('nav_overview', 'local_leducon');

        $body = $this->build_summary_body($fromtime, $totime, $reportkeys);

        $noreplyuser = \core_user::get_noreply_user();

        foreach ($recipients as $emailaddress) {
            $touser             = new \stdClass();
            $touser->id         = -1;
            $touser->email      = $emailaddress;
            $touser->firstname  = '';
            $touser->lastname   = '';
            $touser->username   = $emailaddress;
            $touser->auth       = 'manual';
            $touser->confirmed  = 1;
            $touser->deleted    = 0;
            $touser->suspended  = 0;
            $touser->mnethostid = $CFG->mnet_localhost_id ?? 1;
            $touser->emailstop  = 0;
            $touser->lang       = $CFG->lang ?? 'en';
            $touser->timezone   = $CFG->timezone ?? '99';
            $touser->mailformat = 1;

            $result = email_to_user($touser, $noreplyuser, $subject, $body, $body);
            local_leducon_log_notification(0, $emailaddress, '', $subject, $body, 'email', 'general', $result ? 'sent' : 'failed');
            if ($result) {
                mtrace("local_leducon email_reports: sent to {$emailaddress}");
            } else {
                mtrace("local_leducon email_reports: failed to send to {$emailaddress}");
            }
        }
    }

    protected function build_summary_body(int $fromtime, int $totime, array $reportkeys): string {
        global $DB, $CFG;

        $from = userdate($fromtime, get_string('strftimedate', 'langconfig'));
        $to   = userdate($totime,   get_string('strftimedate', 'langconfig'));

        $lines   = [];
        $lines[] = get_string('pluginname', 'local_leducon') . ' — ' .
                   get_string('nav_overview', 'local_leducon');
        $lines[] = str_repeat('-', 60);
        $lines[] = "Period: {$from} to {$to}";
        $lines[] = '';

        $activeusers = $DB->get_field_sql(
            "SELECT COUNT(DISTINCT userid)
               FROM {logstore_standard_log}
              WHERE timecreated >= :from AND timecreated <= :to AND userid > 0",
            ['from' => $fromtime, 'to' => $totime]
        );
        $lines[] = get_string('kpi_active_this_week', 'local_leducon') . ': ' . $activeusers;

        $newenrolments = $DB->count_records_select(
            'user_enrolments',
            'timecreated >= :from AND timecreated <= :to',
            ['from' => $fromtime, 'to' => $totime]
        );
        $lines[] = get_string('kpi_new_enrolments', 'local_leducon') . ': ' . $newenrolments;

        $completions = (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM {course_completions} cc
             LEFT JOIN {grade_items} gi_er ON gi_er.courseid = cc.course AND gi_er.itemtype = 'course'
             LEFT JOIN {grade_grades} gg_er ON gg_er.itemid = gi_er.id AND gg_er.userid = cc.userid
              WHERE (cc.timecompleted >= :from AND cc.timecompleted <= :to)
                 OR (cc.timecompleted IS NULL AND gg_er.finalgrade IS NOT NULL AND gi_er.grademax > 0
                     AND gg_er.finalgrade / gi_er.grademax * 100 >= 100
                     AND gg_er.timemodified >= :gfrom AND gg_er.timemodified <= :gto)",
            ['from' => $fromtime, 'to' => $totime, 'gfrom' => $fromtime, 'gto' => $totime]
        );
        $lines[] = get_string('kpi_completion', 'local_leducon') . ' (completions): ' . $completions;

        $lines[] = '';
        $lines[] = str_repeat('-', 60);
        $lines[] = 'Generated by ' . get_string('pluginname', 'local_leducon') .
                   ' at ' . userdate(time()) . '.';

        return implode("\n", $lines);
    }
}
