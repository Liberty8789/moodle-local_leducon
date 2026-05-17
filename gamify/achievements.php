<?php
// This file is part of Moodle - http://moodle.org/

/**
 * My achievements — earned and locked achievement grids.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\achievement_manager;
use local_leducon\gamify\level_manager;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/achievements.php'));
$PAGE->set_title(get_string('nav_achievements', 'local_leducon'));
$PAGE->set_heading(get_string('nav_achievements', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_achievements', 'local_leducon'));

$achievements = achievement_manager::get_for_user($USER->id);
$earned = array_filter($achievements, function($a) { return $a->earned; });
$locked = array_filter($achievements, function($a) { return !$a->earned; });

// Level progress data for the progress strip.
$progress = level_manager::get_progress($USER->id);

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'achievements', $USER->id);

// Certificate source note.
echo html_writer::tag('p',
    '🏅 ' . s(get_string('achiev_cert_note', 'local_leducon')),
    ['class' => 'text-muted small mb-3']
);

// Link to own analytics report card if user has permission.
if (has_capability('local/leducon:viewown', $ctx)) {
    echo html_writer::start_div('ld-companion-bar');
    echo '📊 ' . get_string('companion_analytics_report', 'local_leducon') . ' ';
    echo html_writer::link(
        new moodle_url('/local/leducon/analytics/myreport.php'),
        get_string('companion_analytics_label', 'local_leducon')
    );
    echo html_writer::end_div();
}

// Level progress strip — connects levels to achievements.
$cur_level  = $progress['cur_level'];
$next_level = $progress['next_level'];
$total_xp   = $progress['total_xp'];
$prev_xp    = $progress['prev_xp'];
$next_xp    = $progress['next_xp'];

$level_name = $cur_level ? format_string($cur_level->name) : get_string('level_none', 'local_leducon');
$level_num  = $cur_level ? (int)$cur_level->levelnum : 0;
$next_name  = $next_level ? format_string($next_level->name) : '';
$range      = max(1, $next_xp - $prev_xp);
$pct        = $next_level ? min(100, round(($total_xp - $prev_xp) / $range * 100)) : 100;
$xp_remain  = max(0, $next_xp - $total_xp);
?>

<div class="ld-level-strip">
  <div class="ld-level-strip-left">
    <span class="ld-level-badge ld-level-<?php echo min($level_num, 5) ?: 0; ?>">
      <?php echo s($level_name); ?>
    </span>
    <span class="ld-level-strip-xp"><?php echo number_format($total_xp); ?> XP</span>
  </div>
  <div class="ld-level-strip-bar-wrap">
    <div class="ld-level-strip-bar">
      <div class="ld-level-strip-fill" style="width:<?php echo $pct; ?>%"></div>
    </div>
    <div class="ld-level-strip-meta">
      <span><?php echo $pct; ?>%</span>
      <?php if ($next_level): ?>
        <span><?php echo get_string('achiev_xp_to_next', 'local_leducon', number_format($xp_remain)); ?></span>
      <?php else: ?>
        <span><?php echo get_string('achiev_max_level', 'local_leducon'); ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($next_level): ?>
  <div class="ld-level-strip-next">
    <span class="ld-level-strip-next-label"><?php echo get_string('achiev_next_level', 'local_leducon'); ?></span>
    <span class="ld-level-strip-next-name"><?php echo s($next_name); ?></span>
  </div>
  <?php endif; ?>
</div>

<div class="ld-achiev-summary">
  <?php echo get_string('achiev_earned_count', 'local_leducon',
      count($earned) . ' / ' . count($achievements)); ?>
</div>

<?php if ($earned): ?>
<h3 class="ld-section-heading"><?php echo get_string('achiev_earned', 'local_leducon'); ?></h3>
<div class="ld-achiev-grid">
  <?php foreach ($earned as $a): ?>
  <div class="ld-achiev-tile earned ld-achiev-earned">
    <div class="ld-achiev-icon"><?php echo s($a->icon); ?></div>
    <div class="ld-achiev-name"><?php echo s(format_string($a->name)); ?></div>
    <?php if ($a->description): ?>
      <div class="ld-achiev-desc"><?php echo s($a->description); ?></div>
    <?php endif; ?>
    <?php if ((int)$a->xp_bonus > 0): ?>
      <div class="ld-achiev-bonus">+<?php echo number_format((int)$a->xp_bonus); ?> XP</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($locked): ?>
<h3 class="ld-section-heading"><?php echo get_string('achiev_locked', 'local_leducon'); ?></h3>
<div class="ld-achiev-grid">
  <?php foreach ($locked as $a): ?>
  <div class="ld-achiev-tile ld-achiev-locked">
    <div class="ld-achiev-icon ld-achiev-icon-lock">🔒</div>
    <div class="ld-achiev-name"><?php echo s(format_string($a->name)); ?></div>
    <?php if ($a->description): ?>
      <div class="ld-achiev-desc"><?php echo s($a->description); ?></div>
    <?php endif; ?>
    <?php if ((int)$a->xp_bonus > 0): ?>
      <div class="ld-achiev-bonus">+<?php echo number_format((int)$a->xp_bonus); ?> XP</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($achievements)): ?>
  <p class="text-muted"><?php echo get_string('no_achievements', 'local_leducon'); ?></p>
<?php endif; ?>

<?php echo $OUTPUT->footer();
