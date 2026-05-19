<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Department / Category Performance report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_report extends base_report {

    public function get_name(): string {
        return get_string('report_category', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'categoryname'   => ['label' => get_string('cat_categoryname',   'local_leducon'), 'sortable' => true],
            'courses'        => ['label' => get_string('cat_courses',        'local_leducon'), 'sortable' => true],
            'enrolled'       => ['label' => get_string('cat_enrolled',       'local_leducon'), 'sortable' => true],
            'completed'      => ['label' => get_string('cat_completed',      'local_leducon'), 'sortable' => true],
            'completionrate' => ['label' => get_string('cat_completionrate', 'local_leducon'), 'sortable' => true],
            'avggrade'       => ['label' => get_string('cat_avggrade',       'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $categoryid = (int) $this->filter('categoryid', 0);
        $cohortid   = (int) $this->filter('cohortid', 0);

        $catwhere  = '';
        $catparams = ['siteid' => SITEID];

        if ($categoryid > 0) {
            $catwhere = 'AND cat.id = :categoryid';
            $catparams['categoryid'] = $categoryid;
        }

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $activesql = $this->user_active_sql('u');

        $sql = "SELECT cat.id,
                       cat.name                                                   AS categoryname,
                       COUNT(DISTINCT c.id)                                        AS courses,
                       COUNT(DISTINCT ue.userid)                                   AS enrolled,
                       COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                                OR (gg.finalgrade IS NOT NULL AND gi.grademax > 0 AND gg.finalgrade / gi.grademax * 100 >= 100)
                                THEN cc.userid ELSE NULL END) AS completed,
                       AVG(CASE WHEN gg.finalgrade IS NOT NULL AND gi.grademax > 0
                                THEN gg.finalgrade / gi.grademax * 100
                                ELSE NULL END)                                     AS avggrade
                  FROM {course_categories} cat
                  JOIN {course} c          ON c.category = cat.id
                                          AND c.id <> :siteid
                  JOIN {enrol} e           ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                           {$cohortjoin}
                  JOIN {user} u            ON u.id = ue.userid
                                          AND u.deleted = 0 {$activesql}
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
             LEFT JOIN {grade_items} gi    ON gi.courseid = c.id AND gi.itemtype = 'course'
             LEFT JOIN {grade_grades} gg   ON gg.itemid = gi.id AND gg.userid = ue.userid
                 WHERE 1=1 {$catwhere}
              GROUP BY cat.id, cat.name
              ORDER BY cat.name ASC";

        $params = array_merge($catparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $enrolled  = (int) $r->enrolled;
            $completed = (int) $r->completed;
            $rows[] = [
                'categoryname'   => format_string($r->categoryname),
                'courses'        => (int) $r->courses,
                'enrolled'       => $enrolled,
                'completed'      => $completed,
                'completionrate' => $enrolled > 0
                    ? $this->fmt_pct($completed / $enrolled * 100) . '%'
                    : '-',
                'avggrade'       => $r->avggrade !== null
                    ? $this->fmt_pct((float) $r->avggrade) . '%'
                    : '-',
            ];
        }

        return $rows;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }
        $totalcats = count($data);
        $enrolled  = array_sum(array_column($data, 'enrolled'));
        $completed = array_sum(array_column($data, 'completed'));
        $rate      = $enrolled > 0 ? number_format($completed / $enrolled * 100, 1) . '%' : '-';

        return [
            ['label' => get_string('cat_total_categories', 'local_leducon'), 'value' => $totalcats],
            ['label' => get_string('cat_enrolled',         'local_leducon'), 'value' => $enrolled],
            ['label' => get_string('cat_completionrate',   'local_leducon'), 'value' => $rate],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $rates = [];
        $bestcat = '';
        $bestrate = 0;
        $worstcat = '';
        $worstrate = 100;

        foreach ($data as $row) {
            $enrolled  = (int)$row['enrolled'];
            $completed = (int)$row['completed'];
            if ($enrolled > 0) {
                $rate = round($completed / $enrolled * 100, 1);
                $rates[] = $rate;
                if ($rate > $bestrate) {
                    $bestrate = $rate;
                    $bestcat  = $row['categoryname'];
                }
                if ($rate < $worstrate) {
                    $worstrate = $rate;
                    $worstcat  = $row['categoryname'];
                }
            }
        }

        if (!empty($rates)) {
            $avgrate = round(array_sum($rates) / count($rates), 1);
            $spread  = $bestrate - $worstrate;

            if ($spread >= $this->threshold('insight_cat_spread', 40) && count($rates) >= 3) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x93\x8A",
                    'type'   => 'warning',
                    'title'  => get_string('insight_cat_spread', 'local_leducon', round($spread)),
                    'detail' => get_string('insight_cat_spread_detail', 'local_leducon'),
                ];
            }

            if ($bestcat && $bestrate >= $this->threshold('insight_cat_best', 70)) {
                $a = new \stdClass();
                $a->name = $bestcat;
                $a->rate = $bestrate;
                $insights[] = [
                    'icon'   => "\xF0\x9F\x8F\x86",
                    'type'   => 'success',
                    'title'  => get_string('insight_cat_best', 'local_leducon', $a),
                    'detail' => get_string('insight_cat_best_detail', 'local_leducon'),
                ];
            }

            if ($worstcat && $worstrate < $this->threshold('insight_cat_worst', 20) && count($rates) >= 3) {
                $a = new \stdClass();
                $a->name = $worstcat;
                $a->rate = $worstrate;
                $insights[] = [
                    'icon'   => "\xF0\x9F\x94\xB4",
                    'type'   => 'danger',
                    'title'  => get_string('insight_cat_worst', 'local_leducon', $a),
                    'detail' => get_string('insight_cat_worst_detail', 'local_leducon'),
                ];
            }
        }

        return $insights;
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        $labels    = [];
        $enrolled  = [];
        $completed = [];

        foreach (array_slice($data, 0, 12) as $row) {
            $labels[]    = mb_substr($row['categoryname'], 0, 28);
            $enrolled[]  = (int) $row['enrolled'];
            $completed[] = (int) $row['completed'];
        }

        $chart = new \core\chart_bar();
        $s1 = new \core\chart_series(get_string('cat_enrolled',  'local_leducon'), $enrolled);
        $s2 = new \core\chart_series(get_string('cat_completed', 'local_leducon'), $completed);
        $s1->set_color('#93c5fd');
        $s2->set_color('#2563eb');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }
}
