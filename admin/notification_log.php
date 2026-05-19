<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Notification log viewer for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_leducon_notification_log');

$PAGE->set_url(new moodle_url('/local/leducon/admin/notification_log.php'));
$PAGE->set_title(get_string('notiflog_title', 'local_leducon'));
$PAGE->set_heading(get_string('notiflog_title', 'local_leducon'));

// Filters.
$filtertype   = optional_param('type', '', PARAM_ALPHA);
$filterstatus = optional_param('status', '', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = 50;

// Build WHERE.
$where = [];
$params = [];
if ($filtertype !== '') {
    $where[] = 'messagetype = :mtype';
    $params['mtype'] = $filtertype;
}
if ($filterstatus !== '') {
    $where[] = 'status = :mstatus';
    $params['mstatus'] = $filterstatus;
}
$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $DB->count_records_sql("SELECT COUNT(*) FROM {local_leducon_notif_log} $wheresql", $params);
$logs = $DB->get_records_sql(
    "SELECT * FROM {local_leducon_notif_log} $wheresql ORDER BY timecreated DESC",
    $params, $page * $perpage, $perpage
);

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('notiflog_title', 'local_leducon'));
echo html_writer::tag('p', get_string('notiflog_desc', 'local_leducon'), ['class' => 'text-muted']);

// Filter bar.
$baseurl = new moodle_url('/local/leducon/admin/notification_log.php');
echo '<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap">';
echo '<form method="get" action="' . $baseurl->out_omit_querystring() . '" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';

$types = ['', 'insight_alert', 'atrisk_notification', 'compliance_reminder', 'general'];
echo '<select name="type" class="form-control" style="width:auto">';
echo '<option value="">All types</option>';
foreach ($types as $t) {
    if ($t === '') continue;
    $sel = ($filtertype === $t) ? ' selected' : '';
    echo '<option value="' . s($t) . '"' . $sel . '>' . s($t) . '</option>';
}
echo '</select>';

echo '<select name="status" class="form-control" style="width:auto">';
echo '<option value="">All statuses</option>';
foreach (['sent', 'failed'] as $st) {
    $sel = ($filterstatus === $st) ? ' selected' : '';
    echo '<option value="' . s($st) . '"' . $sel . '>' . ucfirst($st) . '</option>';
}
echo '</select>';

echo '<button type="submit" class="btn btn-primary btn-sm">Filter</button>';
echo '<a href="' . $baseurl->out() . '" class="btn btn-secondary btn-sm">Clear</a>';
echo '</form>';

echo '<span class="text-muted" style="margin-left:auto">' . $total . ' total</span>';
echo '</div>';

// Table.
if (empty($logs)) {
    echo html_writer::tag('p', get_string('notiflog_empty', 'local_leducon'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = ['Time', 'Type', 'Channel', 'Recipient', 'Subject', 'Status'];
    $table->attributes['class'] = 'table table-striped table-sm';

    foreach ($logs as $log) {
        $statusclass = $log->status === 'sent' ? 'badge badge-success' : 'badge badge-danger';
        $table->data[] = [
            userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            html_writer::tag('span', s($log->messagetype), ['class' => 'badge badge-info']),
            s($log->channel),
            $log->recipientname ? s($log->recipientname) . '<br><small class="text-muted">' . s($log->recipientemail) . '</small>'
                : s($log->recipientemail),
            s(substr($log->subject, 0, 80)) . (strlen($log->subject) > 80 ? '...' : ''),
            html_writer::tag('span', ucfirst($log->status), ['class' => $statusclass]),
        ];
    }
    echo html_writer::table($table);

    // Paging.
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($baseurl, [
        'type' => $filtertype, 'status' => $filterstatus,
    ]));
}

echo $OUTPUT->footer();
