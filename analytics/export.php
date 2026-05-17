<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Export handler — streams report data as CSV or Excel.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/leducon:viewanalytics', $context);

$reporttype = required_param('reporttype', PARAM_ALPHANUMEXT);
$format     = required_param('format',     PARAM_ALPHA);
$datefrom   = optional_param('datefrom',   0,  PARAM_INT);
$dateto     = optional_param('dateto',     0,  PARAM_INT);
$courseid   = optional_param('courseid',   0,  PARAM_INT);
$categoryid = optional_param('categoryid', 0,  PARAM_INT);
$cohortid   = optional_param('cohortid',   0,  PARAM_INT);

if (!in_array($reporttype, array_keys(local_leducon_get_report_types()), true)) {
    throw new moodle_exception('error_invalidreport', 'local_leducon');
}
if (!in_array($format, ['csv', 'excel'], true)) {
    throw new moodle_exception('error_invalidformat', 'local_leducon');
}

$report = local_leducon_get_report($reporttype);
$report->set_filters([
    'datefrom'   => $datefrom,
    'dateto'     => $dateto,
    'courseid'   => $courseid,
    'categoryid' => $categoryid,
    'cohortid'   => $cohortid,
]);

$columns = $report->get_columns();
$data    = $report->get_data();

$maxrows = (int)get_config('local_leducon', 'maxexportrows') ?: 5000;
if (count($data) > $maxrows) {
    $data = array_slice($data, 0, $maxrows);
}

$filename = clean_filename($reporttype . '_' . date('Ymd_His'));
$headers  = array_map(function($c) { return $c['label']; }, array_values($columns));
$colkeys  = array_keys($columns);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($data as $row) {
        $line = [];
        foreach ($colkeys as $key) { $line[] = $row[$key] ?? ''; }
        fputcsv($out, $line);
    }
    fclose($out);
    die();
}

// Excel.
$xlslib = $CFG->libdir . '/excellib.class.php';
if (file_exists($xlslib)) {
    require_once($xlslib);
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename . '.xlsx');
    $sheet = $workbook->add_worksheet($report->get_name());
    $fmt   = $workbook->add_format(['bold' => 1]);
    foreach ($headers as $ci => $header) {
        $sheet->write_string(0, $ci, $header, $fmt);
    }
    $ri = 1;
    foreach ($data as $row) {
        foreach ($colkeys as $ci => $key) {
            $val = $row[$key] ?? '';
            if (is_numeric($val)) {
                $sheet->write_number($ri, $ci, $val);
            } else {
                $sheet->write_string($ri, $ci, (string)$val);
            }
        }
        $ri++;
    }
    $workbook->close();
    die();
}

// Fallback CSV.
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $headers);
foreach ($data as $row) {
    $line = [];
    foreach ($colkeys as $key) { $line[] = $row[$key] ?? ''; }
    fputcsv($out, $line);
}
fclose($out);
die();
