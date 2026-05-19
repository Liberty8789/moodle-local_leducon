<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Organisation analytics — KPIs and drill-down by org unit.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\org\org_manager;
use local_leducon\org\org_helper;

require_login();
$context = context_system::instance();
require_capability('local/leducon:viewanalytics', $context);

if (!org_helper::is_available()) {
    redirect(new moodle_url('/local/leducon/index.php'));
}

$orgunitid = optional_param('orgunitid', 0, PARAM_INT);
$filters   = local_leducon_get_filter_params();
$self_url  = new moodle_url('/local/leducon/analytics/org_report.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url($self_url, ['orgunitid' => $orgunitid]));
$PAGE->set_title(get_string('org_report_title', 'local_leducon'));
$PAGE->set_heading(get_string('org_report_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('org_report_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'org_report', $USER->id);
echo $OUTPUT->heading(get_string('org_report_title', 'local_leducon'));
echo html_writer::tag('p', get_string('org_report_subtitle', 'local_leducon'), ['class' => 'text-muted']);

// ── Org unit selector ──
$unitopts = org_helper::get_org_dropdown_options(true);
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex align-items-end gap-3']);
echo html_writer::start_div('form-group mb-0 flex-grow-1');
echo html_writer::tag('label', s(get_string('org_select_unit', 'local_leducon')));
echo html_writer::select($unitopts, 'orgunitid', $orgunitid, false,
    ['class' => 'ld-input ld-select', 'onchange' => 'this.form.submit()']);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

if ($orgunitid <= 0) {
    echo $OUTPUT->notification(get_string('org_report_nounit', 'local_leducon'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$unit = org_manager::get_unit($orgunitid);
if (!$unit) {
    echo $OUTPUT->notification(get_string('org_report_nodata', 'local_leducon'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// ── Breadcrumb ──
$ancestors = org_manager::get_ancestors($orgunitid);
$crumblinks = [];
foreach ($ancestors as $a) {
    if ((int)$a->id === $orgunitid) {
        $crumblinks[] = '<strong>' . s($a->name) . '</strong>';
    } else {
        $url = new moodle_url($self_url, ['orgunitid' => $a->id]);
        $crumblinks[] = html_writer::link($url, s($a->name));
    }
}
$alllink = html_writer::link(new moodle_url($self_url), get_string('org_filter_all', 'local_leducon'));
echo '<div class="ld-org-breadcrumb mb-3">' . $alllink . ' &rsaquo; ' . implode(' &rsaquo; ', $crumblinks) . '</div>';

// ── Calculate KPIs ──
// Get all user IDs in this org unit (including descendants).
$descendant_ids = org_manager::get_descendant_ids($orgunitid);
if (empty($descendant_ids)) {
    $descendant_ids = [$orgunitid];
}
list($ousql, $ouparams) = $DB->get_in_or_equal($descendant_ids, SQL_PARAMS_NAMED, 'ou');
$memberids = $DB->get_fieldset_sql(
    "SELECT DISTINCT om.userid
       FROM {local_leducon_org_members} om
      WHERE om.orgunitid {$ousql}",
    $ouparams
);

$total_members = count($memberids);

if ($total_members === 0) {
    echo $OUTPUT->notification(get_string('org_report_nodata', 'local_leducon'), 'info');
    echo $OUTPUT->footer();
    exit;
}

list($usql, $uparams) = $DB->get_in_or_equal($memberids, SQL_PARAMS_NAMED, 'u');

// Completions.
$completions = (int)$DB->count_records_sql(
    "SELECT COUNT(*)
       FROM {course_completions} cc
  LEFT JOIN {grade_items} gi_or ON gi_or.courseid = cc.course AND gi_or.itemtype = 'course'
  LEFT JOIN {grade_grades} gg_or ON gg_or.itemid = gi_or.id AND gg_or.userid = cc.userid
      WHERE cc.userid {$usql}
        AND (cc.timecompleted IS NOT NULL
            OR (gg_or.finalgrade IS NOT NULL AND gi_or.grademax > 0 AND gg_or.finalgrade / gi_or.grademax * 100 >= 100))",
    $uparams
);

// Average grade.
$avggrade = (float)$DB->get_field_sql(
    "SELECT AVG(gg.finalgrade)
       FROM {grade_grades} gg
       JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
      WHERE gg.userid {$usql} AND gg.finalgrade IS NOT NULL",
    $uparams
);

// Active users (accessed in last 7 days) — single query instead of log scan.
$weekago = time() - 7 * DAYSECS;
$uparams2 = $uparams;
$uparams2['weekago'] = $weekago;
$active = (int)$DB->count_records_sql(
    "SELECT COUNT(*)
       FROM {user}
      WHERE id {$usql} AND lastaccess > :weekago",
    $uparams2
);

// At-risk (not accessed for 14+ days) — single batch query instead of N queries.
$atrisk_days = (int)(get_config('local_leducon', 'atrisk_inactive_days') ?: 14);
$atrisk_ts = time() - $atrisk_days * DAYSECS;
$uparams3 = $uparams;
$uparams3['atrisk_ts'] = $atrisk_ts;
$atrisk = (int)$DB->count_records_sql(
    "SELECT COUNT(*)
       FROM {user}
      WHERE id {$usql} AND (lastaccess < :atrisk_ts OR lastaccess = 0 OR lastaccess IS NULL)",
    $uparams3
);

// ── KPI Strip ──
echo '<div class="ld-org-kpi-strip">';
$kpis = [
    ['value' => $total_members, 'label' => get_string('org_report_members', 'local_leducon')],
    ['value' => $completions, 'label' => get_string('org_report_completions', 'local_leducon')],
    ['value' => round($avggrade, 1) . '%', 'label' => get_string('org_report_avggrade', 'local_leducon')],
    ['value' => $active, 'label' => get_string('org_report_active', 'local_leducon')],
    ['value' => $atrisk, 'label' => get_string('org_report_atrisk', 'local_leducon')],
];
foreach ($kpis as $kpi) {
    echo '<div class="ld-org-kpi">';
    echo '<div class="ld-org-kpi-value">' . $kpi['value'] . '</div>';
    echo '<div class="ld-org-kpi-label">' . s($kpi['label']) . '</div>';
    echo '</div>';
}
echo '</div>';

// ── Children Breakdown Table ──
// Uses batch method: 4-5 queries total instead of N*4 (one per child).
$children_data = org_manager::get_children_report_data($orgunitid);
if (!empty($children_data)) {
    echo html_writer::tag('h4', get_string('org_report_children', 'local_leducon'), ['class' => 'mt-4 mb-3']);
    echo html_writer::tag('p', get_string('org_report_drilldown', 'local_leducon'), ['class' => 'text-muted small']);

    echo '<div class="ld-org-compare"><div class="ld-card"><div class="ld-card-body-flush">';
    echo '<div class="table-responsive">';
    echo '<table class="ld-table table table-sm table-hover mb-0">';
    echo '<thead><tr>';
    echo '<th>' . get_string('org_unit_name', 'local_leducon') . '</th>';
    echo '<th style="text-align:center">' . get_string('org_report_members', 'local_leducon') . '</th>';
    echo '<th style="text-align:center">' . get_string('org_report_completions', 'local_leducon') . '</th>';
    echo '<th style="text-align:center">' . get_string('org_report_avggrade', 'local_leducon') . '</th>';
    echo '<th style="text-align:center">' . get_string('org_report_active', 'local_leducon') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($children_data as $cd) {
        $drillurl = new moodle_url($self_url, ['orgunitid' => $cd['id']]);
        echo '<tr onclick="window.location=\'' . $drillurl->out(false) . '\'" style="cursor:pointer">';
        echo '<td><strong>' . s(format_string($cd['name'])) . '</strong></td>';
        echo '<td style="text-align:center">' . (int)$cd['member_count'] . '</td>';
        echo '<td style="text-align:center">' . (int)$cd['completions'] . '</td>';
        echo '<td style="text-align:center">' . round((float)$cd['avggrade'], 1) . '%</td>';
        echo '<td style="text-align:center">' . (int)$cd['active'] . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</div></div></div>';
}

echo $OUTPUT->footer();
