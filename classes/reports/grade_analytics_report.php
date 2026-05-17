<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Grade Analytics report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_analytics_report extends base_report {

    public function get_name(): string {
        return get_string('report_grade_analytics', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'coursename' => ['label' => get_string('ga_coursename', 'local_leducon'), 'sortable' => true],
            'category'   => ['label' => get_string('ga_category',   'local_leducon'), 'sortable' => true],
            'enrolled'   => ['label' => get_string('ga_enrolled',   'local_leducon'), 'sortable' => true],
            'graded'     => ['label' => get_string('ga_graded',     'local_leducon'), 'sortable' => true],
            'avggrade'   => ['label' => get_string('ga_avggrade',   'local_leducon'), 'sortable' => true],
            'highgrade'  => ['label' => get_string('ga_highgrade',  'local_leducon'), 'sortable' => true],
            'lowgrade'   => ['label' => get_string('ga_lowgrade',   'local_leducon'), 'sortable' => true],
            'stddev'     => ['label' => get_string('ga_stddev',     'local_leducon'), 'sortable' => false],
        ];
    }

    public function get_data(): array {
        global $DB;

        $from       = $this->resolve_from();
        $to         = $this->resolve_to();
        $courseid   = (int) $this->filter('courseid', 0);
        $categoryid = (int) $this->filter('categoryid', 0);
        $cohortid     = (int) $this->filter('cohortid', 0);
        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND gg.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $coursewhere  = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursewhere  = 'AND c.id = :courseid';
            $courseparams = ['courseid' => $courseid];
        } elseif ($categoryid > 0) {
            $coursewhere  = 'AND c.category = :categoryid';
            $courseparams = ['categoryid' => $categoryid];
        }

        $sql = "SELECT c.id,
                       c.fullname                              AS coursename,
                       cat.name                               AS category,
                       COUNT(DISTINCT ue.userid)              AS enrolled,
                       COUNT(DISTINCT gg.userid)               AS graded,
                       AVG(gg.finalgrade / NULLIF(gi.grademax,0) * 100) AS avggrade,
                       MAX(gg.finalgrade / NULLIF(gi.grademax,0) * 100) AS highgrade,
                       MIN(gg.finalgrade / NULLIF(gi.grademax,0) * 100) AS lowgrade
                  FROM {course} c
                  JOIN {course_categories} cat    ON cat.id = c.category
                  JOIN {enrol} e                  ON e.courseid = c.id
                  JOIN {user_enrolments} ue       ON ue.enrolid = e.id
                  JOIN {grade_items} gi           ON gi.courseid = c.id
                                                 AND gi.itemtype = 'course'
                  JOIN {grade_grades} gg          ON gg.itemid = gi.id
                                                 AND gg.finalgrade IS NOT NULL
                                                 AND gg.timemodified BETWEEN :from AND :to
                 WHERE c.id <> :siteid
                   {$coursewhere}
                   {$cohortjoin}
              GROUP BY c.id, c.fullname, cat.name
                HAVING COUNT(DISTINCT gg.userid) > 0
              ORDER BY c.fullname ASC";

        $params = array_merge([
            'from'   => $from,
            'to'     => $to,
            'siteid' => SITEID,
        ], $courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $rows[] = [
                'coursename' => format_string($r->coursename),
                'category'   => format_string($r->category),
                'enrolled'   => (int) $r->enrolled,
                'graded'     => (int) $r->graded,
                'avggrade'   => $this->fmt_pct($r->avggrade) . '%',
                'highgrade'  => $this->fmt_pct($r->highgrade) . '%',
                'lowgrade'   => $this->fmt_pct($r->lowgrade) . '%',
                'stddev'     => '-',
            ];
        }

        return $rows;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }
        $avgs = [];
        foreach ($data as $row) {
            $val = rtrim($row['avggrade'], '%');
            if (is_numeric($val)) {
                $avgs[] = (float) $val;
            }
        }
        $overall = count($avgs) > 0 ? array_sum($avgs) / count($avgs) : null;
        $pct     = $overall !== null ? min(100, round($overall, 1)) : 0;
        return [
            ['label' => get_string('ga_coursename', 'local_leducon'), 'value' => count($data)],
            ['label' => get_string('ga_avg_grade',  'local_leducon'),
             'value' => $overall !== null ? number_format($overall, 1) . '%' : '-',
             'pct'   => $pct],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $avgs = [];
        $lowcourses = 0;
        $highcourses = 0;
        foreach ($data as $row) {
            $val = (float) rtrim($row['avggrade'], '%');
            $avgs[] = $val;
            if ($val < 50) {
                $lowcourses++;
            }
            if ($val >= 80) {
                $highcourses++;
            }
        }

        $overall = count($avgs) > 0 ? round(array_sum($avgs) / count($avgs), 1) : 0;

        if ($overall >= 75) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8C\x9F",
                'type'   => 'success',
                'title'  => get_string('insight_ga_highavg', 'local_leducon', $overall),
                'detail' => get_string('insight_ga_highavg_detail', 'local_leducon'),
            ];
        } elseif ($overall < 50 && count($avgs) >= 3) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'danger',
                'title'  => get_string('insight_ga_lowavg', 'local_leducon', $overall),
                'detail' => get_string('insight_ga_lowavg_detail', 'local_leducon'),
            ];
        }

        if ($lowcourses > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x89",
                'type'   => 'warning',
                'title'  => get_string('insight_ga_belowpass', 'local_leducon', $lowcourses),
                'detail' => get_string('insight_ga_belowpass_detail', 'local_leducon'),
            ];
        }

        if ($highcourses > 0 && count($data) > 1) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x86",
                'type'   => 'info',
                'title'  => get_string('insight_ga_excellent', 'local_leducon', $highcourses),
                'detail' => get_string('insight_ga_excellent_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;
        if (empty($data)) {
            return null;
        }
        $data   = array_slice($data, 0, 12);
        $labels = [];
        $avgs   = [];
        $highs  = [];
        $lows   = [];
        foreach ($data as $row) {
            $labels[] = mb_substr($row['coursename'], 0, 28);
            $avgs[]   = (float) rtrim($row['avggrade'],  '%');
            $highs[]  = (float) rtrim($row['highgrade'], '%');
            $lows[]   = (float) rtrim($row['lowgrade'],  '%');
        }
        $chart = new \core\chart_bar();
        $s1 = new \core\chart_series(get_string('ga_avggrade',  'local_leducon'), $avgs);
        $s2 = new \core\chart_series(get_string('ga_highgrade', 'local_leducon'), $highs);
        $s3 = new \core\chart_series(get_string('ga_lowgrade',  'local_leducon'), $lows);
        $s1->set_color('#4e73df');
        $s2->set_color('#28a745');
        $s3->set_color('#e74a3b');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->add_series($s3);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }

    public function get_trend_data(): array {
        global $DB;

        $now   = time();
        $start = $now - 365 * DAYSECS;

        $rs = $DB->get_recordset_sql(
            "SELECT gg.id, gg.timemodified FROM {grade_grades} gg
              WHERE gg.finalgrade IS NOT NULL
                AND gg.timemodified BETWEEN :start AND :now",
            ['start' => $start, 'now' => $now]
        );

        $monthly = [];
        foreach ($rs as $r) {
            $key = date('Y-m', (int) $r->timemodified);
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
