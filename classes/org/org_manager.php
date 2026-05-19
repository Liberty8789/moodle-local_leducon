<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\org;

/**
 * Organisation structure manager — tree CRUD, membership, CSV import, cohort sync.
 *
 * Uses materialised path pattern (same as Moodle's {context} table) for fast
 * descendant lookups without recursive CTEs. Works on MySQL, PostgreSQL, MariaDB.
 *
 * Enterprise-optimised for 200-1000 org units and 5-50K users.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_manager {

    // =====================================================================
    // READ operations
    // =====================================================================

    /**
     * Get the full org tree as nested arrays.
     *
     * Uses LEFT JOIN aggregate instead of N correlated subqueries for member counts.
     *
     * @param bool $enabledonly Only include enabled units.
     * @return array Nested tree: each node has ->children array.
     */
    public static function get_tree(bool $enabledonly = true): array {
        global $DB;

        $where = $enabledonly ? 'WHERE ou.enabled = 1' : '';
        $units = $DB->get_records_sql(
            "SELECT ou.*, COALESCE(mc.cnt, 0) AS member_count
               FROM {local_leducon_org_units} ou
          LEFT JOIN (
                    SELECT orgunitid, COUNT(*) AS cnt
                      FROM {local_leducon_org_members}
                  GROUP BY orgunitid
                ) mc ON mc.orgunitid = ou.id
              {$where}
           ORDER BY ou.depth ASC, ou.sortorder ASC, ou.name ASC"
        );

        // Build tree from flat list.
        $lookup = [];
        foreach ($units as $u) {
            $u->children = [];
            $lookup[$u->id] = $u;
        }

        $roots = [];
        foreach ($lookup as $u) {
            if ($u->parentid > 0 && isset($lookup[$u->parentid])) {
                $lookup[$u->parentid]->children[] = $u;
            } else {
                $roots[] = $u;
            }
        }

        return $roots;
    }

    /**
     * Get a single org unit by ID.
     *
     * @param int $id
     * @return object|false
     */
    public static function get_unit(int $id) {
        global $DB;
        return $DB->get_record('local_leducon_org_units', ['id' => $id]);
    }

    /**
     * Get direct children of a given parent with member counts and has_children flag.
     *
     * Optimised for lazy-load tree (single query, no N+1).
     *
     * @param int $parentid 0 for root units.
     * @return array of objects with: id, name, shortname, enabled, sortorder, member_count, has_children.
     */
    public static function get_children_with_counts(int $parentid = 0): array {
        global $DB;

        $sql = "SELECT ou.id, ou.name, ou.shortname, ou.enabled, ou.sortorder, ou.parentid,
                       ou.depth, ou.path, ou.cohortid, ou.description,
                       COALESCE(mc.cnt, 0) AS member_count,
                       CASE WHEN EXISTS (
                           SELECT 1 FROM {local_leducon_org_units} ch WHERE ch.parentid = ou.id
                       ) THEN 1 ELSE 0 END AS has_children
                  FROM {local_leducon_org_units} ou
             LEFT JOIN (
                       SELECT orgunitid, COUNT(*) AS cnt
                         FROM {local_leducon_org_members}
                     GROUP BY orgunitid
                   ) mc ON mc.orgunitid = ou.id
                 WHERE ou.parentid = :pid
              ORDER BY ou.sortorder ASC, ou.name ASC";
        return array_values($DB->get_records_sql($sql, ['pid' => $parentid]));
    }

    /**
     * Search org units by name or shortname.
     *
     * @param string $query Search term (min 2 chars).
     * @param int    $limit Max results (default 30).
     * @return array of objects with: id, name, shortname, member_count, path_display.
     */
    public static function search_units(string $query, int $limit = 30): array {
        global $DB;

        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $namefull = $DB->sql_like('ou.name', ':q1', false);
        $snlike   = $DB->sql_like('ou.shortname', ':q2', false);

        $sql = "SELECT ou.id, ou.name, ou.shortname, ou.path,
                       COALESCE(mc.cnt, 0) AS member_count
                  FROM {local_leducon_org_units} ou
             LEFT JOIN (
                       SELECT orgunitid, COUNT(*) AS cnt
                         FROM {local_leducon_org_members}
                     GROUP BY orgunitid
                   ) mc ON mc.orgunitid = ou.id
                 WHERE ({$namefull} OR {$snlike})
              ORDER BY ou.depth ASC, ou.name ASC";
        $units = $DB->get_records_sql($sql, [
            'q1' => '%' . $DB->sql_like_escape($query) . '%',
            'q2' => '%' . $DB->sql_like_escape($query) . '%',
        ], 0, $limit);

        // Add human-readable path display.
        foreach ($units as $u) {
            $ids = array_filter(explode('/', trim($u->path, '/')));
            if (count($ids) > 1) {
                array_pop($ids); // Remove self.
                list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'pa');
                $ancestors = $DB->get_records_sql(
                    "SELECT id, name FROM {local_leducon_org_units} WHERE id {$insql} ORDER BY depth ASC",
                    $params
                );
                $u->path_display = implode(' > ', array_column($ancestors, 'name')) . ' > ' . $u->name;
            } else {
                $u->path_display = $u->name;
            }
        }

        return array_values($units);
    }

    /**
     * Get all descendant unit IDs for a given unit (inclusive).
     *
     * Uses the materialised path for fast LIKE query.
     *
     * @param int $unitid
     * @return array of int IDs (includes $unitid itself).
     */
    public static function get_descendant_ids(int $unitid): array {
        global $DB;

        $unit = self::get_unit($unitid);
        if (!$unit) {
            return [];
        }

        $like = $DB->sql_like('path', ':pathlike');
        $ids = $DB->get_fieldset_sql(
            "SELECT id FROM {local_leducon_org_units} WHERE {$like}",
            ['pathlike' => $unit->path . '%']
        );

        return array_map('intval', $ids);
    }

    /**
     * Get ancestor chain from root to this unit (inclusive).
     *
     * @param int $unitid
     * @return array of objects ordered root -> leaf.
     */
    public static function get_ancestors(int $unitid): array {
        global $DB;

        $unit = self::get_unit($unitid);
        if (!$unit) {
            return [];
        }

        // Path is like /1/5/12/ — extract IDs.
        $ids = array_filter(explode('/', trim($unit->path, '/')));
        if (empty($ids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'anc');
        return array_values($DB->get_records_sql(
            "SELECT * FROM {local_leducon_org_units} WHERE id {$insql} ORDER BY depth ASC",
            $params
        ));
    }

    /**
     * Get direct children of a unit (or root units if parentid = 0).
     *
     * @param int $parentid
     * @return array
     */
    public static function get_children(int $parentid = 0): array {
        global $DB;
        return array_values($DB->get_records('local_leducon_org_units',
            ['parentid' => $parentid], 'sortorder ASC, name ASC'));
    }

    /**
     * Get the full org path string for a user: "Division > Department > Unit".
     *
     * @param int $userid
     * @return string|null Null if user has no org assignment.
     */
    public static function get_user_org_path(int $userid): ?string {
        global $DB;

        $membership = $DB->get_record_sql(
            "SELECT om.orgunitid
               FROM {local_leducon_org_members} om
               JOIN {local_leducon_org_units} ou ON ou.id = om.orgunitid AND ou.enabled = 1
              WHERE om.userid = :uid
           ORDER BY ou.depth DESC",
            ['uid' => $userid],
            IGNORE_MULTIPLE
        );

        if (!$membership) {
            return null;
        }

        $ancestors = self::get_ancestors((int)$membership->orgunitid);
        return implode(' > ', array_map(function($a) { return $a->name; }, $ancestors));
    }

    /**
     * Get aggregated report KPIs for all direct children of an org unit.
     *
     * Returns all data in 4-5 queries total instead of N*4 (one set per child).
     *
     * @param int $orgunitid Parent unit ID.
     * @param int $active_days Number of days to consider a user "active" (default 7).
     * @return array Keyed by child unit ID: { name, shortname, member_count, completions, avggrade, active }.
     */
    public static function get_children_report_data(int $orgunitid, int $active_days = 7): array {
        global $DB;

        $children = self::get_children($orgunitid);
        if (empty($children)) {
            return [];
        }

        // Build mapping: child_id => all descendant org unit IDs (including self).
        $child_to_units = [];
        foreach ($children as $child) {
            $child_to_units[(int)$child->id] = self::get_descendant_ids((int)$child->id);
        }

        // Build mapping: child_id => distinct member user IDs using a single query per child batch.
        $child_data = [];
        $all_memberids = [];

        foreach ($children as $child) {
            $cid = (int)$child->id;
            $desc_ids = $child_to_units[$cid];
            if (empty($desc_ids)) {
                $child_data[$cid] = [
                    'id' => $cid, 'name' => $child->name, 'shortname' => $child->shortname ?? '',
                    'member_count' => 0, 'completions' => 0, 'avggrade' => 0, 'active' => 0,
                ];
                continue;
            }

            list($insql, $params) = $DB->get_in_or_equal($desc_ids, SQL_PARAMS_NAMED, 'du' . $cid);
            $members = $DB->get_fieldset_sql(
                "SELECT DISTINCT om.userid FROM {local_leducon_org_members} om WHERE om.orgunitid {$insql}",
                $params
            );

            $child_data[$cid] = [
                'id' => $cid, 'name' => $child->name, 'shortname' => $child->shortname ?? '',
                'member_count' => count($members), 'memberids' => $members,
                'completions' => 0, 'avggrade' => 0, 'active' => 0,
            ];
            $all_memberids = array_merge($all_memberids, $members);
        }

        $all_memberids = array_unique($all_memberids);
        if (empty($all_memberids)) {
            // Strip memberids before returning.
            foreach ($child_data as &$cd) {
                unset($cd['memberids']);
            }
            return $child_data;
        }

        // Now run aggregated queries for each child that has members.
        $weekago = time() - $active_days * DAYSECS;

        foreach ($child_data as $cid => &$cd) {
            if (empty($cd['memberids'])) {
                unset($cd['memberids']);
                continue;
            }

            list($usql, $uparams) = $DB->get_in_or_equal($cd['memberids'], SQL_PARAMS_NAMED, 'u' . $cid);

            // Completions.
            $cd['completions'] = (int)$DB->count_records_sql(
                "SELECT COUNT(*) FROM {course_completions} cc
                LEFT JOIN {grade_items} gi_om ON gi_om.courseid = cc.course AND gi_om.itemtype = 'course'
                LEFT JOIN {grade_grades} gg_om ON gg_om.itemid = gi_om.id AND gg_om.userid = cc.userid
                WHERE cc.userid {$usql}
                  AND (cc.timecompleted IS NOT NULL
                      OR (gg_om.finalgrade IS NOT NULL AND gi_om.grademax > 0 AND gg_om.finalgrade / gi_om.grademax * 100 >= 100))",
                $uparams
            );

            // Average grade.
            $cd['avggrade'] = (float)$DB->get_field_sql(
                "SELECT AVG(gg.finalgrade)
                   FROM {grade_grades} gg
                   JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
                  WHERE gg.userid {$usql} AND gg.finalgrade IS NOT NULL",
                $uparams
            );

            // Active users.
            $aparams = $uparams;
            $aparams['weekago'] = $weekago;
            $cd['active'] = (int)$DB->count_records_sql(
                "SELECT COUNT(*)
                   FROM {user}
                  WHERE id {$usql} AND lastaccess > :weekago",
                $aparams
            );

            unset($cd['memberids']);
        }

        return $child_data;
    }

    // =====================================================================
    // WRITE operations
    // =====================================================================

    /**
     * Create a new org unit.
     *
     * @param object $data Must contain: name. Optional: shortname, parentid, description, cohortid, enabled, sortorder.
     * @return int The new record ID.
     */
    public static function create_unit(object $data): int {
        global $DB;

        $now = time();
        $record = (object)[
            'name'         => trim($data->name),
            'shortname'    => !empty($data->shortname) ? trim($data->shortname) : null,
            'parentid'     => (int)($data->parentid ?? 0),
            'depth'        => 1,
            'path'         => '/',
            'description'  => $data->description ?? null,
            'cohortid'     => !empty($data->cohortid) ? (int)$data->cohortid : null,
            'enabled'      => (int)($data->enabled ?? 1),
            'sortorder'    => (int)($data->sortorder ?? 0),
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        // Calculate depth and path from parent.
        if ($record->parentid > 0) {
            $parent = self::get_unit($record->parentid);
            if ($parent) {
                $record->depth = (int)$parent->depth + 1;
            }
        }

        $id = $DB->insert_record('local_leducon_org_units', $record);

        // Update path now that we have the ID.
        self::recalculate_path($id);

        return (int)$id;
    }

    /**
     * Update an existing org unit.
     *
     * @param int $id
     * @param object $data Fields to update.
     * @return bool
     */
    public static function update_unit(int $id, object $data): bool {
        global $DB;

        $existing = self::get_unit($id);
        if (!$existing) {
            return false;
        }

        $record = (object)['id' => $id, 'timemodified' => time()];

        if (isset($data->name))        $record->name        = trim($data->name);
        if (isset($data->shortname))   $record->shortname   = trim($data->shortname) ?: null;
        if (isset($data->description)) $record->description = $data->description;
        if (isset($data->cohortid))    $record->cohortid    = (int)$data->cohortid ?: null;
        if (isset($data->enabled))     $record->enabled     = (int)$data->enabled;
        if (isset($data->sortorder))   $record->sortorder   = (int)$data->sortorder;

        $parentchanged = false;
        if (isset($data->parentid) && (int)$data->parentid !== (int)$existing->parentid) {
            // Prevent circular reference.
            $newparent = (int)$data->parentid;
            if ($newparent > 0) {
                $descendantids = self::get_descendant_ids($id);
                if (in_array($newparent, $descendantids, true)) {
                    return false; // Would create a loop.
                }
            }
            $record->parentid = $newparent;
            $parentchanged = true;
        }

        $DB->update_record('local_leducon_org_units', $record);

        if ($parentchanged) {
            // Recalculate path for this unit and all descendants.
            self::rebuild_subtree_paths($id);
        }

        return true;
    }

    /**
     * Delete an org unit if it has no children.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_unit(int $id): bool {
        global $DB;

        // Check for children.
        if ($DB->record_exists('local_leducon_org_units', ['parentid' => $id])) {
            return false; // Must delete or reassign children first.
        }

        $DB->delete_records('local_leducon_org_members', ['orgunitid' => $id]);
        $DB->delete_records('local_leducon_org_units', ['id' => $id]);

        return true;
    }

    // =====================================================================
    // PATH management
    // =====================================================================

    /**
     * Recalculate path and depth for a single unit.
     */
    private static function recalculate_path(int $id): void {
        global $DB;

        $unit = $DB->get_record('local_leducon_org_units', ['id' => $id]);
        if (!$unit) {
            return;
        }

        if ($unit->parentid > 0) {
            $parent = $DB->get_record('local_leducon_org_units', ['id' => $unit->parentid]);
            if ($parent) {
                $path  = $parent->path . $id . '/';
                $depth = (int)$parent->depth + 1;
            } else {
                $path  = '/' . $id . '/';
                $depth = 1;
            }
        } else {
            $path  = '/' . $id . '/';
            $depth = 1;
        }

        $DB->update_record('local_leducon_org_units', (object)[
            'id' => $id, 'path' => $path, 'depth' => $depth,
        ]);
    }

    /**
     * Rebuild paths for a unit and all its descendants.
     */
    private static function rebuild_subtree_paths(int $id): void {
        global $DB;

        self::recalculate_path($id);

        // Recursively fix children.
        $children = $DB->get_fieldset_sql(
            "SELECT id FROM {local_leducon_org_units} WHERE parentid = :pid",
            ['pid' => $id]
        );
        foreach ($children as $childid) {
            self::rebuild_subtree_paths((int)$childid);
        }
    }

    /**
     * Full path rebuild — maintenance utility.
     */
    public static function rebuild_all_paths(): void {
        global $DB;

        $roots = $DB->get_fieldset_sql(
            "SELECT id FROM {local_leducon_org_units} WHERE parentid = 0 ORDER BY sortorder ASC"
        );
        foreach ($roots as $rootid) {
            self::rebuild_subtree_paths((int)$rootid);
        }
    }

    // =====================================================================
    // MEMBERSHIP
    // =====================================================================

    /**
     * Add a user to an org unit.
     *
     * @return bool True if added, false if already exists.
     */
    public static function add_member(int $orgunitid, int $userid): bool {
        global $DB;

        if ($DB->record_exists('local_leducon_org_members', ['orgunitid' => $orgunitid, 'userid' => $userid])) {
            return false;
        }

        $DB->insert_record('local_leducon_org_members', (object)[
            'orgunitid'   => $orgunitid,
            'userid'      => $userid,
            'timecreated' => time(),
        ]);

        return true;
    }

    /**
     * Remove a user from an org unit.
     */
    public static function remove_member(int $orgunitid, int $userid): void {
        global $DB;
        $DB->delete_records('local_leducon_org_members', ['orgunitid' => $orgunitid, 'userid' => $userid]);
    }

    /**
     * Get members of an org unit, optionally including descendants.
     *
     * @param int  $orgunitid
     * @param bool $includechildren Include members of all descendant units.
     * @return array of user objects.
     */
    public static function get_members(int $orgunitid, bool $includechildren = false): array {
        global $DB;

        if ($includechildren) {
            $unit = self::get_unit($orgunitid);
            if (!$unit) {
                return [];
            }
            $like = $DB->sql_like('ou.path', ':pathlike');
            return array_values($DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, om.orgunitid, ou.name AS orgunit_name
                   FROM {local_leducon_org_members} om
                   JOIN {user} u ON u.id = om.userid AND u.deleted = 0 AND u.suspended = 0
                   JOIN {local_leducon_org_units} ou ON ou.id = om.orgunitid
                  WHERE {$like}
               ORDER BY u.lastname ASC, u.firstname ASC",
                ['pathlike' => $unit->path . '%']
            ));
        }

        return array_values($DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email
               FROM {local_leducon_org_members} om
               JOIN {user} u ON u.id = om.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE om.orgunitid = :ouid
           ORDER BY u.lastname ASC, u.firstname ASC",
            ['ouid' => $orgunitid]
        ));
    }

    /**
     * Paginated member list with optional search.
     *
     * @param int    $orgunitid
     * @param int    $page     0-based page number.
     * @param int    $perpage  Results per page (default 50).
     * @param string $search   Optional search term for name/email.
     * @return array ['members' => [...], 'total' => int]
     */
    public static function get_members_paginated(int $orgunitid, int $page = 0, int $perpage = 50, string $search = ''): array {
        global $DB;

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $where = 'om.orgunitid = :ouid AND u.deleted = 0 AND u.suspended = 0';
        $params = ['ouid' => $orgunitid];

        if ($search !== '' && strlen($search) >= 2) {
            $namelike  = $DB->sql_like($fullname, ':sq1', false);
            $emaillike = $DB->sql_like('u.email', ':sq2', false);
            $where .= " AND ({$namelike} OR {$emaillike})";
            $params['sq1'] = '%' . $DB->sql_like_escape($search) . '%';
            $params['sq2'] = '%' . $DB->sql_like_escape($search) . '%';
        }

        $total = (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {local_leducon_org_members} om
               JOIN {user} u ON u.id = om.userid
              WHERE {$where}",
            $params
        );

        $members = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email
               FROM {local_leducon_org_members} om
               JOIN {user} u ON u.id = om.userid
              WHERE {$where}
           ORDER BY u.lastname ASC, u.firstname ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        return ['members' => array_values($members), 'total' => $total];
    }

    /**
     * Bulk remove multiple members from an org unit.
     *
     * @param int   $orgunitid
     * @param array $userids Array of user IDs to remove.
     * @return int Number of members removed.
     */
    public static function bulk_remove_members(int $orgunitid, array $userids): int {
        global $DB;

        if (empty($userids)) {
            return 0;
        }

        $userids = array_map('intval', $userids);
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['ouid'] = $orgunitid;

        $count = $DB->count_records_select(
            'local_leducon_org_members',
            "orgunitid = :ouid AND userid {$insql}",
            $params
        );

        $DB->delete_records_select(
            'local_leducon_org_members',
            "orgunitid = :ouid AND userid {$insql}",
            $params
        );

        return (int)$count;
    }

    /**
     * Bulk add members by email addresses.
     *
     * Uses a single batch query to look up all users instead of N queries.
     *
     * @param int   $orgunitid
     * @param array $emails Array of email strings.
     * @return array ['added' => int, 'not_found' => [string], 'already_member' => [string]]
     */
    public static function bulk_add_members_by_email(int $orgunitid, array $emails): array {
        global $DB;

        $result = ['added' => 0, 'not_found' => [], 'already_member' => []];

        // Clean and deduplicate.
        $clean_emails = [];
        foreach ($emails as $e) {
            $e = trim(strtolower($e));
            if ($e !== '' && validate_email($e)) {
                $clean_emails[$e] = $e;
            }
        }
        if (empty($clean_emails)) {
            return $result;
        }

        // Batch look up all users by email in a single query.
        list($insql, $params) = $DB->get_in_or_equal(array_values($clean_emails), SQL_PARAMS_NAMED, 'em');
        $users = $DB->get_records_sql(
            "SELECT id, email
               FROM {user}
              WHERE deleted = 0 AND LOWER(email) {$insql}",
            $params
        );

        // Build email → userid map.
        $email_to_user = [];
        foreach ($users as $u) {
            $email_to_user[strtolower($u->email)] = (int)$u->id;
        }

        // Get existing members to check for duplicates.
        $existing = $DB->get_fieldset_select(
            'local_leducon_org_members', 'userid',
            'orgunitid = :ouid', ['ouid' => $orgunitid]
        );
        $existing_set = array_flip($existing);

        // Process each email.
        foreach ($clean_emails as $email) {
            if (!isset($email_to_user[$email])) {
                $result['not_found'][] = $email;
                continue;
            }

            $uid = $email_to_user[$email];
            if (isset($existing_set[$uid])) {
                $result['already_member'][] = $email;
                continue;
            }

            $DB->insert_record('local_leducon_org_members', (object)[
                'orgunitid'   => $orgunitid,
                'userid'      => $uid,
                'timecreated' => time(),
            ]);
            $existing_set[$uid] = true; // Prevent duplicate inserts within this batch.
            $result['added']++;
        }

        return $result;
    }

    /**
     * Move all members from one org unit to another.
     *
     * Handles deduplication: if a user already exists in target, skip and remove from source.
     *
     * @param int $source_orgunitid
     * @param int $target_orgunitid
     * @return int Number of members moved.
     */
    public static function reassign_all_members(int $source_orgunitid, int $target_orgunitid): int {
        global $DB;

        if ($source_orgunitid === $target_orgunitid) {
            return 0;
        }

        $transaction = $DB->start_delegated_transaction();

        // Get source members.
        $source_members = $DB->get_fieldset_select(
            'local_leducon_org_members', 'userid',
            'orgunitid = :ouid', ['ouid' => $source_orgunitid]
        );

        if (empty($source_members)) {
            $transaction->allow_commit();
            return 0;
        }

        // Get existing target members.
        $target_members = $DB->get_fieldset_select(
            'local_leducon_org_members', 'userid',
            'orgunitid = :ouid', ['ouid' => $target_orgunitid]
        );
        $target_set = array_flip($target_members);

        $moved = 0;
        $now = time();

        foreach ($source_members as $uid) {
            if (!isset($target_set[$uid])) {
                // Move: update the orgunitid.
                $DB->insert_record('local_leducon_org_members', (object)[
                    'orgunitid'   => $target_orgunitid,
                    'userid'      => (int)$uid,
                    'timecreated' => $now,
                ]);
                $moved++;
            }
        }

        // Remove all from source.
        $DB->delete_records('local_leducon_org_members', ['orgunitid' => $source_orgunitid]);

        $transaction->allow_commit();
        return $moved;
    }

    /**
     * Get member count for a unit (optionally including descendants).
     */
    public static function get_member_count(int $orgunitid, bool $includechildren = false): int {
        global $DB;

        if ($includechildren) {
            $unit = self::get_unit($orgunitid);
            if (!$unit) {
                return 0;
            }
            $like = $DB->sql_like('ou.path', ':pathlike');
            return (int)$DB->count_records_sql(
                "SELECT COUNT(DISTINCT om.userid)
                   FROM {local_leducon_org_members} om
                   JOIN {local_leducon_org_units} ou ON ou.id = om.orgunitid
                  WHERE {$like}",
                ['pathlike' => $unit->path . '%']
            );
        }

        return (int)$DB->count_records('local_leducon_org_members', ['orgunitid' => $orgunitid]);
    }

    // =====================================================================
    // COHORT SYNC
    // =====================================================================

    /**
     * Sync an org unit's members to its linked Moodle cohort.
     *
     * Adds missing members and removes members no longer in the org unit.
     *
     * @param int  $orgunitid
     * @param bool $includechildren Also include descendant unit members.
     * @return array ['added' => int, 'removed' => int]
     */
    public static function sync_cohort(int $orgunitid, bool $includechildren = true): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');

        $unit = self::get_unit($orgunitid);
        if (!$unit || empty($unit->cohortid)) {
            return ['added' => 0, 'removed' => 0];
        }

        if (!$DB->record_exists('cohort', ['id' => $unit->cohortid])) {
            return ['added' => 0, 'removed' => 0];
        }

        // Get org members.
        $orgmembers = self::get_members($orgunitid, $includechildren);
        $orguserids = array_column($orgmembers, 'id');

        // Get current cohort members.
        $cohortmembers = $DB->get_fieldset_sql(
            "SELECT userid FROM {cohort_members} WHERE cohortid = :cid",
            ['cid' => $unit->cohortid]
        );

        $added = 0;
        $removed = 0;

        // Add missing to cohort.
        $toadd = array_diff($orguserids, $cohortmembers);
        foreach ($toadd as $uid) {
            try {
                \cohort_add_member($unit->cohortid, $uid);
                $added++;
            } catch (\Exception $e) {
                // User might already be a member via another path.
            }
        }

        // Remove from cohort if no longer in org.
        if (get_config('local_leducon', 'org_sync_remove')) {
            $toremove = array_diff($cohortmembers, $orguserids);
            foreach ($toremove as $uid) {
                \cohort_remove_member($unit->cohortid, $uid);
                $removed++;
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }

    // =====================================================================
    // CSV IMPORT
    // =====================================================================

    /**
     * Import org structure and user assignments from CSV data.
     *
     * Enterprise-hardened: BOM handling, transaction wrapping, preview data.
     *
     * @param string $csvdata Raw CSV content.
     * @param bool   $dryrun  If true, only validate — don't write.
     * @return array ['units_created', 'units_updated', 'members_added', 'errors', 'preview']
     */
    public static function import_csv(string $csvdata, bool $dryrun = false): array {
        global $DB;

        $result = [
            'units_created'  => 0,
            'units_updated'  => 0,
            'members_added'  => 0,
            'errors'         => [],
            'preview'        => [],
        ];

        // Strip UTF-8 BOM if present.
        if (substr($csvdata, 0, 3) === "\xEF\xBB\xBF") {
            $csvdata = substr($csvdata, 3);
        }

        $lines = str_getcsv_lines($csvdata);
        if (empty($lines)) {
            $result['errors'][] = get_string('org_import_empty', 'local_leducon');
            return $result;
        }

        // Parse header.
        $header = array_shift($lines);
        $header = array_map('strtolower', array_map('trim', $header));
        $expected = ['shortname', 'name', 'parent_shortname', 'user_email'];
        if ($header !== $expected) {
            $result['errors'][] = get_string('org_import_badheader', 'local_leducon',
                implode(', ', $expected));
            return $result;
        }

        // Build shortname -> id lookup from existing units.
        $existingunits = $DB->get_records('local_leducon_org_units', null, '', 'id, shortname, name, parentid');
        $snlookup = [];
        foreach ($existingunits as $eu) {
            if (!empty($eu->shortname)) {
                $snlookup[strtolower(trim($eu->shortname))] = (int)$eu->id;
            }
        }

        // Start transaction for non-dry-run.
        $transaction = null;
        if (!$dryrun) {
            $transaction = $DB->start_delegated_transaction();
        }

        try {
            $linenum = 1;
            foreach ($lines as $row) {
                $linenum++;
                if (count($row) < 4) {
                    $row = array_pad($row, 4, '');
                }
                list($shortname, $name, $parentsn, $email) = array_map('trim', $row);

                // Skip empty lines.
                if ($shortname === '' && $name === '' && $email === '') {
                    continue;
                }

                // If name is provided, this is a unit definition line.
                if ($name !== '' && $shortname !== '') {
                    $snkey = strtolower($shortname);
                    $parentid = 0;
                    if ($parentsn !== '') {
                        $parentkey = strtolower($parentsn);
                        if (isset($snlookup[$parentkey])) {
                            $parentid = $snlookup[$parentkey];
                        } else {
                            $result['errors'][] = get_string('org_import_parentnotfound', 'local_leducon',
                                (object)['line' => $linenum, 'parent' => $parentsn]);
                            continue;
                        }
                    }

                    if (isset($snlookup[$snkey])) {
                        // Update existing.
                        if (!$dryrun) {
                            self::update_unit($snlookup[$snkey], (object)[
                                'name' => $name, 'parentid' => $parentid,
                            ]);
                        }
                        $result['units_updated']++;
                        $result['preview'][] = [
                            'line' => $linenum, 'action' => 'update',
                            'shortname' => $shortname, 'name' => $name, 'email' => '',
                        ];
                    } else {
                        // Create new.
                        if (!$dryrun) {
                            $newid = self::create_unit((object)[
                                'name' => $name, 'shortname' => $shortname, 'parentid' => $parentid,
                            ]);
                            $snlookup[$snkey] = $newid;
                        } else {
                            $snlookup[$snkey] = -1; // Placeholder for dry-run.
                        }
                        $result['units_created']++;
                        $result['preview'][] = [
                            'line' => $linenum, 'action' => 'create',
                            'shortname' => $shortname, 'name' => $name, 'email' => '',
                        ];
                    }
                }
                // If email is provided, this is a member assignment line.
                elseif ($email !== '' && $shortname !== '') {
                    $snkey = strtolower($shortname);
                    if (!isset($snlookup[$snkey])) {
                        $result['errors'][] = get_string('org_import_unitnotfound', 'local_leducon',
                            (object)['line' => $linenum, 'shortname' => $shortname]);
                        continue;
                    }

                    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
                    if (!$user) {
                        $result['errors'][] = get_string('org_import_usernotfound', 'local_leducon',
                            (object)['line' => $linenum, 'email' => $email]);
                        continue;
                    }

                    if (!$dryrun && $snlookup[$snkey] > 0) {
                        self::add_member($snlookup[$snkey], (int)$user->id);
                    }
                    $result['members_added']++;
                    $result['preview'][] = [
                        'line' => $linenum, 'action' => 'assign',
                        'shortname' => $shortname, 'name' => '', 'email' => $email,
                    ];
                }
            }

            if ($transaction) {
                $transaction->allow_commit();
            }
        } catch (\Exception $e) {
            if ($transaction) {
                try { $transaction->rollback($e); } catch (\Exception $ignored) {}
            }
            $result['errors'][] = 'Import failed: ' . $e->getMessage();
            $result['units_created'] = 0;
            $result['units_updated'] = 0;
            $result['members_added'] = 0;
            $result['rolled_back'] = true;
        }

        return $result;
    }
}

/**
 * Parse CSV string into array of rows (each row is array of fields).
 *
 * Uses SplTempFileObject for proper CSV parsing (handles quoted fields,
 * multiline values, etc.) instead of naive explode+str_getcsv.
 */
function str_getcsv_lines(string $csvdata): array {
    // Strip UTF-8 BOM if present.
    if (substr($csvdata, 0, 3) === "\xEF\xBB\xBF") {
        $csvdata = substr($csvdata, 3);
    }

    $temp = new \SplTempFileObject();
    $temp->fwrite($csvdata);
    $temp->rewind();
    $temp->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

    $lines = [];
    foreach ($temp as $row) {
        if ($row === [null]) {
            continue; // Skip truly empty rows.
        }
        $lines[] = $row;
    }
    return $lines;
}
