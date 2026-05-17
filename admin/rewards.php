<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Reward catalog management — add/edit/delete/toggle rewards.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:manageall', $ctx);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/admin/rewards.php'));
$PAGE->set_title(get_string('nav_admin_rewards', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_rewards', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_rewards', 'local_leducon'));

$action   = optional_param('action', '', PARAM_ALPHA);
$rewardid = optional_param('id', 0, PARAM_INT);
$self_url = new moodle_url('/local/leducon/admin/rewards.php');

if ($action === 'save' && confirm_sesskey()) {
    $name      = required_param('name', PARAM_TEXT);
    $desc      = optional_param('description', '', PARAM_TEXT);
    $cost_xp   = required_param('cost_xp', PARAM_INT);
    $quantity  = optional_param('quantity', null, PARAM_INT);
    $enabled   = optional_param('enabled', 0, PARAM_INT);
    $sortorder = optional_param('sortorder', 0, PARAM_INT);
    $qty_val   = ($quantity === null || $quantity < 0) ? null : (int)$quantity;
    $data = (object)['name' => $name, 'description' => $desc, 'cost_xp' => $cost_xp, 'quantity' => $qty_val, 'enabled' => $enabled, 'sortorder' => $sortorder, 'timemodified' => time()];
    if ($rewardid) { $data->id = $rewardid; $DB->update_record('local_leducon_rewards', $data); }
    else { $data->timecreated = time(); $DB->insert_record('local_leducon_rewards', $data); }
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey() && $rewardid) {
    if ($DB->record_exists('local_leducon_redemptions', ['rewardid' => $rewardid, 'status' => 'pending'])) {
        redirect($self_url, get_string('reward_has_pending', 'local_leducon'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $DB->delete_records('local_leducon_rewards', ['id' => $rewardid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'toggle' && confirm_sesskey() && $rewardid) {
    $cur = $DB->get_field('local_leducon_rewards', 'enabled', ['id' => $rewardid], MUST_EXIST);
    $DB->set_field('local_leducon_rewards', 'enabled', $cur ? 0 : 1, ['id' => $rewardid]);
    redirect($self_url);
}

// Bulk enable/disable.
if ($action === 'bulktoggle' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    $bulkval = optional_param('bulk_value', 1, PARAM_INT);
    if (!empty($bulkids)) {
        local_leducon_bulk_toggle('local_leducon_rewards', 'enabled', $bulkval ? 1 : 0, $bulkids);
    }
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$edit_rec = ($action === 'edit' && $rewardid) ? $DB->get_record('local_leducon_rewards', ['id' => $rewardid], '*', MUST_EXIST) : null;
$rewards = $DB->get_records('local_leducon_rewards', null, 'sortorder ASC, cost_xp ASC');

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'rewards', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_rewards', 'local_leducon'));
echo html_writer::tag('p', get_string('admin_rewards_desc', 'local_leducon'), ['class' => 'text-muted']);
?>

<div class="ld-admin-split">
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo $edit_rec ? get_string('edit_reward', 'local_leducon') : get_string('add_reward', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <?php if ($edit_rec): ?><input type="hidden" name="id" value="<?php echo (int)$edit_rec->id; ?>"><?php endif; ?>
        <div class="ld-form-row">
          <label><?php echo get_string('reward_name', 'local_leducon'); ?> <span class="text-danger">*</span></label>
          <input type="text" name="name" required maxlength="200" class="ld-input" value="<?php echo $edit_rec ? s($edit_rec->name) : ''; ?>">
        </div>
        <div class="ld-form-row">
          <label><?php echo get_string('reward_desc', 'local_leducon'); ?></label>
          <textarea name="description" rows="2" class="ld-input ld-textarea"><?php echo $edit_rec ? s($edit_rec->description) : ''; ?></textarea>
        </div>
        <div class="ld-form-grid">
          <div class="ld-form-row">
            <label><?php echo get_string('reward_cost_xp', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="number" name="cost_xp" min="0" required class="ld-input" value="<?php echo $edit_rec ? (int)$edit_rec->cost_xp : ''; ?>">
          </div>
          <div class="ld-form-row">
            <label><?php echo get_string('reward_qty', 'local_leducon'); ?> <small class="text-muted">(<?php echo get_string('leave_blank_unlimited', 'local_leducon'); ?>)</small></label>
            <input type="number" name="quantity" min="0" class="ld-input" value="<?php echo $edit_rec && $edit_rec->quantity !== null ? (int)$edit_rec->quantity : ''; ?>">
          </div>
          <div class="ld-form-row">
            <label><?php echo get_string('sort_order', 'local_leducon'); ?></label>
            <input type="number" name="sortorder" min="0" class="ld-input" value="<?php echo $edit_rec ? (int)$edit_rec->sortorder : 0; ?>">
          </div>
          <div class="ld-form-row" style="display:flex;align-items:center;padding-top:1.5rem">
            <label class="d-flex align-items-center gap-2 mb-0" style="gap:.5rem;cursor:pointer">
              <input type="checkbox" name="enabled" value="1" <?php echo (!$edit_rec || $edit_rec->enabled) ? 'checked' : ''; ?>>
              <?php echo get_string('enabled', 'local_leducon'); ?>
            </label>
          </div>
        </div>
        <div class="ld-btn-group mt-2">
          <button type="submit" class="ld-btn ld-btn-primary"><?php echo get_string('save', 'local_leducon'); ?></button>
          <a href="<?php echo $self_url->out(); ?>" class="ld-btn ld-btn-secondary"><?php echo get_string('cancel'); ?></a>
        </div>
      </form>
    </div>
  </div>

  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title">🎁 <?php echo get_string('nav_admin_rewards', 'local_leducon'); ?></h3>
      <span class="ld-badge ld-badge-secondary"><?php echo count($rewards); ?></span>
    </div>
    <div class="ld-card-body-flush">
      <form method="post" id="bulk-form">
        <input type="hidden" name="action" value="bulktoggle">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <div class="ld-table-wrap">
          <table class="ld-table">
            <thead><tr>
              <th style="width:30px"><input type="checkbox" id="bulk-check-all" title="Select all"></th>
              <th><?php echo get_string('reward_name', 'local_leducon'); ?></th>
              <th><?php echo get_string('reward_cost_xp', 'local_leducon'); ?></th>
              <th><?php echo get_string('reward_qty', 'local_leducon'); ?></th>
              <th style="width:60px"><?php echo get_string('enabled', 'local_leducon'); ?></th>
              <th style="width:160px"><?php echo get_string('actions', 'local_leducon'); ?></th>
            </tr></thead>
            <tbody>
              <?php if (empty($rewards)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3"><?php echo get_string('reward_nodata', 'local_leducon'); ?></td></tr>
              <?php endif; ?>
              <?php foreach ($rewards as $r): ?>
              <tr class="<?php echo $r->enabled ? '' : 'ld-row-hidden'; ?>">
                <td><input type="checkbox" name="bulk_ids[]" value="<?php echo (int)$r->id; ?>" class="bulk-check"></td>
                <td><strong><?php echo s(format_string($r->name)); ?></strong><?php if ($r->description): ?><br><small class="text-muted"><?php echo s($r->description); ?></small><?php endif; ?></td>
                <td><span class="ld-reward-cost">⭐ <?php echo number_format((int)$r->cost_xp); ?></span></td>
                <td><?php echo $r->quantity !== null ? (int)$r->quantity : '∞'; ?></td>
                <td class="text-center"><span class="<?php echo $r->enabled ? 'ld-cell-enabled' : 'ld-cell-disabled'; ?>"><?php echo $r->enabled ? '✓' : '—'; ?></span></td>
                <td>
                  <a href="<?php echo (new moodle_url($self_url, ['action' => 'edit', 'id' => $r->id]))->out(); ?>" class="ld-btn ld-btn-sm"><?php echo get_string('edit'); ?></a>
                  <a href="<?php echo (new moodle_url($self_url, ['action' => 'toggle', 'id' => $r->id, 'sesskey' => sesskey()]))->out(); ?>" class="ld-btn ld-btn-sm"><?php echo $r->enabled ? get_string('disable', 'local_leducon') : get_string('enable', 'local_leducon'); ?></a>
                  <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'id' => $r->id, 'sesskey' => sesskey()]))->out(); ?>"
                     class="ld-btn ld-btn-sm ld-btn-danger" onclick="return confirm('<?php echo get_string('confirm_delete', 'local_leducon'); ?>');"><?php echo get_string('delete'); ?></a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (!empty($rewards)): ?>
        <div class="ld-bulk-bar" style="padding:.75rem 1rem;display:flex;align-items:center;gap:.5rem;border-top:1px solid var(--ld-border)">
          <span class="text-muted" style="font-size:.8125rem">Selected:</span>
          <button type="submit" name="bulk_value" value="1" class="ld-btn ld-btn-sm ld-btn-success"><?php echo get_string('enable', 'local_leducon'); ?></button>
          <button type="submit" name="bulk_value" value="0" class="ld-btn ld-btn-sm ld-btn-secondary"><?php echo get_string('disable', 'local_leducon'); ?></button>
        </div>
        <script>
        document.getElementById('bulk-check-all').addEventListener('change', function() {
            document.querySelectorAll('.bulk-check').forEach(function(c) { c.checked = this.checked; }.bind(this));
        });
        </script>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
