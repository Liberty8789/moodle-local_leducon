<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Activity Heatmap page for local_leducon.
 *
 * Renders a 7x24 grid (day-of-week x hour-of-day) of login frequency.
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

// ------------------------------------------------------------------
// Input.
// ------------------------------------------------------------------
$fp       = local_leducon_get_filter_params();
$cohortid = $fp['cohortid'];
$courseid = $fp['courseid'];
$from     = $fp['datefrom_resolved'] > 0 ? $fp['datefrom_resolved'] : local_leducon_days_ago(30);
$to       = $fp['dateto_resolved'];

// ------------------------------------------------------------------
// Page setup.
// ------------------------------------------------------------------
$pageurl = new moodle_url('/local/leducon/analytics/heatmap.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('heatmap_title', 'local_leducon'));
$PAGE->set_heading(get_string('heatmap_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('heatmap_title', 'local_leducon'));

// ------------------------------------------------------------------
// Build query.
// ------------------------------------------------------------------
$params = ['from' => $from, 'to' => $to];
$sql    = "SELECT timecreated FROM {logstore_standard_log}
            WHERE action = 'loggedin'
              AND timecreated BETWEEN :from AND :to";

if ($cohortid > 0) {
    $sql .= " AND userid IN (SELECT userid FROM {cohort_members} WHERE cohortid = :cohortid)";
    $params['cohortid'] = $cohortid;
}
if ($courseid > 0) {
    $sql .= " AND courseid = :courseid";
    $params['courseid'] = $courseid;
}

$grid    = [];
$maxval  = 0;
$hasdata = false;

try {
    $rs = $DB->get_recordset_sql($sql, $params, 0, 100000);
    foreach ($rs as $r) {
        $day  = (int)date('N', $r->timecreated) - 1; // 0=Mon ... 6=Sun
        $hour = (int)date('G', $r->timecreated);
        if (!isset($grid[$day][$hour])) {
            $grid[$day][$hour] = 0;
        }
        $grid[$day][$hour]++;
        if ($grid[$day][$hour] > $maxval) {
            $maxval = $grid[$day][$hour];
        }
    }
    $rs->close();
    $hasdata = !empty($grid);
} catch (\dml_exception $e) {
    echo $OUTPUT->header();
    local_leducon_render_nav('analytics', 'heatmap', $USER->id);
    echo $OUTPUT->notification(get_string('error_db', 'local_leducon'), 'error');
    echo $OUTPUT->footer();
    die();
}

// ------------------------------------------------------------------
// Render.
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'heatmap', $USER->id);

echo $OUTPUT->heading(get_string('heatmap_title', 'local_leducon'));
echo html_writer::tag('p', htmlspecialchars(get_string('heatmap_subtitle', 'local_leducon'), ENT_QUOTES),
    ['class' => 'text-muted mb-3']);

// Filter bar.
local_leducon_render_filter_bar(
    new moodle_url('/local/leducon/analytics/heatmap.php'),
    $fp,
    ['show_course' => true, 'show_cohort' => true, 'show_org' => true]
);

if (!$hasdata) {
    echo $OUTPUT->notification(get_string('heatmap_nodata', 'local_leducon'), 'info');
    echo $OUTPUT->footer();
    die();
}

$days  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$hourlabels = [];
for ($h = 0; $h < 24; $h++) {
    if ($h === 0)  { $hourlabels[$h] = '12am'; }
    elseif ($h === 6)  { $hourlabels[$h] = '6am'; }
    elseif ($h === 12) { $hourlabels[$h] = '12pm'; }
    elseif ($h === 18) { $hourlabels[$h] = '6pm'; }
    else               { $hourlabels[$h] = ''; }
}

// Heatmap table.
echo html_writer::start_div('ld-card');
echo '<div class="ld-heatmap-wrap">';
echo '<table class="ld-heatmap-table">';

// Header row: hours 0-23.
echo '<thead><tr>';
echo '<th class="ld-heatmap-day-label"></th>';
for ($h = 0; $h < 24; $h++) {
    echo '<th class="ld-heatmap-hour-label">'
        . htmlspecialchars($hourlabels[$h], ENT_QUOTES) . '</th>';
}
echo '</tr></thead>';

// 7 data rows.
echo '<tbody>';
for ($d = 0; $d < 7; $d++) {
    echo '<tr>';
    echo '<td class="ld-heatmap-day-label">' . htmlspecialchars($days[$d], ENT_QUOTES) . '</td>';
    for ($h = 0; $h < 24; $h++) {
        $count = $grid[$d][$h] ?? 0;
        if ($maxval > 0 && $count > 0) {
            $lightness = (int)round(95 - ($count / $maxval) * 55);
            $bg = 'hsl(220,90%,' . $lightness . '%)';
        } else {
            $bg = 'hsl(220,0%,97%)';
        }
        $title = $count . ' logins';
        echo '<td class="ld-heatmap-cell" title="' . htmlspecialchars($title, ENT_QUOTES) . '" '
            . 'style="background:' . $bg . '">'
            . '<span class="ld-heatmap-tooltip">' . $count . '</span>'
            . '</td>';
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo '</div>';

// Peak info.
if ($maxval > 0) {
    echo html_writer::tag('p',
        htmlspecialchars(get_string('heatmap_peak', 'local_leducon', $maxval), ENT_QUOTES),
        ['class' => 'text-muted ld-heatmap-peak']
    );
}

// Legend.
echo '<div class="ld-heatmap-legend">';
echo '<span class="text-muted">Less&nbsp;</span>';
echo '<div class="ld-heatmap-legend-scale">';
for ($i = 0; $i <= 9; $i++) {
    $l   = 95 - ($i / 9) * 55;
    $bg  = 'hsl(220,90%,' . round($l) . '%)';
    echo '<div class="ld-heatmap-legend-swatch" style="background:' . $bg . '"></div>';
}
echo '</div>';
echo '<span class="text-muted">&nbsp;More</span>';
echo '</div>';
echo html_writer::end_div();

echo $OUTPUT->footer();
