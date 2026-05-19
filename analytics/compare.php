<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Period-over-period comparison and cross-course comparison for local_leducon.
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
// Input -- use PARAM_ALPHANUMEXT so "2024-01-15" is not stripped to 0.
// ------------------------------------------------------------------
$from_a_str = optional_param('from_a', '', PARAM_ALPHANUMEXT);
$to_a_str   = optional_param('to_a',   '', PARAM_ALPHANUMEXT);
$from_b_str = optional_param('from_b', '', PARAM_ALPHANUMEXT);
$to_b_str   = optional_param('to_b',   '', PARAM_ALPHANUMEXT);

// Parse HTML date strings (YYYY-MM-DD) -> Unix timestamps.
$from_a = $from_a_str ? (int)strtotime($from_a_str . ' 00:00:00') : 0;
$to_a   = $to_a_str   ? (int)strtotime($to_a_str   . ' 23:59:59') : 0;
$from_b = $from_b_str ? (int)strtotime($from_b_str . ' 00:00:00') : 0;
$to_b   = $to_b_str   ? (int)strtotime($to_b_str   . ' 23:59:59') : 0;

// Defaults when nothing was submitted.
$now = time();
if (!$from_a) { $from_a = local_leducon_days_ago(90); }
if (!$to_a)   { $to_a   = local_leducon_days_ago(60); }
if (!$from_b) { $from_b = local_leducon_days_ago(30); }
if (!$to_b)   { $to_b   = $now; }

// Keep string versions in sync with resolved timestamps for form values.
$from_a_str = date('Y-m-d', $from_a);
$to_a_str   = date('Y-m-d', $to_a);
$from_b_str = date('Y-m-d', $from_b);
$to_b_str   = date('Y-m-d', $to_b);

// Active tab.
$tab = optional_param('tab', 'periods', PARAM_ALPHA);

// ------------------------------------------------------------------
// Page setup.
// ------------------------------------------------------------------
$pageurl = new moodle_url('/local/leducon/analytics/compare.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('compare_title', 'local_leducon'));
$PAGE->set_heading(get_string('compare_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('compare_title', 'local_leducon'));

// ------------------------------------------------------------------
// Helper: run a count query for a time range.
// ------------------------------------------------------------------
function local_leducon_count_between(string $sql, int $from, int $to): int {
    global $DB;
    try {
        return (int)$DB->count_records_sql($sql, ['from' => $from, 'to' => $to, 'gfrom' => $from, 'gto' => $to]);
    } catch (\dml_exception $e) {
        return 0;
    }
}

// ------------------------------------------------------------------
// Period-vs-period data.
// ------------------------------------------------------------------
$metrics = [
    'logins' => [
        'label' => get_string('compare_metric_logins', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {logstore_standard_log}
                     WHERE action = 'loggedin' AND timecreated BETWEEN :from AND :to",
    ],
    'enrolments' => [
        'label' => get_string('compare_metric_enrolments', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {user_enrolments}
                     WHERE timecreated BETWEEN :from AND :to",
    ],
    'completions' => [
        'label' => get_string('compare_metric_completions', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {course_completions} cc
               LEFT JOIN {grade_items} gi_cm ON gi_cm.courseid = cc.course AND gi_cm.itemtype = 'course'
               LEFT JOIN {grade_grades} gg_cm ON gg_cm.itemid = gi_cm.id AND gg_cm.userid = cc.userid
                     WHERE (cc.timecompleted BETWEEN :from AND :to)
                        OR (cc.timecompleted IS NULL AND gg_cm.finalgrade IS NOT NULL AND gi_cm.grademax > 0
                            AND gg_cm.finalgrade / gi_cm.grademax * 100 >= 100
                            AND gg_cm.timemodified BETWEEN :gfrom AND :gto)",
    ],
    'quiz_attempts' => [
        'label' => get_string('compare_metric_quiz_attempts', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {quiz_attempts}
                     WHERE state = 'finished' AND timefinish BETWEEN :from AND :to",
    ],
    'forum_posts' => [
        'label' => get_string('compare_metric_forum_posts', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {forum_posts}
                     WHERE created BETWEEN :from AND :to",
    ],
    'assign_submissions' => [
        'label' => get_string('compare_metric_assign_submissions', 'local_leducon'),
        'sql'   => "SELECT COUNT(*) FROM {assign_submission}
                     WHERE status = 'submitted' AND timemodified BETWEEN :from AND :to",
    ],
];

$comparison = [];
foreach ($metrics as $key => $m) {
    $a = local_leducon_count_between($m['sql'], $from_a, $to_a);
    $b = local_leducon_count_between($m['sql'], $from_b, $to_b);

    $delta = $b - $a;
    $pct   = ($a > 0) ? round($delta / $a * 100, 1) : null;

    $comparison[$key] = [
        'label' => $m['label'],
        'a'     => $a,
        'b'     => $b,
        'delta' => $delta,
        'pct'   => $pct,
    ];
}

// ------------------------------------------------------------------
// Cross-course data.
// ------------------------------------------------------------------
$courserows  = [];
$chartlabels = [];
$chartdata   = [];

try {
    $coursecomparison = $DB->get_records_sql(
        "SELECT c.id, c.fullname,
                COUNT(DISTINCT ue.userid) AS enrolled,
                COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL
                    OR (gg_cp.finalgrade IS NOT NULL AND gi_cp.grademax > 0 AND gg_cp.finalgrade / gi_cp.grademax * 100 >= 100)
                    THEN cc.userid ELSE NULL END) AS completed
           FROM {course} c
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
      LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
      LEFT JOIN {grade_items} gi_cp ON gi_cp.courseid = c.id AND gi_cp.itemtype = 'course'
      LEFT JOIN {grade_grades} gg_cp ON gg_cp.itemid = gi_cp.id AND gg_cp.userid = ue.userid
          WHERE c.id <> :siteid
          GROUP BY c.id, c.fullname
          ORDER BY c.fullname ASC",
        ['siteid' => SITEID],
        0,
        30
    );

    foreach ($coursecomparison as $cr) {
        $enrolled  = (int)$cr->enrolled;
        $completed = (int)$cr->completed;
        $rate      = ($enrolled > 0) ? round($completed / $enrolled * 100, 1) : 0.0;

        $courserows[]  = [
            'id'        => (int)$cr->id,
            'fullname'  => format_string($cr->fullname),
            'enrolled'  => $enrolled,
            'completed' => $completed,
            'rate'      => $rate,
        ];
        $chartlabels[] = format_string($cr->fullname);
        $chartdata[]   = $rate;
    }
} catch (\dml_exception $e) {
    debugging('Compare report SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

// ------------------------------------------------------------------
// Tab links -- carry date range params so switching tabs preserves filter.
// ------------------------------------------------------------------
$dateParams = [
    'from_a' => $from_a_str,
    'to_a'   => $to_a_str,
    'from_b' => $from_b_str,
    'to_b'   => $to_b_str,
];

// ------------------------------------------------------------------
// Render.
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'compare', $USER->id);
echo $OUTPUT->heading(get_string('compare_title', 'local_leducon'));

// Bootstrap nav-tabs (preserve date params on tab switch).
$tabperiods = ($tab === 'periods');
$tabcourses = ($tab === 'courses');
$formurl    = new moodle_url('/local/leducon/analytics/compare.php');

echo '<ul class="nav nav-tabs mb-4" id="compareTabs" role="tablist">';
echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tabperiods ? 'active' : '') . '" href="'
    . (new moodle_url('/local/leducon/analytics/compare.php', ['tab' => 'periods'] + $dateParams))->out(false) . '">'
    . htmlspecialchars(get_string('compare_tab_periods', 'local_leducon'), ENT_QUOTES) . '</a>';
echo '</li>';
echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tabcourses ? 'active' : '') . '" href="'
    . (new moodle_url('/local/leducon/analytics/compare.php', ['tab' => 'courses'] + $dateParams))->out(false) . '">'
    . htmlspecialchars(get_string('compare_tab_courses', 'local_leducon'), ENT_QUOTES) . '</a>';
echo '</li>';
echo '</ul>';

// ------------------------------------------------------------------
// Tab 1: Period vs Period.
// ------------------------------------------------------------------
if ($tabperiods) {
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl->out(false), 'class' => 'mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'periods']);
    echo html_writer::start_div('row');

    // Period A card.
    echo html_writer::start_div('ld-compare-period');
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo htmlspecialchars(get_string('compare_period_a', 'local_leducon'), ENT_QUOTES);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('filter_from', 'local_leducon'), ['class' => 'col-form-label-sm']);
    echo html_writer::empty_tag('input', [
        'type'  => 'date',
        'name'  => 'from_a',
        'value' => $from_a_str,
        'class' => 'ld-input',
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('filter_to', 'local_leducon'), ['class' => 'col-form-label-sm']);
    echo html_writer::empty_tag('input', [
        'type'  => 'date',
        'name'  => 'to_a',
        'value' => $to_a_str,
        'class' => 'ld-input',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Period B card.
    echo html_writer::start_div('ld-compare-period');
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-header bg-success text-white');
    echo htmlspecialchars(get_string('compare_period_b', 'local_leducon'), ENT_QUOTES);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('filter_from', 'local_leducon'), ['class' => 'col-form-label-sm']);
    echo html_writer::empty_tag('input', [
        'type'  => 'date',
        'name'  => 'from_b',
        'value' => $from_b_str,
        'class' => 'ld-input',
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('filter_to', 'local_leducon'), ['class' => 'col-form-label-sm']);
    echo html_writer::empty_tag('input', [
        'type'  => 'date',
        'name'  => 'to_b',
        'value' => $to_b_str,
        'class' => 'ld-input',
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Submit.
    echo html_writer::start_div('ld-compare-actions');
    echo html_writer::tag('button',
        htmlspecialchars(get_string('filter_apply', 'local_leducon'), ENT_QUOTES),
        ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('form');

    // Period labels for table headers.
    $labelA = date('d M Y', $from_a) . ' - ' . date('d M Y', $to_a);
    $labelB = date('d M Y', $from_b) . ' - ' . date('d M Y', $to_b);

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead', ['class' => 'thead-light']);
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', htmlspecialchars(get_string('compare_metric',   'local_leducon'), ENT_QUOTES));
    echo html_writer::tag('th', htmlspecialchars(get_string('compare_period_a', 'local_leducon') . ' (' . $labelA . ')', ENT_QUOTES));
    echo html_writer::tag('th', htmlspecialchars(get_string('compare_period_b', 'local_leducon') . ' (' . $labelB . ')', ENT_QUOTES));
    echo html_writer::tag('th', htmlspecialchars(get_string('compare_delta',    'local_leducon'), ENT_QUOTES));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($comparison as $row) {
        $delta = $row['delta'];
        $pct   = $row['pct'];

        if ($delta > 0) {
            $arrow = '&#9650;';
            $txt   = '+' . $delta . ($pct !== null ? ' (' . $arrow . ' ' . $pct . '%)' : ' (' . $arrow . ')');
            $changehtml = html_writer::tag('span', $txt, ['class' => 'text-success font-weight-bold']);
        } elseif ($delta < 0) {
            $arrow = '&#9660;';
            $txt   = $delta . ($pct !== null ? ' (' . $arrow . ' ' . abs($pct) . '%)' : ' (' . $arrow . ')');
            $changehtml = html_writer::tag('span', $txt, ['class' => 'text-danger font-weight-bold']);
        } else {
            $changehtml = html_writer::tag('span',
                get_string('compare_no_change', 'local_leducon'),
                ['class' => 'text-muted']
            );
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', htmlspecialchars($row['label'], ENT_QUOTES));
        echo html_writer::tag('td', number_format($row['a']));
        echo html_writer::tag('td', number_format($row['b']));
        echo html_writer::tag('td', $changehtml);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// ------------------------------------------------------------------
// Tab 2: Cross-Course Comparison.
// ------------------------------------------------------------------
if ($tabcourses) {
    echo $OUTPUT->heading(get_string('compare_courses_title', 'local_leducon'), 3);

    if (empty($courserows)) {
        echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
    } else {
        // Bar chart -- one bar per course, colour-coded by completion rate.
        if (!empty($chartdata) && array_sum($chartdata) > 0) {
            try {
                $chart  = new \core\chart_bar();
                $series = new \core\chart_series(
                    get_string('cc_completionrate', 'local_leducon'),
                    $chartdata
                );

                $colormap = [];
                foreach ($chartdata as $val) {
                    $color      = local_leducon_kpi_color((float)$val, 'completion');
                    $colormap[] = $color === 'success' ? '#28a745' :
                                 ($color === 'warning'  ? '#ffc107' : '#dc3545');
                }
                $series->set_colors($colormap);
                $chart->add_series($series);
                $chart->set_labels($chartlabels);
                $chart->get_yaxis(0, true)->set_min(0);
                $chart->get_yaxis(0, true)->set_max(100);

                echo $OUTPUT->render($chart);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
        }

        // Detail table -- iterate $courserows (numerically indexed, matches $chartdata).
        echo html_writer::start_div('ld-card');
        echo html_writer::start_tag('table', ['class' => 'ld-table']);
        echo html_writer::start_tag('thead', ['class' => 'thead-light']);
        echo html_writer::tag('tr',
            html_writer::tag('th', get_string('cc_coursename',    'local_leducon')) .
            html_writer::tag('th', get_string('cc_enrolled',      'local_leducon')) .
            html_writer::tag('th', get_string('cc_completed',     'local_leducon')) .
            html_writer::tag('th', get_string('cc_completionrate','local_leducon'))
        );
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($courserows as $row) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', htmlspecialchars($row['fullname'], ENT_QUOTES));
            echo html_writer::tag('td', $row['enrolled']);
            echo html_writer::tag('td', $row['completed']);
            echo html_writer::tag('td',
                html_writer::tag('span', $row['rate'] . '%',
                    ['class' => 'badge badge-' . local_leducon_kpi_color((float)$row['rate'], 'completion')]
                )
            );
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
