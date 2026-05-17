<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * SCORM Analytics report — multi-mode status aggregation.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_analytics_report extends base_report {

    public function get_name(): string {
        return get_string('report_scorm_analytics', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'scormname'      => ['label' => get_string('sa_scormname',      'local_leducon'), 'sortable' => true],
            'coursename'     => ['label' => get_string('sa_coursename',     'local_leducon'), 'sortable' => true],
            'mode'           => ['label' => get_string('sa_mode',           'local_leducon'), 'sortable' => true],
            'enrolled'       => ['label' => get_string('sa_enrolled',       'local_leducon'), 'sortable' => true],
            'attempted'      => ['label' => get_string('sa_attempted',      'local_leducon'), 'sortable' => true],
            'criteria_met'   => ['label' => get_string('sa_criteria_met',   'local_leducon'), 'sortable' => true],
            'passed_scorm'   => ['label' => get_string('sa_passed_scorm',   'local_leducon'), 'sortable' => true],
            'moodle_complete'=> ['label' => get_string('sa_moodle_complete','local_leducon'), 'sortable' => true],
            'gap'            => ['label' => get_string('sa_gap',            'local_leducon'), 'sortable' => true],
            'passrate'       => ['label' => get_string('sa_passrate',       'local_leducon'), 'sortable' => true],
            'avgscore'       => ['label' => get_string('sa_avgscore',       'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('scorm')) {
            return [];
        }

        $categoryid = (int) $this->filter('categoryid', 0);
        $courseid   = (int) $this->filter('courseid',   0);
        $cohortid   = (int) $this->filter('cohortid',   0);

        $passmode = get_config('local_leducon', 'scorm_pass_mode') ?: 'auto';

        $where  = 'c.id <> :siteid';
        $params = ['siteid' => SITEID];
        if ($courseid > 0) {
            $where .= ' AND c.id = :courseid';
            $params['courseid'] = $courseid;
        } elseif ($categoryid > 0) {
            $where .= ' AND c.category = :categoryid';
            $params['categoryid'] = $categoryid;
        }

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        try {
            $guestid = $DB->get_field('user', 'id', ['username' => 'guest']) ?: 1;

            $scorms = $DB->get_records_sql(
                "SELECT s.id, s.name AS scormname, c.id AS courseid, c.fullname AS coursename,
                        COUNT(DISTINCT ue.userid) AS enrolled
                   FROM {scorm} s
                   JOIN {course} c            ON c.id = s.course AND {$where}
              LEFT JOIN {enrol} e             ON e.courseid = c.id
              LEFT JOIN {user_enrolments} ue  ON ue.enrolid = e.id AND ue.userid <> :guestid
                                              {$cohortjoin}
                  GROUP BY s.id, s.name, c.id, c.fullname
                  ORDER BY c.fullname ASC, s.name ASC",
                array_merge($params, ['guestid' => $guestid], $cohortparams)
            );

            if (empty($scorms)) {
                return [];
            }

            $scormids  = array_keys($scorms);
            $courseids = [];
            foreach ($scorms as $s) {
                $courseids[$s->courseid] = $s->courseid;
            }

            $sctcohort = '';
            $sctparams = [];
            if ($cohortid > 0) {
                $sctcohort = 'AND userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid2)';
                $sctparams = ['cohortid2' => $cohortid];
            }

            list($insql, $inparams) = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'tr');
            $trackrows = $DB->get_records_sql(
                "SELECT id, scormid, scoid, userid, element, value
                   FROM {scorm_scoes_track}
                  WHERE scormid {$insql}
                    AND element IN (
                        'cmi.core.lesson_status',
                        'cmi.completion_status',
                        'cmi.success_status',
                        'cmi.core.score.raw',
                        'cmi.score.raw'
                    )
                    {$sctcohort}",
                array_merge($inparams, $sctparams)
            );

            $tracker    = [];
            $scoredScos = [];

            foreach ($trackrows as $row) {
                $sid  = (int) $row->scormid;
                $uid  = (int) $row->userid;
                $scid = (int) $row->scoid;

                if (!isset($tracker[$sid][$uid])) {
                    $tracker[$sid][$uid] = [
                        'passed'    => [],
                        'completed' => [],
                        'success'   => [],
                        'scores'    => [],
                    ];
                }
                switch ($row->element) {
                    case 'cmi.core.lesson_status':
                        if ($row->value === 'passed')    { $tracker[$sid][$uid]['passed'][$scid]    = true; }
                        if ($row->value === 'completed') { $tracker[$sid][$uid]['completed'][$scid] = true; }
                        break;
                    case 'cmi.completion_status':
                        if ($row->value === 'completed') { $tracker[$sid][$uid]['completed'][$scid] = true; }
                        break;
                    case 'cmi.success_status':
                        if ($row->value === 'passed')    { $tracker[$sid][$uid]['success'][$scid]   = true; }
                        break;
                    case 'cmi.core.score.raw':
                    case 'cmi.score.raw':
                        $tracker[$sid][$uid]['scores'][] = (float) $row->value;
                        $scoredScos[$sid][$scid]         = true;
                        break;
                }
            }

            $moodlecomplete = [];
            if (!empty($scormids)) {
                list($inscorm, $inscormparams) = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'mc');
                $mcrows = $DB->get_records_sql(
                    "SELECT cmc.userid, cm.instance AS scormid
                       FROM {course_modules_completion} cmc
                       JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                       JOIN {modules} m         ON m.id = cm.module AND m.name = 'scorm'
                      WHERE cm.instance {$inscorm}
                        AND cmc.completionstate >= 1",
                    $inscormparams
                );
                foreach ($mcrows as $mc) {
                    $moodlecomplete[(int)$mc->scormid][(int)$mc->userid] = true;
                }
            }

            $rows = [];
            foreach ($scorms as $s) {
                $sid     = (int) $s->id;
                $usermap = isset($tracker[$sid]) ? $tracker[$sid] : [];

                $attempted_count    = count($usermap);
                $criteria_met_count = 0;
                $passed_scorm_count = 0;
                $moodle_cnt         = 0;
                $gap_count          = 0;
                $all_scores         = [];

                $assessmentScoids  = isset($scoredScos[$sid]) ? array_keys($scoredScos[$sid]) : [];
                $hasAssessmentScos = !empty($assessmentScoids);

                $resolve = function(array $udata) use ($hasAssessmentScos, $assessmentScoids): array {
                    if ($hasAssessmentScos) {
                        $p = $c = $su = false;
                        foreach ($assessmentScoids as $scid) {
                            if (!empty($udata['passed'][$scid]))    { $p  = true; }
                            if (!empty($udata['completed'][$scid])) { $c  = true; }
                            if (!empty($udata['success'][$scid]))   { $su = true; }
                        }
                        return [$p, $c, $su];
                    }
                    return [!empty($udata['passed']), !empty($udata['completed']), !empty($udata['success'])];
                };

                $users_passed = $users_completed = $users_success = 0;

                foreach ($usermap as $uid => $udata) {
                    [$u_passed, $u_completed, $u_success] = $resolve($udata);
                    if ($u_passed)    { $users_passed++;    }
                    if ($u_completed) { $users_completed++; }
                    if ($u_success)   { $users_success++;   }

                    $raw_passed = (!empty($udata['passed']) || !empty($udata['success']));
                    if ($raw_passed) { $passed_scorm_count++; }
                    if (isset($moodlecomplete[$sid][$uid])) { $moodle_cnt++; }
                    if (!empty($udata['scores'])) { $all_scores[] = max($udata['scores']); }
                }

                $threshold     = ($attempted_count > 0) ? max(2, (int) ceil($attempted_count * 0.05)) : 1;
                $sig_passed    = ($users_passed    >= $threshold);
                $sig_completed = ($users_completed >= $threshold);
                $sig_success   = ($users_success   >= $threshold);

                if ($passmode === 'moodle') {
                    $effective = 'moodle';
                } elseif ($passmode !== 'auto') {
                    $effective = $passmode;
                } elseif ($sig_success || ($sig_passed && !$sig_completed)) {
                    $effective = 'pass_priority';
                } elseif (!$sig_passed && $sig_completed) {
                    $effective = 'completed_only';
                } elseif ($sig_passed && $sig_completed) {
                    $effective = 'lenient';
                } else {
                    $effective = 'lenient';
                }

                foreach ($usermap as $uid => $udata) {
                    [$u_passed, $u_completed, $u_success] = $resolve($udata);
                    $scorm_passed = $u_passed || $u_success;

                    switch ($effective) {
                        case 'pass_priority':    $criteria = $scorm_passed; break;
                        case 'completed_only':   $criteria = $u_completed; break;
                        case 'strict':           $criteria = $scorm_passed && $u_completed; break;
                        case 'moodle':           $criteria = isset($moodlecomplete[$sid][$uid]); break;
                        default:                 $criteria = $scorm_passed || $u_completed; break;
                    }
                    if ($criteria) { $criteria_met_count++; }

                    $raw_passed = (!empty($udata['passed']) || !empty($udata['success']));
                    if ($raw_passed && !isset($moodlecomplete[$sid][$uid])) {
                        $gap_count++;
                    }
                }

                if ($sig_success) {
                    $mode_label = get_string('sa_mode_scorm2004', 'local_leducon');
                } elseif ($sig_passed && !$sig_completed) {
                    $mode_label = get_string('sa_mode_passed_incomplete', 'local_leducon');
                } elseif ($sig_passed && $sig_completed) {
                    $mode_label = get_string('sa_mode_mixed', 'local_leducon');
                } elseif (!$sig_passed && $sig_completed) {
                    $mode_label = get_string('sa_mode_completed_incomplete', 'local_leducon');
                } else {
                    $mode_label = get_string('sa_mode_unknown', 'local_leducon');
                }

                $avgscore = '-';
                if (!empty($all_scores)) {
                    $avgscore = number_format(array_sum($all_scores) / count($all_scores), 1);
                }

                $passrate = $attempted_count > 0
                    ? $this->fmt_pct($criteria_met_count / $attempted_count * 100) . '%'
                    : '-';

                $rows[] = [
                    'scormname'       => $s->scormname,
                    'coursename'      => $s->coursename,
                    'mode'            => $mode_label,
                    'enrolled'        => (int) $s->enrolled,
                    'attempted'       => $attempted_count,
                    'criteria_met'    => $criteria_met_count,
                    'passed_scorm'    => $passed_scorm_count,
                    'moodle_complete' => $moodle_cnt,
                    'gap'             => $gap_count,
                    'passrate'        => $passrate,
                    'avgscore'        => $avgscore,
                ];
            }

            return $rows;

        } catch (\dml_exception $e) {
            debugging('SCORM analytics report SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalgap       = 0;
        $totalattempted  = 0;
        $totalcriteria   = 0;
        $zeroenrolled    = 0;

        foreach ($data as $row) {
            $totalgap      += (int)$row['gap'];
            $totalattempted += (int)$row['attempted'];
            $totalcriteria  += (int)$row['criteria_met'];
            if ((int)$row['enrolled'] > 0 && (int)$row['attempted'] === 0) {
                $zeroenrolled++;
            }
        }

        // Completion gap (SCORM passed but Moodle not complete).
        if ($totalgap > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xB4",
                'type'   => 'danger',
                'title'  => get_string('insight_sa_gap', 'local_leducon', $totalgap),
                'detail' => get_string('insight_sa_gap_detail', 'local_leducon'),
            ];
        }

        // Overall pass rate.
        if ($totalattempted > 0) {
            $passrate = round($totalcriteria / $totalattempted * 100, 1);
            if ($passrate >= 80) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x8E\xAF",
                    'type'   => 'success',
                    'title'  => get_string('insight_sa_highpass', 'local_leducon', $passrate),
                    'detail' => get_string('insight_sa_highpass_detail', 'local_leducon'),
                ];
            } elseif ($passrate < 40) {
                $insights[] = [
                    'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                    'type'   => 'warning',
                    'title'  => get_string('insight_sa_lowpass', 'local_leducon', $passrate),
                    'detail' => get_string('insight_sa_lowpass_detail', 'local_leducon'),
                ];
            }
        }

        // SCORM modules with zero attempts.
        if ($zeroenrolled > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xA4",
                'type'   => 'info',
                'title'  => get_string('insight_sa_noattempt', 'local_leducon', $zeroenrolled),
                'detail' => get_string('insight_sa_noattempt_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data           = $this->get_data();
        $total          = count($data);
        $criteria_total = 0;
        $attempted      = 0;
        $gap_total      = 0;
        foreach ($data as $row) {
            $criteria_total += (int) $row['criteria_met'];
            $attempted      += (int) $row['attempted'];
            $gap_total      += (int) $row['gap'];
        }
        $pct  = $attempted > 0 ? round($criteria_total / $attempted * 100, 1) : 0;
        $rate = $attempted > 0 ? $this->fmt_pct($pct) . '%' : '-';
        return [
            ['label' => get_string('sa_scorm_total',     'local_leducon'), 'value' => $total],
            ['label' => get_string('sa_scorm_attempted', 'local_leducon'), 'value' => $attempted],
            ['label' => get_string('sa_passrate',        'local_leducon'), 'value' => $rate, 'pct' => $pct],
            ['label' => get_string('sa_gap',             'local_leducon'), 'value' => $gap_total],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;
        if (empty($data)) {
            return null;
        }
        $data = array_slice($data, 0, 12);
        $labels    = [];
        $attempted = [];
        $criteria  = [];
        $gaps      = [];
        foreach ($data as $row) {
            $labels[]    = mb_substr($row['scormname'], 0, 28);
            $attempted[] = (int) $row['attempted'];
            $criteria[]  = (int) $row['criteria_met'];
            $gaps[]      = (int) $row['gap'];
        }
        $chart = new \core\chart_bar();
        $s1 = new \core\chart_series(get_string('sa_attempted',    'local_leducon'), $attempted);
        $s2 = new \core\chart_series(get_string('sa_criteria_met', 'local_leducon'), $criteria);
        $s3 = new \core\chart_series(get_string('sa_gap',          'local_leducon'), $gaps);
        $s1->set_color('#4e73df');
        $s2->set_color('#28a745');
        $s3->set_color('#e74c3c');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->add_series($s3);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }
}
