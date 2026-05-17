<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Time Spent Learning report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timespent_report extends base_report {

    const MINS_PER_EVENT = 3;

    public function get_name(): string {
        return get_string('report_timespent', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'username'    => ['label' => get_string('ts_username',    'local_leducon'), 'sortable' => true],
            'fullname'    => ['label' => get_string('ts_fullname',    'local_leducon'), 'sortable' => true],
            'coursename'  => ['label' => get_string('ts_coursename',  'local_leducon'), 'sortable' => true],
            'events'      => ['label' => get_string('ts_events',      'local_leducon'), 'sortable' => true],
            'est_minutes' => ['label' => get_string('ts_est_minutes', 'local_leducon'), 'sortable' => true],
            'sessions'    => ['label' => get_string('ts_sessions',    'local_leducon'), 'sortable' => true],
            'lastactive'  => ['label' => get_string('ts_lastactive',  'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $from     = $this->resolve_from();
        $to       = $this->resolve_to();
        $courseid = (int) $this->filter('courseid', 0);
        $cohortid = (int) $this->filter('cohortid', 0);

        $coursecond   = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursecond   = 'AND ls.courseid = :courseid';
            $courseparams = ['courseid' => $courseid];
        }

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND u.id IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $rowkey_sql = $DB->sql_concat('u.id', "'-'", 'ls.courseid');
        $sql = "SELECT {$rowkey_sql}  AS rowkey,
                       u.id                             AS userid,
                       u.username,
                       u.firstname,
                       u.lastname,
                       c.fullname                       AS coursename,
                       ls.courseid,
                       COUNT(ls.id)                     AS events,
                       SUM(CASE WHEN ls.action = 'loggedin' THEN 1 ELSE 0 END) AS sessions,
                       MAX(ls.timecreated)              AS lastactive
                  FROM {logstore_standard_log} ls
                  JOIN {user} u  ON u.id  = ls.userid
                  JOIN {course} c ON c.id = ls.courseid AND c.id <> :siteid
                 WHERE ls.timecreated BETWEEN :from AND :to
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND u.username <> 'guest'
                   AND ls.courseid > 0
                   {$coursecond}
                   {$cohortjoin}
              GROUP BY u.id, u.username, u.firstname, u.lastname, c.fullname, ls.courseid
             HAVING COUNT(ls.id) >= 5
              ORDER BY events DESC, u.lastname ASC";

        $params = array_merge([
            'siteid' => SITEID,
            'from'   => $from,
            'to'     => $to,
        ], $courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $events = (int) $r->events;
            $rows[] = [
                'username'    => $r->username,
                'fullname'    => fullname($r),
                'coursename'  => format_string($r->coursename),
                'events'      => $events,
                'est_minutes' => $events * self::MINS_PER_EVENT,
                'sessions'    => (int) $r->sessions,
                'lastactive'  => $this->fmt_date($r->lastactive),
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalminutes = array_sum(array_column($data, 'est_minutes'));
        $totalusers   = count($data);
        $avgmins      = round($totalminutes / $totalusers);

        $heavy = 0;
        $light = 0;
        foreach ($data as $row) {
            if ((int)$row['est_minutes'] >= 300) {
                $heavy++;
            }
            if ((int)$row['est_minutes'] < 30) {
                $light++;
            }
        }

        if ($avgmins >= 120) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x86",
                'type'   => 'success',
                'title'  => get_string('insight_ts_highavg', 'local_leducon', $avgmins),
                'detail' => get_string('insight_ts_highavg_detail', 'local_leducon'),
            ];
        } elseif ($avgmins < 30) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'warning',
                'title'  => get_string('insight_ts_lowavg', 'local_leducon', $avgmins),
                'detail' => get_string('insight_ts_lowavg_detail', 'local_leducon'),
            ];
        }

        if ($heavy > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xAA",
                'type'   => 'info',
                'title'  => get_string('insight_ts_heavy', 'local_leducon', $heavy),
                'detail' => get_string('insight_ts_heavy_detail', 'local_leducon'),
            ];
        }

        if ($light > 0 && $totalusers > 5) {
            $lightpct = round($light / $totalusers * 100);
            if ($lightpct >= 30) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x93\x89",
                    'type'   => 'danger',
                    'title'  => get_string('insight_ts_light', 'local_leducon', $lightpct),
                    'detail' => get_string('insight_ts_light_detail', 'local_leducon', $light),
                ];
            }
        }

        return $insights;
    }

    public function get_summary(): array {
        $data        = $this->get_data();
        $totalevents = array_sum(array_column($data, 'events'));
        $totalmins   = $totalevents * self::MINS_PER_EVENT;
        return [
            ['label' => get_string('ts_events',      'local_leducon'), 'value' => $totalevents],
            ['label' => get_string('ts_est_minutes', 'local_leducon'), 'value' => $totalmins],
        ];
    }
}
