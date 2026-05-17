<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Recognition wall — public feed of peer recognitions and spotlights.
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
$PAGE->set_url(new moodle_url('/local/leducon/gamify/recognition.php'));
$PAGE->set_title(get_string('nav_recognition', 'local_leducon'));
$PAGE->set_heading(get_string('nav_recognition', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_recognition', 'local_leducon'));

$page    = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$fullname_sender    = $DB->sql_fullname('s.firstname', 's.lastname');
$fullname_recipient = $DB->sql_fullname('r.firstname', 'r.lastname');

$sql = "SELECT rec.id, rec.message, rec.is_spotlight, rec.xp_awarded, rec.timecreated,
               {$fullname_sender}    AS sender_name,
               {$fullname_recipient} AS recipient_name
          FROM {local_leducon_recognition} rec
          JOIN {user} s ON s.id = rec.senderid
          JOIN {user} r ON r.id = rec.recipientid
         WHERE rec.visible = 1
      ORDER BY rec.timecreated DESC";

$total = $DB->count_records_select(
    'local_leducon_recognition', 'visible = 1'
);
$records = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'recognition', $USER->id);

if ($records):
?>
<div class="ld-recog-feed">
  <?php foreach ($records as $rec): ?>
  <div class="ld-recog-item <?php echo $rec->is_spotlight ? 'ld-recog-spotlight' : ''; ?>">
    <div class="ld-recog-header">
      <?php if ($rec->is_spotlight): ?>
        <span class="ld-badge ld-badge-gold">⭐ <?php echo get_string('spotlight', 'local_leducon'); ?></span>
      <?php endif; ?>
      <span class="ld-recog-from">
        <?php echo s($rec->sender_name); ?> →
        <strong><?php echo s($rec->recipient_name); ?></strong>
      </span>
      <span class="ld-recog-time text-muted"><?php echo userdate($rec->timecreated); ?></span>
    </div>
    <?php if ($rec->message): ?>
      <div class="ld-recog-message"><?php echo s($rec->message); ?></div>
    <?php endif; ?>
    <?php if ((int)$rec->xp_awarded > 0): ?>
      <div class="ld-recog-xp">+<?php echo number_format((int)$rec->xp_awarded); ?> XP</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php echo $OUTPUT->paging_bar($total, $page, $perpage,
    new moodle_url('/local/leducon/gamify/recognition.php')); ?>

<?php else: ?>
  <p class="text-muted"><?php echo get_string('no_recognition', 'local_leducon'); ?></p>
<?php endif; ?>

<?php echo $OUTPUT->footer();
