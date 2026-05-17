<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Enrollment report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_report extends base_report {

    public function get_name(): string {
        return get_string('report_enrollment', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'coursename'    => ['label' => get_string('en_coursename',    'local_leducon'), 'sortable' => true],
            'category'      => ['label' => get_string('en_category',      'local_leducon'), 'sortable' => true],
            'totalenrolled' => ['label' => get_string('en_totalenrolled', 'local_leducon'), 'sortable' => true],
            'active'        => ['label' => get_string('en_active',        'local_leducon'), 'sortable' => true],
            'suspended'     => ['label' => get_string('en_suspended',     'local_leducon'), 'sortable' => true],
            'newthisperiod' => ['label' => get_string('en_newthisperiod', 'local_leducon'), 'sortable' => true],
            'method'        => ['label' => get_string('en_method',        'local_leducon'), 'sortable' => false],
        ];
    }

    public function get_data(): array {
        global $DB;

        $from       = $this->resolve_from();
        $to         = $this->resolve_to();
        $courseid   = (int) $this->filter('courseid', 0);
        $categoryid = (int) $this->filter('categoryid', 0);

        $coursewhere  = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursewhere  = 'AND c.id = :courseid';
            $courseparams = ['courseid' => $courseid];
        } elseif ($categoryid > 0) {
            $coursewhere  = 'AND c.category = :categoryid';
            $courseparams = ['categoryid' => $categoryid];
        }

        $cohortid     = (int) $this->filter('cohortid', 0);
        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $sql = "SELECT c.id,
                       c.fullname                                         AS coursename,
                       cat.name                                           AS category,
                       COUNT(DISTINCT ue.userid)                          AS totalenrolled,
                       COUNT(DISTINCT CASE WHEN ue.status = 0 THEN ue.userid ELSE NULL END) AS active,
                       COUNT(DISTINCT CASE WHEN ue.status = 1 THEN ue.userid ELSE NULL END) AS suspended,
                       COUNT(DISTINCT CASE WHEN ue.timecreated BETWEEN :from AND :to
                                THEN ue.userid ELSE NULL END)             AS newthisperiod
                  FROM {course} c
                  JOIN {course_categories} cat ON cat.id = c.category
                  JOIN {enrol} e               ON e.courseid = c.id
                  JOIN {user_enrolments} ue    ON ue.enrolid = e.id
                 WHERE c.id <> :siteid
                   {$coursewhere}
                   {$cohortjoin}
              GROUP BY c.id, c.fullname, cat.name
              ORDER BY totalenrolled DESC, c.fullname ASC";

        $params = array_merge([
            'from'   => $from,
            'to'     => $to,
            'siteid' => SITEID,
        ], $courseparams, $cohortparams);

        $methodsql = "SELECT e.courseid, e.enrol
                        FROM {enrol} e
                        JOIN {course} c ON c.id = e.courseid
                       WHERE c.id <> :siteid2
                             {$coursewhere}
                    GROUP BY e.courseid, e.enrol";
        $mparams = array_merge(['siteid2' => SITEID], $courseparams);

        $methods = [];
        foreach ($DB->get_records_sql($methodsql, $mparams) as $m) {
            $methods[$m->courseid][] = $m->enrol;
        }

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $rows[] = [
                'coursename'    => format_string($r->coursename),
                'category'      => format_string($r->category),
                'totalenrolled' => (int) $r->totalenrolled,
                'active'        => (int) $r->active,
                'suspended'     => (int) $r->suspended,
                'newthisperiod' => (int) $r->newthisperiod,
                'method'        => isset($methods[$r->id]) ? implode(', ', array_unique($methods[$r->id])) : '-',
            ];
        }

        return $rows;
    }

    public function get_summary(): array {
        global $DB;

        $from = $this->resolve_from();
        $to   = $this->resolve_to();

        $total = $DB->count_records_select(
            'user_enrolments',
            'timecreated BETWEEN :from AND :to',
            ['from' => $from, 'to' => $to]
        );

        return [
            ['label' => get_string('en_newthisperiod', 'local_leducon'), 'value' => $total],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalenrolled = array_sum(array_column($data, 'totalenrolled'));
        $totalnew      = array_sum(array_column($data, 'newthisperiod'));
        $totalsuspended = array_sum(array_column($data, 'suspended'));

        // Growth indicator.
        if ($totalnew > 0 && $totalenrolled > 0) {
            $growthpct = round($totalnew / $totalenrolled * 100, 1);
            if ($growthpct >= 10) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x93\x88",
                    'type'   => 'success',
                    'title'  => get_string('insight_en_growth', 'local_leducon', $growthpct),
                    'detail' => get_string('insight_en_growth_detail', 'local_leducon', $totalnew),
                ];
            }
        }

        // Zero new enrolments warning.
        if ($totalnew === 0 && $totalenrolled > 0) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'warning',
                'title'  => get_string('insight_en_nonew', 'local_leducon'),
                'detail' => get_string('insight_en_nonew_detail', 'local_leducon'),
            ];
        }

        // High suspension rate.
        if ($totalsuspended > 0 && $totalenrolled > 0) {
            $suspct = round($totalsuspended / $totalenrolled * 100, 1);
            if ($suspct >= 15) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x9A\xAB",
                    'type'   => 'danger',
                    'title'  => get_string('insight_en_suspended', 'local_leducon', $suspct),
                    'detail' => get_string('insight_en_suspended_detail', 'local_leducon', $totalsuspended),
                ];
            }
        }

        // Courses with very few enrolments.
        $lowcourses = 0;
        foreach ($data as $row) {
            if ((int)$row['totalenrolled'] < 5 && (int)$row['totalenrolled'] > 0) {
                $lowcourses++;
            }
        }
        if ($lowcourses > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x91\xA4",
                'type'   => 'info',
                'title'  => get_string('insight_en_lowenrol', 'local_leducon', $lowcourses),
                'detail' => get_string('insight_en_lowenrol_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_trend_data(): array {
        global $DB;

        $now   = time();
        $start = $now - 365 * DAYSECS;

        $rs = $DB->get_recordset_sql(
            "SELECT id, timecreated FROM {user_enrolments}
              WHERE timecreated BETWEEN :start AND :now",
            ['start' => $start, 'now' => $now]
        );

        $monthly = [];
        foreach ($rs as $r) {
            $key = date('Y-m', (int) $r->timecreated);
            $monthly[$key] = ($monthly[$key] ?? 0) + 1;
        }
        $rs->close();

        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts     = strtotime("-{$i} months", $now);
            $key    = date('Y-m', $ts);
            $result[] = ['month' => date('M Y', $ts), 'value' => $monthly[$key] ?? 0];
        }

        return $result;
    }
}
