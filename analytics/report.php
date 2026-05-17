<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Enhanced report viewer — 17 report types with charts, export, and flexible table.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();
$context = context_system::instance();

$reporttype = optional_param('reporttype', 'course_completion', PARAM_ALPHANUMEXT);
$validtypes = array_keys(local_leducon_get_report_types());
if (!in_array($reporttype, $validtypes, true)) {
    $reporttype = 'course_completion';
}

$report = local_leducon_get_report($reporttype);
require_capability($report->get_required_capability(), $context);

$fp = local_leducon_get_filter_params();

$filters = [
    'datefrom'   => $fp['datefrom_resolved'],
    'dateto'     => $fp['dateto_resolved'],
    'courseid'   => $fp['courseid'],
    'categoryid' => $fp['categoryid'],
    'cohortid'   => $fp['cohortid'],
    'orgunitid'  => $fp['orgunitid'],
];
$report->set_filters($filters);

// Handle POST actions (e.g. "Notify Teachers").
$action = optional_param('reportaction', '', PARAM_ALPHANUMEXT);
if ($action !== '' && confirm_sesskey()) {
    $flashmsg = $report->execute_action($action, array_merge($filters, ['reporttype' => $reporttype]));
    $redirecturl = new moodle_url('/local/leducon/analytics/report.php',
        array_merge($filters, ['reporttype' => $reporttype]));
    redirect($redirecturl, $flashmsg, null, \core\output\notification::NOTIFY_SUCCESS);
}

$currenturl = new moodle_url('/local/leducon/analytics/report.php', array_merge($filters, ['reporttype' => $reporttype]));
$PAGE->set_context($context);
$PAGE->set_url($currenturl);
$PAGE->set_title($report->get_name());
$PAGE->set_heading($report->get_name());
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add($report->get_name(), $currenturl);

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'reports', $USER->id);
echo $OUTPUT->heading($report->get_name());

echo html_writer::start_div('ld-report-layout');

// Sidebar — grouped & collapsible.
echo html_writer::start_div('ld-report-sidebar-col');
echo html_writer::start_div('ld-report-sidebar');
echo html_writer::tag('div', get_string('nav_reports', 'local_leducon'), ['class' => 'ld-sidebar-heading']);
$groups = local_leducon_get_report_groups();
foreach ($groups as $gi => $group) {
    // Filter reports by capability — only show reports the user can access.
    $visiblereports = [];
    foreach ($group['reports'] as $key => $label) {
        try {
            $rpt = local_leducon_get_report($key);
            if (has_capability($rpt->get_required_capability(), $context)) {
                $visiblereports[$key] = $label;
            }
        } catch (\coding_exception $e) {
            $visiblereports[$key] = $label;
        }
    }
    if (empty($visiblereports)) {
        continue;
    }
    // Auto-expand the group containing the active report.
    $groupactive = array_key_exists($reporttype, $visiblereports);
    $collapsed = $groupactive ? '' : ' collapsed';
    echo '<div class="ld-sidebar-group' . $collapsed . '" data-group="' . $gi . '">';
    echo '<div class="ld-sidebar-group-toggle" onclick="this.parentNode.classList.toggle(\'collapsed\')">';
    echo '<span class="ld-sidebar-group-arrow">&#9660;</span>';
    echo '<span class="ld-sidebar-group-label">' . s($group['heading']) . '</span>';
    echo '<span class="ld-sidebar-group-count">' . count($visiblereports) . '</span>';
    echo '</div>';
    echo '<div class="ld-sidebar-group-items">';
    foreach ($visiblereports as $key => $label) {
        $rurl = new moodle_url('/local/leducon/analytics/report.php', ['reporttype' => $key]);
        $cls  = 'ld-sidebar-link' . ($key === $reporttype ? ' active' : '');
        echo html_writer::link($rurl, s($label), ['class' => $cls]);
    }
    echo '</div>';
    echo '</div>';
}
echo html_writer::end_div();
echo html_writer::end_div();

// Content.
echo html_writer::start_div('ld-report-content-col');

$filteropts = $report->get_filter_options();
$filteropts['extra_hidden'] = ['reporttype' => $reporttype];
local_leducon_render_filter_bar(
    new moodle_url('/local/leducon/analytics/report.php'),
    $fp,
    $filteropts
);

// Export buttons.
if (has_capability('local/leducon:viewanalytics', $context)) {
    $exportbase = [
        'reporttype' => $reporttype,
        'datefrom'   => $fp['datefrom_resolved'],
        'dateto'     => $fp['dateto_resolved'],
        'courseid'   => $fp['courseid'],
        'categoryid' => $fp['categoryid'],
        'cohortid'   => $fp['cohortid'],
    ];
    $csvurl = new moodle_url('/local/leducon/analytics/exportreport.php', array_merge($exportbase, ['format' => 'csv']));
    $xlsurl = new moodle_url('/local/leducon/analytics/exportreport.php', array_merge($exportbase, ['format' => 'excel']));
    $pdfurl = new moodle_url('/local/leducon/analytics/exportreport.php', array_merge($exportbase, ['format' => 'pdf']));

    echo html_writer::start_div('ld-action-bar');
    echo html_writer::link($csvurl, get_string('export_csv', 'local_leducon'), ['class' => 'ld-action-btn ld-action-primary']);
    echo html_writer::link($xlsurl, get_string('export_excel', 'local_leducon'), ['class' => 'ld-action-btn ld-action-primary']);
    echo html_writer::link($pdfurl, get_string('export_pdf', 'local_leducon'), ['class' => 'ld-action-btn ld-action-danger', 'target' => '_blank']);
    echo html_writer::end_div();
}

// Summary KPI cards.
$summary = $report->get_summary();
if (!empty($summary)) {
    echo html_writer::start_div('ld-kpi-strip');
    foreach ($summary as $card) {
        $color = isset($card['color']) ? $card['color'] : 'primary';
        echo html_writer::start_div('ld-stat-card ld-' . $color);
        echo html_writer::tag('div', s((string)$card['value']), ['class' => 'ld-stat-value']);
        echo html_writer::tag('div', s($card['label']), ['class' => 'ld-stat-label']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

$columns = $report->get_columns();
try {
    $data = $report->get_data();
} catch (\Throwable $e) {
    debugging('Report data error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo $OUTPUT->notification(get_string('error_db', 'local_leducon'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Dynamic insights strip.
$insights = $report->get_insights($data);
if (!empty($insights)) {
    echo html_writer::start_div('ld-insights-strip');
    foreach ($insights as $insight) {
        $type = isset($insight['type']) ? $insight['type'] : 'info';
        $icon = isset($insight['icon']) ? $insight['icon'] : '';
        echo html_writer::start_div('ld-insight ld-insight-' . $type);
        if ($icon) {
            echo html_writer::tag('span', $icon, ['class' => 'ld-insight-icon']);
        }
        echo html_writer::start_div('ld-insight-body');
        echo html_writer::tag('strong', s($insight['title']));
        if (!empty($insight['detail'])) {
            echo html_writer::tag('span', s($insight['detail']), ['class' => 'ld-insight-detail']);
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

$charthtml = $report->get_chart_html($data);
if ($charthtml !== null) {
    echo html_writer::start_div('ld-card', ['style' => 'padding:1rem;margin-bottom:1rem']);
    echo $charthtml;
    echo html_writer::end_div();
}

if (method_exists($report, 'get_trend_data')) {
    $trenddata = $report->get_trend_data();
    if (!empty($trenddata)) {
        $labels = array_column($trenddata, 'month');
        $values = array_column($trenddata, 'value');
        if (array_sum($values) > 0) {
            echo html_writer::start_div('ld-card', ['style' => 'padding:1rem;margin-bottom:1rem']);
            echo html_writer::tag('h6', get_string('trend_title', 'local_leducon'), ['class' => 'text-muted', 'style' => 'margin-bottom:.5rem']);
            try {
                $lchart = new \core\chart_line();
                $lchart->set_smooth(true);
                $ls = new \core\chart_series(get_string('trend_title', 'local_leducon'), $values);
                $ls->set_color('#2563eb');
                $lchart->add_series($ls);
                $lchart->set_labels($labels);
                echo $OUTPUT->render($lchart);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
            echo html_writer::end_div();
        }
    }
}

if (empty($data)) {
    echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
} else {
    $table = new flexible_table('local-leducon-' . $reporttype);
    $table->define_baseurl($currenturl);
    $table->define_columns(array_keys($columns));
    $table->define_headers(array_map(function($c) { return $c['label']; }, array_values($columns)));
    foreach ($columns as $key => $col) {
        if (!empty($col['sortable'])) {
            $table->sortable(true, $key, SORT_ASC);
        }
    }
    $table->set_attribute('class', 'ld-table');
    $table->collapsible(true);
    $table->initialbars(false);
    $table->setup();
    foreach ($data as $row) {
        $tablerow = [];
        foreach ($columns as $key => $col) {
            $val = isset($row[$key]) ? (string)$row[$key] : '';
            $tablerow[] = !empty($col['html']) ? $val : s($val);
        }
        $table->add_data($tablerow);
    }
    $table->finish_output();
    echo html_writer::tag('p', count($data) . ' ' . get_string('reportresults', 'local_leducon'), ['class' => 'text-muted small']);

    // Action buttons (e.g. "Notify Teachers").
    $actions = $report->get_actions();
    if (!empty($actions)) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $currenturl->out(false),
            'style'  => 'margin-top:1rem',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        foreach ($filters as $k => $v) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $k, 'value' => (string)$v]);
        }
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reporttype', 'value' => $reporttype]);
        foreach ($actions as $act) {
            echo html_writer::tag('button', s($act['label']), [
                'type'  => 'submit',
                'name'  => 'reportaction',
                'value' => $act['key'],
                'class' => isset($act['class']) ? $act['class'] : 'ld-btn ld-btn-primary',
            ]);
            echo ' ';
        }
        echo html_writer::end_tag('form');
    }
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
