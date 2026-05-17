<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Campaign CRUD — create/edit/delete XP campaigns with course and cohort targeting.
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
$PAGE->set_url(new moodle_url('/local/leducon/admin/campaigns.php'));
$PAGE->set_title(get_string('nav_admin_campaigns', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_campaigns', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_campaigns', 'local_leducon'));

$action     = optional_param('action', '', PARAM_ALPHA);
$campaignid = optional_param('id', 0, PARAM_INT);
$self_url   = new moodle_url('/local/leducon/admin/campaigns.php');

if ($action === 'save' && confirm_sesskey()) {
    $name        = required_param('name', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $multiplier  = max(1.0, min(10.0, (float)required_param('multiplier', PARAM_RAW)));
    $startdate_str = required_param('startdate_str', PARAM_TEXT);
    $enddate_str   = required_param('enddate_str', PARAM_TEXT);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startdate_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enddate_str)) {
        redirect($self_url, get_string('campaign_date_error', 'local_leducon'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $startdate   = (int)strtotime($startdate_str . ' 00:00:00');
    $enddate     = (int)strtotime($enddate_str . ' 23:59:59');
    $cohortid    = optional_param('cohortid', null, PARAM_INT) ?: null;
    $enabled     = optional_param('enabled', 0, PARAM_INT);
    $course_ids  = optional_param_array('courseids', [], PARAM_INT);
    if ($enddate <= $startdate) {
        redirect($self_url, get_string('campaign_date_error', 'local_leducon'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $data = (object)['name' => $name, 'description' => $description, 'multiplier' => round((float)$multiplier, 2),
        'startdate' => $startdate, 'enddate' => $enddate, 'cohortid' => $cohortid, 'enabled' => $enabled, 'timemodified' => time()];
    if ($campaignid) {
        $data->id = $campaignid;
        $DB->update_record('local_leducon_campaigns', $data);
        $DB->delete_records('local_leducon_camp_courses', ['campaignid' => $campaignid]);
    } else {
        $data->timecreated = time();
        $campaignid = $DB->insert_record('local_leducon_campaigns', $data);
    }
    foreach (array_unique(array_filter($course_ids)) as $cid) {
        $DB->insert_record('local_leducon_camp_courses', (object)['campaignid' => $campaignid, 'courseid' => $cid]);
    }
    redirect($self_url, get_string('saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && confirm_sesskey() && $campaignid) {
    $DB->delete_records('local_leducon_camp_courses', ['campaignid' => $campaignid]);
    $DB->delete_records('local_leducon_campaigns', ['id' => $campaignid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$edit_rec = null; $edit_course_ids = [];
if ($action === 'edit' && $campaignid) {
    $edit_rec = $DB->get_record('local_leducon_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
    $edit_course_ids = $DB->get_fieldset_select('local_leducon_camp_courses', 'courseid', 'campaignid = :cid', ['cid' => $campaignid]);
}

$campaigns = $DB->get_records('local_leducon_campaigns', null, 'startdate DESC');
$cohorts   = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');
$courses   = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname', 0, 200);

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'campaigns', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_campaigns', 'local_leducon'));
echo html_writer::tag('p', get_string('admin_campaigns_desc', 'local_leducon'), ['class' => 'text-muted']);
?>

<div class="ld-admin-split">
  <div class="ld-card">
    <div class="ld-card-header">
      <h3 class="ld-card-title"><?php echo $edit_rec ? get_string('edit_campaign', 'local_leducon') : get_string('add_campaign', 'local_leducon'); ?></h3>
    </div>
    <div class="ld-card-body">
      <form method="post" action="<?php echo $self_url->out(false); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <?php if ($edit_rec): ?><input type="hidden" name="id" value="<?php echo (int)$edit_rec->id; ?>"><?php endif; ?>
        <div class="ld-form-row"><label><?php echo get_string('campaign_name', 'local_leducon'); ?> <span class="text-danger">*</span></label>
          <input type="text" name="name" required maxlength="200" class="ld-input" value="<?php echo $edit_rec ? s($edit_rec->name) : ''; ?>"></div>
        <div class="ld-form-row"><label><?php echo get_string('campaign_desc', 'local_leducon'); ?></label>
          <textarea name="description" rows="2" class="ld-input ld-textarea"><?php echo $edit_rec ? s($edit_rec->description) : ''; ?></textarea></div>
        <div class="ld-form-grid">
          <div class="ld-form-row"><label><?php echo get_string('campaign_multiplier', 'local_leducon'); ?> <span class="text-danger">*</span> <small class="text-muted">(e.g. 2.0 = double XP)</small></label>
            <input type="number" name="multiplier" min="1" max="10" step="0.1" required class="ld-input" value="<?php echo $edit_rec ? number_format((float)$edit_rec->multiplier, 1) : '2.0'; ?>"></div>
          <div class="ld-form-row"><label><?php echo get_string('cohort_restrict', 'local_leducon'); ?> <small class="text-muted">(<?php echo get_string('leave_blank_all', 'local_leducon'); ?>)</small></label>
            <select name="cohortid" class="ld-select">
              <option value=""><?php echo get_string('all_users', 'local_leducon'); ?></option>
              <?php foreach ($cohorts as $co): ?>
                <option value="<?php echo (int)$co->id; ?>" <?php echo ($edit_rec && $edit_rec->cohortid !== null && (int)$edit_rec->cohortid === (int)$co->id) ? 'selected' : ''; ?>><?php echo s(format_string($co->name)); ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="ld-form-row"><label><?php echo get_string('campaign_startdate', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="date" name="startdate_str" required class="ld-input" value="<?php echo ($edit_rec && $edit_rec->startdate > 0) ? date('Y-m-d', $edit_rec->startdate) : ''; ?>"></div>
          <div class="ld-form-row"><label><?php echo get_string('campaign_enddate', 'local_leducon'); ?> <span class="text-danger">*</span></label>
            <input type="date" name="enddate_str" required class="ld-input" value="<?php echo ($edit_rec && $edit_rec->enddate > 0) ? date('Y-m-d', $edit_rec->enddate) : ''; ?>"></div>
        </div>
        <div class="ld-form-row"><label><?php echo get_string('campaign_courses', 'local_leducon'); ?> <small class="text-muted">(<?php echo get_string('campaign_courses_hint', 'local_leducon'); ?>)</small></label>
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
    <div class="ld-card-header"><h3 class="ld-card-title">🚀 <?php echo get_string('nav_admin_campaigns', 'local_leducon'); ?></h3>
      <span class="ld-badge ld-badge-secondary"><?php echo count($campaigns); ?></span></div>
    <div class="ld-card-body-flush"><div class="ld-table-wrap">
        <table class="ld-table table table-sm table-hover mb-0">
          <thead><tr>
            <th><?php echo get_string('campaign_name', 'local_leducon'); ?></th>
            <th><?php echo get_string('campaign_multiplier', 'local_leducon'); ?></th>
            <th><?php echo get_string('campaign_startdate', 'local_leducon'); ?></th>
            <th><?php echo get_string('campaign_enddate', 'local_leducon'); ?></th>
            <th style="width:60px"><?php echo get_string('enabled', 'local_leducon'); ?></th>
            <th style="width:110px"><?php echo get_string('actions', 'local_leducon'); ?></th>
          </tr></thead>
          <tbody>
            <?php if (empty($campaigns)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3"><?php echo get_string('campaign_nodata_admin', 'local_leducon'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($campaigns as $c): $now=time(); $active=$c->enabled&&$c->startdate<=$now&&$c->enddate>=$now; ?>
            <tr class="<?php echo $c->enabled ? '' : 'ld-row-hidden'; ?>">
              <td><strong><?php echo s(format_string($c->name)); ?></strong>
                <?php if ($active): ?><span class="ld-badge ld-badge-success" style="margin-left:.4rem;font-size:.7rem">● <?php echo get_string('campaign_live', 'local_leducon'); ?></span><?php endif; ?></td>
              <td><span class="ld-badge ld-badge-primary">⚡ <?php echo number_format((float)$c->multiplier, 1); ?>×</span></td>
              <td><?php echo userdate($c->startdate, get_string('strftimedate', 'langconfig')); ?></td>
              <td><?php echo userdate($c->enddate, get_string('strftimedate', 'langconfig')); ?></td>
              <td class="text-center"><span class="<?php echo $c->enabled ? 'ld-cell-enabled' : 'ld-cell-disabled'; ?>"><?php echo $c->enabled ? '✓' : '—'; ?></span></td>
              <td>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'edit', 'id' => $c->id]))->out(); ?>" class="ld-btn ld-btn-sm"><?php echo get_string('edit'); ?></a>
                <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'id' => $c->id, 'sesskey' => sesskey()]))->out(); ?>"
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
