<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Instructor Performance report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instructor_report extends base_report {

    public function get_name(): string {
        return get_string('report_instructor', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'fullname'       => ['label' => get_string('inst_fullname',       'local_leducon'), 'sortable' => true],
            'email'          => ['label' => get_string('inst_email',          'local_leducon'), 'sortable' => true],
            'courses'        => ['label' => get_string('inst_courses',        'local_leducon'), 'sortable' => true],
            'students'       => ['label' => get_string('inst_students',       'local_leducon'), 'sortable' => true],
            'completionrate' => ['label' => get_string('inst_completionrate', 'local_leducon'), 'sortable' => true],
            'avggrade'       => ['label' => get_string('inst_avggrade',       'local_leducon'), 'sortable' => true],
            'lastactive'     => ['label' => get_string('inst_lastactive',     'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $courseid   = (int) $this->filter('courseid', 0);
        $categoryid = (int) $this->filter('categoryid', 0);

        $contextlevel = 50;

        $coursewhere  = 'AND c.id <> :siteid';
        $courseparams = ['siteid' => SITEID, 'ctxlevel' => $contextlevel];

        if ($courseid > 0) {
            $coursewhere .= ' AND c.id = :courseid';
            $courseparams['courseid'] = $courseid;
        } elseif ($categoryid > 0) {
            $coursewhere .= ' AND c.category = :categoryid';
            $courseparams['categoryid'] = $categoryid;
        }

        $sql = "SELECT u.id,
                       u.firstname,
                       u.lastname,
                       u.email,
                       u.lastaccess,
                       COUNT(DISTINCT c.id)                                           AS courses,
                       COUNT(DISTINCT us.id)                                           AS students,
                       COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                                 AND us.id IS NOT NULL THEN cc.userid ELSE NULL END)   AS completions,
                       AVG(CASE WHEN gg.finalgrade IS NOT NULL AND gi.grademax > 0
                                 AND us.id IS NOT NULL
                                THEN gg.finalgrade / gi.grademax * 100
                                ELSE NULL END)                                         AS avggrade
                  FROM {user} u
                  JOIN {role_assignments} ra  ON ra.userid = u.id
                  JOIN {role} r               ON r.id = ra.roleid
                                             AND r.shortname IN ('editingteacher', 'teacher')
                  JOIN {context} ctx          ON ctx.id = ra.contextid
                                             AND ctx.contextlevel = :ctxlevel
                  JOIN {course} c             ON c.id = ctx.instanceid {$coursewhere}
             LEFT JOIN {enrol} e              ON e.courseid = c.id
             LEFT JOIN {user_enrolments} ue   ON ue.enrolid = e.id
             LEFT JOIN {user} us              ON us.id = ue.userid
                                             AND us.deleted = 0 AND us.suspended = 0
                                             AND us.id <> u.id
             LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
             LEFT JOIN {grade_items} gi       ON gi.courseid = c.id AND gi.itemtype = 'course'
             LEFT JOIN {grade_grades} gg      ON gg.itemid = gi.id AND gg.userid = ue.userid
                 WHERE u.deleted = 0 AND u.suspended = 0
              GROUP BY u.id, u.firstname, u.lastname, u.email, u.lastaccess
              ORDER BY u.lastname ASC, u.firstname ASC";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $courseparams) as $r) {
            $students    = (int) $r->students;
            $completions = (int) $r->completions;
            $rows[] = [
                'fullname'       => fullname($r),
                'email'          => $r->email,
                'courses'        => (int) $r->courses,
                'students'       => $students,
                'completionrate' => $students > 0
                    ? $this->fmt_pct($completions / $students * 100) . '%'
                    : '-',
                'avggrade'       => $r->avggrade !== null
                    ? $this->fmt_pct((float) $r->avggrade) . '%'
                    : '-',
                'lastactive'     => !empty($r->lastaccess)
                    ? userdate((int) $r->lastaccess, get_string('strftimedate', 'langconfig'))
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
        $instructors = count($data);
        $students    = array_sum(array_column($data, 'students'));
        $courses     = array_sum(array_column($data, 'courses'));

        return [
            ['label' => get_string('inst_total_instructors', 'local_leducon'), 'value' => $instructors],
            ['label' => get_string('inst_total_students',    'local_leducon'), 'value' => $students],
            ['label' => get_string('inst_total_courses',     'local_leducon'), 'value' => $courses],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $rates = [];
        $highload = 0;
        $inactive = 0;

        foreach ($data as $row) {
            $rate = (float) rtrim($row['completionrate'], '%');
            if ($rate > 0) {
                $rates[] = $rate;
            }
            if ((int)$row['students'] >= 200) {
                $highload++;
            }
            if ($row['lastactive'] === '-') {
                $inactive++;
            }
        }

        if (!empty($rates)) {
            $avgrate = round(array_sum($rates) / count($rates), 1);
            if ($avgrate >= 70) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x8C\x9F",
                    'type'   => 'success',
                    'title'  => get_string('insight_inst_highrate', 'local_leducon', $avgrate),
                    'detail' => get_string('insight_inst_highrate_detail', 'local_leducon'),
                ];
            } elseif ($avgrate < 30) {
                $insights[] = [
                    'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                    'type'   => 'warning',
                    'title'  => get_string('insight_inst_lowrate', 'local_leducon', $avgrate),
                    'detail' => get_string('insight_inst_lowrate_detail', 'local_leducon'),
                ];
            }
        }

        if ($highload > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x9A",
                'type'   => 'info',
                'title'  => get_string('insight_inst_highload', 'local_leducon', $highload),
                'detail' => get_string('insight_inst_highload_detail', 'local_leducon'),
            ];
        }

        if ($inactive > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xB4",
                'type'   => 'danger',
                'title'  => get_string('insight_inst_inactive', 'local_leducon', $inactive),
                'detail' => get_string('insight_inst_inactive_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        usort($data, function($a, $b) {
            $pa = (float) rtrim($a['completionrate'], '%');
            $pb = (float) rtrim($b['completionrate'], '%');
            return $pb <=> $pa;
        });
        $data = array_slice($data, 0, 12);

        $labels   = [];
        $students = [];
        $rates    = [];

        foreach ($data as $row) {
            $labels[]   = mb_substr($row['fullname'], 0, 22);
            $students[] = (int) $row['students'];
            $rates[]    = (float) rtrim($row['completionrate'], '%');
        }

        $chart = new \core\chart_bar();
        $s1 = new \core\chart_series(get_string('inst_students',       'local_leducon'), $students);
        $s2 = new \core\chart_series(get_string('inst_completionrate', 'local_leducon'), $rates);
        $s1->set_color('#93c5fd');
        $s2->set_color('#2563eb');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->set_labels($labels);
        return $OUTPUT->render($chart);
    }
}
