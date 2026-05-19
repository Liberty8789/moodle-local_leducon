<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Leducon - Course and cohort/department leaderboards.
 *
 * ?type=course&courseid=X  - students ranked within one course
 * ?type=cohort&cohortid=X  - cohort members ranked across all their courses
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('error_noaccess', 'local_leducon');
}

$syscontext = context_system::instance();
if (!has_capability('local/leducon:viewanalytics', $syscontext) &&
    !has_capability('local/leducon:viewleaderboard', $syscontext)) {
    throw new moodle_exception('error_noaccess', 'local_leducon');
}

$type     = optional_param('type',     'course', PARAM_ALPHA);
// Validate type param.
if (!in_array($type, ['course', 'cohort', 'xp'], true)) {
    $type = 'course';
}
$courseid = optional_param('courseid', 0,        PARAM_INT);
$cohortid = optional_param('cohortid', 0,        PARAM_INT);

// Privacy setting.
$privacy  = get_config('local_leducon', 'leaderboard_privacy') ?: 'fullnames';
$isTeacher = has_capability('local/leducon:viewanalytics', $syscontext) ||
             ($courseid > 0 && has_capability('local/leducon:viewstudents',
                 context_course::instance($courseid)));
// Students see anonymous names if setting requires it.
$shownames        = ($privacy === 'fullnames') || $isTeacher;
$hiddenFromStudents = ($privacy === 'teacheronly') && !$isTeacher;
$maxrows          = (int) get_config('local_leducon', 'leaderboard_maxrows') ?: 20;

// Self-view check: a user with viewown but not view can still reach leaderboard
// only if it is not hidden; capability 'view' already checked above.

// ------------------------------------------------------------------
// Page setup.
// ------------------------------------------------------------------
$pageurl = new moodle_url('/local/leducon/analytics/leaderboard.php', [
    'type'     => $type,
    'courseid' => $courseid,
    'cohortid' => $cohortid,
]);
$PAGE->set_context($syscontext);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('leaderboard_title', 'local_leducon'));
$PAGE->set_heading(get_string('leaderboard_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('leaderboard_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'leaderboard', $USER->id);
echo $OUTPUT->heading(get_string('leaderboard_title', 'local_leducon'));

if ($hiddenFromStudents) {
    echo $OUTPUT->notification(get_string('settings_privacy_teacheronly', 'local_leducon'), 'info');
    echo $OUTPUT->footer();
    die();
}

// ------------------------------------------------------------------
// Type switcher tabs.
// ------------------------------------------------------------------
$tabs = [
    new tabobject('course',
        new moodle_url('/local/leducon/analytics/leaderboard.php', ['type' => 'course']),
        s(get_string('lb_course_tab', 'local_leducon'))
    ),
    new tabobject('cohort',
        new moodle_url('/local/leducon/analytics/leaderboard.php', ['type' => 'cohort']),
        s(get_string('lb_cohort_tab', 'local_leducon'))
    ),
];
// Add XP tab if gamification is enabled.
$gamify_enabled = (bool)get_config('local_leducon', 'gamify_enabled');
if ($gamify_enabled) {
    $tabs[] = new tabobject('xp',
        new moodle_url('/local/leducon/analytics/leaderboard.php', ['type' => 'xp']),
        s(get_string('lb_xp_tab', 'local_leducon'))
    );
}
echo $OUTPUT->tabtree($tabs, $type);

// ------------------------------------------------------------------
// COURSE LEADERBOARD.
// ------------------------------------------------------------------
if ($type === 'course') {

    // Course selector.
    $courses = $DB->get_records_select('course', 'id <> :sid',
        ['sid' => SITEID], 'fullname ASC', 'id,fullname');
    if (empty($courses)) {
        echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
        echo $OUTPUT->footer();
        die();
    }

    $copts = [];
    foreach ($courses as $c) {
        $copts[$c->id] = format_string($c->fullname);
    }
    if ($courseid === 0) {
        reset($copts);
        $courseid = key($copts);
    }

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'ld-filter-bar']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => 'course']);
    echo html_writer::tag('label', s(get_string('filter_course', 'local_leducon')) . '&nbsp;', ['class' => 'mr-2']);
    echo html_writer::select($copts, 'courseid', $courseid, false,
        ['class' => 'ld-input', 'onchange' => 'this.form.submit()']);
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => s(get_string('filter_apply', 'local_leducon')),
        'class' => 'ld-btn ld-btn-sm ld-btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();

    // Rank students in this course by: avg grade %, activities done, logins.
    // Use subqueries to avoid cartesian product between logstore and course_modules.
    $rows = $DB->get_records_sql(
        "SELECT u.id,
                u.firstname, u.lastname, u.email,
                gi.grademax,
                gg.finalgrade,
                (SELECT COUNT(cm2.id)
                   FROM {course_modules} cm2
                  WHERE cm2.course = :cid2 AND cm2.visible = 1 AND cm2.completion > 0) AS totalact,
                (SELECT COUNT(cmc2.id)
                   FROM {course_modules_completion} cmc2
                   JOIN {course_modules} cm3 ON cm3.id = cmc2.coursemoduleid
                  WHERE cmc2.userid = u.id AND cm3.course = :cid3
                    AND cmc2.completionstate > 0) AS doneact,
                (SELECT COUNT(cm5.id)
                   FROM {course_modules} cm5
                  WHERE cm5.course = :cid5 AND cm5.visible = 1) AS allact,
                (SELECT COUNT(ls2.id)
                   FROM {logstore_standard_log} ls2
                  WHERE ls2.userid = u.id AND ls2.action = 'loggedin'
                    AND ls2.courseid = :cid4) AS logins
           FROM {user_enrolments} ue
           JOIN {enrol} e         ON e.id = ue.enrolid AND e.courseid = :courseid
           JOIN {user} u          ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
      LEFT JOIN {grade_items} gi  ON gi.courseid = :cid1 AND gi.itemtype = 'course'
      LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
          WHERE u.id <> :guestid
       GROUP BY u.id, u.firstname, u.lastname, u.email, gi.grademax, gg.finalgrade
       ORDER BY gg.finalgrade DESC, doneact DESC",
        [
            'courseid' => $courseid,
            'cid1'     => $courseid,
            'cid2'     => $courseid,
            'cid3'     => $courseid,
            'cid4'     => $courseid,
            'cid5'     => $courseid,
            'guestid'  => $DB->get_field('user', 'id', ['username' => 'guest']) ?: 1,
        ],
        0,
        $maxrows
    );

    local_leducon_render_leaderboard(
        $rows, $USER->id, $shownames, $maxrows,
        [
            get_string('lb_rank',       'local_leducon'),
            get_string('lb_student',    'local_leducon'),
            get_string('lb_grade',      'local_leducon'),
            get_string('lb_activities', 'local_leducon'),
            get_string('lb_logins',     'local_leducon'),
        ],
        function($r) {
            $gradepct = (!empty($r->grademax) && $r->finalgrade !== null)
                ? ($r->finalgrade / $r->grademax) * 100 : 0;
            $grade = $gradepct > 0 ? number_format($gradepct, 1) . '%' : '-';
            $total  = (int)$r->totalact;
            $done   = (int)$r->doneact;
            $allmod = (int)$r->allact;
            if ($total > 0) {
                $actcol = "{$done}/{$total}";
            } elseif ($allmod > 0 && $gradepct > 0) {
                $actcol = number_format($gradepct, 0) . '% grade';
            } else {
                $actcol = '-';
            }
            return [$grade, $actcol, (int)$r->logins];
        }
    );

// ------------------------------------------------------------------
// COHORT / DEPARTMENT LEADERBOARD.
// ------------------------------------------------------------------
} elseif ($type === 'cohort') {

    $cohorts = $DB->get_records('cohort', [], 'name ASC', 'id,name,description');
    if (empty($cohorts)) {
        echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
        echo $OUTPUT->footer();
        die();
    }

    $cohopts = [];
    foreach ($cohorts as $ch) {
        $cohopts[$ch->id] = format_string($ch->name);
    }
    if ($cohortid === 0) {
        reset($cohopts);
        $cohortid = key($cohopts);
    }

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'ld-filter-bar']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => 'cohort']);
    echo html_writer::tag('label', s(get_string('filter_cohort', 'local_leducon')) . '&nbsp;', ['class' => 'mr-2']);
    echo html_writer::select($cohopts, 'cohortid', $cohortid, false,
        ['class' => 'ld-input', 'onchange' => 'this.form.submit()']);
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => s(get_string('filter_apply', 'local_leducon')),
        'class' => 'ld-btn ld-btn-sm ld-btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();

    // Rank cohort members by: avg grade across all courses, completions, logins.
    $rows = $DB->get_records_sql(
        "SELECT u.id,
                u.firstname, u.lastname, u.email,
                AVG(gg.finalgrade / NULLIF(gi.grademax, 0) * 100)  AS avggradepct,
                COUNT(DISTINCT comp.course)                          AS completedcourses,
                COUNT(DISTINCT ls.id)                                AS logins
           FROM {cohort_members} cm
           JOIN {user} u          ON u.id = cm.userid AND u.deleted = 0 AND u.suspended = 0
      LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
      LEFT JOIN {enrol} e         ON e.id = ue.enrolid
      LEFT JOIN {course} c        ON c.id = e.courseid AND c.id <> :siteid
      LEFT JOIN {grade_items} gi  ON gi.courseid = c.id AND gi.itemtype = 'course'
      LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id AND gg.finalgrade IS NOT NULL
      LEFT JOIN {course_completions} comp ON comp.course = c.id AND comp.userid = u.id
                                         AND (comp.timecompleted IS NOT NULL
                                             OR (gg.finalgrade IS NOT NULL AND gi.grademax > 0 AND gg.finalgrade / gi.grademax * 100 >= 100))
      LEFT JOIN {logstore_standard_log} ls ON ls.userid = u.id AND ls.action = 'loggedin'
          WHERE cm.cohortid = :cohortid
            AND u.id <> :guestid
       GROUP BY u.id, u.firstname, u.lastname, u.email
       ORDER BY avggradepct DESC, completedcourses DESC",
        [
            'cohortid' => $cohortid,
            'siteid'   => SITEID,
            'guestid'  => $DB->get_field('user', 'id', ['username' => 'guest']) ?: 1,
        ],
        0,
        $maxrows
    );

    local_leducon_render_leaderboard(
        $rows, $USER->id, $shownames, $maxrows,
        [
            get_string('lb_rank',           'local_leducon'),
            get_string('lb_student',        'local_leducon'),
            get_string('lb_avggrade',       'local_leducon'),
            get_string('lb_courses_completed', 'local_leducon'),
            get_string('lb_logins',         'local_leducon'),
        ],
        function($r) {
            $avg = $r->avggradepct !== null ? number_format((float)$r->avggradepct, 1) . '%' : '-';
            return [$avg, (int)$r->completedcourses, (int)$r->logins];
        }
    );

// ------------------------------------------------------------------
// XP LEADERBOARD (gamification).
// ------------------------------------------------------------------
} elseif ($type === 'xp' && $gamify_enabled) {

    $xp_maxrows  = (int)(get_config('local_leducon', 'leaderboard_maxrows') ?: 50);
    $xp_privacy  = (bool)get_config('local_leducon', 'leaderboard_privacy');
    $fullname_sql = $DB->sql_fullname('u.firstname', 'u.lastname');

    // Cohort filter for XP.
    $xp_cohortid = optional_param('cohortid', 0, PARAM_INT);
    $xp_cohorts  = $DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name');

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'ld-filter-bar']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => 'xp']);
    echo html_writer::tag('label', s(get_string('filter_cohort', 'local_leducon')) . '&nbsp;', ['class' => 'mr-2']);
    $xp_cohopts = [0 => get_string('lb_tab_org', 'local_leducon')];
    foreach ($xp_cohorts as $xco) {
        $xp_cohopts[$xco->id] = format_string($xco->name);
    }
    echo html_writer::select($xp_cohopts, 'cohortid', $xp_cohortid, false,
        ['class' => 'ld-input', 'onchange' => 'this.form.submit()']);
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => s(get_string('filter_apply', 'local_leducon')),
        'class' => 'ld-btn ld-btn-sm ld-btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();

    // Query XP data.
    $cohort_join   = '';
    $cohort_params = [];
    if ($xp_cohortid > 0) {
        $cohort_join   = 'JOIN {cohort_members} cm ON cm.userid = gu.userid AND cm.cohortid = :cid';
        $cohort_params = ['cid' => $xp_cohortid];
    }

    $xp_rows = $DB->get_records_sql(
        "SELECT gu.userid AS id,
                u.firstname, u.lastname, u.email,
                gu.total_xp,
                lvl.name AS levelname,
                lvl.levelnum
           FROM {local_leducon_xp_users} gu
           JOIN {user} u ON u.id = gu.userid AND u.deleted = 0 AND u.suspended = 0
           {$cohort_join}
      LEFT JOIN {local_leducon_levels} lvl ON lvl.id = gu.levelid
       ORDER BY gu.total_xp DESC",
        $cohort_params,
        0,
        $xp_maxrows
    );

    local_leducon_render_leaderboard(
        (array)$xp_rows, $USER->id, $shownames, $xp_maxrows,
        [
            get_string('lb_rank',      'local_leducon'),
            get_string('lb_student',   'local_leducon'),
            get_string('lb_col_level', 'local_leducon'),
            get_string('lb_col_xp',    'local_leducon'),
        ],
        function($r) {
            $level = !empty($r->levelname)
                ? local_leducon_level_badge((int)$r->levelnum, $r->levelname)
                : '-';
            return [$level, local_leducon_fmt_xp((int)$r->total_xp)];
        }
    );
}

echo $OUTPUT->footer();

// ------------------------------------------------------------------
// Helper: render a ranked leaderboard table.
// ------------------------------------------------------------------
/**
 * Render a ranked leaderboard HTML table.
 *
 * @param array    $rows          DB result rows.
 * @param int      $currentuserid The logged-in user's id.
 * @param bool     $shownames     Whether to show real names.
 * @param int      $maxrows       Maximum rows limit (informational).
 * @param array    $headers       Column header strings.
 * @param callable $datacols      Returns array of extra cell values for a row.
 */
function local_leducon_render_leaderboard(
    array $rows,
    int $currentuserid,
    bool $shownames,
    int $maxrows,
    array $headers,
    callable $datacols
): void {
    global $OUTPUT;

    if (empty($rows)) {
        echo $OUTPUT->notification(get_string('nodata', 'local_leducon'), 'info');
        return;
    }

    $rows  = array_values($rows);
    $total = count($rows);

    echo html_writer::tag('p',
        $total . ' ' . s(get_string('lb_student', 'local_leducon')),
        ['class' => 'text-muted small mb-2']
    );

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table',
        ['class' => 'ld-table']);

    echo html_writer::start_tag('thead', ['class' => 'thead-dark']);
    echo html_writer::start_tag('tr');
    foreach ($headers as $h) {
        echo html_writer::tag('th', s($h));
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    $rank = 1;
    foreach ($rows as $r) {
        $isMe     = ((int)$r->id === $currentuserid);
        $rowclass = $isMe ? 'table-info font-weight-bold' : '';

        // Medal for top 3.
        $medal = '';
        if ($rank === 1) {
            $medal = ' 🥇';
        } elseif ($rank === 2) {
            $medal = ' 🥈';
        } elseif ($rank === 3) {
            $medal = ' 🥉';
        }

        // Name cell.
        if ($shownames) {
            $name = s(fullname($r));
        } else {
            $name = s(get_string('lb_you', 'local_leducon')) . ' ' . $rank;
        }
        if ($isMe) {
            $name .= ' ' . html_writer::tag('span',
                s(get_string('lb_you', 'local_leducon')),
                ['class' => 'badge badge-info']
            );
        }

        echo html_writer::start_tag('tr', ['class' => $rowclass]);
        echo html_writer::tag('td', $rank . $medal);
        echo html_writer::tag('td', $name);

        foreach ($datacols($r) as $col) {
            echo html_writer::tag('td', s((string)$col));
        }
        echo html_writer::end_tag('tr');
        $rank++;
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}
