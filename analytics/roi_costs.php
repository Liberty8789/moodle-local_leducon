<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * ROI Cost Management -- enter cost and value data per course.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/leducon:manageskills', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/analytics/roi_costs.php'));
$PAGE->set_title(get_string('roicosts_title', 'local_leducon'));
$PAGE->set_heading(get_string('roicosts_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('roicosts_title', 'local_leducon'));

$currencies = ['USD' => 'USD ($)', 'GBP' => 'GBP (£)', 'EUR' => 'EUR (€)',
               'AUD' => 'AUD', 'CAD' => 'CAD', 'NGN' => 'NGN (₦)', 'ZAR' => 'ZAR (R)'];

// ------------------------------------------------------------------
// Delete action.
// ------------------------------------------------------------------
if (optional_param('action', '', PARAM_ALPHA) === 'delete' && confirm_sesskey()) {
    $deleteid = required_param('deleteid', PARAM_INT);
    $DB->delete_records('local_leducon_course_costs', ['courseid' => $deleteid]);
    redirect(new moodle_url('/local/leducon/analytics/roi_costs.php'),
        get_string('roicosts_deleted', 'local_leducon'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ------------------------------------------------------------------
// Save action.
// ------------------------------------------------------------------
if (optional_param('action', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    $cid   = required_param('courseid', PARAM_INT);
    $cost  = max(0, (float)str_replace(',', '', optional_param('costperlearner', '0', PARAM_RAW)));
    $value = max(0, (float)str_replace(',', '', optional_param('valuepercompletion', '0', PARAM_RAW)));
    $curr  = optional_param('currency', 'USD', PARAM_ALPHANUMEXT);
    $notes = trim(optional_param('notes', '', PARAM_TEXT));

    if (!isset($currencies[$curr])) {
        $curr = 'USD';
    }

    if ($cid > 0 && ($cost > 0 || $value > 0)) {
        $now = time();
        $existing = $DB->get_record('local_leducon_course_costs', ['courseid' => $cid]);
        if ($existing) {
            $DB->update_record('local_leducon_course_costs', (object)[
                'id'                 => $existing->id,
                'costperlearner'     => $cost,
                'valuepercompletion' => $value,
                'currency'           => $curr,
                'notes'              => $notes,
                'timemodified'       => $now,
            ]);
        } else {
            $DB->insert_record('local_leducon_course_costs', (object)[
                'courseid'           => $cid,
                'costperlearner'     => $cost,
                'valuepercompletion' => $value,
                'currency'           => $curr,
                'notes'              => $notes,
                'timemodified'       => $now,
            ]);
        }

        redirect(new moodle_url('/local/leducon/analytics/roi_costs.php'),
            get_string('roicosts_saved', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect(new moodle_url('/local/leducon/analytics/roi_costs.php'),
        get_string('roicosts_entercost', 'local_leducon'), null,
        \core\output\notification::NOTIFY_WARNING);
}

// ------------------------------------------------------------------
// Load courses for dropdown + existing cost records.
// ------------------------------------------------------------------
$courses = $DB->get_records_sql(
    "SELECT c.id, c.fullname, cat.name AS catname
       FROM {course} c
       JOIN {course_categories} cat ON cat.id = c.category
      WHERE c.id <> :siteid
      ORDER BY c.fullname ASC",
    ['siteid' => SITEID]
);

$editid = optional_param('editid', 0, PARAM_INT);
$editrecord = null;

$savedcosts = $DB->get_records_sql(
    "SELECT cc.*, c.fullname
       FROM {local_leducon_course_costs} cc
       JOIN {course} c ON c.id = cc.courseid
      ORDER BY c.fullname ASC"
);

if ($editid > 0) {
    // Lookup by courseid (savedcosts is keyed by record id, not courseid).
    foreach ($savedcosts as $sc) {
        if ((int)$sc->courseid === $editid) {
            $editrecord = $sc;
            break;
        }
    }
}

// ------------------------------------------------------------------
// Render.
// ------------------------------------------------------------------
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'roicosts', $USER->id);
echo $OUTPUT->heading(get_string('roicosts_title', 'local_leducon'));

// ================================================================
// HOW ROI WORKS -- explanation panel.
// ================================================================
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('roicosts_howworks_title', 'local_leducon'),
    ['class' => 'card-title']);
echo html_writer::tag('p', get_string('roicosts_howworks_intro', 'local_leducon'),
    ['class' => 'mb-2']);

echo html_writer::start_tag('ul', ['class' => 'mb-2']);
echo html_writer::tag('li', get_string('roicosts_howworks_step1', 'local_leducon'));
echo html_writer::tag('li', get_string('roicosts_howworks_step2', 'local_leducon'));
echo html_writer::tag('li', get_string('roicosts_howworks_step3', 'local_leducon'));
echo html_writer::end_tag('ul');

echo html_writer::start_div('alert alert-light border py-2 px-3 mb-2');
echo html_writer::tag('strong', get_string('roicosts_formula_label', 'local_leducon'));
echo '<br>';
echo html_writer::tag('code', get_string('roicosts_formula', 'local_leducon'),
    ['style' => 'font-size:1.05em']);
echo html_writer::end_div();

echo html_writer::tag('p', get_string('roicosts_howworks_example', 'local_leducon'),
    ['class' => 'text-muted small mb-1']);
echo html_writer::tag('p', get_string('roicosts_howworks_tip', 'local_leducon'),
    ['class' => 'text-muted small mb-0']);

echo html_writer::end_div();
echo html_writer::end_div();

// ================================================================
// ENTRY FORM -- pick one course, enter cost data.
// ================================================================
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5',
    $editrecord ? get_string('roicosts_edit_heading', 'local_leducon')
                : get_string('roicosts_add_heading', 'local_leducon'),
    ['class' => 'card-title mb-3']);

$formurl = new moodle_url('/local/leducon/analytics/roi_costs.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'save']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Row 1: Course selector.
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label', get_string('roicosts_col_course', 'local_leducon'),
    ['class' => 'col-sm-3 col-form-label font-weight-bold', 'for' => 'id_courseid']);
echo html_writer::start_div('col-sm-9');
$courseoptions = ['' => get_string('roicosts_selectcourse', 'local_leducon')];
foreach ($courses as $c) {
    $courseoptions[$c->id] = $c->fullname . ' (' . $c->catname . ')';
}
echo html_writer::select($courseoptions, 'courseid',
    $editrecord ? $editrecord->courseid : '',
    false, ['class' => 'ld-input', 'id' => 'id_courseid', 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::end_div();

// Row 2: Cost per Learner + Value per Completion side by side.
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label', get_string('roicosts_col_cost', 'local_leducon'),
    ['class' => 'col-sm-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-sm-4');
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'name'        => 'costperlearner',
    'value'       => $editrecord ? number_format((float)$editrecord->costperlearner, 2, '.', '') : '',
    'min'         => '0',
    'step'        => '0.01',
    'class'       => 'ld-input',
    'placeholder' => '0.00',
    'required'    => 'required',
]);
echo html_writer::tag('small', get_string('roicosts_cost_help', 'local_leducon'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::tag('label', get_string('roicosts_col_value', 'local_leducon'),
    ['class' => 'col-sm-2 col-form-label font-weight-bold text-right']);
echo html_writer::start_div('col-sm-3');
echo html_writer::empty_tag('input', [
    'type'        => 'number',
    'name'        => 'valuepercompletion',
    'value'       => $editrecord ? number_format((float)$editrecord->valuepercompletion, 2, '.', '') : '',
    'min'         => '0',
    'step'        => '0.01',
    'class'       => 'ld-input',
    'placeholder' => 'auto (1.5x cost)',
]);
echo html_writer::tag('small', get_string('roicosts_value_help', 'local_leducon'),
    ['class' => 'form-text text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();

// Row 3: Currency + Notes.
echo html_writer::start_div('form-group row mb-3');
echo html_writer::tag('label', get_string('roicosts_col_currency', 'local_leducon'),
    ['class' => 'col-sm-3 col-form-label font-weight-bold']);
echo html_writer::start_div('col-sm-2');
echo html_writer::select($currencies, 'currency',
    $editrecord ? $editrecord->currency : 'USD',
    false, ['class' => 'ld-input']);
echo html_writer::end_div();

echo html_writer::tag('label', get_string('roicosts_col_notes', 'local_leducon'),
    ['class' => 'col-sm-2 col-form-label font-weight-bold text-right']);
echo html_writer::start_div('col-sm-5');
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'notes',
    'value'       => $editrecord ? s($editrecord->notes) : '',
    'class'       => 'ld-input',
    'placeholder' => get_string('optional'),
]);
echo html_writer::end_div();
echo html_writer::end_div();

// Submit.
echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-sm-9 offset-sm-3');
echo html_writer::tag('button', get_string('roicosts_savecourse', 'local_leducon'),
    ['type' => 'submit', 'class' => 'ld-btn ld-btn-primary']);
if ($editrecord) {
    echo html_writer::link(
        new moodle_url('/local/leducon/analytics/roi_costs.php'),
        get_string('cancel'),
        ['class' => 'ld-btn ld-btn-secondary']
    );
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

// ================================================================
// SAVED ENTRIES TABLE.
// ================================================================
if (!empty($savedcosts)) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('roicosts_saved_heading', 'local_leducon'),
        ['class' => 'card-title mb-3']);

    echo html_writer::start_div('ld-card');
    echo html_writer::start_tag('table', ['class' => 'ld-table']);
    echo html_writer::start_tag('thead', ['class' => 'thead-light']);
    echo html_writer::tag('tr',
        html_writer::tag('th', get_string('roicosts_col_course',   'local_leducon')) .
        html_writer::tag('th', get_string('roicosts_col_cost',     'local_leducon'), ['style' => 'width:130px;text-align:right']) .
        html_writer::tag('th', get_string('roicosts_col_value',    'local_leducon'), ['style' => 'width:150px;text-align:right']) .
        html_writer::tag('th', get_string('roicosts_col_currency', 'local_leducon'), ['style' => 'width:90px;text-align:center']) .
        html_writer::tag('th', get_string('roicosts_col_notes',    'local_leducon')) .
        html_writer::tag('th', get_string('actions'),              ['style' => 'width:140px;text-align:center'])
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($savedcosts as $sc) {
        $cost = number_format((float)$sc->costperlearner, 2);
        $val  = (float)$sc->valuepercompletion > 0
            ? number_format((float)$sc->valuepercompletion, 2)
            : html_writer::tag('span', 'auto (1.5x)', ['class' => 'text-muted']);

        $editurl = new moodle_url('/local/leducon/analytics/roi_costs.php', ['editid' => $sc->courseid]);
        $deleteurl = new moodle_url('/local/leducon/analytics/roi_costs.php', [
            'action'   => 'delete',
            'deleteid' => $sc->courseid,
            'sesskey'  => sesskey(),
        ]);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($sc->fullname));
        echo html_writer::tag('td', $cost, ['style' => 'text-align:right']);
        echo html_writer::tag('td', $val, ['style' => 'text-align:right']);
        echo html_writer::tag('td', s($sc->currency), ['style' => 'text-align:center']);
        echo html_writer::tag('td', s($sc->notes ?: ''));
        echo html_writer::tag('td',
            html_writer::link($editurl, get_string('edit'), ['class' => 'ld-btn ld-btn-sm ld-btn-primary']) .
            html_writer::link($deleteurl, get_string('delete'), [
                'class'   => 'ld-btn ld-btn-sm ld-btn-danger',
                'onclick' => "return confirm('" . get_string('roicosts_confirmdelete', 'local_leducon') . "');",
            ]),
            ['style' => 'text-align:center']
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();

    // Link to ROI Report.
    echo html_writer::tag('p',
        html_writer::link(
            new moodle_url('/local/leducon/analytics/roi.php'),
            get_string('roicosts_viewreport', 'local_leducon'),
            ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']
        ),
        ['class' => 'mt-2']
    );

    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo $OUTPUT->notification(get_string('roicosts_noentries', 'local_leducon'), 'info');
}

echo $OUTPUT->footer();
