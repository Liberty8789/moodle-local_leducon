<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Recognition moderation — hide/show/delete recognition entries.
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
$PAGE->set_url(new moodle_url('/local/leducon/admin/recognitions.php'));
$PAGE->set_title(get_string('nav_admin_recognitions', 'local_leducon'));
$PAGE->set_heading(get_string('nav_admin_recognitions', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_admin_recognitions', 'local_leducon'));

$action   = optional_param('action', '', PARAM_ALPHA);
$recid    = optional_param('id', 0, PARAM_INT);
$self_url = new moodle_url('/local/leducon/admin/recognitions.php');

if ($action === 'hide' && confirm_sesskey() && $recid) {
    $DB->set_field('local_leducon_recognition', 'visible', 0, ['id' => $recid]);
    redirect($self_url, get_string('recognition_hidden', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'show' && confirm_sesskey() && $recid) {
    $DB->set_field('local_leducon_recognition', 'visible', 1, ['id' => $recid]);
    redirect($self_url);
}
if ($action === 'delete' && confirm_sesskey() && $recid) {
    $DB->delete_records('local_leducon_recognition', ['id' => $recid]);
    redirect($self_url, get_string('deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$page    = optional_param('page', 0, PARAM_INT);
$perpage = (int)(get_config('local_leducon', 'listperpage') ?: 50);
$total   = $DB->count_records('local_leducon_recognition');
$fullname_sender    = $DB->sql_fullname('s.firstname', 's.lastname');
$fullname_recipient = $DB->sql_fullname('r.firstname', 'r.lastname');

$sql = "SELECT rec.id, rec.message, rec.is_spotlight, rec.visible, rec.xp_awarded, rec.timecreated,
               {$fullname_sender}    AS sender_name,
               {$fullname_recipient} AS recipient_name
          FROM {local_leducon_recognition} rec
          JOIN {user} s ON s.id = rec.senderid
          JOIN {user} r ON r.id = rec.recipientid
      ORDER BY rec.timecreated DESC";
$records = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'recognitions', $USER->id);
echo $OUTPUT->heading(get_string('nav_admin_recognitions', 'local_leducon'));
?>

<div class="ld-card">
  <table class="ld-table">
    <thead>
      <tr>
        <th><?php echo get_string('recog_sender', 'local_leducon'); ?></th>
        <th><?php echo get_string('recog_recipient', 'local_leducon'); ?></th>
        <th><?php echo get_string('recog_message', 'local_leducon'); ?></th>
        <th><?php echo get_string('spotlight', 'local_leducon'); ?></th>
        <th><?php echo get_string('visible', 'local_leducon'); ?></th>
        <th><?php echo get_string('timecreated', 'local_leducon'); ?></th>
        <th><?php echo get_string('actions', 'local_leducon'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $rec): ?>
      <tr class="<?php echo !$rec->visible ? 'ld-row-hidden' : ''; ?>">
        <td><?php echo s($rec->sender_name); ?></td>
        <td><?php echo s($rec->recipient_name); ?></td>
        <td><?php echo s($rec->message); ?></td>
        <td><?php echo $rec->is_spotlight ? '⭐' : ''; ?></td>
        <td><?php echo $rec->visible ? '✓' : '—'; ?></td>
        <td><?php echo userdate($rec->timecreated); ?></td>
        <td>
          <?php if ($rec->visible): ?>
            <a href="<?php echo (new moodle_url($self_url, ['action' => 'hide', 'id' => $rec->id, 'sesskey' => sesskey()]))->out(); ?>"
               class="ld-btn ld-btn-sm"><?php echo get_string('hide', 'local_leducon'); ?></a>
          <?php else: ?>
            <a href="<?php echo (new moodle_url($self_url, ['action' => 'show', 'id' => $rec->id, 'sesskey' => sesskey()]))->out(); ?>"
               class="ld-btn ld-btn-sm"><?php echo get_string('show', 'local_leducon'); ?></a>
          <?php endif; ?>
          <a href="<?php echo (new moodle_url($self_url, ['action' => 'delete', 'id' => $rec->id, 'sesskey' => sesskey()]))->out(); ?>"
             class="ld-btn ld-btn-sm ld-btn-danger"
             onclick="return confirm('<?php echo get_string('confirm_delete', 'local_leducon'); ?>');">
            <?php echo get_string('delete'); ?>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php echo $OUTPUT->paging_bar($total, $page, $perpage, $self_url); ?>

<?php echo $OUTPUT->footer();
