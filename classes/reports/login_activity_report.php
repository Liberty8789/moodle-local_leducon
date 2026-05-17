<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Engagement & Login Activity report.
 *
 * Consolidates login activity and user activity into a single engagement view.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login_activity_report extends base_report {

    public function get_name(): string {
        return get_string('report_login_activity', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'username'       => ['label' => get_string('la_username',       'local_leducon'), 'sortable' => true],
            'fullname'       => ['label' => get_string('la_fullname',       'local_leducon'), 'sortable' => true],
            'email'          => ['label' => get_string('la_email',          'local_leducon'), 'sortable' => true],
            'totallogins'    => ['label' => get_string('la_totallogins',    'local_leducon'), 'sortable' => true],
            'coursesactive'  => ['label' => get_string('ua_coursesactive',  'local_leducon'), 'sortable' => true],
            'activitiescomp' => ['label' => get_string('ua_activitiescomp', 'local_leducon'), 'sortable' => true],
            'lastlogin'      => ['label' => get_string('la_lastlogin',      'local_leducon'), 'sortable' => true],
            'daysinactive'   => ['label' => get_string('la_daysinactive',   'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $from = $this->resolve_from();
        $to   = $this->resolve_to();

        $cohortid     = (int) $this->filter('cohortid', 0);
        $cohortjoin   = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $cohortjoin   = 'AND u.id IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }

        $sql = "SELECT u.id,
                       u.username,
                       u.firstname,
                       u.lastname,
                       u.email,
                       u.lastaccess,
                       COUNT(DISTINCT ls.id)         AS totallogins,
                       MAX(ls.timecreated)           AS lastloginperiod,
                       COUNT(DISTINCT cmc_cm.course) AS coursesactive,
                       COUNT(DISTINCT cmc.id)        AS activitiescomp
                  FROM {user} u
             LEFT JOIN {logstore_standard_log} ls ON ls.userid = u.id
                                                  AND ls.action = 'loggedin'
                                                  AND ls.timecreated BETWEEN :from AND :to
             LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
                                                       AND cmc.completionstate > 0
                                                       AND cmc.timemodified BETWEEN :from2 AND :to2
             LEFT JOIN {course_modules} cmc_cm ON cmc_cm.id = cmc.coursemoduleid
                 WHERE u.id <> :guestid
                   AND u.username <> 'guest'
                   AND u.deleted = 0
                   AND u.suspended = 0
                   {$cohortjoin}
              GROUP BY u.id, u.username, u.firstname, u.lastname, u.email, u.lastaccess
             HAVING COUNT(DISTINCT ls.id) > 0
              ORDER BY totallogins DESC, u.lastname ASC";

        $guestid = $DB->get_field('user', 'id', ['username' => 'guest']) ?: 1;
        $params  = array_merge([
            'from'    => $from,
            'to'      => $to,
            'from2'   => $from,
            'to2'     => $to,
            'guestid' => $guestid,
        ], $cohortparams);

        $now  = time();
        $rows = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $lastaccess   = (int) $r->lastaccess;
            $daysinactive = $lastaccess > 0 ? (int) floor(($now - $lastaccess) / DAYSECS) : '-';

            $rows[] = [
                'username'       => $r->username,
                'fullname'       => fullname($r),
                'email'          => $r->email,
                'totallogins'    => (int) $r->totallogins,
                'coursesactive'  => (int) $r->coursesactive,
                'activitiescomp' => (int) $r->activitiescomp,
                'lastlogin'      => $this->fmt_date($r->lastloginperiod ?: $r->lastaccess),
                'daysinactive'   => $daysinactive,
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalusers  = count($data);
        $totallogins = array_sum(array_column($data, 'totallogins'));
        $inactive30  = 0;
        $powerusers  = 0;
        $zeroacts    = 0;
        $multicourse = 0;

        foreach ($data as $row) {
            $days = $row['daysinactive'];
            if (is_numeric($days) && (int)$days >= 30) {
                $inactive30++;
            }
            if ((int)$row['totallogins'] >= 50) {
                $powerusers++;
            }
            if ((int)$row['activitiescomp'] === 0) {
                $zeroacts++;
            }
            if ((int)$row['coursesactive'] >= 3) {
                $multicourse++;
            }
        }

        if ($inactive30 > 0) {
            $pct = round($inactive30 / $totalusers * 100);
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xA4",
                'type'   => 'warning',
                'title'  => get_string('insight_la_inactive30', 'local_leducon', $inactive30),
                'detail' => get_string('insight_la_inactive30_detail', 'local_leducon', $pct),
            ];
        }

        if ($powerusers > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xA5",
                'type'   => 'success',
                'title'  => get_string('insight_la_powerusers', 'local_leducon', $powerusers),
                'detail' => get_string('insight_la_powerusers_detail', 'local_leducon'),
            ];
        }

        $avglogins = round($totallogins / $totalusers, 1);
        if ($avglogins < 3) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x89",
                'type'   => 'danger',
                'title'  => get_string('insight_la_lowfreq', 'local_leducon', $avglogins),
                'detail' => get_string('insight_la_lowfreq_detail', 'local_leducon'),
            ];
        }

        if ($multicourse > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x8C\x9F",
                'type'   => 'success',
                'title'  => get_string('insight_ua_multicourse', 'local_leducon', $multicourse),
                'detail' => get_string('insight_ua_multicourse_detail', 'local_leducon'),
            ];
        }

        if ($zeroacts > 0 && $totalusers > 3) {
            $zeropct = round($zeroacts / $totalusers * 100);
            if ($zeropct >= 25) {
                $insights[] = [
                    'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                    'type'   => 'warning',
                    'title'  => get_string('insight_ua_noactivities', 'local_leducon', $zeroacts),
                    'detail' => get_string('insight_ua_noactivities_detail', 'local_leducon'),
                ];
            }
        }

        return $insights;
    }

    public function get_summary(): array {
        global $DB;

        $from = $this->resolve_from();
        $to   = $this->resolve_to();

        $active = $DB->count_records_select(
            'logstore_standard_log',
            "action = 'loggedin' AND timecreated BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to]
        );

        $cutoff   = time() - 90 * DAYSECS;
        $inactive = $DB->count_records_select(
            'user',
            'lastaccess < :cutoff AND lastaccess > 0 AND deleted = 0',
            ['cutoff' => $cutoff]
        );

        return [
            ['label' => get_string('la_logins',      'local_leducon'), 'value' => $active],
            ['label' => get_string('la_daysinactive','local_leducon') . ' > 90', 'value' => $inactive],
        ];
    }
}
