<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_leducon_upgrade($oldversion) {
    global $DB;

    // 2026051400: Grant viewown, viewleaderboard, viewgamify to Authenticated User role.
    // Archetypes in access.php only apply on fresh install — existing sites need this step.
    if ($oldversion < 2026051400) {
        $authrole = $DB->get_record('role', ['shortname' => 'user']);
        if ($authrole) {
            $sysctx = context_system::instance();
            $caps = ['local/leducon:viewown', 'local/leducon:viewleaderboard', 'local/leducon:viewgamify'];
            foreach ($caps as $cap) {
                if (!$DB->record_exists('role_capabilities', [
                    'roleid'     => $authrole->id,
                    'capability' => $cap,
                    'contextid'  => $sysctx->id,
                ])) {
                    assign_capability($cap, CAP_ALLOW, $authrole->id, $sysctx->id, true);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026051400, 'local', 'leducon');
    }

    // 2026051401: Create org structure tables.
    if ($oldversion < 2026051401) {
        $dbman = $DB->get_manager();

        // Table: local_leducon_org_units.
        $table = new xmldb_table('local_leducon_org_units');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('shortname',    XMLDB_TYPE_CHAR,    '100', null, null, null, null);
            $table->add_field('parentid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('depth',        XMLDB_TYPE_INTEGER, '4',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('path',         XMLDB_TYPE_CHAR,    '1000', null, XMLDB_NOTNULL, null, '/');
            $table->add_field('description',  XMLDB_TYPE_TEXT,    null, null, null, null, null);
            $table->add_field('cohortid',     XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('enabled',      XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sortorder',    XMLDB_TYPE_INTEGER, '6',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
            $table->add_index('cohortid', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
            $table->add_index('enabled',  XMLDB_INDEX_NOTUNIQUE, ['enabled']);
            $dbman->create_table($table);
        }

        // Table: local_leducon_org_members.
        $table = new xmldb_table('local_leducon_org_members');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('orgunitid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('orgunitid_userid', XMLDB_INDEX_UNIQUE, ['orgunitid', 'userid']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051401, 'local', 'leducon');
    }

    // 2026051402: Create custom reports + report schedule tables.
    if ($oldversion < 2026051402) {
        $dbman = $DB->get_manager();

        // Table: local_leducon_custom_rpts.
        $table = new xmldb_table('local_leducon_custom_rpts');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name',         XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description',  XMLDB_TYPE_TEXT,    null, null, null, null, null);
            $table->add_field('datasource',   XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null, 'completions');
            $table->add_field('config',       XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('shared',       XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('createdby',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('createdby', XMLDB_INDEX_NOTUNIQUE, ['createdby']);
            $table->add_index('shared',    XMLDB_INDEX_NOTUNIQUE, ['shared']);
            $dbman->create_table($table);
        }

        // Table: local_leducon_rpt_schedule.
        $table = new xmldb_table('local_leducon_rpt_schedule');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('reportid',     XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('reporttype',   XMLDB_TYPE_CHAR,    '50', null, null, null, null);
            $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('recipients',   XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('frequency',    XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'weekly');
            $table->add_field('day_of_week',  XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('day_of_month', XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('hour',         XMLDB_TYPE_INTEGER, '2',  null, XMLDB_NOTNULL, null, '7');
            $table->add_field('format',       XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'csv');
            $table->add_field('enabled',      XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '1');
            $table->add_field('last_sent',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid',   XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('enabled',  XMLDB_INDEX_NOTUNIQUE, ['enabled']);
            $table->add_index('reportid', XMLDB_INDEX_NOTUNIQUE, ['reportid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051402, 'local', 'leducon');
    }

    // 2026051700: Grant new report capabilities to manager role on upgrade.
    if ($oldversion < 2026051700) {
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $sysctx = context_system::instance();

        $managercaps = [
            'local/leducon:viewteacherview',
            'local/leducon:viewmanagerview',
            'local/leducon:viewskills',
        ];
        foreach ($managercaps as $cap) {
            if ($managerrole && !$DB->record_exists('role_capabilities', [
                'roleid' => $managerrole->id, 'capability' => $cap, 'contextid' => $sysctx->id,
            ])) {
                assign_capability($cap, CAP_ALLOW, $managerrole->id, $sysctx->id, true);
            }
            // Also grant viewteacherview and viewskills to editing teacher.
            if ($teacherrole && $cap !== 'local/leducon:viewmanagerview' &&
                !$DB->record_exists('role_capabilities', [
                    'roleid' => $teacherrole->id, 'capability' => $cap, 'contextid' => $sysctx->id,
                ])) {
                assign_capability($cap, CAP_ALLOW, $teacherrole->id, $sysctx->id, true);
            }
        }

        upgrade_plugin_savepoint(true, 2026051700, 'local', 'leducon');
    }

    // 2026051900: Create notification log table.
    if ($oldversion < 2026051900) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_leducon_notif_log');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',             XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_field('recipientemail', XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('recipientname',  XMLDB_TYPE_CHAR,    '255', null, null, null, null);
            $table->add_field('subject',        XMLDB_TYPE_CHAR,    '500', null, XMLDB_NOTNULL, null, '');
            $table->add_field('body',           XMLDB_TYPE_TEXT,    null,  null, null, null, null);
            $table->add_field('channel',        XMLDB_TYPE_CHAR,    '30',  null, XMLDB_NOTNULL, null, 'email');
            $table->add_field('messagetype',    XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL, null, 'general');
            $table->add_field('status',         XMLDB_TYPE_CHAR,    '20',  null, XMLDB_NOTNULL, null, 'sent');
            $table->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid',      XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('messagetype', XMLDB_INDEX_NOTUNIQUE, ['messagetype']);
            $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('status',      XMLDB_INDEX_NOTUNIQUE, ['status']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051900, 'local', 'leducon');
    }

    return true;
}
