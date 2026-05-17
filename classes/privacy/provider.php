<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_leducon.
 *
 * Covers all gamification user-data tables plus the team_managers table.
 * Analytics configuration tables (alertrules, skills, course_costs) hold
 * no personal data and are excluded.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_leducon_xp_users', [
            'userid'        => 'privacy:metadata:users:userid',
            'total_xp'      => 'privacy:metadata:users:total_xp',
            'levelid'       => 'privacy:metadata:users:levelid',
            'streak_days'   => 'privacy:metadata:users:streak_days',
            'last_activity' => 'privacy:metadata:users:last_activity',
        ], 'privacy:metadata:users');

        $collection->add_database_table('local_leducon_xp_log', [
            'userid'      => 'privacy:metadata:xp_log:userid',
            'points'      => 'privacy:metadata:xp_log:points',
            'eventtype'   => 'privacy:metadata:xp_log:eventtype',
            'note'        => 'privacy:metadata:xp_log:note',
            'timecreated' => 'privacy:metadata:xp_log:timecreated',
        ], 'privacy:metadata:xp_log');

        $collection->add_database_table('local_leducon_user_achiev', [
            'userid'        => 'privacy:metadata:user_achiev:userid',
            'achievementid' => 'privacy:metadata:user_achiev:achievementid',
            'timecreated'   => 'privacy:metadata:user_achiev:timecreated',
        ], 'privacy:metadata:user_achiev');

        $collection->add_database_table('local_leducon_redemptions', [
            'userid'      => 'privacy:metadata:redemptions:userid',
            'rewardid'    => 'privacy:metadata:redemptions:rewardid',
            'cost_xp'     => 'privacy:metadata:redemptions:cost_xp',
            'status'      => 'privacy:metadata:redemptions:status',
            'timecreated' => 'privacy:metadata:redemptions:timecreated',
        ], 'privacy:metadata:redemptions');

        $collection->add_database_table('local_leducon_recognition', [
            'senderid'    => 'privacy:metadata:recognition:senderid',
            'recipientid' => 'privacy:metadata:recognition:recipientid',
            'message'     => 'privacy:metadata:recognition:message',
            'timecreated' => 'privacy:metadata:recognition:timecreated',
        ], 'privacy:metadata:recognition');

        $collection->add_database_table('local_leducon_team_managers', [
            'userid'       => 'privacy:metadata:team_managers:userid',
            'cohortid'     => 'privacy:metadata:team_managers:cohortid',
            'timeassigned' => 'privacy:metadata:team_managers:timeassigned',
        ], 'privacy:metadata:team_managers');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $list = new contextlist();

        $tables = [
            'local_leducon_xp_users'       => 'userid',
            'local_leducon_xp_log'         => 'userid',
            'local_leducon_user_achiev'    => 'userid',
            'local_leducon_redemptions'    => 'userid',
            'local_leducon_team_managers'  => 'userid',
        ];
        foreach ($tables as $table => $field) {
            if ($DB->count_records_select($table, "$field = :uid", ['uid' => $userid]) > 0) {
                $list->add_system_context();
                break;
            }
        }
        if ($DB->record_exists_select(
            'local_leducon_recognition',
            'senderid = :s OR recipientid = :r',
            ['s' => $userid, 'r' => $userid]
        )) {
            $list->add_system_context();
        }

        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $ctx = $userlist->get_context();
        if (!($ctx instanceof \context_system)) {
            return;
        }

        foreach (['local_leducon_xp_users', 'local_leducon_xp_log',
                  'local_leducon_user_achiev', 'local_leducon_redemptions',
                  'local_leducon_team_managers'] as $table) {
            $sql = "SELECT userid FROM {{$table}}";
            $userlist->add_from_sql('userid', $sql, []);
        }

        $sql = "SELECT senderid AS userid FROM {local_leducon_recognition}
                UNION
                SELECT recipientid AS userid FROM {local_leducon_recognition}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $ctx    = \context_system::instance();
        $sub    = [get_string('pluginname', 'local_leducon')];

        $user_rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid]);
        if ($user_rec) {
            writer::with_context($ctx)->export_data(
                array_merge($sub, ['profile']),
                $user_rec
            );
        }

        $logs = $DB->get_records('local_leducon_xp_log', ['userid' => $userid], 'timecreated DESC');
        writer::with_context($ctx)->export_data(
            array_merge($sub, ['xp_log']),
            (object)['records' => array_values($logs)]
        );

        $ach = $DB->get_records('local_leducon_user_achiev', ['userid' => $userid]);
        writer::with_context($ctx)->export_data(
            array_merge($sub, ['achievements']),
            (object)['records' => array_values($ach)]
        );

        $redemptions = $DB->get_records('local_leducon_redemptions', ['userid' => $userid], 'timecreated DESC');
        writer::with_context($ctx)->export_data(
            array_merge($sub, ['redemptions']),
            (object)['records' => array_values($redemptions)]
        );

        $recog = $DB->get_records_select(
            'local_leducon_recognition',
            'senderid = :s OR recipientid = :r',
            ['s' => $userid, 'r' => $userid],
            'timecreated DESC'
        );
        writer::with_context($ctx)->export_data(
            array_merge($sub, ['recognition']),
            (object)['records' => array_values($recog)]
        );

        $teams = $DB->get_records('local_leducon_team_managers', ['userid' => $userid]);
        if (!empty($teams)) {
            writer::with_context($ctx)->export_data(
                array_merge($sub, ['team_assignments']),
                (object)['records' => array_values($teams)]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!($context instanceof \context_system)) {
            return;
        }
        global $DB;
        foreach (['local_leducon_xp_users', 'local_leducon_xp_log',
                  'local_leducon_user_achiev', 'local_leducon_redemptions',
                  'local_leducon_recognition', 'local_leducon_team_managers'] as $table) {
            $DB->delete_records($table);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach (['local_leducon_xp_users', 'local_leducon_xp_log',
                  'local_leducon_user_achiev', 'local_leducon_redemptions',
                  'local_leducon_team_managers'] as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
        $DB->delete_records_select(
            'local_leducon_recognition',
            'senderid = :s OR recipientid = :r',
            ['s' => $userid, 'r' => $userid]
        );
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $ctx = $userlist->get_context();
        if (!($ctx instanceof \context_system)) {
            return;
        }
        $user_ids = $userlist->get_userids();
        if (empty($user_ids)) {
            return;
        }
        [$in_sql, $params] = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'uid');
        foreach (['local_leducon_xp_users', 'local_leducon_xp_log',
                  'local_leducon_user_achiev', 'local_leducon_redemptions',
                  'local_leducon_team_managers'] as $table) {
            $DB->delete_records_select($table, "userid {$in_sql}", $params);
        }
        // Distinct param-name sets to avoid collisions on sender vs recipient.
        [$in_sql_s, $params_s] = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'sid');
        [$in_sql_r, $params_r] = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'rid');
        $DB->delete_records_select(
            'local_leducon_recognition',
            "senderid {$in_sql_s} OR recipientid {$in_sql_r}",
            array_merge($params_s, $params_r)
        );
    }
}
