<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Learning paths — shows all paths with course progress for the current user.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\path_manager;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/gamify/paths.php'));
$PAGE->set_title(get_string('nav_paths', 'local_leducon'));
$PAGE->set_heading(get_string('nav_paths', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_paths', 'local_leducon'));

$userid = $USER->id;
$paths  = path_manager::get_paths_for_user($userid);

// Load courses per path.
foreach ($paths as $path) {
    $path->courses = $DB->get_records_sql(
        "SELECT co.id, co.fullname, co.shortname,
                (SELECT MAX(cc2.timecompleted) FROM {course_completions} cc2
                  WHERE cc2.userid = :uid AND cc2.course = co.id AND cc2.timecompleted IS NOT NULL
                ) AS timecompleted
           FROM {course} co
           JOIN {local_leducon_path_courses} pc ON pc.courseid = co.id
          WHERE pc.pathid = :pid
       ORDER BY pc.sortorder ASC",
        ['uid' => $userid, 'pid' => $path->id]
    );
}

echo $OUTPUT->header();
local_leducon_render_nav('gamify', 'paths', $USER->id);

if ($paths):
?>
<div class="ld-path-list">
  <?php foreach ($paths as $path): ?>
  <div class="ld-path-card <?php echo $path->completed ? 'ld-path-done' : ''; ?>">
    <div class="ld-path-header">
      <span class="ld-path-name"><?php echo s(format_string($path->name)); ?></span>
      <?php if ($path->completed): ?>
        <span class="ld-badge ld-badge-success">✓ <?php echo get_string('path_completed', 'local_leducon'); ?></span>
      <?php endif; ?>
    </div>
    <?php if ($path->description): ?>
      <div class="ld-path-desc"><?php echo format_text($path->description, FORMAT_PLAIN); ?></div>
    <?php endif; ?>
    <div class="ld-progress" title="<?php echo (int)$path->progress; ?>%">
      <div class="ld-progress-bar" style="width:<?php echo (int)$path->progress; ?>%"></div>
    </div>
    <small class="text-muted"><?php echo (int)$path->progress; ?>% <?php echo get_string('complete', 'local_leducon'); ?></small>
    <?php if ((int)$path->xp_bonus > 0): ?>
      <div class="ld-path-bonus"><?php echo get_string('path_bonus', 'local_leducon',
          local_leducon_fmt_xp((int)$path->xp_bonus)); ?></div>
    <?php endif; ?>
    <ol class="ld-path-steps">
      <?php foreach ($path->courses as $co): ?>
        <li class="ld-path-step <?php echo $co->timecompleted ? 'ld-step-done' : ''; ?>">
          <?php echo $co->timecompleted ? '✓ ' : ''; ?>
          <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $co->id]))->out(); ?>">
            <?php echo s(format_string($co->fullname)); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p class="text-muted"><?php echo get_string('no_paths', 'local_leducon'); ?></p>
<?php endif; ?>

<?php echo $OUTPUT->footer();
