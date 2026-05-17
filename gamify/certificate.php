<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Certificate download — validates milestone ownership and streams PDF.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/leducon/lib.php');

use local_leducon\gamify\certificate_generator;

require_login();
$ctx = context_system::instance();
require_capability('local/leducon:viewgamify', $ctx);

if (!get_config('local_leducon', 'gamify_enabled')) {
    redirect(new moodle_url('/local/leducon/index.php'), get_string('gamify_disabled', 'local_leducon'));
}

$type = required_param('type', PARAM_ALPHA);   // e.g. 'level', 'streak', 'path'
$ref  = optional_param('ref',  '', PARAM_TEXT); // human-readable reference (level name, days, etc.)

// Validate that the user has actually earned this milestone.
$userid = $USER->id;
$valid  = false;

switch ($type) {
    case 'level':
        // ref = levelnum.
        $levelnum  = (int)$ref;
        $gu        = $DB->get_record('local_leducon_xp_users', ['userid' => $userid], 'levelid');
        $cur_level = $gu ? $DB->get_record('local_leducon_levels', ['id' => $gu->levelid]) : null;
        $valid     = $cur_level && (int)$cur_level->levelnum >= $levelnum;
        $label     = $cur_level ? format_string($cur_level->name) : 'Level ' . $levelnum;
        break;

    case 'streak':
        // ref = days (7, 30, 90, 365).
        $days  = (int)$ref;
        $streak = local_leducon_get_streak($userid);
        $valid  = $streak >= $days;
        $label  = $days . '-day streak';
        break;

    case 'path':
        // ref = pathid.
        $pathid = (int)$ref;
        $path   = $DB->get_record('local_leducon_paths', ['id' => $pathid], 'name');
        $valid  = $path && $DB->record_exists('local_leducon_xp_log', [
            'userid'    => $userid,
            'eventtype' => 'path_completion',
            'contextid' => $pathid,
        ]);
        $label  = $path ? format_string($path->name) : '';
        break;

    default:
        $valid = false;
        $label = '';
}

if (!$valid) {
    redirect(new moodle_url('/local/leducon/gamify/index.php'),
        get_string('cert_not_earned', 'local_leducon'), null,
        \core\output\notification::NOTIFY_WARNING);
}

certificate_generator::generate($userid, $label);
