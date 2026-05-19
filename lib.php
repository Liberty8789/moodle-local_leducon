<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for local_leducon.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// =====================================================================
// GLOBAL NAVIGATION HOOK
// =====================================================================

function local_leducon_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $ctx = context_system::instance();

    // Main dashboard link for analytics users.
    if (has_capability('local/leducon:viewanalytics', $ctx)) {
        $node = navigation_node::create(
            get_string('pluginname', 'local_leducon'),
            new moodle_url('/local/leducon/index.php'),
            navigation_node::TYPE_CUSTOM, null,
            'local_leducon_dashboard',
            new pix_icon('i/report', '')
        );
        $home = $nav->find('home', navigation_node::TYPE_SYSTEM);
        if ($home) {
            $home->add_node($node);
        } else {
            $nav->add_node($node);
        }
    }

    // My Report Card link for learners.
    if (has_capability('local/leducon:viewown', $ctx)) {
        $node = navigation_node::create(
            get_string('nav_myreport', 'local_leducon'),
            new moodle_url('/local/leducon/analytics/myreport.php'),
            navigation_node::TYPE_CUSTOM, null,
            'local_leducon_myreport',
            new pix_icon('i/report', '')
        );
        $home = $nav->find('home', navigation_node::TYPE_SYSTEM);
        if ($home) {
            $home->add_node($node);
        } else {
            $nav->add_node($node);
        }
    }

    // Gamification dashboard link.
    if (has_capability('local/leducon:viewgamify', $ctx) &&
        get_config('local_leducon', 'gamify_enabled')) {
        $node = navigation_node::create(
            get_string('nav_gamify_dashboard', 'local_leducon'),
            new moodle_url('/local/leducon/gamify/index.php'),
            navigation_node::TYPE_CUSTOM, null,
            'leducon_gamify_nav',
            new pix_icon('i/star-rating', '')
        );
        $home = $nav->find('home', navigation_node::TYPE_SYSTEM);
        if ($home) {
            $home->add_node($node);
        } else {
            $nav->add_node($node);
        }
    }
}

// =====================================================================
// UNIFIED IN-PAGE NAVIGATION
// =====================================================================

/**
 * Render the unified in-page navigation bar.
 *
 * Layout: [Dashboard] [Analytics ▾] [Gamification ▾] [My Team ▾] [Setup ▾]
 *
 * @param string $section    Active section: 'dashboard','analytics','gamify','manager','admin'.
 * @param string $activepage Active page key within that section.
 * @param int    $userid     Optional userid for user-specific links.
 */
function local_leducon_render_nav(string $section = '', string $activepage = '', int $userid = 0): void {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $ctx = context_system::instance();
    $items = [];

    // ── Dashboard (plain link) ─────────────────────────────────────
    if (has_capability('local/leducon:viewanalytics', $ctx) ||
        has_capability('local/leducon:viewgamify', $ctx)) {
        $items[] = [
            'type'    => 'link',
            'url'     => new moodle_url('/local/leducon/index.php'),
            'label'   => get_string('nav_dashboard', 'local_leducon'),
            'key'     => 'dashboard',
            'section' => 'dashboard',
        ];
    }

    // ── Analytics dropdown ─────────────────────────────────────────
    $analyticschildren = [];
    if (has_capability('local/leducon:viewanalytics', $ctx)) {
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/index.php'),
            'label' => get_string('nav_analytics_dashboard', 'local_leducon'),
            'key'   => 'analytics_dashboard',
        ];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/overview.php'),
            'label' => get_string('nav_overview', 'local_leducon'),
            'key'   => 'overview',
        ];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/report.php', ['reporttype' => 'course_completion']),
            'label' => get_string('nav_reports', 'local_leducon'),
            'key'   => 'reports',
        ];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/heatmap.php'),
            'label' => get_string('nav_heatmap', 'local_leducon'),
            'key'   => 'heatmap',
        ];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/compare.php'),
            'label' => get_string('nav_compare', 'local_leducon'),
            'key'   => 'compare',
        ];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/leaderboard.php'),
            'label' => get_string('nav_leaderboards', 'local_leducon'),
            'key'   => 'leaderboard',
        ];
    }
    if (has_capability('local/leducon:viewown', $ctx)) {
        $rparam = ($userid > 0 && $userid != $USER->id) ? ['userid' => $userid] : [];
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/myreport.php', $rparam),
            'label' => get_string('nav_myreport', 'local_leducon'),
            'key'   => 'myreport',
        ];
    }
    if (has_capability('local/leducon:viewanalytics', $ctx)) {
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/roi.php'),
            'label' => get_string('nav_roi', 'local_leducon'),
            'key'   => 'roi',
        ];
    }
    // Org Analytics (only when org module is available).
    if (has_capability('local/leducon:viewanalytics', $ctx) &&
        get_config('local_leducon', 'org_enabled') &&
        class_exists('\local_leducon\org\org_helper') &&
        \local_leducon\org\org_helper::is_available()) {
        $analyticschildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/org_report.php'),
            'label' => get_string('nav_org_report', 'local_leducon'),
            'key'   => 'org_report',
        ];
    }
    if (!empty($analyticschildren)) {
        $items[] = [
            'type'     => 'dropdown',
            'label'    => get_string('nav_analytics', 'local_leducon'),
            'section'  => 'analytics',
            'keys'     => array_column($analyticschildren, 'key'),
            'children' => $analyticschildren,
        ];
    }

    // ── Gamification dropdown ──────────────────────────────────────
    $gamifychildren = [];
    if (has_capability('local/leducon:viewgamify', $ctx) &&
        get_config('local_leducon', 'gamify_enabled')) {
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/index.php'),
            'label' => get_string('nav_gamify_dashboard', 'local_leducon'),
            'key'   => 'gamify_dashboard',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/leaderboard.php', ['type' => 'xp']),
            'label' => get_string('nav_xp_leaderboard', 'local_leducon'),
            'key'   => 'leaderboard',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/achievements.php'),
            'label' => get_string('nav_achievements', 'local_leducon'),
            'key'   => 'achievements',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/paths.php'),
            'label' => get_string('nav_paths', 'local_leducon'),
            'key'   => 'paths',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/campaigns.php'),
            'label' => get_string('nav_campaigns', 'local_leducon'),
            'key'   => 'campaigns',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/rewards.php'),
            'label' => get_string('nav_rewards', 'local_leducon'),
            'key'   => 'rewards',
        ];
        $gamifychildren[] = [
            'url'   => new moodle_url('/local/leducon/gamify/recognition.php'),
            'label' => get_string('nav_recognition', 'local_leducon'),
            'key'   => 'recognition',
        ];
    }
    if (!empty($gamifychildren)) {
        $items[] = [
            'type'     => 'dropdown',
            'label'    => get_string('nav_gamification', 'local_leducon'),
            'section'  => 'gamify',
            'keys'     => array_column($gamifychildren, 'key'),
            'children' => $gamifychildren,
            'accent'   => true,
        ];
    }

    // ── My Team dropdown ───────────────────────────────────────────
    $teamchildren = [];
    if (has_capability('local/leducon:viewteam', $ctx)) {
        $teamchildren[] = [
            'url'   => new moodle_url('/local/leducon/manager/team.php'),
            'label' => get_string('nav_team_overview', 'local_leducon'),
            'key'   => 'team',
        ];
    }
    if (has_capability('local/leducon:awardspotlight', $ctx)) {
        $teamchildren[] = [
            'url'   => new moodle_url('/local/leducon/manager/spotlight.php'),
            'label' => get_string('nav_spotlight', 'local_leducon'),
            'key'   => 'spotlight',
        ];
    }
    if (has_capability('local/leducon:approveredemptions', $ctx)) {
        $teamchildren[] = [
            'url'   => new moodle_url('/local/leducon/manager/redemptions.php'),
            'label' => get_string('nav_redemptions', 'local_leducon'),
            'key'   => 'redemptions',
        ];
    }
    if (!empty($teamchildren)) {
        if (count($teamchildren) === 1) {
            $teamchildren[0]['type'] = 'link';
            $teamchildren[0]['section'] = 'manager';
            $items[] = $teamchildren[0];
        } else {
            $items[] = [
                'type'     => 'dropdown',
                'label'    => get_string('nav_myteam', 'local_leducon'),
                'section'  => 'manager',
                'keys'     => array_column($teamchildren, 'key'),
                'children' => $teamchildren,
            ];
        }
    }

    // ── Setup dropdown ─────────────────────────────────────────────
    $setupchildren = [];
    if (has_capability('local/leducon:manageall', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/levels.php'),
            'label' => get_string('nav_admin_levels', 'local_leducon'),
            'key'   => 'admin_levels',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/rewards.php'),
            'label' => get_string('nav_admin_rewards', 'local_leducon'),
            'key'   => 'admin_rewards',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/campaigns.php'),
            'label' => get_string('nav_admin_campaigns', 'local_leducon'),
            'key'   => 'admin_campaigns',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/paths.php'),
            'label' => get_string('nav_admin_paths', 'local_leducon'),
            'key'   => 'admin_paths',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/recognitions.php'),
            'label' => get_string('nav_admin_recognitions', 'local_leducon'),
            'key'   => 'admin_recognitions',
        ];
    }
    if (has_capability('local/leducon:manageskills', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/skills_manage.php'),
            'label' => get_string('nav_skills_manage', 'local_leducon'),
            'key'   => 'skills_manage',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/team_assign.php'),
            'label' => get_string('nav_teamsetup', 'local_leducon'),
            'key'   => 'teamsetup',
        ];
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/roi_costs.php'),
            'label' => get_string('nav_roicosts', 'local_leducon'),
            'key'   => 'roicosts',
        ];
    }
    if (has_capability('local/leducon:managealerts', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/alertrules.php'),
            'label' => get_string('nav_alertrules', 'local_leducon'),
            'key'   => 'alertrules',
        ];
    }
    if (has_capability('local/leducon:manageorgstructure', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/org_structure.php'),
            'label' => get_string('nav_org_structure', 'local_leducon'),
            'key'   => 'org_structure',
        ];
    }
    if (has_capability('local/leducon:viewanalytics', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/iltupload.php'),
            'label' => get_string('nav_ilt', 'local_leducon'),
            'key'   => 'ilt',
        ];
    }
    if (has_capability('local/leducon:managecustomreports', $ctx)) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/analytics/custom_reports.php'),
            'label' => get_string('nav_custom_reports', 'local_leducon'),
            'key'   => 'custom_reports',
        ];
    }
    if (is_siteadmin()) {
        $setupchildren[] = [
            'url'   => new moodle_url('/local/leducon/admin/notification_log.php'),
            'label' => get_string('notiflog_title', 'local_leducon'),
            'key'   => 'notification_log',
        ];
    }
    if (!empty($setupchildren)) {
        $items[] = [
            'type'     => 'dropdown',
            'label'    => get_string('nav_setup', 'local_leducon'),
            'section'  => 'admin',
            'keys'     => array_column($setupchildren, 'key'),
            'children' => $setupchildren,
        ];
    }

    if (empty($items)) {
        return;
    }

    // ── Render ──────────────────────────────────────────────────────
    echo html_writer::start_div('ld-nav');

    foreach ($items as $item) {
        if (($item['type'] ?? 'link') === 'dropdown') {
            $groupactive = ($item['section'] === $section) || in_array($activepage, $item['keys'], true);
            $accent = !empty($item['accent']);
            $gcls = 'ld-nav-dropdown' . ($groupactive ? ' ld-nav-dropdown-active' : '')
                   . ($accent ? ' ld-nav-accent' : '');
            echo '<div class="' . $gcls . '">';
            echo '<span class="ld-nav-link ld-nav-toggle' . ($groupactive ? ' active' : '')
                . ($accent ? ' ld-nav-link-accent' : '') . '">'
                . s($item['label']) . ' &#9662;</span>';
            echo '<div class="ld-nav-dropdown-menu">';
            foreach ($item['children'] as $child) {
                $cactive = ($child['key'] === $activepage);
                $ccls = 'ld-nav-dd-item' . ($cactive ? ' active' : '');
                echo html_writer::link($child['url'], s($child['label']), ['class' => $ccls]);
            }
            echo '</div></div>';
        } else {
            $isactive = ($item['key'] === $activepage) || ($item['section'] ?? '') === $section;
            $class = 'ld-nav-link' . ($isactive ? ' active' : '');
            if ($isactive) {
                echo html_writer::tag('span', s($item['label']), ['class' => $class]);
            } else {
                echo html_writer::link($item['url'], s($item['label']), ['class' => $class]);
            }
        }
    }

    echo html_writer::end_div();
}

// =====================================================================
// FILTER BAR (analytics pages)
// =====================================================================

function local_leducon_render_filter_bar(
    moodle_url $actionurl,
    array $current,
    array $options = []
): void {
    global $DB;

    $qrange     = (int)   ($current['qrange']     ?? 30);
    $datefrom   = (int)   ($current['datefrom']   ?? 0);
    $dateto     = (int)   ($current['dateto']     ?? 0);
    $courseid   = (int)   ($current['courseid']   ?? 0);
    $categoryid = (int)   ($current['categoryid'] ?? 0);
    $cohortid   = (int)   ($current['cohortid']   ?? 0);
    $orgunitid  = (int)   ($current['orgunitid']  ?? 0);
    $showcourse   = !empty($options['show_course']);
    $showcategory = !empty($options['show_category']);
    $showcohort   = !empty($options['show_cohort']);
    $showorg      = !empty($options['show_org']);
    $extrahidden  = $options['extra_hidden'] ?? [];
    $showcustom   = ($qrange < 0);

    $pills = [
        7   => get_string('last_7days',     'local_leducon'),
        30  => get_string('last_30days',    'local_leducon'),
        90  => get_string('last_90days',    'local_leducon'),
        365 => get_string('last_365days',   'local_leducon'),
        0   => get_string('period_alltime', 'local_leducon'),
    ];

    echo html_writer::start_div('ld-filter-bar');
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $actionurl->out(false),
        'class'  => 'ld-unified-filter',
    ]);

    foreach ($extrahidden as $hname => $hval) {
        echo html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => $hname,
            'value' => s((string) $hval),
        ]);
    }

    // Quick-range pills.
    echo html_writer::start_div('ld-pill-row');
    echo html_writer::tag('span',
        s(get_string('filter_daterange', 'local_leducon')) . ':',
        ['class' => 'ld-pill-label']
    );

    foreach ($pills as $pval => $plabel) {
        $active = (!$showcustom && $pval === $qrange);
        echo html_writer::tag('button', s($plabel), [
            'type'  => 'submit',
            'name'  => 'qrange',
            'value' => $pval,
            'class' => 'ld-pill-btn' . ($active ? ' active' : ''),
        ]);
    }

    echo html_writer::tag('button',
        s(get_string('filter_custom', 'local_leducon')),
        [
            'type'  => 'submit',
            'name'  => 'qrange',
            'value' => '-1',
            'class' => 'ld-pill-btn ld-pill-custom' . ($showcustom ? ' active' : ''),
        ]
    );
    echo html_writer::end_div();

    // Custom date inputs.
    if ($showcustom) {
        $dfval = $datefrom > 0 ? date('Y-m-d', $datefrom) : date('Y-m-d', time() - 30 * DAYSECS);
        $dtval = $dateto   > 0 ? date('Y-m-d', $dateto)   : date('Y-m-d');

        echo html_writer::start_div('ld-custom-row');
        echo html_writer::tag('label',
            s(get_string('filter_from', 'local_leducon')) . ':',
            ['class' => 'ld-filter-label']
        );
        echo html_writer::empty_tag('input', [
            'type'  => 'date',
            'name'  => 'datefrom_str',
            'value' => $dfval,
            'class' => 'ld-input ld-filter-input',
        ]);
        echo html_writer::tag('label',
            s(get_string('filter_to', 'local_leducon')) . ':',
            ['class' => 'ld-filter-label']
        );
        echo html_writer::empty_tag('input', [
            'type'  => 'date',
            'name'  => 'dateto_str',
            'value' => $dtval,
            'class' => 'ld-input ld-filter-input',
        ]);
        echo html_writer::end_div();
    }

    // Check if org module is available for the org dropdown.
    if ($showorg) {
        $showorg = get_config('local_leducon', 'org_enabled') &&
            class_exists('\local_leducon\org\org_helper') &&
            \local_leducon\org\org_helper::is_available();
    }

    // Dropdown filters.
    $hasdropdowns = $showcourse || $showcategory || $showcohort || $showorg;
    if ($hasdropdowns) {
        echo html_writer::start_div('ld-dropdown-row');

        if ($showcourse) {
            try {
                $courses = $DB->get_records_menu('course', null, 'fullname ASC', 'id,fullname', 0, 500);
                unset($courses[SITEID]);
            } catch (\dml_exception $e) {
                $courses = [];
            }
            $copts = [0 => get_string('filter_allcourses', 'local_leducon')] + $courses;
            echo html_writer::tag('label',
                s(get_string('filter_course', 'local_leducon')) . ':',
                ['class' => 'ld-filter-label']
            );
            echo html_writer::select($copts, 'courseid', $courseid, false,
                ['class' => 'ld-input ld-filter-input']);
        }

        if ($showcategory) {
            try {
                $cats = $DB->get_records_menu('course_categories', [], 'name ASC', 'id,name', 0, 200);
            } catch (\dml_exception $e) {
                $cats = [];
            }
            $catopts = [0 => get_string('filter_allcategories', 'local_leducon')] + $cats;
            echo html_writer::tag('label',
                s(get_string('filter_category', 'local_leducon')) . ':',
                ['class' => 'ld-filter-label']
            );
            echo html_writer::select($catopts, 'categoryid', $categoryid, false,
                ['class' => 'ld-input ld-filter-input']);
        }

        if ($showcohort) {
            try {
                $cohorts = $DB->get_records_menu('cohort', [], 'name ASC', 'id,name', 0, 200);
            } catch (\dml_exception $e) {
                $cohorts = [];
            }
            $cohopts = [0 => get_string('filter_allcohorts', 'local_leducon')] + $cohorts;
            echo html_writer::tag('label',
                s(get_string('filter_cohort', 'local_leducon')) . ':',
                ['class' => 'ld-filter-label']
            );
            echo html_writer::select($cohopts, 'cohortid', $cohortid, false,
                ['class' => 'ld-input ld-filter-input']);
        }

        if ($showorg) {
            $orgopts = \local_leducon\org\org_helper::get_org_dropdown_options(true);
            echo html_writer::tag('label',
                s(get_string('filter_org', 'local_leducon')) . ':',
                ['class' => 'ld-filter-label']
            );
            echo html_writer::select($orgopts, 'orgunitid', $orgunitid, false,
                ['class' => 'ld-input ld-filter-input']);
        }

        echo html_writer::end_div();
    }

    // Apply + Reset.
    echo html_writer::start_div('ld-filter-actions');
    echo html_writer::tag('button',
        s(get_string('filter_apply', 'local_leducon')),
        ['type' => 'submit', 'name' => 'apply', 'value' => '1', 'class' => 'ld-filter-apply-btn']
    );
    $reseturl = new moodle_url($actionurl, $extrahidden);
    echo html_writer::link(
        $reseturl->out(false),
        s(get_string('filter_reset', 'local_leducon')),
        ['class' => 'ld-filter-reset-btn']
    );
    echo html_writer::end_div();

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

function local_leducon_get_filter_params(): array {
    $qrange     = optional_param('qrange',      30, PARAM_INT);
    $datefromts = optional_param('datefrom',     0,  PARAM_INT);
    $datetots   = optional_param('dateto',       0,  PARAM_INT);
    $courseid   = optional_param('courseid',     0,  PARAM_INT);
    $categoryid = optional_param('categoryid',   0,  PARAM_INT);
    $cohortid   = optional_param('cohortid',     0,  PARAM_INT);
    $orgunitid  = optional_param('orgunitid',    0,  PARAM_INT);

    $datefromstr = optional_param('datefrom_str', '', PARAM_ALPHANUMEXT);
    $datetostr   = optional_param('dateto_str',   '', PARAM_ALPHANUMEXT);
    if ($datefromstr !== '') {
        $ts = strtotime($datefromstr . ' 00:00:00');
        if ($ts !== false) {
            $datefromts = $ts;
        }
    }
    if ($datetostr !== '') {
        $ts = strtotime($datetostr . ' 23:59:59');
        if ($ts !== false) {
            $datetots = $ts;
        }
    }

    [$from, $to] = local_leducon_resolve_daterange($qrange, $datefromts, $datetots);

    return [
        'qrange'            => $qrange,
        'datefrom'          => $datefromts,
        'dateto'            => $datetots,
        'datefrom_resolved' => $from,
        'dateto_resolved'   => $to,
        'courseid'          => $courseid,
        'categoryid'        => $categoryid,
        'cohortid'          => $cohortid,
        'orgunitid'         => $orgunitid,
    ];
}

// =====================================================================
// REPORT TYPES & FACTORY
// =====================================================================

function local_leducon_get_report_types(): array {
    $types = [
        'course_completion' => get_string('report_course_completion', 'local_leducon'),
        'grade_analytics'   => get_string('report_grade_analytics',   'local_leducon'),
        'enrollment'        => get_string('report_enrollment',        'local_leducon'),
        'quiz_analytics'    => get_string('report_quiz_analytics',    'local_leducon'),
        'login_activity'    => get_string('report_login_activity',    'local_leducon'),
        'scorm_analytics'   => get_string('report_scorm_analytics',   'local_leducon'),
        'assignment'        => get_string('report_assignment',        'local_leducon'),
        'forum'             => get_string('report_forum',             'local_leducon'),
        'atrisk'            => get_string('report_atrisk',            'local_leducon'),
        'timespent'         => get_string('report_timespent',         'local_leducon'),
        'badge'             => get_string('report_badge',             'local_leducon'),
        'certificate'       => get_string('report_certificate',       'local_leducon'),
        'compliance'        => get_string('report_compliance',        'local_leducon'),
        'category'          => get_string('report_category',          'local_leducon'),
        'instructor'        => get_string('report_instructor',        'local_leducon'),
        'ilt'               => get_string('report_ilt',               'local_leducon'),
        'teacherview'       => get_string('report_teacherview',       'local_leducon'),
        'managerview'       => get_string('report_managerview',       'local_leducon'),
        'skills'            => get_string('report_skills',            'local_leducon'),
        'roi_analyst'       => get_string('report_roi_analyst',       'local_leducon'),
    ];

    $togglemap = [
        'quiz_analytics'  => 'enable_quiz_reports',
        'assignment'      => 'enable_assignment_reports',
        'forum'           => 'enable_forum_reports',
        'scorm_analytics' => 'enable_scorm_reports',
        'ilt'             => 'enable_ilt_reports',
    ];
    foreach ($togglemap as $key => $setting) {
        if (!get_config('local_leducon', $setting)) {
            unset($types[$key]);
        }
    }

    return $types;
}

/**
 * Get report types grouped into logical categories for the sidebar.
 *
 * Each group has a lang-string key for its heading and an array of report keys.
 * The active report's group auto-expands; others are collapsed.
 *
 * @return array [ ['heading' => string, 'reports' => [key => label, ...]], ... ]
 */
function local_leducon_get_report_groups(): array {
    $all = local_leducon_get_report_types();

    $groups = [
        [
            'heading' => get_string('report_group_learner', 'local_leducon'),
            'keys'    => ['course_completion', 'enrollment', 'grade_analytics', 'atrisk', 'compliance'],
        ],
        [
            'heading' => get_string('report_group_activity', 'local_leducon'),
            'keys'    => ['login_activity', 'timespent', 'quiz_analytics', 'scorm_analytics'],
        ],
        [
            'heading' => get_string('report_group_content', 'local_leducon'),
            'keys'    => ['assignment', 'forum', 'badge', 'certificate', 'ilt'],
        ],
        [
            'heading' => get_string('report_group_management', 'local_leducon'),
            'keys'    => ['category', 'instructor', 'teacherview', 'managerview'],
        ],
        [
            'heading' => get_string('report_group_skills_roi', 'local_leducon'),
            'keys'    => ['skills', 'roi_analyst'],
        ],
    ];

    $result = [];
    foreach ($groups as $g) {
        $reports = [];
        foreach ($g['keys'] as $key) {
            if (isset($all[$key])) {
                $reports[$key] = $all[$key];
            }
        }
        if (!empty($reports)) {
            $result[] = ['heading' => $g['heading'], 'reports' => $reports];
        }
    }
    return $result;
}

function local_leducon_get_report(string $type): \local_leducon\reports\base_report {
    $map = [
        'course_completion' => \local_leducon\reports\course_completion_report::class,
        'user_activity'     => \local_leducon\reports\user_activity_report::class,
        'grade_analytics'   => \local_leducon\reports\grade_analytics_report::class,
        'enrollment'        => \local_leducon\reports\enrollment_report::class,
        'quiz_analytics'    => \local_leducon\reports\quiz_analytics_report::class,
        'login_activity'    => \local_leducon\reports\login_activity_report::class,
        'scorm_analytics'   => \local_leducon\reports\scorm_analytics_report::class,
        'assignment'        => \local_leducon\reports\assignment_report::class,
        'forum'             => \local_leducon\reports\forum_report::class,
        'atrisk'            => \local_leducon\reports\atrisk_report::class,
        'timespent'         => \local_leducon\reports\timespent_report::class,
        'badge'             => \local_leducon\reports\badge_report::class,
        'certificate'       => \local_leducon\reports\certificate_report::class,
        'compliance'        => \local_leducon\reports\compliance_report::class,
        'category'          => \local_leducon\reports\category_report::class,
        'instructor'        => \local_leducon\reports\instructor_report::class,
        'ilt'               => \local_leducon\reports\ilt_report::class,
        'teacherview'       => \local_leducon\reports\teacherview_report::class,
        'managerview'       => \local_leducon\reports\managerview_report::class,
        'skills'            => \local_leducon\reports\skills_report::class,
        'roi_analyst'       => \local_leducon\reports\roi_analyst_report::class,
    ];

    if (!array_key_exists($type, $map)) {
        throw new coding_exception("Unknown report type: $type");
    }

    $class = $map[$type];
    return new $class();
}

// =====================================================================
// ANALYTICS HELPERS
// =====================================================================

function local_leducon_kpi_color(float $pct, string $type = 'completion'): string {
    $green = (float) (get_config('local_leducon', 'kpi_' . $type . '_green') ?: 75);
    $amber = (float) (get_config('local_leducon', 'kpi_' . $type . '_amber') ?: 50);

    if ($pct >= $green) {
        return 'success';
    }
    if ($pct >= $amber) {
        return 'warning';
    }
    return 'danger';
}

function local_leducon_days_ago(int $days): int {
    return time() - $days * DAYSECS;
}

function local_leducon_resolve_daterange(int $qrange, int $datefrom, int $dateto): array {
    $now = time();
    if ($qrange > 0) {
        return [$now - $qrange * DAYSECS, $now];
    }
    if ($qrange === 0) {
        return [0, $now];
    }
    $from = ($datefrom > 0) ? $datefrom : ($now - 30 * DAYSECS);
    $to   = ($dateto   > 0) ? $dateto   : $now;
    return [$from, $to];
}

// =====================================================================
// GAMIFICATION HELPERS
// =====================================================================

function local_leducon_page_header(
    string $title,
    string $description = '',
    int    $count       = -1,
    string $count_class = 'ld-badge-primary'
): void {
    echo html_writer::start_div('ld-page-header');
    $heading = html_writer::tag('h2', s($title), ['class' => 'ld-page-title']);
    if ($count >= 0) {
        $heading .= ' ' . html_writer::tag('span', $count, ['class' => 'ld-badge ' . $count_class]);
    }
    echo $heading;
    if ($description !== '') {
        echo html_writer::tag('p', s($description), ['class' => 'ld-page-desc']);
    }
    echo html_writer::end_div();
}

function local_leducon_card_header(string $title, string $action_html = ''): void {
    echo html_writer::start_div('ld-card-header');
    echo html_writer::tag('h3', s($title), ['class' => 'ld-card-title']);
    if ($action_html !== '') {
        echo $action_html;
    }
    echo html_writer::end_div();
}

function local_leducon_level_badge(int $levelnum, string $levelname): string {
    $icons = [1 => '🌱', 2 => '📘', 3 => '⭐', 4 => '🔥', 5 => '👑'];
    $icon  = $icons[$levelnum] ?? '🎖️';
    return html_writer::tag('span',
        $icon . ' ' . s($levelname),
        ['class' => 'ld-level-badge ld-level-' . $levelnum]
    );
}

function local_leducon_xp_bar(int $current_xp, int $next_level_xp, int $prev_level_xp): string {
    $range  = max(1, $next_level_xp - $prev_level_xp);
    $earned = max(0, $current_xp - $prev_level_xp);
    $pct    = min(100, (int) round($earned / $range * 100));

    $bar = html_writer::tag('div', '', [
        'class' => 'ld-xp-fill',
        'style' => "width:{$pct}%",
    ]);
    return html_writer::tag('div', $bar, ['class' => 'ld-xp-bar']) .
           html_writer::tag('small',
               "{$pct}% " . get_string('to_next_level', 'local_leducon'),
               ['class' => 'ld-xp-label text-muted']
           );
}

function local_leducon_fmt_xp(int $xp): string {
    return number_format($xp) . ' XP';
}

function local_leducon_get_streak(int $userid): int {
    global $DB;
    $rec = $DB->get_record('local_leducon_xp_users', ['userid' => $userid], 'streak_days');
    return $rec ? (int) $rec->streak_days : 0;
}

function local_leducon_avatar(string $fullname, string $extra_class = ''): string {
    $parts    = explode(' ', trim($fullname));
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (isset($parts[1])) {
        $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
    }
    return html_writer::tag('span', $initials,
        ['class' => 'ld-avatar ' . $extra_class, 'aria-hidden' => 'true']);
}

function local_leducon_rank_medal(int $rank): string {
    if ($rank === 1) {
        return '<span class="ld-lb-rank gold" title="1st">🥇</span>';
    }
    if ($rank === 2) {
        return '<span class="ld-lb-rank silver" title="2nd">🥈</span>';
    }
    if ($rank === 3) {
        return '<span class="ld-lb-rank bronze" title="3rd">🥉</span>';
    }
    return '<span class="ld-lb-rank">' . $rank . '</span>';
}

// =====================================================================
// PLATFORM ROLES
// =====================================================================

/**
 * Create or update the three shared Leducon platform roles.
 *
 * Safe to call repeatedly — skips role creation if shortname already exists,
 * and skips capabilities that are not yet registered.
 */
function local_leducon_setup_roles(): void {
    global $DB;

    $syscontext = context_system::instance();

    $roles = [

        // L&D Executive — strategic read-only view.
        [
            'shortname'   => 'leducon_executive',
            'name'        => 'L&D Executive',
            'description' => 'Read-only strategic oversight of learning analytics and gamification activity. '
                           . 'Sees org-wide reports, ROI, and skills coverage. '
                           . 'Cannot modify platform configuration or access individual learner records.',
            'archetype'   => 'manager',
            'caps'        => [
                'local/leducon:viewgamify',
                'local/leducon:viewleaderboard',
                'local/leducon:viewanalytics',
                'local/leducon:viewmanagerview',
                'local/leducon:viewskills',
            ],
        ],

        // Team Manager — people manager with team oversight.
        [
            'shortname'   => 'leducon_teammanager',
            'name'        => 'Team Manager',
            'description' => 'People manager with oversight of a cohort or department. '
                           . 'Can view team analytics, award spotlights, and approve reward redemptions. '
                           . 'Cannot modify platform configuration.',
            'archetype'   => 'manager',
            'caps'        => [
                'local/leducon:viewgamify',
                'local/leducon:viewleaderboard',
                'local/leducon:viewteam',
                'local/leducon:awardspotlight',
                'local/leducon:approveredemptions',
                'local/leducon:viewanalytics',
                'local/leducon:viewown',
                'local/leducon:viewteacherview',
                'local/leducon:viewmanagerview',
                'local/leducon:viewstudents',
                'local/leducon:viewskills',
            ],
        ],

        // L&D Administrator — full platform ownership.
        [
            'shortname'   => 'leducon_ldadmin',
            'name'        => 'L&D Administrator',
            'description' => 'Full access to all Leducon features. '
                           . 'Manages levels, rewards, campaigns, paths, skills, ROI costs and alert rules.',
            'archetype'   => 'manager',
            'caps'        => [
                'local/leducon:viewgamify',
                'local/leducon:viewleaderboard',
                'local/leducon:viewteam',
                'local/leducon:awardspotlight',
                'local/leducon:approveredemptions',
                'local/leducon:manageall',
                'local/leducon:viewanalytics',
                'local/leducon:viewown',
                'local/leducon:viewteacherview',
                'local/leducon:viewmanagerview',
                'local/leducon:viewstudents',
                'local/leducon:viewskills',
                'local/leducon:manageskills',
                'local/leducon:managealerts',
                'local/leducon:manageorgstructure',
                'local/leducon:managecustomreports',
            ],
        ],
    ];

    foreach ($roles as $roledef) {
        if ($existing = $DB->get_record('role', ['shortname' => $roledef['shortname']])) {
            $roleid = (int) $existing->id;
        } else {
            $roleid = create_role(
                $roledef['name'],
                $roledef['shortname'],
                $roledef['description'],
                $roledef['archetype']
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }

        foreach ($roledef['caps'] as $cap) {
            if (!get_capability_info($cap)) {
                continue;
            }
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
    }
}

// =====================================================================
// PAGINATION HELPER
// =====================================================================

/**
 * Renders a simple pagination bar.
 *
 * @param int    $total    Total number of records.
 * @param int    $page     Current page (0-based).
 * @param int    $perpage  Records per page.
 * @param string $baseurl  Base URL (moodle_url or string).
 * @return string HTML
 */
function local_leducon_render_pagination(int $total, int $page, int $perpage, $baseurl): string {
    if ($total <= $perpage) {
        return '';
    }
    $totalpages = (int)ceil($total / $perpage);
    $out = '<div class="ld-pagination">';
    if ($page > 0) {
        $out .= '<a class="ld-page-link" href="' . $baseurl . '&page=' . ($page - 1) . '">&laquo;</a>';
    }
    for ($i = 0; $i < $totalpages; $i++) {
        $cls = $i === $page ? 'ld-page-link active' : 'ld-page-link';
        $out .= '<a class="' . $cls . '" href="' . $baseurl . '&page=' . $i . '">' . ($i + 1) . '</a>';
    }
    if ($page < $totalpages - 1) {
        $out .= '<a class="ld-page-link" href="' . $baseurl . '&page=' . ($page + 1) . '">&raquo;</a>';
    }
    $out .= '</div>';
    return $out;
}

/**
 * Returns paginated slice of array data.
 *
 * @param array $data    Full data array.
 * @param int   $page    Current page (0-based).
 * @param int   $perpage Per-page limit.
 * @return array Sliced data.
 */
function local_leducon_paginate(array $data, int $page, int $perpage): array {
    return array_slice($data, $page * $perpage, $perpage);
}

// =====================================================================
// MUC CACHE HELPERS (for expensive KPI queries)
// =====================================================================

/**
 * Get a cached value from MUC application cache, or compute and store it.
 *
 * Uses Moodle's core cache API with a 5-minute TTL for analytics KPIs.
 * Falls back to direct computation if caching is unavailable.
 *
 * @param string   $key      Cache key.
 * @param callable $compute  Callback that returns the value to cache.
 * @param int      $ttl      Time-to-live in seconds (default 300 = 5 min).
 * @return mixed
 */
function local_leducon_cached(string $key, callable $compute, int $ttl = 300) {
    // Use Moodle's request cache for simple short-term caching.
    // This avoids repeated identical queries within the same page load.
    static $localcache = [];
    static $timestamps = [];

    $now = time();
    if (isset($localcache[$key]) && isset($timestamps[$key]) && ($now - $timestamps[$key]) < $ttl) {
        return $localcache[$key];
    }

    $value = $compute();
    $localcache[$key] = $value;
    $timestamps[$key] = $now;
    return $value;
}

// =====================================================================
// AJAX ENDPOINT HELPER
// =====================================================================

/**
 * Returns JSON response for AJAX dashboard requests.
 * Called from index.php when ?ajax=1 parameter is present.
 *
 * @param string $section Section to load (kpis|gamify|quicklaunch).
 * @param int    $userid  User ID.
 * @return array Data array for JSON encoding.
 */
function local_leducon_ajax_section(string $section, int $userid): array {
    global $DB;

    switch ($section) {
        case 'kpis':
            $kpi = [];
            try {
                $kpi['active_users'] = (int)$DB->count_records_sql(
                    "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
                      WHERE action = 'loggedin' AND timecreated > :weekago",
                    ['weekago' => time() - 7 * DAYSECS]
                );
            } catch (\dml_exception $e) { $kpi['active_users'] = 0; }
            try {
                $kpi['completions'] = (int)$DB->count_records_sql(
                    "SELECT COUNT(*) FROM {course_completions} cc
                    LEFT JOIN {grade_items} gi_l ON gi_l.courseid = cc.course AND gi_l.itemtype = 'course'
                    LEFT JOIN {grade_grades} gg_l ON gg_l.itemid = gi_l.id AND gg_l.userid = cc.userid
                    WHERE cc.timecompleted IS NOT NULL
                       OR (gg_l.finalgrade IS NOT NULL AND gi_l.grademax > 0 AND gg_l.finalgrade / gi_l.grademax * 100 >= 100)");
            } catch (\dml_exception $e) { $kpi['completions'] = 0; }
            return $kpi;

        default:
            return ['error' => 'unknown_section'];
    }
}

// =====================================================================
// NOTIFICATION HELPERS
// =====================================================================

/**
 * Send a notification via Moodle messaging API and log it.
 *
 * @param object $userto Recipient user object (must have id, email, firstname, lastname)
 * @param string $subject Message subject
 * @param string $body Plain text body
 * @param string $messagetype Message provider name (e.g. 'insight_alert', 'compliance_reminder')
 * @param string|null $contexturl Optional URL for the notification
 * @param string|null $contexturlname Optional label for the URL
 * @return bool Whether the message was sent successfully
 */
function local_leducon_send_notification($userto, string $subject, string $body,
        string $messagetype = 'general', ?string $contexturl = null, ?string $contexturlname = null): bool {
    global $DB;

    $supportuser = \core_user::get_support_user();

    $message = new \core\message\message();
    $message->component         = 'local_leducon';
    $message->name              = $messagetype;
    $message->userfrom          = $supportuser;
    $message->userto            = $userto;
    $message->subject           = $subject;
    $message->fullmessage       = $body;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml   = nl2br(s($body));
    $message->smallmessage      = $subject;
    $message->notification      = 1;
    if ($contexturl) {
        $message->contexturl     = new \moodle_url($contexturl);
        $message->contexturlname = $contexturlname ?? $subject;
    }

    $success = false;
    try {
        message_send($message);
        $success = true;
    } catch (\Exception $e) {
        debugging('local_leducon notification send failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    local_leducon_log_notification(
        $userto->id ?? 0,
        $userto->email ?? '',
        fullname($userto),
        $subject,
        $body,
        'message',
        $messagetype,
        $success ? 'sent' : 'failed'
    );

    return $success;
}

/**
 * Send an email and log it.
 */
function local_leducon_send_email($userto, string $subject, string $body,
        string $messagetype = 'general'): bool {
    $noreply = \core_user::get_noreply_user();
    $result = email_to_user($userto, $noreply, $subject, $body, nl2br(s($body)));

    local_leducon_log_notification(
        $userto->id ?? 0,
        $userto->email ?? '',
        is_object($userto) && isset($userto->firstname) ? fullname($userto) : ($userto->email ?? ''),
        $subject,
        $body,
        'email',
        $messagetype,
        $result ? 'sent' : 'failed'
    );

    return (bool) $result;
}

/**
 * Log a notification/email to the database.
 */
function local_leducon_log_notification(int $userid, string $email, string $name,
        string $subject, string $body, string $channel, string $messagetype, string $status): void {
    global $DB;
    try {
        $DB->insert_record('local_leducon_notif_log', (object) [
            'userid'         => $userid,
            'recipientemail' => substr($email, 0, 255),
            'recipientname'  => substr($name, 0, 255),
            'subject'        => substr($subject, 0, 500),
            'body'           => $body,
            'channel'        => $channel,
            'messagetype'    => $messagetype,
            'status'         => $status,
            'timecreated'    => time(),
        ]);
    } catch (\Exception $e) {
        debugging('local_leducon notification log insert failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// =====================================================================
// BULK OPERATION HELPERS (Admin pages)
// =====================================================================

/**
 * Process a bulk enable/disable action for admin pages.
 *
 * @param string $table   DB table name (without prefix).
 * @param string $field   Field to update (typically 'enabled').
 * @param int    $value   New value (1 = enable, 0 = disable).
 * @param array  $ids     Array of record IDs to update.
 * @return int Number of records updated.
 */
function local_leducon_bulk_toggle(string $table, string $field, int $value, array $ids): int {
    global $DB;

    if (empty($ids)) {
        return 0;
    }

    $count = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $DB->set_field($table, $field, $value, ['id' => $id]);
            $count++;
        }
    }
    return $count;
}

// =====================================================================
// FILE SERVING
// =====================================================================

function local_leducon_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return false;
}
