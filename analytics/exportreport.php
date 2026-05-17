<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Export any report as print-ready PDF HTML or CSV/Excel.
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
$format     = optional_param('format',     'pdf', PARAM_ALPHA);
$datefrom   = optional_param('datefrom',   0,     PARAM_INT);
$dateto     = optional_param('dateto',     0,     PARAM_INT);
$courseid   = optional_param('courseid',   0,     PARAM_INT);
$categoryid = optional_param('categoryid', 0,     PARAM_INT);
$cohortid   = optional_param('cohortid',   0,     PARAM_INT);

$validtypes = array_keys(local_leducon_get_report_types());
if (!in_array($reporttype, $validtypes, true)) {
    throw new moodle_exception('error_invalidreport', 'local_leducon');
}

$report = local_leducon_get_report($reporttype);
$filters = ['datefrom' => $datefrom, 'dateto' => $dateto, 'courseid' => $courseid, 'categoryid' => $categoryid, 'cohortid' => $cohortid];
$report->set_filters($filters);
$data    = $report->get_data();
$columns = $report->get_columns();

$maxrows = (int)get_config('local_leducon', 'maxexportrows') ?: 5000;
if (count($data) > $maxrows) {
    $data = array_slice($data, 0, $maxrows);
}

$filename = clean_filename($reporttype . '_' . date('Ymd_His'));
$colkeys  = array_keys($columns);
$headers  = array_map(function($c) { return $c['label']; }, array_values($columns));

// CSV/Excel.
if ($format === 'csv' || $format === 'excel') {
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($data as $row) {
            $line = [];
            foreach ($colkeys as $key) { $line[] = $row[$key] ?? ''; }
            fputcsv($out, $line);
        }
        fclose($out);
    } else {
        $xlslib = $CFG->libdir . '/excellib.class.php';
        if (file_exists($xlslib)) {
            require_once($xlslib);
            $workbook = new MoodleExcelWorkbook('-');
            $workbook->send($filename . '.xlsx');
            $sheet = $workbook->add_worksheet($report->get_name());
            $fmt = $workbook->add_format(['bold' => 1]);
            foreach ($headers as $ci => $h) { $sheet->write_string(0, $ci, $h, $fmt); }
            $ri = 1;
            foreach ($data as $row) {
                foreach ($colkeys as $ci => $key) {
                    $val = $row[$key] ?? '';
                    is_numeric($val) ? $sheet->write_number($ri, $ci, $val) : $sheet->write_string($ri, $ci, (string)$val);
                }
                $ri++;
            }
            $workbook->close();
        } else {
            // Fallback: excellib not available, output CSV instead.
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($data as $row) {
                $line = [];
                foreach ($colkeys as $key) { $line[] = $row[$key] ?? ''; }
                fputcsv($out, $line);
            }
            fclose($out);
        }
    }
    die();
}

// PDF — standalone print-ready HTML.
$title   = $report->get_name();
$summary = $report->get_summary();
$gendate = userdate(time());
$fromstr = $datefrom > 0 ? userdate($datefrom, get_string('strftimedate', 'langconfig')) : 'All time';
$tostr   = userdate($dateto > 0 ? $dateto : time(), get_string('strftimedate', 'langconfig'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo s($title); ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; margin: 20px; }
  h1 { font-size: 16pt; margin-bottom: 4px; }
  .meta { font-size: 9pt; color: #666; margin-bottom: 16px; }
  .summary { display: flex; gap: 20px; margin-bottom: 16px; flex-wrap: wrap; }
  .summary-item { background: #f5f5f5; border-left: 3px solid #2563eb; padding: 8px 12px; border-radius: 2px; }
  .summary-item strong { display: block; font-size: 14pt; color: #2563eb; }
  .summary-item span   { font-size: 8pt; color: #555; }
  table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 10px; }
  th { background: #2563eb; color: #fff; padding: 6px 8px; text-align: left; font-weight: bold; white-space: nowrap; }
  td { padding: 4px 8px; border-bottom: 1px solid #e0e0e0; }
  tr:nth-child(even) td { background: #f9f9f9; }
  @page { size: A4 landscape; margin: 15mm; }
  @media print { .no-print { display: none; } body { margin: 0; font-size: 9pt; } }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:12px">
  <button onclick="window.print()" style="padding:6px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:10pt;">Print / Save as PDF</button>
  <button onclick="window.close()" style="margin-left:8px;padding:6px 16px;background:#888;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:10pt;">Close</button>
</div>
<h1><?php echo s($title); ?></h1>
<div class="meta">Generated: <?php echo s($gendate); ?> | Period: <?php echo s($fromstr); ?> &ndash; <?php echo s($tostr); ?> | Rows: <?php echo count($data); ?></div>
<?php if (!empty($summary)): ?>
<div class="summary">
  <?php foreach ($summary as $item): ?>
  <div class="summary-item"><strong><?php echo s((string)$item['value']); ?></strong><span><?php echo s($item['label']); ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (empty($data)): ?>
<p><?php echo s(get_string('nodata', 'local_leducon')); ?></p>
<?php else: ?>
<table>
  <thead><tr><?php foreach ($headers as $h): ?><th><?php echo s($h); ?></th><?php endforeach; ?></tr></thead>
  <tbody>
    <?php foreach ($data as $row): ?>
    <tr><?php foreach ($colkeys as $key): ?><td><?php echo s((string)($row[$key] ?? '')); ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<script>if(window.location.search.indexOf('autoprint=1')!==-1){window.onload=function(){window.print();}}</script>
</body>
</html>
<?php die();
