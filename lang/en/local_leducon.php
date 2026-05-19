<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Language strings for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// =================================================================
// PLUGIN META
// =================================================================
$string['pluginname'] = 'Leducon Learning Platform';
$string['plugindesc'] = 'Unified learning analytics and gamification platform with KPI tracking, at-risk detection, XP engine, rewards, campaigns, and team management.';
$string['plugin_disabled'] = 'Gamification features are currently disabled by an administrator.';

// =================================================================
// CAPABILITIES
// =================================================================
$string['leducon:viewanalytics']      = 'View analytics dashboards and reports';
$string['leducon:viewstudents']       = 'View individual student data';
$string['leducon:viewown']            = 'View own report card';
$string['leducon:viewleaderboard']    = 'View leaderboards';
$string['leducon:managealerts']       = 'Manage alert rules';
$string['leducon:viewteacherview']    = 'Access teacher view';
$string['leducon:viewmanagerview']    = 'Access manager view';
$string['leducon:manageskills']       = 'Manage skills and course mapping';
$string['leducon:viewskills']         = 'View skills reports';
$string['leducon:viewgamify']         = 'Access gamification features';
$string['leducon:viewteam']           = 'View team overview';
$string['leducon:awardspotlight']     = 'Award spotlight recognition';
$string['leducon:approveredemptions'] = 'Approve reward redemption requests';
$string['leducon:manageall']          = 'Manage all platform settings';
$string['leducon:manageorgstructure'] = 'Manage organisation structure';

// =================================================================
// UNIFIED NAVIGATION
// =================================================================
$string['nav_dashboard']       = 'Dashboard';
$string['nav_analytics']       = 'Analytics';
$string['nav_gamification']    = 'Gamification';
$string['nav_myteam']          = 'My Team';
$string['nav_setup']           = 'Setup';

// Analytics dropdown.
$string['nav_analytics_dashboard'] = 'Analytics Dashboard';
$string['nav_overview']        = 'Executive Overview';
$string['nav_reports']         = 'Reports';
$string['nav_atrisk']          = 'At-Risk Students';
$string['nav_heatmap']         = 'Activity Heatmap';
$string['nav_compare']         = 'Period Comparison';
$string['nav_performance_rankings'] = 'Performance Rankings';
$string['nav_leaderboards']         = 'Leaderboards';
$string['nav_perf_rankings']   = 'Performance Rankings';
$string['nav_teacherview']     = 'Teacher View';
$string['nav_managerview']     = 'Manager View';
$string['nav_myreport']        = 'My Report Card';
$string['nav_roi']             = 'ROI';
$string['nav_skills']          = 'Skills';
$string['nav_ilt']             = 'ILT / VILT';
$string['nav_leaderboard']     = 'Leaderboard';
$string['nav_views']           = 'Views';

// Gamification dropdown.
$string['nav_gamify']          = 'Gamification';
$string['nav_gamify_dashboard'] = 'My Progress';
$string['nav_my_progress']     = 'My Progress';
$string['nav_xp_leaderboard']  = 'XP Leaderboard';
$string['nav_achievements']    = 'Achievements';
$string['nav_paths']           = 'Learning Paths';
$string['nav_campaigns']       = 'Campaigns';
$string['nav_rewards']         = 'Rewards';
$string['nav_recognition']     = 'Recognition Wall';

// My Team dropdown.
$string['nav_team_overview']   = 'Team Overview';
$string['nav_spotlight']       = 'Award Spotlight';
$string['nav_redemptions']     = 'Redemption Approvals';

// Setup dropdown.
$string['nav_team_managers']   = 'Team Managers';
$string['nav_alertrules']      = 'Alert Rules';
$string['nav_skills_manage']   = 'Manage Skills';
$string['nav_admin_levels']    = 'Manage Levels';
$string['nav_admin_rewards']   = 'Manage Rewards';
$string['nav_admin_campaigns'] = 'Manage Campaigns';
$string['nav_admin_paths']     = 'Manage Paths';
$string['nav_admin_recognitions'] = 'Recognition Feed';
$string['nav_admin_managers']  = 'Team Managers';
$string['nav_admin_label']     = 'Admin';
$string['nav_roicosts']        = 'ROI Costs';
$string['nav_teamsetup']       = 'Team Setup';

// Companion links & tabs.
$string['companion_analytics_team']   = 'For detailed analytics, visit';
$string['companion_analytics_report'] = 'For your full analytics report, visit';
$string['companion_analytics_label']  = 'Analytics View';
$string['tab_gamification']    = 'Gamification';
$string['tab_analytics']       = 'Analytics';
$string['col_lastaccess']      = 'Last Access';

// Misc.
$string['gamify_disabled']     = 'Gamification features are currently disabled.';
$string['error_invaliddata']   = 'Invalid data submitted.';
$string['cert_title']          = 'Certificate of Achievement';

// =================================================================
// UNIFIED DASHBOARD (index.php)
// =================================================================
$string['dashboard_title']         = 'Leducon Learning Platform';
$string['dashboard_subtitle']      = 'Your learning analytics and progress at a glance.';
$string['dashboard_analytics_kpis'] = 'Learning Analytics';
$string['dashboard_gamify_hero']   = 'Your Learning Journey';
$string['dashboard_quick_launch']  = 'Quick Launch';

// =================================================================
// ANALYTICS: KPI / DASHBOARD
// =================================================================
$string['kpi_completion']       = 'Completion Rate';
$string['kpi_passrate']         = 'Pass Rate';
$string['kpi_engagement']       = 'Engagement Rate';
$string['kpi_atrisk']           = 'At-Risk Students';
$string['kpi_enrolled']         = 'Total Enrolled';
$string['kpi_active_this_week'] = 'Active This Week';
$string['kpi_new_enrolments']   = 'New Enrolments';
$string['kpi_avg_grade']        = 'Avg Grade';
$string['kpi_active_users']     = 'Active Users';
$string['kpi_completions']      = 'Completions';
$string['kpi_enrolments']       = 'New Enrolments';
$string['kpi_completion_rate']  = 'Completion Rate';
$string['select_report']        = 'View Full Report';
$string['view_overview']        = 'Executive Overview';
$string['analytics_title']      = 'Analytics Dashboard';
$string['overview_title']       = 'Executive Overview';
$string['last_7days']           = 'Last 7 days';
$string['last_30days']          = 'Last 30 days';
$string['last_90days']          = 'Last 90 days';
$string['last_365days']         = 'Last year';
$string['period_alltime']       = 'All time';

// =================================================================
// ANALYTICS: REPORT NAMES
// =================================================================
$string['report_course_completion'] = 'Course Completion';
$string['report_user_activity']     = 'User Activity';
$string['report_grade_analytics']   = 'Grade Analytics';
$string['report_quiz_analytics']    = 'Quiz Analytics';
$string['report_enrollment']        = 'Enrollment';
$string['report_login_activity']    = 'Login Activity';
$string['report_scorm_analytics']   = 'SCORM Analytics';
$string['report_assignment']        = 'Assignment Submissions';
$string['report_forum']             = 'Forum Participation';
$string['report_atrisk']            = 'At-Risk Students';
$string['report_timespent']         = 'Time Spent Learning';
$string['report_badge']             = 'Badge Report';
$string['report_certificate']       = 'Certificate Report';
$string['report_compliance']        = 'Training Compliance';
$string['report_category']          = 'Category Performance';
$string['report_instructor']        = 'Instructor Performance';
$string['report_ilt']               = 'ILT / VILT Sessions';

// Report sidebar group headings.
$string['report_group_learner']     = 'Learner Progress';
$string['report_group_activity']    = 'Activity & Engagement';
$string['report_group_content']     = 'Content & Credentials';
$string['report_group_management']  = 'Management';

// =================================================================
// ANALYTICS: FILTER LABELS
// =================================================================
$string['filter_report']        = 'Report';
$string['filter_from']          = 'From';
$string['filter_to']            = 'To';
$string['filter_course']        = 'Course';
$string['filter_category']      = 'Category';
$string['filter_cohort']        = 'Cohort/Department';
$string['filter_allcourses']    = 'All Courses';
$string['filter_allcategories'] = 'All Categories';
$string['filter_allcohorts']    = 'All Cohorts';
$string['filter_passmark']      = 'Pass mark (%)';
$string['filter_apply']         = 'Apply Filters';
$string['filter_reset']         = 'Reset';
$string['filter_period_a']      = 'Period A';
$string['filter_period_b']      = 'Period B';
$string['filter_daterange']     = 'Date Range';
$string['filter_datefrom']      = 'From:';
$string['filter_dateto']        = 'To:';
$string['filter_custom']        = 'Custom';
$string['applyfilters']         = 'Apply Filters';
$string['resetfilters']         = 'Reset';

// =================================================================
// ANALYTICS: AT-RISK
// =================================================================
$string['atrisk_title']        = 'At-Risk Students';
$string['atrisk_username']     = 'Username';
$string['atrisk_fullname']     = 'Full Name';
$string['atrisk_email']        = 'Email';
$string['atrisk_course']       = 'Course';
$string['atrisk_lastlogin']    = 'Last Login';
$string['atrisk_daysinactive'] = 'Days Inactive';
$string['atrisk_grade']        = 'Grade %';
$string['atrisk_completion']   = 'Completion %';
$string['atrisk_risk']         = 'Risk';
$string['atrisk_risk_high']    = 'High';
$string['atrisk_risk_medium']  = 'Medium';
$string['atrisk_risk_low']     = 'Low';
$string['atrisk_reason']       = 'Risk Reason';
$string['atrisk_nodata']       = 'No at-risk students found with current filters.';
$string['atrisk_notify']       = 'Notify Teacher';
$string['atrisk_notify_all']   = 'Notify All Teachers';
$string['atrisk_notified']     = 'Notification sent';
$string['atrisk_email_subject'] = 'At-Risk Students Alert — {$a}';
$string['atrisk_email_greeting'] = 'Dear {$a},';
$string['atrisk_email_intro']  = 'The following students in your course are flagged as at-risk:';
$string['atrisk_email_action'] = 'Please review and follow up with these students.';
$string['atrisk_email_risk']   = 'Risk';
$string['atrisk_inactive']     = 'Inactive {$a} days';
$string['atrisk_lowgrade']     = 'Grade {$a}%';
$string['atrisk_lowcompletion'] = 'Completion {$a}%';

// =================================================================
// ANALYTICS: HEATMAP
// =================================================================
$string['heatmap_title']    = 'Activity Heatmap';
$string['heatmap_subtitle'] = 'Login frequency by day and hour';
$string['heatmap_day_mon']  = 'Mon';
$string['heatmap_day_tue']  = 'Tue';
$string['heatmap_day_wed']  = 'Wed';
$string['heatmap_day_thu']  = 'Thu';
$string['heatmap_day_fri']  = 'Fri';
$string['heatmap_day_sat']  = 'Sat';
$string['heatmap_day_sun']  = 'Sun';
$string['heatmap_midnight'] = 'Midnight';
$string['heatmap_noon']     = 'Noon';
$string['heatmap_peak']     = 'Peak: {$a} logins';
$string['heatmap_nodata']   = 'No login data for selected period.';

// =================================================================
// ANALYTICS: COMPARE
// =================================================================
$string['compare_title']        = 'Period Comparison';
$string['compare_metric']       = 'Metric';
$string['compare_period_a']     = 'Period A';
$string['compare_period_b']     = 'Period B';
$string['compare_delta']        = 'Change';
$string['compare_courses_title'] = 'Course Completion Comparison';
$string['compare_tab_periods']  = 'Period vs Period';
$string['compare_tab_courses']  = 'Cross-Course Comparison';
$string['compare_improved']     = 'Improved';
$string['compare_declined']     = 'Declined';
$string['compare_no_change']    = 'No change';
$string['compare_metric_logins']             = 'Total Logins';
$string['compare_metric_enrolments']         = 'New Enrolments';
$string['compare_metric_completions']        = 'Course Completions';
$string['compare_metric_quiz_attempts']      = 'Quiz Attempts';
$string['compare_metric_forum_posts']        = 'Forum Posts';
$string['compare_metric_assign_submissions'] = 'Assignment Submissions';

// =================================================================
// ANALYTICS: MANAGER VIEW
// =================================================================
$string['managerview_title']        = 'Manager View';
$string['mv_fullname']              = 'Name';
$string['mv_email']                 = 'Email';
$string['mv_courses']               = 'Courses';
$string['mv_completed']             = 'Completed';
$string['mv_avggrade']              = 'Avg Grade';
$string['mv_lastlogin']             = 'Last Login';
$string['mv_atrisk']                = 'At Risk';
$string['mv_viewcard']              = 'View Card';
$string['mv_select_cohort']         = 'Select cohort/department';
$string['mv_nodata']                = 'No members found.';
$string['mv_atrisk_yes']            = 'At Risk';
$string['mv_atrisk_no']             = 'On Track';
$string['managerview_nocohort']      = 'Please select a cohort/department to view team progress.';
$string['managerview_select']        = 'Select Department';
$string['managerview_total_members'] = 'Total Members';
$string['managerview_avg_completion'] = 'Avg Completion %';
$string['managerview_at_risk']       = 'At Risk';

// =================================================================
// ANALYTICS: ALERT RULES
// =================================================================
$string['alertrules_title']          = 'Alert Rules';
$string['alertrules_add']            = 'Add Alert Rule';
$string['alertrules_name']           = 'Rule Name';
$string['alertrules_condition']      = 'Condition';
$string['alertrules_threshold']      = 'Threshold';
$string['alertrules_course']         = 'Course';
$string['alertrules_cohort']         = 'Cohort';
$string['alertrules_notify_teachers'] = 'Notify Teachers';
$string['alertrules_notify_emails']  = 'Extra Emails (comma-separated)';
$string['alertrules_active']         = 'Active';
$string['alertrules_actions']        = 'Actions';
$string['alertrules_edit']           = 'Edit';
$string['alertrules_delete']         = 'Delete';
$string['alertrules_save']           = 'Save Rule';
$string['alertrules_cancel']         = 'Cancel';
$string['alertrules_confirm_delete'] = 'Are you sure you want to delete this alert rule?';
$string['alertrules_nodata']         = 'No alert rules defined. Add one to start monitoring.';
$string['alertrules_saved']          = 'Alert rule saved.';
$string['alertrules_deleted']        = 'Alert rule deleted.';
$string['alertrules_condition_types'] = 'Condition Type';
$string['condition_inactive_days']   = 'Inactive for more than {$a} days';
$string['condition_grade_below']     = 'Grade below {$a}%';
$string['condition_completion_below'] = 'Completion below {$a}%';
$string['condition_attempts_zero']   = 'No quiz attempts';
$string['condition_type_inactive_days']   = 'Inactive > N days';
$string['condition_type_grade_below']     = 'Grade below N%';
$string['condition_type_completion_below'] = 'Completion below N%';
$string['condition_type_attempts_zero']   = 'No quiz attempts';

// =================================================================
// ANALYTICS: TREND
// =================================================================
$string['trend_title']  = 'Trend (Last 12 Months)';
$string['trend_month']  = 'Month';
$string['trend_nodata'] = 'Insufficient data for trend chart.';

// =================================================================
// ANALYTICS: REPORT COLUMNS
// =================================================================

// Time spent (ts_).
$string['ts_username']    = 'Username';
$string['ts_fullname']    = 'Full Name';
$string['ts_coursename']  = 'Course';
$string['ts_events']      = 'Log Events';
$string['ts_est_minutes'] = 'Est. Minutes';
$string['ts_sessions']    = 'Sessions';
$string['ts_lastactive']  = 'Last Active';

// Course completion (cc_).
$string['cc_coursename']     = 'Course';
$string['cc_category']       = 'Category';
$string['cc_enrolled']       = 'Enrolled';
$string['cc_completed']      = 'Completed';
$string['cc_inprogress']     = 'In Progress';
$string['cc_notstarted']     = 'Not Started';
$string['cc_completionrate'] = 'Completion Rate';
$string['cc_total_courses']  = 'Total Courses';
$string['cc_completions']    = 'Completions';

// User activity (ua_).
$string['ua_username']       = 'Username';
$string['ua_fullname']       = 'Full Name';
$string['ua_email']          = 'Email';
$string['ua_lastaccess']     = 'Last Access';
$string['ua_logins']         = 'Logins';
$string['ua_coursesactive']  = 'Active Courses';
$string['ua_activitiescomp'] = 'Activities Completed';

// Grade analytics (ga_).
$string['ga_coursename'] = 'Course';
$string['ga_category']   = 'Category';
$string['ga_enrolled']   = 'Enrolled';
$string['ga_graded']     = 'Graded';
$string['ga_avggrade']   = 'Avg Grade';
$string['ga_highgrade']  = 'High';
$string['ga_lowgrade']   = 'Low';
$string['ga_passrate']   = 'Pass Rate';
$string['ga_stddev']     = 'Std Dev';
$string['ga_avg_grade']  = 'Avg Grade';

// Quiz analytics (qa_).
$string['qa_quizname']    = 'Quiz';
$string['qa_coursename']  = 'Course';
$string['qa_attempts']    = 'Attempts';
$string['qa_uniqueusers'] = 'Unique Users';
$string['qa_avggrade']    = 'Avg Grade';
$string['qa_highgrade']   = 'High';
$string['qa_lowgrade']    = 'Low';
$string['qa_passrate']    = 'Pass Rate';
$string['qa_avgtime']     = 'Avg Time (min)';

// Enrollment (en_).
$string['en_coursename']    = 'Course';
$string['en_category']      = 'Category';
$string['en_totalenrolled'] = 'Total Enrolled';
$string['en_active']        = 'Active';
$string['en_suspended']     = 'Suspended';
$string['en_newthisperiod'] = 'New This Period';
$string['en_method']        = 'Method(s)';

// Login activity (la_).
$string['la_username']     = 'Username';
$string['la_fullname']     = 'Full Name';
$string['la_email']        = 'Email';
$string['la_totallogins']  = 'Total Logins';
$string['la_lastlogin']    = 'Last Login';
$string['la_daysinactive'] = 'Days Inactive';
$string['la_logins']       = 'Logins';

// SCORM analytics (sa_).
$string['sa_scormname']       = 'SCORM Activity';
$string['sa_coursename']      = 'Course';
$string['sa_enrolled']        = 'Enrolled';
$string['sa_attempted']       = 'Attempted';
$string['sa_completed']       = 'Completed';
$string['sa_passrate']        = 'Pass Rate';
$string['sa_avgscore']        = 'Avg Score';
$string['sa_scorm_total']     = 'Total SCORM Activities';
$string['sa_scorm_attempted'] = 'Total Attempted';
$string['sa_mode']            = 'SCORM Mode';
$string['sa_criteria_met']    = 'Criteria Met';
$string['sa_passed_scorm']    = 'Passed (SCORM)';
$string['sa_moodle_complete'] = 'Moodle Complete';
$string['sa_gap']             = 'Pass-Gap';
$string['sa_gap_desc']        = 'Passed SCORM but not recorded as complete in Moodle';
$string['sa_mode_passed_incomplete']    = 'Passed/Incomplete';
$string['sa_mode_completed_incomplete'] = 'Completed/Incomplete';
$string['sa_mode_passed_failed']        = 'Passed/Failed';
$string['sa_mode_scorm2004']            = 'SCORM 2004';
$string['sa_mode_mixed']               = 'Mixed';
$string['sa_mode_unknown']             = 'Unknown';

// Assignment (ar_).
$string['ar_assignname']        = 'Assignment';
$string['ar_coursename']        = 'Course';
$string['ar_duedate']           = 'Due Date';
$string['ar_submitted']         = 'Submitted';
$string['ar_ontime']            = 'On Time';
$string['ar_late']              = 'Late';
$string['ar_graded']            = 'Graded';
$string['ar_ungraded']          = 'Ungraded';
$string['ar_avggrade']          = 'Avg Grade';
$string['ar_total_assignments'] = 'Total Assignments';

// Forum (fr_).
$string['fr_forumname']    = 'Forum';
$string['fr_coursename']   = 'Course';
$string['fr_discussions']  = 'Discussions';
$string['fr_posts']        = 'Posts';
$string['fr_participants'] = 'Participants';
$string['fr_lastpost']     = 'Last Post';
$string['fr_total_forums'] = 'Total Forums';

// Badge report.
$string['badge_user']              = 'User';
$string['badge_email']             = 'Email';
$string['badge_name']              = 'Badge Name';
$string['badge_type']              = 'Type';
$string['badge_type_site']         = 'Site-wide';
$string['badge_type_course']       = 'Course';
$string['badge_course']            = 'Course';
$string['badge_dateissued']        = 'Date Issued';
$string['badge_dateexpire']        = 'Expires';
$string['badge_never_expire']      = 'Never';
$string['badge_total_issued']      = 'Total Issued';
$string['badge_unique_badges']     = 'Unique Badges';
$string['badge_unique_recipients'] = 'Unique Recipients';

// Certificate report.
$string['report_certificate']           = 'Certificate Report';
$string['cert_user']                    = 'User';
$string['cert_email']                   = 'Email';
$string['cert_name']                    = 'Certificate';
$string['cert_course']                  = 'Course';
$string['cert_dateissued']              = 'Date Issued';
$string['cert_plugin']                  = 'Certificate Type';
$string['cert_total_issued']            = 'Total Issued';
$string['cert_unique_certs']            = 'Unique Certificates';
$string['cert_unique_recipients']       = 'Unique Recipients';
$string['cert_noplugin']                = 'No certificate plugin detected. Install mod_certificate, mod_customcert, or tool_certificate to use this report.';
$string['cert_plugin_certificate']      = 'Certificate';
$string['cert_plugin_customcert']       = 'Custom Certificate';
$string['cert_plugin_tool_certificate'] = 'Certificate Tool';

// Compliance report (comp_).
$string['comp_fullname']           = 'User';
$string['comp_email']              = 'Email';
$string['comp_coursename']         = 'Course';
$string['comp_category']           = 'Category';
$string['comp_status']             = 'Status';
$string['comp_timecompleted']      = 'Completed On';
$string['comp_daysenrolled']       = 'Days Enrolled';
$string['comp_status_completed']   = 'Completed';
$string['comp_status_outstanding'] = 'Outstanding';
$string['comp_total']              = 'Total Enrolments';
$string['comp_completed']          = 'Completed';
$string['comp_outstanding']        = 'Outstanding';
$string['comp_rate']               = 'Compliance Rate';

// Category report (cat_).
$string['cat_categoryname']     = 'Category';
$string['cat_courses']          = 'Courses';
$string['cat_enrolled']         = 'Enrolled';
$string['cat_completed']        = 'Completed';
$string['cat_completionrate']   = 'Completion %';
$string['cat_avggrade']         = 'Avg Grade';
$string['cat_total_categories'] = 'Categories';

// Instructor report (inst_).
$string['inst_fullname']          = 'Instructor';
$string['inst_email']             = 'Email';
$string['inst_courses']           = 'Courses';
$string['inst_students']          = 'Students';
$string['inst_completionrate']    = 'Completion %';
$string['inst_avggrade']          = 'Avg Grade';
$string['inst_lastactive']        = 'Last Active';
$string['inst_total_instructors'] = 'Instructors';
$string['inst_total_students']    = 'Total Students';
$string['inst_total_courses']     = 'Total Courses';

// Overview columns.
$string['overview_top5']          = 'Top 5 Courses by Completion';
$string['overview_bottom5']       = 'Lowest 5 Courses by Completion';
$string['overview_col_course']    = 'Course';
$string['overview_col_enrolled']  = 'Enrolled';
$string['overview_col_completed'] = 'Completed';
$string['overview_col_pct']       = 'Completion %';

// =================================================================
// ANALYTICS: LEADERBOARD (academic)
// =================================================================
$string['leaderboard_title']      = 'Performance Rankings';
$string['lb_rank']                = 'Rank';
$string['lb_student']             = 'Student';
$string['lb_grade']               = 'Grade %';
$string['lb_activities']          = 'Activities';
$string['lb_logins']              = 'Logins';
$string['lb_avggrade']            = 'Avg Grade %';
$string['lb_courses_completed']   = 'Courses Completed';
$string['lb_course_tab']          = 'Course Rankings';
$string['lb_cohort_tab']          = 'Cohort Rankings';
$string['lb_xp_tab']              = 'XP Rankings';
$string['lb_you']                 = 'You';

// =================================================================
// ANALYTICS: TEACHER VIEW
// =================================================================
$string['teacherview_title']      = 'Teacher View';
$string['tv_student']             = 'Student';
$string['tv_email']               = 'Email';
$string['tv_enrolled']            = 'Enrolled';
$string['tv_status']              = 'Status';
$string['tv_grade']               = 'Grade %';
$string['tv_lastaccess']          = 'Last Access';
$string['tv_viewcard']            = 'View Card';
$string['tv_completed']           = 'Completed';
$string['tv_notstarted']          = 'Not Started';
$string['tv_actions']             = 'Actions';
$string['teacherview_course']     = 'Course';
$string['teacherview_nocourses']  = 'No courses found for your account.';
$string['teacherview_nostudents'] = 'No students found for this course.';
$string['teacherview_students']   = 'students enrolled';
$string['teacherview_viewcard']   = 'View Card';
$string['teacherview']            = 'Teacher View';

// =================================================================
// ANALYTICS: MY REPORT CARD
// =================================================================
$string['myreport_title']              = 'My Report Card';
$string['myreport_courses_enrolled']   = 'Courses Enrolled';
$string['myreport_courses_completed']  = 'Completed';
$string['myreport_in_progress']        = 'In Progress';
$string['myreport_overall_avg']        = 'Overall Average';
$string['myreport_total_logins']       = 'Total Logins';
$string['myreport_overview']           = 'My Overview';
$string['myreport_courses']            = 'My Courses';
$string['myreport_quizzes']            = 'My Quiz Results';
$string['myreport_recentlogins']       = 'Recent Logins';
$string['myreport_scorm']             = 'My SCORM Activities';
$string['myreport_assignments']        = 'My Assignments';
$string['myreport_section_courses']    = 'My Courses';
$string['myreport_section_quizzes']    = 'My Quiz Results';
$string['myreport_section_logins']     = 'Recent Logins';
$string['myreport_section_scorm']      = 'SCORM Activities';
$string['myreport_section_assignments'] = 'Assignments';
$string['my_totalcourses']             = 'Courses Enrolled';
$string['my_completedcourses']         = 'Courses Completed';
$string['my_inprogresscourses']        = 'In Progress';
$string['my_overallavg']              = 'Overall Average';
$string['my_totallogins']             = 'Total Logins';
$string['my_membersince']             = 'Member Since';
$string['my_lastlogin']               = 'Last Login';
$string['my_course']                   = 'Course';
$string['my_enrolled']                 = 'Enrolled On';
$string['my_started']                  = 'Started On';
$string['my_completed']                = 'Completed On';
$string['my_logins_showing']           = 'Showing 10 most recent of {$a} total logins';
$string['my_status']                   = 'Status';
$string['my_grade']                    = 'Grade';
$string['my_progress']                 = 'Activities Done';
$string['my_status_completed']         = 'Completed';
$string['my_status_inprogress']        = 'In Progress';
$string['my_status_notstarted']        = 'Not Started';
$string['my_quiz']                     = 'Quiz';
$string['my_attempts']                 = 'Attempts';
$string['my_bestgrade']                = 'Best Grade';
$string['my_lastgrade']                = 'Last Grade';
$string['my_lastattempt']              = 'Last Attempt';
$string['my_passed']                   = 'Result';
$string['my_pass']                     = 'Pass';
$string['my_fail']                     = 'Fail';
$string['my_logintime']                = 'Login Time';
$string['my_loginip']                  = 'IP Address';
$string['my_nologins']                 = 'No logins recorded in this period.';
$string['my_scorm_activity']           = 'Activity';
$string['my_scorm_course']             = 'Course';
$string['my_scorm_status']             = 'Status';
$string['my_scorm_score']              = 'Best Score';
$string['my_scorm_attempts']           = 'Attempts';
$string['my_scorm_passed']             = 'Passed';
$string['my_scorm_completed']          = 'Completed';
$string['my_scorm_failed']             = 'Failed';
$string['my_scorm_incomplete']         = 'In Progress';
$string['my_scorm_notattempted']       = 'Not Attempted';
$string['my_assign_name']             = 'Assignment';
$string['my_assign_course']            = 'Course';
$string['my_assign_due']               = 'Due Date';
$string['my_assign_status']            = 'Status';
$string['my_assign_grade']             = 'Grade';
$string['my_assign_submitted']         = 'Submitted';
$string['my_assign_late']              = 'Late';
$string['my_assign_notsubmitted']      = 'Not Submitted';
$string['my_assign_graded']            = 'Graded';
$string['myreport_quiz_name']          = 'Quiz';
$string['myreport_quiz_course']        = 'Course';
$string['myreport_quiz_attempts']      = 'Attempts';
$string['myreport_quiz_best']          = 'Best Grade';
$string['myreport_quiz_last']          = 'Last Grade';
$string['myreport_quiz_result']        = 'Result';
$string['myreport_quiz_lasttime']      = 'Last Attempt';
$string['myreport_login_no']           = '#';
$string['myreport_login_time']         = 'Login Time';
$string['myreport_login_ip']           = 'IP Address';
$string['myreport_scorm_name']         = 'Activity';
$string['myreport_scorm_course']       = 'Course';
$string['myreport_scorm_status']       = 'Status';
$string['myreport_scorm_score']        = 'Best Score';
$string['myreport_scorm_attempts']     = 'Attempts';
$string['myreport_assign_name']        = 'Assignment';
$string['myreport_assign_course']      = 'Course';
$string['myreport_assign_due']         = 'Due Date';
$string['myreport_assign_status']      = 'Status';
$string['myreport_assign_grade']       = 'Grade';
$string['myreport_assign_submitted']   = 'Submitted';
$string['myreport_assign_late']        = 'Late';
$string['myreport_assign_notsubmitted'] = 'Not Submitted';
$string['myreport_pass']               = 'Pass';
$string['myreport_fail']               = 'Fail';
$string['myreport_export_pdf']         = 'Export PDF';
$string['myreport_export_csv']         = 'Export CSV';
$string['myreport_filterbar_label']    = 'Showing data for:';
$string['myreport_alltime']            = 'All Time';
$string['myreport']                    = 'My Report Card';
$string['myreport_viewuser']           = 'View report for:';
$string['myreport_viewbtn']            = 'View Report';
$string['quickrange_7']                = 'Last 7 days';
$string['quickrange_30']               = 'Last 30 days';
$string['quickrange_90']               = 'Last 90 days';
$string['quickrange_365']              = 'Last 12 months';
$string['quickrange_all']              = 'All time';

// =================================================================
// ANALYTICS: ILT / VILT
// =================================================================
$string['ilt_title']                  = 'ILT / VILT Session Data';
$string['ilt_upload_heading']         = 'Upload Session CSV';
$string['ilt_upload_desc']            = 'Upload a CSV file containing ILT or VILT attendance records. Each row represents one attendee at one session.';
$string['ilt_choose_file']            = 'Choose CSV file (.csv)';
$string['ilt_upload_btn']             = 'Upload';
$string['ilt_download_template']      = 'Download Template CSV';
$string['ilt_view_report']            = 'View ILT / VILT Report';
$string['ilt_files_heading']          = 'Uploaded Session Files';
$string['ilt_files_count']            = 'files uploaded';
$string['ilt_nofiles']                = 'No session files uploaded yet. Upload a CSV to get started.';
$string['ilt_col_filename']           = 'Filename';
$string['ilt_col_uploaded']           = 'Uploaded';
$string['ilt_col_size']               = 'Size';
$string['ilt_col_records']            = 'Records';
$string['ilt_col_actions']            = 'Actions';
$string['ilt_rows']                   = 'rows';
$string['ilt_delete']                 = 'Delete';
$string['ilt_confirm_delete']         = 'Delete this file? All attendance records in it will be removed from reports.';
$string['ilt_deleted']                = 'File deleted successfully.';
$string['ilt_uploaded']               = 'File uploaded successfully.';
$string['ilt_error_upload']           = 'File upload failed. Please try again.';
$string['ilt_error_notcsv']           = 'Only CSV files (.csv) are accepted.';
$string['ilt_error_missingcols']      = 'CSV is missing required columns: {$a}.';
$string['ilt_date']                   = 'Date';
$string['ilt_session_title']          = 'Session Title';
$string['ilt_type']                   = 'Type';
$string['ilt_type_ilt']               = 'ILT';
$string['ilt_type_vilt']              = 'VILT';
$string['ilt_facilitator']            = 'Facilitator';
$string['ilt_department']             = 'Department';
$string['ilt_attendee_name']          = 'Attendee';
$string['ilt_attendee_email']         = 'Email';
$string['ilt_duration_minutes']       = 'Duration (min)';
$string['ilt_outcome']                = 'Outcome';
$string['ilt_summary_sessions']       = 'Total Sessions';
$string['ilt_summary_attendees']      = 'Attendance Records';
$string['ilt_summary_attendance_rate'] = 'Attendance Rate';
$string['ilt_summary_ilt']            = 'ILT Sessions';
$string['ilt_summary_vilt']           = 'VILT Sessions';

// ILT: New session-level columns.
$string['ilt_registered']             = 'Registered';
$string['ilt_attended']               = 'Attended';
$string['ilt_noshow']                 = 'No-Shows';
$string['ilt_attendance_rate']        = 'Attendance Rate';
$string['ilt_duration_hours']         = 'Duration (hrs)';
$string['ilt_cost_per_head']          = 'Cost / Head';
$string['ilt_total_cost']             = 'Total Cost';
$string['ilt_fill_rate']              = 'Fill Rate';
$string['ilt_avg_rating']             = 'Avg Rating';
$string['ilt_pass_rate']              = 'Pass Rate';
$string['ilt_location']               = 'Location';

// ILT: Summary KPIs.
$string['ilt_summary_registered']     = 'Total Registered';
$string['ilt_summary_attended']       = 'Total Attended';
$string['ilt_summary_noshows']        = 'Total No-Shows';
$string['ilt_summary_total_hours']    = 'Total Training Hours';
$string['ilt_summary_total_cost']     = 'Total Cost';
$string['ilt_summary_cost_per_attendee'] = 'Cost / Attendee';

// ILT: Chart titles.
$string['ilt_chart_sessions_by_month'] = 'Sessions by Month (ILT vs VILT)';
$string['ilt_chart_top_facilitators']  = 'Top Facilitators by Session Count';
$string['ilt_chart_sessions']          = 'Sessions';

// =================================================================
// ANALYTICS: SKILLS
// =================================================================
$string['skills_manage_title']         = 'Manage Skills';
$string['skills_tab_skills']           = 'Skills';
$string['skills_tab_mapping']          = 'Course Mapping';
$string['skill_name']                  = 'Skill Name';
$string['skill_description']           = 'Description';
$string['skill_add']                   = 'Add Skill';
$string['skill_edit']                  = 'Edit Skill';
$string['skill_delete']                = 'Delete';
$string['skill_save']                  = 'Save Skill';
$string['skill_saved']                 = 'Skill saved successfully.';
$string['skill_deleted']               = 'Skill deleted.';
$string['skill_name_required']         = 'Skill name is required.';
$string['skill_courses']               = 'Courses that develop this skill';
$string['skill_noskills']              = 'No skills defined yet. Add your first skill above.';
$string['skills_mapping_title']        = 'Map Skills to Courses';
$string['skills_mapping_intro']        = 'Select the skills developed by each course.';
$string['skills_mapping_saved']        = 'Course skill mapping saved.';
$string['skills_mapping_none']         = 'No courses found.';
$string['skillsreport_title']          = 'Skills Analytics';
$string['skillsreport_subtitle']       = 'Organisation-wide skill coverage based on course completion data.';
$string['skillsreport_col_skill']      = 'Skill';
$string['skillsreport_col_courses']    = 'Courses';
$string['skillsreport_col_learners']   = 'Learners Qualified';
$string['skillsreport_col_coverage']   = 'Coverage (%)';
$string['skillsreport_top_skills']     = 'Top Skills by Learner Count';
$string['skillsreport_gap']            = 'Skills Gap (Fewest Qualified)';
$string['skillsreport_nodata']         = 'No skill mappings found. Go to Skills > Course Mapping to assign skills to courses.';
$string['skillsreport_total_skills']   = 'Total Skills Defined';
$string['skillsreport_total_learners'] = 'Learners with 1+ Skill';
$string['skillsreport_avg_skills']     = 'Avg Skills per Learner';

// =================================================================
// ANALYTICS: MY TEAM SKILLS
// =================================================================
$string['myteam_skills_title']        = 'My Team Skills';
$string['myteam_skills_subtitle']     = 'Skills gained by your team members through course completions.';
$string['myteam_col_member']          = 'Team Member';
$string['myteam_col_skills']          = 'Skills Gained';
$string['myteam_col_completions']     = 'Completions';
$string['myteam_col_last_active']     = 'Last Active';
$string['myteam_noassignment']        = 'You have no cohorts assigned. Ask your administrator to assign cohorts in Team Setup.';
$string['myteam_noskills']            = 'No skills recorded for your team yet.';
$string['myteam_skill_badge']         = '{$a} skills';

// =================================================================
// ANALYTICS: TEAM SETUP
// =================================================================
$string['teamsetup_title']            = 'Team Setup';
$string['teamsetup_subtitle']         = 'Assign managers or supervisors to cohorts.';
$string['teamsetup_col_manager']      = 'Manager / Supervisor';
$string['teamsetup_col_cohort']       = 'Cohort';
$string['teamsetup_col_assigned']     = 'Assigned';
$string['teamsetup_col_actions']      = 'Actions';
$string['teamsetup_add_title']        = 'Add Assignment';
$string['teamsetup_select_user']      = 'Select User';
$string['teamsetup_select_cohort']    = 'Select Cohort';
$string['teamsetup_add_btn']          = 'Add';
$string['teamsetup_remove']           = 'Remove';
$string['teamsetup_saved']            = 'Assignment added.';
$string['teamsetup_removed']          = 'Assignment removed.';
$string['teamsetup_duplicate']        = 'This manager is already assigned to that cohort.';
$string['teamsetup_noassignments']    = 'No assignments yet.';
$string['teamsetup_nouser']           = 'Please select a user.';
$string['teamsetup_nocohort']         = 'Please select a cohort.';

// =================================================================
// ANALYTICS: ROI
// =================================================================
$string['roicosts_title']             = 'ROI Cost Management';
$string['roicosts_subtitle']          = 'Enter cost and value data per course to enable ROI reporting.';
$string['roicosts_col_course']        = 'Course';
$string['roicosts_col_cost']          = 'Cost per Learner';
$string['roicosts_col_value']         = 'Value per Completion';
$string['roicosts_col_currency']      = 'Currency';
$string['roicosts_col_notes']         = 'Notes';
$string['roicosts_save']              = 'Save All';
$string['roicosts_saved']             = 'Cost data saved successfully.';
$string['roicosts_hint']              = 'If Value per Completion is left at 0, the system will use 1.5 x Cost per Learner as the estimated value.';
$string['roicosts_selectcourse']      = '-- Select a course --';
$string['roicosts_savecourse']        = 'Save Cost Data';
$string['roicosts_entercost']         = 'Please select a course and enter at least a cost or value amount.';
$string['roicosts_deleted']           = 'Cost entry removed.';
$string['roicosts_confirmdelete']     = 'Are you sure you want to remove this cost entry?';
$string['roicosts_add_heading']       = 'Add Course Cost Data';
$string['roicosts_edit_heading']      = 'Edit Course Cost Data';
$string['roicosts_saved_heading']     = 'Courses with Cost Data';
$string['roicosts_noentries']         = 'No cost entries yet. Use the form above to add cost data for a course.';
$string['roicosts_viewreport']        = 'View ROI Report';
$string['roicosts_cost_help']         = 'What you spend per learner enrolled in this course.';
$string['roicosts_value_help']        = 'Leave blank to auto-calculate as 1.5x the cost.';
$string['roicosts_howworks_title']    = 'How ROI Works';
$string['roicosts_howworks_intro']    = 'Return on Investment (ROI) measures the financial return of your training programmes relative to their cost.';
$string['roicosts_howworks_step1']    = '<strong>Cost per Learner</strong> — Enter what you spend per person enrolled in a course.';
$string['roicosts_howworks_step2']    = '<strong>Value per Completion</strong> — Enter the estimated business value when a learner completes the course. Leave blank and the system will estimate it at 1.5x the cost.';
$string['roicosts_howworks_step3']    = '<strong>ROI Calculation</strong> — The system multiplies Cost per Learner by total enrolments to get Total Investment, and Value per Completion by total completions to get Total Value.';
$string['roicosts_formula_label']     = 'Formula:';
$string['roicosts_formula']           = 'ROI % = ((Total Value - Total Investment) / Total Investment) x 100';
$string['roicosts_howworks_example']  = 'Example: A course costs $50/learner with 100 enrolments ($5,000 investment). If 80 learners complete it at $75 value each ($6,000 value), the ROI is 20%.';
$string['roicosts_howworks_tip']      = 'Tip: A positive ROI means the training delivered more value than it cost. Focus on improving completion rates to maximise your ROI.';
$string['roi_title']                  = 'ROI Report';
$string['roi_tab_executive']          = 'Executive Summary';
$string['roi_tab_analyst']            = 'Analyst Detail';
$string['roi_kpi_investment']         = 'Total Investment';
$string['roi_kpi_value']              = 'Total Value Delivered';
$string['roi_kpi_roi']                = 'Overall ROI';
$string['roi_kpi_costpercompletion']  = 'Cost per Completion';
$string['roi_kpi_completions']        = 'Total Completions';
$string['roi_kpi_courses']            = 'Courses Tracked';
$string['roi_chart_title']            = 'ROI by Course';
$string['roi_top3']                   = 'Top 3 Highest-ROI Courses';
$string['roi_nodata']                 = 'No ROI data found. Add cost data in ROI Costs before viewing this report.';
$string['roi_nocostdata']             = 'No cost data entered yet.';
$string['roi_col_course']             = 'Course';
$string['roi_col_enrolled']           = 'Enrolled';
$string['roi_col_completions']        = 'Completions';
$string['roi_col_rate']               = 'Completion Rate';
$string['roi_col_cost']               = 'Total Cost';
$string['roi_col_value']              = 'Total Value';
$string['roi_col_net']                = 'Net Return';
$string['roi_col_roi']                = 'ROI %';
$string['roi_col_currency']           = 'Currency';
$string['roi_positive']               = 'Positive ROI';
$string['roi_negative']               = 'Negative ROI';
$string['roi_executive_narrative']     = 'Based on {$a->courses} tracked courses with {$a->completions} total completions, your organisation has invested {$a->investment} and delivered an estimated {$a->value} in training value — an overall ROI of {$a->roi}.';
$string['roi_link_costs']             = 'Manage Cost Data';
$string['roi_mixed_currency']         = 'Warning: Your cost data uses multiple currencies ({$a}). Aggregate totals may not be meaningful. For accurate reporting, use a single currency across all courses.';

// =================================================================
// ANALYTICS: CHARTS
// =================================================================
$string['chart_mau']             = 'Monthly Active Users';
$string['chart_completions']     = 'Completions by Month';
$string['chart_enrolments']      = 'New Enrolments by Month';
$string['chart_grade_dist']      = 'Grade Distribution';
$string['chart_top_courses']     = 'Top Courses by Completion Rate';
$string['chart_atrisk']          = 'At-Risk Students';
$string['chart_progress']        = 'Course Progress';
$string['chart_rolling12']       = 'Rolling 12 months';
$string['chart_alltime']         = 'All time';
$string['view_all']              = 'View all';
$string['never_logged_in']       = 'Never logged in';
$string['days_inactive']         = '{$a} days inactive';

// =================================================================
// GAMIFICATION: DASHBOARD
// =================================================================
$string['gamify_dashboard_title']    = 'My Learning Dashboard';
$string['gamify_dashboard_subtitle'] = 'Your XP, achievements, and progress at a glance.';
$string['stat_total_xp']            = 'Total XP';
$string['stat_completions']         = 'Courses Completed';
$string['stat_achievements']        = 'Achievements';
$string['streak_day_label']         = 'Day Streak';
$string['recent_activity']          = 'Recent XP Activity';
$string['no_recent_activity']       = 'No XP activity yet. Complete a course to get started!';
$string['active_campaigns']         = 'Active Campaigns';
$string['your_paths']               = 'Your Learning Paths';
$string['complete']                 = 'complete';
$string['level_none']               = 'No Level Yet';
$string['xp_multiplier']            = '{$a}x XP';
$string['ends']                     = 'Ends';
$string['to_next_level']            = 'to next level';
$string['streak_on_fire']           = 'You\'re on fire!';

// =================================================================
// GAMIFICATION: XP EVENT LABELS
// =================================================================
$string['event_course_completion']   = 'Course Completed';
$string['event_quiz_pass_first']     = 'Quiz Passed (1st Attempt)';
$string['event_quiz_attempt']        = 'Quiz Attempted';
$string['event_assign_ontime']       = 'Assignment Submitted On Time';
$string['event_assign_submitted']    = 'Assignment Submitted';
$string['event_forum_post']          = 'Forum Post';
$string['event_badge_awarded']       = 'Badge Earned';
$string['event_login_streak']        = 'Login Streak Milestone';
$string['event_streak_milestone']    = 'Learning Streak Milestone';
$string['event_path_completion']     = 'Learning Path Completed';
$string['event_campaign_completion'] = 'Campaign Completed';
$string['event_spotlight_received']  = 'Spotlight Recognition Received';
$string['event_achievement_bonus']   = 'Achievement Bonus';
$string['event_manual']              = 'Manual Award';
$string['event_manual_award']        = 'Manual Award';
$string['event_level_up']            = 'Level Up Bonus';
$string['event_redemption']          = 'Reward Redeemed';
$string['event_redemption_refund']   = 'Redemption Refund';

// =================================================================
// GAMIFICATION: LEVELS
// =================================================================
$string['levels_title']          = 'Manage Levels';
$string['levels_subtitle']       = 'Define the XP thresholds and names for each learner level.';
$string['level_num']             = 'Level #';
$string['level_name']            = 'Level Name';
$string['level_min_xp']          = 'Minimum XP';
$string['level_points_required'] = 'XP Required';
$string['level_icon']            = 'Icon (emoji)';
$string['add_level']             = 'Add Level';
$string['edit_level']            = 'Edit Level';
$string['level_add']             = 'Add Level';
$string['level_save']            = 'Save Level';
$string['level_saved']           = 'Level saved.';
$string['level_deleted']         = 'Level deleted.';
$string['level_nodata']          = 'No levels defined. Add your first level.';
$string['level_duplicate']       = 'A level with that number already exists.';
$string['level_default_1']       = 'Learner';
$string['level_default_2']       = 'Explorer';
$string['level_default_3']       = 'Achiever';
$string['level_default_4']       = 'Champion';
$string['level_default_5']       = 'Legend';

// =================================================================
// GAMIFICATION: ACHIEVEMENTS
// =================================================================
$string['achievements_title']    = 'My Achievements';
$string['achievements_subtitle'] = 'Milestones and badges you have earned.';
$string['achiev_earned']         = 'Earned Achievements';
$string['achiev_locked']         = 'Locked Achievements';
$string['achiev_earned_count']   = 'Earned: {$a}';
$string['achiev_earned_on']      = 'Earned {$a}';
$string['achiev_progress']       = '{$a->done} / {$a->total}';
$string['no_achievements']       = 'No achievements defined yet.';
$string['achiev_nodata']         = 'Keep learning to unlock achievements!';
$string['achiev_xp_bonus']       = '+{$a} XP bonus';
$string['achiev_cert_note']      = 'These certificates are awarded for reaching level and streak milestones.';
$string['achiev_xp_to_next']     = '{$a} XP to next level';
$string['achiev_max_level']      = 'Max level reached!';
$string['achiev_next_level']     = 'Next';

// =================================================================
// GAMIFICATION: LEADERBOARD (XP)
// =================================================================
$string['xp_leaderboard_title']    = 'XP Leaderboard';
$string['xp_leaderboard_subtitle'] = 'See how your XP compares across the organisation.';
$string['lb_tab_org']              = 'Organisation';
$string['lb_tab_dept']             = 'My Department';
$string['lb_col_rank']             = 'Rank';
$string['lb_col_name']             = 'Learner';
$string['lb_col_learner']          = 'Learner';
$string['lb_col_level']            = 'Level';
$string['lb_col_xp']               = 'XP';
$string['lb_col_streak']           = 'Streak';
$string['lb_col_completions']      = 'Completions';
$string['lb_empty']                = 'No learners on the leaderboard yet.';
$string['lb_nodata']               = 'No learners found.';
$string['lb_anonymous']            = 'Anonymous Learner';

// =================================================================
// GAMIFICATION: REWARDS
// =================================================================
$string['rewards_title']           = 'Reward Catalog';
$string['rewards_subtitle']        = 'Spend your XP on rewards. Current balance: {$a} XP.';
$string['your_balance']            = 'Your XP Balance';
$string['reward_cost']             = '{$a} XP';
$string['redeem_btn']              = 'Redeem';
$string['out_of_stock']            = 'Out of Stock';
$string['insufficient_xp']         = 'You do not have enough XP to redeem this reward.';
$string['no_rewards']              = 'No rewards available yet.';
$string['qty_left']                = '{$a} remaining';
$string['reward_out_of_stock']     = 'Out of Stock';
$string['reward_insufficient']     = 'Not Enough XP';
$string['reward_nodata']           = 'No rewards available yet.';
$string['reward_type_voucher']     = 'Voucher';
$string['reward_type_leave']       = 'Extra Leave';
$string['reward_type_merch']       = 'Merchandise';
$string['reward_type_other']       = 'Other';
$string['reward_qty_left']         = '{$a} remaining';
$string['reward_qty_unlimited']    = 'Unlimited';

// =================================================================
// GAMIFICATION: REDEMPTIONS
// =================================================================
$string['redeem_title']            = 'Confirm Redemption';
$string['redeem_confirm_heading']  = 'Confirm Your Redemption';
$string['redeem_cost_line']        = 'Cost: {$a}';
$string['redeem_balance_after']    = 'Balance after redemption: {$a}';
$string['redeem_confirm_btn']      = 'Confirm Redemption';
$string['redeem_confirm']          = 'Are you sure you want to redeem "{$a->name}" for {$a->cost} XP?';
$string['redemption_submitted']    = 'Redemption request submitted. Your manager will be notified.';
$string['redeem_insufficient_xp']  = 'You do not have enough XP to redeem this reward.';
$string['redeem_not_available']    = 'This reward is no longer available.';
$string['redeem_status_pending']   = 'Pending Approval';
$string['redeem_status_approved']  = 'Approved';
$string['redeem_status_fulfilled'] = 'Fulfilled';
$string['redeem_status_rejected']  = 'Rejected';
$string['my_redemptions_title']    = 'My Redemption Requests';
$string['my_redemptions_nodata']   = 'You have no redemption requests.';

// =================================================================
// GAMIFICATION: CAMPAIGNS
// =================================================================
$string['campaigns_title']         = 'Learning Campaigns';
$string['campaigns_subtitle']      = 'Time-limited learning sprints with bonus XP rewards.';
$string['campaign_status_active']  = 'Active';
$string['campaign_status_upcoming'] = 'Upcoming';
$string['campaign_status_ended']   = 'Ended';
$string['campaign_active']         = 'Active';
$string['campaign_upcoming']       = 'Upcoming';
$string['campaign_ended']          = 'Ended';
$string['campaign_ends_in']        = 'Ends in {$a}';
$string['campaign_starts_in']      = 'Starts in {$a}';
$string['campaign_multiplier']     = 'Multiplier';
$string['included_courses']        = 'Included Courses';
$string['no_campaigns']            = 'No campaigns available right now.';
$string['campaign_bonus_xp']       = '+{$a} XP on completion';
$string['campaign_progress']       = '{$a->done} of {$a->total} courses completed';
$string['campaign_completed_on']   = 'Completed on {$a}';
$string['campaign_nodata']         = 'No active campaigns right now. Check back soon!';
$string['campaign_all_courses']    = 'All courses';

// =================================================================
// GAMIFICATION: LEARNING PATHS
// =================================================================
$string['paths_title']      = 'Learning Paths';
$string['paths_subtitle']   = 'Structured journeys to build your skills step by step.';
$string['path_courses']     = '{$a} courses';
$string['path_bonus']       = '+{$a} XP on completion';
$string['path_start']       = 'Start Path';
$string['path_continue']    = 'Continue';
$string['path_completed']   = 'Completed!';
$string['path_progress']    = '{$a->done}/{$a->total} courses done';
$string['no_paths']         = 'No learning paths are available for you right now.';
$string['path_nodata']      = 'No learning paths are available for you right now.';

// =================================================================
// GAMIFICATION: RECOGNITION
// =================================================================
$string['recognition_title']    = 'Recognition Wall';
$string['recognition_subtitle'] = 'Celebrating great learning across the organisation.';
$string['recognition_gave']     = '{$a->from} recognised {$a->to}';
$string['spotlight']            = 'Spotlight';
$string['no_recognition']       = 'No recognitions yet. Ask a manager to spotlight a great learner!';
$string['recognition_nodata']   = 'No recognitions yet. Ask a manager to spotlight a great learner!';

// =================================================================
// GAMIFICATION: CERTIFICATES
// =================================================================
$string['gamify_cert_title']     = 'Achievement Certificate';
$string['gamify_cert_download']  = 'Download Certificate';
$string['cert_awarded_to']      = 'Awarded to';
$string['cert_for']             = 'For achieving: {$a}';
$string['cert_date']            = 'Date';
$string['cert_issued_by']       = 'Issued by';
$string['cert_not_earned']      = 'You have not yet earned this certificate.';
$string['cert_type_level']      = 'Level {$a} Achievement';
$string['cert_type_xp']         = '{$a} XP Milestone';
$string['cert_type_path']       = 'Learning Path Completion';
$string['cert_type_campaign']   = 'Campaign Completion';
$string['cert_type_streak']     = '{$a}-Day Streak';

// =================================================================
// GAMIFICATION: STREAKS / NUDGES
// =================================================================
$string['streak_days']              = '{$a} days';
$string['streak_broken']            = 'Streak reset';
$string['streak_milestone_subject'] = 'You\'ve hit a {$a}-day streak!';
$string['streak_milestone_body']    = 'Amazing work, {$a->firstname}! You\'ve maintained a {$a->streak}-day learning streak and earned {$a->xp} bonus XP!';
$string['nudge_subject']            = 'Keep your learning streak going — {$a}';
$string['nudge_body']               = "Hi {$a->firstname},\n\nIt looks like you haven't logged in recently. Don't lose your progress!\n\nVisit your learning dashboard: {$a->siteurl}\n\nKeep it up,\n{$a->sitename}";

// =================================================================
// GAMIFICATION: WEEKLY DIGEST
// =================================================================
$string['digest_subject']     = 'Weekly Team Learning Digest — {$a}';
$string['digest_body']        = "Hi {$a->firstname},\n\nYour team earned {$a->team_xp} XP this week. Keep up the great work!\n\nView your team: {$a->teamurl}\n\n{$a->sitename}";
$string['digest_body_intro']  = 'Here\'s a summary of your team\'s learning activity this week:';
$string['digest_top_earner']  = 'Top XP earner: {$a->name} ({$a->xp} XP)';
$string['digest_completions'] = 'Total completions: {$a}';
$string['digest_at_risk']     = 'Inactive 7+ days: {$a} members';

// =================================================================
// MANAGER: SPOTLIGHT
// =================================================================
$string['spotlight_title']               = 'Award Spotlight Recognition';
$string['spotlight_subtitle']            = 'Publicly recognise a team member\'s learning achievement.';
$string['award_spotlight']               = 'Award Spotlight Recognition';
$string['spotlight_remaining']           = 'Spotlights remaining today: {$a}';
$string['spotlight_recipient']           = 'Recipient';
$string['spotlight_message']             = 'Recognition Message';
$string['spotlight_message_placeholder'] = 'What did they achieve? Be specific and encouraging.';
$string['spotlight_message_ph']          = 'What did they achieve? Be specific and encouraging.';
$string['send_spotlight']                = 'Send Spotlight';
$string['spotlight_submit']              = 'Award Recognition';
$string['spotlight_sent']                = 'Spotlight recognition awarded successfully!';
$string['spotlight_saved']               = 'Recognition awarded successfully!';
$string['spotlight_limit_reached']       = 'You have reached your daily spotlight limit.';
$string['spotlight_limit']               = 'You have reached your daily spotlight limit ({$a}).';
$string['spotlight_self_error']          = 'You cannot award a spotlight to yourself.';
$string['spotlight_from']                = 'Spotlight from {$a}';
$string['select_team_member']            = 'Select a team member...';
$string['recent_spotlights']             = 'Recently Awarded';
$string['spotlight_select_user']         = 'Select Team Member';
$string['spotlight_noassign']            = 'You are not assigned to any team cohorts. Ask an administrator.';
$string['spotlight_required']            = 'Please select a team member and write a message.';

// =================================================================
// MANAGER: TEAM VIEW
// =================================================================
$string['myteam_title']           = 'My Team';
$string['myteam_subtitle']        = 'Engagement and XP overview for your team members.';
$string['myteam_col_level']       = 'Level';
$string['myteam_col_xp']          = 'XP';
$string['myteam_col_streak']      = 'Streak';
$string['myteam_col_lastactive']  = 'Last Active';
$string['myteam_noassign']        = 'You have no cohorts assigned. Contact an administrator.';
$string['myteam_nodata']          = 'No team members found.';
$string['no_cohorts_assigned']    = 'You are not assigned to any cohorts. Ask an administrator.';
$string['team_empty']             = 'No team members found in this cohort.';

// =================================================================
// MANAGER: REDEMPTION APPROVALS
// =================================================================
$string['mgr_redemptions_title']     = 'Redemption Approvals';
$string['mgr_redemptions_subtitle']  = 'Review and action pending reward redemption requests from your team.';
$string['mgr_col_learner']          = 'Learner';
$string['mgr_col_reward']           = 'Reward';
$string['mgr_col_cost']             = 'XP Cost';
$string['mgr_col_requested']        = 'Requested';
$string['mgr_col_status']           = 'Status';
$string['mgr_col_actions']          = 'Actions';
$string['approve']                   = 'Approve';
$string['reject']                    = 'Reject';
$string['mark_fulfilled']            = 'Mark Fulfilled';
$string['mgr_approve']              = 'Approve';
$string['mgr_reject']               = 'Reject';
$string['mgr_fulfill']              = 'Mark Fulfilled';
$string['redemption_approved']       = 'Redemption approved.';
$string['redemption_rejected']       = 'Redemption rejected and XP refunded.';
$string['redemption_fulfilled']      = 'Redemption marked as fulfilled.';
$string['mgr_approved']             = 'Request approved.';
$string['mgr_rejected']             = 'Request rejected.';
$string['mgr_fulfilled']            = 'Request marked as fulfilled.';
$string['no_redemptions']           = 'No redemption requests found.';
$string['mgr_nodata']               = 'No pending redemption requests.';
$string['mgr_notes_ph']             = 'Optional notes for the learner...';
$string['access_denied']             = 'Access denied.';
$string['redemption_status_pending']   = 'Pending';
$string['redemption_status_approved']  = 'Approved';
$string['redemption_status_fulfilled'] = 'Fulfilled';
$string['redemption_status_rejected']  = 'Rejected';

// =================================================================
// ADMIN: REWARDS
// =================================================================
$string['admin_rewards_title']    = 'Manage Reward Catalog';
$string['admin_rewards_subtitle'] = 'Create and manage rewards that learners can redeem with their XP.';
$string['admin_rewards_desc']     = 'Create and manage rewards that learners can redeem using their accumulated XP.';
$string['reward_name']            = 'Reward Name';
$string['reward_desc']            = 'Description';
$string['reward_type']            = 'Type';
$string['reward_cost_xp']         = 'XP Cost';
$string['reward_xp_cost']         = 'XP Cost';
$string['reward_qty']             = 'Quantity';
$string['reward_quantity']        = 'Quantity (-1 = unlimited)';
$string['reward_available']       = 'Available';
$string['add_reward']             = 'Add Reward';
$string['edit_reward']            = 'Edit Reward';
$string['reward_add']             = 'Add Reward';
$string['reward_save']            = 'Save Reward';
$string['reward_saved']           = 'Reward saved.';
$string['reward_deleted']         = 'Reward deleted.';
$string['reward_col_name']        = 'Reward';
$string['reward_col_type']        = 'Type';
$string['reward_col_cost']        = 'XP Cost';
$string['reward_col_qty']         = 'Qty Left';
$string['reward_col_status']      = 'Status';
$string['reward_col_actions']     = 'Actions';
$string['leave_blank_unlimited']  = 'leave blank for unlimited';
$string['reward_has_pending']     = 'This reward has pending redemption requests and cannot be deleted.';

// =================================================================
// ADMIN: CAMPAIGNS
// =================================================================
$string['admin_campaigns_title']       = 'Manage Campaigns';
$string['admin_campaigns_subtitle']    = 'Create time-limited learning sprints with XP bonuses.';
$string['admin_campaigns_desc']        = 'Create time-limited learning sprints with an XP multiplier.';
$string['campaign_name']               = 'Campaign Name';
$string['campaign_desc']               = 'Description';
$string['campaign_startdate']          = 'Start Date';
$string['campaign_enddate']            = 'End Date';
$string['campaign_xp_multiplier']      = 'XP Multiplier';
$string['campaign_xp_multiplier_desc'] = 'E.g. 1.5 = 50% bonus XP during campaign.';
$string['campaign_xp_bonus']           = 'Completion Bonus XP';
$string['campaign_required_courses']   = 'Required Courses (leave blank for all)';
$string['campaign_courses']            = 'Included Courses';
$string['add_campaign']                = 'Add Campaign';
$string['edit_campaign']               = 'Edit Campaign';
$string['campaign_add']                = 'Add Campaign';
$string['campaign_save']               = 'Save Campaign';
$string['campaign_saved']              = 'Campaign saved.';
$string['campaign_deleted']            = 'Campaign deleted.';
$string['campaign_date_error']         = 'End date must be after start date.';
$string['campaign_col_name']           = 'Campaign';
$string['campaign_col_dates']          = 'Dates';
$string['campaign_col_multiplier']     = 'Multiplier';
$string['campaign_col_bonus']          = 'Bonus XP';
$string['campaign_col_status']         = 'Status';
$string['campaign_col_actions']        = 'Actions';
$string['campaign_nodata_admin']       = 'No campaigns defined yet.';
$string['campaign_live']               = 'Live';
$string['campaign_courses_hint']       = 'Hold Ctrl / Cmd to select multiple courses.';

// =================================================================
// ADMIN: LEARNING PATHS
// =================================================================
$string['admin_paths_title']     = 'Manage Learning Paths';
$string['admin_paths_subtitle']  = 'Define structured course sequences that reward completion with bonus XP.';
$string['admin_paths_desc']      = 'Define structured course sequences that reward learners with bonus XP on completion.';
$string['path_name']             = 'Path Name';
$string['path_desc']             = 'Description';
$string['path_select_courses']   = 'Courses (in order)';
$string['path_xp_bonus']         = 'Completion Bonus XP';
$string['path_cohort']           = 'Restrict to Cohort (0 = all)';
$string['path_active']           = 'Active';
$string['add_path']              = 'Add Path';
$string['edit_path']             = 'Edit Path';
$string['path_add']              = 'Add Path';
$string['path_save']             = 'Save Path';
$string['path_saved']            = 'Learning path saved.';
$string['path_deleted']          = 'Learning path deleted.';
$string['path_courses_hint']     = 'Hold Ctrl / Cmd to select multiple. Order of selection = path step order.';
$string['path_col_name']         = 'Path';
$string['path_col_courses']      = 'Courses';
$string['path_col_bonus']        = 'Bonus XP';
$string['path_col_cohort']       = 'Cohort';
$string['path_col_status']       = 'Status';
$string['path_col_actions']      = 'Actions';
$string['path_nodata_admin']     = 'No learning paths defined yet.';

// =================================================================
// ADMIN: TEAM MANAGERS
// =================================================================
$string['admin_managers_title']    = 'Team Manager Assignments';
$string['admin_managers_subtitle'] = 'Assign managers or supervisors to cohorts for team view access.';
$string['admin_managers_desc']     = 'Assign Moodle users as team managers for a cohort.';
$string['assign_manager']          = 'Assign Manager to Cohort';
$string['manager_user']            = 'Manager';
$string['user_id_placeholder']     = 'Enter Moodle User ID';
$string['manager_user_hint']       = 'Start typing a name or email address to find a user.';
$string['cohort']                  = 'Cohort';
$string['assign']                  = 'Assign';
$string['current_assignments']     = 'Current Assignments';
$string['no_assignments']          = 'No manager assignments yet.';
$string['remove']                  = 'Remove';
$string['invalid_user']            = 'Invalid user. Please check the User ID.';
$string['invalid_cohort']          = 'Invalid cohort selected.';
$string['manager_duplicate']       = 'This manager is already assigned to that cohort.';
$string['mgr_col_manager']         = 'Manager';
$string['mgr_col_cohort']          = 'Cohort';
$string['mgr_col_assigned']        = 'Assigned';
$string['mgr_add_title']           = 'Add Assignment';
$string['mgr_select_user']         = 'Select User';
$string['mgr_select_cohort']       = 'Select Cohort';
$string['mgr_add_btn']             = 'Add';
$string['mgr_remove']              = 'Remove';
$string['mgr_assignment_saved']    = 'Assignment added.';
$string['mgr_assignment_removed']  = 'Assignment removed.';
$string['mgr_assignment_duplicate'] = 'This user is already assigned to that cohort.';
$string['mgr_noassignments']       = 'No assignments yet.';
$string['mgr_nouser']              = 'Please select a user.';
$string['mgr_nocohort']            = 'Please select a cohort.';

// =================================================================
// ADMIN: RECOGNITION MODERATION
// =================================================================
$string['admin_recog_title']    = 'Recognition Feed';
$string['admin_recog_subtitle'] = 'View and moderate all spotlight recognitions.';
$string['recognition_hidden']   = 'Recognition hidden.';
$string['visible']              = 'Visible';
$string['timecreated']          = 'Date';
$string['recog_sender']         = 'Sender';
$string['recog_recipient']      = 'Recipient';
$string['recog_message']        = 'Message';
$string['hide']                 = 'Hide';
$string['show']                 = 'Show';
$string['recog_delete']         = 'Delete';
$string['recog_deleted']        = 'Recognition deleted.';
$string['recog_nodata']         = 'No recognitions yet.';
$string['recog_col_from']       = 'From';
$string['recog_col_to']         = 'To';
$string['recog_col_message']    = 'Message';
$string['recog_col_date']       = 'Date';
$string['recog_col_actions']    = 'Actions';

// =================================================================
// ADMIN: LEVELS
// =================================================================
$string['admin_levels_desc'] = 'Define the XP thresholds and display names for each learner level.';

// =================================================================
// SETTINGS
// =================================================================
$string['settings_general_heading']            = 'General Settings';
$string['settings_heading']                    = 'Leducon Platform Settings';
$string['settings_gamify_enabled']             = 'Enable Gamification';
$string['settings_gamify_enabled_desc']        = 'Uncheck to disable all gamification features without uninstalling.';
$string['settings_defaultperiod']              = 'Default date period (days)';
$string['settings_maxexportrows']              = 'Max export rows';
$string['settings_listperpage']                = 'Records per page';
$string['settings_listperpage_desc']           = 'Number of records shown per page in Teacher View and Manager View.';
$string['settings_enablecharts']               = 'Enable charts';
$string['settings_leaderboard_heading']        = 'Leaderboard Settings';
$string['settings_leaderboard_maxrows']        = 'Leaderboard max rows';
$string['settings_leaderboard_privacy']        = 'Leaderboard privacy';
$string['settings_privacy_fullnames']          = 'Show full names';
$string['settings_privacy_anonymous']          = 'Anonymous (teachers see names)';
$string['settings_privacy_teacheronly']        = 'Teachers only';
$string['settings_kpi_heading']                = 'KPI Thresholds';
$string['settings_kpi_completion_green']       = 'Completion rate: Green threshold (%)';
$string['settings_kpi_completion_amber']       = 'Completion rate: Amber threshold (%)';
$string['settings_kpi_pass_green']             = 'Pass rate: Green threshold (%)';
$string['settings_kpi_pass_amber']             = 'Pass rate: Amber threshold (%)';
$string['settings_kpi_engagement_green']       = 'Engagement: Green threshold (%)';
$string['settings_kpi_engagement_amber']       = 'Engagement: Amber threshold (%)';
$string['settings_atrisk_heading']             = 'At-Risk Thresholds';
$string['settings_atrisk_inactive_days']       = 'Days inactive before at-risk';
$string['settings_atrisk_grade']               = 'Grade threshold (%)';
$string['settings_atrisk_completion']          = 'Completion threshold (%)';
$string['settings_scorm_heading']              = 'SCORM Reporting';
$string['settings_scorm_pass_mode']            = 'SCORM pass mode';
$string['settings_scorm_pass_mode_desc']       = 'Controls how the SCORM report counts "criteria met" for each activity.';
$string['settings_scorm_auto']                 = 'Auto-detect per SCORM (recommended)';
$string['settings_scorm_lenient']              = 'Override: Lenient (passed OR completed = criteria met)';
$string['settings_scorm_pass_priority']        = 'Override: Pass-priority (passed signal only)';
$string['settings_scorm_strict']               = 'Override: Strict (passed AND completed both required)';
$string['settings_scorm_moodle']               = 'Override: Moodle completion record only';
$string['settings_includeinactive']            = 'Include inactive users in reports';
$string['settings_includeinactive_desc']       = 'When enabled, suspended and deleted users are excluded from all reports.';
$string['settings_email_heading']              = 'Scheduled Email Reports';
$string['settings_email_recipients']           = 'Extra email recipients (one per line)';
$string['settings_email_reports_list']         = 'Reports to include (comma-separated keys)';
$string['settings_xp_heading']                 = 'XP Event Values';
$string['settings_xp_course_completion']       = 'Course Completion';
$string['settings_xp_quiz_pass_first']         = 'Quiz Passed (First Attempt)';
$string['settings_xp_quiz_attempt']            = 'Quiz Attempt (any, once per quiz per day)';
$string['settings_xp_assign_ontime']           = 'Assignment Submitted On Time';
$string['settings_xp_assign_submitted']        = 'Assignment Submitted (any)';
$string['settings_xp_forum_post']              = 'Forum Post (up to daily cap)';
$string['settings_xp_badge_awarded']           = 'Moodle Badge Earned';
$string['settings_xp_path_completion']         = 'Learning Path Completed';
$string['settings_xp_spotlight_received']      = 'Spotlight Recognition Received';
$string['settings_xp_forum_post_maxday']       = 'Max Forum Posts Awarding XP per Day';
$string['settings_xp_forum_post_maxday_desc']  = 'Limits XP farming via forum posts.';
$string['settings_streak_heading']             = 'Streak Milestone XP Bonuses';
$string['settings_xp_streak_days']             = '{$a}-Day Streak Bonus XP';
$string['settings_spotlight_heading']          = 'Spotlight & Recognition';
$string['settings_spotlight_daily_limit']      = 'Spotlight Awards per Manager per Day';
$string['settings_spotlight_daily_limit_desc'] = 'Maximum number of spotlight recognitions a manager can give per day.';
$string['settings_nudge_heading']              = 'Nudge Emails';
$string['settings_nudge_enabled']              = 'Send Streak Nudge Emails';
$string['settings_nudge_enabled_desc']         = 'Send an email when a learner has been inactive for too long.';
$string['settings_nudge_inactive_days']        = 'Days of Inactivity Before Nudge';
$string['settings_nudge_inactive_days_desc']   = 'Send the nudge after this many days without a login.';

// =================================================================
// SCHEDULED TASKS
// =================================================================
$string['task_alert_checker']   = 'Check analytics alert rules';
$string['task_email_reports']   = 'Send scheduled email reports';
$string['task_process_streaks'] = 'Process daily learning streaks';
$string['task_send_nudges']     = 'Send streak nudge emails';
$string['task_campaign_check']  = 'Auto-close expired campaigns';
$string['task_weekly_digest']   = 'Send weekly manager digest';

// =================================================================
// EXPORT
// =================================================================
$string['export_csv']            = 'Export CSV';
$string['export_excel']          = 'Export Excel';
$string['export_pdf']            = 'Export PDF';
$string['exportmycsv']           = 'Export CSV';
$string['exportpdfview']         = 'Print / PDF';
$string['exportreport_title']    = 'Export Report';
$string['exportreport_generated'] = 'Generated: {$a}';

// =================================================================
// ERRORS
// =================================================================
$string['error_nologin']       = 'You must be logged in to view this page.';
$string['error_noaccess']      = 'You do not have permission to view this page.';
$string['error_invalidreport'] = 'Invalid report type specified.';
$string['error_invalidformat'] = 'Invalid export format specified.';
$string['error_db']            = 'A database error occurred. Please try again.';

// =================================================================
// MISC / SHARED
// =================================================================
$string['btn_apply']           = 'Apply';
$string['btn_reset']           = 'Reset';
$string['loading']             = 'Loading...';
$string['nodata']              = 'No data found.';
$string['noresults']           = 'No results found.';
$string['reportresults']       = 'Showing rows';
$string['period_label']        = 'Period: {$a->from} to {$a->to}';
$string['dashboard']           = 'Dashboard';
$string['saved']               = 'Saved successfully.';
$string['deleted']             = 'Deleted successfully.';
$string['confirm_delete']      = 'Are you sure you want to delete this item? This cannot be undone.';
$string['actions']             = 'Actions';
$string['enabled']             = 'Enabled';
$string['enable']              = 'Enable';
$string['disable']             = 'Disable';
$string['sort_order']          = 'Sort Order';
$string['cohort_restrict']     = 'Restrict to Cohort';
$string['leave_blank_all']     = 'leave blank for all users';
$string['all_users']           = 'All Users';
$string['xp_earned']           = '+{$a} XP';
$string['save']                = 'Save';
$string['edit']                = 'Edit';
$string['delete']              = 'Delete';
$string['add']                 = 'Add';
$string['active']              = 'Active';
$string['inactive']            = 'Inactive';
$string['never']               = 'Never';
$string['days_ago']            = '{$a} days ago';
$string['today']               = 'Today';
$string['yesterday']           = 'Yesterday';
$string['cohort_all']          = 'All Cohorts';
$string['select_cohort']       = 'Select cohort...';
$string['all_departments']     = 'All Departments';
$string['user_search_placeholder'] = 'Search by name or email...';
$string['user_search_hint']        = 'Type at least 2 characters to search for a user.';
$string['user_search_noresults']   = 'No users found.';

// =================================================================
// PRIVACY API
// =================================================================
$string['privacy:metadata']                            = 'The Leducon plugin stores gamification data (XP, achievements, redemptions) and reads existing Moodle data for analytics.';
$string['privacy:metadata:users']                      = 'Per-user gamification totals: XP, current level, and streak.';
$string['privacy:metadata:xp_log']                     = 'Detailed log of every XP transaction for each user.';
$string['privacy:metadata:user_achiev']                = 'Record of achievements earned by each user.';
$string['privacy:metadata:redemptions']                = 'Reward redemption requests made by users.';
$string['privacy:metadata:recognition']                = 'Peer recognition and spotlight messages given and received.';
$string['privacy:metadata:users:userid']               = 'The ID of the user.';
$string['privacy:metadata:users:total_xp']             = 'Total XP earned by the user.';
$string['privacy:metadata:users:levelid']              = 'The ID of the user\'s current level.';
$string['privacy:metadata:users:streak_days']          = 'Current login streak in days.';
$string['privacy:metadata:users:last_activity']        = 'Timestamp of last recorded learning activity.';
$string['privacy:metadata:xp_log:userid']              = 'The user who earned or spent XP.';
$string['privacy:metadata:xp_log:points']              = 'XP awarded (positive) or spent (negative).';
$string['privacy:metadata:xp_log:eventtype']           = 'The type of event that triggered the XP change.';
$string['privacy:metadata:xp_log:note']                = 'Human-readable description of the XP transaction.';
$string['privacy:metadata:xp_log:timecreated']         = 'When the XP was awarded.';
$string['privacy:metadata:user_achiev:userid']         = 'The user who earned the achievement.';
$string['privacy:metadata:user_achiev:achievementid']  = 'The ID of the achievement earned.';
$string['privacy:metadata:user_achiev:timecreated']    = 'When the achievement was earned.';
$string['privacy:metadata:redemptions:userid']         = 'The user who requested the redemption.';
$string['privacy:metadata:redemptions:rewardid']       = 'The ID of the reward being redeemed.';
$string['privacy:metadata:redemptions:cost_xp']        = 'XP cost at the time of redemption.';
$string['privacy:metadata:redemptions:status']         = 'Current status of the redemption request.';
$string['privacy:metadata:redemptions:timecreated']    = 'When the redemption was requested.';
$string['privacy:metadata:recognition:senderid']       = 'The user who gave the recognition.';
$string['privacy:metadata:recognition:recipientid']    = 'The user who received the recognition.';
$string['privacy:metadata:recognition:message']        = 'The recognition message text.';
$string['privacy:metadata:recognition:timecreated']    = 'When the recognition was given.';

// =================================================================
// CHART LABELS
// =================================================================
$string['chart_activity_trend']    = 'Activity Trend (Last 12 Weeks)';
$string['chart_logins']            = 'Logins';
$string['chart_completion_status'] = 'Completion Status';
$string['chart_completed']         = 'Completed';
$string['chart_inprogress']        = 'In Progress';
$string['chart_notstarted']        = 'Not Started';
$string['chart_grade_dist']        = 'Grade Distribution';
$string['chart_learners']          = 'Learners';
$string['chart_enrol_trend']       = 'Enrolment Trend (6 Months)';
$string['chart_enrolments']        = 'Enrolments';
$string['chart_xp_daily']          = 'XP Earned (Last 14 Days)';
$string['chart_xp_earned']         = 'XP Earned';
$string['chart_xp_breakdown']      = 'XP by Activity Type';
$string['chart_team_xp']           = 'Team XP Rankings';
$string['chart_team_performance']  = 'Team Performance Overview';
$string['chart_my_progress']       = 'My Course Progress & Grades';
$string['chart_student_grades']    = 'Student Grades';
$string['chart_risk_status']       = 'Risk Status Overview';
$string['chart_on_track']          = 'On Track';
$string['chart_at_risk']           = 'At Risk';
$string['chart_member_grades']     = 'Member Grades & Completion';
$string['chart_completion_rate']   = 'Completion Rate';
$string['chart_nodata']            = 'No data available yet.';

// =================================================================
// ORGANISATION STRUCTURE
// =================================================================
$string['org_title']                = 'Organisation Structure';
$string['org_subtitle']             = 'Manage your organisation hierarchy, assign users to units, and import structures via CSV.';
$string['org_tab_tree']             = 'Tree';
$string['org_tab_members']          = 'Members';
$string['org_tab_import']           = 'Import';
$string['org_add_unit']             = 'Add Organisation Unit';
$string['org_edit_unit']            = 'Edit Organisation Unit';
$string['org_unit_name']            = 'Unit Name';
$string['org_unit_shortname']       = 'Short Name / Code';
$string['org_parent']               = 'Parent Unit';
$string['org_root_level']           = '— Root Level (no parent) —';
$string['org_description']          = 'Description';
$string['org_linked_cohort']        = 'Linked Moodle Cohort';
$string['org_no_cohort']            = '— None —';
$string['org_cohort_hint']          = 'Optionally link a Moodle cohort for automatic member sync.';
$string['org_unit_created']         = 'Organisation unit created.';
$string['org_unit_updated']         = 'Organisation unit updated.';
$string['org_unit_deleted']         = 'Organisation unit deleted.';
$string['org_unit_haschildren']     = 'Cannot delete this unit — it still has child units. Delete or move children first.';
$string['org_tree_heading']         = 'Organisation Tree';
$string['org_notree']               = 'No organisation units defined yet. Use the form above to create your first unit.';
$string['org_child']                = 'Child';
$string['org_members_label']        = 'members';
$string['org_select_unit']          = 'Select Organisation Unit';
$string['org_select_unit_prompt']   = 'Select an organisation unit above to manage its members.';
$string['org_add_member']           = 'Add Member';
$string['org_user']                 = 'User';
$string['org_member_added']         = 'Member added to organisation unit.';
$string['org_member_removed']       = 'Member removed from organisation unit.';
$string['org_members_heading']      = 'Members';
$string['org_no_members']           = 'No members assigned to this unit yet.';
$string['org_sync_cohort']          = 'Sync to Cohort: {$a}';
$string['org_sync_result']          = 'Cohort sync complete. Added: {$a->added}, Removed: {$a->removed}.';
$string['org_import_heading']       = 'CSV Import';
$string['org_import_desc']          = 'Upload a CSV file to bulk-create organisation units and assign users.';
$string['org_import_format_title']  = 'CSV Format';
$string['org_import_format_desc']   = 'Lines with a name = unit definition (parent_shortname blank = root). Lines with user_email only = member assignment to that shortname.';
$string['org_import_file']          = 'CSV File';
$string['org_import_preview_btn']   = 'Preview (Dry Run)';
$string['org_import_btn']           = 'Import';
$string['org_import_preview']       = 'DRY RUN —';
$string['org_import_summary']       = '{$a->created} units to create, {$a->updated} to update, {$a->members} member assignments.';
$string['org_import_empty']         = 'CSV file is empty.';
$string['org_import_badheader']     = 'CSV header must be: {$a}';
$string['org_import_parentnotfound'] = 'Line {$a->line}: parent shortname "{$a->parent}" not found.';
$string['org_import_unitnotfound']  = 'Line {$a->line}: unit shortname "{$a->shortname}" not found.';
$string['org_import_usernotfound']  = 'Line {$a->line}: user email "{$a->email}" not found.';
$string['org_maintenance']          = 'Maintenance';
$string['org_rebuild_desc']         = 'Recalculate all materialised paths. Use after manual database changes.';
$string['org_rebuild_btn']          = 'Rebuild All Paths';
$string['org_rebuild_done']         = 'All organisation paths rebuilt.';
$string['org_filter_all']           = 'All Organisation';
$string['org_report_title']         = 'Organisation Analytics';
$string['org_report_subtitle']      = 'View learning KPIs aggregated by organisation unit with drill-down.';
$string['org_report_members']       = 'Total Members';
$string['org_report_completions']   = 'Completions';
$string['org_report_avggrade']      = 'Avg Grade';
$string['org_report_active']        = 'Active (7d)';
$string['org_report_atrisk']        = 'At-Risk';
$string['org_report_children']      = 'Unit Breakdown';
$string['org_report_nounit']        = 'Select an organisation unit to view analytics.';
$string['org_report_nodata']        = 'No data available for this organisation unit.';
$string['org_report_drilldown']     = 'Click a row to drill down.';
$string['nav_org_structure']        = 'Org Structure';
$string['nav_org_report']           = 'Org Analytics';
$string['settings_org_enabled']          = 'Enable Organisation Structure';
$string['settings_org_enabled_desc']     = 'When enabled, admins can build an org hierarchy and filter all reports by org unit.';
$string['settings_org_sync_cohorts']     = 'Auto-Sync Cohorts on Member Change';
$string['settings_org_sync_cohorts_desc'] = 'Automatically update linked Moodle cohorts when org unit members change.';
$string['settings_org_sync_remove']      = 'Remove Cohort Members Not in Org';
$string['settings_org_sync_remove_desc'] = 'When syncing, remove users from the Moodle cohort if they are no longer in the org unit.';
$string['settings_org_heading']          = 'Organisation Structure';
$string['filter_org']               = 'Organisation Unit';

// Enterprise org strings (bulk ops, AJAX, pagination).
$string['org_member_exists']        = 'User is already a member of this unit.';
$string['org_bulk_removed']         = '{$a} member(s) removed.';
$string['org_bulk_added']           = '{$a} member(s) added.';
$string['org_members_reassigned']   = '{$a} member(s) reassigned to target unit.';
$string['org_unit_update_failed']   = 'Organisation unit update failed — check for circular parent reference.';
$string['org_bulk_add_title']       = 'Bulk Add Members by Email';
$string['org_bulk_add_desc']        = 'Enter email addresses, one per line.';
$string['org_bulk_add_btn']         = 'Add All';
$string['org_bulk_remove_btn']      = 'Remove Selected';
$string['org_bulk_reassign_btn']    = 'Reassign All Members';
$string['org_bulk_reassign_title']  = 'Reassign Members';
$string['org_bulk_reassign_desc']   = 'Move all members from this unit to a target unit.';
$string['org_target_unit']          = 'Target Unit';
$string['org_search_members']       = 'Search members...';
$string['org_page_info']            = 'Showing {$a->from}-{$a->to} of {$a->total}';
$string['org_no_selection']         = 'No members selected.';
$string['org_confirm_bulk_remove']  = 'Are you sure you want to remove {$a} selected member(s)?';
$string['org_confirm_reassign']     = 'Are you sure you want to move all members to the target unit?';
$string['org_search_units']         = 'Search units...';
$string['org_loading']              = 'Loading...';
$string['org_expand']               = 'Expand';
$string['org_collapse']             = 'Collapse';
$string['settings_org_import_batch_size']      = 'CSV Import Batch Size';
$string['settings_org_import_batch_size_desc'] = 'Maximum number of rows to process per CSV import. Increase for large organisations, reduce if you experience timeouts.';
$string['settings_org_members_perpage']        = 'Members Per Page';
$string['settings_org_members_perpage_desc']   = 'Number of members to display per page in the org members tab.';

// =================================================================
// CAPABILITIES (additional)
// =================================================================
$string['leducon:managecustomreports'] = 'Create and manage custom reports';

// =================================================================
// CUSTOM REPORT BUILDER
// =================================================================
$string['customreport_title']           = 'Custom Reports';
$string['customreport_subtitle']        = 'Build your own reports by selecting data sources, columns, and conditions. Schedule email delivery of any report.';
$string['customreport_create']          = 'Create Custom Report';
$string['customreport_edit']            = 'Edit Custom Report';
$string['customreport_updated']         = 'Custom report updated.';
$string['customreport_created']         = 'Custom report created.';
$string['customreport_deleted']         = 'Custom report deleted.';
$string['customreport_confirmdelete']   = 'Are you sure you want to delete this custom report?';
$string['customreport_nodata']          = 'No custom reports yet. Create your first custom report or use a template below.';
$string['nav_custom_reports']           = 'Custom Reports';
$string['customreport_templates']       = 'Report Templates';
$string['customreport_templates_desc']  = 'Pre-built reports ready to use. Click "Use Template" to add a copy to your reports.';
$string['customreport_use_template']    = 'Use Template';
$string['customreport_template_added']  = 'Template report created and added to your reports.';

// Pre-built report templates.
$string['tpl_monthly_completion']       = 'Monthly Completion Report';
$string['tpl_monthly_completion_desc']  = 'All course completions with learner name, course, category, and completion date.';
$string['tpl_grade_summary']            = 'Grade Summary Report';
$string['tpl_grade_summary_desc']       = 'Course grades for all learners showing final grade, max grade, and percentage.';
$string['tpl_low_performers']           = 'Low Performers Report';
$string['tpl_low_performers_desc']      = 'Learners scoring below 50% across all courses — useful for intervention planning.';
$string['tpl_new_enrolments']           = 'New Enrolments Report';
$string['tpl_new_enrolments_desc']      = 'Recent enrolments with method and status — track onboarding progress.';
$string['tpl_quiz_performance']         = 'Quiz Performance Report';
$string['tpl_quiz_performance_desc']    = 'Completed quiz attempts with scores — monitor assessment outcomes.';
$string['tpl_failed_quiz']              = 'Failed Quiz Attempts';
$string['tpl_failed_quiz_desc']         = 'Quiz attempts scoring below 50% — identify learners needing support.';
$string['tpl_overdue_assignments']      = 'Pending Assignments Report';
$string['tpl_overdue_assignments_desc'] = 'Assignments with "new" status (not yet submitted) — follow up with learners.';
$string['tpl_login_activity']           = 'Login Activity Report';
$string['tpl_login_activity_desc']      = 'Recent login records with timestamps and IP addresses.';
$string['tpl_inactive_users']           = 'Inactive Users Report';
$string['tpl_inactive_users_desc']      = 'Users sorted by last access (oldest first) — find disengaged learners.';
$string['tpl_department_roster']        = 'Department Roster';
$string['tpl_department_roster_desc']   = 'Active users grouped by department and institution — a full staff directory.';

// Custom report tabs.
$string['cr_tab_myreports'] = 'My Reports';
$string['cr_tab_builder']   = 'Report Builder';
$string['cr_tab_schedules'] = 'Scheduled Deliveries';

// Custom report columns.
$string['cr_col_name']    = 'Report Name';
$string['cr_col_source']  = 'Data Source';
$string['cr_col_shared']  = 'Shared';
$string['cr_col_created'] = 'Created';
$string['cr_col_actions'] = 'Actions';

// Custom report actions.
$string['cr_action_view']   = 'View';
$string['cr_action_edit']   = 'Edit';
$string['cr_action_delete'] = 'Delete';
$string['cr_back_to_list']  = 'Back to Reports';

// Custom report builder form.
$string['cr_field_name']        = 'Report Name';
$string['cr_field_description'] = 'Description';
$string['cr_field_datasource']  = 'Data Source';
$string['cr_field_columns']     = 'Columns to Display';
$string['cr_field_conditions']  = 'Filter Conditions';
$string['cr_field_orderby']     = 'Order By';
$string['cr_field_shared']      = 'Share with all managers';
$string['cr_name_placeholder']  = 'e.g. Compliance Completion Status';
$string['cr_save_report']       = 'Save Report';
$string['cr_cancel']            = 'Cancel';
$string['cr_add_condition']     = 'Add Condition';
$string['cr_cond_selectfield']  = 'Select field';
$string['cr_cond_value']        = 'Value...';

// Custom report: scope filters.
$string['cr_scope_heading']           = 'User Scope Filters';
$string['cr_scope_desc']              = 'Restrict this report to specific groups of users. Leave blank to include all users.';
$string['cr_scope_all']               = 'All';
$string['cr_scope_cohort']            = 'Cohort / Department';
$string['cr_scope_department']        = 'Department';
$string['cr_scope_institution']       = 'Institution / Organisation';
$string['cr_scope_orgunit']           = 'Organisation Unit';
$string['cr_scope_country']           = 'Country';
$string['cr_scope_city']              = 'City';
$string['cr_scope_city_placeholder']  = 'Enter city name...';
$string['cr_scope_profilefields']     = 'Custom Profile Fields';
$string['cr_scope_selectfield']       = 'Select field';
$string['cr_scope_add_profile']       = 'Add Profile Field Filter';

// Data sources.
$string['cr_ds_completions']      = 'Course Completions';
$string['cr_ds_completions_desc'] = 'Users who have completed courses, with dates and grades.';
$string['cr_ds_grades']           = 'Course Grades';
$string['cr_ds_grades_desc']      = 'Final course grades for all graded users.';
$string['cr_ds_enrollments']      = 'Enrolments';
$string['cr_ds_enrollments_desc'] = 'All user enrolments with course and method details.';
$string['cr_ds_quiz']             = 'Quiz Attempts';
$string['cr_ds_quiz_desc']        = 'Individual quiz attempt records with scores.';
$string['cr_ds_logins']           = 'Login Activity';
$string['cr_ds_logins_desc']      = 'User login events with timestamps and IP addresses.';
$string['cr_ds_assignments']      = 'Assignment Submissions';
$string['cr_ds_assignments_desc'] = 'Assignment submission status, grades, and dates.';
$string['cr_ds_users']            = 'User Directory';
$string['cr_ds_users_desc']       = 'All users with profile fields, login status, and department.';

// =================================================================
// SCHEDULED REPORT DELIVERY
// =================================================================
$string['schedule_new_heading']     = 'Schedule a Report Delivery';
$string['schedule_new_desc']        = 'Choose a custom report or built-in report to be emailed automatically as a CSV attachment.';
$string['schedule_report']          = 'Custom Report';
$string['schedule_or_builtin']      = 'Or Built-in Report';
$string['schedule_recipients']      = 'Recipients (comma-separated emails)';
$string['schedule_save']            = 'Create Schedule';
$string['schedule_saved']           = 'Report schedule created.';
$string['schedule_deleted']         = 'Report schedule deleted.';
$string['schedule_active_heading']  = 'Active Schedules';
$string['schedule_nodata']          = 'No scheduled deliveries yet.';
$string['schedule_col_report']      = 'Report';
$string['schedule_col_frequency']   = 'Frequency';
$string['schedule_col_recipients']  = 'Recipients';
$string['schedule_col_lastsent']    = 'Last Sent';
$string['schedule_col_status']      = 'Status';
$string['scheduled_report_body']    = 'Please find attached the scheduled report "{$a->name}" generated on {$a->date}.';

// =================================================================
// AUTOMATION: SCHEDULED TASK NAMES
// =================================================================
$string['task_compliance_reminder'] = 'Compliance Deadline Reminders';
$string['task_report_scheduler']    = 'Scheduled Report Delivery';
$string['task_data_retention']      = 'Data Retention Cleanup';

// =================================================================
// AUTOMATION: COMPLIANCE REMINDERS
// =================================================================
$string['compliance_reminder_subject'] = 'Deadline Approaching: {$a->course} ({$a->days} days left)';
$string['compliance_reminder_body']    = "Hello {$a->fullname},\n\nThis is a reminder that the course \"{$a->coursename}\" has a deadline approaching.\n\nDeadline: {$a->deadline}\nDays remaining: {$a->days}\n\nPlease log in and complete the course before the deadline:\n{$a->courseurl}\n\nThank you.";

// =================================================================
// AUTOMATION: SETTINGS
// =================================================================
$string['settings_compliance_heading']      = 'Compliance Deadline Reminders';
$string['settings_compliance_enabled']      = 'Enable Compliance Reminders';
$string['settings_compliance_enabled_desc'] = 'Automatically email learners when a course deadline is approaching.';
$string['settings_compliance_days']         = 'Days Before Deadline';
$string['settings_compliance_days_desc']    = 'Send reminder this many days before the course end date.';
$string['settings_retention_heading']       = 'Data Retention';
$string['settings_retention_enabled']       = 'Enable Data Retention Cleanup';
$string['settings_retention_enabled_desc']  = 'Automatically purge old XP log and recognition entries beyond the retention period. XP totals are preserved.';
$string['settings_retention_months']        = 'Retention Period';
$string['settings_retention_months_desc']   = 'Keep detailed XP log entries for this many months.';

// =================================================================
// DASHBOARD CHARTS
// =================================================================
$string['settings_dashboard_heading']           = 'Dashboard Charts';
$string['settings_dashboard_trend_weeks']       = 'Activity trend weeks';
$string['settings_dashboard_trend_weeks_desc']  = 'Number of weeks to show in the dashboard activity trend chart.';
$string['settings_dashboard_enrol_months']      = 'Enrolment trend months';
$string['settings_dashboard_enrol_months_desc'] = 'Number of months to show in the dashboard enrolment trend chart.';
$string['settings_grade_bracket_1']             = 'Grade distribution brackets';
$string['settings_grade_bracket_desc']          = 'Comma-separated boundaries for grade distribution chart (e.g. 0,40,50,70,90,100).';

// =================================================================
// MODULE FEATURE TOGGLES
// =================================================================
$string['settings_modules_heading']          = 'Module Reports';
$string['settings_modules_heading_desc']     = 'Enable or disable reports for specific Moodle activity modules. Disabled reports will be hidden from the sidebar.';
$string['settings_enable_quiz_reports']      = 'Enable quiz reports';
$string['settings_enable_assignment_reports'] = 'Enable assignment reports';
$string['settings_enable_forum_reports']     = 'Enable forum reports';
$string['settings_enable_scorm_reports']     = 'Enable SCORM reports';
$string['settings_enable_ilt_reports']       = 'Enable ILT/VILT reports';

// =================================================================
// INSIGHT THRESHOLDS
// =================================================================
$string['settings_insight_heading']               = 'Insight Thresholds';
$string['settings_insight_heading_desc']          = 'Configure the thresholds that trigger insight alerts on report pages. Values are percentages unless otherwise noted.';
$string['settings_insight_completion_high']        = 'Completion rate: high (%)';
$string['settings_insight_completion_low']         = 'Completion rate: low (%)';
$string['settings_insight_completion_notstarted']  = 'Not-started learners: warning (%)';
$string['settings_insight_completion_best']        = 'Top course: minimum rate (%)';
$string['settings_insight_compliance_high']        = 'Compliance rate: good (%)';
$string['settings_insight_compliance_low']         = 'Compliance rate: danger (%)';
$string['settings_insight_grade_high']             = 'Grade average: high (%)';
$string['settings_insight_grade_low']              = 'Grade average: low (%)';
$string['settings_insight_quiz_highpass']          = 'Quiz pass rate: excellent (%)';
$string['settings_insight_quiz_lowpass']           = 'Quiz pass rate: concern (%)';
$string['settings_insight_quiz_longtime']          = 'Quiz long time: threshold (mins)';
$string['settings_insight_forum_active']           = 'Forum active: min posts';
$string['settings_insight_assign_ungraded']        = 'Assignment ungraded: warning count';
$string['settings_insight_assign_ontime']          = 'Assignment on-time: excellence (%)';
$string['settings_insight_assign_late_pct']        = 'Assignment late: warning (%)';
$string['settings_insight_login_inactive_days']    = 'Login inactive: days threshold';
$string['settings_insight_login_poweruser']        = 'Login power user: min logins';
$string['settings_insight_login_lowfreq']          = 'Login low frequency: avg threshold';
$string['settings_insight_login_multicourse']      = 'Multi-course learner: min courses';
$string['settings_insight_login_noact_pct']        = 'No activities: warning (%)';
$string['settings_insight_ts_highavg']             = 'Time spent: high avg (mins)';
$string['settings_insight_ts_lowavg']              = 'Time spent: low avg (mins)';
$string['settings_insight_ts_heavy']               = 'Time spent: heavy user (mins)';
$string['settings_insight_ts_light_pct']           = 'Time spent: light users warning (%)';
$string['settings_insight_badge_multi']            = 'Badge multi-earner: avg threshold';
$string['settings_insight_badge_volume']           = 'Badge volume: high count';
$string['settings_insight_cert_volume']            = 'Certificate volume: high count';
$string['settings_insight_cert_achievers']         = 'Certificate achievers: ratio threshold';
$string['settings_insight_cat_spread']             = 'Category spread: warning (% points)';
$string['settings_insight_cat_best']               = 'Category best: min rate (%)';
$string['settings_insight_cat_worst']              = 'Category worst: max rate (%)';
$string['settings_insight_inst_highrate']          = 'Instructor completion: high (%)';
$string['settings_insight_inst_lowrate']           = 'Instructor completion: low (%)';
$string['settings_insight_inst_highload']          = 'Instructor high load: student count';
$string['settings_insight_mv_highrisk']            = 'Manager view: high risk (%)';
$string['settings_insight_mv_topperformers']       = 'Manager view: top performers count';
$string['settings_insight_atrisk_inactive_days']   = 'At-risk inactive: days threshold';
$string['settings_insight_atrisk_min_count']       = 'At-risk insights: minimum count';
$string['settings_insight_ilt_highattend']         = 'ILT high attendance (%)';
$string['settings_insight_ilt_lowattend']          = 'ILT low attendance (%)';
$string['settings_insight_ilt_noshow_min']         = 'ILT no-show: minimum count';
$string['settings_insight_ilt_highrating']         = 'ILT high rating (out of 5)';
$string['settings_insight_ilt_lowrating']          = 'ILT low rating (out of 5)';
$string['settings_insight_ilt_facilitator_count']  = 'ILT top facilitator: minimum session count';
$string['settings_insight_ilt_dept_count']         = 'ILT department engagement: minimum departments';

// =================================================================
// MISCELLANEOUS SETTINGS
// =================================================================
$string['settings_misc_heading']                     = 'Miscellaneous';
$string['settings_mins_per_log_event']               = 'Minutes per log event';
$string['settings_mins_per_log_event_desc']          = 'Estimated minutes of learning time per log event for time-spent calculations.';
$string['settings_quiz_firstpass_threshold']         = 'Quiz first-pass bonus threshold (%)';
$string['settings_quiz_firstpass_threshold_desc']    = 'Minimum grade percentage on first quiz attempt to earn the first-pass XP bonus.';
$string['settings_streak_milestones']                = 'Streak milestone days';
$string['settings_streak_milestones_desc']           = 'Comma-separated day counts for streak milestones (e.g. 7,30,90,365).';
$string['settings_scheduler_daily_buffer']           = 'Scheduler daily buffer (hours)';
$string['settings_scheduler_daily_buffer_desc']      = 'Minimum hours between daily scheduled report emails.';
$string['settings_scheduler_weekly_buffer']          = 'Scheduler weekly buffer (days)';
$string['settings_scheduler_weekly_buffer_desc']     = 'Minimum days between weekly scheduled report emails.';
$string['settings_scheduler_monthly_buffer']         = 'Scheduler monthly buffer (days)';
$string['settings_scheduler_monthly_buffer_desc']    = 'Minimum days between monthly scheduled report emails.';
$string['settings_precompute_period_days']           = 'Precompute period (days)';
$string['settings_precompute_period_days_desc']      = 'Default date range in days for precomputed report aggregates.';

// =================================================================
// THEME / COLORS
// =================================================================
$string['settings_theme_heading']      = 'Theme Colors';
$string['settings_theme_heading_desc'] = 'Customise the accent colors used across the dashboard, KPI cards, charts, and report tiles.';
$string['settings_color_primary']      = 'Primary color';
$string['settings_color_success']      = 'Success color';
$string['settings_color_warning']      = 'Warning color';
$string['settings_color_danger']       = 'Danger color';
$string['settings_color_info']         = 'Info color';
$string['settings_color_purple']       = 'Accent color';

// =================================================================
// CONSOLIDATED REPORT CLASSES
// =================================================================
$string['report_teacherview']       = 'Teacher View';
$string['report_managerview']       = 'Manager View';
$string['report_skills']            = 'Skills Coverage';
$string['report_roi_analyst']       = 'ROI Analyst Detail';
$string['report_group_skills_roi']  = 'Skills & ROI';

// =================================================================
// DYNAMIC INSIGHTS
// =================================================================

// Course Completion insights.
$string['insight_cc_highrate']           = 'Strong completion rate: {$a}%';
$string['insight_cc_highrate_detail']    = 'Your courses are performing well above average.';
$string['insight_cc_lowrate']            = 'Low completion rate: {$a}%';
$string['insight_cc_lowrate_detail']     = 'Consider reviewing course design or adding engagement nudges.';
$string['insight_cc_zerocourses']        = '{$a} courses have zero completions';
$string['insight_cc_zerocourses_detail'] = 'These courses may need attention — learners are enrolled but nobody has completed.';
$string['insight_cc_notstarted']         = '{$a}% of learners haven\'t started';
$string['insight_cc_notstarted_detail']  = '{$a} enrolled learners have not begun any activities yet.';
$string['insight_cc_bestcourse']         = 'Top performer: {$a->name} ({$a->rate}%)';
$string['insight_cc_bestcourse_detail']  = 'Analyse what makes this course successful and replicate it.';

// At-Risk insights.
$string['insight_atrisk_none']            = 'No at-risk students detected';
$string['insight_atrisk_none_detail']     = 'All learners are progressing within normal parameters.';
$string['insight_atrisk_high_count']      = '{$a} students are high-risk';
$string['insight_atrisk_high_pct']        = '{$a}% of flagged students have multiple risk factors — prioritise intervention.';
$string['insight_atrisk_inactive']        = '{$a} learners inactive 30+ days';
$string['insight_atrisk_inactive_detail'] = 'Extended inactivity suggests disengagement — consider a nudge email or manager outreach.';
$string['insight_atrisk_course']          = 'Most at-risk in: {$a}';
$string['insight_atrisk_course_detail']   = '{$a} at-risk students are concentrated in this course — investigate course-level issues.';

// Teacher View insights.
$string['insight_tv_highcomp']          = 'Excellent: {$a}% completion rate';
$string['insight_tv_highcomp_detail']   = 'This course has outstanding learner engagement.';
$string['insight_tv_lowcomp']           = 'Only {$a}% have completed';
$string['insight_tv_lowcomp_detail']    = 'Most students are stuck — review if content is accessible and deadlines are clear.';
$string['insight_tv_neverlogin']        = '{$a} students never accessed the course';
$string['insight_tv_neverlogin_detail'] = 'These learners may not know they are enrolled — send a welcome notification.';
$string['insight_tv_nograde']           = '{$a} students have no grade yet';
$string['insight_tv_nograde_detail']    = 'Over half the class hasn\'t been assessed — check if assignments are open.';

// Manager View insights.
$string['insight_mv_highrisk']              = '{$a}% of team at risk';
$string['insight_mv_highrisk_detail']       = '{$a} team members need immediate attention.';
$string['insight_mv_norisk']                = 'All team members on track';
$string['insight_mv_norisk_detail']         = 'No at-risk indicators detected — keep up the momentum.';
$string['insight_mv_topperformers']         = '{$a} high performers (80%+ avg)';
$string['insight_mv_topperformers_detail']  = 'Consider recognising these team members with a spotlight.';
$string['insight_mv_noenrol']               = '{$a} members not enrolled in any course';
$string['insight_mv_noenrol_detail']        = 'Assign learning paths or courses to engage these team members.';

// Skills insights.
$string['insight_skills_gap']            = '{$a} skills have zero learners';
$string['insight_skills_gap_detail']     = 'Map these skills to courses to close the gap.';
$string['insight_skills_strong']         = '{$a} skills have 75%+ coverage';
$string['insight_skills_strong_detail']  = 'Your organisation has strong capability in these areas.';
$string['insight_skills_top']            = 'Most developed skill: {$a}';
$string['insight_skills_top_detail']     = '{$a} learners have acquired this skill.';

// ROI insights.
$string['insight_roi_allpositive']         = 'All courses show positive ROI';
$string['insight_roi_allpositive_detail']  = '{$a} courses are delivering more value than their cost.';
$string['insight_roi_negative']            = '{$a} courses have negative ROI';
$string['insight_roi_negative_detail']     = 'Worst performer: {$a} — consider improving completion rates or reducing cost.';
$string['insight_roi_best']                = 'Star course: {$a->name} (+{$a->roi}% ROI)';
$string['insight_roi_best_detail']         = 'Replicate this course\'s approach across similar programmes.';

// Enrollment insights.
$string['insight_en_growth']              = '{$a}% enrolment growth this period';
$string['insight_en_growth_detail']       = '{$a} new enrolments added — strong intake momentum.';
$string['insight_en_nonew']               = 'No new enrolments this period';
$string['insight_en_nonew_detail']        = 'Zero new learners enrolled — check if registration is open and promoted.';
$string['insight_en_suspended']           = '{$a}% of enrolments are suspended';
$string['insight_en_suspended_detail']    = '{$a} suspended enrolments may indicate access issues or policy changes.';
$string['insight_en_lowenrol']            = '{$a} courses have fewer than 5 learners';
$string['insight_en_lowenrol_detail']     = 'Low-enrollment courses may need promotion or consolidation.';

// Grade Analytics insights.
$string['insight_ga_highavg']             = 'Strong overall average: {$a}%';
$string['insight_ga_highavg_detail']      = 'Learners are performing well across courses.';
$string['insight_ga_lowavg']              = 'Concerning overall average: {$a}%';
$string['insight_ga_lowavg_detail']       = 'Average grades are below pass thresholds — review assessment difficulty or support resources.';
$string['insight_ga_belowpass']           = '{$a} courses below 50% average';
$string['insight_ga_belowpass_detail']    = 'These courses may have assessment issues or need additional learner support.';
$string['insight_ga_excellent']           = '{$a} courses have 80%+ average grades';
$string['insight_ga_excellent_detail']    = 'Well-designed assessments with strong learner performance.';

// Login Activity insights.
$string['insight_la_inactive30']          = '{$a} users inactive 30+ days';
$string['insight_la_inactive30_detail']   = '{$a}% of active users haven\'t logged in for over a month.';
$string['insight_la_powerusers']          = '{$a} power users (50+ logins)';
$string['insight_la_powerusers_detail']   = 'Highly engaged learners who log in frequently.';
$string['insight_la_lowfreq']             = 'Low login frequency: {$a} avg per user';
$string['insight_la_lowfreq_detail']      = 'Users are logging in infrequently — consider engagement notifications.';

// Quiz Analytics insights.
$string['insight_qa_lowpass']             = '{$a} quizzes have below 50% pass rate';
$string['insight_qa_lowpass_detail']      = 'Quizzes with low pass rates may be too difficult or poorly aligned with content.';
$string['insight_qa_highpass']            = '{$a} quizzes have 90%+ pass rate';
$string['insight_qa_highpass_detail']     = 'Well-calibrated assessments with strong learner outcomes.';
$string['insight_qa_longtime']            = '{$a->name} averages {$a->mins} minutes';
$string['insight_qa_longtime_detail']     = 'Long completion times may indicate overly complex questions or insufficient preparation.';

// Assignment insights.
$string['insight_ar_late']                = '{$a}% of submissions are late';
$string['insight_ar_late_detail']         = '{$a} assignments submitted after deadline — consider clearer due date communication.';
$string['insight_ar_ungraded']            = '{$a} submissions awaiting grading';
$string['insight_ar_ungraded_detail']     = 'A growing grading backlog can delay learner feedback and progression.';
$string['insight_ar_ontime']              = '{$a}% on-time submission rate';
$string['insight_ar_ontime_detail']       = 'Excellent punctuality — learners are well-informed about deadlines.';

// SCORM Analytics insights.
$string['insight_sa_gap']                 = '{$a} learners passed SCORM but not marked complete in Moodle';
$string['insight_sa_gap_detail']          = 'A completion tracking gap exists — review SCORM activity completion settings.';
$string['insight_sa_highpass']            = 'Strong pass rate: {$a}%';
$string['insight_sa_highpass_detail']     = 'SCORM modules are well-configured with good learner outcomes.';
$string['insight_sa_lowpass']             = 'Low pass rate: {$a}%';
$string['insight_sa_lowpass_detail']      = 'Learners are struggling with SCORM content — review completion criteria or content difficulty.';
$string['insight_sa_noattempt']           = '{$a} SCORM modules have zero attempts';
$string['insight_sa_noattempt_detail']    = 'Enrolled learners haven\'t launched these modules — check access paths.';

// Forum insights.
$string['insight_fr_dead']                = '{$a} forums have zero posts this period';
$string['insight_fr_dead_detail']         = 'Inactive forums may need seeding with discussion prompts or instructor participation.';
$string['insight_fr_active']              = '{$a} forums are highly active (20+ posts)';
$string['insight_fr_active_detail']       = 'Strong community engagement in these discussion spaces.';
$string['insight_fr_engaged']             = '{$a} posts per participant on average';
$string['insight_fr_engaged_detail']      = 'Good discussion depth — participants are contributing multiple times.';

// Time Spent insights.
$string['insight_ts_highavg']             = 'High engagement: {$a} min average per learner';
$string['insight_ts_highavg_detail']      = 'Learners are investing significant time in learning activities.';
$string['insight_ts_lowavg']              = 'Low engagement: only {$a} min average per learner';
$string['insight_ts_lowavg_detail']       = 'Minimal time spent suggests content may not be engaging or accessible.';
$string['insight_ts_heavy']              = '{$a} power learners (5+ hours)';
$string['insight_ts_heavy_detail']        = 'These highly engaged learners are dedicating significant learning time.';
$string['insight_ts_light']               = '{$a}% of learners spent less than 30 minutes';
$string['insight_ts_light_detail']        = '{$a} learners may need encouragement or clearer learning paths.';

// Badge insights.
$string['insight_badge_multi']            = 'Avg {$a} badges per recipient';
$string['insight_badge_multi_detail']     = 'Learners are earning multiple badges — gamification is driving engagement.';
$string['insight_badge_popular']          = 'Most popular: {$a->name} ({$a->count} issued)';
$string['insight_badge_popular_detail']   = 'This badge has the highest issuance — it may represent a key milestone.';
$string['insight_badge_rare']             = '{$a} badges issued only once';
$string['insight_badge_rare_detail']      = 'Rarely-issued badges may be too difficult to achieve or not well promoted.';

// Certificate insights.
$string['insight_cert_volume']            = '{$a} certificates issued this period';
$string['insight_cert_volume_detail']     = 'Awarded to {$a} unique learners — strong output.';
$string['insight_cert_achievers']         = 'Avg {$a} certificates per learner';
$string['insight_cert_achievers_detail']  = 'Learners are completing multiple certified programmes.';
$string['insight_cert_multiplugin']       = '{$a} certificate plugins active';
$string['insight_cert_multiplugin_detail'] = 'Data is consolidated from multiple certificate systems.';

// Compliance insights.
$string['insight_comp_highrate']          = '{$a}% compliance rate — excellent';
$string['insight_comp_highrate_detail']   = 'Nearly all mandatory training is completed on time.';
$string['insight_comp_lowrate']           = 'Only {$a}% compliance rate';
$string['insight_comp_lowrate_detail']    = '{$a} outstanding items need urgent attention to meet requirements.';
$string['insight_comp_overdue']           = '{$a} learners overdue by 90+ days';
$string['insight_comp_overdue_detail']    = 'Long-overdue compliance items present a risk — escalate to management.';

// Instructor insights.
$string['insight_inst_highrate']          = 'Average completion rate: {$a}% across instructors';
$string['insight_inst_highrate_detail']   = 'Instructors are driving strong learner outcomes.';
$string['insight_inst_lowrate']           = 'Low average completion: {$a}%';
$string['insight_inst_lowrate_detail']    = 'Instructors may need additional training support or course redesign guidance.';
$string['insight_inst_highload']          = '{$a} instructors have 200+ students';
$string['insight_inst_highload_detail']   = 'High student loads may affect feedback quality and response times.';
$string['insight_inst_inactive']          = '{$a} instructors have never logged in';
$string['insight_inst_inactive_detail']   = 'Inactive instructors cannot support their students — verify assignments.';

// Category insights.
$string['insight_cat_spread']             = '{$a}% gap between best and worst categories';
$string['insight_cat_spread_detail']      = 'Large performance variance across departments — investigate root causes.';
$string['insight_cat_best']               = 'Top category: {$a->name} ({$a->rate}%)';
$string['insight_cat_best_detail']        = 'This department leads in completion — share their practices.';
$string['insight_cat_worst']              = 'Lowest: {$a->name} ({$a->rate}%)';
$string['insight_cat_worst_detail']       = 'This category needs intervention — review course design and learner support.';

// User Activity insights.
$string['insight_ua_multicourse']         = '{$a} users active in 3+ courses';
$string['insight_ua_multicourse_detail']  = 'Multi-course learners show strong platform engagement.';
$string['insight_ua_noactivities']        = '{$a} users logged in but completed zero activities';
$string['insight_ua_noactivities_detail'] = 'These learners are accessing the platform but not progressing — investigate barriers.';
$string['insight_ua_highactivity']        = 'Avg {$a} activities completed per user';
$string['insight_ua_highactivity_detail'] = 'Learners are actively engaging with course materials.';

// ILT insights.
$string['insight_ilt_highattend']         = '{$a}% attendance rate — excellent';
$string['insight_ilt_highattend_detail']  = 'Strong attendance across instructor-led sessions.';
$string['insight_ilt_lowattend']          = 'Low attendance: {$a}%';
$string['insight_ilt_lowattend_detail']   = 'Poor attendance may indicate scheduling conflicts or low awareness.';
$string['insight_ilt_noshow']             = '{$a} no-shows recorded';
$string['insight_ilt_noshow_detail']      = 'Frequent no-shows waste facilitator time — consider reminder notifications.';
$string['insight_ilt_viltmix']            = '{$a}% of sessions are virtual (VILT)';
$string['insight_ilt_viltmix_detail']     = 'A mix of in-person and virtual delivery supports flexible learning.';
$string['insight_ilt_costwaste']          = '{$a->noshow} no-shows wasted ${$a->cost} in training budget';
$string['insight_ilt_costwaste_detail']   = 'No-show cost is estimated from average cost-per-head across sessions with cost data.';
$string['insight_ilt_lowsessions']        = '{$a} sessions had attendance below 60%';
$string['insight_ilt_lowsessions_detail'] = 'Low-attendance sessions waste facilitator time and venue costs — review scheduling or awareness.';
$string['insight_ilt_topfacilitator']     = '{$a->name} led {$a->count} sessions (most active)';
$string['insight_ilt_topfacilitator_detail'] = 'Recognise high-volume facilitators and ensure workload balance.';
$string['insight_ilt_highrating']         = 'Average session rating: {$a}/5';
$string['insight_ilt_highrating_detail']  = 'Learners rate sessions highly — facilitators and content are well-received.';
$string['insight_ilt_lowrating']          = 'Average session rating only {$a}/5';
$string['insight_ilt_lowrating_detail']   = 'Low ratings suggest content or delivery needs review — gather detailed feedback.';
$string['insight_ilt_topdept']            = 'Most engaged department: {$a}';
$string['insight_ilt_topdept_detail']     = '{$a} attendees from this department — they invest heavily in instructor-led training.';

// =================================================================
// SCHEDULED TASKS
// =================================================================
$string['task_precompute_reports']   = 'Pre-compute report aggregates';
$string['task_insight_notifier']     = 'Check insights and send critical alerts';

// =================================================================
// NOTIFICATION / MESSAGING
// =================================================================
$string['messageprovider:insight_alert']        = 'Critical analytics insight alerts';
$string['messageprovider:atrisk_notification']  = 'At-risk student notifications for teachers';
$string['messageprovider:compliance_reminder']  = 'Compliance deadline reminders';
$string['insight_notification_subject']         = 'Leducon: Critical analytics insights detected';
$string['insight_notification_intro']           = 'The following critical insights were detected in your analytics reports:';
$string['insight_notification_action']          = 'Review these reports in your analytics dashboard to take corrective action.';

// =================================================================
// WEB SERVICES
// =================================================================
$string['privacy:metadata:users']               = 'Gamification user profile data';
$string['privacy:metadata:users:userid']        = 'The user ID';
$string['privacy:metadata:users:total_xp']      = 'Total experience points earned';
$string['privacy:metadata:users:levelid']       = 'Current level achieved';
$string['privacy:metadata:users:streak_days']   = 'Consecutive active days';
$string['privacy:metadata:users:last_activity'] = 'Timestamp of last activity';
