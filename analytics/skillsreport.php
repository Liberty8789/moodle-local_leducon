<?php
// This file is part of Moodle - http://moodle.org/
//
// Redirect shim — Skills Report is now served by report.php.
// This file preserves old bookmarked URLs.

require_once(__DIR__ . '/../../../config.php');
require_login();

$params = ['reporttype' => 'skills'];
$cohortid = optional_param('cohortid', 0, PARAM_INT);
if ($cohortid) {
    $params['cohortid'] = $cohortid;
}

redirect(new moodle_url('/local/leducon/analytics/report.php', $params));
