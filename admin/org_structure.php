<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Organisation structure management — tree, members, CSV import.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\org\org_manager;
use local_leducon\org\org_helper;

require_login();
$context = context_system::instance();
require_capability('local/leducon:manageorgstructure', $context);

$tab    = optional_param('tab',    'tree',  PARAM_ALPHA);
$action = optional_param('action', '',      PARAM_ALPHANUMEXT);
$self_url = new moodle_url('/local/leducon/admin/org_structure.php');

// =====================================================================
// AJAX user search (before output).
// =====================================================================
if ($action === 'search_users') {
    require_sesskey();
    header('Content-Type: application/json');
    $q = trim(optional_param('q', '', PARAM_TEXT));
    $results = [];
    if (strlen($q) >= 2) {
        $fullname_sql = $DB->sql_fullname('firstname', 'lastname');
        $like1 = $DB->sql_like($fullname_sql, ':q1', false);
        $like2 = $DB->sql_like('email',        ':q2', false);
        $sql   = "SELECT id, firstname, lastname, email
                    FROM {user}
                   WHERE deleted = 0 AND suspended = 0 AND id <> :guestid
                     AND ($like1 OR $like2)
                ORDER BY lastname ASC, firstname ASC";
        $rows = $DB->get_records_sql($sql, [
            'guestid' => guest_user()->id,
            'q1'      => '%' . $DB->sql_like_escape($q) . '%',
            'q2'      => '%' . $DB->sql_like_escape($q) . '%',
        ], 0, 15);
        foreach ($rows as $u) {
            $results[] = ['id' => (int)$u->id, 'fullname' => fullname($u), 'email' => $u->email];
        }
    }
    echo json_encode(['results' => $results]);
    die;
}

// =====================================================================
// POST actions.
// =====================================================================

// Create / update org unit.
if ($action === 'save_unit' && confirm_sesskey()) {
    $unitid     = optional_param('unitid', 0, PARAM_INT);
    $name       = required_param('name', PARAM_TEXT);
    $shortname  = optional_param('shortname', '', PARAM_TEXT);
    $parentid   = optional_param('parentid', 0, PARAM_INT);
    $description = optional_param('description', '', PARAM_TEXT);
    $cohortid   = optional_param('cohortid', 0, PARAM_INT);
    $enabled    = optional_param('enabled', 1, PARAM_INT);
    $sortorder  = optional_param('sortorder', 0, PARAM_INT);

    $data = (object)[
        'name' => $name, 'shortname' => $shortname, 'parentid' => $parentid,
        'description' => $description, 'cohortid' => $cohortid,
        'enabled' => $enabled, 'sortorder' => $sortorder,
    ];

    if ($unitid > 0) {
        org_manager::update_unit($unitid, $data);
        $msg = get_string('org_unit_updated', 'local_leducon');
    } else {
        org_manager::create_unit($data);
        $msg = get_string('org_unit_created', 'local_leducon');
    }
    redirect(new moodle_url($self_url, ['tab' => 'tree']), $msg, null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Delete org unit.
if ($action === 'delete_unit' && confirm_sesskey()) {
    $unitid = required_param('unitid', PARAM_INT);
    if (org_manager::delete_unit($unitid)) {
        redirect(new moodle_url($self_url, ['tab' => 'tree']),
            get_string('org_unit_deleted', 'local_leducon'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect(new moodle_url($self_url, ['tab' => 'tree']),
            get_string('org_unit_haschildren', 'local_leducon'), null,
            \core\output\notification::NOTIFY_WARNING);
    }
}

// Add member.
if ($action === 'add_member' && confirm_sesskey()) {
    $orgunitid = required_param('orgunitid', PARAM_INT);
    $userid    = required_param('userid', PARAM_INT);
    org_manager::add_member($orgunitid, $userid);
    redirect(new moodle_url($self_url, ['tab' => 'members', 'orgunitid' => $orgunitid]),
        get_string('org_member_added', 'local_leducon'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Remove member.
if ($action === 'remove_member' && confirm_sesskey()) {
    $orgunitid = required_param('orgunitid', PARAM_INT);
    $userid    = required_param('userid', PARAM_INT);
    org_manager::remove_member($orgunitid, $userid);
    redirect(new moodle_url($self_url, ['tab' => 'members', 'orgunitid' => $orgunitid]),
        get_string('org_member_removed', 'local_leducon'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Sync cohort.
if ($action === 'sync_cohort' && confirm_sesskey()) {
    $orgunitid = required_param('orgunitid', PARAM_INT);
    $result = org_manager::sync_cohort($orgunitid);
    $msg = get_string('org_sync_result', 'local_leducon',
        (object)['added' => $result['added'], 'removed' => $result['removed']]);
    redirect(new moodle_url($self_url, ['tab' => 'members', 'orgunitid' => $orgunitid]),
        $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Rebuild paths.
if ($action === 'rebuild_paths' && confirm_sesskey()) {
    org_manager::rebuild_all_paths();
    redirect(new moodle_url($self_url, ['tab' => 'import']),
        get_string('org_rebuild_done', 'local_leducon'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// CSV import.
if ($action === 'import_csv' && confirm_sesskey()) {
    $dryrun = optional_param('dryrun', 0, PARAM_INT);
    $csvfile = $_FILES['csvfile'] ?? null;
    if ($csvfile && $csvfile['error'] === UPLOAD_ERR_OK) {
        $csvdata = file_get_contents($csvfile['tmp_name']);
        $result = org_manager::import_csv($csvdata, (bool)$dryrun);

        $SESSION->org_import_result = $result;
        $SESSION->org_import_dryrun = (bool)$dryrun;
    }
    redirect(new moodle_url($self_url, ['tab' => 'import']));
}

// =====================================================================
// PAGE SETUP
// =====================================================================

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/leducon/admin/org_structure.php', ['tab' => $tab]));
$PAGE->set_title(get_string('org_title', 'local_leducon'));
$PAGE->set_heading(get_string('org_title', 'local_leducon'));
$PAGE->set_pagelayout('report');
$PAGE->navbar->add(get_string('pluginname', 'local_leducon'), new moodle_url('/local/leducon/index.php'));
$PAGE->navbar->add(get_string('org_title', 'local_leducon'));

echo $OUTPUT->header();
local_leducon_render_nav('admin', 'org_structure', $USER->id);
echo $OUTPUT->heading(get_string('org_title', 'local_leducon'));
echo html_writer::tag('p', get_string('org_subtitle', 'local_leducon'), ['class' => 'text-muted']);

// =====================================================================
// TABS
// =====================================================================

echo '<ul class="nav nav-tabs mb-4">';
foreach (['tree' => 'org_tab_tree', 'members' => 'org_tab_members', 'import' => 'org_tab_import'] as $tkey => $tstr) {
    $active = ($tab === $tkey) ? ' active' : '';
    $url = (new moodle_url($self_url, ['tab' => $tkey]))->out(false);
    echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $url . '">'
        . s(get_string($tstr, 'local_leducon')) . '</a></li>';
}
echo '</ul>';

// =====================================================================
// TAB 1: TREE VIEW
// =====================================================================

if ($tab === 'tree') {
    $editid = optional_param('editid', 0, PARAM_INT);
    $editunit = $editid > 0 ? org_manager::get_unit($editid) : null;
    $addparent = optional_param('addparent', 0, PARAM_INT);

    // Cohort list for dropdown.
    $cohorts = $DB->get_records_menu('cohort', null, 'name ASC', 'id,name', 0, 200);

    // Parent options (flattened tree).
    $parentopts = org_helper::get_org_dropdown_options(false);
    unset($parentopts[0]); // Remove "All Organisation" label.
    $parentopts = [0 => get_string('org_root_level', 'local_leducon')] + $parentopts;

    // Remove self and descendants from parent options if editing.
    if ($editunit) {
        $descendants = org_manager::get_descendant_ids($editid);
        foreach ($descendants as $did) {
            unset($parentopts[$did]);
        }
    }

    // ── Add / Edit form ──
    $formurl = new moodle_url($self_url);
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', $editunit
        ? s(get_string('org_edit_unit', 'local_leducon'))
        : s(get_string('org_add_unit', 'local_leducon')), ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save_unit']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'tree']);
    if ($editunit) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitid', 'value' => $editunit->id]);
    }

    // Name.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_unit_name', 'local_leducon')) . ' <span class="text-danger">*</span>');
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'name', 'class' => 'ld-input', 'required' => 'required',
        'value' => $editunit ? s($editunit->name) : '',
    ]);
    echo html_writer::end_div();

    // Shortname.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_unit_shortname', 'local_leducon')));
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'shortname', 'class' => 'ld-input',
        'value' => $editunit ? s($editunit->shortname) : '',
        'placeholder' => 'e.g. MORT-OPS',
    ]);
    echo html_writer::end_div();

    // Parent.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_parent', 'local_leducon')));
    $selparent = $editunit ? (int)$editunit->parentid : $addparent;
    echo html_writer::select($parentopts, 'parentid', $selparent, false, ['class' => 'ld-input ld-select']);
    echo html_writer::end_div();

    // Description.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_description', 'local_leducon')));
    echo html_writer::tag('textarea', $editunit ? s($editunit->description) : '', [
        'name' => 'description', 'class' => 'ld-input', 'rows' => '2',
    ]);
    echo html_writer::end_div();

    // Cohort link.
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_linked_cohort', 'local_leducon')));
    $cohopts = [0 => get_string('org_no_cohort', 'local_leducon')] + $cohorts;
    echo html_writer::select($cohopts, 'cohortid', $editunit ? (int)$editunit->cohortid : 0, false,
        ['class' => 'ld-input ld-select']);
    echo html_writer::tag('small', get_string('org_cohort_hint', 'local_leducon'), ['class' => 'text-muted']);
    echo html_writer::end_div();

    // Sort order + enabled on same row.
    echo html_writer::start_div('d-flex gap-3');
    echo html_writer::start_div('form-group mr-3');
    echo html_writer::tag('label', s(get_string('sort_order', 'local_leducon')));
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'name' => 'sortorder', 'class' => 'ld-input', 'style' => 'width:100px',
        'value' => $editunit ? (int)$editunit->sortorder : 0,
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('enabled', 'local_leducon')));
    echo html_writer::select([1 => get_string('yes'), 0 => get_string('no')], 'enabled',
        $editunit ? (int)$editunit->enabled : 1, false, ['class' => 'ld-input ld-select']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Buttons.
    echo html_writer::tag('button', get_string('save'), ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']);
    if ($editunit) {
        echo ' ' . html_writer::link(new moodle_url($self_url, ['tab' => 'tree']),
            get_string('cancel'), ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
    }
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();

    // ── Tree display ──
    $tree = org_manager::get_tree(false);
    if (empty($tree)) {
        echo $OUTPUT->notification(get_string('org_notree', 'local_leducon'), 'info');
    } else {
        echo html_writer::start_div('card');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', s(get_string('org_tree_heading', 'local_leducon')), ['class' => 'mb-0']);
        echo html_writer::end_div();
        echo html_writer::start_div('card-body p-0');
        echo '<div class="ld-org-tree">';
        org_render_tree_nodes($tree, $self_url);
        echo '</div>';
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

/**
 * Recursively render tree nodes.
 */
function org_render_tree_nodes(array $nodes, moodle_url $self_url): void {
    foreach ($nodes as $node) {
        $haschildren = !empty($node->children);
        $cls = 'ld-org-node' . ($node->enabled ? '' : ' ld-org-disabled');

        echo '<div class="' . $cls . '">';
        echo '<div class="ld-org-node-row">';

        // Expand indicator.
        if ($haschildren) {
            echo '<span class="ld-org-toggle" onclick="this.parentNode.parentNode.classList.toggle(\'collapsed\')">&#9660;</span>';
        } else {
            echo '<span class="ld-org-toggle-spacer"></span>';
        }

        // Name + member count.
        echo '<span class="ld-org-node-name">' . s(format_string($node->name)) . '</span>';
        echo '<span class="ld-badge ld-badge-secondary">'
            . (int)$node->member_count . ' ' . get_string('org_members_label', 'local_leducon') . '</span>';

        if (!empty($node->shortname)) {
            echo '<span class="text-muted ml-2" style="font-size:.85em">[' . s($node->shortname) . ']</span>';
        }
        if (!$node->enabled) {
            echo '<span class="ld-badge ld-badge-warning">' . get_string('inactive') . '</span>';
        }

        // Action buttons.
        echo '<span class="ld-org-node-actions">';
        $editurl = new moodle_url($self_url, ['tab' => 'tree', 'editid' => $node->id]);
        echo html_writer::link($editurl, get_string('edit'), ['class' => 'ld-btn ld-btn-sm ld-btn-secondary']);

        $addurl = new moodle_url($self_url, ['tab' => 'tree', 'addparent' => $node->id]);
        echo html_writer::link($addurl, '+ ' . get_string('org_child', 'local_leducon'),
            ['class' => 'ld-btn ld-btn-sm ld-btn-primary']);

        if (!$haschildren) {
            $delurl = new moodle_url($self_url, ['action' => 'delete_unit', 'unitid' => $node->id, 'sesskey' => sesskey()]);
            echo html_writer::link($delurl, get_string('delete'),
                ['class' => 'ld-btn ld-btn-sm ld-btn-danger',
                 'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete', 'local_leducon')) . ')']);
        }
        echo '</span>';
        echo '</div>'; // node-row

        if ($haschildren) {
            echo '<div class="ld-org-children">';
            org_render_tree_nodes($node->children, $self_url);
            echo '</div>';
        }

        echo '</div>'; // node
    }
}

// =====================================================================
// TAB 2: MEMBERS
// =====================================================================

if ($tab === 'members') {
    $orgunitid = optional_param('orgunitid', 0, PARAM_INT);
    $unitopts = org_helper::get_org_dropdown_options(false);
    unset($unitopts[0]);

    // Unit selector.
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex align-items-end gap-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'members']);
    echo html_writer::start_div('form-group mb-0 flex-grow-1');
    echo html_writer::tag('label', s(get_string('org_select_unit', 'local_leducon')));
    echo html_writer::select($unitopts, 'orgunitid', $orgunitid, ['' => get_string('org_select_unit', 'local_leducon')],
        ['class' => 'ld-input ld-select', 'onchange' => 'this.form.submit()']);
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();

    if ($orgunitid > 0) {
        $unit = org_manager::get_unit($orgunitid);
        if ($unit) {
            // Breadcrumb.
            $ancestors = org_manager::get_ancestors($orgunitid);
            $crumbs = array_map(function($a) { return s($a->name); }, $ancestors);
            echo '<div class="ld-org-breadcrumb mb-3">' . implode(' &rsaquo; ', $crumbs) . '</div>';

            // AJAX base URL for all org operations.
            $ajax_url = (new moodle_url('/local/leducon/ajax/org_ajax.php', ['sesskey' => sesskey()]))->out(false);
            $search_url = (new moodle_url($self_url, ['action' => 'search_users', 'sesskey' => sesskey()]))->out(false);

            // ── Single Add Member ──
            echo '<div class="ld-card mb-4">';
            echo '<div class="ld-card-header"><h3 class="ld-card-title">'
                . get_string('org_add_member', 'local_leducon') . '</h3></div>';
            echo '<div class="ld-card-body">';
            echo '<form method="post" id="add-member-form">';
            echo '<input type="hidden" name="action" value="add_member">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="tab" value="members">';
            echo '<input type="hidden" name="orgunitid" value="' . (int)$orgunitid . '">';
            echo '<div class="ld-form-row">';
            echo '<label>' . get_string('org_user', 'local_leducon') . ' <span class="text-danger">*</span></label>';
            echo '<div class="ld-user-search-wrap" id="user-search-wrap">';
            echo '<span class="ld-user-search-icon" aria-hidden="true">&#128269;</span>';
            echo '<input type="search" id="user-search-input" class="ld-input ld-user-search-input"
                         name="ld_org_member_lookup_' . sesskey() . '"
                         placeholder="' . s(get_string('user_search_placeholder', 'local_leducon')) . '"
                         autocomplete="new-password" role="combobox" aria-autocomplete="list" aria-controls="user-dropdown">';
            echo '<ul class="ld-user-dropdown" id="user-dropdown" role="listbox" hidden></ul>';
            echo '</div>';
            echo '<input type="hidden" name="userid" id="user-id-hidden" required>';
            echo '<div id="user-selected-display" class="ld-user-selected" style="display:none"></div>';
            echo '<small class="text-muted">' . get_string('user_search_hint', 'local_leducon') . '</small>';
            echo '</div>';
            echo '<div class="ld-action-bar mt-1">';
            echo '<button type="submit" class="ld-btn ld-btn-primary" id="add-member-btn" disabled>'
                . get_string('add') . '</button>';
            echo '</div>';
            echo '</form>';
            echo '</div></div>';

            // ── Bulk Add by Email ──
            echo '<div class="ld-card mb-4">';
            echo '<div class="ld-card-header"><h3 class="ld-card-title">'
                . s(get_string('org_bulk_add_title', 'local_leducon')) . '</h3></div>';
            echo '<div class="ld-card-body">';
            echo '<p class="text-muted small">' . s(get_string('org_bulk_add_desc', 'local_leducon')) . '</p>';
            echo '<textarea id="bulk-emails-input" class="ld-input" rows="4" placeholder="user1@example.com&#10;user2@example.com"></textarea>';
            echo '<div class="ld-action-bar mt-2">';
            echo '<button type="button" class="ld-btn ld-btn-sm ld-btn-primary" id="bulk-add-btn">'
                . s(get_string('org_bulk_add_btn', 'local_leducon')) . '</button>';
            echo '</div>';
            echo '<div id="bulk-add-result" class="mt-2" style="display:none"></div>';
            echo '</div></div>';

            // ── Bulk Actions Bar ──
            echo '<div class="d-flex gap-2 mb-3 flex-wrap align-items-center">';

            // Sync cohort button.
            if (!empty($unit->cohortid)) {
                $cohortname = $DB->get_field('cohort', 'name', ['id' => $unit->cohortid]);
                echo '<form method="post" style="display:inline">';
                echo '<input type="hidden" name="action" value="sync_cohort">';
                echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
                echo '<input type="hidden" name="orgunitid" value="' . (int)$orgunitid . '">';
                echo '<input type="hidden" name="tab" value="members">';
                echo '<button type="submit" class="ld-btn ld-btn-sm ld-btn-secondary">'
                    . get_string('org_sync_cohort', 'local_leducon', s($cohortname)) . '</button>';
                echo '</form>';
            }

            // Bulk remove selected.
            echo '<button type="button" class="ld-btn ld-btn-sm ld-btn-danger" id="bulk-remove-btn" disabled>'
                . s(get_string('org_bulk_remove_btn', 'local_leducon'))
                . ' (<span id="bulk-remove-count">0</span>)</button>';

            // Reassign members.
            $parentopts_reassign = org_helper::get_org_dropdown_options(false);
            unset($parentopts_reassign[$orgunitid]); // Can't reassign to self.
            unset($parentopts_reassign[0]);
            echo '<select id="reassign-target" class="ld-input ld-select" style="width:auto;display:inline-block;max-width:250px">';
            echo '<option value="">' . s(get_string('org_target_unit', 'local_leducon')) . '...</option>';
            foreach ($parentopts_reassign as $rid => $rname) {
                echo '<option value="' . (int)$rid . '">' . s($rname) . '</option>';
            }
            echo '</select>';
            echo '<button type="button" class="ld-btn ld-btn-sm ld-btn-warning" id="reassign-btn">'
                . s(get_string('org_bulk_reassign_btn', 'local_leducon')) . '</button>';

            echo '</div>';

            // ── Search & Pagination Controls ──
            $perpage = (int)(get_config('local_leducon', 'org_members_perpage') ?: 50);
            $page    = optional_param('mpage', 0, PARAM_INT);
            $search  = optional_param('msearch', '', PARAM_TEXT);

            echo '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">';
            echo '<form method="get" class="d-flex gap-2 align-items-center">';
            echo '<input type="hidden" name="tab" value="members">';
            echo '<input type="hidden" name="orgunitid" value="' . (int)$orgunitid . '">';
            echo '<input type="search" name="msearch" class="ld-input" style="width:220px"'
                . ' placeholder="' . s(get_string('org_search_members', 'local_leducon')) . '"'
                . ' value="' . s($search) . '">';
            echo '<button type="submit" class="ld-btn ld-btn-sm ld-btn-secondary">' . get_string('search') . '</button>';
            echo '</form>';

            // Page info.
            $data = org_manager::get_members_paginated($orgunitid, $page, $perpage, $search);
            $total = $data['total'];
            $members = $data['members'];
            $totalpages = (int)ceil($total / $perpage);
            $from = $total > 0 ? ($page * $perpage + 1) : 0;
            $to   = min(($page + 1) * $perpage, $total);
            echo '<span class="text-muted small">'
                . get_string('org_page_info', 'local_leducon', (object)['from' => $from, 'to' => $to, 'total' => $total])
                . '</span>';
            echo '</div>';

            // ── Members Table with Checkboxes ──
            echo '<div class="ld-card">';
            echo '<div class="ld-card-header"><h3 class="ld-card-title">'
                . get_string('org_members_heading', 'local_leducon')
                . '</h3> <span class="ld-badge ld-badge-primary">' . $total . '</span></div>';
            if (empty($members)) {
                echo '<div class="ld-card-body"><p class="text-muted mb-0">'
                    . get_string('org_no_members', 'local_leducon') . '</p></div>';
            } else {
                echo '<div class="ld-card-body-flush"><div class="table-responsive">';
                echo '<table class="ld-table table table-sm table-hover mb-0">';
                echo '<thead><tr>';
                echo '<th style="width:40px"><input type="checkbox" id="select-all-members" title="Select all"></th>';
                echo '<th>' . get_string('fullname') . '</th>';
                echo '<th>' . get_string('email') . '</th>';
                echo '<th style="width:110px">' . get_string('actions') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($members as $m) {
                    $removeurl = new moodle_url($self_url, [
                        'action' => 'remove_member', 'orgunitid' => $orgunitid,
                        'userid' => $m->id, 'sesskey' => sesskey(), 'tab' => 'members',
                    ]);
                    echo '<tr>';
                    echo '<td><input type="checkbox" class="member-checkbox" value="' . (int)$m->id . '"></td>';
                    echo '<td><strong>' . s(fullname($m)) . '</strong></td>';
                    echo '<td><small class="text-muted">' . s($m->email) . '</small></td>';
                    echo '<td>';
                    echo html_writer::link($removeurl, get_string('remove'),
                        ['class' => 'ld-btn ld-btn-sm ld-btn-danger',
                         'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete', 'local_leducon')) . ')']);
                    echo '</td></tr>';
                }
                echo '</tbody></table></div></div>';

                // ── Pagination ──
                if ($totalpages > 1) {
                    echo '<div class="ld-card-body d-flex justify-content-center gap-1">';
                    for ($p = 0; $p < $totalpages; $p++) {
                        $purl = new moodle_url($self_url, [
                            'tab' => 'members', 'orgunitid' => $orgunitid,
                            'mpage' => $p, 'msearch' => $search,
                        ]);
                        $cls = ($p === $page) ? 'ld-btn ld-btn-sm ld-btn-primary' : 'ld-btn ld-btn-sm ld-btn-secondary';
                        echo html_writer::link($purl, (string)($p + 1), ['class' => $cls]);
                    }
                    echo '</div>';
                }
            }
            echo '</div>';

            // ── JavaScript: user search, bulk ops, checkbox management ──
            ?>
<script>
(function () {
    'use strict';
    var AJAX_URL = <?php echo json_encode($ajax_url); ?>;
    var SEARCH_URL = <?php echo json_encode($search_url); ?>;
    var ORGUNITID = <?php echo (int)$orgunitid; ?>;
    var NO_RESULTS = <?php echo json_encode(get_string('user_search_noresults', 'local_leducon')); ?>;
    var CONFIRM_REMOVE = <?php echo json_encode(get_string('org_confirm_bulk_remove', 'local_leducon', '{n}')); ?>;
    var CONFIRM_REASSIGN = <?php echo json_encode(get_string('org_confirm_reassign', 'local_leducon')); ?>;

    // ── User Search (single add) ──
    var input = document.getElementById('user-search-input'),
        dropdown = document.getElementById('user-dropdown'),
        hiddenId = document.getElementById('user-id-hidden'),
        selectedDiv = document.getElementById('user-selected-display'),
        addBtn = document.getElementById('add-member-btn'),
        timer = null, DEBOUNCE = 280;

    function escHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    function clearSelection(){hiddenId.value='';selectedDiv.style.display='none';selectedDiv.textContent='';addBtn.disabled=true;}
    function selectUser(id,fullname,email){
        hiddenId.value=id;input.value='';dropdown.hidden=true;dropdown.innerHTML='';
        selectedDiv.style.display='flex';
        selectedDiv.innerHTML='<span class="ld-user-sel-name">'+escHtml(fullname)+'</span>'+
            '<span class="ld-user-sel-email">'+escHtml(email)+'</span>'+
            '<button type="button" class="ld-user-sel-clear" aria-label="Clear">&#x2715;</button>';
        selectedDiv.querySelector('.ld-user-sel-clear').addEventListener('click',function(){clearSelection();input.focus();});
        addBtn.disabled=false;
    }
    if (input) {
        input.addEventListener('input',function(){var q=this.value.trim();clearTimeout(timer);clearSelection();
            if(q.length<2){dropdown.hidden=true;dropdown.innerHTML='';return;}
            timer=setTimeout(function(){doSearch(q);},DEBOUNCE);});
    }
    function doSearch(q){
        fetch(SEARCH_URL+'&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json();}).then(function(data){
            dropdown.innerHTML='';
            if(!data.results||data.results.length===0){var li=document.createElement('li');li.className='ld-user-dd-empty';li.textContent=NO_RESULTS;dropdown.appendChild(li);}
            else{data.results.forEach(function(u){var li=document.createElement('li');li.setAttribute('role','option');li.className='ld-user-dd-item';
                li.innerHTML='<span class="ld-user-dd-name">'+escHtml(u.fullname)+'</span><span class="ld-user-dd-email">'+escHtml(u.email)+'</span>';
                li.addEventListener('mousedown',function(e){e.preventDefault();});
                li.addEventListener('click',(function(uid,name,mail){return function(){selectUser(uid,name,mail);};})(u.id,u.fullname,u.email));
                dropdown.appendChild(li);});}
            dropdown.hidden=false;}).catch(function(){dropdown.hidden=true;});
    }
    document.addEventListener('click',function(e){var wrap=document.getElementById('user-search-wrap');if(wrap&&!wrap.contains(e.target)){dropdown.hidden=true;}});
    if (input) {
        input.addEventListener('keydown',function(e){var items=dropdown.querySelectorAll('.ld-user-dd-item');if(!items.length)return;
            var active=dropdown.querySelector('.ld-user-dd-item.focused'),idx=Array.prototype.indexOf.call(items,active);
            if(e.key==='ArrowDown'){e.preventDefault();if(active)active.classList.remove('focused');items[Math.min(idx+1,items.length-1)].classList.add('focused');}
            else if(e.key==='ArrowUp'){e.preventDefault();if(active)active.classList.remove('focused');items[Math.max(idx-1,0)].classList.add('focused');}
            else if(e.key==='Enter'){var f=dropdown.querySelector('.ld-user-dd-item.focused');if(f){e.preventDefault();f.click();}}
            else if(e.key==='Escape'){dropdown.hidden=true;}});
    }

    // ── Checkbox management ──
    var selectAll = document.getElementById('select-all-members');
    var bulkRemoveBtn = document.getElementById('bulk-remove-btn');
    var bulkRemoveCount = document.getElementById('bulk-remove-count');

    function getSelectedIds() {
        var ids = [];
        document.querySelectorAll('.member-checkbox:checked').forEach(function(cb) {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }
    function updateBulkCount() {
        var ids = getSelectedIds();
        bulkRemoveCount.textContent = ids.length;
        bulkRemoveBtn.disabled = ids.length === 0;
    }
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.member-checkbox').forEach(function(cb) { cb.checked = checked; });
            updateBulkCount();
        });
    }
    document.querySelectorAll('.member-checkbox').forEach(function(cb) {
        cb.addEventListener('change', updateBulkCount);
    });

    // Helper: POST with form-encoded body (avoids URL length limits for large payloads).
    function ajaxPost(action, bodyParams) {
        var parts = ['action=' + encodeURIComponent(action)];
        for (var k in bodyParams) {
            if (bodyParams.hasOwnProperty(k)) {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(bodyParams[k]));
            }
        }
        return fetch(AJAX_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: parts.join('&')
        }).then(function(r) { return r.json(); });
    }

    // ── Bulk Remove ──
    if (bulkRemoveBtn) {
        bulkRemoveBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            if (!confirm(CONFIRM_REMOVE.replace('{n}', ids.length))) return;
            bulkRemoveBtn.disabled = true;
            ajaxPost('bulk_remove_members', {
                orgunitid: ORGUNITID,
                userids: JSON.stringify(ids)
            })
            .then(function(data) {
                if (data.success) { window.location.reload(); }
                else { alert(data.error || 'Error'); bulkRemoveBtn.disabled = false; }
            })
            .catch(function() { alert('Network error'); bulkRemoveBtn.disabled = false; });
        });
    }

    // ── Bulk Add by Email ──
    var bulkAddBtn = document.getElementById('bulk-add-btn');
    var bulkEmailsInput = document.getElementById('bulk-emails-input');
    var bulkAddResult = document.getElementById('bulk-add-result');

    if (bulkAddBtn) {
        bulkAddBtn.addEventListener('click', function() {
            var raw = bulkEmailsInput.value.trim();
            if (!raw) return;
            bulkAddBtn.disabled = true;
            bulkAddResult.style.display = 'none';
            ajaxPost('bulk_add_members', {
                orgunitid: ORGUNITID,
                emails: raw
            })
            .then(function(data) {
                bulkAddBtn.disabled = false;
                if (data.success && data.data) {
                    var d = data.data;
                    var html = '<div class="alert alert-info mb-0">';
                    html += '<strong>' + d.added + '</strong> added.';
                    if (d.already_member && d.already_member.length > 0) {
                        html += ' <strong>' + d.already_member.length + '</strong> already members.';
                    }
                    if (d.not_found && d.not_found.length > 0) {
                        html += '<br><span class="text-danger">Not found: ' + escHtml(d.not_found.join(', ')) + '</span>';
                    }
                    html += '</div>';
                    bulkAddResult.innerHTML = html;
                    bulkAddResult.style.display = 'block';
                    if (d.added > 0) {
                        setTimeout(function() { window.location.reload(); }, 1500);
                    }
                } else {
                    bulkAddResult.innerHTML = '<div class="alert alert-danger mb-0">' + escHtml(data.error || 'Error') + '</div>';
                    bulkAddResult.style.display = 'block';
                }
            })
            .catch(function() { bulkAddBtn.disabled = false; alert('Network error'); });
        });
    }

    // ── Reassign All Members ──
    var reassignBtn = document.getElementById('reassign-btn');
    var reassignTarget = document.getElementById('reassign-target');

    if (reassignBtn) {
        reassignBtn.addEventListener('click', function() {
            var target = reassignTarget.value;
            if (!target) { alert('Select a target unit first.'); return; }
            if (!confirm(CONFIRM_REASSIGN)) return;
            reassignBtn.disabled = true;
            ajaxPost('reassign_members', {
                source_orgunitid: ORGUNITID,
                target_orgunitid: target
            })
            .then(function(data) {
                if (data.success) { window.location.reload(); }
                else { alert(data.error || 'Error'); reassignBtn.disabled = false; }
            })
            .catch(function() { alert('Network error'); reassignBtn.disabled = false; });
        });
    }
}());
</script>
            <?php
        }
    } else {
        echo $OUTPUT->notification(get_string('org_select_unit_prompt', 'local_leducon'), 'info');
    }
}

// =====================================================================
// TAB 3: CSV IMPORT
// =====================================================================

if ($tab === 'import') {
    // Show results from last import.
    if (!empty($SESSION->org_import_result)) {
        $r = $SESSION->org_import_result;
        $dryrun = !empty($SESSION->org_import_dryrun);
        unset($SESSION->org_import_result, $SESSION->org_import_dryrun);

        $prefix = $dryrun ? get_string('org_import_preview', 'local_leducon') . ' ' : '';
        $summary = $prefix . get_string('org_import_summary', 'local_leducon', (object)[
            'created' => $r['units_created'], 'updated' => $r['units_updated'],
            'members' => $r['members_added'],
        ]);
        echo $OUTPUT->notification($summary, empty($r['errors']) ? 'success' : 'warning');

        if (!empty($r['errors'])) {
            echo '<div class="alert alert-warning"><ul class="mb-0">';
            foreach ($r['errors'] as $err) {
                echo '<li>' . s($err) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    // Template download.
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', s(get_string('org_import_heading', 'local_leducon')), ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::tag('p', get_string('org_import_desc', 'local_leducon'), ['class' => 'text-muted']);

    // CSV format help.
    echo '<div class="alert alert-info">';
    echo '<strong>' . get_string('org_import_format_title', 'local_leducon') . '</strong><br>';
    echo '<code>shortname,name,parent_shortname,user_email</code><br>';
    echo get_string('org_import_format_desc', 'local_leducon');
    echo '</div>';

    // Upload form.
    echo html_writer::start_tag('form', [
        'method' => 'post', 'enctype' => 'multipart/form-data',
        'action' => (new moodle_url($self_url))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'import_csv']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'import']);

    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', s(get_string('org_import_file', 'local_leducon')));
    echo html_writer::empty_tag('input', [
        'type' => 'file', 'name' => 'csvfile', 'accept' => '.csv',
        'class' => 'ld-input', 'required' => 'required',
    ]);
    echo html_writer::end_div();

    echo '<div class="d-flex gap-2">';
    echo html_writer::tag('button', get_string('org_import_preview_btn', 'local_leducon'),
        ['type' => 'submit', 'name' => 'dryrun', 'value' => '1', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
    echo html_writer::tag('button', get_string('org_import_btn', 'local_leducon'),
        ['type' => 'submit', 'name' => 'dryrun', 'value' => '0', 'class' => 'ld-btn ld-btn-sm ld-btn-primary']);
    echo '</div>';
    echo html_writer::end_tag('form');

    echo html_writer::end_div();
    echo html_writer::end_div();

    // Rebuild paths utility.
    echo html_writer::start_div('card');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('h5', s(get_string('org_maintenance', 'local_leducon')), ['class' => 'mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');
    echo html_writer::tag('p', get_string('org_rebuild_desc', 'local_leducon'), ['class' => 'text-muted']);
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="rebuild_paths">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="tab" value="import">';
    echo html_writer::tag('button', get_string('org_rebuild_btn', 'local_leducon'),
        ['type' => 'submit', 'class' => 'ld-btn ld-btn-sm ld-btn-secondary']);
    echo '</form>';
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
