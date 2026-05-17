<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * ILT / VILT session data upload and management page.
 *
 * Admins upload CSV files containing attendance records for face-to-face
 * (ILT) and virtual (VILT) training sessions. Files are stored in
 * Moodle's file API -- no custom database table required. The ILT report
 * in report.php reads and merges all uploaded files at query time.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();

$syscontext = context_system::instance();
require_capability('local/leducon:viewanalytics', $syscontext);

// ------------------------------------------------------------------
// Template CSV download -- must happen before any output.
// ------------------------------------------------------------------
if (optional_param('downloadtemplate', 0, PARAM_INT)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ilt_upload_template.csv"');
    $rows = [
        ['date', 'title', 'type', 'facilitator', 'department', 'attendee_name', 'attendee_email', 'duration_minutes', 'outcome'],
        ['2024-03-15', 'Annual Compliance Workshop', 'ILT',  'John Smith',   'Human Resources', 'Jane Doe',    'jane.doe@company.com',    '90',  'Attended'],
        ['2024-03-15', 'Annual Compliance Workshop', 'ILT',  'John Smith',   'Human Resources', 'Bob Brown',   'bob.brown@company.com',   '90',  'No-show'],
        ['2024-04-10', 'Leadership Bootcamp',        'VILT', 'Sarah Jones',  'Management',      'Alice Green', 'alice.green@company.com', '120', 'Pass'],
        ['2024-04-10', 'Leadership Bootcamp',        'VILT', 'Sarah Jones',  'Management',      'Tom White',   'tom.white@company.com',   '120', 'Pass'],
        ['2024-05-22', 'Safety Induction',           'ILT',  'Mark Davis',   'Operations',      'Mike Black',  'mike.black@company.com',  '60',  'Attended'],
        ['2024-06-05', 'Data Privacy Training',      'VILT', 'Linda Carter', 'IT',              'Eve Adams',   'eve.adams@company.com',   '45',  'Attended'],
    ];
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$pageurl = new moodle_url('/local/leducon/analytics/iltupload.php');
$fs      = get_file_storage();
$ctx     = context_system::instance();

// ------------------------------------------------------------------
// Handle delete action (POST).
// ------------------------------------------------------------------
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'delete') {
    require_sesskey();
    $filehash = required_param('filehash', PARAM_ALPHANUM);
    $f = $fs->get_file_by_hash($filehash);
    if ($f
        && $f->get_component() === 'local_leducon'
        && $f->get_filearea()  === 'iltdata'
        && $f->get_contextid() === $ctx->id
    ) {
        $f->delete();
    }
    redirect($pageurl, get_string('ilt_deleted', 'local_leducon'));
}

// ------------------------------------------------------------------
// Handle file upload (POST).
// ------------------------------------------------------------------
$uploaderror = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
    require_sesskey();
    $uploaded = $_FILES['csvfile'];

    if ($uploaded['error'] !== UPLOAD_ERR_OK || empty($uploaded['tmp_name'])) {
        $uploaderror = get_string('ilt_error_upload', 'local_leducon');
    } else {
        $origname = clean_filename(basename($uploaded['name']));

        if (!preg_match('/\.csv$/i', $origname)) {
            $uploaderror = get_string('ilt_error_notcsv', 'local_leducon');
        } else {
            // Validate required column headers before storing.
            $handle    = fopen($uploaded['tmp_name'], 'r');
            $headerrow = $handle ? fgetcsv($handle) : false;
            if ($handle) {
                fclose($handle);
            }
            if ($headerrow) {
                $foundheaders = array_map('strtolower', array_map('trim', $headerrow));
                $required     = ['date', 'title', 'type', 'attendee_name'];
                $missing      = array_diff($required, $foundheaders);
                if (!empty($missing)) {
                    $uploaderror = get_string('ilt_error_missingcols', 'local_leducon', implode(', ', $missing));
                }
            }

            if (!$uploaderror) {
                $filename = date('Ymd_His') . '_' . $origname;
                $fileinfo = [
                    'contextid' => $ctx->id,
                    'component' => 'local_leducon',
                    'filearea'  => 'iltdata',
                    'itemid'    => (int) (microtime(true) * 1000),
                    'filepath'  => '/',
                    'filename'  => $filename,
                ];
                $fs->create_file_from_pathname($fileinfo, $uploaded['tmp_name']);
                redirect($pageurl, get_string('ilt_uploaded', 'local_leducon'));
            }
        }
    }
}

// ------------------------------------------------------------------
// Page setup.
// ------------------------------------------------------------------
$PAGE->set_context($syscontext);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('ilt_title', 'local_leducon'));
$PAGE->set_heading(get_string('ilt_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('ilt_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'ilt', $USER->id);
echo $OUTPUT->heading(get_string('ilt_title', 'local_leducon'));

if ($uploaderror) {
    echo $OUTPUT->notification($uploaderror, 'error');
}

// ------------------------------------------------------------------
// Action bar: download template + view report.
// ------------------------------------------------------------------
$templateurl = new moodle_url('/local/leducon/analytics/iltupload.php', ['downloadtemplate' => 1]);
$reporturl   = new moodle_url('/local/leducon/analytics/report.php', ['reporttype' => 'ilt']);

echo html_writer::start_div('ld-action-bar');
echo html_writer::link($templateurl, get_string('ilt_download_template', 'local_leducon'),
    ['class' => 'ld-action-btn ld-action-primary']);
echo html_writer::link($reporturl, get_string('ilt_view_report', 'local_leducon'),
    ['class' => 'ld-action-btn ld-action-info']);
echo html_writer::end_div();

// ------------------------------------------------------------------
// Upload form.
// ------------------------------------------------------------------
echo html_writer::start_div('ld-filter-bar mb-4');
echo html_writer::tag('h5', get_string('ilt_upload_heading', 'local_leducon'), ['class' => 'mb-2']);
echo html_writer::tag('p',  get_string('ilt_upload_desc',    'local_leducon'), ['class' => 'text-muted small mb-3']);

echo html_writer::start_tag('form', [
    'method'  => 'post',
    'action'  => $pageurl->out(false),
    'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('form-group mb-2');
echo html_writer::tag('label', get_string('ilt_choose_file', 'local_leducon'), ['class' => 'd-block mb-1']);
echo html_writer::empty_tag('input', [
    'type'   => 'file',
    'name'   => 'csvfile',
    'accept' => '.csv',
    'class'  => 'ld-input',
]);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('ilt_upload_btn', 'local_leducon'),
    'class' => 'ld-btn ld-btn-sm ld-btn-primary',
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// ------------------------------------------------------------------
// Uploaded files table.
// ------------------------------------------------------------------
$allfiles = $fs->get_area_files($ctx->id, 'local_leducon', 'iltdata', false, 'timemodified DESC', false);

echo html_writer::start_div('d-flex align-items-center justify-content-between mb-3');
echo html_writer::tag('h5', get_string('ilt_files_heading', 'local_leducon'), ['class' => 'mb-0']);
if (!empty($allfiles)) {
    echo html_writer::tag('span',
        count($allfiles) . ' ' . get_string('ilt_files_count', 'local_leducon'),
        ['class' => 'text-muted small']);
}
echo html_writer::end_div();

if (empty($allfiles)) {
    echo $OUTPUT->notification(get_string('ilt_nofiles', 'local_leducon'), 'info');
} else {
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('ilt_col_filename', 'local_leducon'),
        get_string('ilt_col_uploaded', 'local_leducon'),
        get_string('ilt_col_size',     'local_leducon'),
        get_string('ilt_col_records',  'local_leducon'),
        get_string('ilt_col_actions',  'local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($allfiles as $f) {
        // Count data rows: lines minus the header, minus blank lines.
        $content = $f->get_content();
        $lines   = array_filter(
            explode("\n", str_replace(["\r\n", "\r"], "\n", $content)),
            'strlen'
        );
        $records = max(0, count($lines) - 1);

        // Delete is a POST form so sesskey stays out of server logs.
        $delform =
            html_writer::start_tag('form', [
                'method'   => 'post',
                'action'   => $pageurl->out(false),
                'style'    => 'display:inline',
                'onsubmit' => 'return confirm(' . json_encode(get_string('ilt_confirm_delete', 'local_leducon')) . ')',
            ]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',   'value' => 'delete']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filehash', 'value' => $f->get_pathnamehash()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',  'value' => sesskey()]) .
            html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('ilt_delete', 'local_leducon'),
                'class' => 'ld-btn ld-btn-sm ld-btn-danger',
            ]) .
            html_writer::end_tag('form');

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($f->get_filename()));
        echo html_writer::tag('td', s(userdate($f->get_timemodified(), get_string('strftimedatetimeshort', 'langconfig'))));
        echo html_writer::tag('td', display_size($f->get_filesize()));
        echo html_writer::tag('td', $records . ' ' . get_string('ilt_rows', 'local_leducon'));
        echo html_writer::tag('td', $delform);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
