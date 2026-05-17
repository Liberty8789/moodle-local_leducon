<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Award spotlights — managers send recognition with XP bonus to team members.
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
require_capability('local/leducon:awardspotlight', $ctx);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/manager/spotlight.php'));
$PAGE->set_title(get_string('nav_spotlight', 'local_leducon'));
$PAGE->set_heading(get_string('nav_spotlight', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_spotlight', 'local_leducon'));

$managerid   = $USER->id;
$daily_limit = (int)(get_config('local_leducon', 'spotlight_daily_limit') ?: 3);
$self_url    = new moodle_url('/local/leducon/manager/spotlight.php');

// Count how many spotlights this manager has sent today.
$daystart = mktime(0, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
$sent_today = $DB->count_records_select(
    'local_leducon_recognition',
    'senderid = :uid AND is_spotlight = 1 AND timecreated >= :ds',
    ['uid' => $managerid, 'ds' => $daystart]
);

// Submit spotlight.
if (optional_param('action', '', PARAM_ALPHA) === 'award' && confirm_sesskey()) {
    if ($sent_today >= $daily_limit) {
        redirect($self_url, get_string('spotlight_limit_reached', 'local_leducon'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $recipientid = required_param('recipientid', PARAM_INT);
    $message     = required_param('message', PARAM_TEXT);

    if (!$DB->record_exists('user', ['id' => $recipientid, 'deleted' => 0])) {
        redirect($self_url, get_string('invalid_user', 'local_leducon'), null,
            \core\output\notification::NOTIFY_WARNING);
    }
    if ($recipientid === $managerid) {
        redirect($self_url, get_string('spotlight_self_error', 'local_leducon'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $xp = (int)(get_config('local_leducon', 'xp_spotlight_received') ?: 75);
    $DB->insert_record('local_leducon_recognition', (object)[
        'senderid'     => $managerid,
        'recipientid'  => $recipientid,
        'message'      => $message,
        'is_spotlight' => 1,
        'xp_awarded'   => $xp,
        'visible'      => 1,
        'timecreated'  => time(),
    ]);
    // Award XP to recipient.
    xp_engine::award($recipientid, 'spotlight_received', $managerid,
        get_string('spotlight_from', 'local_leducon', fullname($USER)));

    redirect($self_url, get_string('spotlight_sent', 'local_leducon'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Team members across all assigned cohorts.
$cohort_ids = $DB->get_fieldset_select(
    'local_leducon_team_managers', 'cohortid',
    'userid = :uid', ['uid' => $managerid]
);

$team_members = [];
if ($cohort_ids) {
    [$in_sql, $params] = $DB->get_in_or_equal($cohort_ids, SQL_PARAMS_NAMED);
    $fullname_sql = $DB->sql_fullname('u.firstname', 'u.lastname');
    $team_members = $DB->get_records_sql(
        "SELECT DISTINCT u.id, {$fullname_sql} AS fullname
           FROM {cohort_members} cm
           JOIN {user} u ON u.id = cm.userid
          WHERE cm.cohortid {$in_sql} AND u.deleted = 0 AND u.suspended = 0
            AND u.id != :mgr
       ORDER BY u.lastname ASC, u.firstname ASC",
        array_merge($params, ['mgr' => $managerid])
    );
}

// Recent spotlights sent by this manager.
$fullname_recip = $DB->sql_fullname('r.firstname', 'r.lastname');
$recent = $DB->get_records_sql(
    "SELECT rec.id, rec.message, rec.timecreated, {$fullname_recip} AS recipient_name
       FROM {local_leducon_recognition} rec
       JOIN {user} r ON r.id = rec.recipientid
      WHERE rec.senderid = :uid AND rec.is_spotlight = 1
   ORDER BY rec.timecreated DESC",
    ['uid' => $managerid],
    0, 10
);

echo $OUTPUT->header();
local_leducon_render_nav('manager', 'spotlight', $USER->id);
?>

<div class="ld-card">
  <h3><?php echo get_string('award_spotlight', 'local_leducon'); ?></h3>
  <p class="text-muted">
    <?php echo get_string('spotlight_remaining', 'local_leducon',
        max(0, $daily_limit - $sent_today) . ' / ' . $daily_limit); ?>
  </p>

  <?php if ($sent_today < $daily_limit): ?>
  <form method="post">
    <input type="hidden" name="action"  value="award">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <div class="ld-form-row">
      <label><?php echo get_string('spotlight_recipient', 'local_leducon'); ?></label>
      <select name="recipientid" required class="ld-select">
        <option value=""><?php echo get_string('select_team_member', 'local_leducon'); ?></option>
        <?php foreach ($team_members as $m): ?>
          <option value="<?php echo (int)$m->id; ?>"><?php echo s($m->fullname); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ld-form-row">
      <label><?php echo get_string('spotlight_message', 'local_leducon'); ?></label>
      <textarea name="message" required maxlength="500" rows="4" class="ld-input ld-textarea"
                placeholder="<?php echo get_string('spotlight_message_placeholder', 'local_leducon'); ?>"></textarea>
    </div>
    <button type="submit" class="ld-btn ld-btn-primary">
      ⭐ <?php echo get_string('send_spotlight', 'local_leducon'); ?>
    </button>
  </form>
  <?php else: ?>
    <p class="text-muted"><?php echo get_string('spotlight_limit_reached', 'local_leducon'); ?></p>
  <?php endif; ?>
</div>

<?php if ($recent): ?>
<div class="ld-card">
  <h3><?php echo get_string('recent_spotlights', 'local_leducon'); ?></h3>
  <?php foreach ($recent as $r): ?>
  <div class="ld-recog-item ld-recog-spotlight">
    <div class="ld-recog-header">
      <strong><?php echo s($r->recipient_name); ?></strong>
      <span class="text-muted"><?php echo userdate($r->timecreated); ?></span>
    </div>
    <div class="ld-recog-message"><?php echo s($r->message); ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php echo $OUTPUT->footer();
