<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for Organisation Structure operations.
 *
 * Supports lazy-load tree, paginated members, search, and bulk ops.
 * All actions require sesskey and manageorgstructure capability.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\org\org_manager;

require_login();
require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/leducon:manageorgstructure', $context);

$action = required_param('action', PARAM_ALPHANUMEXT);

header('Content-Type: application/json; charset=utf-8');

try {
    $result = null;

    switch ($action) {

        // ==============================================================
        // TREE: Lazy-load children for a parent node.
        // ==============================================================
        case 'get_children':
            $parentid = required_param('parentid', PARAM_INT);
            $children = org_manager::get_children_with_counts($parentid);
            $result = ['children' => $children];
            break;

        // ==============================================================
        // TREE: Get ancestors (breadcrumb) for a unit.
        // ==============================================================
        case 'get_ancestors':
            $unitid = required_param('unitid', PARAM_INT);
            $ancestors = org_manager::get_ancestors($unitid);
            $crumbs = [];
            foreach ($ancestors as $a) {
                $crumbs[] = [
                    'id'   => (int)$a->id,
                    'name' => format_string($a->name),
                ];
            }
            $result = ['ancestors' => $crumbs];
            break;

        // ==============================================================
        // TREE: Search units by name/shortname.
        // ==============================================================
        case 'search_units':
            $q     = required_param('q', PARAM_TEXT);
            $limit = optional_param('limit', 30, PARAM_INT);
            $limit = min(max($limit, 1), 100);
            $units = org_manager::search_units($q, $limit);
            $clean = [];
            foreach ($units as $u) {
                $clean[] = [
                    'id'           => (int)$u->id,
                    'name'         => format_string($u->name),
                    'shortname'    => $u->shortname ?? '',
                    'member_count' => (int)$u->member_count,
                    'path_display' => $u->path_display,
                ];
            }
            $result = ['units' => $clean];
            break;

        // ==============================================================
        // USERS: Search Moodle users by name/email (for add-member).
        // ==============================================================
        case 'search_users':
            $q = trim(required_param('q', PARAM_TEXT));
            $users = [];
            if (strlen($q) >= 2) {
                $fullname_sql = $DB->sql_fullname('firstname', 'lastname');
                $like1 = $DB->sql_like($fullname_sql, ':q1', false);
                $like2 = $DB->sql_like('email', ':q2', false);
                $sql = "SELECT id, firstname, lastname, email
                          FROM {user}
                         WHERE deleted = 0 AND suspended = 0 AND id <> :guestid
                           AND ({$like1} OR {$like2})
                      ORDER BY lastname ASC, firstname ASC";
                $rows = $DB->get_records_sql($sql, [
                    'guestid' => guest_user()->id,
                    'q1'      => '%' . $DB->sql_like_escape($q) . '%',
                    'q2'      => '%' . $DB->sql_like_escape($q) . '%',
                ], 0, 20);
                foreach ($rows as $u) {
                    $users[] = [
                        'id'       => (int)$u->id,
                        'fullname' => fullname($u),
                        'email'    => $u->email,
                    ];
                }
            }
            $result = ['results' => $users];
            break;

        // ==============================================================
        // MEMBERS: Paginated member list with search.
        // ==============================================================
        case 'get_members_page':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $page      = optional_param('page', 0, PARAM_INT);
            $perpage   = optional_param('perpage', 50, PARAM_INT);
            $search    = optional_param('search', '', PARAM_TEXT);
            $perpage   = min(max($perpage, 10), 200);

            $data = org_manager::get_members_paginated($orgunitid, $page, $perpage, $search);

            // Format member records for JSON.
            $members = [];
            foreach ($data['members'] as $m) {
                $members[] = [
                    'id'       => (int)$m->id,
                    'fullname' => fullname($m),
                    'email'    => $m->email,
                ];
            }

            $result = [
                'members'  => $members,
                'total'    => (int)$data['total'],
                'page'     => $page,
                'perpage'  => $perpage,
                'pages'    => (int)ceil($data['total'] / $perpage),
            ];
            break;

        // ==============================================================
        // MEMBERS: Add a single member.
        // ==============================================================
        case 'add_member':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $userid    = required_param('userid', PARAM_INT);
            $added = org_manager::add_member($orgunitid, $userid);
            $result = [
                'success' => $added,
                'message' => $added
                    ? get_string('org_member_added', 'local_leducon')
                    : get_string('org_member_exists', 'local_leducon'),
            ];
            break;

        // ==============================================================
        // MEMBERS: Remove a single member.
        // ==============================================================
        case 'remove_member':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $userid    = required_param('userid', PARAM_INT);
            org_manager::remove_member($orgunitid, $userid);
            $result = [
                'success' => true,
                'message' => get_string('org_member_removed', 'local_leducon'),
            ];
            break;

        // ==============================================================
        // MEMBERS: Bulk remove members (checkbox selection).
        // ==============================================================
        case 'bulk_remove_members':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $userids_json = required_param('userids', PARAM_RAW);
            $userids = json_decode($userids_json, true);

            if (!is_array($userids) || empty($userids)) {
                throw new \moodle_exception('invalidparam', 'error', '', 'userids');
            }

            $userids = array_map('intval', $userids);
            $removed = org_manager::bulk_remove_members($orgunitid, $userids);
            $result = [
                'success' => true,
                'removed' => $removed,
                'message' => get_string('org_bulk_removed', 'local_leducon', $removed),
            ];
            break;

        // ==============================================================
        // MEMBERS: Bulk add members by email list.
        // ==============================================================
        case 'bulk_add_members':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $emails_raw = required_param('emails', PARAM_RAW);

            // Accept both JSON array and newline/comma-separated string.
            $emails = json_decode($emails_raw, true);
            if (!is_array($emails)) {
                // Fall back to line-separated or comma-separated.
                $emails = preg_split('/[\r\n,;]+/', $emails_raw, -1, PREG_SPLIT_NO_EMPTY);
                $emails = array_map('trim', $emails);
            }

            if (empty($emails)) {
                throw new \moodle_exception('invalidparam', 'error', '', 'emails');
            }

            $res = org_manager::bulk_add_members_by_email($orgunitid, $emails);
            $result = [
                'success'        => true,
                'added'          => $res['added'],
                'not_found'      => $res['not_found'],
                'already_member' => $res['already_member'],
                'message'        => get_string('org_bulk_added', 'local_leducon', $res['added']),
            ];
            break;

        // ==============================================================
        // MEMBERS: Reassign all members from one unit to another.
        // ==============================================================
        case 'reassign_members':
            $source = required_param('source_orgunitid', PARAM_INT);
            $target = required_param('target_orgunitid', PARAM_INT);
            $moved  = org_manager::reassign_all_members($source, $target);
            $result = [
                'success' => true,
                'moved'   => $moved,
                'message' => get_string('org_members_reassigned', 'local_leducon', $moved),
            ];
            break;

        // ==============================================================
        // UNIT: Create a new unit (inline from tree).
        // ==============================================================
        case 'create_unit':
            $name       = required_param('name', PARAM_TEXT);
            $shortname  = optional_param('shortname', '', PARAM_TEXT);
            $parentid   = optional_param('parentid', 0, PARAM_INT);
            $description = optional_param('description', '', PARAM_TEXT);
            $cohortid   = optional_param('cohortid', 0, PARAM_INT);
            $sortorder  = optional_param('sortorder', 0, PARAM_INT);

            $newid = org_manager::create_unit((object)[
                'name' => $name, 'shortname' => $shortname, 'parentid' => $parentid,
                'description' => $description, 'cohortid' => $cohortid, 'sortorder' => $sortorder,
            ]);

            $unit = org_manager::get_unit($newid);
            $result = [
                'success' => true,
                'unit'    => [
                    'id'        => (int)$unit->id,
                    'name'      => format_string($unit->name),
                    'shortname' => $unit->shortname ?? '',
                    'parentid'  => (int)$unit->parentid,
                    'depth'     => (int)$unit->depth,
                    'path'      => $unit->path,
                    'enabled'   => (int)$unit->enabled,
                ],
                'message' => get_string('org_unit_created', 'local_leducon'),
            ];
            break;

        // ==============================================================
        // UNIT: Update an existing unit.
        // ==============================================================
        case 'update_unit':
            $unitid     = required_param('unitid', PARAM_INT);
            $data = new \stdClass();

            // Only set fields that were actually sent.
            $name = optional_param('name', null, PARAM_TEXT);
            if ($name !== null) $data->name = $name;

            $shortname = optional_param('shortname', null, PARAM_TEXT);
            if ($shortname !== null) $data->shortname = $shortname;

            $parentid = optional_param('parentid', null, PARAM_INT);
            if ($parentid !== null) $data->parentid = $parentid;

            $description = optional_param('description', null, PARAM_TEXT);
            if ($description !== null) $data->description = $description;

            $cohortid = optional_param('cohortid', null, PARAM_INT);
            if ($cohortid !== null) $data->cohortid = $cohortid;

            $enabled = optional_param('enabled', null, PARAM_INT);
            if ($enabled !== null) $data->enabled = $enabled;

            $sortorder = optional_param('sortorder', null, PARAM_INT);
            if ($sortorder !== null) $data->sortorder = $sortorder;

            $ok = org_manager::update_unit($unitid, $data);
            $result = [
                'success' => $ok,
                'message' => $ok
                    ? get_string('org_unit_updated', 'local_leducon')
                    : get_string('org_unit_update_failed', 'local_leducon'),
            ];
            break;

        // ==============================================================
        // UNIT: Delete a unit (only if no children).
        // ==============================================================
        case 'delete_unit':
            $unitid = required_param('unitid', PARAM_INT);
            $ok = org_manager::delete_unit($unitid);
            $result = [
                'success' => $ok,
                'message' => $ok
                    ? get_string('org_unit_deleted', 'local_leducon')
                    : get_string('org_unit_haschildren', 'local_leducon'),
            ];
            break;

        // ==============================================================
        // SYNC: Sync unit members to linked cohort.
        // ==============================================================
        case 'sync_cohort':
            $orgunitid = required_param('orgunitid', PARAM_INT);
            $res = org_manager::sync_cohort($orgunitid);
            $result = [
                'success' => true,
                'added'   => $res['added'],
                'removed' => $res['removed'],
                'message' => get_string('org_sync_result', 'local_leducon',
                    (object)['added' => $res['added'], 'removed' => $res['removed']]),
            ];
            break;

        // ==============================================================
        // STATS: Get member count for a unit (with/without descendants).
        // ==============================================================
        case 'get_member_count':
            $orgunitid       = required_param('orgunitid', PARAM_INT);
            $includechildren = optional_param('includechildren', 0, PARAM_INT);
            $count = org_manager::get_member_count($orgunitid, (bool)$includechildren);
            $result = ['count' => $count];
            break;

        default:
            throw new \moodle_exception('invalidparam', 'error', '', 'action');
    }

    echo json_encode(['success' => true, 'data' => $result]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
