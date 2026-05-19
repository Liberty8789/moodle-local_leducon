<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Manager View report — cohort members' learning progress.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class managerview_report extends base_report {

    public function get_name(): string {
        return get_string('report_managerview', 'local_leducon');
    }

    public function get_required_capability(): string {
        return 'local/leducon:viewmanagerview';
    }

    public function get_filter_options(): array {
        return [
            'show_course'   => false,
            'show_category' => false,
            'show_cohort'   => true,
            'show_org'      => false,
        ];
    }

    public function get_columns(): array {
        return [
            'fullname'   => ['label' => get_string('mv_fullname',  'local_leducon'), 'sortable' => true, 'html' => true],
            'email'      => ['label' => get_string('mv_email',     'local_leducon'), 'sortable' => true],
            'courses'    => ['label' => get_string('mv_courses',   'local_leducon'), 'sortable' => true],
            'completed'  => ['label' => get_string('mv_completed', 'local_leducon'), 'sortable' => true],
            'avggrade'   => ['label' => get_string('mv_avggrade',  'local_leducon'), 'sortable' => true],
            'lastlogin'  => ['label' => get_string('mv_lastlogin', 'local_leducon'), 'sortable' => true],
            'atrisk'     => ['label' => get_string('mv_atrisk',    'local_leducon'), 'sortable' => true, 'html' => true],
            'viewcard'   => ['label' => get_string('mv_viewcard',  'local_leducon'), 'sortable' => false, 'html' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $cohortid = (int) $this->filter('cohortid', 0);
        if ($cohortid <= 0) {
            return [];
        }

        $atrisk_days  = (int) get_config('local_leducon', 'atrisk_inactive_days') ?: 14;
        $atrisk_grade = (int) get_config('local_leducon', 'atrisk_grade_threshold') ?: 50;
        $now          = time();
        $atrisk_cut   = $now - $atrisk_days * DAYSECS;

        $members = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                    COUNT(DISTINCT e.courseid) AS courses_enrolled,
                    COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                             OR (gg.finalgrade IS NOT NULL AND gi.grademax > 0 AND gg.finalgrade / gi.grademax * 100 >= 100)
                             THEN cc.course ELSE NULL END) AS courses_completed,
                    AVG(CASE WHEN gg.finalgrade IS NOT NULL AND gi.grademax > 0
                             THEN gg.finalgrade / gi.grademax * 100 ELSE NULL END) AS avggrade
               FROM {user} u
               JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid
          LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
          LEFT JOIN {enrol} e            ON e.id = ue.enrolid
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
          LEFT JOIN {grade_items} gi     ON gi.courseid = e.courseid AND gi.itemtype = 'course'
          LEFT JOIN {grade_grades} gg    ON gg.itemid = gi.id AND gg.userid = u.id AND gg.finalgrade IS NOT NULL
              WHERE u.deleted = 0 AND u.suspended = 0
              GROUP BY u.id, u.firstname, u.lastname, u.email, u.lastaccess
              ORDER BY u.lastname ASC, u.firstname ASC",
            ['cohortid' => $cohortid]
        );

        $rows = [];
        foreach ($members as $m) {
            $atrisk = ($m->lastaccess < $atrisk_cut || ($m->avggrade !== null && (float)$m->avggrade < $atrisk_grade));

            $atriskbadge = $atrisk
                ? \html_writer::tag('span', s(get_string('mv_atrisk_yes', 'local_leducon')), ['class' => 'ld-badge ld-badge-danger'])
                : \html_writer::tag('span', s(get_string('mv_atrisk_no',  'local_leducon')), ['class' => 'ld-badge ld-badge-success']);

            $cardurl    = new \moodle_url('/local/leducon/analytics/myreport.php', ['userid' => $m->id]);
            $namelink   = \html_writer::link($cardurl, s(fullname($m)));
            $actionlink = \html_writer::link($cardurl, s(get_string('mv_viewcard', 'local_leducon')),
                ['class' => 'ld-btn ld-btn-sm ld-btn-primary', 'target' => '_blank']);

            $rows[] = [
                'fullname'  => $namelink,
                'email'     => $m->email,
                'courses'   => (int)$m->courses_enrolled,
                'completed' => (int)$m->courses_completed,
                'avggrade'  => $m->avggrade !== null ? number_format((float)$m->avggrade, 1) . '%' : '-',
                'lastlogin' => $m->lastaccess
                    ? userdate($m->lastaccess, get_string('strftimedate', 'langconfig'))
                    : '-',
                'atrisk'    => $atriskbadge,
                'viewcard'  => $actionlink,
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

        $atrisk = 0;
        $highperformers = 0;
        $zeroenrolled = 0;

        foreach ($data as $row) {
            if (strpos($row['atrisk'], 'ld-badge-danger') !== false) {
                $atrisk++;
            }
            $grade = $row['avggrade'] ?? '-';
            if ($grade !== '-' && (float)$grade >= 80) {
                $highperformers++;
            }
            if ((int)$row['courses'] === 0) {
                $zeroenrolled++;
            }
        }

        $atriskpct = round($atrisk / $total * 100);

        if ($atriskpct >= $this->threshold('insight_mv_highrisk', 40)) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x9A\xA8",
                'type'   => 'danger',
                'title'  => get_string('insight_mv_highrisk', 'local_leducon', $atriskpct),
                'detail' => get_string('insight_mv_highrisk_detail', 'local_leducon', $atrisk),
            ];
        } elseif ($atrisk === 0 && $total >= 3) {
            $insights[] = [
                'icon'   => "\xE2\x9C\x85",
                'type'   => 'success',
                'title'  => get_string('insight_mv_norisk', 'local_leducon'),
                'detail' => get_string('insight_mv_norisk_detail', 'local_leducon'),
            ];
        }

        if ($highperformers >= $this->threshold('insight_mv_topperformers', 3)) {
            $insights[] = [
                'icon'   => "\xE2\xAD\x90",
                'type'   => 'success',
                'title'  => get_string('insight_mv_topperformers', 'local_leducon', $highperformers),
                'detail' => get_string('insight_mv_topperformers_detail', 'local_leducon'),
            ];
        }

        if ($zeroenrolled > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\xAD",
                'type'   => 'info',
                'title'  => get_string('insight_mv_noenrol', 'local_leducon', $zeroenrolled),
                'detail' => get_string('insight_mv_noenrol_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        $total = count($data);
        $atriskcount = 0;
        $completionpcts = [];
        foreach ($data as $row) {
            if (strpos($row['atrisk'], 'ld-badge-danger') !== false) {
                $atriskcount++;
            }
            $enrolled = (int)$row['courses'];
            $done     = (int)$row['completed'];
            if ($enrolled > 0) {
                $completionpcts[] = round($done / $enrolled * 100, 1);
            }
        }
        $avgcompl = count($completionpcts) > 0 ? round(array_sum($completionpcts) / count($completionpcts), 1) : 0;

        return [
            ['label' => get_string('managerview_total_members',  'local_leducon'), 'value' => $total,           'color' => 'primary'],
            ['label' => get_string('managerview_avg_completion', 'local_leducon'), 'value' => $avgcompl . '%',   'color' => 'success'],
            ['label' => get_string('managerview_at_risk',        'local_leducon'), 'value' => $atriskcount,      'color' => 'danger'],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        $safe = 0;
        $atr  = 0;
        foreach ($data as $row) {
            if (strpos($row['atrisk'], 'ld-badge-danger') !== false) {
                $atr++;
            } else {
                $safe++;
            }
        }

        if (($safe + $atr) === 0) {
            return null;
        }

        try {
            $pie = new \core\chart_pie();
            $ps = new \core\chart_series(
                get_string('chart_risk_status', 'local_leducon'),
                [$safe, $atr]
            );
            $ps->set_colors(['#16a34a', '#dc2626']);
            $pie->add_series($ps);
            $pie->set_labels([
                get_string('chart_on_track', 'local_leducon'),
                get_string('chart_at_risk', 'local_leducon'),
            ]);
            $pie->set_doughnut(true);
            return $OUTPUT->render($pie);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
