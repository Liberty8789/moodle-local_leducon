<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Forum Participation report.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_report extends base_report {

    public function get_name(): string {
        return get_string('report_forum', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'forumname'    => ['label' => get_string('fr_forumname',    'local_leducon'), 'sortable' => true],
            'coursename'   => ['label' => get_string('fr_coursename',   'local_leducon'), 'sortable' => true],
            'discussions'  => ['label' => get_string('fr_discussions',  'local_leducon'), 'sortable' => true],
            'posts'        => ['label' => get_string('fr_posts',        'local_leducon'), 'sortable' => true],
            'participants' => ['label' => get_string('fr_participants',  'local_leducon'), 'sortable' => true],
            'lastpost'     => ['label' => get_string('fr_lastpost',     'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('forum')) {
            return [];
        }

        $categoryid = (int) $this->filter('categoryid', 0);
        $courseid   = (int) $this->filter('courseid',   0);
        $from       = $this->resolve_from();
        $to         = $this->resolve_to();

        $where  = 'c.id <> :siteid';
        $params = ['siteid' => SITEID, 'from' => $from, 'to' => $to];

        if ($courseid > 0) {
            $where .= ' AND c.id = :courseid';
            $params['courseid'] = $courseid;
        } elseif ($categoryid > 0) {
            $where .= ' AND c.category = :categoryid';
            $params['categoryid'] = $categoryid;
        }

        $cohortid     = (int) $this->filter('cohortid', 0);
        $fpcohort     = '';
        $cohortparams = [];
        if ($cohortid > 0) {
            $fpcohort     = 'AND fp.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $cohortparams = ['cohortid' => $cohortid];
        }
        $params = array_merge($params, $cohortparams);

        $forums = $DB->get_records_sql(
            "SELECT f.id,
                    f.name          AS forumname,
                    c.fullname      AS coursename,
                    COUNT(DISTINCT fd.id)     AS discussions,
                    COUNT(fp.id)              AS posts,
                    COUNT(DISTINCT fp.userid) AS participants,
                    MAX(fp.created)           AS lastpost
               FROM {forum} f
               JOIN {course} c              ON c.id = f.course AND {$where}
          LEFT JOIN {forum_discussions} fd  ON fd.forum = f.id
          LEFT JOIN {forum_posts} fp        ON fp.discussion = fd.id
                                           AND fp.created BETWEEN :from AND :to
                                           {$fpcohort}
              GROUP BY f.id, f.name, c.fullname
              ORDER BY c.fullname ASC, f.name ASC",
            $params
        );

        $rows = [];
        foreach ($forums as $f) {
            $rows[] = [
                'forumname'    => $f->forumname,
                'coursename'   => $f->coursename,
                'discussions'  => (int) $f->discussions,
                'posts'        => (int) $f->posts,
                'participants' => (int) $f->participants,
                'lastpost'     => $f->lastpost ? $this->fmt_date((int) $f->lastpost) : '-',
            ];
        }

        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalposts  = array_sum(array_column($data, 'posts'));
        $totalparts  = array_sum(array_column($data, 'participants'));
        $deadforums  = 0;
        $activeforums = 0;

        foreach ($data as $row) {
            if ((int)$row['posts'] === 0) {
                $deadforums++;
            }
            if ((int)$row['posts'] >= 20) {
                $activeforums++;
            }
        }

        if ($deadforums > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xA4",
                'type'   => 'warning',
                'title'  => get_string('insight_fr_dead', 'local_leducon', $deadforums),
                'detail' => get_string('insight_fr_dead_detail', 'local_leducon'),
            ];
        }

        if ($activeforums > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\xA5",
                'type'   => 'success',
                'title'  => get_string('insight_fr_active', 'local_leducon', $activeforums),
                'detail' => get_string('insight_fr_active_detail', 'local_leducon'),
            ];
        }

        if ($totalparts > 0 && $totalposts > 0) {
            $postsper = round($totalposts / $totalparts, 1);
            if ($postsper >= 5) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x92\xAC",
                    'type'   => 'info',
                    'title'  => get_string('insight_fr_engaged', 'local_leducon', $postsper),
                    'detail' => get_string('insight_fr_engaged_detail', 'local_leducon'),
                ];
            }
        }

        return $insights;
    }

    public function get_summary(): array {
        $data  = $this->get_data();
        $posts = array_sum(array_column($data, 'posts'));
        $parts = array_sum(array_column($data, 'participants'));
        return [
            ['label' => get_string('fr_total_forums',  'local_leducon'), 'value' => count($data)],
            ['label' => get_string('fr_posts',         'local_leducon'), 'value' => $posts],
            ['label' => get_string('fr_participants',  'local_leducon'), 'value' => $parts],
        ];
    }
}
