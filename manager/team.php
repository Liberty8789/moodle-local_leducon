<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Unified team view — analytics + gamification data for manager's cohort members.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewteam', $ctx);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/leducon/manager/team.php'));
$PAGE->set_title(get_string('nav_myteam', 'local_leducon'));
$PAGE->set_heading(get_string('nav_myteam', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('nav_myteam', 'local_leducon'));

$managerid  = $USER->id;
$cohortid   = optional_param('cohortid', 0, PARAM_INT);
$tab        = optional_param('tab', 'gamify', PARAM_ALPHA);

// Cohorts this manager is assigned to.
$my_cohorts = $DB->get_records_sql(
    "SELECT c.id, c.name
       FROM {cohort} c
       JOIN {local_leducon_team_managers} m ON m.cohortid = c.id
      WHERE m.userid = :uid
   ORDER BY c.name ASC",
    ['uid' => $managerid]
);

if (empty($my_cohorts)) {
    echo $OUTPUT->header();
    local_leducon_render_nav('manager', 'myteam', $USER->id);
    echo $OUTPUT->notification(get_string('no_cohorts_assigned', 'local_leducon'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Default to first cohort if none selected or selected not in list.
$cohort_ids = array_keys($my_cohorts);
if (!$cohortid || !in_array($cohortid, $cohort_ids)) {
    $cohortid = $cohort_ids[0];
}

$fullname_sql = $DB->sql_fullname('u.firstname', 'u.lastname');

echo $OUTPUT->header();
local_leducon_render_nav('manager', 'myteam', $USER->id);

// Companion links to analytics views.
if (has_capability('local/leducon:viewmanagerview', $ctx)) {
    echo html_writer::start_div('ld-companion-bar');
    echo '📊 ' . get_string('companion_analytics_team', 'local_leducon') . ' ';
    echo html_writer::link(
        new moodle_url('/local/leducon/analytics/managerview.php',
            $cohortid ? ['cohortid' => $cohortid] : []),
        get_string('companion_analytics_label', 'local_leducon')
    );
    echo html_writer::end_div();
}
?>

<!-- Cohort selector tabs -->
<div class="ld-tabs">
  <?php foreach ($my_cohorts as $co): ?>
  <a href="?cohortid=<?php echo (int)$co->id; ?>&tab=<?php echo s($tab); ?>"
     class="ld-tab-link <?php echo $cohortid == $co->id ? 'active' : ''; ?>">
    <?php echo s(format_string($co->name)); ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Tab toggle: Gamify / Analytics -->
<div class="ld-tabs mb-3">
  <a href="?cohortid=<?php echo $cohortid; ?>&tab=gamify"
     class="ld-tab-link <?php echo $tab === 'gamify' ? 'active' : ''; ?>">
    <?php echo get_string('tab_gamification', 'local_leducon'); ?>
  </a>
  <a href="?cohortid=<?php echo $cohortid; ?>&tab=analytics"
     class="ld-tab-link <?php echo $tab === 'analytics' ? 'active' : ''; ?>">
    <?php echo get_string('tab_analytics', 'local_leducon'); ?>
  </a>
</div>

<?php
if ($tab === 'gamify') {
    // Gamification team view.
    $sql = "SELECT u.id AS userid,
                   {$fullname_sql} AS fullname,
                   u.email,
                   COALESCE(gu.total_xp, 0) AS total_xp,
                   COALESCE(gu.streak_days, 0) AS streak_days,
                   lvl.name AS levelname,
                   lvl.levelnum
              FROM {cohort_members} cm
              JOIN {user} u ON u.id = cm.userid
         LEFT JOIN {local_leducon_xp_users} gu  ON gu.userid = u.id
         LEFT JOIN {local_leducon_levels} lvl ON lvl.id = gu.levelid
             WHERE cm.cohortid = :cid AND u.deleted = 0 AND u.suspended = 0
          ORDER BY COALESCE(gu.total_xp, 0) DESC, u.lastname ASC, u.firstname ASC";
    $members = $DB->get_records_sql($sql, ['cid' => $cohortid]);

    if ($members):
        // Chart: Top 10 team members by XP.
        $top10 = array_slice($members, 0, 10);
        $team_xp_labels = [];
        $team_xp_values = [];
        foreach ($top10 as $tm) {
            $team_xp_labels[] = $tm->fullname;
            $team_xp_values[] = (int)$tm->total_xp;
        }
    ?>
    <div class="ld-card ld-chart-card" style="margin-bottom:1.5rem">
      <div class="ld-card-header">
        <h3 class="ld-card-title"><?php echo get_string('chart_team_xp', 'local_leducon'); ?></h3>
      </div>
      <div class="ld-card-body ld-chart-body">
      <?php
      if (array_sum($team_xp_values) > 0) {
          try {
              $team_chart = new \core\chart_bar();
              $team_chart->set_horizontal(true);
              $txs = new \core\chart_series(get_string('lb_col_xp', 'local_leducon'), $team_xp_values);
              $txs->set_color('#7c3aed');
              $team_chart->add_series($txs);
              $team_chart->set_labels($team_xp_labels);
              echo $OUTPUT->render($team_chart);
          } catch (\Throwable $e) {
              echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
          }
      } else {
          echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
      }
      ?>
      </div>
    </div>

    <div class="ld-card">
      <table class="ld-table">
        <thead>
          <tr>
            <th>#</th>
            <th><?php echo get_string('lb_col_name', 'local_leducon'); ?></th>
            <th><?php echo get_string('lb_col_level', 'local_leducon'); ?></th>
            <th><?php echo get_string('lb_col_xp', 'local_leducon'); ?></th>
            <th><?php echo get_string('streak_day_label', 'local_leducon'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $rank = 1; foreach ($members as $m): ?>
          <tr>
            <td><?php echo $rank++; ?></td>
            <td><?php echo s($m->fullname); ?></td>
            <td><?php echo $m->levelname
                  ? local_leducon_level_badge((int)$m->levelnum, $m->levelname)
                  : '—'; ?></td>
            <td><?php echo local_leducon_fmt_xp((int)$m->total_xp); ?></td>
            <td>🔥 <?php echo (int)$m->streak_days; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted"><?php echo get_string('team_empty', 'local_leducon'); ?></p>
    <?php endif;

} else {
    // Analytics team view — course completion + grades for team members.
    $sql = "SELECT u.id AS userid,
                   {$fullname_sql} AS fullname,
                   u.email,
                   u.lastaccess,
                   COUNT(DISTINCT cc.course) AS completions,
                   AVG(CASE WHEN gg.finalgrade IS NOT NULL AND gi.grademax > 0
                        THEN gg.finalgrade / gi.grademax * 100 ELSE NULL END) AS avggrade
              FROM {cohort_members} cm
              JOIN {user} u ON u.id = cm.userid
         LEFT JOIN {course_completions} cc ON cc.userid = u.id
                   AND (cc.timecompleted IS NOT NULL
                       OR EXISTS (SELECT 1 FROM {grade_items} gi_t JOIN {grade_grades} gg_t ON gg_t.itemid = gi_t.id
                                  WHERE gi_t.courseid = cc.course AND gi_t.itemtype = 'course'
                                    AND gg_t.userid = u.id AND gi_t.grademax > 0 AND gg_t.finalgrade / gi_t.grademax * 100 >= 100))
         LEFT JOIN {grade_grades} gg ON gg.userid = u.id AND gg.finalgrade IS NOT NULL
         LEFT JOIN {grade_items} gi  ON gi.id = gg.itemid AND gi.itemtype = 'course' AND gi.grademax > 0
             WHERE cm.cohortid = :cid AND u.deleted = 0 AND u.suspended = 0
          GROUP BY u.id, {$fullname_sql}, u.email, u.lastaccess
          ORDER BY u.lastname ASC, u.firstname ASC";
    $members = $DB->get_records_sql($sql, ['cid' => $cohortid]);

    if ($members):
        // Chart: Team completion & grade overview.
        $team_a_labels = [];
        $team_a_comp = [];
        $team_a_grade = [];
        $cnt = 0;
        foreach ($members as $tm) {
            if ($cnt >= 10) break;
            $team_a_labels[] = $tm->fullname;
            $team_a_comp[]   = (int)$tm->completions;
            $team_a_grade[]  = $tm->avggrade !== null ? round((float)$tm->avggrade, 1) : 0;
            $cnt++;
        }
    ?>
    <div class="ld-card ld-chart-card" style="margin-bottom:1.5rem">
      <div class="ld-card-header">
        <h3 class="ld-card-title"><?php echo get_string('chart_team_performance', 'local_leducon'); ?></h3>
      </div>
      <div class="ld-card-body ld-chart-body">
      <?php
      if (array_sum($team_a_comp) > 0 || array_sum($team_a_grade) > 0) {
          try {
              $perf_chart = new \core\chart_bar();
              $perf_chart->set_horizontal(true);
              $pc1 = new \core\chart_series(get_string('overview_col_completed', 'local_leducon'), $team_a_comp);
              $pc1->set_color('#16a34a');
              $pc2 = new \core\chart_series(get_string('kpi_avg_grade', 'local_leducon'), $team_a_grade);
              $pc2->set_color('#2563eb');
              $perf_chart->add_series($pc1);
              $perf_chart->add_series($pc2);
              $perf_chart->set_labels($team_a_labels);
              echo $OUTPUT->render($perf_chart);
          } catch (\Throwable $e) {
              echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
          }
      } else {
          echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
      }
      ?>
      </div>
    </div>

    <div class="ld-card">
      <table class="ld-table">
        <thead>
          <tr>
            <th><?php echo get_string('lb_col_name', 'local_leducon'); ?></th>
            <th><?php echo get_string('overview_col_completed', 'local_leducon'); ?></th>
            <th><?php echo get_string('kpi_avg_grade', 'local_leducon'); ?></th>
            <th><?php echo get_string('col_lastaccess', 'local_leducon'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m): ?>
          <tr>
            <td><?php echo s($m->fullname); ?></td>
            <td><?php echo (int)$m->completions; ?></td>
            <td><?php echo $m->avggrade !== null ? number_format((float)$m->avggrade, 1) . '%' : '—'; ?></td>
            <td><?php echo $m->lastaccess > 0 ? userdate($m->lastaccess) : get_string('never', 'local_leducon'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted"><?php echo get_string('team_empty', 'local_leducon'); ?></p>
    <?php endif;
}

echo $OUTPUT->footer();
