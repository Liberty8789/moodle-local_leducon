<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Quiz Analytics report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_analytics_report extends base_report {

    public function get_name(): string {
        return get_string('report_quiz_analytics', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'quizname'    => ['label' => get_string('qa_quizname',    'local_leducon'), 'sortable' => true],
            'coursename'  => ['label' => get_string('qa_coursename',  'local_leducon'), 'sortable' => true],
            'attempts'    => ['label' => get_string('qa_attempts',    'local_leducon'), 'sortable' => true],
            'uniqueusers' => ['label' => get_string('qa_uniqueusers', 'local_leducon'), 'sortable' => true],
            'avggrade'    => ['label' => get_string('qa_avggrade',    'local_leducon'), 'sortable' => true],
            'highgrade'   => ['label' => get_string('qa_highgrade',   'local_leducon'), 'sortable' => true],
            'lowgrade'    => ['label' => get_string('qa_lowgrade',    'local_leducon'), 'sortable' => true],
            'passrate'    => ['label' => get_string('qa_passrate',    'local_leducon'), 'sortable' => true],
            'avgtime'     => ['label' => get_string('qa_avgtime',     'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('quiz') || !$dbman->table_exists('quiz_attempts')) {
            return [];
        }

        $from       = $this->resolve_from();
        $to         = $this->resolve_to();
        $courseid   = (int) $this->filter('courseid', 0);
        $categoryid = (int) $this->filter('categoryid', 0);

        $coursewhere  = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursewhere  = 'AND q.course = :courseid';
            $courseparams = ['courseid' => $courseid];
        } elseif ($categoryid > 0) {
            $coursewhere  = 'AND c.category = :categoryid';
            $courseparams = ['categoryid' => $categoryid];
        }

        $cohortid     = (int) $this->filter('cohortid', 0);
        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND qa.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $passthreshold = (float) (get_config('local_leducon', 'kpi_pass_green') ?: 70);

        $sql = "SELECT q.id,
                       q.name                                                   AS quizname,
                       c.fullname                                               AS coursename,
                       COUNT(qa.id)                                             AS attempts,
                       COUNT(DISTINCT qa.userid)                                AS uniqueusers,
                       AVG(qa.sumgrades / NULLIF(q.sumgrades, 0) * 100)        AS avggrade,
                       MAX(qa.sumgrades / NULLIF(q.sumgrades, 0) * 100)        AS highgrade,
                       MIN(qa.sumgrades / NULLIF(q.sumgrades, 0) * 100)        AS lowgrade,
                       AVG((qa.timefinish - qa.timestart) / 60.0)              AS avgmins,
                       SUM(CASE
                             WHEN (qa.sumgrades / NULLIF(q.sumgrades, 0) * 100) >= :passthreshold
                             THEN 1 ELSE 0
                           END)                                                 AS passcount
                  FROM {quiz} q
                  JOIN {course} c       ON c.id = q.course
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                                         AND qa.state = 'finished'
                                         AND qa.timefinish BETWEEN :from AND :to
                 WHERE c.id <> :siteid
                   {$coursewhere}
                   {$cohortjoin}
              GROUP BY q.id, q.name, c.fullname, q.sumgrades
                HAVING COUNT(qa.id) > 0
              ORDER BY attempts DESC, q.name ASC";

        $params = array_merge([
            'from'          => $from,
            'to'            => $to,
            'siteid'        => SITEID,
            'passthreshold' => $passthreshold,
        ], $courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $attempts = (int) $r->attempts;
            $pass     = (int) $r->passcount;
            $rows[] = [
                'quizname'    => format_string($r->quizname),
                'coursename'  => format_string($r->coursename),
                'attempts'    => $attempts,
                'uniqueusers' => (int) $r->uniqueusers,
                'avggrade'    => $this->fmt_pct($r->avggrade) . '%',
                'highgrade'   => $this->fmt_pct($r->highgrade) . '%',
                'lowgrade'    => $this->fmt_pct($r->lowgrade) . '%',
                'passrate'    => $attempts > 0 ? $this->fmt_pct($pass / $attempts * 100) . '%' : '-',
                'avgtime'     => $r->avgmins !== null ? number_format((float) $r->avgmins, 1) : '-',
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalattempts = array_sum(array_column($data, 'attempts'));
        $lowpass = 0;
        $highpass = 0;
        $longestquiz = '';
        $longesttime = 0;

        foreach ($data as $row) {
            $pr = (float) rtrim($row['passrate'], '%');
            if ($pr < $this->threshold('insight_quiz_lowpass', 50) && $pr > 0) {
                $lowpass++;
            }
            if ($pr >= $this->threshold('insight_quiz_highpass', 90)) {
                $highpass++;
            }
            $avgmin = is_numeric($row['avgtime']) ? (float)$row['avgtime'] : 0;
            if ($avgmin > $longesttime) {
                $longesttime = $avgmin;
                $longestquiz = $row['quizname'];
            }
        }

        if ($lowpass > 0) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'danger',
                'title'  => get_string('insight_qa_lowpass', 'local_leducon', $lowpass),
                'detail' => get_string('insight_qa_lowpass_detail', 'local_leducon'),
            ];
        }

        if ($highpass > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8E\xAF",
                'type'   => 'success',
                'title'  => get_string('insight_qa_highpass', 'local_leducon', $highpass),
                'detail' => get_string('insight_qa_highpass_detail', 'local_leducon'),
            ];
        }

        if ($longesttime >= $this->threshold('insight_quiz_longtime', 60)) {
            $a = new \stdClass();
            $a->name = $longestquiz;
            $a->mins = round($longesttime);
            $insights[] = [
                'icon'   => "\xE2\x8F\xB1\xEF\xB8\x8F",
                'type'   => 'info',
                'title'  => get_string('insight_qa_longtime', 'local_leducon', $a),
                'detail' => get_string('insight_qa_longtime_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        global $DB;

        $from = $this->resolve_from();
        $to   = $this->resolve_to();

        $attempts = $DB->count_records_select(
            'quiz_attempts',
            "state = 'finished' AND timefinish BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to]
        );

        return [
            ['label' => get_string('qa_attempts', 'local_leducon'), 'value' => $attempts],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;
        if (empty($data)) {
            return null;
        }
        $data      = array_slice($data, 0, 12);
        $labels    = [];
        $attempts  = [];
        $passrates = [];
        foreach ($data as $row) {
            $labels[]    = mb_substr($row['quizname'], 0, 28);
            $attempts[]  = (int) $row['attempts'];
            $passrates[] = (float) rtrim($row['passrate'], '%');
        }
        $chart = new \core\chart_bar();
        $s1 = new \core\chart_series(get_string('qa_attempts', 'local_leducon'), $attempts);
        $s2 = new \core\chart_series(get_string('qa_passrate', 'local_leducon'), $passrates);
        $s1->set_color('#4e73df');
        $s2->set_color('#28a745');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }
}
