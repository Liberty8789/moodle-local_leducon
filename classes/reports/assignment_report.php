<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Assignment Submissions report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_report extends base_report {

    public function get_name(): string {
        return get_string('report_assignment', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'assignname' => ['label' => get_string('ar_assignname', 'local_leducon'), 'sortable' => true],
            'coursename' => ['label' => get_string('ar_coursename', 'local_leducon'), 'sortable' => true],
            'duedate'    => ['label' => get_string('ar_duedate',    'local_leducon'), 'sortable' => true],
            'submitted'  => ['label' => get_string('ar_submitted',  'local_leducon'), 'sortable' => true],
            'ontime'     => ['label' => get_string('ar_ontime',     'local_leducon'), 'sortable' => true],
            'late'       => ['label' => get_string('ar_late',       'local_leducon'), 'sortable' => true],
            'graded'     => ['label' => get_string('ar_graded',     'local_leducon'), 'sortable' => true],
            'ungraded'   => ['label' => get_string('ar_ungraded',   'local_leducon'), 'sortable' => true],
            'avggrade'   => ['label' => get_string('ar_avggrade',   'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('assign')) {
            return [];
        }

        $categoryid = (int) $this->filter('categoryid', 0);
        $courseid   = (int) $this->filter('courseid',   0);
        $from       = $this->resolve_from();
        $to         = $this->resolve_to();

        $where  = 'c.id <> :siteid';
        $params = ['siteid' => SITEID, 'from' => $from, 'to' => $to];

        if ($courseid > 0) {
            $where .= ' AND c.id = :courseid';
            $params['courseid'] = $courseid;
        } elseif ($categoryid > 0) {
            $where .= ' AND c.category = :categoryid';
            $params['categoryid'] = $categoryid;
        }

        $cohortid     = (int) $this->filter('cohortid', 0);
        $asubcohort   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $asubcohort   = 'AND asub.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }
        $params = array_merge($params, $cohortparams);

        $assigns = $DB->get_records_sql(
            "SELECT a.id,
                    a.name        AS assignname,
                    c.fullname    AS coursename,
                    a.duedate,
                    a.grade       AS maxgrade,
                    COUNT(DISTINCT asub.userid)  AS submitted,
                    COUNT(DISTINCT CASE WHEN a.duedate > 0 AND asub.timemodified <= a.duedate
                             THEN asub.userid ELSE NULL END) AS ontime,
                    COUNT(DISTINCT CASE WHEN a.duedate > 0 AND asub.timemodified > a.duedate
                             THEN asub.userid ELSE NULL END) AS late,
                    COUNT(DISTINCT CASE WHEN ag.grade >= 0 THEN ag.userid ELSE NULL END) AS graded,
                    AVG(CASE WHEN ag.grade >= 0 AND a.grade > 0
                             THEN ag.grade / a.grade * 100
                             ELSE NULL END) AS avgpct
               FROM {assign} a
               JOIN {course} c              ON c.id = a.course AND {$where}
          LEFT JOIN {assign_submission} asub ON asub.assignment = a.id
                                            AND asub.status = 'submitted'
                                            AND asub.timemodified BETWEEN :from AND :to
                                            {$asubcohort}
          LEFT JOIN {assign_grades} ag       ON ag.assignment = a.id
                                            AND ag.userid = asub.userid
              GROUP BY a.id, a.name, c.fullname, a.duedate, a.grade
              ORDER BY c.fullname ASC, a.name ASC",
            $params
        );

        $rows = [];
        foreach ($assigns as $rec) {
            $sub    = (int) $rec->submitted;
            $graded = (int) $rec->graded;
            $rows[] = [
                'assignname' => $rec->assignname,
                'coursename' => $rec->coursename,
                'duedate'    => $rec->duedate ? $this->fmt_date($rec->duedate) : '-',
                'submitted'  => $sub,
                'ontime'     => (int) $rec->ontime,
                'late'       => (int) $rec->late,
                'graded'     => $graded,
                'ungraded'   => max(0, $sub - $graded),
                'avggrade'   => $rec->avgpct !== null
                                    ? $this->fmt_pct((float) $rec->avgpct) . '%'
                                    : '-',
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalsub    = array_sum(array_column($data, 'submitted'));
        $totallate   = array_sum(array_column($data, 'late'));
        $totalungrad = array_sum(array_column($data, 'ungraded'));

        // Late submissions warning.
        if ($totallate > 0 && $totalsub > 0) {
            $latepct = round($totallate / $totalsub * 100);
            if ($latepct >= 20) {
                $insights[] = [
                    'icon'   => "\xE2\x8F\xB0",
                    'type'   => 'warning',
                    'title'  => get_string('insight_ar_late', 'local_leducon', $latepct),
                    'detail' => get_string('insight_ar_late_detail', 'local_leducon', $totallate),
                ];
            }
        }

        // Grading backlog.
        if ($totalungrad > 10) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x9D",
                'type'   => 'danger',
                'title'  => get_string('insight_ar_ungraded', 'local_leducon', $totalungrad),
                'detail' => get_string('insight_ar_ungraded_detail', 'local_leducon'),
            ];
        }

        // On-time rate.
        $totalontime = array_sum(array_column($data, 'ontime'));
        if ($totalsub > 0) {
            $ontimepct = round($totalontime / $totalsub * 100);
            if ($ontimepct >= 90) {
                $insights[] = [
                    'icon'   => "\xE2\x9C\x85",
                    'type'   => 'success',
                    'title'  => get_string('insight_ar_ontime', 'local_leducon', $ontimepct),
                    'detail' => get_string('insight_ar_ontime_detail', 'local_leducon'),
                ];
            }
        }

        return $insights;
    }

    public function get_summary(): array {
        $data     = $this->get_data();
        $total    = count($data);
        $sub      = array_sum(array_column($data, 'submitted'));
        $ungraded = array_sum(array_column($data, 'ungraded'));
        $pct = $sub > 0 ? round(($sub - $ungraded) / $sub * 100, 1) : 0;
        return [
            ['label' => get_string('ar_total_assignments', 'local_leducon'), 'value' => $total],
            ['label' => get_string('ar_submitted',         'local_leducon'), 'value' => $sub],
            ['label' => get_string('ar_ungraded',          'local_leducon'), 'value' => $ungraded, 'pct' => $pct],
        ];
    }
}
