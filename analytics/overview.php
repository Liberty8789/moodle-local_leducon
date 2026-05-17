<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Executive Overview page.
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

$fp = local_leducon_get_filter_params();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/analytics/overview.php'));
$PAGE->set_title(get_string('overview_title', 'local_leducon'));
$PAGE->set_heading(get_string('overview_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('overview_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'overview', $USER->id);
echo $OUTPUT->heading(get_string('overview_title', 'local_leducon'));

local_leducon_render_filter_bar(
    new moodle_url('/local/leducon/analytics/overview.php'),
    $fp,
    ['show_cohort' => true, 'show_org' => true]
);

$pfrom = $fp['datefrom_resolved'];
$pto   = $fp['dateto_resolved'];
$dberror = false;

$totalenrolled = local_leducon_cached('overview_enrolled', function() {
    global $DB;
    try {
        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0",
            ['siteid' => SITEID]
        );
    } catch (\dml_exception $e) { return 0; }
});

$activethisweek = local_leducon_cached('overview_active_week', function() {
    global $DB;
    try {
        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
              WHERE action = 'loggedin' AND timecreated > :weekago",
            ['weekago' => time() - 7 * DAYSECS]
        );
    } catch (\dml_exception $e) { return 0; }
});

$overallcomppct = null;
try {
    $comprow = $DB->get_record_sql(
        "SELECT COUNT(DISTINCT ue.userid) AS total,
                COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN cc.userid ELSE NULL END) AS completed
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
           JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
      LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid",
        ['siteid' => SITEID]
    );
    if ($comprow && $comprow->total > 0) {
        $overallcomppct = round((float)$comprow->completed / (float)$comprow->total * 100, 1);
    }
} catch (\dml_exception $e) { $dberror = true; }

$avggradepct = null;
try {
    $graderow = $DB->get_record_sql(
        "SELECT AVG(gg.finalgrade / NULLIF(gi.grademax, 0) * 100) AS avggradepct
           FROM {grade_grades} gg
           JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
          WHERE gg.finalgrade IS NOT NULL AND gi.grademax > 0"
    );
    if ($graderow && $graderow->avggradepct !== null) {
        $avggradepct = round((float)$graderow->avggradepct, 1);
    }
} catch (\dml_exception $e) { $dberror = true; }

$atriskcount = 0;
try {
    $atrisk_days = (int)get_config('local_leducon', 'atrisk_inactive_days') ?: 14;
    $atriskcount = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
           JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
          WHERE u.lastaccess < :cutoff AND u.lastaccess > 0",
        ['siteid' => SITEID, 'cutoff' => time() - $atrisk_days * DAYSECS]
    );
} catch (\dml_exception $e) { $dberror = true; }

$newenrolments = 0;
try {
    $monthstart = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
    $newenrolments = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
          WHERE ue.timecreated >= :monthstart",
        ['siteid' => SITEID, 'monthstart' => $monthstart]
    );
} catch (\dml_exception $e) { $dberror = true; }

if ($dberror) {
    echo $OUTPUT->notification(get_string('error_db', 'local_leducon'), 'error');
}

$coursecompletionstats = [];
try {
    $coursecompletionstats = $DB->get_records_sql(
        "SELECT c.id, c.fullname,
                COUNT(DISTINCT ue.userid) AS enrolled,
                COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN cc.userid ELSE NULL END) AS completed
           FROM {course} c
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
           JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
      LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
          WHERE c.id <> :siteid
       GROUP BY c.id, c.fullname
         HAVING COUNT(DISTINCT ue.userid) > 0
       ORDER BY c.fullname ASC",
        ['siteid' => SITEID]
    );
} catch (\dml_exception $e) {
    debugging('Overview course comparison SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
}

$coursestats = [];
foreach ($coursecompletionstats as $cs) {
    $pct = ($cs->enrolled > 0) ? round((float)$cs->completed / (float)$cs->enrolled * 100, 1) : 0.0;
    $coursestats[] = ['id' => $cs->id, 'fullname' => $cs->fullname, 'enrolled' => (int)$cs->enrolled, 'completed' => (int)$cs->completed, 'pct' => $pct];
}
usort($coursestats, function($a, $b) { return $b['pct'] <=> $a['pct']; });
$top5    = array_slice($coursestats, 0, 5);
$bottom5 = array_slice(array_reverse($coursestats), 0, 5);

// Trend data: per-month COUNT queries — cross-DB safe (no FROM_UNIXTIME).
$trendlabels = [];
$trendvalues = [];
for ($i = 11; $i >= 0; $i--) {
    $mstart = strtotime(date('Y-m-01', strtotime("-{$i} months")));
    $mend   = strtotime(date('Y-m-01', strtotime("-" . ($i - 1) . " months")));
    $trendlabels[] = date('M Y', $mstart);
    try {
        $trendvalues[] = (int)$DB->count_records_sql(
            "SELECT COUNT(*) FROM {logstore_standard_log}
              WHERE action = 'loggedin' AND timecreated >= :mstart AND timecreated < :mend",
            ['mstart' => $mstart, 'mend' => $mend]
        );
    } catch (\Exception $e) {
        $trendvalues[] = 0;
    }
}

// KPI cards.
$compcolor  = $overallcomppct !== null ? local_leducon_kpi_color($overallcomppct, 'completion') : 'secondary';
$gradecolor = $avggradepct   !== null ? local_leducon_kpi_color($avggradepct, 'pass')          : 'secondary';

echo html_writer::start_div('ld-kpi-strip');
$kpis = [
    ['value' => $totalenrolled, 'label' => get_string('kpi_enrolled', 'local_leducon'), 'color' => 'primary'],
    ['value' => $activethisweek, 'label' => get_string('kpi_active_this_week', 'local_leducon'), 'color' => 'info'],
    ['value' => ($overallcomppct !== null ? number_format($overallcomppct, 1) . '%' : '-'), 'label' => get_string('kpi_completion', 'local_leducon'), 'color' => $compcolor],
    ['value' => ($avggradepct !== null ? number_format($avggradepct, 1) . '%' : '-'), 'label' => get_string('kpi_avg_grade', 'local_leducon'), 'color' => $gradecolor],
    ['value' => $atriskcount, 'label' => get_string('kpi_atrisk', 'local_leducon'), 'color' => ($atriskcount > 0 ? 'danger' : 'success')],
    ['value' => $newenrolments, 'label' => get_string('kpi_new_enrolments', 'local_leducon'), 'color' => 'secondary'],
];
foreach ($kpis as $kpi) {
    echo html_writer::start_div('ld-stat-card ld-' . $kpi['color']);
    echo html_writer::tag('div', s((string)$kpi['value']), ['class' => 'ld-stat-value']);
    echo html_writer::tag('div', s($kpi['label']),         ['class' => 'ld-stat-label']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

// Top/Bottom courses.
echo html_writer::start_div('ld-overview-split');

$rendercoursetable = function(array $rows, string $title) {
    echo html_writer::start_div('ld-overview-half');
    echo html_writer::tag('h5', s($title), ['style' => 'margin-bottom:.75rem']);
    if (empty($rows)) {
        echo html_writer::tag('p', '-', ['class' => 'text-muted']);
        echo html_writer::end_div();
        return;
    }
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('overview_col_course',    'local_leducon'),
        get_string('overview_col_enrolled',  'local_leducon'),
        get_string('overview_col_completed', 'local_leducon'),
        get_string('overview_col_pct',       'local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($rows as $row) {
        $pctint = min(100, max(0, (int)round($row['pct'])));
        $bar = html_writer::tag('div', '', ['class' => 'ld-progress-bar ld-bg-info', 'style' => "width:{$pctint}%"]);
        $barcell = html_writer::tag('div', $bar, ['class' => 'ld-progress']);
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', html_writer::link(new moodle_url('/course/view.php', ['id' => $row['id']]), s($row['fullname'])));
        echo html_writer::tag('td', (int)$row['enrolled']);
        echo html_writer::tag('td', (int)$row['completed']);
        echo html_writer::tag('td', $barcell);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
    echo html_writer::end_div();
};

$rendercoursetable($top5,    get_string('overview_top5',    'local_leducon'));
$rendercoursetable($bottom5, get_string('overview_bottom5', 'local_leducon'));
echo html_writer::end_div();

// Charts row.
echo html_writer::start_div('ld-chart-grid');

// Trend chart.
echo html_writer::start_div('ld-card ld-chart-card');
echo html_writer::start_div('ld-card-header');
echo html_writer::tag('h3', get_string('trend_title', 'local_leducon'), ['class' => 'ld-card-title']);
echo html_writer::end_div();
echo html_writer::start_div('ld-card-body ld-chart-body');
if (array_sum($trendvalues) > 0) {
    try {
        $lchart = new \core\chart_line();
        $lchart->set_smooth(true);
        $ls = new \core\chart_series(get_string('trend_title', 'local_leducon'), $trendvalues);
        $ls->set_color('#2563eb');
        $lchart->add_series($ls);
        $lchart->set_labels($trendlabels);
        echo $OUTPUT->render($lchart);
    } catch (\Throwable $e) {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
} else {
    echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
}
echo html_writer::end_div();
echo html_writer::end_div();

// Completion pie chart — 3 separate COUNT queries (cross-DB safe).
$ov_comp = ['completed' => 0, 'inprogress' => 0, 'notstarted' => 0];
try {
    $ov_comp['completed'] = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT cc.id)
           FROM {course_completions} cc
           JOIN {user} u ON u.id = cc.userid AND u.deleted = 0 AND u.suspended = 0
          WHERE cc.timecompleted IS NOT NULL"
    );
} catch (\Exception $e) {}
try {
    $ov_comp['inprogress'] = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT cc.id)
           FROM {course_completions} cc
           JOIN {user} u ON u.id = cc.userid AND u.deleted = 0 AND u.suspended = 0
          WHERE cc.timecompleted IS NULL AND cc.timestarted IS NOT NULL"
    );
} catch (\Exception $e) {}
try {
    $ov_total = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.id)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
           JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0",
        ['siteid' => SITEID]
    );
    $ov_comp['notstarted'] = max(0, $ov_total - $ov_comp['completed'] - $ov_comp['inprogress']);
} catch (\Exception $e) {}

$ov_comp_total = $ov_comp['completed'] + $ov_comp['inprogress'] + $ov_comp['notstarted'];
echo html_writer::start_div('ld-card ld-chart-card');
echo html_writer::start_div('ld-card-header');
echo html_writer::tag('h3', get_string('chart_completion_status', 'local_leducon'), ['class' => 'ld-card-title']);
echo html_writer::end_div();
echo html_writer::start_div('ld-card-body ld-chart-body');
if ($ov_comp_total > 0) {
    try {
        $ov_pie = new \core\chart_pie();
        $ov_ps = new \core\chart_series(
            get_string('chart_completion_status', 'local_leducon'),
            [$ov_comp['completed'], $ov_comp['inprogress'], $ov_comp['notstarted']]
        );
        $ov_ps->set_colors(['#16a34a', '#d97706', '#e5e7eb']);
        $ov_pie->add_series($ov_ps);
        $ov_pie->set_labels([
            get_string('chart_completed', 'local_leducon'),
            get_string('chart_inprogress', 'local_leducon'),
            get_string('chart_notstarted', 'local_leducon'),
        ]);
        $ov_pie->set_doughnut(true);
        echo $OUTPUT->render($ov_pie);
    } catch (\Throwable $e) {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
} else {
    echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // .ld-chart-grid

echo $OUTPUT->footer();
