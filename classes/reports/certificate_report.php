<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Certificate Report — auto-detects installed certificate plugins.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_report extends base_report {

    private $detectedplugins = null;

    public function get_name(): string {
        return get_string('report_certificate', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'fullname'   => ['label' => get_string('cert_user',       'local_leducon'), 'sortable' => true],
            'email'      => ['label' => get_string('cert_email',      'local_leducon'), 'sortable' => true],
            'certname'   => ['label' => get_string('cert_name',       'local_leducon'), 'sortable' => true],
            'coursename' => ['label' => get_string('cert_course',     'local_leducon'), 'sortable' => true],
            'dateissued' => ['label' => get_string('cert_dateissued', 'local_leducon'), 'sortable' => true],
            'plugin'     => ['label' => get_string('cert_plugin',     'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        $plugins = $this->detected_plugins();
        if (empty($plugins)) {
            return [];
        }

        $allrows = [];
        foreach ($plugins as $plugin) {
            $allrows = array_merge($allrows, $this->rows_for_plugin($plugin));
        }

        usort($allrows, function($a, $b) { return $b['rawdate'] - $a['rawdate']; });

        return array_map(function($row) {
            unset($row['rawdate']);
            return $row;
        }, $allrows);
    }

    public function get_summary(): array {
        if (empty($this->detected_plugins())) {
            return [
                ['label' => get_string('cert_noplugin', 'local_leducon'), 'value' => '—'],
            ];
        }

        $data = $this->get_data();
        if (empty($data)) {
            return [];
        }

        $uniquecerts      = count(array_unique(array_column($data, 'certname')));
        $uniquerecipients = count(array_unique(array_column($data, 'userid')));
        return [
            ['label' => get_string('cert_total_issued',      'local_leducon'), 'value' => count($data)],
            ['label' => get_string('cert_unique_certs',      'local_leducon'), 'value' => $uniquecerts],
            ['label' => get_string('cert_unique_recipients', 'local_leducon'), 'value' => $uniquerecipients],
        ];
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalissued      = count($data);
        $uniquerecipients = count(array_unique(array_column($data, 'userid')));
        $uniquecerts      = count(array_unique(array_column($data, 'certname')));

        if ($totalissued >= 50) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x9C",
                'type'   => 'success',
                'title'  => get_string('insight_cert_volume', 'local_leducon', $totalissued),
                'detail' => get_string('insight_cert_volume_detail', 'local_leducon', $uniquerecipients),
            ];
        }

        // Concentration: many certs, few recipients.
        if ($uniquerecipients > 0 && $totalissued > 0) {
            $ratio = round($totalissued / $uniquerecipients, 1);
            if ($ratio >= 3) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x8E\x93",
                    'type'   => 'info',
                    'title'  => get_string('insight_cert_achievers', 'local_leducon', $ratio),
                    'detail' => get_string('insight_cert_achievers_detail', 'local_leducon'),
                ];
            }
        }

        // Plugins detected.
        $plugins = array_unique(array_column($data, 'plugin'));
        if (count($plugins) > 1) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x94\x97",
                'type'   => 'info',
                'title'  => get_string('insight_cert_multiplugin', 'local_leducon', count($plugins)),
                'detail' => get_string('insight_cert_multiplugin_detail', 'local_leducon'),
            ];
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
            $key = ($row['coursename'] !== '-') ? $row['coursename'] : $row['certname'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $top = array_slice($counts, 0, 12, true);

        $chart = new \core\chart_bar();
        $series = new \core\chart_series(
            get_string('cert_total_issued', 'local_leducon'),
            array_values($top)
        );
        $series->set_color('#16a34a');
        $chart->add_series($series);
        $chart->set_labels(array_map(
            function($n) { return mb_substr($n, 0, 30); },
            array_keys($top)
        ));
        return $OUTPUT->render($chart);
    }

    public function get_trend_data(): array {
        $plugins = $this->detected_plugins();
        if (empty($plugins)) {
            return [];
        }

        global $DB;
        $now     = time();
        $start   = $now - 365 * DAYSECS;
        $monthly = [];

        foreach ($plugins as $plugin) {
            $table = $this->issues_table($plugin);
            if ($table === null) {
                continue;
            }
            try {
                $rows = $DB->get_records_sql(
                    "SELECT timecreated FROM {{$table}} WHERE timecreated BETWEEN :start AND :now",
                    ['start' => $start, 'now' => $now]
                );
                foreach ($rows as $r) {
                    $key = date('Y-m', (int) $r->timecreated);
                    $monthly[$key] = ($monthly[$key] ?? 0) + 1;
                }
            } catch (\dml_exception $e) {
                // Skip gracefully.
            }
        }

        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts     = strtotime("-{$i} months", $now);
            $key    = date('Y-m', $ts);
            $result[] = ['month' => date('M Y', $ts), 'value' => $monthly[$key] ?? 0];
        }
        return $result;
    }

    private function detected_plugins(): array {
        if ($this->detectedplugins !== null) {
            return $this->detectedplugins;
        }
        global $DB;
        $dbman = $DB->get_manager();
        $found = [];
        foreach (['certificate', 'customcert', 'tool_certificate'] as $plugin) {
            $table = $this->issues_table($plugin);
            if ($table !== null && $dbman->table_exists($table)) {
                $found[] = $plugin;
            }
        }
        $this->detectedplugins = $found;
        return $found;
    }

    private function issues_table(string $plugin): ?string {
        $map = [
            'certificate'      => 'certificate_issues',
            'customcert'       => 'customcert_issues',
            'tool_certificate' => 'tool_certificate_issues',
        ];
        return $map[$plugin] ?? null;
    }

    private function plugin_label(string $plugin): string {
        $map = [
            'certificate'      => get_string('cert_plugin_certificate',      'local_leducon'),
            'customcert'       => get_string('cert_plugin_customcert',       'local_leducon'),
            'tool_certificate' => get_string('cert_plugin_tool_certificate', 'local_leducon'),
        ];
        return $map[$plugin] ?? $plugin;
    }

    private function rows_for_plugin(string $plugin): array {
        global $DB;

        $from     = $this->resolve_from();
        $to       = $this->resolve_to();
        $courseid = (int) $this->filter('courseid', 0);
        $cohortid = (int) $this->filter('cohortid', 0);
        $inactive = $this->user_active_sql('u');
        $label    = $this->plugin_label($plugin);

        $params = ['datefrom' => $from, 'dateto' => $to];

        $coursefilter = '';
        $cohortfilter = '';

        if ($cohortid > 0) {
            $cohortfilter        = 'AND ci.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
            $params['cohortid'] = $cohortid;
        }

        try {
            switch ($plugin) {

                case 'certificate':
                    if ($courseid > 0) {
                        $coursefilter       = 'AND cert.course = :courseid';
                        $params['courseid'] = $courseid;
                    }
                    $sql = "SELECT ci.id,
                                   u.id        AS userid,
                                   u.firstname,
                                   u.lastname,
                                   u.email,
                                   cert.name   AS certname,
                                   c.fullname  AS coursename,
                                   ci.timecreated AS dateissued
                              FROM {certificate_issues} ci
                              JOIN {certificate} cert ON cert.id = ci.certificateid
                              JOIN {user}   u  ON u.id  = ci.userid
                              JOIN {course} c  ON c.id  = cert.course
                             WHERE ci.timecreated >= :datefrom
                               AND ci.timecreated <= :dateto
                               {$inactive}
                               {$coursefilter}
                               {$cohortfilter}
                          ORDER BY ci.timecreated DESC";
                    break;

                case 'customcert':
                    if ($courseid > 0) {
                        $coursefilter       = 'AND cc.course = :courseid';
                        $params['courseid'] = $courseid;
                    }
                    $sql = "SELECT ci.id,
                                   u.id        AS userid,
                                   u.firstname,
                                   u.lastname,
                                   u.email,
                                   cc.name     AS certname,
                                   c.fullname  AS coursename,
                                   ci.timecreated AS dateissued
                              FROM {customcert_issues} ci
                              JOIN {customcert} cc ON cc.id = ci.customcertid
                              JOIN {user}   u  ON u.id  = ci.userid
                              JOIN {course} c  ON c.id  = cc.course
                             WHERE ci.timecreated >= :datefrom
                               AND ci.timecreated <= :dateto
                               {$inactive}
                               {$coursefilter}
                               {$cohortfilter}
                          ORDER BY ci.timecreated DESC";
                    break;

                case 'tool_certificate':
                    if ($courseid > 0) {
                        $coursefilter       = 'AND ctx.instanceid = :courseid';
                        $params['courseid'] = $courseid;
                    }
                    $sql = "SELECT ci.id,
                                   u.id        AS userid,
                                   u.firstname,
                                   u.lastname,
                                   u.email,
                                   ct.name     AS certname,
                                   c.fullname  AS coursename,
                                   ci.timecreated AS dateissued
                              FROM {tool_certificate_issues} ci
                              JOIN {tool_certificate_templates} ct ON ct.id = ci.templateid
                              JOIN {user}    u   ON u.id = ci.userid
                         LEFT JOIN {context} ctx ON ctx.id = ct.contextid AND ctx.contextlevel = 50
                         LEFT JOIN {course}  c   ON c.id  = ctx.instanceid
                             WHERE ci.timecreated >= :datefrom
                               AND ci.timecreated <= :dateto
                               {$inactive}
                               {$coursefilter}
                               {$cohortfilter}
                          ORDER BY ci.timecreated DESC";
                    break;

                default:
                    return [];
            }

            $rows = [];
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $rows[] = [
                    'userid'     => (int) $r->userid,
                    'fullname'   => fullname($r),
                    'email'      => $r->email,
                    'certname'   => $r->certname,
                    'coursename' => !empty($r->coursename) ? format_string($r->coursename) : '-',
                    'dateissued' => $this->fmt_date($r->dateissued),
                    'rawdate'    => (int) $r->dateissued,
                    'plugin'     => $label,
                ];
            }
            return $rows;

        } catch (\dml_exception $e) {
            // Table may not exist for this certificate plugin variant — return empty gracefully.
            debugging('Certificate report SQL error (' . $plugin . '): ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }
}
