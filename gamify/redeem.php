<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Reward redemption — confirm and process XP-based reward claims.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\xp_engine;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$rewardid = required_param('rewardid', PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_INT);
$rewards_url = new moodle_url('/local/leducon/gamify/rewards.php');

$reward = $DB->get_record('local_leducon_rewards', ['id' => $rewardid, 'enabled' => 1], '*', MUST_EXIST);
$userid = $USER->id;
$my_xp  = (int)($DB->get_field('local_leducon_xp_users', 'total_xp', ['userid' => $userid]) ?: 0);

if ($my_xp < (int)$reward->cost_xp) {
    redirect($rewards_url, get_string('insufficient_xp', 'local_leducon'), null,
        \core\output\notification::NOTIFY_WARNING);
}
if ($reward->quantity !== null && (int)$reward->quantity <= 0) {
    redirect($rewards_url, get_string('out_of_stock', 'local_leducon'), null,
        \core\output\notification::NOTIFY_WARNING);
}

if ($confirm && confirm_sesskey()) {
    $DB->insert_record('local_leducon_redemptions', (object)[
        'userid'       => $userid,
        'rewardid'     => $reward->id,
        'cost_xp'      => $reward->cost_xp,
        'status'       => 'pending',
        'approvedby'   => null,
        'manager_note' => null,
        'timecreated'  => time(),
        'timemodified' => time(),
    ]);
    // Deduct XP immediately; refunded if rejected.
    xp_engine::deduct($userid, (int)$reward->cost_xp, 'Redemption: ' . format_string($reward->name));

    // Decrement quantity if finite.
    if ($reward->quantity !== null) {
        $DB->set_field('local_leducon_rewards', 'quantity',
            max(0, (int)$reward->quantity - 1), ['id' => $reward->id]);
    }

    redirect($rewards_url, get_string('redemption_submitted', 'local_leducon'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/redeem.php', ['rewardid' => $rewardid]));
$PAGE->set_title(get_string('redeem_title', 'local_leducon'));
$PAGE->set_heading(get_string('redeem_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_rewards', 'local_leducon'), $rewards_url);
$PAGE->navbar->add(get_string('redeem_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'rewards', $USER->id);
?>

<div class="ld-card ld-confirm-card">
  <h3><?php echo get_string('redeem_confirm_heading', 'local_leducon'); ?></h3>
  <p><strong><?php echo s(format_string($reward->name)); ?></strong></p>
  <p><?php echo get_string('redeem_cost_line', 'local_leducon',
      local_leducon_fmt_xp((int)$reward->cost_xp)); ?></p>
  <p class="text-muted"><?php echo get_string('redeem_balance_after', 'local_leducon',
      local_leducon_fmt_xp($my_xp - (int)$reward->cost_xp)); ?></p>
  <form method="post">
    <input type="hidden" name="rewardid" value="<?php echo (int)$rewardid; ?>">
    <input type="hidden" name="confirm"  value="1">
    <?php echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
    <button type="submit" class="ld-btn ld-btn-primary">
      <?php echo get_string('redeem_confirm_btn', 'local_leducon'); ?>
    </button>
    <a href="<?php echo $rewards_url->out(); ?>" class="ld-btn ld-btn-secondary">
      <?php echo get_string('cancel'); ?>
    </a>
  </form>
</div>

<?php echo $OUTPUT->footer();
