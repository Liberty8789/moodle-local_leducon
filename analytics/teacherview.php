<?php
// This file is part of Moodle - http://moodle.org/
//
// Redirect shim — Teacher View is now served by report.php.
// This file preserves old bookmarked URLs.

require_once(__DIR__ . '/../../../config.php');
require_login();

$params = ['reporttype' => 'teacherview'];
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid) {
    $params['courseid'] = $courseid;
}

redirect(new moodle_url('/local/leducon/analytics/report.php', $params));
