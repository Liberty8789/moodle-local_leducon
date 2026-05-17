<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Skills management — create/edit/delete skills and map them to courses.
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

$tab    = optional_param('tab',    'skills', PARAM_ALPHA);
$action = optional_param('action', '',       PARAM_ALPHANUMEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/admin/skills_manage.php'));
$PAGE->set_title(get_string('skills_manage_title', 'local_leducon'));
$PAGE->set_heading(get_string('skills_manage_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('skills_manage_title', 'local_leducon'));

// Actions — Skills CRUD.
if ($action === 'save_skill' && confirm_sesskey()) {
    $skillid     = optional_param('skillid', 0, PARAM_INT);
    $name        = required_param('skillname', PARAM_TEXT);
    $description = optional_param('description', '', PARAM_TEXT);
    $name = trim($name);
    if ($name !== '') {
        $now = time();
        if ($skillid > 0) {
            $DB->update_record('local_leducon_skills', (object)[
                'id' => $skillid, 'name' => $name, 'description' => $description, 'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_leducon_skills', (object)[
                'name' => $name, 'description' => $description, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
    }
    redirect(new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'skills']),
        get_string('skill_saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete_skill' && confirm_sesskey()) {
    $skillid = required_param('skillid', PARAM_INT);
    $DB->delete_records('local_leducon_skills', ['id' => $skillid]);
    $DB->delete_records('local_leducon_course_skills', ['skillid' => $skillid]);
    redirect(new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'skills']),
        get_string('skill_deleted', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Actions — Course mapping.
if ($action === 'save_mapping' && confirm_sesskey()) {
    $courseid = required_param('courseid', PARAM_INT);
    $skillids = optional_param_array('skills', [], PARAM_INT);
    $DB->delete_records('local_leducon_course_skills', ['courseid' => $courseid]);
    foreach ($skillids as $sid) {
        if ($sid > 0) {
            $DB->insert_record('local_leducon_course_skills', (object)['courseid' => $courseid, 'skillid' => $sid]);
        }
    }
    redirect(new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'mapping']),
        get_string('skills_mapping_saved', 'local_leducon'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Load data.
$skills  = $DB->get_records('local_leducon_skills', null, 'name ASC');
$courses = $DB->get_records_sql(
    "SELECT c.id, c.fullname FROM {course} c WHERE c.id <> :siteid ORDER BY c.fullname ASC",
    ['siteid' => SITEID]
);
$mappings = [];
foreach ($DB->get_records('local_leducon_course_skills') as $m) {
    $mappings[$m->courseid][] = $m->skillid;
}

$editskill = null;
if ($action === 'edit_skill') {
    $skillid   = optional_param('skillid', 0, PARAM_INT);
    $editskill = $skillid > 0 ? $DB->get_record('local_leducon_skills', ['id' => $skillid]) : null;
}

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'skills', $USER->id);
echo $OUTPUT->heading(get_string('skills_manage_title', 'local_leducon'));

// Tabs.
echo '<ul class="nav nav-tabs mb-4">';
echo '<li class="nav-item"><a class="nav-link ' . ($tab === 'skills' ? 'active' : '') . '" href="' .
    (new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'skills']))->out(false) . '">' .
    s(get_string('skills_tab_skills', 'local_leducon')) . '</a></li>';
echo '<li class="nav-item"><a class="nav-link ' . ($tab === 'mapping' ? 'active' : '') . '" href="' .
    (new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'mapping']))->out(false) . '">' .
    s(get_string('skills_tab_mapping', 'local_leducon')) . '</a></li>';
echo '</ul>';

// Tab 1: Skills CRUD.
if ($tab === 'skills') {
    $formurl = new moodle_url('/local/leducon/admin/skills_manage.php');
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', $editskill ? s(get_string('skill_edit', 'local_leducon')) : s(get_string('skill_add', 'local_leducon')), ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save_skill']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'skills']);
    if ($editskill) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'skillid', 'value' => $editskill->id]);
    }
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('skill_name', 'local_leducon')) . ' <span class="text-danger">*</span>');
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'skillname', 'value' => $editskill ? s($editskill->name) : '', 'class' => 'ld-input', 'required' => 'required']);
    echo html_writer::end_div();
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('skill_description', 'local_leducon')));
    echo html_writer::tag('textarea', $editskill ? s($editskill->description) : '', ['name' => 'description', 'class' => 'ld-input', 'rows' => '2']);
    echo html_writer::end_div();
    echo html_writer::tag('button', s(get_string('skill_save', 'local_leducon')), ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']);
    if ($editskill) {
        echo ' ' . html_writer::link(new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'skills']), get_string('cancel'), ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
    }
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Skills list.
    if (empty($skills)) {
        echo $OUTPUT->notification(get_string('skill_noskills', 'local_leducon'), 'info');
    } else {
        echo html_writer::start_div('card');
        echo html_writer::start_div('card-body p-0');
        echo html_writer::start_div('ld-card');
        echo html_writer::start_tag('table', ['class' => 'ld-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::tag('tr',
            html_writer::tag('th', s(get_string('skill_name', 'local_leducon'))) .
            html_writer::tag('th', s(get_string('skill_description', 'local_leducon'))) .
            html_writer::tag('th', s(get_string('skill_courses', 'local_leducon')), ['style' => 'width:80px;text-align:center']) .
            html_writer::tag('th', '', ['style' => 'width:120px'])
        );
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        foreach ($skills as $skill) {
            $coursecount = $DB->count_records('local_leducon_course_skills', ['skillid' => $skill->id]);
            $editurl   = new moodle_url('/local/leducon/admin/skills_manage.php', ['tab' => 'skills', 'action' => 'edit_skill', 'skillid' => $skill->id]);
            $deleteurl = new moodle_url('/local/leducon/admin/skills_manage.php', ['action' => 'delete_skill', 'skillid' => $skill->id, 'sesskey' => sesskey()]);
            $actions   = html_writer::link($editurl, get_string('edit'), ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']) .
                html_writer::link($deleteurl, get_string('delete'), ['class' => 'ld-btn ld-btn-sm ld-btn-danger', 'onclick' => 'return confirm(' . json_encode(get_string('areyousure')) . ')']);
            echo html_writer::tag('tr',
                html_writer::tag('td', html_writer::tag('strong', s($skill->name))) .
                html_writer::tag('td', s($skill->description ?: '—')) .
                html_writer::tag('td', $coursecount, ['style' => 'text-align:center']) .
                html_writer::tag('td', $actions)
            );
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

// Tab 2: Course → Skills mapping.
if ($tab === 'mapping') {
    echo html_writer::tag('p', s(get_string('skills_mapping_intro', 'local_leducon')), ['class' => 'text-muted mb-3']);
    if (empty($skills)) {
        echo $OUTPUT->notification(get_string('skill_noskills', 'local_leducon'), 'warning');
    } else if (empty($courses)) {
        echo $OUTPUT->notification(get_string('skills_mapping_none', 'local_leducon'), 'info');
    } else {
        $formurl = new moodle_url('/local/leducon/admin/skills_manage.php');
        foreach ($courses as $course) {
            $mapped = $mappings[$course->id] ?? [];
            echo html_writer::start_div('card mb-3');
            echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
            echo html_writer::tag('span', s($course->fullname), ['class' => 'font-weight-bold']);
            if (!empty($mapped)) {
                echo html_writer::tag('span', count($mapped) . ' ' . get_string('nav_skills', 'local_leducon'), ['class' => 'badge badge-primary']);
            }
            echo html_writer::end_div();
            echo html_writer::start_div('card-body');
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save_mapping']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $course->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'mapping']);
            echo html_writer::start_div('d-flex flex-wrap');
            foreach ($skills as $skill) {
                $checked = in_array($skill->id, $mapped);
                $cbid    = 'skill_' . $course->id . '_' . $skill->id;
                echo html_writer::start_div('custom-control custom-checkbox mr-3 mb-2');
                echo html_writer::empty_tag('input', ['type' => 'checkbox', 'class' => 'custom-control-input', 'id' => $cbid, 'name' => 'skills[]', 'value' => $skill->id, 'checked' => $checked ? 'checked' : null]);
                echo html_writer::tag('label', s($skill->name), ['class' => 'custom-control-label', 'for' => $cbid]);
                echo html_writer::end_div();
            }
            echo html_writer::end_div();
            echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']);
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
    }
}

echo $OUTPUT->footer();
