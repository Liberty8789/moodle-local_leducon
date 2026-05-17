<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Export the learner report card as a printable PDF view or CSV download.
 *
 * ?userid=X&format=pdf|csv&datefrom=&dateto=
 *
 * PDF: renders a clean print-optimised HTML page and auto-triggers window.print().
 *      The user's browser saves it as PDF via the system print dialog.
 * CSV: streams a UTF-8 CSV file with all report card data.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('error_nologin', 'local_leducon');
}

$syscontext = context_system::instance();
require_capability('local/leducon:viewown', $syscontext);

$userid   = optional_param('userid',   $USER->id, PARAM_INT);
$format   = optional_param('format',   'pdf',     PARAM_ALPHA);
$datefrom = optional_param('datefrom', 0,         PARAM_INT);
$dateto   = optional_param('dateto',   0,         PARAM_INT);

if ($userid != $USER->id) {
    require_capability('local/leducon:viewanalytics', $syscontext);
}

$user   = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$dateto = $dateto > 0 ? $dateto : time();
$alltime = ($datefrom === 0);

// ------------------------------------------------------------------
// Shared data queries (same logic as myreport.php).
// ------------------------------------------------------------------
// Use MIN(ue.timecreated) and COUNT(DISTINCT ...) to prevent duplicate rows
// when a user has multiple enrolments in the same course.
$courserows = $DB->get_records_sql(
    "SELECT c.id, c.fullname, cc.name AS catname,
            MIN(ue.timecreated) AS timeenrolled,
            comp.timecompleted, comp.timestarted,
            gi.grademax, gg.finalgrade,
            COUNT(DISTINCT cm.id)                                                      AS totalactivities,
            COUNT(DISTINCT CASE WHEN cmc.completionstate > 0 THEN cmc.id ELSE NULL END) AS doneactivities,
            COUNT(DISTINCT cmall.id)                                                    AS totalmodules
       FROM {user_enrolments} ue
       JOIN {enrol} e              ON e.id = ue.enrolid
       JOIN {course} c             ON c.id = e.courseid AND c.id <> :siteid
       JOIN {course_categories} cc ON cc.id = c.category
  LEFT JOIN {course_completions} comp ON comp.course = c.id AND comp.userid = :uid1
  LEFT JOIN {grade_items} gi      ON gi.courseid = c.id AND gi.itemtype = 'course'
  LEFT JOIN {grade_grades} gg     ON gg.itemid = gi.id  AND gg.userid = :uid2
  LEFT JOIN {course_modules} cm   ON cm.course = c.id AND cm.visible = 1 AND cm.completion > 0
  LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :uid3
  LEFT JOIN {course_modules} cmall ON cmall.course = c.id AND cmall.visible = 1
      WHERE ue.userid = :uid4" .
        ($alltime ? '' : ' AND ue.timecreated BETWEEN :from AND :to') . "
   GROUP BY c.id, c.fullname, cc.name,
            comp.timecompleted, comp.timestarted, gi.grademax, gg.finalgrade
   ORDER BY MIN(ue.timecreated) DESC",
    array_merge(
        ['siteid' => SITEID, 'uid1' => $userid, 'uid2' => $userid, 'uid3' => $userid, 'uid4' => $userid],
        $alltime ? [] : ['from' => $datefrom, 'to' => $dateto]
    )
);

$quizrows = $DB->get_records_sql(
    "SELECT q.id, q.name AS quizname, c.fullname AS coursename,
            q.sumgrades,
            COUNT(qa.id)                                      AS attempts,
            MAX(qa.sumgrades / NULLIF(q.sumgrades,0) * 100)  AS bestgrade,
            MAX(qa.timefinish)                                AS lastattempt
       FROM {quiz} q
       JOIN {course} c         ON c.id = q.course
       JOIN {quiz_attempts} qa ON qa.quiz = q.id AND qa.userid = :uid AND qa.state = 'finished'" .
        ($alltime ? '' : ' AND qa.timefinish BETWEEN :from AND :to') . "
      WHERE c.id <> :siteid
   GROUP BY q.id, q.name, c.fullname, q.sumgrades
   ORDER BY lastattempt DESC",
    array_merge(
        ['uid' => $userid, 'siteid' => SITEID],
        $alltime ? [] : ['from' => $datefrom, 'to' => $dateto]
    )
);

$loginparams = ['uid' => $userid];
$loginwhere  = "userid = :uid AND action = 'loggedin'";
if (!$alltime) {
    $loginwhere .= ' AND timecreated BETWEEN :from AND :to';
    $loginparams['from'] = $datefrom;
    $loginparams['to']   = $dateto;
}
$loginrows = $DB->get_records_select('logstore_standard_log', $loginwhere, $loginparams,
    'timecreated DESC', 'id,timecreated,ip', 0, 20);

// ------------------------------------------------------------------
// Query 4 - SCORM activities the user is enrolled in.
// ------------------------------------------------------------------
$scormrows      = [];
$scormtrackdata = [];
try {
    $scormrows = $DB->get_records_sql(
        "SELECT s.id, s.name AS scormname, c.fullname AS coursename
           FROM {scorm} s
           JOIN {course} c           ON c.id = s.course AND c.id <> :siteid
           JOIN {enrol} e            ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :uid
          GROUP BY s.id, s.name, c.fullname
          ORDER BY c.fullname ASC, s.name ASC",
        ['siteid' => SITEID, 'uid' => $userid]
    );

    if (!empty($scormrows)) {
        $scormids = array_keys($scormrows);
        list($sinsql, $sinparams) = $DB->get_in_or_equal($scormids, SQL_PARAMS_NAMED, 'sct');
        $sinparams['suid'] = $userid;
        $trackrows = $DB->get_records_sql(
            "SELECT id, scormid, attempt, element, value
               FROM {scorm_scoes_track}
              WHERE scormid {$sinsql} AND userid = :suid
                AND element IN (
                    'cmi.core.lesson_status', 'cmi.completion_status',
                    'cmi.success_status', 'cmi.core.score.raw', 'cmi.score.raw'
                )",
            $sinparams
        );
        foreach ($trackrows as $tr) {
            if (!isset($scormtrackdata[$tr->scormid])) {
                $scormtrackdata[$tr->scormid] = ['attempts' => [], 'statuses' => [], 'scores' => []];
            }
            $scormtrackdata[$tr->scormid]['attempts'][$tr->attempt] = true;
            if (in_array($tr->element, ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'])) {
                $scormtrackdata[$tr->scormid]['statuses'][] = strtolower(trim($tr->value));
            }
            if (in_array($tr->element, ['cmi.core.score.raw', 'cmi.score.raw'])) {
                $val = (float) $tr->value;
                if ($val >= 0) {
                    $scormtrackdata[$tr->scormid]['scores'][] = $val;
                }
            }
        }
    }
} catch (\dml_exception $e) {
    $scormrows = [];
}

// ------------------------------------------------------------------
// Query 5 - Assignments in courses the user is enrolled in.
// ------------------------------------------------------------------
$assignrows = [];
try {
    $assignrows = $DB->get_records_sql(
        "SELECT a.id, a.name AS assignname, c.fullname AS coursename,
                a.duedate, a.grade AS maxgrade,
                MAX(asub.status)       AS substatus,
                MAX(asub.timemodified) AS subtime,
                MAX(ag.grade)          AS finalgrade
           FROM {assign} a
           JOIN {course} c              ON c.id = a.course AND c.id <> :siteid
           JOIN {enrol} e               ON e.courseid = c.id
           JOIN {user_enrolments} ue    ON ue.enrolid = e.id AND ue.userid = :uid
      LEFT JOIN {assign_submission} asub ON asub.assignment = a.id
                                        AND asub.userid = :uid2
                                        AND asub.status = 'submitted'
      LEFT JOIN {assign_grades} ag      ON ag.assignment = a.id AND ag.userid = :uid3
          GROUP BY a.id, a.name, c.fullname, a.duedate, a.grade
          ORDER BY c.fullname ASC, a.duedate ASC",
        ['siteid' => SITEID, 'uid' => $userid, 'uid2' => $userid, 'uid3' => $userid]
    );
} catch (\dml_exception $e) {
    $assignrows = [];
}

// ------------------------------------------------------------------
// CSV EXPORT.
// ------------------------------------------------------------------
if ($format === 'csv') {
    $filename = clean_filename('reportcard_' . $user->username . '_' . date('Ymd_His')) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM.

    // User info.
    fputcsv($out, ['Report Card', fullname($user), $user->email, date('Y-m-d')]);
    fputcsv($out, []);

    // Courses.
    fputcsv($out, ['MY COURSES']);
    fputcsv($out, ['Course', 'Category', 'Status', 'Grade %', 'Activities Done', 'Total Activities', 'Enrolled On', 'Started On', 'Completed On']);
    foreach ($courserows as $cr) {
        $hasgrade  = ($cr->finalgrade !== null && (float)$cr->finalgrade > 0);
        $hasdone   = ((int)$cr->doneactivities > 0);
        $gradepct  = (!empty($cr->grademax) && $cr->finalgrade !== null)
            ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
        $trackable = (int)$cr->totalactivities;
        if (!empty($cr->timecompleted)) {
            $status = 'Completed';
        } elseif ($trackable === 0 && $gradepct >= 100) {
            $status = 'Completed';
        } elseif (!empty($cr->timestarted) || $hasdone || $hasgrade) {
            $status = 'In Progress';
        } else {
            $status = 'Not Started';
        }
        $grade = $gradepct > 0 ? number_format($gradepct, 1) : '';
        fputcsv($out, [
            $cr->fullname,
            $cr->catname,
            $status,
            $grade,
            (int)$cr->doneactivities,
            (int)$cr->totalactivities,
            $cr->timeenrolled  ? date('Y-m-d', $cr->timeenrolled)  : '',
            !empty($cr->timestarted) ? date('Y-m-d', $cr->timestarted) : '',
            $cr->timecompleted ? date('Y-m-d', $cr->timecompleted) : '',
        ]);
    }
    fputcsv($out, []);

    // Quizzes.
    fputcsv($out, ['MY QUIZ RESULTS']);
    fputcsv($out, ['Quiz', 'Course', 'Attempts', 'Best Grade %', 'Last Attempt']);
    foreach ($quizrows as $qr) {
        fputcsv($out, [
            $qr->quizname,
            $qr->coursename,
            (int)$qr->attempts,
            $qr->bestgrade !== null ? number_format((float)$qr->bestgrade, 1) : '',
            $qr->lastattempt ? date('Y-m-d H:i', $qr->lastattempt) : '',
        ]);
    }
    fputcsv($out, []);

    // SCORM Activities.
    if (!empty($scormrows)) {
        fputcsv($out, ['MY SCORM ACTIVITIES']);
        fputcsv($out, ['Activity', 'Course', 'Status', 'Best Score', 'Attempts']);
        foreach ($scormrows as $sr) {
            $td = isset($scormtrackdata[$sr->id]) ? $scormtrackdata[$sr->id] : null;
            $numatt = $td ? count($td['attempts']) : 0;
            $statuslabel = 'Not Attempted';
            if ($td && !empty($td['statuses'])) {
                $statuses = $td['statuses'];
                if (in_array('passed', $statuses)) {
                    $statuslabel = 'Passed';
                } elseif (in_array('completed', $statuses)) {
                    $statuslabel = 'Completed';
                } elseif (in_array('failed', $statuses)) {
                    $statuslabel = 'Failed';
                } elseif (in_array('incomplete', $statuses) || $numatt > 0) {
                    $statuslabel = 'In Progress';
                }
            }
            $score = ($td && !empty($td['scores'])) ? number_format(max($td['scores']), 1) : '';
            fputcsv($out, [$sr->scormname, $sr->coursename, $statuslabel, $score, $numatt]);
        }
        fputcsv($out, []);
    }

    // Assignments.
    if (!empty($assignrows)) {
        fputcsv($out, ['MY ASSIGNMENTS']);
        fputcsv($out, ['Assignment', 'Course', 'Due Date', 'Status', 'Grade']);
        foreach ($assignrows as $ar) {
            if (!empty($ar->substatus)) {
                $islate = ($ar->duedate > 0 && $ar->subtime > $ar->duedate);
                $substatus = $islate ? 'Late' : 'Submitted';
            } else {
                $substatus = 'Not Submitted';
            }
            $gradecell = '';
            if (!empty($ar->substatus) && $ar->finalgrade !== null && (float)$ar->finalgrade >= 0) {
                if (!empty($ar->maxgrade) && $ar->maxgrade > 0) {
                    $gradecell = number_format((float)$ar->finalgrade / (float)$ar->maxgrade * 100, 1);
                } else {
                    $gradecell = 'Graded';
                }
            }
            fputcsv($out, [
                $ar->assignname,
                $ar->coursename,
                $ar->duedate ? date('Y-m-d', $ar->duedate) : '',
                $substatus,
                $gradecell,
            ]);
        }
        fputcsv($out, []);
    }

    // Logins (last 20).
    fputcsv($out, ['RECENT LOGINS (Last 20)']);
    fputcsv($out, ['Login Time', 'IP Address']);
    foreach ($loginrows as $lr) {
        fputcsv($out, [date('Y-m-d H:i:s', $lr->timecreated), $lr->ip]);
    }

    fclose($out);
    die();
}

// ------------------------------------------------------------------
// PDF PRINT VIEW - clean standalone HTML page, auto-triggers print.
// ------------------------------------------------------------------
$fullname  = fullname($user);
$rangelabel = $alltime ? 'All time'
    : (date('d M Y', $datefrom) . ' - ' . date('d M Y', $dateto));
$generated = date('d M Y, H:i');

// Compute summary — match myreport.php status logic exactly.
$completed = 0; $inprog = 0; $gsum = 0.0; $gcnt = 0;
foreach ($courserows as $cr) {
    $hasgrade  = ($cr->finalgrade !== null && (float)$cr->finalgrade > 0);
    $hasdone   = ((int)$cr->doneactivities > 0);
    $hasstarted = !empty($cr->timestarted);
    $gpct      = (!empty($cr->grademax) && $cr->finalgrade !== null)
        ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
    $trackable = (int)$cr->totalactivities;
    if (!empty($cr->timecompleted)) {
        $completed++;
    } elseif ($trackable === 0 && $gpct >= 100) {
        $completed++;
    } elseif ($hasstarted || $hasdone || $hasgrade) {
        $inprog++;
    }
    if (!empty($cr->grademax) && $cr->finalgrade !== null) {
        $gsum += ($cr->finalgrade / $cr->grademax) * 100;
        $gcnt++;
    }
}
$avg = $gcnt > 0 ? number_format($gsum / $gcnt, 1) . '%' : '-';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Report Card - <?php echo htmlspecialchars($fullname, ENT_QUOTES); ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;font-size:11pt;color:#222;padding:20px}
  h1{font-size:16pt;margin-bottom:4px}
  h2{font-size:12pt;margin:18px 0 6px;border-bottom:2px solid #1a73e8;padding-bottom:3px;color:#1a73e8}
  .meta{color:#666;font-size:9pt;margin-bottom:16px}
  .stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .stat{border:1px solid #ddd;border-radius:6px;padding:8px 14px;text-align:center;min-width:100px}
  .stat-val{font-size:18pt;font-weight:bold;color:#1a73e8}
  .stat-lbl{font-size:8pt;color:#666}
  table{width:100%;border-collapse:collapse;margin-bottom:14px;font-size:9.5pt}
  th{background:#1a73e8;color:#fff;padding:5px 7px;text-align:left}
  td{padding:4px 7px;border-bottom:1px solid #eee}
  tr:nth-child(even) td{background:#f9f9f9}
  .badge{padding:2px 7px;border-radius:10px;font-size:8pt;font-weight:bold;color:#fff}
  .g-success{background:#28a745}.g-warning{background:#ffc107;color:#333}
  .g-secondary{background:#6c757d}.g-pass{background:#28a745}.g-fail{background:#dc3545}
  .range{font-size:9pt;color:#555;margin-bottom:14px}
  .noprint{display:none}
  @media screen{
    .noprint{display:block;margin-bottom:16px}
    .noprint button{padding:8px 18px;background:#1a73e8;color:#fff;border:none;
                    border-radius:4px;font-size:11pt;cursor:pointer}
  }
  @media print{.noprint{display:none!important}}
</style>
</head>
<body onload="window.print()">

<div class="noprint">
  <button onclick="window.print()">&#128438; Print / Save as PDF</button>
  &nbsp;
  <a href="<?php echo (new moodle_url('/local/leducon/analytics/myreport.php',
      ['userid' => $userid, 'datefrom' => $datefrom, 'dateto' => $dateto]))->out(false); ?>"
     style="color:#1a73e8">← Back to Report Card</a>
</div>

<h1><?php echo htmlspecialchars($fullname, ENT_QUOTES); ?> - Report Card</h1>
<div class="meta">
  <?php echo htmlspecialchars($user->email, ENT_QUOTES); ?> &nbsp;|&nbsp;
  Generated: <?php echo htmlspecialchars($generated, ENT_QUOTES); ?>
</div>
<div class="range">Period: <strong><?php echo htmlspecialchars($rangelabel, ENT_QUOTES); ?></strong></div>

<h2>Overview</h2>
<div class="stats">
  <?php
  foreach ([
      ['Enrolled',   count($courserows)],
      ['Completed',  $completed],
      ['In Progress',$inprog],
      ['Avg Grade',  $avg],
      ['Logins',     count($loginrows)],
  ] as [$lbl, $val]) {
      echo '<div class="stat"><div class="stat-val">' . htmlspecialchars((string)$val, ENT_QUOTES) . '</div>'
         . '<div class="stat-lbl">' . htmlspecialchars($lbl, ENT_QUOTES) . '</div></div>';
  }
  ?>
</div>

<h2>My Courses</h2>
<?php if (empty($courserows)): ?>
  <p>No courses found for this period.</p>
<?php else: ?>
<table>
  <thead><tr><th>Course</th><th>Status</th><th>Grade</th><th>Activities</th><th>Enrolled</th><th>Started</th><th>Completed</th></tr></thead>
  <tbody>
  <?php foreach ($courserows as $cr):
      $hasgrade  = ($cr->finalgrade !== null && (float)$cr->finalgrade > 0);
      $hasdone   = ((int)$cr->doneactivities > 0);
      $hasstarted = !empty($cr->timestarted);
      $gpct      = (!empty($cr->grademax) && $cr->finalgrade !== null)
          ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
      $trackable = (int)$cr->totalactivities;
      if (!empty($cr->timecompleted)) {
          [$sl,$sc] = ['Completed',  'g-success'];
      } elseif ($trackable === 0 && $gpct >= 100) {
          [$sl,$sc] = ['Completed',  'g-success'];
      } elseif ($hasstarted || $hasdone || $hasgrade) {
          [$sl,$sc] = ['In Progress','g-warning'];
      } else {
          [$sl,$sc] = ['Not Started','g-secondary'];
      }
      $g = $gpct > 0 ? number_format($gpct, 1) . '%' : '-';
      $tot  = (int)$cr->totalactivities;
      $done = (int)$cr->doneactivities;
      $allmods = (int)$cr->totalmodules;
  ?>
  <tr>
    <td><?php echo htmlspecialchars($cr->fullname, ENT_QUOTES); ?></td>
    <td><span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
    <td><?php echo htmlspecialchars($g, ENT_QUOTES); ?></td>
    <td><?php echo $tot > 0 ? "{$done}/{$tot}" : ($allmods > 0 && $gpct > 0 ? number_format($gpct, 0) . '% grade' : '-'); ?></td>
    <td><?php echo $cr->timeenrolled ? date('d M Y', $cr->timeenrolled) : '-'; ?></td>
    <td><?php echo !empty($cr->timestarted) ? date('d M Y', $cr->timestarted) : '-'; ?></td>
    <td><?php echo $cr->timecompleted ? date('d M Y', $cr->timecompleted) : '-'; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>My Quiz Results</h2>
<?php if (empty($quizrows)): ?>
  <p>No quiz attempts found for this period.</p>
<?php else: ?>
<table>
  <thead><tr><th>Quiz</th><th>Course</th><th>Attempts</th><th>Best Grade</th><th>Result</th><th>Last Attempt</th></tr></thead>
  <tbody>
  <?php
      $passthreshold = (float) get_config('local_leducon', 'kpi_pass_green') ?: 70;
      foreach ($quizrows as $qr):
      $best = $qr->bestgrade !== null ? number_format((float)$qr->bestgrade, 1) . '%' : '-';
      $pass = $qr->bestgrade !== null ? ((float)$qr->bestgrade >= $passthreshold ? 'Pass' : 'Fail') : '-';
      $pc   = $qr->bestgrade !== null ? ((float)$qr->bestgrade >= $passthreshold ? 'g-pass' : 'g-fail') : '';
  ?>
  <tr>
    <td><?php echo htmlspecialchars($qr->quizname,   ENT_QUOTES); ?></td>
    <td><?php echo htmlspecialchars($qr->coursename, ENT_QUOTES); ?></td>
    <td><?php echo (int)$qr->attempts; ?></td>
    <td><?php echo htmlspecialchars($best, ENT_QUOTES); ?></td>
    <td><?php if ($pc): ?><span class="badge <?php echo $pc; ?>"><?php echo $pass; ?></span><?php else: echo '-'; endif; ?></td>
    <td><?php echo $qr->lastattempt ? date('d M Y H:i', $qr->lastattempt) : '-'; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (!empty($scormrows)): ?>
<h2>SCORM Activities</h2>
<table>
  <thead><tr><th>Activity</th><th>Course</th><th>Status</th><th>Best Score</th><th>Attempts</th></tr></thead>
  <tbody>
  <?php foreach ($scormrows as $sr):
      $td = isset($scormtrackdata[$sr->id]) ? $scormtrackdata[$sr->id] : null;
      $numatt = $td ? count($td['attempts']) : 0;
      $statuslabel = 'Not Attempted'; $statusclass = 'g-secondary';
      if ($td && !empty($td['statuses'])) {
          $statuses = $td['statuses'];
          if (in_array('passed', $statuses)) {
              $statuslabel = 'Passed'; $statusclass = 'g-success';
          } elseif (in_array('completed', $statuses)) {
              $statuslabel = 'Completed'; $statusclass = 'g-success';
          } elseif (in_array('failed', $statuses)) {
              $statuslabel = 'Failed'; $statusclass = 'g-fail';
          } elseif (in_array('incomplete', $statuses) || $numatt > 0) {
              $statuslabel = 'In Progress'; $statusclass = 'g-warning';
          }
      }
      $score = ($td && !empty($td['scores'])) ? number_format(max($td['scores']), 1) : '-';
  ?>
  <tr>
    <td><?php echo htmlspecialchars($sr->scormname, ENT_QUOTES); ?></td>
    <td><?php echo htmlspecialchars($sr->coursename, ENT_QUOTES); ?></td>
    <td><span class="badge <?php echo $statusclass; ?>"><?php echo $statuslabel; ?></span></td>
    <td><?php echo htmlspecialchars($score, ENT_QUOTES); ?></td>
    <td><?php echo $numatt; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (!empty($assignrows)): ?>
<h2>Assignments</h2>
<table>
  <thead><tr><th>Assignment</th><th>Course</th><th>Due Date</th><th>Status</th><th>Grade</th></tr></thead>
  <tbody>
  <?php foreach ($assignrows as $ar):
      if (!empty($ar->substatus)) {
          $islate = ($ar->duedate > 0 && $ar->subtime > $ar->duedate);
          if ($islate) {
              $sublabel = 'Late'; $subclass = 'g-warning';
          } else {
              $sublabel = 'Submitted'; $subclass = 'g-success';
          }
      } else {
          $sublabel = 'Not Submitted'; $subclass = 'g-secondary';
      }
      $gradecell = '-';
      if (!empty($ar->substatus) && $ar->finalgrade !== null && (float)$ar->finalgrade >= 0) {
          if (!empty($ar->maxgrade) && $ar->maxgrade > 0) {
              $gradecell = number_format((float)$ar->finalgrade / (float)$ar->maxgrade * 100, 1) . '%';
          } else {
              $gradecell = 'Graded';
          }
      }
  ?>
  <tr>
    <td><?php echo htmlspecialchars($ar->assignname, ENT_QUOTES); ?></td>
    <td><?php echo htmlspecialchars($ar->coursename, ENT_QUOTES); ?></td>
    <td><?php echo $ar->duedate ? date('d M Y', $ar->duedate) : '-'; ?></td>
    <td><span class="badge <?php echo $subclass; ?>"><?php echo $sublabel; ?></span></td>
    <td><?php echo htmlspecialchars($gradecell, ENT_QUOTES); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Recent Logins (Last 20)</h2>
<?php if (empty($loginrows)): ?>
  <p>No logins recorded for this period.</p>
<?php else: ?>
<table>
  <thead><tr><th>#</th><th>Login Time</th><th>IP Address</th></tr></thead>
  <tbody>
  <?php $i = 1; foreach ($loginrows as $lr): ?>
  <tr>
    <td><?php echo $i++; ?></td>
    <td><?php echo date('d M Y H:i:s', $lr->timecreated); ?></td>
    <td><?php echo htmlspecialchars($lr->ip, ENT_QUOTES); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

</body>
</html>
<?php
die();
