<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Course Completion report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_completion_report extends base_report {

    public function get_name(): string {
        return get_string('report_course_completion', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'coursename'     => ['label' => get_string('cc_coursename',     'local_leducon'), 'sortable' => true],
            'category'       => ['label' => get_string('cc_category',       'local_leducon'), 'sortable' => true],
            'enrolled'       => ['label' => get_string('cc_enrolled',       'local_leducon'), 'sortable' => true],
            'completed'      => ['label' => get_string('cc_completed',      'local_leducon'), 'sortable' => true],
            'inprogress'     => ['label' => get_string('cc_inprogress',     'local_leducon'), 'sortable' => true],
            'notstarted'     => ['label' => get_string('cc_notstarted',     'local_leducon'), 'sortable' => true],
            'completionrate' => ['label' => get_string('cc_completionrate', 'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $categoryid = (int) $this->filter('categoryid', 0);
        $courseid   = (int) $this->filter('courseid', 0);
        $from       = $this->resolve_from();
        $to         = $this->resolve_to();

        $cohortid     = (int) $this->filter('cohortid', 0);
        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
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
                       c.fullname                                            AS coursename,
                       cc2.name                                              AS category,
                       COUNT(DISTINCT ue.userid)                             AS enrolled,
                       COUNT(DISTINCT CASE WHEN ccomp.timecompleted IS NOT NULL
                                 AND ccomp.timecompleted BETWEEN :from AND :to
                                THEN ccomp.userid ELSE NULL END)             AS completed,
                       COUNT(DISTINCT CASE WHEN ccomp.timecompleted IS NULL
                                 AND ccomp.timestarted IS NOT NULL
                                THEN ccomp.userid ELSE NULL END)             AS inprogress,
                       COUNT(DISTINCT CASE WHEN ccomp.id IS NULL
                                 OR  ccomp.timestarted IS NULL
                                THEN ue.userid ELSE NULL END)                AS notstarted
                  FROM {course} c
                  JOIN {course_categories} cc2   ON cc2.id  = c.category
                  JOIN {enrol} e                  ON e.courseid = c.id
                  JOIN {user_enrolments} ue       ON ue.enrolid = e.id
             LEFT JOIN {course_completions} ccomp ON ccomp.course = c.id
                                                 AND ccomp.userid = ue.userid
                 WHERE c.id <> :siteid
                   {$coursewhere}
                   {$cohortjoin}
              GROUP BY c.id, c.fullname, cc2.name
              ORDER BY c.fullname ASC";

        $params = array_merge([
            'from'   => $from,
            'to'     => $to,
            'siteid' => SITEID,
        ], $courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $enrolled  = (int) $r->enrolled;
            $completed = (int) $r->completed;
            $rows[] = [
                'coursename'     => format_string($r->coursename),
                'category'       => format_string($r->category),
                'enrolled'       => $enrolled,
                'completed'      => $completed,
                'inprogress'     => (int) $r->inprogress,
                'notstarted'     => (int) $r->notstarted,
                'completionrate' => $enrolled > 0 ? $this->fmt_pct($completed / $enrolled * 100) . '%' : '-',
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalenrolled  = array_sum(array_column($data, 'enrolled'));
        $totalcompleted = array_sum(array_column($data, 'completed'));
        $totalnotstarted = array_sum(array_column($data, 'notstarted'));
        $overallrate    = $totalenrolled > 0 ? round($totalcompleted / $totalenrolled * 100, 1) : 0;

        // Overall completion rate insight.
        if ($overallrate >= $this->threshold('insight_completion_high', 70)) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8E\xAF",
                'type'   => 'success',
                'title'  => get_string('insight_cc_highrate', 'local_leducon', $overallrate),
                'detail' => get_string('insight_cc_highrate_detail', 'local_leducon'),
            ];
        } elseif ($overallrate < $this->threshold('insight_completion_low', 30) && $totalenrolled >= 10) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'warning',
                'title'  => get_string('insight_cc_lowrate', 'local_leducon', $overallrate),
                'detail' => get_string('insight_cc_lowrate_detail', 'local_leducon'),
            ];
        }

        // Courses with zero completions.
        $zerocourses = 0;
        $bestcourse = '';
        $bestrate = 0;
        foreach ($data as $row) {
            if ((int)$row['completed'] === 0 && (int)$row['enrolled'] > 0) {
                $zerocourses++;
            }
            $enrolled = (int)$row['enrolled'];
            if ($enrolled > 0) {
                $rate = (int)$row['completed'] / $enrolled * 100;
                if ($rate > $bestrate) {
                    $bestrate = $rate;
                    $bestcourse = $row['coursename'];
                }
            }
        }

        if ($zerocourses > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xB4",
                'type'   => 'danger',
                'title'  => get_string('insight_cc_zerocourses', 'local_leducon', $zerocourses),
                'detail' => get_string('insight_cc_zerocourses_detail', 'local_leducon'),
            ];
        }

        // Not-started learners.
        if ($totalnotstarted > 0 && $totalenrolled > 0) {
            $nspct = round($totalnotstarted / $totalenrolled * 100);
            if ($nspct >= $this->threshold('insight_completion_notstarted', 40)) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x92\xA4",
                    'type'   => 'warning',
                    'title'  => get_string('insight_cc_notstarted', 'local_leducon', $nspct),
                    'detail' => get_string('insight_cc_notstarted_detail', 'local_leducon', $totalnotstarted),
                ];
            }
        }

        // Top performing course.
        if ($bestcourse && $bestrate >= $this->threshold('insight_completion_best', 80)) {
            $a = new \stdClass();
            $a->name = $bestcourse;
            $a->rate = round($bestrate, 1);
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x86",
                'type'   => 'info',
                'title'  => get_string('insight_cc_bestcourse', 'local_leducon', $a),
                'detail' => get_string('insight_cc_bestcourse_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }
        $totalenrolled  = array_sum(array_column($data, 'enrolled'));
        $totalcompleted = array_sum(array_column($data, 'completed'));
        $pct  = $totalenrolled > 0 ? round($totalcompleted / $totalenrolled * 100, 1) : 0;
        $rate = $totalenrolled > 0 ? number_format($pct, 1) . '%' : '-';
        return [
            ['label' => get_string('cc_total_courses', 'local_leducon'), 'value' => count($data)],
            ['label' => get_string('cc_enrolled',      'local_leducon'), 'value' => $totalenrolled],
            ['label' => get_string('cc_completionrate','local_leducon'), 'value' => $rate, 'pct' => $pct],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;
        if (empty($data)) {
            return null;
        }
        $data = array_slice($data, 0, 12);
        $labels     = [];
        $completed  = [];
        $inprogress = [];
        $notstarted = [];
        foreach ($data as $row) {
            $labels[]     = mb_substr($row['coursename'], 0, 28);
            $completed[]  = (int) $row['completed'];
            $inprogress[] = (int) $row['inprogress'];
            $notstarted[] = (int) $row['notstarted'];
        }
        $chart = new \core\chart_bar();
        $chart->set_stacked(true);
        $s1 = new \core\chart_series(get_string('cc_completed',  'local_leducon'), $completed);
        $s2 = new \core\chart_series(get_string('cc_inprogress', 'local_leducon'), $inprogress);
        $s3 = new \core\chart_series(get_string('cc_notstarted', 'local_leducon'), $notstarted);
        $s1->set_color('#28a745');
        $s2->set_color('#ffc107');
        $s3->set_color('#6c757d');
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

        // Use recordset to avoid memory issues and prevent duplicate-key loss.
        $rs = $DB->get_recordset_sql(
            "SELECT id, timecompleted FROM {course_completions}
              WHERE timecompleted BETWEEN :start AND :now
                AND timecompleted IS NOT NULL",
            ['start' => $start, 'now' => $now]
        );

        $monthly = [];
        foreach ($rs as $r) {
            $key = date('Y-m', (int) $r->timecompleted);
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
