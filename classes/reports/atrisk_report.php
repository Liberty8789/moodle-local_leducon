<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * At-Risk Students report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class atrisk_report extends base_report {

    public function get_name(): string {
        return get_string('report_atrisk', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'username'     => ['label' => get_string('atrisk_username',     'local_leducon'), 'sortable' => true],
            'fullname'     => ['label' => get_string('atrisk_fullname',     'local_leducon'), 'sortable' => true],
            'email'        => ['label' => get_string('atrisk_email',        'local_leducon'), 'sortable' => false],
            'course'       => ['label' => get_string('atrisk_course',       'local_leducon'), 'sortable' => true],
            'lastlogin'    => ['label' => get_string('atrisk_lastlogin',    'local_leducon'), 'sortable' => true],
            'daysinactive' => ['label' => get_string('atrisk_daysinactive', 'local_leducon'), 'sortable' => true],
            'grade'        => ['label' => get_string('atrisk_grade',        'local_leducon'), 'sortable' => true],
            'completion'   => ['label' => get_string('atrisk_completion',   'local_leducon'), 'sortable' => true],
            'risk'         => ['label' => get_string('atrisk_risk',         'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $config         = get_config('local_leducon');
        $inactivedays   = (int) ($config->atrisk_inactive_days     ?? 14);
        $gradethreshold = (float) ($config->atrisk_grade_threshold  ?? 50);
        $compthreshold  = (float) ($config->atrisk_completion_threshold ?? 25);

        $courseid     = (int) $this->filter('courseid', 0);
        $cohortid     = (int) $this->filter('cohortid', 0);

        $coursewhere  = '';
        $courseparams = [];
        if ($courseid > 0) {
            $coursewhere  = 'AND e.courseid = :courseid';
            $courseparams = ['courseid' => $courseid];
        }

        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'JOIN {cohort_members} chm ON chm.userid = u.id AND chm.cohortid = :cohortid';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $now             = time();
        $inactivethresh  = $now - $inactivedays * DAYSECS;

        $sql = "SELECT u.id,
                       u.username,
                       u.firstname,
                       u.lastname,
                       u.email,
                       u.lastaccess,
                       e.courseid,
                       c.fullname                                            AS coursename,
                       gg.finalgrade,
                       gi.grademax,
                       cc.timecompleted,
                       (SELECT COUNT(cm2.id) FROM {course_modules} cm2
                         WHERE cm2.course = e.courseid AND cm2.visible = 1 AND cm2.completion > 0) AS totalmodules,
                       (SELECT COUNT(cmc2.id) FROM {course_modules_completion} cmc2
                         JOIN {course_modules} cm3 ON cm3.id = cmc2.coursemoduleid
                         WHERE cmc2.userid = u.id AND cm3.course = e.courseid
                           AND cmc2.completionstate > 0) AS donemodules,
                       (SELECT COUNT(cm4.id) FROM {course_modules} cm4
                         WHERE cm4.course = e.courseid AND cm4.visible = 1) AS allmodules
                  FROM {user_enrolments} ue
                  JOIN {enrol} e        ON e.id = ue.enrolid
                  JOIN {course} c       ON c.id = e.courseid AND c.id <> :siteid
                  JOIN {user} u         ON u.id = ue.userid
                  {$cohortjoin}
             LEFT JOIN {grade_items} gi ON gi.courseid = e.courseid AND gi.itemtype = 'course'
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
             LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = u.id
                 WHERE ue.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND u.username <> 'guest'
                   AND (
                       u.lastaccess < :inactivethresh
                       OR (gg.finalgrade IS NOT NULL AND gi.grademax > 0
                           AND (gg.finalgrade / gi.grademax * 100) < :gradethreshold)
                   )
                   {$coursewhere}
              ORDER BY u.lastname ASC, c.fullname ASC";

        $params = array_merge([
            'siteid'          => SITEID,
            'inactivethresh'  => $inactivethresh,
            'gradethreshold'  => $gradethreshold,
        ], $courseparams, $cohortparams);

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $total   = (int) $r->totalmodules;
            $done    = (int) $r->donemodules;
            $allmods = (int) $r->allmodules;
            $comppct = $total > 0 ? round($done / $total * 100, 1) : 0;

            $gradepct = null;
            if ($r->finalgrade !== null && !empty($r->grademax)) {
                $gradepct = round($r->finalgrade / $r->grademax * 100, 1);
            }

            // If no completion-tracked activities but grade is 100%, treat as completed.
            if ($total === 0 && $allmods > 0 && $gradepct !== null && $gradepct >= 100) {
                $comppct = 100;
            }

            $lastaccess    = (int) $r->lastaccess;
            $daysinactive = $lastaccess > 0 ? (int) floor(($now - $lastaccess) / DAYSECS) : null;

            $reasons = [];
            if ($daysinactive !== null && $daysinactive >= $inactivedays) {
                $reasons[] = get_string('atrisk_inactive', 'local_leducon', $daysinactive);
            }
            if ($gradepct !== null && $gradepct < $gradethreshold) {
                $reasons[] = get_string('atrisk_lowgrade', 'local_leducon', $gradepct);
            }
            if ($comppct < $compthreshold && $total > 0) {
                $reasons[] = get_string('atrisk_lowcompletion', 'local_leducon', $comppct);
            }

            $riskcount = count($reasons);
            if ($riskcount >= 2) {
                $risk = get_string('atrisk_risk_high',   'local_leducon');
            } elseif ($riskcount === 1) {
                $risk = get_string('atrisk_risk_medium', 'local_leducon');
            } else {
                $risk = get_string('atrisk_risk_low',    'local_leducon');
            }

            $rows[] = [
                'userid'       => (int) $r->id,
                'courseid'     => (int) $r->courseid,
                'username'     => $r->username,
                'fullname'     => fullname($r),
                'email'        => $r->email,
                'course'       => format_string($r->coursename),
                'lastlogin'    => $lastaccess > 0 ? $this->fmt_date($lastaccess) : '-',
                'lastlogin_ts' => $lastaccess,
                'daysinactive' => $daysinactive ?? '-',
                'grade'        => $gradepct !== null ? $gradepct . '%' : '-',
                'completion'   => ($total > 0 || $comppct > 0) ? $comppct . '%' : '-',
                'risk'         => $risk,
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        $total = count($data);

        if ($total === 0) {
            $insights[] = [
                'icon'   => "\xE2\x9C\x85",
                'type'   => 'success',
                'title'  => get_string('insight_atrisk_none', 'local_leducon'),
                'detail' => get_string('insight_atrisk_none_detail', 'local_leducon'),
            ];
            return $insights;
        }

        $high = 0;
        $inactive_only = 0;
        $grade_only = 0;
        $courses = [];
        $strhigh = get_string('atrisk_risk_high', 'local_leducon');

        foreach ($data as $row) {
            if (strpos($row['risk'], $strhigh) !== false) {
                $high++;
            }
            $days = $row['daysinactive'] ?? '-';
            if ($days !== '-' && (int)$days >= $this->threshold('insight_atrisk_inactive_days', 30)) {
                $inactive_only++;
            }
            $coursename = $row['course'] ?? '';
            if ($coursename) {
                if (!isset($courses[$coursename])) {
                    $courses[$coursename] = 0;
                }
                $courses[$coursename]++;
            }
        }

        // High-risk concentration.
        if ($high > 0) {
            $pct = round($high / $total * 100);
            $insights[] = [
                'icon'   => "\xF0\x9F\x9A\xA8",
                'type'   => 'danger',
                'title'  => get_string('insight_atrisk_high_count', 'local_leducon', $high),
                'detail' => get_string('insight_atrisk_high_pct', 'local_leducon', $pct),
            ];
        }

        // Long-inactive learners.
        if ($inactive_only >= $this->threshold('insight_atrisk_min_count', 3)) {
            $insights[] = [
                'icon'   => "\xE2\x8F\xB0",
                'type'   => 'warning',
                'title'  => get_string('insight_atrisk_inactive', 'local_leducon', $inactive_only),
                'detail' => get_string('insight_atrisk_inactive_detail', 'local_leducon'),
            ];
        }

        // Course with most at-risk students.
        if (!empty($courses)) {
            arsort($courses);
            $topcoursename = key($courses);
            $topcoursecount = current($courses);
            if ($topcoursecount >= $this->threshold('insight_atrisk_min_count', 3)) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x93\x8A",
                    'type'   => 'info',
                    'title'  => get_string('insight_atrisk_course', 'local_leducon', $topcoursename),
                    'detail' => get_string('insight_atrisk_course_detail', 'local_leducon', $topcoursecount),
                ];
            }
        }

        return $insights;
    }

    public function get_actions(): array {
        return [
            [
                'key'   => 'notify_teachers',
                'label' => get_string('atrisk_notify_all', 'local_leducon'),
                'class' => 'ld-btn ld-btn-warning',
            ],
        ];
    }

    public function execute_action($key, array $data) {
        global $DB;

        if ($key !== 'notify_teachers') {
            return '';
        }

        $rows = $this->get_data();
        $bycourse = [];
        foreach ($rows as $row) {
            $cid = isset($row['courseid']) ? (int)$row['courseid'] : 0;
            if ($cid) {
                $bycourse[$cid][] = $row;
            }
        }

        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $teacherrole        = $DB->get_record('role', ['shortname' => 'teacher']);
        $roleids = [];
        if ($editingteacherrole) {
            $roleids[] = $editingteacherrole->id;
        }
        if ($teacherrole) {
            $roleids[] = $teacherrole->id;
        }

        foreach ($bycourse as $cid => $students) {
            if (empty($roleids)) {
                break;
            }
            $coursecontext = \context_course::instance($cid);
            list($rsql, $rparams) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
            $teachers = $DB->get_records_sql(
                "SELECT DISTINCT u.*
                   FROM {user} u
                   JOIN {role_assignments} ra ON ra.userid = u.id
                  WHERE ra.contextid = :ctxid AND ra.roleid $rsql
                    AND u.deleted = 0 AND u.suspended = 0",
                array_merge(['ctxid' => $coursecontext->id], $rparams)
            );

            foreach ($teachers as $teacher) {
                $coursename = format_string($DB->get_field('course', 'fullname', ['id' => $cid]));
                $subject = get_string('atrisk_email_subject', 'local_leducon', $coursename);

                $body  = get_string('atrisk_email_greeting', 'local_leducon', fullname($teacher)) . "\n\n";
                $body .= get_string('atrisk_email_intro', 'local_leducon') . "\n\n";
                foreach ($students as $s) {
                    $body .= '- ' . ($s['fullname'] ?? '') . ' (' . ($s['email'] ?? '') . ')'
                           . ' | ' . get_string('atrisk_email_risk', 'local_leducon') . ': ' . ($s['risk'] ?? '') . "\n";
                }
                $body .= "\n" . get_string('atrisk_email_action', 'local_leducon') . "\n\n" . get_string('pluginname', 'local_leducon');

                $htmlbody  = '<div style="font-family:system-ui,-apple-system,sans-serif;max-width:600px;margin:0 auto">';
                $htmlbody .= '<div style="background:#2563eb;color:#fff;padding:16px 24px;border-radius:8px 8px 0 0">';
                $htmlbody .= '<h2 style="margin:0;font-size:18px">' . s(get_string('pluginname', 'local_leducon')) . '</h2></div>';
                $htmlbody .= '<div style="padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px">';
                $htmlbody .= '<p>' . s(get_string('atrisk_email_greeting', 'local_leducon', fullname($teacher))) . '</p>';
                $htmlbody .= '<p>' . s(get_string('atrisk_email_intro', 'local_leducon')) . '</p>';
                $htmlbody .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0">';
                $htmlbody .= '<thead><tr style="background:#f9fafb">';
                $htmlbody .= '<th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb">' . s(get_string('atrisk_fullname', 'local_leducon')) . '</th>';
                $htmlbody .= '<th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb">' . s(get_string('atrisk_email', 'local_leducon')) . '</th>';
                $htmlbody .= '<th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb">' . s(get_string('atrisk_email_risk', 'local_leducon')) . '</th>';
                $htmlbody .= '</tr></thead><tbody>';
                foreach ($students as $s) {
                    $risk = s($s['risk'] ?? '');
                    $riskcolor = (stripos($risk, 'High') === 0) ? '#dc2626' : ((stripos($risk, 'Medium') === 0) ? '#d97706' : '#0891b2');
                    $htmlbody .= '<tr style="border-bottom:1px solid #e5e7eb">';
                    $htmlbody .= '<td style="padding:8px 12px">' . s($s['fullname'] ?? '') . '</td>';
                    $htmlbody .= '<td style="padding:8px 12px">' . s($s['email'] ?? '') . '</td>';
                    $htmlbody .= '<td style="padding:8px 12px"><span style="background:' . $riskcolor . ';color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600">' . $risk . '</span></td>';
                    $htmlbody .= '</tr>';
                }
                $htmlbody .= '</tbody></table>';
                $htmlbody .= '<p style="color:#374151">' . s(get_string('atrisk_email_action', 'local_leducon')) . '</p>';
                $htmlbody .= '<p style="color:#6b7280;font-size:13px;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px">' . s(get_string('pluginname', 'local_leducon')) . '</p>';
                $htmlbody .= '</div></div>';

                email_to_user($teacher, \core_user::get_noreply_user(), $subject, $body, $htmlbody);
            }
        }

        return get_string('atrisk_notified', 'local_leducon');
    }

    public function get_summary(): array {
        $data   = $this->get_data();
        $total  = count($data);
        $high   = 0;
        $medium = 0;
        $strhigh   = get_string('atrisk_risk_high',   'local_leducon');
        $strmedium = get_string('atrisk_risk_medium', 'local_leducon');
        foreach ($data as $row) {
            if (strpos($row['risk'], $strhigh) !== false) {
                $high++;
            } elseif (strpos($row['risk'], $strmedium) !== false) {
                $medium++;
            }
        }
        return [
            ['label' => $strhigh,                                          'value' => $high],
            ['label' => $strmedium,                                        'value' => $medium],
            ['label' => get_string('atrisk_title', 'local_leducon'),      'value' => $total],
        ];
    }
}
