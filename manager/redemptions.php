<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Approve/reject/fulfill reward redemptions for team members.
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
require_capability('local/leducon:approveredemptions', $ctx);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/manager/redemptions.php'));
$PAGE->set_title(get_string('nav_redemptions', 'local_leducon'));
$PAGE->set_heading(get_string('nav_redemptions', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_redemptions', 'local_leducon'));

$managerid = $USER->id;
$self_url  = new moodle_url('/local/leducon/manager/redemptions.php');

// Manager's cohort members.
$cohort_ids = $DB->get_fieldset_select(
    'local_leducon_team_managers', 'cohortid',
    'userid = :uid', ['uid' => $managerid]
);
$member_ids = [];
if ($cohort_ids) {
    [$in_sql, $params] = $DB->get_in_or_equal($cohort_ids, SQL_PARAMS_NAMED);
    $member_ids = $DB->get_fieldset_sql(
        "SELECT DISTINCT userid FROM {cohort_members} WHERE cohortid {$in_sql}",
        $params
    );
}

// Process action.
$action = optional_param('action', '', PARAM_ALPHA);
$rid    = optional_param('rid', 0, PARAM_INT);

if ($action && confirm_sesskey() && $rid && in_array($action, ['approve', 'reject', 'fulfill'])) {
    $redemption = $DB->get_record('local_leducon_redemptions', ['id' => $rid], '*', MUST_EXIST);

    // Manager can only act on their own team members.
    if (!empty($member_ids) && !in_array((int)$redemption->userid, $member_ids)) {
        redirect($self_url, get_string('access_denied', 'local_leducon'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $note = optional_param('manager_note', '', PARAM_TEXT);

    switch ($action) {
        case 'approve':
            $DB->update_record('local_leducon_redemptions', (object)[
                'id'           => $rid,
                'status'       => 'approved',
                'approvedby'   => $managerid,
                'manager_note' => $note,
                'timemodified' => time(),
            ]);
            redirect($self_url, get_string('redemption_approved', 'local_leducon'), null,
                \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'reject':
            // Refund XP.
            xp_engine::update_user_total((int)$redemption->userid, (int)$redemption->cost_xp);
            $DB->insert_record('local_leducon_xp_log', (object)[
                'userid'      => $redemption->userid,
                'points'      => (int)$redemption->cost_xp,
                'eventtype'   => 'redemption_refund',
                'contextid'   => null,
                'note'        => 'Refund for rejected redemption #' . $rid,
                'timecreated' => time(),
            ]);
            $DB->update_record('local_leducon_redemptions', (object)[
                'id'           => $rid,
                'status'       => 'rejected',
                'approvedby'   => $managerid,
                'manager_note' => $note,
                'timemodified' => time(),
            ]);
            redirect($self_url, get_string('redemption_rejected', 'local_leducon'), null,
                \core\output\notification::NOTIFY_SUCCESS);
            break;

        case 'fulfill':
            $DB->update_record('local_leducon_redemptions', (object)[
                'id'           => $rid,
                'status'       => 'fulfilled',
                'manager_note' => $note,
                'timemodified' => time(),
            ]);
            redirect($self_url, get_string('redemption_fulfilled', 'local_leducon'), null,
                \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

// Load redemptions for this manager's team.
$tab = optional_param('tab', 'pending', PARAM_ALPHA);
$allowed_statuses = ['pending', 'approved', 'fulfilled', 'rejected'];
if (!in_array($tab, $allowed_statuses)) {
    $tab = 'pending';
}

$fullname_sql = $DB->sql_fullname('u.firstname', 'u.lastname');
$where_user   = '';
$params_user  = ['status' => $tab];

if (!empty($member_ids)) {
    [$in_sql, $uid_params] = $DB->get_in_or_equal($member_ids, SQL_PARAMS_NAMED);
    $where_user  = "AND rd.userid {$in_sql}";
    $params_user = array_merge($params_user, $uid_params);
} else {
    // No team members — show nothing.
    $where_user  = 'AND 1 = 0';
}

$sql = "SELECT rd.id, rd.userid, rd.cost_xp, rd.status, rd.manager_note, rd.timecreated,
               {$fullname_sql} AS fullname,
               rw.name AS reward_name
          FROM {local_leducon_redemptions} rd
          JOIN {user} u  ON u.id  = rd.userid
          JOIN {local_leducon_rewards} rw ON rw.id = rd.rewardid
         WHERE rd.status = :status {$where_user}
      ORDER BY rd.timecreated DESC";
$redemptions = $DB->get_records_sql($sql, $params_user);

echo $OUTPUT->header();
local_leducon_render_nav('manager', 'redemptions', $USER->id);
?>

<div class="ld-tabs">
  <?php foreach ($allowed_statuses as $s): ?>
  <a href="?tab=<?php echo $s; ?>"
     class="ld-tab-link <?php echo $tab === $s ? 'active' : ''; ?>">
    <?php echo get_string('redemption_status_' . $s, 'local_leducon'); ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($redemptions): ?>
<div class="ld-card">
  <table class="ld-table">
    <thead>
      <tr>
        <th><?php echo get_string('lb_col_name', 'local_leducon'); ?></th>
        <th><?php echo get_string('reward_name', 'local_leducon'); ?></th>
        <th><?php echo get_string('reward_cost_xp', 'local_leducon'); ?></th>
        <th><?php echo get_string('timecreated', 'local_leducon'); ?></th>
        <?php if ($tab === 'pending' || $tab === 'approved'): ?>
          <th><?php echo get_string('actions', 'local_leducon'); ?></th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($redemptions as $rd): ?>
      <tr>
        <td><?php echo s($rd->fullname); ?></td>
        <td><?php echo s(format_string($rd->reward_name)); ?></td>
        <td><?php echo number_format((int)$rd->cost_xp); ?> XP</td>
        <td><?php echo userdate($rd->timecreated); ?></td>
        <?php if ($tab === 'pending'): ?>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="action"  value="approve">
            <input type="hidden" name="rid"     value="<?php echo (int)$rd->id; ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button class="ld-btn ld-btn-sm ld-btn-success"><?php echo get_string('approve', 'local_leducon'); ?></button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="action"  value="reject">
            <input type="hidden" name="rid"     value="<?php echo (int)$rd->id; ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button class="ld-btn ld-btn-sm ld-btn-danger"><?php echo get_string('reject', 'local_leducon'); ?></button>
          </form>
        </td>
        <?php elseif ($tab === 'approved'): ?>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="action"  value="fulfill">
            <input type="hidden" name="rid"     value="<?php echo (int)$rd->id; ?>">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button class="ld-btn ld-btn-sm ld-btn-primary"><?php echo get_string('mark_fulfilled', 'local_leducon'); ?></button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
  <p class="text-muted"><?php echo get_string('no_redemptions', 'local_leducon'); ?></p>
<?php endif; ?>

<?php echo $OUTPUT->footer();
