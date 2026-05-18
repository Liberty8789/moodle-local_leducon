<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Leducon Learning Platform — unified dashboard.
 *
 * Combines analytics KPIs, gamification XP hero, and quick-launch tiles.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_leducon\gamify\level_manager;
use local_leducon\gamify\campaign_manager;

require_login();
$context = context_system::instance();

// At least one of analytics or gamify capability is needed.
$cananalytics = has_capability('local/leducon:viewanalytics', $context);
$cangamify    = has_capability('local/leducon:viewgamify', $context);

if (!$cananalytics && !$cangamify) {
    require_capability('local/leducon:viewanalytics', $context);
}

// AJAX progressive loading endpoint.
$ajax = optional_param('ajax', '', PARAM_ALPHA);
if ($ajax !== '') {
    require_sesskey();
    header('Content-Type: application/json');
    echo json_encode(local_leducon_ajax_section($ajax, $USER->id));
    die();
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/index.php'));
$PAGE->set_title(get_string('dashboard_title', 'local_leducon'));
$PAGE->set_heading(get_string('dashboard_title', 'local_leducon'));
$PAGE->set_pagelayout('report');

$userid = $USER->id;
$now    = time();

// ------------------------------------------------------------------
// Period params — global (KPIs).
// ------------------------------------------------------------------
$allowed = [7, 30, 90, 365, 0];
$period  = optional_param('period', 30, PARAM_INT);
if (!in_array($period, $allowed, true)) { $period = 30; }
$pfrom   = $period > 0 ? $now - $period * DAYSECS : 0;

// ------------------------------------------------------------------
// KPI data (analytics).
// ------------------------------------------------------------------
$kpi = ['active_users' => 0, 'completions' => 0, 'enrolments' => 0,
        'completion_rate' => 0.0, 'avg_grade' => 0.0, 'atrisk' => 0];

if ($cananalytics) {
    try {
        $ausql = "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log} WHERE userid > 1";
        $aup   = [];
        if ($period > 0) { $ausql .= ' AND timecreated >= :pfrom'; $aup['pfrom'] = $pfrom; }
        $kpi['active_users'] = (int)$DB->get_field_sql($ausql, $aup);
    } catch (\dml_exception $e) {}

    try {
        $cw = $period > 0 ? 'timecompleted >= :pfrom' : 'timecompleted IS NOT NULL';
        $cp = $period > 0 ? ['pfrom' => $pfrom] : [];
        $kpi['completions'] = (int)$DB->count_records_select('course_completions', $cw, $cp);
    } catch (\dml_exception $e) {}

    try {
        $ew = $period > 0 ? 'timecreated >= :pfrom' : '1=1';
        $ep = $period > 0 ? ['pfrom' => $pfrom] : [];
        $kpi['enrolments'] = (int)$DB->count_records_select('user_enrolments', $ew, $ep);
    } catch (\dml_exception $e) {}

    try {
        $total = (int)$DB->count_records('user_enrolments');
        $done  = (int)$DB->count_records_select('course_completions', 'timecompleted IS NOT NULL', []);
        $kpi['completion_rate'] = $total > 0 ? round($done / $total * 100, 1) : 0.0;
    } catch (\dml_exception $e) {}

    try {
        $kpi['avg_grade'] = round((float)$DB->get_field_sql(
            "SELECT AVG(gg.finalgrade / gi.grademax * 100)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course' AND gi.grademax > 0
              WHERE gg.finalgrade IS NOT NULL"
        ), 1);
    } catch (\dml_exception $e) {}

    try {
        $atrisk_days = (int)(get_config('local_leducon', 'atrisk_inactive_days') ?: 14);
        $cutoff = $now - $atrisk_days * DAYSECS;
        $kpi['atrisk'] = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0 AND u.id > 1
              WHERE (u.lastaccess < :cutoff OR u.lastaccess = 0)",
            ['cutoff' => $cutoff]
        );
    } catch (\dml_exception $e) {}
}

// ------------------------------------------------------------------
// Gamification data.
// ------------------------------------------------------------------
$gamify_enabled = (bool)get_config('local_leducon', 'gamify_enabled') && $cangamify;
$progress = null;
$streak   = 0;
$total_achievements = 0;
$total_completions  = 0;
$campaigns = [];

if ($gamify_enabled) {
    $progress = level_manager::get_progress($userid);
    $streak   = local_leducon_get_streak($userid);
    $total_completions  = $DB->count_records_select(
        'course_completions',
        'userid = :uid AND timecompleted IS NOT NULL',
        ['uid' => $userid]
    );
    $total_achievements = $DB->count_records('local_leducon_user_achiev', ['userid' => $userid]);
    $campaigns = campaign_manager::get_active_campaigns($userid);
}

// ------------------------------------------------------------------
// KPI card definitions.
// ------------------------------------------------------------------
$tc = [
    'primary' => get_config('local_leducon', 'color_primary') ?: '#2563eb',
    'success' => get_config('local_leducon', 'color_success') ?: '#16a34a',
    'info'    => get_config('local_leducon', 'color_info')    ?: '#0891b2',
    'purple'  => get_config('local_leducon', 'color_purple')  ?: '#7c3aed',
    'warning' => get_config('local_leducon', 'color_warning') ?: '#d97706',
    'danger'  => get_config('local_leducon', 'color_danger')  ?: '#dc2626',
];
$kpi_cards = [
    ['label' => get_string('kpi_active_users',    'local_leducon'), 'value' => number_format($kpi['active_users']),   'accent' => $tc['primary'], 'icon' => '&#128100;'],
    ['label' => get_string('kpi_completions',     'local_leducon'), 'value' => number_format($kpi['completions']),    'accent' => $tc['success'], 'icon' => '&#10003;'],
    ['label' => get_string('kpi_enrolments',      'local_leducon'), 'value' => number_format($kpi['enrolments']),     'accent' => $tc['info'],    'icon' => '&#43;'],
    ['label' => get_string('kpi_completion_rate', 'local_leducon'), 'value' => $kpi['completion_rate'] . '%',         'accent' => $tc['purple'],  'icon' => '&#128202;'],
    ['label' => get_string('kpi_avg_grade',       'local_leducon'), 'value' => $kpi['avg_grade'] . '%',               'accent' => $tc['warning'], 'icon' => '&#9733;'],
    ['label' => get_string('kpi_atrisk',          'local_leducon'), 'value' => number_format($kpi['atrisk']),         'accent' => $tc['danger'],  'icon' => '&#9888;'],
];

// Period pill labels.
$period_pills = [
    7   => get_string('last_7days',     'local_leducon'),
    30  => get_string('last_30days',    'local_leducon'),
    90  => get_string('last_90days',    'local_leducon'),
    365 => get_string('last_365days',   'local_leducon'),
    0   => get_string('period_alltime', 'local_leducon'),
];

// Quick-launch tile metadata.
$ql_meta = [
    'course_completion' => ['icon' => '&#9989;',  'accent' => $tc['success']],
    'user_activity'     => ['icon' => '&#128200;', 'accent' => $tc['primary']],
    'grade_analytics'   => ['icon' => '&#9733;',  'accent' => $tc['warning']],
    'enrollment'        => ['icon' => '&#43;',    'accent' => $tc['info']],
    'quiz_analytics'    => ['icon' => '&#10067;', 'accent' => $tc['purple']],
    'login_activity'    => ['icon' => '&#128274;', 'accent' => '#6b7280'],
    'scorm_analytics'   => ['icon' => '&#127760;', 'accent' => $tc['success']],
    'assignment'        => ['icon' => '&#128196;', 'accent' => $tc['primary']],
    'forum'             => ['icon' => '&#128172;', 'accent' => $tc['info']],
    'atrisk'            => ['icon' => '&#9888;',  'accent' => $tc['danger']],
    'timespent'         => ['icon' => '&#9201;',  'accent' => $tc['purple']],
    'badge'             => ['icon' => '&#127942;', 'accent' => $tc['warning']],
    'certificate'       => ['icon' => '&#127881;', 'accent' => $tc['success']],
    'compliance'        => ['icon' => '&#10004;', 'accent' => $tc['primary']],
    'category'          => ['icon' => '&#128193;', 'accent' => $tc['purple']],
    'instructor'        => ['icon' => '&#128104;&#8205;&#127979;', 'accent' => $tc['info']],
    'ilt'               => ['icon' => '&#127979;', 'accent' => $tc['success']],
];

// ------------------------------------------------------------------
// Chart data: Activity Trend (last 12 weeks, weekly buckets).
// Uses 12 individual COUNT queries — one per week boundary — for
// guaranteed cross-DB compatibility (no FLOOR/FROM_UNIXTIME needed).
// ------------------------------------------------------------------
$chart_trend_labels = [];
$chart_trend_values = [];
if ($cananalytics) {
    $trendweeks = (int)(get_config('local_leducon', 'dashboard_trend_weeks') ?: 12);
    for ($i = $trendweeks - 1; $i >= 0; $i--) {
        $wstart = $now - ($i + 1) * 7 * DAYSECS;
        $wend   = $now - $i * 7 * DAYSECS;
        $chart_trend_labels[] = date('d M', (int)$wend);
        try {
            $chart_trend_values[] = (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
                  WHERE action = 'loggedin' AND timecreated BETWEEN :wstart AND :wend",
                ['wstart' => $wstart, 'wend' => $wend]
            );
        } catch (\Exception $e) {
            $chart_trend_values[] = 0;
        }
    }
}

// Chart data: Completion breakdown (pie chart).
// Three separate COUNT queries — safe on every DB engine.
$chart_comp = ['completed' => 0, 'inprogress' => 0, 'notstarted' => 0];
if ($cananalytics) {
    try {
        $chart_comp['completed'] = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id)
               FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE cc.timecompleted IS NOT NULL"
        );
    } catch (\Exception $e) {}
    try {
        $chart_comp['inprogress'] = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.id)
               FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE cc.timecompleted IS NULL AND cc.timestarted IS NOT NULL"
        );
    } catch (\Exception $e) {}
    try {
        $total_enrolments = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid <> :siteid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0",
            ['siteid' => SITEID]
        );
        $chart_comp['notstarted'] = max(0, $total_enrolments - $chart_comp['completed'] - $chart_comp['inprogress']);
    } catch (\Exception $e) {}
}

// Chart data: Grade distribution (bar chart — 5 buckets).
// DB-side aggregation — never loads individual grades into PHP.
$bracketstr = get_config('local_leducon', 'grade_bracket_1') ?: '0,40,50,70,90,100';
$brackets = array_map('intval', explode(',', $bracketstr));
if (count($brackets) < 3) {
    $brackets = [0, 40, 50, 70, 90, 100];
}
$chart_grade_labels = [];
$chart_grade_values = [];
$grade_cases = [];
for ($bi = 0; $bi < count($brackets) - 1; $bi++) {
    $lo = $brackets[$bi];
    $hi = $brackets[$bi + 1];
    $chart_grade_labels[] = $lo . '–' . ($hi - 1) . '%';
    $chart_grade_values[] = 0;
    if ($bi === count($brackets) - 2) {
        $grade_cases[] = "SUM(CASE WHEN gg.finalgrade / gi.grademax * 100 >= {$lo} THEN 1 ELSE 0 END) AS g{$bi}";
        $chart_grade_labels[$bi] = $lo . '–100%';
    } else {
        $grade_cases[] = "SUM(CASE WHEN gg.finalgrade / gi.grademax * 100 >= {$lo} AND gg.finalgrade / gi.grademax * 100 < {$hi} THEN 1 ELSE 0 END) AS g{$bi}";
    }
}
if ($cananalytics) {
    $grade_sql = "SELECT " . implode(",\n        ", $grade_cases) . "
      FROM {grade_grades} gg
      JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course' AND gi.grademax > 0
     WHERE gg.finalgrade IS NOT NULL";
    try {
        $grow = $DB->get_record_sql($grade_sql);
        if ($grow) {
            for ($bi = 0; $bi < count($brackets) - 1; $bi++) {
                $field = 'g' . $bi;
                $chart_grade_values[$bi] = (int)($grow->$field ?? 0);
            }
        }
    } catch (\Exception $e) {}
}

// Chart data: Enrolment trend (last 6 months, monthly buckets).
// Uses per-month COUNT queries — cross-DB safe (no FROM_UNIXTIME/CONCAT).
$chart_enrol_labels = [];
$chart_enrol_values = [];
if ($cananalytics) {
    $enrolmonths = (int)(get_config('local_leducon', 'dashboard_enrol_months') ?: 6);
    for ($i = $enrolmonths - 1; $i >= 0; $i--) {
        $mstart = strtotime(date('Y-m-01', strtotime("-{$i} months")));
        $mend   = strtotime(date('Y-m-01', strtotime("-" . ($i - 1) . " months")));
        $chart_enrol_labels[] = date('M Y', $mstart);
        try {
            $chart_enrol_values[] = (int)$DB->count_records_sql(
                "SELECT COUNT(*) FROM {user_enrolments}
                  WHERE timecreated >= :mstart AND timecreated < :mend",
                ['mstart' => $mstart, 'mend' => $mend]
            );
        } catch (\Exception $e) {
            $chart_enrol_values[] = 0;
        }
    }
}

// ------------------------------------------------------------------
// OUTPUT
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('dashboard', 'dashboard', $userid);
?>
<div class="ld-dashboard-page">

    <!-- ── Top bar: title + global KPI period pills ─── -->
    <div class="ld-dash-topbar">
        <div class="ld-dash-title">
            <span class="ld-dash-title-icon">&#128202;</span>
            <?php echo s(get_string('dashboard_title', 'local_leducon')); ?>
        </div>
        <?php if ($cananalytics): ?>
        <div class="ld-period-bar">
            <span class="ld-period-label">KPIs:</span>
            <?php foreach ($period_pills as $pval => $plabel): ?>
                <?php
                    $purl = new moodle_url('/local/leducon/index.php', ['period' => $pval]);
                    $pcls = 'ld-period-pill' . ($pval === $period ? ' active' : '');
                ?>
                <a href="<?php echo $purl->out(false); ?>" class="<?php echo $pcls; ?>">
                    <?php echo s($plabel); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($gamify_enabled && $progress): ?>
    <!-- ── XP Hero Card ──────────────────────────── -->
    <?php
        $cur_level = $progress['cur_level'];
        $levelname = $cur_level ? format_string($cur_level->name) : get_string('level_none', 'local_leducon');
        $levelnum  = $cur_level ? (int)$cur_level->levelnum : 0;
    ?>
    <div class="ld-hero">
      <div class="ld-hero-inner">
        <div class="ld-hero-left">
          <div class="ld-hero-name"><?php echo s(fullname($USER)); ?></div>
          <div class="ld-hero-level"><?php echo local_leducon_level_badge($levelnum, $levelname); ?></div>
          <div class="ld-hero-xp"><?php echo local_leducon_fmt_xp($progress['total_xp']); ?></div>
          <?php echo local_leducon_xp_bar($progress['total_xp'], $progress['next_xp'], $progress['prev_xp']); ?>
        </div>
        <div class="ld-hero-right">
          <div class="ld-streak <?php echo (int)$streak === 0 ? 'ld-streak-0' : ''; ?>">
            <span class="ld-streak-icon">&#128293;</span>
            <span class="ld-streak-days"><?php echo (int)$streak; ?></span>
            <span class="ld-streak-label"><?php echo get_string('streak_day_label', 'local_leducon'); ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Gamify stat grid ──────────────────────── -->
    <div class="ld-stat-grid">
      <div class="ld-stat">
        <div class="ld-stat-accent" style="--ld-stat-color:var(--ld-accent)"></div>
        <div class="ld-stat-value"><?php echo local_leducon_fmt_xp($progress['total_xp']); ?></div>
        <div class="ld-stat-label"><?php echo get_string('stat_total_xp', 'local_leducon'); ?></div>
      </div>
      <div class="ld-stat">
        <div class="ld-stat-accent" style="--ld-stat-color:#16a34a"></div>
        <div class="ld-stat-value"><?php echo (int)$total_completions; ?></div>
        <div class="ld-stat-label"><?php echo get_string('stat_completions', 'local_leducon'); ?></div>
      </div>
      <div class="ld-stat">
        <div class="ld-stat-accent" style="--ld-stat-color:#d97706"></div>
        <div class="ld-stat-value"><?php echo (int)$total_achievements; ?></div>
        <div class="ld-stat-label"><?php echo get_string('stat_achievements', 'local_leducon'); ?></div>
      </div>
      <div class="ld-stat">
        <div class="ld-stat-accent" style="--ld-stat-color:#f59e0b"></div>
        <div class="ld-stat-value"><?php echo (int)$streak; ?></div>
        <div class="ld-stat-label"><?php echo get_string('streak_day_label', 'local_leducon'); ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($cananalytics): ?>
    <!-- ── Analytics KPI strip ───────────────────── -->
    <div class="ld-kpi-strip">
        <?php foreach ($kpi_cards as $card): ?>
        <div class="ld-kpi-v2" style="--kpi-accent:<?php echo $card['accent']; ?>">
            <div class="ld-kpi-v2-accent"></div>
            <div class="ld-kpi-v2-icon"><?php echo $card['icon']; ?></div>
            <div class="ld-kpi-v2-value"><?php echo s($card['value']); ?></div>
            <div class="ld-kpi-v2-label"><?php echo s($card['label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($cananalytics): ?>
    <!-- ── Dashboard Charts ──────────────────────── -->
    <div class="ld-chart-grid">

      <!-- Activity Trend Line Chart -->
      <div class="ld-card ld-chart-card">
        <div class="ld-card-header">
          <h3 class="ld-card-title"><?php echo get_string('chart_activity_trend', 'local_leducon'); ?></h3>
        </div>
        <div class="ld-card-body ld-chart-body">
        <?php
        if (array_sum($chart_trend_values) > 0) {
            try {
                $trend_chart = new \core\chart_line();
                $trend_chart->set_smooth(true);
                $ts = new \core\chart_series(get_string('chart_logins', 'local_leducon'), $chart_trend_values);
                $ts->set_color('#2563eb');
                $trend_chart->add_series($ts);
                $trend_chart->set_labels($chart_trend_labels);
                echo $OUTPUT->render($trend_chart);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
        } else {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
        ?>
        </div>
      </div>

      <!-- Completion Status Pie Chart -->
      <div class="ld-card ld-chart-card">
        <div class="ld-card-header">
          <h3 class="ld-card-title"><?php echo get_string('chart_completion_status', 'local_leducon'); ?></h3>
        </div>
        <div class="ld-card-body ld-chart-body">
        <?php
        $comp_total = $chart_comp['completed'] + $chart_comp['inprogress'] + $chart_comp['notstarted'];
        if ($comp_total > 0) {
            try {
                $pie = new \core\chart_pie();
                $pie_series = new \core\chart_series(
                    get_string('chart_completion_status', 'local_leducon'),
                    [$chart_comp['completed'], $chart_comp['inprogress'], $chart_comp['notstarted']]
                );
                $pie_series->set_colors(['#16a34a', '#d97706', '#e5e7eb']);
                $pie->add_series($pie_series);
                $pie->set_labels([
                    get_string('chart_completed', 'local_leducon'),
                    get_string('chart_inprogress', 'local_leducon'),
                    get_string('chart_notstarted', 'local_leducon'),
                ]);
                $pie->set_doughnut(true);
                echo $OUTPUT->render($pie);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
        } else {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
        ?>
        </div>
      </div>

      <!-- Grade Distribution Bar Chart -->
      <div class="ld-card ld-chart-card">
        <div class="ld-card-header">
          <h3 class="ld-card-title"><?php echo get_string('chart_grade_dist', 'local_leducon'); ?></h3>
        </div>
        <div class="ld-card-body ld-chart-body">
        <?php
        if (array_sum($chart_grade_values) > 0) {
            try {
                $gbar = new \core\chart_bar();
                $gs = new \core\chart_series(get_string('chart_learners', 'local_leducon'), $chart_grade_values);
                $gs->set_colors(['#dc2626', '#d97706', '#2563eb', '#16a34a', '#059669']);
                $gbar->add_series($gs);
                $gbar->set_labels($chart_grade_labels);
                echo $OUTPUT->render($gbar);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
        } else {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
        ?>
        </div>
      </div>

      <!-- Enrolment Trend Bar Chart -->
      <div class="ld-card ld-chart-card">
        <div class="ld-card-header">
          <h3 class="ld-card-title"><?php echo get_string('chart_enrol_trend', 'local_leducon'); ?></h3>
        </div>
        <div class="ld-card-body ld-chart-body">
        <?php
        if (array_sum($chart_enrol_values) > 0) {
            try {
                $ebar = new \core\chart_bar();
                $es = new \core\chart_series(get_string('chart_enrolments', 'local_leducon'), $chart_enrol_values);
                $es->set_color('#0891b2');
                $ebar->add_series($es);
                $ebar->set_labels($chart_enrol_labels);
                echo $OUTPUT->render($ebar);
            } catch (\Throwable $e) {
                echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
            }
        } else {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
        ?>
        </div>
      </div>

    </div>
    <?php endif; ?>

    <!-- ── Quick-launch report tiles ──────────────── -->
    <?php if ($cananalytics): ?>
    <div class="ld-ql-section">
        <div class="ld-ql-heading"><?php echo s(get_string('nav_reports', 'local_leducon')); ?></div>
        <div class="ld-ql-grid">
            <?php foreach (local_leducon_get_report_types() as $type => $label):
                $rurl = new moodle_url('/local/leducon/analytics/report.php', ['reporttype' => $type]);
                $meta = $ql_meta[$type] ?? ['icon' => '&#128202;', 'accent' => '#6b7280'];
            ?>
            <a href="<?php echo $rurl->out(false); ?>"
               class="ld-ql-tile"
               style="--ql-accent:<?php echo $meta['accent']; ?>">
                <span class="ld-ql-icon"><?php echo $meta['icon']; ?></span>
                <span class="ld-ql-label"><?php echo s($label); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($gamify_enabled && !empty($campaigns)): ?>
    <!-- ── Active campaigns preview ──────────────── -->
    <div class="ld-ql-section">
        <div class="ld-ql-heading"><?php echo s(get_string('active_campaigns', 'local_leducon')); ?></div>
        <div class="ld-campaign-list">
            <?php foreach (array_slice($campaigns, 0, 3) as $camp): ?>
            <div class="ld-campaign-card">
                <div class="ld-campaign-header">
                    <span class="ld-campaign-name"><?php echo s(format_string($camp->name)); ?></span>
                    <span class="ld-badge ld-badge-success"><?php echo get_string('campaign_status_active', 'local_leducon'); ?></span>
                </div>
                <div class="ld-campaign-meta">
                    <span><?php echo get_string('xp_multiplier', 'local_leducon', number_format((float)$camp->multiplier, 1)); ?></span>
                    <span><?php echo get_string('ends', 'local_leducon'); ?>: <?php echo userdate($camp->enddate, get_string('strftimedate', 'langconfig')); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();
