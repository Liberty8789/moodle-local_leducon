<?php
// This file is part of Moodle - http://moodle.org/

/**
 * XP level thresholds management — add/edit/delete levels.
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
$PAGE->set_url(new moodle_url('/local/leducon/admin/levels.php'));
$PAGE->set_title(get_string('nav_admin_levels', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_levels', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_levels', 'local_leducon'));

$action   = optional_param('action', '', PARAM_ALPHA);
$levelid  = optional_param('id', 0, PARAM_INT);
$self_url = new moodle_url('/local/leducon/admin/levels.php');

if ($action === 'save' && confirm_sesskey()) {
    $levelnum = required_param('levelnum', PARAM_INT);
    $name     = required_param('name', PARAM_TEXT);
    $min_xp   = required_param('min_xp', PARAM_INT);
    $now      = time();
    if ($levelid) {
        $dup = $DB->record_exists_select('local_leducon_levels', 'levelnum = :lnum AND id <> :lid', ['lnum' => $levelnum, 'lid' => $levelid]);
    } else {
        $dup = $DB->record_exists('local_leducon_levels', ['levelnum' => $levelnum]);
    }
    if ($dup) {
        redirect($self_url, get_string('level_duplicate', 'local_leducon'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($levelid) {
        $DB->update_record('local_leducon_levels', (object)['id' => $levelid, 'levelnum' => $levelnum, 'name' => $name, 'min_xp' => $min_xp, 'timemodified' => $now]);
    } else {
        $DB->insert_record('local_leducon_levels', (object)['levelnum' => $levelnum, 'name' => $name, 'min_xp' => $min_xp, 'timecreated' => $now, 'timemodified' => $now]);
    }
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey() && $levelid) {
    $DB->delete_records('local_leducon_levels', ['id' => $levelid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$edit_record = ($action === 'edit' && $levelid) ? $DB->get_record('local_leducon_levels', ['id' => $levelid], '*', MUST_EXIST) : null;
$levels = $DB->get_records('local_leducon_levels', null, 'levelnum ASC');

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'levels', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_levels', 'local_leducon'));
echo html_writer::tag('p', get_string('admin_levels_desc', 'local_leducon'), ['class' => 'text-muted']);
?>

<div class="ld-admin-split">
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo $edit_record ? get_string('edit_level', 'local_leducon') : get_string('add_level', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <?php if ($edit_record): ?><input type="hidden" name="id" value="<?php echo (int)$edit_record->id; ?>"><?php endif; ?>
        <div class="ld-form-grid">
          <div class="ld-form-row">
            <label><?php echo get_string('level_num', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="number" name="levelnum" min="1" required class="ld-input" value="<?php echo $edit_record ? (int)$edit_record->levelnum : ''; ?>">
          </div>
          <div class="ld-form-row">
            <label><?php echo get_string('level_min_xp', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="number" name="min_xp" min="0" required class="ld-input" value="<?php echo $edit_record ? (int)$edit_record->min_xp : ''; ?>">
          </div>
          <div class="ld-form-row ld-form-row-full">
            <label><?php echo get_string('level_name', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="text" name="name" required maxlength="100" class="ld-input" value="<?php echo $edit_record ? s($edit_record->name) : ''; ?>">
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
      <h3 class="ld-card-title">📊 <?php echo get_string('nav_admin_levels', 'local_leducon'); ?></h3>
      <span class="ld-badge ld-badge-secondary"><?php echo count($levels); ?></span>
    </div>
    <div class="ld-card-body-flush">
      <div class="ld-table-wrap">
        <table class="ld-table table table-sm table-hover mb-0">
          <thead><tr>
            <th style="width:60px">#</th>
            <th><?php echo get_string('level_name', 'local_leducon'); ?></th>
            <th><?php echo get_string('level_min_xp', 'local_leducon'); ?></th>
            <th style="width:130px"><?php echo get_string('actions', 'local_leducon'); ?></th>
          </tr></thead>
          <tbody>
            <?php if (empty($levels)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3"><?php echo get_string('level_nodata', 'local_leducon'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($levels as $lvl): ?>
            <tr>
              <td><?php echo local_leducon_level_badge((int)$lvl->levelnum, (string)$lvl->levelnum); ?></td>
              <td><strong><?php echo s(format_string($lvl->name)); ?></strong></td>
              <td><?php echo number_format((int)$lvl->min_xp); ?> XP</td>
              <td>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'edit', 'id' => $lvl->id]))->out(); ?>" class="ld-btn ld-btn-sm"><?php echo get_string('edit'); ?></a>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'id' => $lvl->id, 'sesskey' => sesskey()]))->out(); ?>"
                   class="ld-btn ld-btn-sm ld-btn-danger" onclick="return confirm('<?php echo get_string('confirm_delete', 'local_leducon'); ?>');"><?php echo get_string('delete'); ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php echo $OUTPUT->footer();
