<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Skills Coverage report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class skills_report extends base_report {

    public function get_name(): string {
        return get_string('report_skills', 'local_leducon');
    }

    public function get_required_capability(): string {
        return 'local/leducon:viewskills';
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
            'skill'         => ['label' => get_string('skillsreport_col_skill',    'local_leducon'), 'sortable' => true],
            'course_count'  => ['label' => get_string('skillsreport_col_courses',  'local_leducon'), 'sortable' => true],
            'learner_count' => ['label' => get_string('skillsreport_col_learners', 'local_leducon'), 'sortable' => true],
            'coverage'      => ['label' => get_string('skillsreport_col_coverage', 'local_leducon'), 'sortable' => true, 'html' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $cohortid = (int) $this->filter('cohortid', 0);

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND cc.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $skillrows = [];
        try {
            $skillrows = $DB->get_records_sql(
                "SELECT s.id, s.name, s.description,
                        COUNT(DISTINCT cs.courseid)  AS course_count,
                        COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                            OR (gg_s.finalgrade IS NOT NULL AND gi_s.grademax > 0 AND gg_s.finalgrade / gi_s.grademax * 100 >= 100)
                            THEN cc.userid ELSE NULL END) AS learner_count
                   FROM {local_leducon_skills} s
                   JOIN {local_leducon_course_skills} cs ON cs.skillid = s.id
              LEFT JOIN {course_completions} cc ON cc.course = cs.courseid
                                               {$cohortjoin}
              LEFT JOIN {grade_items} gi_s ON gi_s.courseid = cs.courseid AND gi_s.itemtype = 'course'
              LEFT JOIN {grade_grades} gg_s ON gg_s.itemid = gi_s.id AND gg_s.userid = cc.userid
                  GROUP BY s.id, s.name, s.description
                  ORDER BY learner_count DESC, s.name ASC",
                $cohortparams
            );
        } catch (\dml_exception $e) {
            debugging('Skills report query error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        // Total learners for coverage %.
        $totallearners = 0;
        try {
            $totallearners = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ue.userid)
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
                   JOIN {user}  u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0",
                ['siteid' => SITEID]
            );
        } catch (\dml_exception $e) {
            debugging('Skills report enrolment count error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $rows = [];
        foreach ($skillrows as $sr) {
            $pct    = $totallearners > 0 ? round($sr->learner_count / $totallearners * 100, 1) : 0;
            $pctint = min(100, max(0, (int) $pct));
            $bar    = \html_writer::tag('div', '', [
                'class' => 'ld-progress-bar ld-bg-' . ($pct >= 75 ? 'success' : ($pct >= 40 ? 'warning' : 'danger')),
                'style' => "width:{$pctint}%",
            ]);
            $barcell = \html_writer::tag('div', $bar, ['class' => 'ld-progress']) .
                \html_writer::tag('small', $pct . '%', ['class' => 'text-muted']);

            $rows[] = [
                'skill'         => $sr->name . ($sr->description
                    ? \html_writer::tag('div', s($sr->description), ['class' => 'small text-muted'])
                    : ''),
                'course_count'  => (int) $sr->course_count,
                'learner_count' => (int) $sr->learner_count,
                'coverage'      => $barcell,
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        $total = count($data);
        if ($total === 0) {
            return $insights;
        }

        $zerolearners = 0;
        $highcoverage = 0;
        $topskill = null;
        $topcount = 0;

        foreach ($data as $row) {
            $count = (int)$row['learner_count'];
            if ($count === 0) {
                $zerolearners++;
            }
            if (strpos($row['coverage'], 'ld-bg-success') !== false) {
                $highcoverage++;
            }
            if ($count > $topcount) {
                $topcount = $count;
                $topskill = strip_tags($row['skill']);
            }
        }

        // Skills gap alert.
        if ($zerolearners > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xB4",
                'type'   => 'danger',
                'title'  => get_string('insight_skills_gap', 'local_leducon', $zerolearners),
                'detail' => get_string('insight_skills_gap_detail', 'local_leducon'),
            ];
        }

        // Strong coverage.
        if ($highcoverage > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xAA",
                'type'   => 'success',
                'title'  => get_string('insight_skills_strong', 'local_leducon', $highcoverage),
                'detail' => get_string('insight_skills_strong_detail', 'local_leducon'),
            ];
        }

        // Most popular skill.
        if ($topskill && $topcount > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x85",
                'type'   => 'info',
                'title'  => get_string('insight_skills_top', 'local_leducon', $topskill),
                'detail' => get_string('insight_skills_top_detail', 'local_leducon', $topcount),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        $totalskills = count($data);
        $totallearners = 0;
        $totalskillgains = 0;
        foreach ($data as $row) {
            $totalskillgains += (int)$row['learner_count'];
        }

        // Learners with at least one skill.
        global $DB;
        try {
            $totallearners = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                    OR (gg_i.finalgrade IS NOT NULL AND gi_i.grademax > 0 AND gg_i.finalgrade / gi_i.grademax * 100 >= 100)
                    THEN cc.userid ELSE NULL END)
                   FROM {course_completions} cc
                   JOIN {local_leducon_course_skills} cs ON cs.courseid = cc.course
              LEFT JOIN {grade_items} gi_i ON gi_i.courseid = cc.course AND gi_i.itemtype = 'course'
              LEFT JOIN {grade_grades} gg_i ON gg_i.itemid = gi_i.id AND gg_i.userid = cc.userid"
            );
        } catch (\dml_exception $e) {
            $totallearners = 0;
        }

        $avgskills = $totallearners > 0 ? round($totalskillgains / $totallearners, 1) : 0;

        return [
            ['label' => get_string('skillsreport_total_skills',  'local_leducon'), 'value' => $totalskills,   'color' => 'primary'],
            ['label' => get_string('skillsreport_total_learners','local_leducon'), 'value' => $totallearners,  'color' => 'success'],
            ['label' => get_string('skillsreport_avg_skills',    'local_leducon'), 'value' => $avgskills,      'color' => 'info'],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        $top5 = array_slice($data, 0, 5);
        if (empty($top5)) {
            return null;
        }

        $labels = array_map(function($r) { return mb_substr($r['skill'], 0, 30); }, $top5);
        // Strip any HTML from labels for chart.
        $labels = array_map('strip_tags', $labels);
        $values = array_map(function($r) { return (int)$r['learner_count']; }, $top5);

        try {
            $chart = new \core\chart_bar();
            $chart->set_horizontal(true);
            $series = new \core\chart_series(
                get_string('skillsreport_col_learners', 'local_leducon'),
                $values
            );
            $series->set_color('#4e73df');
            $chart->add_series($series);
            $chart->set_labels($labels);
            return \html_writer::tag('h6', s(get_string('skillsreport_top_skills', 'local_leducon')),
                    ['class' => 'text-muted mb-2']) . $OUTPUT->render($chart);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
