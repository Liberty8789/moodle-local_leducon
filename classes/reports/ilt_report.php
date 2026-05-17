<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * ILT / VILT Sessions report — session-level analytics with cost, capacity,
 * facilitator performance, and attendance intelligence.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ilt_report extends base_report {

    public function get_name(): string {
        return get_string('report_ilt', 'local_leducon');
    }

    public function get_columns(): array {
        return [
            'date'             => ['label' => get_string('ilt_date',             'local_leducon'), 'sortable' => true],
            'title'            => ['label' => get_string('ilt_session_title',    'local_leducon'), 'sortable' => true],
            'type'             => ['label' => get_string('ilt_type',             'local_leducon'), 'sortable' => true],
            'facilitator'      => ['label' => get_string('ilt_facilitator',      'local_leducon'), 'sortable' => true],
            'department'       => ['label' => get_string('ilt_department',       'local_leducon'), 'sortable' => true],
            'registered'       => ['label' => get_string('ilt_registered',       'local_leducon'), 'sortable' => true],
            'attended'         => ['label' => get_string('ilt_attended',         'local_leducon'), 'sortable' => true],
            'noshow'           => ['label' => get_string('ilt_noshow',           'local_leducon'), 'sortable' => true],
            'attendance_rate'  => ['label' => get_string('ilt_attendance_rate',  'local_leducon'), 'sortable' => true],
            'duration_hours'   => ['label' => get_string('ilt_duration_hours',   'local_leducon'), 'sortable' => true],
            'cost_per_head'    => ['label' => get_string('ilt_cost_per_head',    'local_leducon'), 'sortable' => true],
            'total_cost'       => ['label' => get_string('ilt_total_cost',       'local_leducon'), 'sortable' => true],
            'fill_rate'        => ['label' => get_string('ilt_fill_rate',        'local_leducon'), 'sortable' => true],
            'avg_rating'       => ['label' => get_string('ilt_avg_rating',       'local_leducon'), 'sortable' => true],
            'pass_rate'        => ['label' => get_string('ilt_pass_rate',        'local_leducon'), 'sortable' => true],
            'location'         => ['label' => get_string('ilt_location',         'local_leducon'), 'sortable' => true],
        ];
    }

    public function get_data(): array {
        $from = $this->resolve_from();
        $to   = $this->resolve_to();
        $raw  = $this->parse_all_files();

        if (empty($raw)) {
            return [];
        }

        // Aggregate by session (date + title + facilitator).
        $sessions = [];
        foreach ($raw as $row) {
            $ts = strtotime($row['date']);
            if ($ts === false || $ts < $from || $ts > $to) {
                continue;
            }
            $key = $row['date'] . '|' . $row['title'] . '|' . $row['facilitator'];
            if (!isset($sessions[$key])) {
                $sessions[$key] = [
                    'ts'           => $ts,
                    'date'         => $row['date'],
                    'title'        => $row['title'],
                    'type'         => $row['type'],
                    'facilitator'  => $row['facilitator'],
                    'department'   => $row['department'],
                    'location'     => $row['location'] ?? '',
                    'max_capacity' => 0,
                    'registered'   => 0,
                    'attended'     => 0,
                    'noshow'       => 0,
                    'passed'       => 0,
                    'total_mins'   => 0,
                    'total_cost'   => 0,
                    'ratings'      => [],
                    'attendees'    => 0,
                ];
            }

            $s = &$sessions[$key];
            $s['attendees']++;
            $s['registered']++;

            $outcome = strtolower(trim($row['outcome']));
            if ($outcome === 'attended' || $outcome === 'pass' || $outcome === 'complete') {
                $s['attended']++;
            }
            if ($outcome === 'no show' || $outcome === 'absent' || $outcome === 'noshow') {
                $s['noshow']++;
            }
            if ($outcome === 'pass' || $outcome === 'complete') {
                $s['passed']++;
            }

            $mins = (int) ($row['duration_minutes'] ?? 0);
            if ($mins > $s['total_mins']) {
                $s['total_mins'] = $mins;
            }

            if (!empty($row['cost_per_head']) && is_numeric($row['cost_per_head'])) {
                $s['total_cost'] += (float) $row['cost_per_head'];
            }

            if (!empty($row['max_capacity']) && is_numeric($row['max_capacity'])) {
                $s['max_capacity'] = max($s['max_capacity'], (int) $row['max_capacity']);
            }

            if (!empty($row['rating']) && is_numeric($row['rating'])) {
                $s['ratings'][] = (float) $row['rating'];
            }
        }

        // Build output rows.
        $rows = [];
        foreach ($sessions as $s) {
            $registered = $s['registered'];
            $attended   = $s['attended'];
            $durationh  = $s['total_mins'] > 0 ? round($s['total_mins'] / 60, 1) : '-';
            $atrate     = $registered > 0 ? round($attended / $registered * 100, 1) : 0;
            $passrate   = $attended > 0 ? round($s['passed'] / $attended * 100, 1) : 0;
            $fillrate   = $s['max_capacity'] > 0 ? round($registered / $s['max_capacity'] * 100, 1) : '-';
            $avgrating  = !empty($s['ratings']) ? round(array_sum($s['ratings']) / count($s['ratings']), 1) : '-';
            $costhead   = $registered > 0 && $s['total_cost'] > 0
                ? round($s['total_cost'] / $registered, 2)
                : '-';

            $rows[] = [
                'date'            => userdate($s['ts'], get_string('strftimedate', 'langconfig')),
                'title'           => $s['title'],
                'type'            => $s['type'],
                'facilitator'     => $s['facilitator'],
                'department'      => $s['department'],
                'registered'      => $registered,
                'attended'        => $attended,
                'noshow'          => $s['noshow'],
                'attendance_rate' => $atrate . '%',
                'duration_hours'  => $durationh,
                'cost_per_head'   => is_numeric($costhead) ? number_format($costhead, 2) : '-',
                'total_cost'      => $s['total_cost'] > 0 ? number_format($s['total_cost'], 2) : '-',
                'fill_rate'       => is_numeric($fillrate) ? $fillrate . '%' : '-',
                'avg_rating'      => is_numeric($avgrating) ? $avgrating . '/5' : '-',
                'pass_rate'       => $passrate . '%',
                'location'        => $s['location'] ?: '-',
                '_raw_ts'         => $s['ts'],
                '_raw_attended'   => $attended,
                '_raw_registered' => $registered,
                '_raw_noshow'     => $s['noshow'],
                '_raw_cost'       => $s['total_cost'],
                '_raw_rating'     => $avgrating,
                '_raw_passrate'   => $passrate,
                '_raw_atrate'     => $atrate,
            ];
        }

        usort($rows, function($a, $b) { return $b['_raw_ts'] - $a['_raw_ts']; });
        return $rows;
    }

    public function get_insights(array $data): array {
        $insights = [];
        if (empty($data)) {
            return $insights;
        }

        $totalsessions   = count($data);
        $totalregistered = 0;
        $totalattended   = 0;
        $totalnoshow     = 0;
        $totalcost       = 0;
        $lowattend       = 0;
        $highattend      = 0;
        $ratings         = [];
        $facilitators    = [];
        $departments     = [];

        foreach ($data as $row) {
            $totalregistered += $row['_raw_registered'];
            $totalattended   += $row['_raw_attended'];
            $totalnoshow     += $row['_raw_noshow'];
            $totalcost       += $row['_raw_cost'];

            if ($row['_raw_atrate'] < 60 && $row['_raw_registered'] >= 5) {
                $lowattend++;
            }
            if ($row['_raw_atrate'] >= 90) {
                $highattend++;
            }
            if (is_numeric($row['_raw_rating'])) {
                $ratings[] = (float) $row['_raw_rating'];
            }

            $fac = $row['facilitator'];
            if ($fac) {
                $facilitators[$fac] = ($facilitators[$fac] ?? 0) + 1;
            }
            $dept = $row['department'];
            if ($dept) {
                $departments[$dept] = ($departments[$dept] ?? 0) + $row['_raw_attended'];
            }
        }

        // Overall attendance rate.
        if ($totalregistered > 0) {
            $overallatrate = round($totalattended / $totalregistered * 100, 1);
            if ($overallatrate >= 85) {
                $insights[] = [
                    'icon'   => "\xE2\x9C\x85",
                    'type'   => 'success',
                    'title'  => get_string('insight_ilt_highattend', 'local_leducon', $overallatrate),
                    'detail' => get_string('insight_ilt_highattend_detail', 'local_leducon'),
                ];
            } elseif ($overallatrate < 60) {
                $insights[] = [
                    'icon'   => "\xE2\x9A\xA0\xEF\xB8\x8F",
                    'type'   => 'danger',
                    'title'  => get_string('insight_ilt_lowattend', 'local_leducon', $overallatrate),
                    'detail' => get_string('insight_ilt_lowattend_detail', 'local_leducon'),
                ];
            }
        }

        // No-show cost waste.
        if ($totalnoshow > 5 && $totalcost > 0 && $totalregistered > 0) {
            $costperhead = $totalcost / $totalregistered;
            $wastedcost = round($costperhead * $totalnoshow, 2);
            $a = new \stdClass();
            $a->noshow = $totalnoshow;
            $a->cost = number_format($wastedcost, 2);
            $insights[] = [
                'icon'   => "\xF0\x9F\x92\xB8",
                'type'   => 'danger',
                'title'  => get_string('insight_ilt_costwaste', 'local_leducon', $a),
                'detail' => get_string('insight_ilt_costwaste_detail', 'local_leducon'),
            ];
        } elseif ($totalnoshow > 5) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x9A\xAB",
                'type'   => 'warning',
                'title'  => get_string('insight_ilt_noshow', 'local_leducon', $totalnoshow),
                'detail' => get_string('insight_ilt_noshow_detail', 'local_leducon'),
            ];
        }

        // Low-attendance sessions.
        if ($lowattend > 0) {
            $insights[] = [
                'icon'   => "\xF0\x9F\x93\x89",
                'type'   => 'warning',
                'title'  => get_string('insight_ilt_lowsessions', 'local_leducon', $lowattend),
                'detail' => get_string('insight_ilt_lowsessions_detail', 'local_leducon'),
            ];
        }

        // Top facilitator.
        if (!empty($facilitators)) {
            arsort($facilitators);
            $topfac = array_keys($facilitators)[0];
            $topcount = $facilitators[$topfac];
            if ($topcount >= 3) {
                $a = new \stdClass();
                $a->name = $topfac;
                $a->count = $topcount;
                $insights[] = [
                    'icon'   => "\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x8F\xAB",
                    'type'   => 'info',
                    'title'  => get_string('insight_ilt_topfacilitator', 'local_leducon', $a),
                    'detail' => get_string('insight_ilt_topfacilitator_detail', 'local_leducon'),
                ];
            }
        }

        // Average rating.
        if (!empty($ratings)) {
            $avgr = round(array_sum($ratings) / count($ratings), 1);
            if ($avgr >= 4.0) {
                $insights[] = [
                    'icon'   => "\xE2\xAD\x90",
                    'type'   => 'success',
                    'title'  => get_string('insight_ilt_highrating', 'local_leducon', $avgr),
                    'detail' => get_string('insight_ilt_highrating_detail', 'local_leducon'),
                ];
            } elseif ($avgr < 3.0) {
                $insights[] = [
                    'icon'   => "\xF0\x9F\x93\x89",
                    'type'   => 'danger',
                    'title'  => get_string('insight_ilt_lowrating', 'local_leducon', $avgr),
                    'detail' => get_string('insight_ilt_lowrating_detail', 'local_leducon'),
                ];
            }
        }

        // Department engagement.
        if (count($departments) >= 3) {
            arsort($departments);
            $topdept = array_keys($departments)[0];
            $insights[] = [
                'icon'   => "\xF0\x9F\x8F\xA2",
                'type'   => 'info',
                'title'  => get_string('insight_ilt_topdept', 'local_leducon', $topdept),
                'detail' => get_string('insight_ilt_topdept_detail', 'local_leducon', $departments[$topdept]),
            ];
        }

        return $insights;
    }

    public function get_summary(): array {
        $data = $this->get_data();
        if (empty($data)) {
            return [
                ['label' => get_string('ilt_summary_sessions',        'local_leducon'), 'value' => 0],
                ['label' => get_string('ilt_summary_attendees',       'local_leducon'), 'value' => 0],
                ['label' => get_string('ilt_summary_attendance_rate', 'local_leducon'), 'value' => '-'],
            ];
        }

        $totalsessions   = count($data);
        $totalregistered = array_sum(array_column($data, '_raw_registered'));
        $totalattended   = array_sum(array_column($data, '_raw_attended'));
        $totalnoshow     = array_sum(array_column($data, '_raw_noshow'));
        $totalcost       = array_sum(array_column($data, '_raw_cost'));

        $atrate = $totalregistered > 0 ? number_format($totalattended / $totalregistered * 100, 1) . '%' : '-';

        $ilt = 0;
        $vilt = 0;
        $totalhours = 0;
        foreach ($data as $row) {
            if ($row['type'] === 'ILT') { $ilt++; }
            if ($row['type'] === 'VILT') { $vilt++; }
            if (is_numeric($row['duration_hours'])) {
                $totalhours += (float) $row['duration_hours'];
            }
        }

        $items = [
            ['label' => get_string('ilt_summary_sessions',        'local_leducon'), 'value' => $totalsessions],
            ['label' => get_string('ilt_summary_registered',      'local_leducon'), 'value' => $totalregistered],
            ['label' => get_string('ilt_summary_attended',        'local_leducon'), 'value' => $totalattended],
            ['label' => get_string('ilt_summary_attendance_rate', 'local_leducon'), 'value' => $atrate],
            ['label' => get_string('ilt_summary_noshows',         'local_leducon'), 'value' => $totalnoshow, 'color' => 'danger'],
            ['label' => get_string('ilt_summary_total_hours',     'local_leducon'), 'value' => number_format($totalhours, 1)],
        ];

        if ($totalcost > 0) {
            $items[] = ['label' => get_string('ilt_summary_total_cost', 'local_leducon'), 'value' => number_format($totalcost, 2)];
            if ($totalattended > 0) {
                $items[] = [
                    'label' => get_string('ilt_summary_cost_per_attendee', 'local_leducon'),
                    'value' => number_format($totalcost / $totalattended, 2),
                ];
            }
        }

        if ($ilt > 0 || $vilt > 0) {
            $items[] = ['label' => get_string('ilt_summary_ilt',  'local_leducon'), 'value' => $ilt];
            $items[] = ['label' => get_string('ilt_summary_vilt', 'local_leducon'), 'value' => $vilt];
        }

        return $items;
    }

    public function get_chart_html(array $data = []): ?string {
        global $OUTPUT;

        if (empty($data)) {
            return null;
        }

        // Chart 1: Monthly sessions by type.
        $iltmonthly  = [];
        $viltmonthly = [];
        foreach ($data as $row) {
            $key = date('Y-m', $row['_raw_ts']);
            if ($row['type'] === 'ILT') {
                $iltmonthly[$key] = ($iltmonthly[$key] ?? 0) + 1;
            } elseif ($row['type'] === 'VILT') {
                $viltmonthly[$key] = ($viltmonthly[$key] ?? 0) + 1;
            }
        }

        $labels   = [];
        $iltvals  = [];
        $viltvals = [];
        $now      = time();
        for ($i = 11; $i >= 0; $i--) {
            $ts       = strtotime("-{$i} months", $now);
            $key      = date('Y-m', $ts);
            $labels[] = date('M Y', $ts);
            $iltvals[]  = $iltmonthly[$key] ?? 0;
            $viltvals[] = $viltmonthly[$key] ?? 0;
        }

        $chart = new \core\chart_bar();
        $chart->set_stacked(true);
        $s1 = new \core\chart_series(get_string('ilt_type_ilt',  'local_leducon'), $iltvals);
        $s2 = new \core\chart_series(get_string('ilt_type_vilt', 'local_leducon'), $viltvals);
        $s1->set_color('#2563eb');
        $s2->set_color('#0891b2');
        $chart->add_series($s1);
        $chart->add_series($s2);
        $chart->set_labels($labels);

        $html = \html_writer::tag('h6', s(get_string('ilt_chart_sessions_by_month', 'local_leducon')),
                ['class' => 'text-muted mb-2']) . $OUTPUT->render($chart);

        // Chart 2: Top facilitators by session count.
        $facilitators = [];
        foreach ($data as $row) {
            $f = $row['facilitator'];
            if ($f) {
                $facilitators[$f] = ($facilitators[$f] ?? 0) + 1;
            }
        }
        if (count($facilitators) >= 2) {
            arsort($facilitators);
            $topfacs = array_slice($facilitators, 0, 8, true);

            $chart2 = new \core\chart_bar();
            $chart2->set_horizontal(true);
            $series2 = new \core\chart_series(
                get_string('ilt_chart_sessions', 'local_leducon'),
                array_values($topfacs)
            );
            $series2->set_color('#7c3aed');
            $chart2->add_series($series2);
            $chart2->set_labels(array_map(function($n) { return mb_substr($n, 0, 25); }, array_keys($topfacs)));

            $html .= \html_writer::tag('h6', s(get_string('ilt_chart_top_facilitators', 'local_leducon')),
                    ['class' => 'text-muted mb-2 mt-4']) . $OUTPUT->render($chart2);
        }

        return $html;
    }

    public function get_trend_data(): array {
        $raw = $this->parse_all_files();
        if (empty($raw)) {
            return [];
        }

        $monthly = [];
        foreach ($raw as $row) {
            $ts = strtotime($row['date']);
            if ($ts === false) {
                continue;
            }
            $key = date('Y-m', $ts);
            $monthly[$key] = ($monthly[$key] ?? 0) + 1;
        }

        $result = [];
        $now    = time();
        for ($i = 11; $i >= 0; $i--) {
            $ts       = strtotime("-{$i} months", $now);
            $key      = date('Y-m', $ts);
            $result[] = ['month' => date('M Y', $ts), 'value' => $monthly[$key] ?? 0];
        }

        return $result;
    }

    /**
     * Parses all uploaded ILT CSV files.
     *
     * Supports extended columns: location, max_capacity, cost_per_head, rating.
     */
    private function parse_all_files(): array {
        $ctx   = \context_system::instance();
        $fs    = get_file_storage();
        $files = $fs->get_area_files(
            $ctx->id, 'local_leducon', 'iltdata',
            false, 'timemodified ASC', false
        );

        $required = ['date', 'title', 'type', 'attendee_name'];
        $rows     = [];

        foreach ($files as $file) {
            $content = $file->get_content();
            $lines   = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
            $headers = null;
            $skip    = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $cols = str_getcsv($line);

                if ($headers === null) {
                    $headers = array_map('strtolower', array_map('trim', $cols));
                    foreach ($required as $req) {
                        if (!in_array($req, $headers, true)) {
                            $skip = true;
                            break;
                        }
                    }
                    continue;
                }

                if ($skip) {
                    break;
                }

                $cols = array_pad($cols, count($headers), '');
                $row  = array_combine($headers, array_slice($cols, 0, count($headers)));

                $rows[] = [
                    'date'             => trim($row['date']             ?? ''),
                    'title'            => trim($row['title']            ?? ''),
                    'type'             => strtoupper(trim($row['type']  ?? '')),
                    'facilitator'      => trim($row['facilitator']      ?? ''),
                    'department'       => trim($row['department']       ?? ''),
                    'attendee_name'    => trim($row['attendee_name']    ?? ''),
                    'attendee_email'   => strtolower(trim($row['attendee_email'] ?? '')),
                    'duration_minutes' => trim($row['duration_minutes'] ?? ''),
                    'outcome'          => trim($row['outcome']          ?? ''),
                    'location'         => trim($row['location']         ?? ''),
                    'max_capacity'     => trim($row['max_capacity']     ?? ''),
                    'cost_per_head'    => trim($row['cost_per_head']    ?? ''),
                    'rating'           => trim($row['rating']           ?? ''),
                ];
            }
        }

        return $rows;
    }
}
