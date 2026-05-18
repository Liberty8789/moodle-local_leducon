<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Badge Report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_report extends base_report {

    const TYPE_SITE   = 1;
    const TYPE_COURSE = 2;

    public function get_name(): string {
        return get_string('report_badge', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'fullname'   => ['label' => get_string('badge_user',        'local_leducon'), 'sortable' => true],
            'email'      => ['label' => get_string('badge_email',       'local_leducon'), 'sortable' => true],
            'badgename'  => ['label' => get_string('badge_name',        'local_leducon'), 'sortable' => true],
            'badgetype'  => ['label' => get_string('badge_type',        'local_leducon'), 'sortable' => true],
            'coursename' => ['label' => get_string('badge_course',      'local_leducon'), 'sortable' => true],
            'dateissued' => ['label' => get_string('badge_dateissued',  'local_leducon'), 'sortable' => true],
            'dateexpire' => ['label' => get_string('badge_dateexpire',  'local_leducon'), 'sortable' => false],
        ];
    }

    public function get_data(): array {
        global $DB;

        $from     = $this->resolve_from();
        $to       = $this->resolve_to();
        $courseid = (int) $this->filter('courseid', 0);
        $cohortid = (int) $this->filter('cohortid', 0);
        $inactive = $this->user_active_sql('u');

        $params = ['datefrom' => $from, 'dateto' => $to];

        $coursefilter = '';
        if ($courseid > 0) {
            $coursefilter        = 'AND b.courseid = :courseid AND b.type = :badgetype';
            $params['courseid'] = $courseid;
            $params['badgetype']= self::TYPE_COURSE;
        }

        $cohortfilter = '';
        if ($cohortid > 0) {
            $cohortfilter        = 'AND bi.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        $sql = "SELECT bi.id,
                       u.id         AS userid,
                       u.firstname,
                       u.lastname,
                       u.email,
                       b.name       AS badgename,
                       b.type       AS badgetype,
                       c.fullname   AS coursename,
                       bi.dateissued,
                       bi.dateexpire
                  FROM {badge_issued} bi
                  JOIN {badge}  b    ON b.id = bi.badgeid
                  JOIN {user}   u    ON u.id = bi.userid
             LEFT JOIN {course} c    ON c.id = b.courseid AND b.type = " . self::TYPE_COURSE . "
                 WHERE bi.dateissued >= :datefrom
                   AND bi.dateissued <= :dateto
                   {$inactive}
                   {$coursefilter}
                   {$cohortfilter}
              ORDER BY bi.dateissued DESC";

        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $isCourse = ((int) $r->badgetype === self::TYPE_COURSE);
            $rows[] = [
                'userid'     => (int) $r->userid,
                'fullname'   => fullname($r),
                'email'      => $r->email,
                'badgename'  => $r->badgename,
                'badgetype'  => $isCourse
                    ? get_string('badge_type_course', 'local_leducon')
                    : get_string('badge_type_site',   'local_leducon'),
                'coursename' => ($isCourse && !empty($r->coursename))
                    ? format_string($r->coursename)
                    : get_string('badge_type_site', 'local_leducon'),
                'dateissued' => $this->fmt_date($r->dateissued),
                'dateexpire' => (!empty($r->dateexpire) && (int) $r->dateexpire > 0)
                    ? $this->fmt_date($r->dateexpire)
                    : get_string('badge_never_expire', 'local_leducon'),
            ];
        }

        return $rows;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }
        $uniquebadges     = count(array_unique(array_column($data, 'badgename')));
        $uniquerecipients = count(array_unique(array_column($data, 'userid')));
        return [
            ['label' => get_string('badge_total_issued',      'local_leducon'), 'value' => count($data)],
            ['label' => get_string('badge_unique_badges',     'local_leducon'), 'value' => $uniquebadges],
            ['label' => get_string('badge_unique_recipients', 'local_leducon'), 'value' => $uniquerecipients],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalissued      = count($data);
        $uniquerecipients = count(array_unique(array_column($data, 'userid')));
        $uniquebadges     = count(array_unique(array_column($data, 'badgename')));

        $avgper = $uniquerecipients > 0 ? round($totalissued / $uniquerecipients, 1) : 0;

        if ($avgper >= $this->threshold('insight_badge_multi', 3)) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\x85",
                'type'   => 'success',
                'title'  => get_string('insight_badge_multi', 'local_leducon', $avgper),
                'detail' => get_string('insight_badge_multi_detail', 'local_leducon'),
            ];
        }

        if ($uniquebadges > 0 && $totalissued > 0) {
            $counts = [];
            foreach ($data as $row) {
                $key = $row['badgename'];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            arsort($counts);
            $top = array_keys($counts);
            $topname = $top[0];
            $topcount = $counts[$topname];

            if ($topcount >= 5) {
                $a = new \stdClass();
                $a->name = $topname;
                $a->count = $topcount;
                $insights[] = [
                    'icon'   => "\xF0\x9F\x8C\x9F",
                    'type'   => 'info',
                    'title'  => get_string('insight_badge_popular', 'local_leducon', $a),
                    'detail' => get_string('insight_badge_popular_detail', 'local_leducon'),
                ];
            }

            // Badges rarely issued.
            $rare = 0;
            foreach ($counts as $c) {
                if ($c === 1) {
                    $rare++;
                }
            }
            if ($rare > 0 && $uniquebadges > 3) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x92\xA1",
                    'type'   => 'warning',
                    'title'  => get_string('insight_badge_rare', 'local_leducon', $rare),
                    'detail' => get_string('insight_badge_rare_detail', 'local_leducon'),
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
        $counts = [];
        foreach ($data as $row) {
            $key = $row['badgename'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $top = array_slice($counts, 0, 12, true);

        $chart = new \core\chart_bar();
        $series = new \core\chart_series(
            get_string('badge_total_issued', 'local_leducon'),
            array_values($top)
        );
        $series->set_color('#2563eb');
        $chart->add_series($series);
        $chart->set_labels(array_map(
            function($n) { return mb_substr($n, 0, 30); },
            array_keys($top)
        ));
        return $OUTPUT->render($chart);
    }

    public function get_trend_data(): array {
        global $DB;
        $now   = time();
        $start = $now - 365 * DAYSECS;

        try {
            $rows = $DB->get_records_sql(
                "SELECT dateissued FROM {badge_issued} WHERE dateissued BETWEEN :start AND :now",
                ['start' => $start, 'now' => $now]
            );
        } catch (\dml_exception $e) {
            debugging('Badge report trend SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }

        $monthly = [];
        foreach ($rows as $r) {
            $key = date('Y-m', (int) $r->dateissued);
            $monthly[$key] = ($monthly[$key] ?? 0) + 1;
        }

        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts     = strtotime("-{$i} months", $now);
            $key    = date('Y-m', $ts);
            $result[] = ['month' => date('M Y', $ts), 'value' => $monthly[$key] ?? 0];
        }
        return $result;
    }
}
