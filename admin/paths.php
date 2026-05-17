<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Learning path CRUD — create/edit/delete paths with ordered course lists.
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
$PAGE->set_url(new moodle_url('/local/leducon/admin/paths.php'));
$PAGE->set_title(get_string('nav_admin_paths', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_paths', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_paths', 'local_leducon'));

$action  = optional_param('action', '', PARAM_ALPHA);
$pathid  = optional_param('id', 0, PARAM_INT);
$self_url = new moodle_url('/local/leducon/admin/paths.php');

if ($action === 'save' && confirm_sesskey()) {
    $name        = required_param('name', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $xp_bonus    = required_param('xp_bonus', PARAM_INT);
    $cohortid    = optional_param('cohortid', null, PARAM_INT) ?: null;
    $enabled     = optional_param('enabled', 0, PARAM_INT);
    $course_ids  = optional_param_array('courseids', [], PARAM_INT);
    $data = (object)['name' => $name, 'description' => $description, 'xp_bonus' => $xp_bonus, 'cohortid' => $cohortid, 'enabled' => $enabled, 'timemodified' => time()];
    if ($pathid) {
        $data->id = $pathid;
        $DB->update_record('local_leducon_paths', $data);
        $DB->delete_records('local_leducon_path_courses', ['pathid' => $pathid]);
    } else {
        $data->timecreated = time();
        $pathid = $DB->insert_record('local_leducon_paths', $data);
    }
    foreach (array_values(array_unique(array_filter($course_ids))) as $i => $cid) {
        $DB->insert_record('local_leducon_path_courses', (object)['pathid' => $pathid, 'courseid' => $cid, 'sortorder' => $i]);
    }
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey() && $pathid) {
    $DB->delete_records('local_leducon_path_courses', ['pathid' => $pathid]);
    $DB->delete_records('local_leducon_paths', ['id' => $pathid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$edit_rec = null; $edit_course_ids = [];
if ($action === 'edit' && $pathid) {
    $edit_rec = $DB->get_record('local_leducon_paths', ['id' => $pathid], '*', MUST_EXIST);
    $edit_course_ids = $DB->get_fieldset_sql("SELECT courseid FROM {local_leducon_path_courses} WHERE pathid = :pid ORDER BY sortorder ASC", ['pid' => $pathid]);
}

$paths   = $DB->get_records('local_leducon_paths', null, 'name ASC');
$cohorts = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
$courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname', 0, 200);

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'paths', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_paths', 'local_leducon'));
echo html_writer::tag('p', get_string('admin_paths_desc', 'local_leducon'), ['class' => 'text-muted']);
?>

<div class="ld-admin-split">
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo $edit_rec ? get_string('edit_path', 'local_leducon') : get_string('add_path', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <?php if ($edit_rec): ?><input type="hidden" name="id" value="<?php echo (int)$edit_rec->id; ?>"><?php endif; ?>
        <div class="ld-form-row"><label><?php echo get_string('path_name', 'local_leducon'); ?> <span class="text-danger">*</span></label>
          <input type="text" name="name" required maxlength="200" class="ld-input" value="<?php echo $edit_rec ? s($edit_rec->name) : ''; ?>"></div>
        <div class="ld-form-row"><label><?php echo get_string('path_desc', 'local_leducon'); ?></label>
          <textarea name="description" rows="2" class="ld-input ld-textarea"><?php echo $edit_rec ? s($edit_rec->description) : ''; ?></textarea></div>
        <div class="ld-form-grid">
          <div class="ld-form-row"><label><?php echo get_string('path_xp_bonus', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="number" name="xp_bonus" min="0" required class="ld-input" value="<?php echo $edit_rec ? (int)$edit_rec->xp_bonus : 400; ?>"></div>
          <div class="ld-form-row"><label><?php echo get_string('cohort_restrict', 'local_leducon'); ?> <small class="text-muted">(<?php echo get_string('leave_blank_all', 'local_leducon'); ?>)</small></label>
            <select name="cohortid" class="ld-select">
              <option value=""><?php echo get_string('all_users', 'local_leducon'); ?></option>
              <?php foreach ($cohorts as $co): ?>
                <option value="<?php echo (int)$co->id; ?>" <?php echo ($edit_rec && $edit_rec->cohortid !== null && (int)$edit_rec->cohortid === (int)$co->id) ? 'selected' : ''; ?>><?php echo s(format_string($co->name)); ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="ld-form-row"><label><?php echo get_string('path_courses', 'local_leducon'); ?> <small class="text-muted">(<?php echo get_string('path_courses_hint', 'local_leducon'); ?>)</small></label>
          <select name="courseids[]" multiple size="8" class="ld-input">
            <?php foreach ($courses as $co): ?>
              <option value="<?php echo (int)$co->id; ?>" <?php echo in_array($co->id, $edit_course_ids) ? 'selected' : ''; ?>><?php echo s(format_string($co->fullname)); ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="ld-form-row"><label class="d-flex align-items-center" style="gap:.5rem;cursor:pointer">
            <input type="checkbox" name="enabled" value="1" <?php echo (!$edit_rec || $edit_rec->enabled) ? 'checked' : ''; ?>> <?php echo get_string('enabled', 'local_leducon'); ?></label></div>
        <div class="ld-btn-group mt-1">
          <button type="submit" class="ld-btn ld-btn-primary"><?php echo get_string('save', 'local_leducon'); ?></button>
          <a href="<?php echo $self_url->out(); ?>" class="ld-btn ld-btn-secondary"><?php echo get_string('cancel'); ?></a>
        </div>
      </form>
    </div>
  </div>

  <div class="ld-card">
    <div class="ld-card-header"><h3 class="ld-card-title">🗺️ <?php echo get_string('nav_admin_paths', 'local_leducon'); ?></h3>
      <span class="ld-badge ld-badge-secondary"><?php echo count($paths); ?></span></div>
    <div class="ld-card-body-flush"><div class="ld-table-wrap">
        <table class="ld-table table table-sm table-hover mb-0">
          <thead><tr>
            <th><?php echo get_string('path_name', 'local_leducon'); ?></th>
            <th><?php echo get_string('path_xp_bonus', 'local_leducon'); ?></th>
            <th style="width:60px"><?php echo get_string('enabled', 'local_leducon'); ?></th>
            <th style="width:110px"><?php echo get_string('actions', 'local_leducon'); ?></th>
          </tr></thead>
          <tbody>
            <?php if (empty($paths)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3"><?php echo get_string('path_nodata_admin', 'local_leducon'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($paths as $p): ?>
            <tr>
              <td><strong><?php echo s(format_string($p->name)); ?></strong></td>
              <td><span class="ld-badge ld-badge-primary">⭐ <?php echo number_format((int)$p->xp_bonus); ?> XP</span></td>
              <td class="text-center"><span class="<?php echo $p->enabled ? 'ld-cell-enabled' : 'ld-cell-disabled'; ?>"><?php echo $p->enabled ? '✓' : '—'; ?></span></td>
              <td>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'edit', 'id' => $p->id]))->out(); ?>" class="ld-btn ld-btn-sm"><?php echo get_string('edit'); ?></a>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]))->out(); ?>"
                   class="ld-btn ld-btn-sm ld-btn-danger" onclick="return confirm('<?php echo get_string('confirm_delete', 'local_leducon'); ?>');"><?php echo get_string('delete'); ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
    </div></div>
  </div>
</div>
<?php echo $OUTPUT->footer();
