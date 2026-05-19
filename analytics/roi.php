<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * ROI Report -- Executive Summary and Analyst Detail views.
 *
 * Executive tab: KPI cards, top-3 courses, trend chart, narrative paragraph.
 * Analyst tab:   full per-course breakdown table with export.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/leducon:viewanalytics', $context);

$tab    = optional_param('tab', 'executive', PARAM_ALPHA);
$export = optional_param('export', '', PARAM_ALPHA);

// Analyst tab is now served by the report framework.
if ($tab === 'analyst' && $export === '') {
    $params = ['reporttype' => 'roi_analyst'];
    $fp_tmp = local_leducon_get_filter_params();
    if ((int)$fp_tmp['cohortid'] > 0) {
        $params['cohortid'] = (int)$fp_tmp['cohortid'];
    }
    redirect(new moodle_url('/local/leducon/analytics/report.php', $params));
}

$fp     = local_leducon_get_filter_params();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/analytics/roi.php'));
$PAGE->set_title(get_string('roi_title', 'local_leducon'));
$PAGE->set_heading(get_string('roi_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('roi_title', 'local_leducon'));

// ------------------------------------------------------------------
// Helper: format currency amount.
// ------------------------------------------------------------------
if (!function_exists('local_leducon_fmt_money')) {
    function local_leducon_fmt_money(float $amount, string $currency): string {
        $symbols = ['USD' => '$', 'GBP' => "\xC2\xA3", 'EUR' => "\xE2\x82\xAC", 'NGN' => "\xE2\x82\xA6", 'ZAR' => 'R'];
        $sym     = $symbols[$currency] ?? ($currency . ' ');
        return $sym . number_format($amount, 2);
    }
}

// ------------------------------------------------------------------
// Core data query -- per course ROI.
// ------------------------------------------------------------------
$pfrom    = (int)$fp['datefrom_resolved'];
$pto      = (int)$fp['dateto_resolved'];
$cohortid = (int)$fp['cohortid'];

$cohortjoin   = '';
$cohortparams = [];
if ($cohortid > 0) {
    $cohortjoin   = 'AND ue.userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)';
    $cohortparams = ['cohortid' => $cohortid];
}

$datewhere  = '';
$dateparams = [];
if ($pfrom > 0) {
    $datewhere  = 'AND ue.timecreated BETWEEN :pfrom AND :pto';
    $dateparams = ['pfrom' => $pfrom, 'pto' => $pto];
}

$coursedata = [];
try {
    $coursedata = $DB->get_records_sql(
        "SELECT c.id,
                c.fullname,
                cat.name                                                         AS catname,
                cc_cost.costperlearner,
                cc_cost.valuepercompletion,
                cc_cost.currency,
                cc_cost.notes,
                COUNT(DISTINCT ue.userid)                                                      AS enrolled,
                COUNT(DISTINCT CASE WHEN comp.timecompleted IS NOT NULL
                    OR (gg_roi.finalgrade IS NOT NULL AND gi_roi.grademax > 0 AND gg_roi.finalgrade / gi_roi.grademax * 100 >= 100)
                    THEN comp.userid END) AS completions
           FROM {course} c
           JOIN {course_categories} cat          ON cat.id = c.category
           JOIN {local_leducon_course_costs} cc_cost ON cc_cost.courseid = c.id
           JOIN {enrol} e                        ON e.courseid = c.id
           JOIN {user_enrolments} ue             ON ue.enrolid = e.id
                                                    {$datewhere}
                                                    {$cohortjoin}
           JOIN {user} u                         ON u.id = ue.userid
                                                 AND u.deleted = 0 AND u.suspended = 0
      LEFT JOIN {course_completions} comp        ON comp.course = c.id AND comp.userid = ue.userid
      LEFT JOIN {grade_items} gi_roi             ON gi_roi.courseid = c.id AND gi_roi.itemtype = 'course'
      LEFT JOIN {grade_grades} gg_roi            ON gg_roi.itemid = gi_roi.id AND gg_roi.userid = ue.userid
          WHERE c.id <> :siteid
          GROUP BY c.id, c.fullname, cat.name,
                   cc_cost.costperlearner, cc_cost.valuepercompletion,
                   cc_cost.currency, cc_cost.notes
          ORDER BY c.fullname ASC",
        array_merge(['siteid' => SITEID], $dateparams, $cohortparams)
    );
} catch (\dml_exception $e) {
    $coursedata = [];
}

// ------------------------------------------------------------------
// Compute ROI per course.
// ------------------------------------------------------------------
$rows = [];
foreach ($coursedata as $cd) {
    $enrolled    = (int)$cd->enrolled;
    $completions = (int)$cd->completions;
    $costpl      = (float)$cd->costperlearner;
    $valuepc     = (float)$cd->valuepercompletion > 0
                    ? (float)$cd->valuepercompletion
                    : $costpl * 1.5;
    $currency    = $cd->currency ?: 'USD';

    $total_cost  = $enrolled    * $costpl;
    $total_value = $completions * $valuepc;
    $net_return  = $total_value - $total_cost;
    $roi_pct     = $total_cost > 0
                    ? round($net_return / $total_cost * 100, 1)
                    : null;
    $comp_rate   = $enrolled > 0 ? round($completions / $enrolled * 100, 1) : 0.0;

    $rows[] = [
        'id'          => (int)$cd->id,
        'fullname'    => format_string($cd->fullname),
        'catname'     => format_string($cd->catname),
        'currency'    => $currency,
        'enrolled'    => $enrolled,
        'completions' => $completions,
        'comp_rate'   => $comp_rate,
        'total_cost'  => $total_cost,
        'total_value' => $total_value,
        'net_return'  => $net_return,
        'roi_pct'     => $roi_pct,
        'notes'       => $cd->notes,
    ];
}

// ------------------------------------------------------------------
// Aggregate KPIs.
// ------------------------------------------------------------------
$agg_investment  = array_sum(array_column($rows, 'total_cost'));
$agg_value       = array_sum(array_column($rows, 'total_value'));
$agg_net         = $agg_value - $agg_investment;
$agg_roi         = $agg_investment > 0 ? round($agg_net / $agg_investment * 100, 1) : null;
$agg_completions = array_sum(array_column($rows, 'completions'));
$agg_enrolled    = array_sum(array_column($rows, 'enrolled'));
$cost_per_comp   = $agg_completions > 0 ? round($agg_investment / $agg_completions, 2) : null;
$currency_label  = !empty($rows) ? $rows[0]['currency'] : 'USD';
$all_currencies  = array_unique(array_column($rows, 'currency'));
$mixed_currency  = count($all_currencies) > 1;

// Top 3 by ROI %.
$sorted_by_roi = $rows;
usort($sorted_by_roi, function($a, $b) { return ($b['roi_pct'] ?? -9999) <=> ($a['roi_pct'] ?? -9999); });
$top3 = array_slice(array_filter($sorted_by_roi, function($r) { return $r['roi_pct'] !== null; }), 0, 3);

// Chart data -- top 10 courses by ROI % (built before header).
$chart_rows   = array_slice(array_filter($sorted_by_roi, function($r) { return $r['roi_pct'] !== null; }), 0, 10);
$chartlabels  = array_map(function($r) { return mb_substr($r['fullname'], 0, 28); }, $chart_rows);
$chart_roi    = array_map(function($r) { return $r['roi_pct']; }, $chart_rows);
$chart_colors = array_map(function($r) { return $r['roi_pct'] >= 0 ? '#28a745' : '#dc3545'; }, $chart_rows);

$roichart = null;
if (!empty($chart_roi)) {
    $roichart = new \core\chart_bar();
    $roichart->set_horizontal(true);
    $series = new \core\chart_series(get_string('roi_col_roi', 'local_leducon'), $chart_roi);
    $series->set_colors($chart_colors);
    $roichart->add_series($series);
    $roichart->set_labels($chartlabels);
}

// ------------------------------------------------------------------
// Inline CSV/Excel export for analyst tab.
// ------------------------------------------------------------------
if ($export === 'csv' || $export === 'excel') {
    $filename = clean_filename('roi_report_' . date('Ymd_His'));
    $expheaders = [
        get_string('roi_col_course', 'local_leducon'),
        get_string('roi_col_currency', 'local_leducon'),
        get_string('roi_col_enrolled', 'local_leducon'),
        get_string('roi_col_completions', 'local_leducon'),
        get_string('roi_col_rate', 'local_leducon'),
        get_string('roi_col_cost', 'local_leducon'),
        get_string('roi_col_value', 'local_leducon'),
        get_string('roi_col_net', 'local_leducon'),
        get_string('roi_col_roi', 'local_leducon'),
    ];

    $exprows = [];
    foreach ($rows as $r) {
        $exprows[] = [
            $r['fullname'],
            $r['currency'],
            $r['enrolled'],
            $r['completions'],
            $r['comp_rate'] . '%',
            $r['total_cost'],
            $r['total_value'],
            $r['net_return'],
            $r['roi_pct'] !== null ? $r['roi_pct'] . '%' : '',
        ];
    }

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $expheaders);
        foreach ($exprows as $line) {
            fputcsv($out, $line);
        }
        fclose($out);
    } else {
        require_once($CFG->libdir . '/excellib.class.php');
        $workbook = new MoodleExcelWorkbook('-');
        $workbook->send($filename . '.xlsx');
        $sheet = $workbook->add_worksheet('ROI');
        $fmt   = $workbook->add_format(['bold' => 1]);
        foreach ($expheaders as $ci => $h) {
            $sheet->write_string(0, $ci, $h, $fmt);
        }
        $ri = 1;
        foreach ($exprows as $line) {
            foreach ($line as $ci => $val) {
                is_numeric($val) ? $sheet->write_number($ri, $ci, $val)
                                 : $sheet->write_string($ri, $ci, (string)$val);
            }
            $ri++;
        }
        $workbook->close();
    }
    die();
}

// ------------------------------------------------------------------
// Render.
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'roi', $USER->id);
echo $OUTPUT->heading(get_string('roi_title', 'local_leducon'));

local_leducon_render_filter_bar(
    new moodle_url('/local/leducon/analytics/roi.php'),
    $fp,
    ['show_cohort' => true, 'show_org' => true, 'extra_hidden' => ['tab' => $tab]]
);

if ($mixed_currency) {
    echo $OUTPUT->notification(
        get_string('roi_mixed_currency', 'local_leducon', implode(', ', $all_currencies)),
        'warning'
    );
}

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('roi_nodata', 'local_leducon'), 'info');
    if (has_capability('local/leducon:manageskills', $context)) {
        echo html_writer::tag('p',
            html_writer::link(
                new moodle_url('/local/leducon/analytics/roi_costs.php'),
                s(get_string('roi_link_costs', 'local_leducon')),
                ['class' => 'ld-btn ld-btn-sm ld-btn-primary']
            )
        );
    }
    echo $OUTPUT->footer();
    die();
}

// Tab navigation.
echo '<ul class="nav nav-tabs mb-4">';
foreach (['executive' => get_string('roi_tab_executive', 'local_leducon'),
          'analyst'   => get_string('roi_tab_analyst',   'local_leducon')] as $tkey => $tlabel) {
    $turl = new moodle_url('/local/leducon/analytics/roi.php', ['tab' => $tkey]);
    echo '<li class="nav-item">';
    echo '<a class="nav-link ' . ($tab === $tkey ? 'active' : '') . '" href="' . $turl->out(false) . '">'
        . s($tlabel) . '</a>';
    echo '</li>';
}
echo '</ul>';

// ================================================================
// EXECUTIVE SUMMARY TAB
// ================================================================
if ($tab === 'executive') {

    // KPI strip -- 6 cards.
    echo html_writer::start_div('ld-kpi-strip');

    $kpis = [
        [get_string('roi_kpi_investment',      'local_leducon'), local_leducon_fmt_money($agg_investment, $currency_label), 'primary'],
        [get_string('roi_kpi_value',           'local_leducon'), local_leducon_fmt_money($agg_value,      $currency_label), 'success'],
        [get_string('roi_kpi_roi',             'local_leducon'), $agg_roi !== null ? $agg_roi . '%' : "\xE2\x80\x94",
            $agg_roi === null ? 'secondary' : ($agg_roi >= 0 ? 'success' : 'danger')],
        [get_string('roi_kpi_costpercompletion','local_leducon'),
            $cost_per_comp !== null ? local_leducon_fmt_money($cost_per_comp, $currency_label) : "\xE2\x80\x94", 'info'],
        [get_string('roi_kpi_completions',     'local_leducon'), number_format($agg_completions), 'warning'],
        [get_string('roi_kpi_courses',         'local_leducon'), count($rows),                    'secondary'],
    ];

    foreach ($kpis as [$label, $value, $color]) {
        echo html_writer::start_div('');
        echo html_writer::start_div('ld-stat-card ld-' . $color);
        echo html_writer::tag('div', s((string)$value), ['class' => 'ld-stat-value']);
        echo html_writer::tag('div', s($label),          ['class' => 'ld-stat-label']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    // Narrative paragraph.
    $narrativedata = (object)[
        'courses'     => count($rows),
        'completions' => number_format($agg_completions),
        'investment'  => local_leducon_fmt_money($agg_investment, $currency_label),
        'value'       => local_leducon_fmt_money($agg_value,      $currency_label),
        'roi'         => $agg_roi !== null ? $agg_roi . '%' : "\xE2\x80\x94",
    ];
    echo html_writer::start_div('alert alert-light border mb-4');
    echo html_writer::tag('p',
        get_string('roi_executive_narrative', 'local_leducon', $narrativedata),
        ['class' => 'mb-0']
    );
    echo html_writer::end_div();

    // Two columns: chart + Top 3.
    echo html_writer::start_div('ld-overview-split');

    // ROI chart.
    echo html_writer::start_div('ld-overview-half');
    echo html_writer::start_div('card p-3 h-100');
    echo html_writer::tag('h5', s(get_string('roi_chart_title', 'local_leducon')), ['class' => 'mb-3']);
    if ($roichart) {
        try {
            echo $OUTPUT->render($roichart);
        } catch (\Throwable $e) {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
    } else {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Top 3 highest-ROI courses.
    echo html_writer::start_div('ld-overview-half');
    echo html_writer::start_div('card p-3 h-100');
    echo html_writer::tag('h5', s(get_string('roi_top3', 'local_leducon')), ['class' => 'mb-3']);

    if (empty($top3)) {
        echo html_writer::tag('p', "\xE2\x80\x94", ['class' => 'text-muted']);
    } else {
        foreach ($top3 as $i => $r) {
            $medal   = ["\xF0\x9F\xA5\x87", "\xF0\x9F\xA5\x88", "\xF0\x9F\xA5\x89"][$i] ?? ($i + 1) . '.';
            $roobadge = html_writer::tag('span',
                ($r['roi_pct'] >= 0 ? '+' : '') . $r['roi_pct'] . '%',
                ['class' => 'badge badge-' . ($r['roi_pct'] >= 0 ? 'success' : 'danger') . ' float-right']
            );
            echo html_writer::start_div('border-bottom pb-2 mb-2');
            echo html_writer::tag('div',
                $medal . ' ' . $roobadge . html_writer::tag('strong', s($r['fullname'])),
                []
            );
            echo html_writer::tag('small',
                number_format($r['completions']) . ' completions · ' .
                local_leducon_fmt_money($r['total_value'], $r['currency']) . ' value',
                ['class' => 'text-muted']
            );
            echo html_writer::end_div();
        }
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // row

    // Link to analyst view.
    echo html_writer::tag('p',
        html_writer::link(
            new moodle_url('/local/leducon/analytics/roi.php', ['tab' => 'analyst']),
            get_string('roi_tab_analyst', 'local_leducon') . ' ->',
            ['class' => 'ld-btn ld-btn-sm ld-btn-primary']
        )
    );
}

// ================================================================
// ANALYST DETAIL TAB
// ================================================================
if ($tab === 'analyst') {

    // Export buttons -- handled inline by this file (no matching report class).
    if (has_capability('local/leducon:viewanalytics', $context)) {
        $exportbase = [
            'tab'      => 'analyst',
            'datefrom' => $fp['datefrom_resolved'],
            'dateto'   => $fp['dateto_resolved'],
            'cohortid' => $fp['cohortid'],
        ];
        $csvurl = new moodle_url('/local/leducon/analytics/roi.php',
            array_merge($exportbase, ['export' => 'csv']));
        $xlsurl = new moodle_url('/local/leducon/analytics/roi.php',
            array_merge($exportbase, ['export' => 'excel']));

        echo html_writer::start_div('ld-action-bar mb-3');
        echo html_writer::link($csvurl,
            s(get_string('export_csv',   'local_leducon')),
            ['class' => 'ld-action-btn ld-action-primary']);
        echo html_writer::link($xlsurl,
            s(get_string('export_excel', 'local_leducon')),
            ['class' => 'ld-action-btn']);
        echo html_writer::end_div();
    }

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead', ['class' => 'thead-light']);
    echo html_writer::tag('tr',
        html_writer::tag('th', s(get_string('roi_col_course',      'local_leducon'))) .
        html_writer::tag('th', s(get_string('roi_col_currency',    'local_leducon')), ['style' => 'width:70px']) .
        html_writer::tag('th', s(get_string('roi_col_enrolled',    'local_leducon')), ['style' => 'width:80px;text-align:center']) .
        html_writer::tag('th', s(get_string('roi_col_completions', 'local_leducon')), ['style' => 'width:100px;text-align:center']) .
        html_writer::tag('th', s(get_string('roi_col_rate',        'local_leducon')), ['style' => 'width:100px;text-align:center']) .
        html_writer::tag('th', s(get_string('roi_col_cost',        'local_leducon')), ['style' => 'width:110px;text-align:right']) .
        html_writer::tag('th', s(get_string('roi_col_value',       'local_leducon')), ['style' => 'width:110px;text-align:right']) .
        html_writer::tag('th', s(get_string('roi_col_net',         'local_leducon')), ['style' => 'width:110px;text-align:right']) .
        html_writer::tag('th', s(get_string('roi_col_roi',         'local_leducon')), ['style' => 'width:90px;text-align:center'])
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($rows as $r) {
        $roipct   = $r['roi_pct'];
        $roiclass = $roipct === null ? 'text-muted' : ($roipct >= 0 ? 'text-success font-weight-bold' : 'text-danger font-weight-bold');
        $roistr   = $roipct !== null ? ($roipct >= 0 ? '+' : '') . $roipct . '%' : "\xE2\x80\x94";

        $netclass = $r['net_return'] >= 0 ? 'text-success' : 'text-danger';

        // Completion rate bar.
        $pctint  = min(100, max(0, (int)round($r['comp_rate'])));
        $bar     = html_writer::tag('div', '', [
            'class' => 'ld-progress-bar ld-bg-' . ($r['comp_rate'] >= 75 ? 'success' : ($r['comp_rate'] >= 40 ? 'warning' : 'danger')),
            'style' => "width:{$pctint}%",
        ]);
        $ratecell = html_writer::tag('div', $bar, ['class' => 'ld-progress']) .
            html_writer::tag('small', $r['comp_rate'] . '%', ['class' => 'text-muted']);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td',
            html_writer::link(
                new moodle_url('/course/view.php', ['id' => $r['id']]),
                s($r['fullname'])
            ) . html_writer::tag('div', s($r['catname']), ['class' => 'small text-muted'])
        );
        echo html_writer::tag('td', s($r['currency']));
        echo html_writer::tag('td', number_format($r['enrolled']),    ['style' => 'text-align:center']);
        echo html_writer::tag('td', number_format($r['completions']), ['style' => 'text-align:center']);
        echo html_writer::tag('td', $ratecell,                         ['style' => 'min-width:100px']);
        echo html_writer::tag('td', local_leducon_fmt_money($r['total_cost'],  $r['currency']), ['style' => 'text-align:right']);
        echo html_writer::tag('td', local_leducon_fmt_money($r['total_value'], $r['currency']), ['style' => 'text-align:right']);
        echo html_writer::tag('td', html_writer::tag('span',
            local_leducon_fmt_money($r['net_return'], $r['currency']), ['class' => $netclass]),
            ['style' => 'text-align:right']);
        echo html_writer::tag('td', html_writer::tag('span', $roistr, ['class' => $roiclass]),
            ['style' => 'text-align:center']);
        echo html_writer::end_tag('tr');
    }

    // Totals row.
    echo html_writer::start_tag('tr', ['class' => 'table-secondary font-weight-bold']);
    echo html_writer::tag('td', get_string('total'));
    echo html_writer::tag('td', s($currency_label));
    echo html_writer::tag('td', number_format($agg_enrolled),    ['style' => 'text-align:center']);
    echo html_writer::tag('td', number_format($agg_completions), ['style' => 'text-align:center']);
    echo html_writer::tag('td', '');
    echo html_writer::tag('td', local_leducon_fmt_money($agg_investment, $currency_label), ['style' => 'text-align:right']);
    echo html_writer::tag('td', local_leducon_fmt_money($agg_value,      $currency_label), ['style' => 'text-align:right']);
    $netclass = $agg_net >= 0 ? 'text-success' : 'text-danger';
    echo html_writer::tag('td', html_writer::tag('span',
        local_leducon_fmt_money($agg_net, $currency_label), ['class' => $netclass]),
        ['style' => 'text-align:right']);
    echo html_writer::tag('td',
        html_writer::tag('span',
            $agg_roi !== null ? ($agg_roi >= 0 ? '+' : '') . $agg_roi . '%' : "\xE2\x80\x94",
            ['class' => $agg_roi === null ? 'text-muted' : ($agg_roi >= 0 ? 'text-success' : 'text-danger')]
        ), ['style' => 'text-align:center']);
    echo html_writer::end_tag('tr');

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
