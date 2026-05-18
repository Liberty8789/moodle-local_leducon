<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Mandatory Training Compliance report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compliance_report extends base_report {

    public function get_name(): string {
        return get_string('report_compliance', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'fullname'      => ['label' => get_string('comp_fullname',      'local_leducon'), 'sortable' => true],
            'email'         => ['label' => get_string('comp_email',         'local_leducon'), 'sortable' => true],
            'coursename'    => ['label' => get_string('comp_coursename',    'local_leducon'), 'sortable' => true],
            'category'      => ['label' => get_string('comp_category',      'local_leducon'), 'sortable' => true],
            'status'        => ['label' => get_string('comp_status',        'local_leducon'), 'sortable' => true],
            'timecompleted' => ['label' => get_string('comp_timecompleted', 'local_leducon'), 'sortable' => true],
            'daysenrolled'  => ['label' => get_string('comp_daysenrolled',  'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $courseid   = (int) $this->filter('courseid', 0);
        $categoryid = (int) $this->filter('categoryid', 0);
        $cohortid   = (int) $this->filter('cohortid', 0);
        $from       = $this->resolve_from();
        $to         = $this->resolve_to();
        $now        = time();

        $coursewhere  = 'AND c.id <> :siteid';
        $courseparams = ['siteid' => SITEID];

        if ($courseid > 0) {
            $coursewhere .= ' AND c.id = :courseid';
            $courseparams['courseid'] = $courseid;
        } elseif ($categoryid > 0) {
            $coursewhere .= ' AND c.category = :categoryid';
            $courseparams['categoryid'] = $categoryid;
        }

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $activesql = $this->user_active_sql('u');

        $sql = "SELECT u.id AS userid, c.id AS courseid,
                       u.firstname, u.lastname, u.email,
                       c.fullname     AS coursename,
                       cat.name       AS category,
                       ue.timecreated AS timeenrolled,
                       cc.timecompleted
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e            ON e.id = ue.enrolid
                  JOIN {course} c           ON c.id = e.courseid {$coursewhere}
                  JOIN {course_categories} cat ON cat.id = c.category
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
                 WHERE u.deleted = 0 {$activesql}
                   {$cohortjoin}
              ORDER BY u.lastname ASC, u.firstname ASC, c.fullname ASC";

        $params = array_merge($courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $completed = !empty($r->timecompleted);
            $daysenr   = $r->timeenrolled > 0 ? (int) round(($now - $r->timeenrolled) / DAYSECS) : '-';

            $rows[] = [
                'fullname'      => fullname($r),
                'email'         => $r->email,
                'coursename'    => format_string($r->coursename),
                'category'      => format_string($r->category),
                'status'        => $completed
                    ? get_string('comp_status_completed',   'local_leducon')
                    : get_string('comp_status_outstanding', 'local_leducon'),
                'timecompleted' => $completed ? $this->fmt_date((int) $r->timecompleted) : '-',
                'daysenrolled'  => $daysenr,
            ];
        }

        return $rows;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }
        $total       = count($data);
        $completed   = count(array_filter($data, function($r) {
            return $r['timecompleted'] !== '-';
        }));
        $outstanding = $total - $completed;
        $rate        = $total > 0 ? number_format($completed / $total * 100, 1) . '%' : '-';

        return [
            ['label' => get_string('comp_total',       'local_leducon'), 'value' => $total],
            ['label' => get_string('comp_completed',   'local_leducon'), 'value' => $completed],
            ['label' => get_string('comp_outstanding', 'local_leducon'), 'value' => $outstanding],
            ['label' => get_string('comp_rate',        'local_leducon'), 'value' => $rate],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $total       = count($data);
        $completed   = 0;
        $outstanding = 0;
        $overdue90   = 0;

        foreach ($data as $row) {
            if ($row['timecompleted'] !== '-') {
                $completed++;
            } else {
                $outstanding++;
                if (is_numeric($row['daysenrolled']) && (int)$row['daysenrolled'] > 90) {
                    $overdue90++;
                }
            }
        }

        $rate = $total > 0 ? round($completed / $total * 100, 1) : 0;

        if ($rate >= $this->threshold('insight_compliance_high', 90)) {
            $insights[] = [
                'icon'   => "\xE2\x9C\x85",
                'type'   => 'success',
                'title'  => get_string('insight_comp_highrate', 'local_leducon', $rate),
                'detail' => get_string('insight_comp_highrate_detail', 'local_leducon'),
            ];
        } elseif ($rate < $this->threshold('insight_compliance_low', 50) && $total >= 10) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x9A\xA8",
                'type'   => 'danger',
                'title'  => get_string('insight_comp_lowrate', 'local_leducon', $rate),
                'detail' => get_string('insight_comp_lowrate_detail', 'local_leducon', $outstanding),
            ];
        }

        if ($overdue90 > 0) {
            $insights[] = [
                'icon'   => "\xE2\x8F\xB0",
                'type'   => 'warning',
                'title'  => get_string('insight_comp_overdue', 'local_leducon', $overdue90),
                'detail' => get_string('insight_comp_overdue_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        $coursestats = [];
        foreach ($data as $row) {
            $cn = $row['coursename'];
            if (!isset($coursestats[$cn])) {
                $coursestats[$cn] = ['total' => 0, 'completed' => 0];
            }
            $coursestats[$cn]['total']++;
            if ($row['timecompleted'] !== '-') {
                $coursestats[$cn]['completed']++;
            }
        }

        $stats = [];
        foreach ($coursestats as $name => $s) {
            $stats[] = [
                'name' => $name,
                'pct'  => $s['total'] > 0 ? round($s['completed'] / $s['total'] * 100, 1) : 0.0,
            ];
        }
        usort($stats, function($a, $b) { return $a['pct'] <=> $b['pct']; });
        $stats = array_slice($stats, 0, 12);

        $labels = [];
        $values = [];
        foreach ($stats as $s) {
            $labels[] = mb_substr($s['name'], 0, 28);
            $values[] = $s['pct'];
        }

        $chart = new \core\chart_bar();
        $series = new \core\chart_series(get_string('comp_rate', 'local_leducon'), $values);
        $series->set_color('#2563eb');
        $chart->add_series($series);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }
}
