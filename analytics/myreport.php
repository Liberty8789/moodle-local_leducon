<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Learner personal report card with date-range filtering.
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

// Allow managers to preview any user via ?userid=X.
$userid = optional_param('userid', $USER->id, PARAM_INT);
if ($userid != $USER->id) {
    require_capability('local/leducon:viewanalytics', $syscontext);
}
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

// ------------------------------------------------------------------
// Date-range filter resolution.
// quickrange = 7|30|90|365|0(all-time).  Custom datefrom/dateto
// override quickrange when both are provided.
// ------------------------------------------------------------------
$quickrange = optional_param('quickrange', -1,  PARAM_INT);
$datefrom   = optional_param('datefrom',    0,  PARAM_INT);
$dateto     = optional_param('dateto',      0,  PARAM_INT);

// Convert HTML date inputs (yyyy-mm-dd strings) if needed.
$datefromraw = optional_param('datefromstr', '', PARAM_ALPHANUMEXT);
$datetoraw   = optional_param('datetostr',   '', PARAM_ALPHANUMEXT);
if ($datefromraw !== '') {
    $datefrom = (int) strtotime($datefromraw . ' 00:00:00');
}
if ($datetoraw !== '') {
    $dateto = (int) strtotime($datetoraw . ' 23:59:59');
}

// Apply quickrange if no custom dates were given.
if ($quickrange >= 0 && $datefrom === 0) {
    if ($quickrange === 0) {
        $datefrom = 0; // all time
    } else {
        $datefrom = (int) strtotime("-{$quickrange} days midnight");
    }
}

// Defaults.
if ($datefrom === 0 && $quickrange < 0) {
    $datefrom = (int) strtotime('-30 days midnight');
}
$dateto = ($dateto > 0) ? $dateto : time();

$alltime = ($datefrom === 0);

// ------------------------------------------------------------------
// Page setup.
// ------------------------------------------------------------------
$urlparams = ['userid' => $userid, 'datefrom' => $datefrom, 'dateto' => $dateto];
$pageurl   = new moodle_url('/local/leducon/analytics/myreport.php', $urlparams);

$PAGE->set_context($syscontext);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('myreport', 'local_leducon'));
$PAGE->set_heading(get_string('myreport', 'local_leducon'));
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string('myreport', 'local_leducon'));

// ------------------------------------------------------------------
// Helper: build SQL date condition and params.
// Returns ['', []] when alltime is active (no filter applied).
// ------------------------------------------------------------------
$datewherelogin  = $alltime ? '' : 'AND ls.timecreated  BETWEEN :lsfrom  AND :lsto';
$datewherequiz   = $alltime ? '' : 'AND qa.timefinish   BETWEEN :qafrom  AND :qato';

// ------------------------------------------------------------------
// Query 1 - Enrolled courses.
// Use MIN(ue.timecreated) and GROUP BY without ue.timecreated to
// prevent duplicate rows when a user has multiple enrolments in the
// same course (e.g. manual + self-enrolment).
// ------------------------------------------------------------------
$datewherecourse2 = $alltime ? '' : 'AND ue.timecreated BETWEEN :uefrom AND :ueto';
$courserows = $DB->get_records_sql(
    "SELECT c.id,
            c.fullname,
            cc.name                                                AS catname,
            MIN(ue.timecreated)                                    AS timeenrolled,
            comp.timecompleted,
            comp.timestarted,
            gi.grademax,
            gg.finalgrade,
            COUNT(DISTINCT cm.id)                                  AS totalactivities,
            COUNT(DISTINCT CASE WHEN cmc.completionstate > 0 THEN cmc.id ELSE NULL END) AS doneactivities,
            COUNT(DISTINCT cmall.id)                               AS totalmodules
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
      WHERE ue.userid = :uid4
            {$datewherecourse2}
   GROUP BY c.id, c.fullname, cc.name,
            comp.timecompleted, comp.timestarted, gi.grademax, gg.finalgrade
   ORDER BY MIN(ue.timecreated) DESC",
    array_merge(
        ['siteid' => SITEID, 'uid1' => $userid, 'uid2' => $userid, 'uid3' => $userid, 'uid4' => $userid],
        $alltime ? [] : ['uefrom' => $datefrom, 'ueto' => $dateto]
    )
);

// ------------------------------------------------------------------
// Query 2 - Quiz attempts.
// ------------------------------------------------------------------
$quizrows = $DB->get_records_sql(
    "SELECT q.id,
            q.name                                             AS quizname,
            c.fullname                                         AS coursename,
            q.sumgrades,
            COUNT(qa.id)                                       AS attempts,
            MAX(qa.sumgrades / NULLIF(q.sumgrades,0) * 100)   AS bestgrade,
            MAX(qa.timefinish)                                 AS lastattempt,
            (SELECT qa2.sumgrades / NULLIF(q.sumgrades,0) * 100
               FROM {quiz_attempts} qa2
              WHERE qa2.quiz = q.id AND qa2.userid = :uid0
                AND qa2.state = 'finished'
                AND qa2.timefinish = (
                    SELECT MAX(qa3.timefinish) FROM {quiz_attempts} qa3
                     WHERE qa3.quiz = q.id AND qa3.userid = :uid0b
                       AND qa3.state = 'finished'
                ))                                            AS lastgrade
       FROM {quiz} q
       JOIN {course} c        ON c.id = q.course
       JOIN {quiz_attempts} qa ON qa.quiz = q.id
                               AND qa.userid = :uid1
                               AND qa.state  = 'finished'
                               {$datewherequiz}
      WHERE c.id <> :siteid2
   GROUP BY q.id, q.name, c.fullname, q.sumgrades
   ORDER BY lastattempt DESC",
    array_merge(
        ['uid0' => $userid, 'uid0b' => $userid, 'uid1' => $userid, 'siteid2' => SITEID],
        $alltime ? [] : ['qafrom' => $datefrom, 'qato' => $dateto]
    )
);

// ------------------------------------------------------------------
// Query 3 - Logins within the period.
// ------------------------------------------------------------------
$loginparams = ['uid' => $userid];
$loginwhere  = "userid = :uid AND action = 'loggedin'";
if (!$alltime) {
    $loginwhere .= ' AND timecreated BETWEEN :from AND :to';
    $loginparams['from'] = $datefrom;
    $loginparams['to']   = $dateto;
}
// Total login count for the KPI card (unlimited).
$totallogincount = $DB->count_records_select('logstore_standard_log', $loginwhere, $loginparams);
// Only fetch the most recent 10 for display.
$loginrows = $DB->get_records_select(
    'logstore_standard_log',
    $loginwhere,
    $loginparams,
    'timecreated DESC',
    'id,timecreated,ip',
    0,
    10
);

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
    $scormrows = []; // SCORM module not available on this site.
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
    $assignrows = []; // Assignment module not available on this site.
}

// ------------------------------------------------------------------
// Summary calculations.
// ------------------------------------------------------------------
$totalcourses     = count($courserows);
$completedcourses = 0;
$inprogress       = 0;
$gradessum        = 0.0;
$gradedcount      = 0;

foreach ($courserows as $cr) {
    $hasgrade   = ($cr->finalgrade !== null && (float)$cr->finalgrade > 0);
    $hasdone    = ((int)$cr->doneactivities > 0);
    $hasstarted = !empty($cr->timestarted);
    $sgpct      = (!empty($cr->grademax) && $cr->finalgrade !== null)
        ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
    $strackable = (int)$cr->totalactivities;

    if (!empty($cr->timecompleted)) {
        $completedcourses++;
    } elseif ($strackable === 0 && $sgpct >= 100) {
        $completedcourses++;
    } elseif ($hasstarted || $hasdone || $hasgrade) {
        $inprogress++;
    }
    if (!empty($cr->grademax) && $cr->finalgrade !== null) {
        $gradessum += ($cr->finalgrade / $cr->grademax) * 100;
        $gradedcount++;
    }
}
$overallavg  = $gradedcount > 0 ? number_format($gradessum / $gradedcount, 1) . '%' : '-';
$totallogins = $totallogincount;

// ------------------------------------------------------------------
// OUTPUT
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'myreport', $userid);

// ------------------------------------------------------------------
// User picker for managers / admins.
// ------------------------------------------------------------------
if (has_capability('local/leducon:viewanalytics', $syscontext)) {
    $allusers = $DB->get_records_sql(
        "SELECT u.id, u.firstname, u.lastname, u.email, u.username
           FROM {user} u
          WHERE u.deleted = 0 AND u.suspended = 0
            AND u.username <> 'guest'
          ORDER BY u.lastname ASC, u.firstname ASC",
        [],
        0,
        500
    );
    if (!empty($allusers)) {
        $useropts = [];
        foreach ($allusers as $u) {
            $useropts[$u->id] = fullname($u) . ' (' . $u->email . ')';
        }
        echo html_writer::start_div('ld-card ld-user-picker-card');
        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => (new moodle_url('/local/leducon/analytics/myreport.php'))->out(false),
            'class'  => 'ld-filter-bar',
        ]);
        echo html_writer::tag('label',
            get_string('myreport_viewuser', 'local_leducon') . '&nbsp;',
            ['for' => 'ld-user-picker', 'class' => 'font-weight-bold']);
        echo html_writer::select($useropts, 'userid', $userid, false,
            ['id' => 'ld-user-picker', 'class' => 'ld-input']);
        echo html_writer::empty_tag('input', [
            'type'  => 'submit',
            'value' => get_string('myreport_viewbtn', 'local_leducon'),
            'class' => 'ld-btn ld-btn-sm ld-btn-primary',
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        // Enhance select with Moodle autocomplete for search.
        $PAGE->requires->js_amd_inline("
            require(['jquery'], function(\$) {
                var sel = document.getElementById('ld-user-picker');
                if (sel && typeof sel.setAttribute === 'function') {
                    // Add a search filter via native datalist or select2 if available.
                    try {
                        require(['core/form-autocomplete'], function(autocomplete) {
                            autocomplete.enhance('#ld-user-picker', false, '', '" .
                                get_string('search') . "');
                        });
                    } catch(e) {}
                }
            });
        ");
    }
}

// ------------------------------------------------------------------
// User header card.
// ------------------------------------------------------------------
echo html_writer::start_div('ld-card ld-usercard');
echo html_writer::start_div('ld-usercard-inner');
echo html_writer::start_div('ld-usercard-avatar');
echo $OUTPUT->user_picture($user, ['size' => 72, 'class' => 'ld-avatar-circle']);
echo html_writer::end_div();
echo html_writer::start_div('ld-usercard-info');
echo html_writer::tag('h3', s(fullname($user)), ['class' => 'ld-usercard-name']);
echo html_writer::tag('p',  s($user->email),    ['class' => 'text-muted ld-usercard-email']);
echo html_writer::tag('small',
    get_string('my_membersince', 'local_leducon') . ': <strong>' .
    s(userdate($user->firstaccess, get_string('strftimedatetimeshort', 'langconfig'))) .
    '</strong>&nbsp;|&nbsp;' .
    get_string('my_lastlogin', 'local_leducon') . ': <strong>' .
    ($user->lastaccess ? s(userdate($user->lastaccess, get_string('strftimedatetimeshort', 'langconfig'))) : '-') .
    '</strong>',
    ['class' => 'text-muted']
);
echo html_writer::end_div();
echo html_writer::start_div('ld-usercard-actions');
$exportbase = ['userid' => $userid, 'datefrom' => $datefrom, 'dateto' => $dateto];
$pdfurl = new moodle_url('/local/leducon/analytics/exportpdf.php', array_merge($exportbase, ['format' => 'pdf']));
$csvurl = new moodle_url('/local/leducon/analytics/exportpdf.php', array_merge($exportbase, ['format' => 'csv']));
echo html_writer::link($pdfurl,
    s(get_string('exportpdfview', 'local_leducon')),
    ['class' => 'ld-btn ld-btn-sm ld-btn-secondary', 'target' => '_blank']);
echo html_writer::link($csvurl,
    s(get_string('exportmycsv', 'local_leducon')),
    ['class' => 'ld-btn ld-btn-sm ld-btn-success']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// ------------------------------------------------------------------
// Date-range filter bar (ld- design system).
// ------------------------------------------------------------------
$formurl = new moodle_url('/local/leducon/analytics/myreport.php');
echo html_writer::start_div('ld-card ld-myreport-filter');
echo html_writer::tag('h6', get_string('filter_daterange', 'local_leducon'), ['class' => 'ld-myreport-filter-title']);

// Quick-range buttons.
$quickranges = [
    7   => get_string('quickrange_7',   'local_leducon'),
    30  => get_string('quickrange_30',  'local_leducon'),
    90  => get_string('quickrange_90',  'local_leducon'),
    365 => get_string('quickrange_365', 'local_leducon'),
    0   => get_string('quickrange_all', 'local_leducon'),
];

echo html_writer::start_div('ld-period-bar');
foreach ($quickranges as $days => $label) {
    $active = '';
    if ($days === 0 && $alltime) {
        $active = ' active';
    } elseif ($days > 0 && !$alltime) {
        $expected = (int) strtotime("-{$days} days midnight");
        if (abs($datefrom - $expected) < 120) {
            $active = ' active';
        }
    }
    $qurl = new moodle_url('/local/leducon/analytics/myreport.php', [
        'userid'     => $userid,
        'quickrange' => $days,
    ]);
    echo html_writer::link($qurl, s($label),
        ['class' => 'ld-period-pill' . $active]);
}
echo html_writer::end_div();

// Custom date pickers.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl->out(false), 'class' => 'ld-custom-row']);
if ($userid != $USER->id) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
}

$dfval = $datefrom > 0 ? date('Y-m-d', $datefrom) : '';
$dtval = date('Y-m-d', $dateto);

echo html_writer::tag('label', s(get_string('filter_datefrom', 'local_leducon')), ['class' => 'ld-filter-label']);
echo html_writer::empty_tag('input', [
    'type'  => 'date', 'name' => 'datefromstr',
    'value' => $dfval, 'class' => 'ld-filter-input',
]);
echo html_writer::tag('label', s(get_string('filter_dateto', 'local_leducon')), ['class' => 'ld-filter-label']);
echo html_writer::empty_tag('input', [
    'type'  => 'date', 'name' => 'datetostr',
    'value' => $dtval, 'class' => 'ld-filter-input',
]);
echo html_writer::tag('button', s(get_string('applyfilters', 'local_leducon')),
    ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']);
$reseturl = new moodle_url('/local/leducon/analytics/myreport.php', ['userid' => $userid]);
echo html_writer::link($reseturl, s(get_string('resetfilters', 'local_leducon')),
    ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
echo html_writer::end_tag('form');

// Active range label.
$rangelabel = $alltime
    ? get_string('quickrange_all', 'local_leducon')
    : userdate($datefrom, get_string('strftimedate', 'langconfig')) .
      ' &ndash; ' .
      userdate($dateto,   get_string('strftimedate', 'langconfig'));
echo html_writer::tag('small', $rangelabel, ['class' => 'text-muted', 'style' => 'display:block;margin-top:.5rem']);

echo html_writer::end_div();

// ------------------------------------------------------------------
// Overview stats.
// ------------------------------------------------------------------
echo html_writer::tag('h4', get_string('myreport_overview', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);
$stats = [
    [get_string('my_totalcourses',     'local_leducon'), $totalcourses,     'primary'],
    [get_string('my_completedcourses', 'local_leducon'), $completedcourses, 'success'],
    [get_string('my_inprogresscourses','local_leducon'), $inprogress,       'warning'],
    [get_string('my_overallavg',       'local_leducon'), $overallavg,       'info'],
    [get_string('my_totallogins',      'local_leducon'), $totallogins,      'secondary'],
];
echo html_writer::start_div('ld-kpi-strip');
foreach ($stats as [$label, $value, $color]) {
    echo html_writer::start_div('ld-stat-card ld-' . $color);
    echo html_writer::tag('div', s((string)$value), ['class' => 'ld-stat-value']);
    echo html_writer::tag('div', s($label), ['class' => 'ld-stat-label']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

// ------------------------------------------------------------------
// My Report Charts.
// ------------------------------------------------------------------
if (!empty($courserows)) {
    // Build chart data from course query results.
    $my_chart_labels = [];
    $my_chart_grades = [];
    $my_chart_progress = [];
    $my_comp_counts = ['completed' => $completedcourses, 'inprogress' => $inprogress,
                       'notstarted' => max(0, $totalcourses - $completedcourses - $inprogress)];
    foreach ($courserows as $cr) {
        $my_chart_labels[] = format_string($cr->fullname);
        $my_chart_grades[] = (!empty($cr->grademax) && $cr->finalgrade !== null)
            ? round(($cr->finalgrade / $cr->grademax) * 100, 1) : 0;
        $total = (int)$cr->totalactivities;
        $done  = (int)$cr->doneactivities;
        $chartgpct = (!empty($cr->grademax) && $cr->finalgrade !== null)
            ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
        if ($total > 0) {
            $my_chart_progress[] = (int)round($done / $total * 100);
        } elseif ((int)$cr->totalmodules > 0 && $chartgpct > 0) {
            $my_chart_progress[] = min(100, (int)round($chartgpct));
        } else {
            $my_chart_progress[] = 0;
        }
    }

    echo html_writer::start_div('ld-chart-grid');

    // Course progress bar chart.
    echo html_writer::start_div('ld-card ld-chart-card');
    echo html_writer::start_div('ld-card-header');
    echo html_writer::tag('h3', get_string('chart_my_progress', 'local_leducon'), ['class' => 'ld-card-title']);
    echo html_writer::end_div();
    echo html_writer::start_div('ld-card-body ld-chart-body');
    if (array_sum($my_chart_progress) > 0 || array_sum($my_chart_grades) > 0) {
        try {
            $my_bar = new \core\chart_bar();
            $my_bar->set_horizontal(true);
            $ps = new \core\chart_series(get_string('my_progress', 'local_leducon'), $my_chart_progress);
            $ps->set_color('#2563eb');
            $my_bar->add_series($ps);
            $gs = new \core\chart_series(get_string('my_grade', 'local_leducon'), $my_chart_grades);
            $gs->set_color('#16a34a');
            $my_bar->add_series($gs);
            $my_bar->set_labels(array_slice($my_chart_labels, 0, 10));
            echo $OUTPUT->render($my_bar);
        } catch (\Throwable $e) {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
    } else {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Course completion pie chart.
    $my_comp_total = $my_comp_counts['completed'] + $my_comp_counts['inprogress'] + $my_comp_counts['notstarted'];
    echo html_writer::start_div('ld-card ld-chart-card');
    echo html_writer::start_div('ld-card-header');
    echo html_writer::tag('h3', get_string('chart_completion_status', 'local_leducon'), ['class' => 'ld-card-title']);
    echo html_writer::end_div();
    echo html_writer::start_div('ld-card-body ld-chart-body');
    if ($my_comp_total > 0) {
        try {
            $my_pie = new \core\chart_pie();
            $mps = new \core\chart_series(
                get_string('chart_completion_status', 'local_leducon'),
                [$my_comp_counts['completed'], $my_comp_counts['inprogress'], $my_comp_counts['notstarted']]
            );
            $mps->set_colors(['#16a34a', '#d97706', '#e5e7eb']);
            $my_pie->add_series($mps);
            $my_pie->set_labels([
                get_string('chart_completed', 'local_leducon'),
                get_string('chart_inprogress', 'local_leducon'),
                get_string('chart_notstarted', 'local_leducon'),
            ]);
            $my_pie->set_doughnut(true);
            echo $OUTPUT->render($my_pie);
        } catch (\Throwable $e) {
            echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
        }
    } else {
        echo html_writer::tag('p', get_string('chart_nodata', 'local_leducon'), ['class' => 'text-muted ld-chart-empty']);
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_div(); // .ld-chart-grid
}

// ------------------------------------------------------------------
// My Courses table.
// ------------------------------------------------------------------
echo html_writer::tag('h4', get_string('myreport_courses', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);

if (empty($courserows)) {
    echo $OUTPUT->notification(get_string('noresults', 'local_leducon'), 'info');
} else {
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('my_course',   'local_leducon'),
        get_string('my_status',   'local_leducon'),
        get_string('my_grade',    'local_leducon'),
        get_string('my_progress', 'local_leducon'),
        get_string('my_enrolled', 'local_leducon'),
        get_string('my_started',  'local_leducon'),
        get_string('my_completed','local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($courserows as $cr) {
        $hasgrade    = ($cr->finalgrade !== null && (float)$cr->finalgrade > 0);
        $hasdone     = ((int)$cr->doneactivities > 0);
        $hasstarted  = !empty($cr->timestarted);
        $gradepct    = (!empty($cr->grademax) && $cr->finalgrade !== null)
                        ? ($cr->finalgrade / $cr->grademax) * 100 : 0;
        $trackable   = (int)$cr->totalactivities;

        if (!empty($cr->timecompleted)) {
            [$slabel, $sclass] = [get_string('my_status_completed',  'local_leducon'), 'ld-badge-success'];
        } elseif ($trackable === 0 && $gradepct >= 100) {
            [$slabel, $sclass] = [get_string('my_status_completed',  'local_leducon'), 'ld-badge-success'];
        } elseif ($hasstarted || $hasdone || $hasgrade) {
            [$slabel, $sclass] = [get_string('my_status_inprogress', 'local_leducon'), 'ld-badge-warning'];
        } else {
            [$slabel, $sclass] = [get_string('my_status_notstarted', 'local_leducon'), 'ld-badge-muted'];
        }
        $badge = html_writer::tag('span', s($slabel), ['class' => "ld-badge {$sclass}"]);

        $gradecell = (!empty($cr->grademax) && $cr->finalgrade !== null)
            ? number_format(($cr->finalgrade / $cr->grademax) * 100, 1) . '%'
            : '-';

        $total = (int)$cr->totalactivities;
        $done  = (int)$cr->doneactivities;
        $allmods = (int)$cr->totalmodules;
        if ($total > 0) {
            $pct = min(100, (int)round($done / $total * 100));
            $innerbar = html_writer::tag('div', '', [
                'class' => 'ld-progress-bar ld-bg-primary',
                'style' => "width:{$pct}%",
            ]);
            $bar = html_writer::tag('div', $innerbar, ['class' => 'ld-progress'])
                 . html_writer::tag('small', "{$done}/{$total}", ['class' => 'text-muted']);
        } elseif ($total === 0 && $allmods > 0 && $gradepct > 0) {
            $gpct = min(100, (int)round($gradepct));
            $innerbar = html_writer::tag('div', '', [
                'class' => 'ld-progress-bar ld-bg-primary',
                'style' => "width:{$gpct}%",
            ]);
            $bar = html_writer::tag('div', $innerbar, ['class' => 'ld-progress'])
                 . html_writer::tag('small', number_format($gradepct, 0) . '% ' . get_string('my_grade', 'local_leducon'), ['class' => 'text-muted']);
        } else {
            $bar = '-';
        }

        $startedcell = !empty($cr->timestarted)
            ? s(userdate($cr->timestarted, get_string('strftimedate', 'langconfig'))) : '-';
        $completedcell = !empty($cr->timecompleted)
            ? s(userdate($cr->timecompleted, get_string('strftimedate', 'langconfig'))) : '-';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', html_writer::link(
            new moodle_url('/course/view.php', ['id' => $cr->id]), s($cr->fullname)));
        echo html_writer::tag('td', $badge);
        echo html_writer::tag('td', s($gradecell));
        echo html_writer::tag('td', $bar);
        echo html_writer::tag('td', s(userdate($cr->timeenrolled, get_string('strftimedate', 'langconfig'))));
        echo html_writer::tag('td', $startedcell);
        echo html_writer::tag('td', $completedcell);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// ------------------------------------------------------------------
// Quiz Results table.
// ------------------------------------------------------------------
echo html_writer::tag('h4', get_string('myreport_quizzes', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);

if (empty($quizrows)) {
    echo $OUTPUT->notification(get_string('noresults', 'local_leducon'), 'info');
} else {
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('my_quiz',        'local_leducon'),
        get_string('my_course',      'local_leducon'),
        get_string('my_attempts',    'local_leducon'),
        get_string('my_bestgrade',   'local_leducon'),
        get_string('my_lastgrade',   'local_leducon'),
        get_string('my_passed',      'local_leducon'),
        get_string('my_lastattempt', 'local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($quizrows as $qr) {
        $best = $qr->bestgrade !== null ? number_format((float)$qr->bestgrade, 1) . '%' : '-';
        $last = $qr->lastgrade  !== null ? number_format((float)$qr->lastgrade,  1) . '%' : '-';
        if ($qr->bestgrade !== null) {
            $passthreshold = (float) get_config('local_leducon', 'kpi_pass_green') ?: 70;
            $badge = (float)$qr->bestgrade >= $passthreshold
                ? html_writer::tag('span', get_string('my_pass', 'local_leducon'), ['class' => 'ld-badge ld-badge-success'])
                : html_writer::tag('span', get_string('my_fail', 'local_leducon'), ['class' => 'ld-badge ld-badge-danger']);
        } else {
            $badge = '-';
        }
        $lastatt = $qr->lastattempt
            ? s(userdate((int)$qr->lastattempt, get_string('strftimedatetimeshort', 'langconfig')))
            : '-';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($qr->quizname));
        echo html_writer::tag('td', s($qr->coursename));
        echo html_writer::tag('td', (int)$qr->attempts);
        echo html_writer::tag('td', s($best));
        echo html_writer::tag('td', s($last));
        echo html_writer::tag('td', $badge);
        echo html_writer::tag('td', $lastatt);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// ------------------------------------------------------------------
// Recent Logins table.
// ------------------------------------------------------------------
echo html_writer::tag('h4', get_string('myreport_recentlogins', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);

if (empty($loginrows)) {
    echo html_writer::tag('p', s(get_string('my_nologins', 'local_leducon')), ['class' => 'text-muted']);
} else {
    if ($totallogincount > 10) {
        echo html_writer::tag('p',
            get_string('my_logins_showing', 'local_leducon', $totallogincount),
            ['class' => 'text-muted small']);
    }
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', '#');
    echo html_writer::tag('th', s(get_string('my_logintime', 'local_leducon')));
    echo html_writer::tag('th', s(get_string('my_loginip',   'local_leducon')));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    $i = 1;
    foreach ($loginrows as $lr) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $i++);
        echo html_writer::tag('td', s(userdate($lr->timecreated, get_string('strftimedatetimeshort', 'langconfig'))));
        echo html_writer::tag('td', s($lr->ip));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// ------------------------------------------------------------------
// SCORM Activities table.
// ------------------------------------------------------------------
if (!empty($scormrows)) {
    echo html_writer::tag('h4', get_string('myreport_scorm', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('my_scorm_activity',  'local_leducon'),
        get_string('my_scorm_course',    'local_leducon'),
        get_string('my_scorm_status',    'local_leducon'),
        get_string('my_scorm_score',     'local_leducon'),
        get_string('my_scorm_attempts',  'local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($scormrows as $sr) {
        $td = isset($scormtrackdata[$sr->id]) ? $scormtrackdata[$sr->id] : null;
        $numatt = $td ? count($td['attempts']) : 0;

        // Determine best status.
        $statuslabel = get_string('my_scorm_notattempted', 'local_leducon');
        $statusclass = 'ld-badge-muted';
        if ($td && !empty($td['statuses'])) {
            $statuses = $td['statuses'];
            if (in_array('passed', $statuses)) {
                $statuslabel = get_string('my_scorm_passed',    'local_leducon');
                $statusclass = 'ld-badge-success';
            } elseif (in_array('completed', $statuses)) {
                $statuslabel = get_string('my_scorm_completed', 'local_leducon');
                $statusclass = 'ld-badge-success';
            } elseif (in_array('failed', $statuses)) {
                $statuslabel = get_string('my_scorm_failed',    'local_leducon');
                $statusclass = 'ld-badge-danger';
            } elseif (in_array('incomplete', $statuses) || $numatt > 0) {
                $statuslabel = get_string('my_scorm_incomplete','local_leducon');
                $statusclass = 'ld-badge-warning';
            }
        }
        $badge = html_writer::tag('span', s($statuslabel), ['class' => "ld-badge {$statusclass}"]);

        // Best raw score.
        $score = '-';
        if ($td && !empty($td['scores'])) {
            $score = number_format(max($td['scores']), 1);
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($sr->scormname));
        echo html_writer::tag('td', s($sr->coursename));
        echo html_writer::tag('td', $badge);
        echo html_writer::tag('td', s($score));
        echo html_writer::tag('td', $numatt);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// ------------------------------------------------------------------
// Assignments table.
// ------------------------------------------------------------------
if (!empty($assignrows)) {
    echo html_writer::tag('h4', get_string('myreport_assignments', 'local_leducon'), ['style' => 'margin-bottom:.75rem']);
    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    foreach ([
        get_string('my_assign_name',   'local_leducon'),
        get_string('my_assign_course', 'local_leducon'),
        get_string('my_assign_due',    'local_leducon'),
        get_string('my_assign_status', 'local_leducon'),
        get_string('my_assign_grade',  'local_leducon'),
    ] as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($assignrows as $ar) {
        // Submission status badge.
        if (!empty($ar->substatus)) {
            $islate = ($ar->duedate > 0 && $ar->subtime > $ar->duedate);
            if ($islate) {
                $sublabel = get_string('my_assign_late',      'local_leducon');
                $subclass = 'ld-badge-warning';
            } else {
                $sublabel = get_string('my_assign_submitted', 'local_leducon');
                $subclass = 'ld-badge-success';
            }
        } else {
            $sublabel = get_string('my_assign_notsubmitted', 'local_leducon');
            $subclass = 'ld-badge-muted';
        }
        $subbadge = html_writer::tag('span', s($sublabel), ['class' => "ld-badge {$subclass}"]);

        // Grade display.
        $gradecell = '-';
        if (!empty($ar->substatus) && $ar->finalgrade !== null && (float)$ar->finalgrade >= 0) {
            if (!empty($ar->maxgrade) && $ar->maxgrade > 0) {
                $gradecell = number_format((float)$ar->finalgrade / (float)$ar->maxgrade * 100, 1) . '%';
            } else {
                $gradecell = html_writer::tag('span',
                    s(get_string('my_assign_graded', 'local_leducon')), ['class' => 'ld-badge ld-badge-info']);
            }
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($ar->assignname));
        echo html_writer::tag('td', s($ar->coursename));
        echo html_writer::tag('td', $ar->duedate
            ? s(userdate($ar->duedate, get_string('strftimedate', 'langconfig'))) : '-');
        echo html_writer::tag('td', $subbadge);
        echo html_writer::tag('td', s((string)$gradecell));
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

// Print styles are provided by the unified styles.css print rules.

echo $OUTPUT->footer();
