<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Custom Report Viewer — run and display a saved custom report.
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
require_capability('local/leducon:managecustomreports', $context);

$id     = required_param('id', PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);

$definition = $DB->get_record('local_leducon_custom_rpts', ['id' => $id], '*', MUST_EXIST);

// Check visibility: owner or shared.
if ((int)$definition->createdby !== (int)$USER->id && !$definition->shared) {
    throw new moodle_exception('nopermissions', 'error', '', 'view this report');
}

$report = new \local_leducon\reports\custom_report($definition);

// ── CSV EXPORT ─────────────────────────────────────────────────
if ($format === 'csv') {
    $csv = $report->export_csv();
    $filename = clean_filename($definition->name . '_' . date('Y-m-d') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    die();
}

$pageurl = new moodle_url('/local/leducon/analytics/custom_report_view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title($definition->name);
$PAGE->set_heading($definition->name);
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('customreport_title', 'local_leducon'),
    new moodle_url('/local/leducon/analytics/custom_reports.php'));
$PAGE->navbar->add($definition->name);

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'custom_reports', $USER->id);
echo $OUTPUT->heading(s($definition->name));

if ($definition->description) {
    echo html_writer::tag('p', s($definition->description), ['class' => 'text-muted mb-3']);
}

// Data source label.
$datasources = \local_leducon\reports\custom_report::get_datasources();
$dslabel = $datasources[$definition->datasource]['label'] ?? $definition->datasource;
echo html_writer::tag('p',
    html_writer::tag('strong', get_string('cr_col_source', 'local_leducon') . ': ') . s($dslabel),
    ['class' => 'small text-muted']
);

// Export + edit buttons.
$csvurl  = new moodle_url($pageurl, ['format' => 'csv']);
$editurl = new moodle_url('/local/leducon/analytics/custom_reports.php', ['action' => 'edit', 'id' => $id]);

echo html_writer::start_div('ld-action-bar');
echo html_writer::link($csvurl, get_string('export_csv', 'local_leducon'),
    ['class' => 'ld-action-btn ld-action-primary']);
echo html_writer::link($editurl, get_string('cr_action_edit', 'local_leducon'),
    ['class' => 'ld-action-btn ld-action-secondary']);
echo html_writer::link(
    new moodle_url('/local/leducon/analytics/custom_reports.php'),
    get_string('cr_back_to_list', 'local_leducon'),
    ['class' => 'ld-action-btn ld-action-secondary']
);
echo html_writer::end_div();

// Run the report.
$columns = $report->get_columns();
try {
    $data = $report->get_data();
} catch (\Throwable $e) {
    echo $OUTPUT->notification(get_string('error_db', 'local_leducon'), 'error');
    echo $OUTPUT->footer();
    die();
}

if (empty($data)) {
    echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
} else {
    // KPI strip: row count.
    echo html_writer::start_div('ld-kpi-strip');
    echo html_writer::start_div('');
    echo html_writer::start_div('ld-stat-card ld-primary');
    echo html_writer::tag('div', count($data), ['class' => 'ld-stat-value']);
    echo html_writer::tag('div', get_string('reportresults', 'local_leducon'), ['class' => 'ld-stat-label']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Data table.
    $table = new flexible_table('local-leducon-custom-' . $id);
    $table->define_baseurl($pageurl);
    $table->define_columns(array_keys($columns));
    $table->define_headers(array_map(function($c) { return $c['label']; }, $columns));
    $table->sortable(true);
    $table->set_attribute('class', 'ld-table');
    $table->collapsible(true);
    $table->initialbars(false);
    $table->setup();

    foreach ($data as $row) {
        $tablerow = [];
        foreach (array_keys($columns) as $key) {
            $tablerow[] = s((string)($row[$key] ?? ''));
        }
        $table->add_data($tablerow);
    }
    $table->finish_output();

    echo html_writer::tag('p', count($data) . ' ' . get_string('reportresults', 'local_leducon'),
        ['class' => 'text-muted small']);
}

echo $OUTPUT->footer();
