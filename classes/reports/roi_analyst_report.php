<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * ROI Analyst Detail report — per-course ROI breakdown.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roi_analyst_report extends base_report {

    public function get_name(): string {
        return get_string('report_roi_analyst', 'local_leducon');
    }

    public function get_required_capability(): string {
        return 'local/leducon:viewanalytics';
    }

    public function get_filter_options(): array {
        return [
            'show_course'   => false,
            'show_category' => false,
            'show_cohort'   => true,
            'show_org'      => true,
        ];
    }

    public function get_columns(): array {
        return [
            'course'      => ['label' => get_string('roi_col_course',      'local_leducon'), 'sortable' => true, 'html' => true],
            'currency'    => ['label' => get_string('roi_col_currency',    'local_leducon'), 'sortable' => true],
            'enrolled'    => ['label' => get_string('roi_col_enrolled',    'local_leducon'), 'sortable' => true],
            'completions' => ['label' => get_string('roi_col_completions', 'local_leducon'), 'sortable' => true],
            'comp_rate'   => ['label' => get_string('roi_col_rate',        'local_leducon'), 'sortable' => true, 'html' => true],
            'total_cost'  => ['label' => get_string('roi_col_cost',        'local_leducon'), 'sortable' => true],
            'total_value' => ['label' => get_string('roi_col_value',       'local_leducon'), 'sortable' => true],
            'net_return'  => ['label' => get_string('roi_col_net',         'local_leducon'), 'sortable' => true, 'html' => true],
            'roi_pct'     => ['label' => get_string('roi_col_roi',         'local_leducon'), 'sortable' => true, 'html' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $cohortid = (int) $this->filter('cohortid', 0);

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $coursedata = [];
        try {
            $coursedata = $DB->get_records_sql(
                "SELECT c.id, c.fullname,
                        cat.name AS catname,
                        cc_cost.costperlearner,
                        cc_cost.valuepercompletion,
                        cc_cost.currency,
                        COUNT(DISTINCT ue.userid) AS enrolled,
                        COUNT(DISTINCT CASE WHEN comp.timecompleted IS NOT NULL THEN comp.userid END) AS completions
                   FROM {course} c
                   JOIN {course_categories} cat          ON cat.id = c.category
                   JOIN {local_leducon_course_costs} cc_cost ON cc_cost.courseid = c.id
                   JOIN {enrol} e                        ON e.courseid = c.id
                   JOIN {user_enrolments} ue             ON ue.enrolid = e.id
                                                            {$cohortjoin}
                   JOIN {user} u                         ON u.id = ue.userid
                                                         AND u.deleted = 0 AND u.suspended = 0
              LEFT JOIN {course_completions} comp        ON comp.course = c.id AND comp.userid = ue.userid
                  WHERE c.id <> :siteid
                  GROUP BY c.id, c.fullname, cat.name,
                           cc_cost.costperlearner, cc_cost.valuepercompletion, cc_cost.currency
                  ORDER BY c.fullname ASC",
                array_merge(['siteid' => SITEID], $cohortparams)
            );
        } catch (\dml_exception $e) {
            debugging('ROI analyst report query error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        $rows = [];
        foreach ($coursedata as $cd) {
            $enrolled    = (int) $cd->enrolled;
            $completions = (int) $cd->completions;
            $costpl      = (float) $cd->costperlearner;
            $valuepc     = (float) $cd->valuepercompletion > 0
                ? (float) $cd->valuepercompletion
                : $costpl * 1.5;
            $currency = $cd->currency ?: 'USD';

            $total_cost  = $enrolled * $costpl;
            $total_value = $completions * $valuepc;
            $net_return  = $total_value - $total_cost;
            $roi_pct     = $total_cost > 0
                ? round($net_return / $total_cost * 100, 1)
                : null;
            $comp_rate   = $enrolled > 0 ? round($completions / $enrolled * 100, 1) : 0.0;

            // Format display values.
            $courselink = \html_writer::link(
                new \moodle_url('/course/view.php', ['id' => $cd->id]),
                s(format_string($cd->fullname))
            ) . \html_writer::tag('div', s(format_string($cd->catname)), ['class' => 'small text-muted']);

            $pctint  = min(100, max(0, (int) round($comp_rate)));
            $bar     = \html_writer::tag('div', '', [
                'class' => 'ld-progress-bar ld-bg-' . ($comp_rate >= 75 ? 'success' : ($comp_rate >= 40 ? 'warning' : 'danger')),
                'style' => "width:{$pctint}%",
            ]);
            $ratecell = \html_writer::tag('div', $bar, ['class' => 'ld-progress']) .
                \html_writer::tag('small', $comp_rate . '%', ['class' => 'text-muted']);

            $netclass = $net_return >= 0 ? 'text-success' : 'text-danger';
            $nethtml  = \html_writer::tag('span',
                $this->fmt_money($net_return, $currency), ['class' => $netclass]);

            $roiclass = $roi_pct === null ? 'text-muted' : ($roi_pct >= 0 ? 'text-success font-weight-bold' : 'text-danger font-weight-bold');
            $roistr   = $roi_pct !== null ? ($roi_pct >= 0 ? '+' : '') . $roi_pct . '%' : "\xE2\x80\x94";
            $roihtml  = \html_writer::tag('span', $roistr, ['class' => $roiclass]);

            $rows[] = [
                'course'      => $courselink,
                'currency'    => $currency,
                'enrolled'    => number_format($enrolled),
                'completions' => number_format($completions),
                'comp_rate'   => $ratecell,
                'total_cost'  => $this->fmt_money($total_cost, $currency),
                'total_value' => $this->fmt_money($total_value, $currency),
                'net_return'  => $nethtml,
                'roi_pct'     => $roihtml,
                '_roi_raw'    => $roi_pct,
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $positive = 0;
        $negative = 0;
        $bestroi = null;
        $bestname = '';
        $worstroi = null;
        $worstname = '';

        foreach ($data as $row) {
            $roi = $row['_roi_raw'];
            if ($roi === null) {
                continue;
            }
            if ($roi >= 0) {
                $positive++;
            } else {
                $negative++;
            }
            if ($bestroi === null || $roi > $bestroi) {
                $bestroi = $roi;
                $bestname = strip_tags($row['course']);
            }
            if ($worstroi === null || $roi < $worstroi) {
                $worstroi = $roi;
                $worstname = strip_tags($row['course']);
            }
        }

        $totalwithdata = $positive + $negative;

        if ($totalwithdata > 0 && $negative === 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xB0",
                'type'   => 'success',
                'title'  => get_string('insight_roi_allpositive', 'local_leducon'),
                'detail' => get_string('insight_roi_allpositive_detail', 'local_leducon', $positive),
            ];
        } elseif ($negative > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x89",
                'type'   => 'danger',
                'title'  => get_string('insight_roi_negative', 'local_leducon', $negative),
                'detail' => get_string('insight_roi_negative_detail', 'local_leducon', $worstname),
            ];
        }

        if ($bestroi !== null && $bestroi > 50) {
            $a = new \stdClass();
            $a->name = $bestname;
            $a->roi = $bestroi;
            $insights[] = [
                'icon'   => "\xF0\x9F\x8C\x9F",
                'type'   => 'success',
                'title'  => get_string('insight_roi_best', 'local_leducon', $a),
                'detail' => get_string('insight_roi_best_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }

        return [
            ['label' => get_string('roi_kpi_courses', 'local_leducon'), 'value' => count($data), 'color' => 'secondary'],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        $chartrows = array_filter($data, function($r) { return $r['_roi_raw'] !== null; });
        usort($chartrows, function($a, $b) { return ($b['_roi_raw'] ?? -9999) <=> ($a['_roi_raw'] ?? -9999); });
        $chartrows = array_slice($chartrows, 0, 10);

        if (empty($chartrows)) {
            return null;
        }

        $labels = array_map(function($r) { return mb_substr(strip_tags($r['course']), 0, 28); }, $chartrows);
        $values = array_map(function($r) { return $r['_roi_raw']; }, $chartrows);
        $colors = array_map(function($r) { return $r['_roi_raw'] >= 0 ? '#28a745' : '#dc3545'; }, $chartrows);

        try {
            $chart = new \core\chart_bar();
            $chart->set_horizontal(true);
            $series = new \core\chart_series(get_string('roi_col_roi', 'local_leducon'), $values);
            $series->set_colors($colors);
            $chart->add_series($series);
            $chart->set_labels($labels);
            return \html_writer::tag('h6', s(get_string('roi_chart_title', 'local_leducon')),
                    ['class' => 'text-muted mb-2']) . $OUTPUT->render($chart);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fmt_money($amount, $currency): string {
        $symbols = ['USD' => '$', 'GBP' => "\xC2\xA3", 'EUR' => "\xE2\x82\xAC", 'NGN' => "\xE2\x82\xA6", 'ZAR' => 'R'];
        $sym = isset($symbols[$currency]) ? $symbols[$currency] : ($currency . ' ');
        return $sym . number_format((float) $amount, 2);
    }
}
