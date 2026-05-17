<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\org;

/**
 * Report-level helpers for org-unit filtering.
 *
 * Provides SQL fragments and dropdown options that plug into
 * the existing filter bar and report classes.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_helper {

    /**
     * Build a SQL subquery that filters users by org unit (including descendants).
     *
     * Usage in a report query:
     *   list($orgsql, $orgparams) = org_helper::get_org_filter_sql($orgunitid, 'u');
     *   $sql .= $orgsql;
     *   $params = array_merge($params, $orgparams);
     *
     * @param int    $orgunitid  The selected org unit ID (0 = no filter).
     * @param string $useralias  The alias used for the user table (default 'u').
     * @return array [string $sql, array $params]
     */
    public static function get_org_filter_sql(int $orgunitid, string $useralias = 'u'): array {
        global $DB;

        if ($orgunitid <= 0) {
            return ['', []];
        }

        $unit = $DB->get_record('local_leducon_org_units', ['id' => $orgunitid]);
        if (!$unit) {
            return ['', []];
        }

        $like = $DB->sql_like('_lou.path', ':_orgpath');
        $sql = " AND {$useralias}.id IN (
            SELECT _lom.userid
              FROM {local_leducon_org_members} _lom
              JOIN {local_leducon_org_units} _lou ON _lou.id = _lom.orgunitid
             WHERE {$like}
        )";

        return [$sql, ['_orgpath' => $unit->path . '%']];
    }

    /**
     * Build an indented option list for an HTML <select> dropdown.
     *
     * Returns: [0 => 'All Organisation', 1 => 'Retail Banking', 5 => '-- Consumer Lending', ...]
     *
     * @param bool $enabledonly Only show enabled units.
     * @return array id => display_label
     */
    public static function get_org_dropdown_options(bool $enabledonly = true): array {
        $tree = org_manager::get_tree($enabledonly);
        $options = [0 => get_string('org_filter_all', 'local_leducon')];
        self::flatten_tree($tree, $options, 0);
        return $options;
    }

    /**
     * Recursively flatten a tree into an indented list.
     */
    private static function flatten_tree(array $nodes, array &$options, int $level): void {
        foreach ($nodes as $node) {
            $indent = str_repeat("\xC2\xA0\xC2\xA0", $level); // Non-breaking spaces.
            $prefix = $level > 0 ? $indent . '-- ' : '';
            $count  = isset($node->member_count) ? ' (' . (int)$node->member_count . ')' : '';
            $options[$node->id] = $prefix . format_string($node->name) . $count;

            if (!empty($node->children)) {
                self::flatten_tree($node->children, $options, $level + 1);
            }
        }
    }

    /**
     * Check if the org structure module is enabled and has units defined.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;

        if (!get_config('local_leducon', 'org_enabled')) {
            return false;
        }

        return $DB->record_exists('local_leducon_org_units', ['enabled' => 1]);
    }
}
