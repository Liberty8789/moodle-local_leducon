<?php
// This file is part of Moodle - http://moodle.org/

/**
 * XP Leaderboard — redirects to unified leaderboard with XP tab selected.
 *
 * Kept for backward-compatibility so old bookmarks and links still work.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();

// Redirect to the unified leaderboard with the XP tab pre-selected.
$params = ['type' => 'xp'];
$cohortid = optional_param('cohortid', 0, PARAM_INT);
if ($cohortid > 0) {
    $params['cohortid'] = $cohortid;
}
redirect(new moodle_url('/local/leducon/analytics/leaderboard.php', $params));
