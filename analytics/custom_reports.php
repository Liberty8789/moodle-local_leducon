<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Custom Report Builder — list, create, edit, delete, and schedule reports.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/leducon:managecustomreports', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/local/leducon/analytics/custom_reports.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('customreport_title', 'local_leducon'));
$PAGE->set_heading(get_string('customreport_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/analytics/index.php'));
$PAGE->navbar->add(get_string('customreport_title', 'local_leducon'));

use local_leducon\reports\custom_report;

// ─── POST HANDLERS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($action === 'save') {
        $name        = required_param('name', PARAM_TEXT);
        $description = optional_param('description', '', PARAM_TEXT);
        $datasource  = required_param('datasource', PARAM_ALPHANUMEXT);
        $shared      = optional_param('shared', 0, PARAM_INT);

        $columns = [];
        $allcols = custom_report::get_datasource_columns($datasource);
        foreach (array_keys($allcols) as $colkey) {
            if (optional_param('col_' . $colkey, 0, PARAM_INT)) {
                $columns[] = $colkey;
            }
        }

        $condfields = optional_param_array('cond_field', [], PARAM_ALPHANUMEXT);
        $condops    = optional_param_array('cond_op', [], PARAM_ALPHANUMEXT);
        $condvals   = optional_param_array('cond_val', [], PARAM_RAW);
        $conditions = [];
        foreach ($condfields as $i => $cf) {
            if (!empty($cf)) {
                $conditions[] = [
                    'field'    => $cf,
                    'operator' => $condops[$i] ?? 'eq',
                    'value'    => clean_param($condvals[$i] ?? '', PARAM_TEXT),
                ];
            }
        }

        $orderby  = optional_param('orderby', '', PARAM_ALPHANUMEXT);
        $orderdir = optional_param('orderdir', 'ASC', PARAM_ALPHA);

        $scope = [
            'cohortid'    => optional_param('scope_cohortid', 0, PARAM_INT),
            'department'  => optional_param('scope_department', '', PARAM_TEXT),
            'institution' => optional_param('scope_institution', '', PARAM_TEXT),
            'orgunitid'   => optional_param('scope_orgunitid', 0, PARAM_INT),
            'country'     => optional_param('scope_country', '', PARAM_ALPHA),
            'city'        => optional_param('scope_city', '', PARAM_TEXT),
        ];

        $pf_fieldids = optional_param_array('scope_pf_fieldid', [], PARAM_INT);
        $pf_values   = optional_param_array('scope_pf_value', [], PARAM_TEXT);
        $profilefields = [];
        foreach ($pf_fieldids as $i => $fid) {
            $fval = trim($pf_values[$i] ?? '');
            if ((int)$fid > 0 && $fval !== '') {
                $profilefields[] = ['fieldid' => (int)$fid, 'value' => $fval];
            }
        }
        $scope['profilefields'] = $profilefields;

        $config = [
            'columns'    => $columns,
            'conditions' => $conditions,
            'scope'      => $scope,
            'orderby'    => $orderby,
            'orderdir'   => $orderdir,
        ];

        if ($id > 0) {
            $existing = $DB->get_record('local_leducon_custom_rpts', ['id' => $id]);
            if (!$existing || ((int)$existing->createdby !== (int)$USER->id && !has_capability('local/leducon:manageall', $context))) {
                throw new moodle_exception('nopermissions', 'error', '', 'edit this report');
            }
            custom_report::update($id, $name, $description, $datasource, $config, (bool)$shared);
            $msg = get_string('customreport_updated', 'local_leducon');
        } else {
            $id = custom_report::create($name, $description, $datasource, $config, $USER->id, (bool)$shared);
            $msg = get_string('customreport_created', 'local_leducon');
        }
        redirect(new moodle_url('/local/leducon/analytics/custom_reports.php'), $msg, null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'delete' && $id > 0) {
        $existing = $DB->get_record('local_leducon_custom_rpts', ['id' => $id]);
        if (!$existing || ((int)$existing->createdby !== (int)$USER->id && !has_capability('local/leducon:manageall', $context))) {
            throw new moodle_exception('nopermissions', 'error', '', 'delete this report');
        }
        custom_report::delete($id);
        redirect($pageurl, get_string('customreport_deleted', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'duplicate' && $id > 0) {
        $existing = $DB->get_record('local_leducon_custom_rpts', ['id' => $id]);
        if ($existing) {
            $cfg = json_decode($existing->config, true) ?: [];
            custom_report::create(
                $existing->name . ' (' . get_string('cr_copy', 'local_leducon') . ')',
                $existing->description,
                $existing->datasource,
                $cfg,
                $USER->id,
                false
            );
        }
        redirect($pageurl, get_string('customreport_created', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'usetemplate') {
        $tplkey = required_param('template', PARAM_ALPHANUMEXT);
        custom_report::create_from_template($tplkey, $USER->id);
        redirect($pageurl, get_string('customreport_template_added', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'saveschedule') {
        $reportid   = optional_param('sched_reportid', 0, PARAM_INT);
        $reporttype = optional_param('sched_reporttype', '', PARAM_ALPHANUMEXT);
        $recipients = required_param('sched_recipients', PARAM_TEXT);
        $frequency  = optional_param('sched_frequency', 'weekly', PARAM_ALPHA);
        $dow        = optional_param('sched_dow', 1, PARAM_INT);
        $dom        = optional_param('sched_dom', 1, PARAM_INT);
        $hour       = optional_param('sched_hour', 7, PARAM_INT);
        $format     = optional_param('sched_format', 'csv', PARAM_ALPHA);

        $rec = new stdClass();
        $rec->reportid     = $reportid > 0 ? $reportid : null;
        $rec->reporttype   = !empty($reporttype) ? $reporttype : null;
        $rec->userid       = $USER->id;
        $rec->recipients   = $recipients;
        $rec->frequency    = $frequency;
        $rec->day_of_week  = $dow;
        $rec->day_of_month = $dom;
        $rec->hour         = max(0, min(23, $hour));
        $rec->format       = $format;
        $rec->enabled      = 1;
        $rec->last_sent    = 0;
        $rec->timecreated  = time();
        $rec->timemodified = time();
        $DB->insert_record('local_leducon_rpt_schedule', $rec);
        redirect($pageurl, get_string('schedule_saved', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'deleteschedule') {
        $schedid = required_param('schedid', PARAM_INT);
        $DB->delete_records('local_leducon_rpt_schedule', ['id' => $schedid, 'userid' => $USER->id]);
        redirect($pageurl, get_string('schedule_deleted', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'toggleschedule') {
        $schedid = required_param('schedid', PARAM_INT);
        $sched = $DB->get_record('local_leducon_rpt_schedule', ['id' => $schedid, 'userid' => $USER->id]);
        if ($sched) {
            $sched->enabled = $sched->enabled ? 0 : 1;
            $sched->timemodified = time();
            $DB->update_record('local_leducon_rpt_schedule', $sched);
        }
        redirect($pageurl);
    }
}

// ─── BUILD FORM DATA ──────────────────────────────────────────
$editreport = null;
$editconfig = [];
if ($action === 'edit' && $id > 0) {
    $editreport = $DB->get_record('local_leducon_custom_rpts', ['id' => $id]);
    if ($editreport) {
        $editconfig = json_decode($editreport->config ?? '{}', true) ?: [];
    }
}

$datasources  = custom_report::get_datasources();
$reports      = custom_report::get_user_reports($USER->id);
$reporttypes  = local_leducon_get_report_types();
$myschedules  = $DB->get_records('local_leducon_rpt_schedule', ['userid' => $USER->id], 'timecreated DESC');

// Datasource icons for visual flair.
$dsicons = [
    'completions'   => "\xF0\x9F\x8E\x93",
    'grades'        => "\xF0\x9F\x93\x8A",
    'enrollments'   => "\xF0\x9F\x93\x9D",
    'quiz_attempts' => "\xF0\x9F\x93\x9D",
    'logins'        => "\xF0\x9F\x94\x91",
    'assignments'   => "\xF0\x9F\x93\x8B",
    'users'         => "\xF0\x9F\x91\xA5",
];

// ─── RENDER ────────────────────────────────────────────────────
echo $OUTPUT->header();
local_leducon_render_nav('analytics', 'custom_reports', $USER->id);
echo $OUTPUT->heading(get_string('customreport_title', 'local_leducon'));
echo html_writer::tag('p', s(get_string('customreport_subtitle', 'local_leducon')), ['class' => 'text-muted mb-3']);

// ── Tab navigation ─────────────────────────────────────
$tab = optional_param('tab', 'list', PARAM_ALPHA);
if ($action === 'edit' || $action === 'new') {
    $tab = 'builder';
}

$tabicons = ['list' => "\xF0\x9F\x93\x84", 'builder' => "\xF0\x9F\x94\xA7", 'schedule' => "\xF0\x9F\x93\x85"];
$tabs = [
    'list'     => get_string('cr_tab_myreports', 'local_leducon'),
    'builder'  => get_string('cr_tab_builder', 'local_leducon'),
    'schedule' => get_string('cr_tab_schedules', 'local_leducon'),
];
echo html_writer::start_div('ld-tabs');
foreach ($tabs as $tkey => $tlabel) {
    $turl = new moodle_url($pageurl, ['tab' => $tkey]);
    $cls = 'ld-tab' . ($tkey === $tab ? ' active' : '');
    $icon = $tabicons[$tkey] ?? '';
    echo html_writer::link($turl, $icon . ' ' . s($tlabel), ['class' => $cls]);
}
echo html_writer::end_div();

// ══════════════════════════════════════════════════════════════
// TAB: MY REPORTS LIST
// ══════════════════════════════════════════════════════════════
if ($tab === 'list') {

    // ── User's saved reports ──────────────────────────────
    echo html_writer::tag('h4', s(get_string('cr_tab_myreports', 'local_leducon')),
        ['style' => 'margin-bottom:.75rem']);

    if (empty($reports)) {
        echo html_writer::start_div('ld-card', ['style' => 'padding:2rem;text-align:center']);
        echo html_writer::tag('div', "\xF0\x9F\x93\x84", ['style' => 'font-size:2.5rem;margin-bottom:.5rem']);
        echo html_writer::tag('p', s(get_string('customreport_nodata', 'local_leducon')),
            ['class' => 'text-muted', 'style' => 'margin:0']);
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('ld-card');
        echo html_writer::start_div('ld-table-wrap');
        echo html_writer::start_tag('table', ['class' => 'ld-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::tag('tr',
            html_writer::tag('th', get_string('cr_col_name', 'local_leducon')) .
            html_writer::tag('th', get_string('cr_col_source', 'local_leducon'), ['style' => 'width:150px']) .
            html_writer::tag('th', get_string('cr_col_shared', 'local_leducon'), ['style' => 'width:80px;text-align:center']) .
            html_writer::tag('th', get_string('cr_col_created', 'local_leducon'), ['style' => 'width:130px']) .
            html_writer::tag('th', get_string('cr_col_actions', 'local_leducon'), ['style' => 'width:280px'])
        );
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($reports as $r) {
            $dslabel = $datasources[$r->datasource]['label'] ?? $r->datasource;
            $icon = $dsicons[$r->datasource] ?? '';
            $viewurl = new moodle_url('/local/leducon/analytics/custom_report_view.php', ['id' => $r->id]);
            $editurl = new moodle_url($pageurl, ['action' => 'edit', 'id' => $r->id]);
            $ismine  = ((int)$r->createdby === (int)$USER->id);

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td',
                html_writer::link($viewurl, s($r->name), ['style' => 'font-weight:600;color:var(--ld-primary)']) .
                ($r->description ? html_writer::tag('div', s($r->description), ['class' => 'small text-muted', 'style' => 'margin-top:.15rem']) : '')
            );
            echo html_writer::tag('td',
                html_writer::tag('span', $icon . ' ' . s($dslabel), ['class' => 'ld-badge ld-badge-secondary'])
            );
            echo html_writer::tag('td',
                $r->shared
                    ? html_writer::tag('span', get_string('cr_shared_yes', 'local_leducon'), ['class' => 'ld-badge ld-badge-info'])
                    : html_writer::tag('span', get_string('cr_shared_no', 'local_leducon'), ['class' => 'ld-badge ld-badge-secondary']),
                ['style' => 'text-align:center']
            );
            echo html_writer::tag('td', userdate($r->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                ['class' => 'small']);

            // Actions column.
            echo html_writer::start_tag('td');
            echo html_writer::start_div('', ['style' => 'display:flex;gap:.35rem;flex-wrap:wrap;align-items:center']);
            echo html_writer::link($viewurl, get_string('cr_action_view', 'local_leducon'),
                ['class' => 'ld-btn ld-btn-sm ld-btn-primary']);
            if ($ismine || has_capability('local/leducon:manageall', $context)) {
                echo html_writer::link($editurl, get_string('cr_action_edit', 'local_leducon'),
                    ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
            }
            // Duplicate.
            echo html_writer::start_tag('form', [
                'method' => 'post', 'action' => $pageurl->out(false), 'style' => 'display:inline;margin:0',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'duplicate']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $r->id]);
            echo html_writer::tag('button', get_string('cr_action_duplicate', 'local_leducon'),
                ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
            echo html_writer::end_tag('form');
            // Delete.
            if ($ismine || has_capability('local/leducon:manageall', $context)) {
                echo html_writer::start_tag('form', [
                    'method' => 'post', 'action' => $pageurl->out(false), 'style' => 'display:inline;margin:0',
                    'onsubmit' => 'return confirm("' . s(get_string('customreport_confirmdelete', 'local_leducon')) . '")',
                ]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $r->id]);
                echo html_writer::tag('button', get_string('cr_action_delete', 'local_leducon'),
                    ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-danger']);
                echo html_writer::end_tag('form');
            }
            echo html_writer::end_div();
            echo html_writer::end_tag('td');
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div(); // table-wrap.
        echo html_writer::end_div(); // card.
    }

    echo html_writer::tag('p',
        html_writer::link(
            new moodle_url($pageurl, ['action' => 'new']),
            '+ ' . get_string('customreport_create', 'local_leducon'),
            ['class' => 'ld-btn ld-btn-primary', 'style' => 'margin-top:1rem']
        )
    );

    // ── Report Templates ──────────────────────────────────
    $templates = custom_report::get_templates();
    echo html_writer::tag('h4', s(get_string('customreport_templates', 'local_leducon')),
        ['style' => 'margin-top:2.5rem;margin-bottom:.25rem']);
    echo html_writer::tag('p', s(get_string('customreport_templates_desc', 'local_leducon')),
        ['class' => 'text-muted', 'style' => 'margin-bottom:1rem']);

    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.75rem']);
    foreach ($templates as $tkey => $tpl) {
        $dslabel = $datasources[$tpl['datasource']]['label'] ?? $tpl['datasource'];
        $icon = $dsicons[$tpl['datasource']] ?? "\xF0\x9F\x93\x84";
        $colcount = count($tpl['config']['columns'] ?? []);

        echo html_writer::start_div('ld-card', ['style' => 'padding:1rem;display:flex;flex-direction:column']);
        echo html_writer::start_div('', ['style' => 'display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem']);
        echo html_writer::tag('span', $icon, ['style' => 'font-size:1.5rem']);
        echo html_writer::start_div();
        echo html_writer::tag('h5', s($tpl['name']), ['style' => 'margin:0;font-size:.9rem;line-height:1.2']);
        echo html_writer::start_div('', ['style' => 'display:flex;gap:.35rem;margin-top:.2rem']);
        echo html_writer::tag('span', s($dslabel), ['class' => 'ld-badge ld-badge-info', 'style' => 'font-size:.7rem']);
        echo html_writer::tag('span', $colcount . ' ' . get_string('cr_field_columns', 'local_leducon'),
            ['class' => 'ld-badge ld-badge-secondary', 'style' => 'font-size:.7rem']);
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();

        echo html_writer::tag('p', s($tpl['description']),
            ['class' => 'text-muted small', 'style' => 'margin:0 0 .75rem;flex:1']);

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => $pageurl->out(false), 'style' => 'margin:0',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'usetemplate']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'template', 'value' => $tkey]);
        echo html_writer::tag('button', s(get_string('customreport_use_template', 'local_leducon')),
            ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary', 'style' => 'width:100%']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

// ══════════════════════════════════════════════════════════════
// TAB: REPORT BUILDER
// ══════════════════════════════════════════════════════════════
if ($tab === 'builder') {
    $curds = $editreport ? $editreport->datasource : 'completions';
    $curcols = $editconfig['columns'] ?? [];
    $curconds = $editconfig['conditions'] ?? [];
    $curorderby = $editconfig['orderby'] ?? '';
    $curorderdir = $editconfig['orderdir'] ?? 'ASC';

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $pageurl->out(false),
        'id'     => 'ld-cr-builder',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
    if ($editreport) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $editreport->id]);
    }

    // ── Section 1: Basic Info ─────────────────────────────
    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1rem']);
    echo html_writer::tag('h5', "\xF0\x9F\x93\x9D " . s(get_string($editreport ? 'customreport_edit' : 'customreport_create', 'local_leducon')),
        ['style' => 'margin:0 0 1rem;font-size:1rem']);

    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:1fr 1fr;gap:1rem']);

    echo html_writer::start_div('ld-form-group', ['style' => 'margin-bottom:0']);
    echo html_writer::tag('label', s(get_string('cr_field_name', 'local_leducon')), ['class' => 'ld-form-label', 'for' => 'cr_name']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'name', 'id' => 'cr_name',
        'value' => s($editreport->name ?? ''),
        'class' => 'ld-input', 'required' => 'required',
        'placeholder' => get_string('cr_name_placeholder', 'local_leducon'),
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin-bottom:0']);
    echo html_writer::tag('label', s(get_string('cr_field_datasource', 'local_leducon')), ['class' => 'ld-form-label']);
    $dsopts = [];
    foreach ($datasources as $dskey => $dsmeta) {
        $dsopts[$dskey] = ($dsicons[$dskey] ?? '') . ' ' . $dsmeta['label'];
    }
    echo html_writer::select($dsopts, 'datasource', $curds, false, [
        'class' => 'ld-input', 'id' => 'cr_datasource',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div(); // grid.

    echo html_writer::start_div('ld-form-group', ['style' => 'margin-top:1rem;margin-bottom:0']);
    echo html_writer::tag('label', s(get_string('cr_field_description', 'local_leducon')),
        ['class' => 'ld-form-label', 'for' => 'cr_desc']);
    echo html_writer::tag('textarea', s($editreport->description ?? ''), [
        'name' => 'description', 'id' => 'cr_desc', 'rows' => 2,
        'class' => 'ld-input', 'style' => 'resize:vertical',
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div(); // card.

    // ── Section 2: Columns ────────────────────────────────
    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1rem']);
    echo html_writer::tag('h5', "\xF0\x9F\x93\x8B " . s(get_string('cr_field_columns', 'local_leducon')),
        ['style' => 'margin:0 0 .5rem;font-size:1rem']);
    echo html_writer::tag('p', s(get_string('cr_columns_desc', 'local_leducon')),
        ['class' => 'text-muted small', 'style' => 'margin-bottom:.75rem']);

    foreach ($datasources as $dskey => $dsmeta) {
        $dscols = custom_report::get_datasource_columns($dskey);
        $vis = ($dskey === $curds) ? '' : 'display:none';
        echo html_writer::start_div('cr-colgroup', [
            'id' => 'colgroup_' . $dskey,
            'style' => $vis . ';display:' . ($dskey === $curds ? 'grid' : 'none') . ';grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.5rem',
        ]);
        foreach ($dscols as $colkey => $colmeta) {
            $checked = in_array($colkey, $curcols) && $dskey === $curds;
            $chkstyle = 'background:' . ($checked ? 'var(--ld-primary-light,#dbeafe)' : '#f9fafb') .
                ';border:1px solid ' . ($checked ? 'var(--ld-primary,#2563eb)' : '#e5e7eb') .
                ';border-radius:6px;padding:.5rem .625rem;cursor:pointer;transition:all .15s';
            echo html_writer::start_tag('label', ['style' => $chkstyle, 'class' => 'cr-col-label']);
            echo html_writer::checkbox('col_' . $colkey, 1, $checked, $colmeta['label'], ['class' => 'cr-col-check']);
            echo html_writer::end_tag('label');
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // card.

    // ── Section 3: Scope Filters ──────────────────────────
    $scopeopts = custom_report::get_scope_options();
    $curscope = $editconfig['scope'] ?? [];

    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1rem']);
    echo html_writer::tag('h5', "\xF0\x9F\x8E\xAF " . s(get_string('cr_scope_heading', 'local_leducon')),
        ['style' => 'margin:0 0 .25rem;font-size:1rem']);
    echo html_writer::tag('p', s(get_string('cr_scope_desc', 'local_leducon')),
        ['class' => 'text-muted small', 'style' => 'margin-bottom:.75rem']);

    // Row 1: Cohort + Department + Institution.
    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-bottom:.75rem']);

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_scope_cohort', 'local_leducon')), ['class' => 'ld-form-label small']);
    $cohortopts = [0 => '-- ' . get_string('cr_scope_all', 'local_leducon') . ' --'];
    foreach ($scopeopts['cohorts'] as $cid => $cname) { $cohortopts[$cid] = $cname; }
    echo html_writer::select($cohortopts, 'scope_cohortid', $curscope['cohortid'] ?? 0, false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_scope_department', 'local_leducon')), ['class' => 'ld-form-label small']);
    $deptopts = ['' => '-- ' . get_string('cr_scope_all', 'local_leducon') . ' --'];
    foreach ($scopeopts['departments'] as $d) { $deptopts[$d] = $d; }
    echo html_writer::select($deptopts, 'scope_department', $curscope['department'] ?? '', false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_scope_institution', 'local_leducon')), ['class' => 'ld-form-label small']);
    $instopts = ['' => '-- ' . get_string('cr_scope_all', 'local_leducon') . ' --'];
    foreach ($scopeopts['institutions'] as $inst) { $instopts[$inst] = $inst; }
    echo html_writer::select($instopts, 'scope_institution', $curscope['institution'] ?? '', false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Row 2: Org Unit + Country + City.
    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem']);

    if (!empty($scopeopts['orgunits'])) {
        echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
        echo html_writer::tag('label', s(get_string('cr_scope_orgunit', 'local_leducon')), ['class' => 'ld-form-label small']);
        $orgopts = [0 => '-- ' . get_string('cr_scope_all', 'local_leducon') . ' --'];
        foreach ($scopeopts['orgunits'] as $oid => $oname) { $orgopts[$oid] = $oname; }
        echo html_writer::select($orgopts, 'scope_orgunitid', $curscope['orgunitid'] ?? 0, false, ['class' => 'ld-input']);
        echo html_writer::end_div();
    }

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_scope_country', 'local_leducon')), ['class' => 'ld-form-label small']);
    $countryopts = ['' => '-- ' . get_string('cr_scope_all', 'local_leducon') . ' --'];
    foreach ($scopeopts['countries'] as $code => $cname) { $countryopts[$code] = $cname; }
    echo html_writer::select($countryopts, 'scope_country', $curscope['country'] ?? '', false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_scope_city', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'scope_city',
        'value' => s($curscope['city'] ?? ''),
        'class' => 'ld-input',
        'placeholder' => get_string('cr_scope_city_placeholder', 'local_leducon'),
    ]);
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Profile fields.
    if (!empty($scopeopts['profilefields'])) {
        echo html_writer::tag('label', s(get_string('cr_scope_profilefields', 'local_leducon')),
            ['class' => 'ld-form-label small', 'style' => 'margin-top:.75rem']);
        $curpfs = $curscope['profilefields'] ?? [];
        $pfcount = max(1, count($curpfs));
        echo html_writer::start_div('', ['id' => 'cr_scope_pfs']);
        for ($pi = 0; $pi < $pfcount; $pi++) {
            $pfid = $curpfs[$pi]['fieldid'] ?? 0;
            $pfval = $curpfs[$pi]['value'] ?? '';
            echo html_writer::start_div('', ['style' => 'display:flex;gap:.5rem;margin-bottom:.35rem;flex-wrap:wrap']);
            echo '<select name="scope_pf_fieldid[]" class="ld-input" style="max-width:220px">';
            echo '<option value="0">-- ' . s(get_string('cr_scope_selectfield', 'local_leducon')) . ' --</option>';
            foreach ($scopeopts['profilefields'] as $fid => $fname) {
                $sel = ((int)$pfid === (int)$fid) ? ' selected' : '';
                echo '<option value="' . (int)$fid . '"' . $sel . '>' . s($fname) . '</option>';
            }
            echo '</select>';
            echo html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'scope_pf_value[]',
                'value' => s($pfval), 'class' => 'ld-input', 'style' => 'max-width:220px',
                'placeholder' => get_string('cr_cond_value', 'local_leducon'),
            ]);
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
        echo html_writer::tag('button', '+ ' . get_string('cr_scope_add_profile', 'local_leducon'), [
            'type' => 'button', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary', 'id' => 'cr_add_pf',
            'style' => 'margin-top:.25rem',
        ]);
    }

    echo html_writer::end_div(); // card.

    // ── Section 4: Conditions ─────────────────────────────
    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1rem']);
    echo html_writer::tag('h5', "\xF0\x9F\x94\x8D " . s(get_string('cr_field_conditions', 'local_leducon')),
        ['style' => 'margin:0 0 .25rem;font-size:1rem']);
    echo html_writer::tag('p', s(get_string('cr_conditions_desc', 'local_leducon')),
        ['class' => 'text-muted small', 'style' => 'margin-bottom:.75rem']);

    echo html_writer::start_div('', ['id' => 'cr_conditions']);
    $condcount = max(1, count($curconds));
    $operators = custom_report::get_operators();

    for ($ci = 0; $ci < $condcount; $ci++) {
        $cf = $curconds[$ci]['field'] ?? '';
        $co = $curconds[$ci]['operator'] ?? 'eq';
        $cv = $curconds[$ci]['value'] ?? '';

        echo html_writer::start_div('ld-condition-row', [
            'style' => 'display:grid;grid-template-columns:1fr auto 1fr auto;gap:.5rem;margin-bottom:.5rem;align-items:center',
        ]);
        echo '<select name="cond_field[]" class="ld-input cr-cond-field">';
        echo '<option value="">-- ' . s(get_string('cr_cond_selectfield', 'local_leducon')) . ' --</option>';
        $dscols = custom_report::get_datasource_columns($curds);
        foreach ($dscols as $colkey => $colmeta) {
            $sel = ($colkey === $cf) ? ' selected' : '';
            echo '<option value="' . s($colkey) . '"' . $sel . '>' . s($colmeta['label']) . '</option>';
        }
        echo '</select>';

        echo '<select name="cond_op[]" class="ld-input" style="max-width:140px">';
        foreach ($operators as $okey => $olabel) {
            $sel = ($okey === $co) ? ' selected' : '';
            echo '<option value="' . s($okey) . '"' . $sel . '>' . s($olabel) . '</option>';
        }
        echo '</select>';

        echo html_writer::empty_tag('input', [
            'type' => 'text', 'name' => 'cond_val[]',
            'value' => s($cv), 'class' => 'ld-input',
            'placeholder' => get_string('cr_cond_value', 'local_leducon'),
        ]);

        echo html_writer::tag('button', "\xC3\x97", [
            'type' => 'button', 'class' => 'ld-btn ld-btn-sm ld-btn-danger cr-remove-cond',
            'title' => get_string('cr_action_delete', 'local_leducon'),
            'style' => 'padding:.25rem .5rem;line-height:1',
        ]);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::tag('button', '+ ' . get_string('cr_add_condition', 'local_leducon'), [
        'type' => 'button', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary', 'id' => 'cr_add_cond',
    ]);

    echo html_writer::end_div(); // card.

    // ── Section 5: Sort + Options ─────────────────────────
    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1rem']);
    echo html_writer::tag('h5', "\xE2\x9A\x99\xEF\xB8\x8F " . s(get_string('cr_field_options', 'local_leducon')),
        ['style' => 'margin:0 0 .75rem;font-size:1rem']);

    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:1fr 160px auto;gap:.75rem;align-items:end']);

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_field_orderby', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo '<select name="orderby" class="ld-input" id="cr_orderby">';
    echo '<option value="">--</option>';
    foreach (custom_report::get_datasource_columns($curds) as $colkey => $colmeta) {
        $sel = ($colkey === $curorderby) ? ' selected' : '';
        echo '<option value="' . s($colkey) . '"' . $sel . '>' . s($colmeta['label']) . '</option>';
    }
    echo '</select>';
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('cr_field_direction', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::select(
        ['ASC' => get_string('cr_sort_asc', 'local_leducon'), 'DESC' => get_string('cr_sort_desc', 'local_leducon')],
        'orderdir', $curorderdir, false, ['class' => 'ld-input']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0;padding-bottom:.35rem']);
    echo html_writer::checkbox('shared', 1, $editreport ? (bool)$editreport->shared : false,
        get_string('cr_field_shared', 'local_leducon'));
    echo html_writer::end_div();

    echo html_writer::end_div(); // grid.
    echo html_writer::end_div(); // card.

    // ── Submit bar ────────────────────────────────────────
    echo html_writer::start_div('', ['style' => 'display:flex;gap:.75rem;align-items:center']);
    echo html_writer::tag('button', s(get_string('cr_save_report', 'local_leducon')),
        ['type' => 'submit', 'class' => 'ld-btn ld-btn-primary']);
    echo html_writer::link($pageurl, s(get_string('cr_cancel', 'local_leducon')),
        ['class' => 'ld-btn ld-btn-secondary']);
    echo html_writer::end_div();

    echo html_writer::end_tag('form');

    // Emit column definitions as JSON for JS.
    $jscols = [];
    foreach ($datasources as $dskey => $dsmeta) {
        $dscols = custom_report::get_datasource_columns($dskey);
        $jscols[$dskey] = [];
        foreach ($dscols as $colkey => $colmeta) {
            $jscols[$dskey][$colkey] = $colmeta['label'];
        }
    }
    echo '<script>var LD_CR_COLUMNS = ' . json_encode($jscols, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';

    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {

        function toggleGroupInputs(ds) {
            document.querySelectorAll(".cr-colgroup").forEach(function(el) {
                var isActive = el.id === "colgroup_" + ds;
                el.style.display = isActive ? "grid" : "none";
                el.querySelectorAll("input").forEach(function(inp) { inp.disabled = !isActive; });
            });
        }

        function rebuildFieldSelects(ds) {
            var cols = LD_CR_COLUMNS[ds] || {};
            var placeholder = ' . json_encode('-- ' . get_string('cr_cond_selectfield', 'local_leducon') . ' --') . ';
            document.querySelectorAll(".cr-cond-field").forEach(function(sel) {
                sel.innerHTML = "";
                var blank = document.createElement("option");
                blank.value = ""; blank.textContent = placeholder;
                sel.appendChild(blank);
                for (var key in cols) {
                    if (cols.hasOwnProperty(key)) {
                        var opt = document.createElement("option");
                        opt.value = key; opt.textContent = cols[key];
                        sel.appendChild(opt);
                    }
                }
            });
            var orderSel = document.getElementById("cr_orderby");
            if (orderSel) {
                orderSel.innerHTML = "";
                var blank = document.createElement("option");
                blank.value = ""; blank.textContent = "--";
                orderSel.appendChild(blank);
                for (var key in cols) {
                    if (cols.hasOwnProperty(key)) {
                        var opt = document.createElement("option");
                        opt.value = key; opt.textContent = cols[key];
                        orderSel.appendChild(opt);
                    }
                }
            }
        }

        function updateColLabels() {
            document.querySelectorAll(".cr-col-label").forEach(function(lbl) {
                var chk = lbl.querySelector("input[type=checkbox]");
                if (!chk) return;
                if (chk.checked) {
                    lbl.style.background = "var(--ld-primary-light, #dbeafe)";
                    lbl.style.borderColor = "var(--ld-primary, #2563eb)";
                } else {
                    lbl.style.background = "#f9fafb";
                    lbl.style.borderColor = "#e5e7eb";
                }
            });
        }

        document.addEventListener("change", function(e) {
            if (e.target.classList.contains("cr-col-check")) { updateColLabels(); }
        });

        var dsSelect = document.getElementById("cr_datasource");
        if (dsSelect) {
            toggleGroupInputs(dsSelect.value);
            dsSelect.addEventListener("change", function() {
                toggleGroupInputs(this.value);
                rebuildFieldSelects(this.value);
                updateColLabels();
            });
        }

        document.getElementById("cr_add_cond").addEventListener("click", function() {
            var container = document.getElementById("cr_conditions");
            var rows = container.querySelectorAll(".ld-condition-row");
            if (rows.length > 0) {
                var clone = rows[rows.length - 1].cloneNode(true);
                clone.querySelectorAll("input").forEach(function(inp) { inp.value = ""; });
                clone.querySelectorAll("select").forEach(function(sel) { sel.selectedIndex = 0; });
                container.appendChild(clone);
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("cr-remove-cond")) {
                var row = e.target.closest(".ld-condition-row");
                var container = document.getElementById("cr_conditions");
                if (container.querySelectorAll(".ld-condition-row").length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll("input").forEach(function(inp) { inp.value = ""; });
                    row.querySelectorAll("select").forEach(function(sel) { sel.selectedIndex = 0; });
                }
            }
        });

        var addPfBtn = document.getElementById("cr_add_pf");
        if (addPfBtn) {
            addPfBtn.addEventListener("click", function() {
                var container = document.getElementById("cr_scope_pfs");
                if (!container) return;
                var rows = container.children;
                if (rows.length > 0 && rows.length < 5) {
                    var clone = rows[rows.length - 1].cloneNode(true);
                    clone.querySelectorAll("input").forEach(function(inp) { inp.value = ""; });
                    clone.querySelectorAll("select").forEach(function(sel) { sel.selectedIndex = 0; });
                    container.appendChild(clone);
                }
            });
        }
    });
    </script>';
}

// ══════════════════════════════════════════════════════════════
// TAB: SCHEDULED DELIVERIES
// ══════════════════════════════════════════════════════════════
if ($tab === 'schedule') {

    // ── New schedule form ─────────────────────────────────
    echo html_writer::start_div('ld-card', ['style' => 'padding:1.25rem;margin-bottom:1.25rem']);
    echo html_writer::tag('h5', "\xF0\x9F\x93\x85 " . s(get_string('schedule_new_heading', 'local_leducon')),
        ['style' => 'margin:0 0 .25rem;font-size:1rem']);
    echo html_writer::tag('p', s(get_string('schedule_new_desc', 'local_leducon')),
        ['class' => 'text-muted small', 'style' => 'margin-bottom:1rem']);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saveschedule']);

    // Report selector row.
    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem']);

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_report', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo '<select name="sched_reportid" class="ld-input">';
    echo '<option value="0">-- ' . s(get_string('cr_tab_myreports', 'local_leducon')) . ' --</option>';
    foreach ($reports as $r) {
        echo '<option value="' . (int)$r->id . '">' . s($r->name) . '</option>';
    }
    echo '</select>';
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_or_builtin', 'local_leducon')), ['class' => 'ld-form-label small']);
    $rtopts = ['' => '-- ' . get_string('nav_reports', 'local_leducon') . ' --'];
    foreach ($reporttypes as $rtk => $rtl) { $rtopts[$rtk] = $rtl; }
    echo html_writer::select($rtopts, 'sched_reporttype', '', false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Recipients.
    echo html_writer::start_div('ld-form-group', ['style' => 'margin-bottom:1rem']);
    echo html_writer::tag('label', s(get_string('schedule_recipients', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'sched_recipients',
        'class' => 'ld-input', 'required' => 'required',
        'placeholder' => 'email1@example.com, email2@example.com',
    ]);
    echo html_writer::end_div();

    // Frequency row.
    echo html_writer::start_div('', ['style' => 'display:grid;grid-template-columns:repeat(4,auto);gap:.75rem;align-items:end;margin-bottom:1rem']);

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_frequency', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::select(
        ['daily' => get_string('schedule_daily', 'local_leducon'), 'weekly' => get_string('schedule_weekly', 'local_leducon'), 'monthly' => get_string('schedule_monthly', 'local_leducon')],
        'sched_frequency', 'weekly', false, ['class' => 'ld-input']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_dow', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::select(
        [1 => get_string('monday', 'calendar'), 2 => get_string('tuesday', 'calendar'), 3 => get_string('wednesday', 'calendar'),
         4 => get_string('thursday', 'calendar'), 5 => get_string('friday', 'calendar'), 6 => get_string('saturday', 'calendar'),
         7 => get_string('sunday', 'calendar')],
        'sched_dow', 1, false, ['class' => 'ld-input']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_dom', 'local_leducon')), ['class' => 'ld-form-label small']);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'name' => 'sched_dom', 'value' => 1,
        'min' => 1, 'max' => 28, 'class' => 'ld-input',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('ld-form-group', ['style' => 'margin:0']);
    echo html_writer::tag('label', s(get_string('schedule_hour', 'local_leducon')), ['class' => 'ld-form-label small']);
    $houropts = [];
    for ($h = 0; $h <= 23; $h++) { $houropts[$h] = sprintf('%02d:00', $h); }
    echo html_writer::select($houropts, 'sched_hour', 7, false, ['class' => 'ld-input']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    echo html_writer::tag('button', s(get_string('schedule_save', 'local_leducon')),
        ['type' => 'submit', 'class' => 'ld-btn ld-btn-primary']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div(); // card.

    // ── Active schedules ──────────────────────────────────
    echo html_writer::tag('h5', s(get_string('schedule_active_heading', 'local_leducon')),
        ['style' => 'margin-bottom:.75rem']);

    if (empty($myschedules)) {
        echo html_writer::start_div('ld-card', ['style' => 'padding:2rem;text-align:center']);
        echo html_writer::tag('div', "\xF0\x9F\x93\x85", ['style' => 'font-size:2.5rem;margin-bottom:.5rem']);
        echo html_writer::tag('p', s(get_string('schedule_nodata', 'local_leducon')),
            ['class' => 'text-muted', 'style' => 'margin:0']);
        echo html_writer::end_div();
    } else {
        echo html_writer::start_div('ld-card');
        echo html_writer::start_div('ld-table-wrap');
        echo html_writer::start_tag('table', ['class' => 'ld-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::tag('tr',
            html_writer::tag('th', get_string('schedule_col_report', 'local_leducon')) .
            html_writer::tag('th', get_string('schedule_col_frequency', 'local_leducon')) .
            html_writer::tag('th', get_string('schedule_col_recipients', 'local_leducon')) .
            html_writer::tag('th', get_string('schedule_col_lastsent', 'local_leducon'), ['style' => 'width:130px']) .
            html_writer::tag('th', get_string('schedule_col_status', 'local_leducon'), ['style' => 'width:80px;text-align:center']) .
            html_writer::tag('th', get_string('cr_col_actions', 'local_leducon'), ['style' => 'width:170px'])
        );
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($myschedules as $s) {
            $rname = '?';
            if (!empty($s->reportid)) {
                $crpt = $DB->get_record('local_leducon_custom_rpts', ['id' => $s->reportid], 'name');
                $rname = $crpt ? $crpt->name : '(' . get_string('cr_action_delete', 'local_leducon') . ')';
            } elseif (!empty($s->reporttype)) {
                $rname = $reporttypes[$s->reporttype] ?? $s->reporttype;
            }

            $freqlabel = ucfirst($s->frequency);
            if ($s->frequency === 'weekly') {
                $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                $freqlabel .= ' (' . ($days[$s->day_of_week] ?? '?') . ')';
            } elseif ($s->frequency === 'monthly') {
                $freqlabel .= ' (' . get_string('schedule_dom', 'local_leducon') . ' ' . $s->day_of_month . ')';
            }
            $freqlabel .= ' @ ' . sprintf('%02d:00', $s->hour);

            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', s($rname), ['style' => 'font-weight:600']);
            echo html_writer::tag('td', s($freqlabel), ['class' => 'small']);
            echo html_writer::tag('td', s($s->recipients), ['class' => 'small']);
            echo html_writer::tag('td',
                $s->last_sent > 0 ? userdate($s->last_sent, get_string('strftimedatetimeshort', 'langconfig')) : '-',
                ['class' => 'small']
            );
            echo html_writer::tag('td',
                html_writer::tag('span',
                    $s->enabled ? get_string('schedule_active', 'local_leducon') : get_string('schedule_paused', 'local_leducon'),
                    ['class' => 'ld-badge ld-badge-' . ($s->enabled ? 'success' : 'secondary')]
                ),
                ['style' => 'text-align:center']
            );

            echo html_writer::start_tag('td');
            echo html_writer::start_div('', ['style' => 'display:flex;gap:.35rem']);
            // Toggle.
            echo html_writer::start_tag('form', [
                'method' => 'post', 'action' => $pageurl->out(false), 'style' => 'margin:0',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'toggleschedule']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'schedid', 'value' => $s->id]);
            echo html_writer::tag('button',
                $s->enabled ? get_string('schedule_pause', 'local_leducon') : get_string('schedule_resume', 'local_leducon'),
                ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary']
            );
            echo html_writer::end_tag('form');
            // Delete.
            echo html_writer::start_tag('form', [
                'method' => 'post', 'action' => $pageurl->out(false), 'style' => 'margin:0',
                'onsubmit' => 'return confirm("' . s(get_string('schedule_confirm_delete', 'local_leducon')) . '")',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deleteschedule']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'schedid', 'value' => $s->id]);
            echo html_writer::tag('button', get_string('cr_action_delete', 'local_leducon'),
                ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-danger']);
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
            echo html_writer::end_tag('td');
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div(); // table-wrap.
        echo html_writer::end_div(); // card.
    }
}

echo $OUTPUT->footer();
