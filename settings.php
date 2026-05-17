<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_leducon_settings',
        get_string('pluginname', 'local_leducon')
    );

    // =================================================================
    // GENERAL
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/general_heading',
        get_string('settings_general_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/gamify_enabled',
        get_string('settings_gamify_enabled', 'local_leducon'),
        get_string('settings_gamify_enabled_desc', 'local_leducon'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'local_leducon/defaultperiod',
        get_string('settings_defaultperiod', 'local_leducon'),
        '',
        30,
        [
            7   => get_string('last_7days',   'local_leducon'),
            30  => get_string('last_30days',  'local_leducon'),
            90  => get_string('last_90days',  'local_leducon'),
            365 => get_string('last_365days', 'local_leducon'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/maxexportrows',
        get_string('settings_maxexportrows', 'local_leducon'),
        '',
        5000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/listperpage',
        get_string('settings_listperpage', 'local_leducon'),
        get_string('settings_listperpage_desc', 'local_leducon'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/enablecharts',
        get_string('settings_enablecharts', 'local_leducon'),
        '',
        1
    ));

    // =================================================================
    // LEADERBOARD
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/leaderboard_heading',
        get_string('settings_leaderboard_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/leaderboard_maxrows',
        get_string('settings_leaderboard_maxrows', 'local_leducon'),
        '',
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_leducon/leaderboard_privacy',
        get_string('settings_leaderboard_privacy', 'local_leducon'),
        '',
        'fullnames',
        [
            'fullnames'   => get_string('settings_privacy_fullnames',   'local_leducon'),
            'anonymous'   => get_string('settings_privacy_anonymous',   'local_leducon'),
            'teacheronly' => get_string('settings_privacy_teacheronly', 'local_leducon'),
        ]
    ));

    // =================================================================
    // KPI THRESHOLDS
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/kpi_heading',
        get_string('settings_kpi_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_completion_green',
        get_string('settings_kpi_completion_green', 'local_leducon'),
        '', 75, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_completion_amber',
        get_string('settings_kpi_completion_amber', 'local_leducon'),
        '', 50, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_pass_green',
        get_string('settings_kpi_pass_green', 'local_leducon'),
        '', 70, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_pass_amber',
        get_string('settings_kpi_pass_amber', 'local_leducon'),
        '', 50, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_engagement_green',
        get_string('settings_kpi_engagement_green', 'local_leducon'),
        '', 60, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/kpi_engagement_amber',
        get_string('settings_kpi_engagement_amber', 'local_leducon'),
        '', 30, PARAM_INT
    ));

    // =================================================================
    // AT-RISK THRESHOLDS
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/atrisk_heading',
        get_string('settings_atrisk_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/atrisk_inactive_days',
        get_string('settings_atrisk_inactive_days', 'local_leducon'),
        '', 14, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/atrisk_grade_threshold',
        get_string('settings_atrisk_grade', 'local_leducon'),
        '', 50, PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/atrisk_completion_threshold',
        get_string('settings_atrisk_completion', 'local_leducon'),
        '', 25, PARAM_INT
    ));

    // =================================================================
    // SCORM REPORTING
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/scorm_heading',
        get_string('settings_scorm_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/includeinactive',
        get_string('settings_includeinactive', 'local_leducon'),
        get_string('settings_includeinactive_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'local_leducon/scorm_pass_mode',
        get_string('settings_scorm_pass_mode', 'local_leducon'),
        get_string('settings_scorm_pass_mode_desc', 'local_leducon'),
        'auto',
        [
            'auto'          => get_string('settings_scorm_auto',          'local_leducon'),
            'lenient'       => get_string('settings_scorm_lenient',       'local_leducon'),
            'pass_priority' => get_string('settings_scorm_pass_priority', 'local_leducon'),
            'strict'        => get_string('settings_scorm_strict',        'local_leducon'),
            'moodle'        => get_string('settings_scorm_moodle',        'local_leducon'),
        ]
    ));

    // =================================================================
    // SCHEDULED EMAIL REPORTS
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/email_heading',
        get_string('settings_email_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_leducon/email_recipients',
        get_string('settings_email_recipients', 'local_leducon'),
        '', '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_leducon/email_reports_list',
        get_string('settings_email_reports_list', 'local_leducon'),
        '', '', PARAM_TEXT
    ));

    // =================================================================
    // XP EVENT VALUES
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/xp_heading',
        get_string('settings_xp_heading', 'local_leducon'),
        ''
    ));

    $xpevents = [
        'course_completion'  => [500,  get_string('settings_xp_course_completion',  'local_leducon')],
        'quiz_pass_first'    => [150,  get_string('settings_xp_quiz_pass_first',    'local_leducon')],
        'quiz_attempt'       => [30,   get_string('settings_xp_quiz_attempt',       'local_leducon')],
        'assign_ontime'      => [100,  get_string('settings_xp_assign_ontime',      'local_leducon')],
        'assign_submitted'   => [50,   get_string('settings_xp_assign_submitted',   'local_leducon')],
        'forum_post'         => [25,   get_string('settings_xp_forum_post',         'local_leducon')],
        'badge_awarded'      => [200,  get_string('settings_xp_badge_awarded',      'local_leducon')],
        'path_completion'    => [400,  get_string('settings_xp_path_completion',    'local_leducon')],
        'spotlight_received' => [75,   get_string('settings_xp_spotlight_received', 'local_leducon')],
    ];
    foreach ($xpevents as $key => [$default, $label]) {
        $settings->add(new admin_setting_configtext(
            'local_leducon/xp_' . $key,
            $label, '', $default, PARAM_INT
        ));
    }

    $settings->add(new admin_setting_configtext(
        'local_leducon/xp_forum_post_maxday',
        get_string('settings_xp_forum_post_maxday', 'local_leducon'),
        get_string('settings_xp_forum_post_maxday_desc', 'local_leducon'),
        3, PARAM_INT
    ));

    // =================================================================
    // STREAK MILESTONES
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/streak_heading',
        get_string('settings_streak_heading', 'local_leducon'),
        ''
    ));

    foreach ([7 => 50, 30 => 200, 90 => 500, 365 => 2000] as $days => $default) {
        $settings->add(new admin_setting_configtext(
            'local_leducon/xp_streak_' . $days,
            get_string('settings_xp_streak_days', 'local_leducon', $days),
            '', $default, PARAM_INT
        ));
    }

    // =================================================================
    // SPOTLIGHT & RECOGNITION
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/spotlight_heading',
        get_string('settings_spotlight_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/spotlight_daily_limit',
        get_string('settings_spotlight_daily_limit', 'local_leducon'),
        get_string('settings_spotlight_daily_limit_desc', 'local_leducon'),
        3, PARAM_INT
    ));

    // =================================================================
    // NUDGE EMAILS
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/nudge_heading',
        get_string('settings_nudge_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/nudge_enabled',
        get_string('settings_nudge_enabled', 'local_leducon'),
        get_string('settings_nudge_enabled_desc', 'local_leducon'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/nudge_inactive_days',
        get_string('settings_nudge_inactive_days', 'local_leducon'),
        get_string('settings_nudge_inactive_days_desc', 'local_leducon'),
        7, PARAM_INT
    ));

    // =================================================================
    // ORGANISATION STRUCTURE
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/org_heading',
        get_string('settings_org_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/org_enabled',
        get_string('settings_org_enabled', 'local_leducon'),
        get_string('settings_org_enabled_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/org_auto_sync_cohorts',
        get_string('settings_org_sync_cohorts', 'local_leducon'),
        get_string('settings_org_sync_cohorts_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/org_sync_remove',
        get_string('settings_org_sync_remove', 'local_leducon'),
        get_string('settings_org_sync_remove_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/org_import_batch_size',
        get_string('settings_org_import_batch_size', 'local_leducon'),
        get_string('settings_org_import_batch_size_desc', 'local_leducon'),
        500,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/org_members_perpage',
        get_string('settings_org_members_perpage', 'local_leducon'),
        get_string('settings_org_members_perpage_desc', 'local_leducon'),
        50,
        PARAM_INT
    ));

    // =================================================================
    // AUTOMATION: COMPLIANCE REMINDERS
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/compliance_heading',
        get_string('settings_compliance_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/compliance_reminder_enabled',
        get_string('settings_compliance_enabled', 'local_leducon'),
        get_string('settings_compliance_enabled_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_leducon/compliance_reminder_days',
        get_string('settings_compliance_days', 'local_leducon'),
        get_string('settings_compliance_days_desc', 'local_leducon'),
        7,
        PARAM_INT
    ));

    // =================================================================
    // AUTOMATION: DATA RETENTION
    // =================================================================
    $settings->add(new admin_setting_heading(
        'local_leducon/retention_heading',
        get_string('settings_retention_heading', 'local_leducon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_leducon/data_retention_enabled',
        get_string('settings_retention_enabled', 'local_leducon'),
        get_string('settings_retention_enabled_desc', 'local_leducon'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'local_leducon/data_retention_months',
        get_string('settings_retention_months', 'local_leducon'),
        get_string('settings_retention_months_desc', 'local_leducon'),
        24,
        [
            6  => '6 months',
            12 => '12 months',
            18 => '18 months',
            24 => '24 months',
            36 => '36 months',
            48 => '48 months',
            60 => '60 months',
        ]
    ));

    $ADMIN->add('localplugins', $settings);
}
