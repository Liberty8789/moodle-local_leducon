<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Gamification XP dashboard — hero card, stats, recent XP, campaigns, paths.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\level_manager;
use local_leducon\gamify\campaign_manager;
use local_leducon\gamify\path_manager;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/index.php'));
$PAGE->set_title(get_string('nav_gamify', 'local_leducon'));
$PAGE->set_heading(get_string('nav_gamify', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_gamify', 'local_leducon'));

$userid = $USER->id;

// Progress data.
$progress = level_manager::get_progress($userid);
$streak   = local_leducon_get_streak($userid);

// Event type → icon map.
$event_icons = [
    'course_completion'  => '🎓',
    'quiz_attempt'       => '📝',
    'quiz_pass_first'    => '✅',
    'assign_submitted'   => '📤',
    'assign_ontime'      => '⏰',
    'forum_post'         => '💬',
    'badge_awarded'      => '🏅',
    'path_completion'    => '🗺️',
    'achievement_bonus'  => '🏆',
    'spotlight_received' => '✨',
    'streak_milestone'   => '🔥',
];

// Recent XP transactions (last 10).
$recent_xp = $DB->get_records_sql(
    "SELECT xl.id, xl.points, xl.eventtype, xl.note, xl.timecreated
       FROM {local_leducon_xp_log} xl
      WHERE xl.userid = :uid AND xl.points > 0
   ORDER BY xl.timecreated DESC",
    ['uid' => $userid],
    0, 10
);

// Active campaigns.
$campaigns = campaign_manager::get_active_campaigns($userid);

// Active paths (up to 3 in-progress).
$paths = array_filter(path_manager::get_paths_for_user($userid), function($p) { return !$p->completed; });
$paths = array_slice($paths, 0, 3);

// Stat grid.
$total_completions  = (int)$DB->count_records_sql(
    "SELECT COUNT(*) FROM {course_completions} cc
    LEFT JOIN {grade_items} gi_gi ON gi_gi.courseid = cc.course AND gi_gi.itemtype = 'course'
    LEFT JOIN {grade_grades} gg_gi ON gg_gi.itemid = gi_gi.id AND gg_gi.userid = cc.userid
    WHERE cc.userid = :uid
      AND (cc.timecompleted IS NOT NULL
          OR (gg_gi.finalgrade IS NOT NULL AND gi_gi.grademax > 0 AND gg_gi.finalgrade / gi_gi.grademax * 100 >= 100))",
    ['uid' => $userid]
);
$total_achievements = $DB->count_records('local_leducon_user_achiev', ['userid' => $userid]);

// ------------------------------------------------------------------
// Chart data: XP earned per day (last 14 days).
// ------------------------------------------------------------------
$xp_daily_labels = [];
$xp_daily_values = [];
$xp_daily_counts = [];
try {
    $twoweeks = time() - 14 * DAYSECS;
    $xp_rs = $DB->get_recordset_sql(
        "SELECT id, timecreated, points FROM {local_leducon_xp_log}
          WHERE userid = :uid AND points > 0 AND timecreated > :cutoff",
        ['uid' => $userid, 'cutoff' => $twoweeks]
    );
    foreach ($xp_rs as $xr) {
        $daykey = date('Y-m-d', $xr->timecreated);
        $xp_daily_counts[$daykey] = ($xp_daily_counts[$daykey] ?? 0) + (int)$xr->points;
    }
    $xp_rs->close();
} catch (\dml_exception $e) {}

for ($i = 13; $i >= 0; $i--) {
    $daykey = date('Y-m-d', time() - $i * DAYSECS);
    $xp_daily_labels[] = date('d M', time() - $i * DAYSECS);
    $xp_daily_values[] = $xp_daily_counts[$daykey] ?? 0;
}

// Chart data: XP by event type (pie chart).
$xp_type_labels = [];
$xp_type_values = [];
try {
    $type_rows = $DB->get_records_sql(
        "SELECT eventtype, SUM(points) AS total
           FROM {local_leducon_xp_log}
          WHERE userid = :uid AND points > 0
       GROUP BY eventtype
       ORDER BY total DESC",
        ['uid' => $userid]
    );
    foreach ($type_rows as $tr) {
        $str_key = 'event_' . $tr->eventtype;
        $label = get_string_manager()->string_exists($str_key, 'local_leducon')
            ? get_string($str_key, 'local_leducon') : ucwords(str_replace('_', ' ', $tr->eventtype));
        $xp_type_labels[] = $label;
        $xp_type_values[] = (int)$tr->total;
    }
} catch (\dml_exception $e) {}

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'dashboard', $USER->id);

// Hero card.
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
        <span class="ld-streak-icon">🔥</span>
        <span class="ld-streak-days"><?php echo (int)$streak; ?></span>
        <span class="ld-streak-label"><?php echo get_string('streak_day_label', 'local_leducon'); ?></span>
      </div>
      <?php if ($streak >= 7): ?>
        <div class="ld-streak-note"><?php echo get_string('streak_on_fire', 'local_leducon'); ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Stat grid -->
<div class="ld-stat-grid">
  <div class="ld-stat">
    <div class="ld-stat-accent" style="--ld-accent:var(--ld-primary)"></div>
    <div class="ld-stat-icon">⚡</div>
    <div class="ld-stat-value"><?php echo local_leducon_fmt_xp($progress['total_xp']); ?></div>
    <div class="ld-stat-label"><?php echo get_string('stat_total_xp', 'local_leducon'); ?></div>
  </div>
  <div class="ld-stat">
    <div class="ld-stat-accent" style="--ld-accent:#16a34a"></div>
    <div class="ld-stat-icon">🎓</div>
    <div class="ld-stat-value"><?php echo (int)$total_completions; ?></div>
    <div class="ld-stat-label"><?php echo get_string('stat_completions', 'local_leducon'); ?></div>
  </div>
  <div class="ld-stat">
    <div class="ld-stat-accent" style="--ld-accent:#d97706"></div>
    <div class="ld-stat-icon">🏅</div>
    <div class="ld-stat-value"><?php echo (int)$total_achievements; ?></div>
    <div class="ld-stat-label"><?php echo get_string('stat_achievements', 'local_leducon'); ?></div>
  </div>
  <div class="ld-stat">
    <div class="ld-stat-accent" style="--ld-accent:#f59e0b"></div>
    <div class="ld-stat-icon">🔥</div>
    <div class="ld-stat-value"><?php echo (int)$streak; ?></div>
    <div class="ld-stat-label"><?php echo get_string('streak_day_label', 'local_leducon'); ?></div>
  </div>
</div>

<!-- XP Charts -->
<div class="ld-chart-grid">

  <!-- XP Earnings (last 14 days) -->
  <div class="ld-card ld-chart-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo get_string('chart_xp_daily', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body ld-chart-body">
    <?php
    if (array_sum($xp_daily_values) > 0) {
        try {
            $xp_chart = new \core\chart_bar();
            $xps = new \core\chart_series(get_string('chart_xp_earned', 'local_leducon'), $xp_daily_values);
            $xps->set_color('#7c3aed');
            $xp_chart->add_series($xps);
            $xp_chart->set_labels($xp_daily_labels);
            echo $OUTPUT->render($xp_chart);
        } catch (\Throwable $e) {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
    } else {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
    ?>
    </div>
  </div>

  <!-- XP by Activity Type -->
  <div class="ld-card ld-chart-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo get_string('chart_xp_breakdown', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body ld-chart-body">
    <?php
    if (!empty($xp_type_values) && array_sum($xp_type_values) > 0) {
        try {
            $xp_pie = new \core\chart_pie();
            $xp_pie_s = new \core\chart_series(
                get_string('chart_xp_breakdown', 'local_leducon'),
                $xp_type_values
            );
            $xp_pie_s->set_colors(['#7c3aed', '#2563eb', '#16a34a', '#d97706', '#0891b2', '#dc2626', '#6366f1', '#059669']);
            $xp_pie->add_series($xp_pie_s);
            $xp_pie->set_labels($xp_type_labels);
            $xp_pie->set_doughnut(true);
            echo $OUTPUT->render($xp_pie);
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

<div class="ld-dashboard-cols">

  <!-- Recent XP activity -->
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title">⚡ <?php echo get_string('recent_activity', 'local_leducon'); ?></h3>
      <a href="<?php echo (new moodle_url('/local/leducon/gamify/achievements.php'))->out(); ?>"
         class="ld-card-link"><?php echo get_string('view_all', 'local_leducon'); ?> →</a>
    </div>
    <div class="ld-card-body-flush">
      <?php if ($recent_xp): ?>
        <?php foreach ($recent_xp as $tx):
              $icon = $event_icons[$tx->eventtype] ?? '⭐';
              $event_str = 'event_' . $tx->eventtype;
              $label = get_string_manager()->string_exists($event_str, 'local_leducon')
                  ? get_string($event_str, 'local_leducon', $tx->note) : $tx->note;
        ?>
        <div class="ld-tx-item">
          <span class="ld-tx-icon"><?php echo $icon; ?></span>
          <span class="ld-tx-desc"><?php echo s($label); ?></span>
          <span class="ld-tx-time"><?php echo userdate($tx->timecreated, get_string('strftimedatetimeshort', 'langconfig')); ?></span>
          <span class="ld-tx-pts">+<?php echo number_format((int)$tx->points); ?> XP</span>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ld-card-body">
          <p class="text-muted mb-0"><?php echo get_string('no_recent_activity', 'local_leducon'); ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Active campaigns -->
  <?php if ($campaigns): ?>
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title">⚡ <?php echo get_string('active_campaigns', 'local_leducon'); ?></h3>
      <a href="<?php echo (new moodle_url('/local/leducon/gamify/campaigns.php'))->out(); ?>"
         class="ld-card-link"><?php echo get_string('view_all', 'local_leducon'); ?> →</a>
    </div>
    <div class="ld-card-body">
      <div class="ld-campaign-list">
        <?php foreach ($campaigns as $camp): ?>
        <div class="ld-campaign-card">
          <div class="ld-campaign-header">
            <span class="ld-campaign-name"><?php echo s(format_string($camp->name)); ?></span>
            <span class="ld-badge ld-badge-success"><?php echo get_string('campaign_status_active', 'local_leducon'); ?></span>
          </div>
          <div class="ld-campaign-meta">
            <span>🚀 <?php echo get_string('xp_multiplier', 'local_leducon', number_format((float)$camp->multiplier, 1)); ?></span>
            <span>⏳ <?php echo get_string('ends', 'local_leducon'); ?>: <?php echo userdate($camp->enddate, get_string('strftimedate', 'langconfig')); ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Active learning paths -->
  <?php if ($paths): ?>
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title">🗺️ <?php echo get_string('your_paths', 'local_leducon'); ?></h3>
      <a href="<?php echo (new moodle_url('/local/leducon/gamify/paths.php'))->out(); ?>"
         class="ld-card-link"><?php echo get_string('view_all', 'local_leducon'); ?> →</a>
    </div>
    <div class="ld-card-body">
      <div class="ld-path-list">
        <?php foreach ($paths as $path): ?>
        <div class="ld-path-card">
          <div class="ld-path-header">
            <span class="ld-path-title"><?php echo s(format_string($path->name)); ?></span>
            <span class="ld-badge ld-badge-secondary"><?php echo (int)$path->progress; ?>%</span>
          </div>
          <div class="ld-progress mt-2">
            <div class="ld-progress-bar ld-fill-primary" style="width:<?php echo (int)$path->progress; ?>%"></div>
          </div>
          <small class="text-muted"><?php echo (int)$path->progress; ?>% <?php echo get_string('complete', 'local_leducon'); ?></small>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();
