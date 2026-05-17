<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Campaigns — active, upcoming, and ended XP campaigns for the current user.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\campaign_manager;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/campaigns.php'));
$PAGE->set_title(get_string('nav_campaigns', 'local_leducon'));
$PAGE->set_heading(get_string('nav_campaigns', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_campaigns', 'local_leducon'));

$campaigns = campaign_manager::get_all_for_user($USER->id);
$grouped   = ['active' => [], 'upcoming' => [], 'ended' => []];
foreach ($campaigns as $c) {
    $grouped[$c->status][] = $c;
}

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'campaigns', $USER->id);

foreach (['active', 'upcoming', 'ended'] as $status):
    if (empty($grouped[$status])) continue;
?>
<h3 class="ld-section-heading"><?php echo get_string('campaign_status_' . $status, 'local_leducon'); ?></h3>
<div class="ld-campaign-list">
  <?php foreach ($grouped[$status] as $camp): ?>
  <div class="ld-campaign-card ld-campaign-<?php echo s($status); ?>">
    <div class="ld-campaign-header">
      <span class="ld-campaign-name"><?php echo s(format_string($camp->name)); ?></span>
      <span class="ld-badge ld-badge-<?php echo s($status); ?>">
        <?php echo get_string('campaign_status_' . $status, 'local_leducon'); ?>
      </span>
    </div>
    <?php if ($camp->description): ?>
      <div class="ld-campaign-desc"><?php echo format_text($camp->description, FORMAT_PLAIN); ?></div>
    <?php endif; ?>
    <div class="ld-campaign-meta">
      <span><?php echo get_string('xp_multiplier', 'local_leducon',
          number_format((float)$camp->multiplier, 1)); ?>x XP</span>
      <span><?php echo userdate($camp->startdate, get_string('strftimedate', 'langconfig')); ?>
            — <?php echo userdate($camp->enddate, get_string('strftimedate', 'langconfig')); ?></span>
    </div>
    <?php if (!empty($camp->courses)): ?>
      <div class="ld-campaign-courses">
        <strong><?php echo get_string('included_courses', 'local_leducon'); ?>:</strong>
        <ul>
          <?php foreach ($camp->courses as $co): ?>
            <li><?php echo s(format_string($co->fullname)); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($campaigns)): ?>
  <p class="text-muted"><?php echo get_string('no_campaigns', 'local_leducon'); ?></p>
<?php endif; ?>

<?php echo $OUTPUT->footer();
