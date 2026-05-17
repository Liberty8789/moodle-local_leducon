<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Reward catalog — browse available rewards and initiate redemption.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/rewards.php'));
$PAGE->set_title(get_string('nav_rewards', 'local_leducon'));
$PAGE->set_heading(get_string('nav_rewards', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_rewards', 'local_leducon'));

$userid   = $USER->id;
$my_xp    = (int)($DB->get_field('local_leducon_xp_users', 'total_xp', ['userid' => $userid]) ?: 0);
$rewards  = $DB->get_records('local_leducon_rewards', ['enabled' => 1], 'sortorder ASC, cost_xp ASC');

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'rewards', $USER->id);
?>

<div class="ld-balance-bar">
  <span><?php echo get_string('your_balance', 'local_leducon'); ?>:</span>
  <strong><?php echo local_leducon_fmt_xp($my_xp); ?></strong>
</div>

<?php if ($rewards): ?>
<div class="ld-reward-grid">
  <?php foreach ($rewards as $r): ?>
    <?php $can_afford = $my_xp >= (int)$r->cost_xp; ?>
    <div class="ld-reward-card <?php echo $can_afford ? '' : 'ld-reward-locked'; ?>">
      <div class="ld-reward-name"><?php echo s(format_string($r->name)); ?></div>
      <?php if ($r->description): ?>
        <div class="ld-reward-desc"><?php echo format_text($r->description, FORMAT_PLAIN); ?></div>
      <?php endif; ?>
      <div class="ld-reward-cost"><?php echo local_leducon_fmt_xp((int)$r->cost_xp); ?></div>
      <?php if ($r->quantity !== null): ?>
        <div class="ld-reward-qty"><?php echo get_string('qty_left', 'local_leducon', (int)$r->quantity); ?></div>
      <?php endif; ?>
      <?php if ($can_afford && ($r->quantity === null || (int)$r->quantity > 0)): ?>
        <a href="<?php echo (new moodle_url('/local/leducon/gamify/redeem.php', ['rewardid' => $r->id]))->out(); ?>"
           class="ld-btn ld-btn-primary">
          <?php echo get_string('redeem_btn', 'local_leducon'); ?>
        </a>
      <?php else: ?>
        <span class="ld-btn ld-btn-disabled">
          <?php echo $can_afford ? get_string('out_of_stock', 'local_leducon') : get_string('insufficient_xp', 'local_leducon'); ?>
        </span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="ld-empty-state">
    <div class="ld-empty-state-icon">🎁</div>
    <div class="ld-empty-state-title"><?php echo get_string('no_rewards', 'local_leducon'); ?></div>
    <div class="ld-empty-state-desc"><?php echo get_string('reward_nodata', 'local_leducon'); ?></div>
  </div>
<?php endif; ?>

<?php echo $OUTPUT->footer();
