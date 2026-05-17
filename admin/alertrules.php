<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Alert rules management — create/edit/delete conditional alert rules.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

require_login();
$syscontext = context_system::instance();
require_capability('local/leducon:managealerts', $syscontext);

$action = optional_param('action', 'list', PARAM_ALPHA);
$ruleid = optional_param('ruleid', 0, PARAM_INT);

$pageurl = new moodle_url('/local/leducon/admin/alertrules.php');
$PAGE->set_context($syscontext);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('alertrules_title', 'local_leducon'));
$PAGE->set_heading(get_string('alertrules_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('alertrules_title', 'local_leducon'));

$conditiontypes = [
    'inactive_days'    => get_string('condition_type_inactive_days', 'local_leducon'),
    'grade_below'      => get_string('condition_type_grade_below', 'local_leducon'),
    'completion_below' => get_string('condition_type_completion_below', 'local_leducon'),
    'attempts_zero'    => get_string('condition_type_attempts_zero', 'local_leducon'),
];

// Handle DELETE.
if ($action === 'delete' && $ruleid > 0) {
    require_sesskey();
    $DB->delete_records('local_leducon_alertrules', ['id' => $ruleid]);
    redirect($pageurl, get_string('alertrules_deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle SAVE (add/edit).
if ($action === 'save') {
    require_sesskey();
    $name            = required_param('name', PARAM_TEXT);
    $condition_type  = required_param('condition_type', PARAM_ALPHANUMEXT);
    $threshold       = optional_param('threshold', 0, PARAM_FLOAT);
    $courseid        = optional_param('courseid', 0, PARAM_INT);
    $cohortid        = optional_param('cohortid', 0, PARAM_INT);
    $notify_teachers = optional_param('notify_teachers', 0, PARAM_INT);
    $notify_emails   = optional_param('notify_emails', '', PARAM_TEXT);
    $active          = optional_param('active', 0, PARAM_INT);
    $editid          = optional_param('editid', 0, PARAM_INT);

    if (empty(trim($name)) || !array_key_exists($condition_type, $conditiontypes)) {
        redirect($pageurl, get_string('error_invaliddata', 'local_leducon'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $record = (object)[
        'name'            => trim($name),
        'condition_type'  => $condition_type,
        'threshold'       => (float)$threshold,
        'courseid'        => (int)$courseid,
        'cohortid'        => (int)$cohortid,
        'notify_teachers' => $notify_teachers ? 1 : 0,
        'notify_emails'   => trim($notify_emails),
        'active'          => $active ? 1 : 0,
        'timemodified'    => time(),
    ];

    if ($editid > 0) {
        $record->id = $editid;
        $DB->update_record('local_leducon_alertrules', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_leducon_alertrules', $record);
    }
    redirect($pageurl, get_string('alertrules_saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'alertrules', $USER->id);
echo $OUTPUT->heading(get_string('alertrules_title', 'local_leducon'));

// Add/Edit form.
if ($action === 'add' || $action === 'edit') {
    $rule = ($action === 'edit' && $ruleid > 0)
        ? $DB->get_record('local_leducon_alertrules', ['id' => $ruleid])
        : (object)['id' => 0, 'name' => '', 'condition_type' => 'inactive_days',
            'threshold' => 14, 'courseid' => 0, 'cohortid' => 0,
            'notify_teachers' => 1, 'notify_emails' => '', 'active' => 1];

    $formurl = new moodle_url('/local/leducon/admin/alertrules.php');
    echo html_writer::start_div('card mb-4 p-4');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',  'value' => 'save']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'editid',  'value' => $rule->id]);

    // Name.
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_name', 'local_leducon')), ['class' => 'col-sm-3 col-form-label', 'for' => 'rule_name']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'rule_name', 'name' => 'name', 'value' => s($rule->name), 'class' => 'ld-input', 'required' => 'required']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Condition type.
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_condition', 'local_leducon')), ['class' => 'col-sm-3 col-form-label', 'for' => 'rule_ctype']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::select($conditiontypes, 'condition_type', $rule->condition_type, false, ['id' => 'rule_ctype', 'class' => 'ld-input']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Threshold.
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_threshold', 'local_leducon')), ['class' => 'col-sm-3 col-form-label', 'for' => 'rule_thresh']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'rule_thresh', 'name' => 'threshold', 'value' => $rule->threshold, 'class' => 'ld-input', 'step' => '0.1', 'min' => '0']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Course.
    $courses = [0 => get_string('filter_allcourses', 'local_leducon')] +
        $DB->get_records_menu('course', null, 'fullname ASC', 'id,fullname', 0, 500);
    unset($courses[SITEID]);
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_course', 'local_leducon')), ['class' => 'col-sm-3 col-form-label']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::select($courses, 'courseid', $rule->courseid, false, ['class' => 'ld-input']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Cohort.
    $cohorts = [0 => get_string('filter_allcohorts', 'local_leducon')] +
        $DB->get_records_menu('cohort', [], 'name ASC', 'id,name', 0, 200);
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_cohort', 'local_leducon')), ['class' => 'col-sm-3 col-form-label']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::select($cohorts, 'cohortid', $rule->cohortid, false, ['class' => 'ld-input']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Notify teachers.
    $nt = !empty($rule->notify_teachers) ? ['checked' => 'checked'] : [];
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_notify_teachers', 'local_leducon')), ['class' => 'col-sm-3 col-form-label']);
    echo html_writer::start_div('col-sm-9 pt-2');
    echo html_writer::empty_tag('input', array_merge(['type' => 'checkbox', 'name' => 'notify_teachers', 'value' => '1'], $nt));
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Extra emails.
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_notify_emails', 'local_leducon')), ['class' => 'col-sm-3 col-form-label']);
    echo html_writer::start_div('col-sm-9');
    echo html_writer::tag('textarea', s($rule->notify_emails), ['name' => 'notify_emails', 'class' => 'ld-input', 'rows' => 3, 'placeholder' => 'email1@example.com, email2@example.com']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Active.
    $ac = !empty($rule->active) ? ['checked' => 'checked'] : [];
    echo html_writer::start_div('form-group row');
    echo html_writer::tag('label', s(get_string('alertrules_active', 'local_leducon')), ['class' => 'col-sm-3 col-form-label']);
    echo html_writer::start_div('col-sm-9 pt-2');
    echo html_writer::empty_tag('input', array_merge(['type' => 'checkbox', 'name' => 'active', 'value' => '1'], $ac));
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Buttons.
    echo html_writer::start_div('form-group row');
    echo html_writer::start_div('col-sm-9 offset-sm-3');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => s(get_string('alertrules_save', 'local_leducon')), 'class' => 'ld-btn ld-btn-primary']);
    echo html_writer::link($pageurl, s(get_string('cancel')), ['class' => 'ld-btn ld-btn-secondary']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div();

} else {
    // List view.
    echo html_writer::link(
        new moodle_url('/local/leducon/admin/alertrules.php', ['action' => 'add']),
        '+ ' . s(get_string('alertrules_add', 'local_leducon')),
        ['class' => 'ld-btn ld-btn-sm ld-btn-success']);

    $rules = $DB->get_records('local_leducon_alertrules', null, 'name ASC');

    if (empty($rules)) {
        echo $OUTPUT->notification(get_string('alertrules_nodata', 'local_leducon'), 'info');
    } else {
        echo html_writer::start_div('ld-card');
        echo html_writer::start_tag('table', ['class' => 'ld-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        foreach ([
            get_string('alertrules_name', 'local_leducon'),
            get_string('alertrules_condition', 'local_leducon'),
            get_string('alertrules_threshold', 'local_leducon'),
            get_string('alertrules_course', 'local_leducon'),
            get_string('alertrules_cohort', 'local_leducon'),
            get_string('alertrules_notify_teachers', 'local_leducon'),
            get_string('alertrules_active', 'local_leducon'),
            get_string('actions', 'local_leducon'),
        ] as $h) {
            echo html_writer::tag('th', s($h));
        }
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($rules as $rule) {
            $condlabel   = $conditiontypes[$rule->condition_type] ?? $rule->condition_type;
            $courselabel = $rule->courseid > 0 ? s($DB->get_field('course', 'fullname', ['id' => $rule->courseid]) ?: '-') : '-';
            $cohortlabel = $rule->cohortid > 0 ? s($DB->get_field('cohort', 'name', ['id' => $rule->cohortid]) ?: '-') : '-';
            $editurl     = new moodle_url('/local/leducon/admin/alertrules.php', ['action' => 'edit', 'ruleid' => $rule->id]);
            $deleteurl   = new moodle_url('/local/leducon/admin/alertrules.php', ['action' => 'delete', 'ruleid' => $rule->id, 'sesskey' => sesskey()]);

            echo html_writer::start_tag('tr', $rule->active ? [] : ['class' => 'text-muted']);
            echo html_writer::tag('td', s($rule->name));
            echo html_writer::tag('td', s($condlabel));
            echo html_writer::tag('td', s((string)$rule->threshold));
            echo html_writer::tag('td', $courselabel);
            echo html_writer::tag('td', $cohortlabel);
            echo html_writer::tag('td', $rule->notify_teachers ? '&#10003;' : '-');
            echo html_writer::tag('td', $rule->active
                ? html_writer::tag('span', 'Active', ['class' => 'badge badge-success'])
                : html_writer::tag('span', 'Inactive', ['class' => 'badge badge-secondary']));
            echo html_writer::tag('td',
                html_writer::link($editurl, get_string('edit'), ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']) .
                html_writer::link($deleteurl, get_string('delete'), ['class' => 'ld-btn ld-btn-sm ld-btn-danger',
                    'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete', 'local_leducon')) . ')']));
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
