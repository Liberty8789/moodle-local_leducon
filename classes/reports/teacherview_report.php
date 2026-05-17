<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Teacher View report — students in a selected course.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacherview_report extends base_report {

    public function get_name(): string {
        return get_string('report_teacherview', 'local_leducon');
    }

    public function get_required_capability(): string {
        return 'local/leducon:viewteacherview';
    }

    public function get_filter_options(): array {
        return [
            'show_course'   => true,
            'show_category' => false,
            'show_cohort'   => false,
            'show_org'      => false,
        ];
    }

    public function get_columns(): array {
        return [
            'fullname'    => ['label' => get_string('tv_student',    'local_leducon'), 'sortable' => true, 'html' => true],
            'email'       => ['label' => get_string('tv_email',      'local_leducon'), 'sortable' => true],
            'enrolled'    => ['label' => get_string('tv_enrolled',   'local_leducon'), 'sortable' => true],
            'status'      => ['label' => get_string('tv_status',     'local_leducon'), 'sortable' => true, 'html' => true],
            'grade'       => ['label' => get_string('tv_grade',      'local_leducon'), 'sortable' => true],
            'lastaccess'  => ['label' => get_string('tv_lastaccess', 'local_leducon'), 'sortable' => true],
            'actions'     => ['label' => get_string('tv_actions',    'local_leducon'), 'sortable' => false, 'html' => true],
        ];
    }

    public function get_data(): array {
        global $DB, $USER;

        $courseid = (int) $this->filter('courseid', 0);

        // If no course selected, pick the first available.
        if ($courseid <= 0) {
            $courses = $this->get_teacher_courses();
            if (!empty($courses)) {
                reset($courses);
                $courseid = key($courses);
            }
        }

        if ($courseid <= 0) {
            return [];
        }

        // Verify teacher has access to this course.
        $courses = $this->get_teacher_courses();
        if (!isset($courses[$courseid])) {
            return [];
        }

        $guestid = $DB->get_field('user', 'id', ['username' => 'guest']);
        if (!$guestid) {
            $guestid = 1;
        }

        $students = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess,
                    ue.timecreated AS timeenrolled,
                    comp.timecompleted,
                    gi.grademax,
                    gg.finalgrade
               FROM {user} u
               JOIN {user_enrolments} ue  ON ue.userid = u.id
               JOIN {enrol} e             ON e.id = ue.enrolid AND e.courseid = :courseid
          LEFT JOIN {course_completions} comp ON comp.course = :cid1 AND comp.userid = u.id
          LEFT JOIN {grade_items} gi      ON gi.courseid = :cid2 AND gi.itemtype = 'course'
          LEFT JOIN {grade_grades} gg     ON gg.itemid = gi.id AND gg.userid = u.id
              WHERE u.deleted = 0 AND u.suspended = 0 AND u.id <> :guestid
              ORDER BY u.lastname ASC, u.firstname ASC",
            ['courseid' => $courseid, 'cid1' => $courseid, 'cid2' => $courseid, 'guestid' => $guestid]
        );

        $rows = [];
        foreach ($students as $s) {
            $grade = (!empty($s->grademax) && $s->finalgrade !== null)
                ? number_format(($s->finalgrade / $s->grademax) * 100, 1) . '%'
                : '-';

            $completed = !empty($s->timecompleted);
            $statushtml = $completed
                ? \html_writer::tag('span', s(get_string('tv_completed', 'local_leducon')), ['class' => 'ld-badge ld-badge-success'])
                : \html_writer::tag('span', s(get_string('tv_notstarted', 'local_leducon')), ['class' => 'ld-badge ld-badge-secondary']);

            $cardurl = new \moodle_url('/local/leducon/analytics/myreport.php', ['userid' => $s->id]);
            $namelink = \html_writer::link($cardurl, s(fullname($s)));
            $actionlink = \html_writer::link($cardurl, s(get_string('tv_viewcard', 'local_leducon')),
                ['class' => 'ld-btn ld-btn-sm ld-btn-primary', 'target' => '_blank']);

            $rows[] = [
                'fullname'   => $namelink,
                'email'      => $s->email,
                'enrolled'   => $s->timeenrolled
                    ? userdate($s->timeenrolled, get_string('strftimedate', 'langconfig'))
                    : '-',
                'status'     => $statushtml,
                'grade'      => $grade,
                'lastaccess' => $s->lastaccess
                    ? userdate($s->lastaccess, get_string('strftimedate', 'langconfig'))
                    : '-',
                'actions'    => $actionlink,
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

        $completed = 0;
        $nograde = 0;
        $neveraccessed = 0;

        foreach ($data as $row) {
            if (strpos($row['status'], 'ld-badge-success') !== false) {
                $completed++;
            }
            if (($row['grade'] ?? '-') === '-') {
                $nograde++;
            }
            if (($row['lastaccess'] ?? '-') === '-') {
                $neveraccessed++;
            }
        }

        $comprate = round($completed / $total * 100);

        if ($comprate >= 80) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x86",
                'type'   => 'success',
                'title'  => get_string('insight_tv_highcomp', 'local_leducon', $comprate),
                'detail' => get_string('insight_tv_highcomp_detail', 'local_leducon'),
            ];
        } elseif ($comprate < 30 && $total >= 5) {
            $insights[] = [
                'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                'type'   => 'warning',
                'title'  => get_string('insight_tv_lowcomp', 'local_leducon', $comprate),
                'detail' => get_string('insight_tv_lowcomp_detail', 'local_leducon'),
            ];
        }

        if ($neveraccessed > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x91\xBB",
                'type'   => 'danger',
                'title'  => get_string('insight_tv_neverlogin', 'local_leducon', $neveraccessed),
                'detail' => get_string('insight_tv_neverlogin_detail', 'local_leducon'),
            ];
        }

        if ($nograde > ($total * 0.5) && $total >= 5) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x9D",
                'type'   => 'info',
                'title'  => get_string('insight_tv_nograde', 'local_leducon', $nograde),
                'detail' => get_string('insight_tv_nograde_detail', 'local_leducon'),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        $total = count($data);
        return [
            ['label' => get_string('teacherview_students', 'local_leducon'), 'value' => $total],
        ];
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        $comp = 0;
        $inprog = 0;
        $notst = 0;
        foreach ($data as $row) {
            $status = $row['status'];
            if (strpos($status, 'ld-badge-success') !== false) {
                $comp++;
            } elseif (strpos($status, 'ld-badge-secondary') !== false) {
                $notst++;
            } else {
                $inprog++;
            }
        }

        if (($comp + $inprog + $notst) === 0) {
            return null;
        }

        try {
            $pie = new \core\chart_pie();
            $ps = new \core\chart_series(
                get_string('chart_completion_status', 'local_leducon'),
                [$comp, $inprog, $notst]
            );
            $ps->set_colors(['#16a34a', '#d97706', '#e5e7eb']);
            $pie->add_series($ps);
            $pie->set_labels([
                get_string('chart_completed', 'local_leducon'),
                get_string('chart_inprogress', 'local_leducon'),
                get_string('chart_notstarted', 'local_leducon'),
            ]);
            $pie->set_doughnut(true);
            return $OUTPUT->render($pie);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get courses the current user may view students in.
     */
    private function get_teacher_courses(): array {
        global $DB, $USER;

        $ctx = \context_system::instance();
        $courses = [];

        if (has_capability('local/leducon:viewanalytics', $ctx)) {
            foreach ($DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC') as $c) {
                $courses[$c->id] = $c;
            }
        } else {
            foreach (enrol_get_all_users_courses($USER->id, true) as $c) {
                $cctx = \context_course::instance($c->id);
                if (has_capability('local/leducon:viewstudents', $cctx)) {
                    $courses[$c->id] = $c;
                }
            }
        }

        return $courses;
    }
}
