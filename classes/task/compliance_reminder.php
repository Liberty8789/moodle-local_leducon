<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: send compliance deadline reminders.
 *
 * Finds learners enrolled in courses with an end date approaching within
 * a configurable window (default 7 days) who have NOT yet completed,
 * and sends them a reminder email.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

class compliance_reminder extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_compliance_reminder', 'local_leducon');
    }

    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->libdir . '/moodlelib.php');

        $enabled = (int) get_config('local_leducon', 'compliance_reminder_enabled');
        if (!$enabled) {
            mtrace('local_leducon compliance_reminder: disabled, skipping.');
            return;
        }

        $days = (int) get_config('local_leducon', 'compliance_reminder_days') ?: 7;
        $now = time();
        $windowend = $now + ($days * DAYSECS);

        // Find users enrolled in courses ending soon who haven't completed.
        $sql = "SELECT DISTINCT ue.id AS ueid, u.id AS userid,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       u.email, u.lang,
                       c.id AS courseid, c.fullname AS coursename,
                       c.enddate
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
             LEFT JOIN {grade_items} gi_cr ON gi_cr.courseid = c.id AND gi_cr.itemtype = 'course'
             LEFT JOIN {grade_grades} gg_cr ON gg_cr.itemid = gi_cr.id AND gg_cr.userid = u.id
                 WHERE ue.status = 0
                   AND c.enddate > :now
                   AND c.enddate <= :windowend
                   AND (cc.id IS NULL OR cc.timecompleted IS NULL)
                   AND NOT (gg_cr.finalgrade IS NOT NULL AND gi_cr.grademax > 0 AND gg_cr.finalgrade / gi_cr.grademax * 100 >= 100)
                 ORDER BY c.enddate ASC, u.lastname ASC";

        $params = ['now' => $now, 'windowend' => $windowend];

        try {
            $records = $DB->get_records_sql($sql, $params, 0, 5000);
        } catch (\dml_exception $e) {
            mtrace('local_leducon compliance_reminder: DB error — ' . $e->getMessage());
            return;
        }

        if (empty($records)) {
            mtrace('local_leducon compliance_reminder: no approaching deadlines found.');
            return;
        }

        mtrace('local_leducon compliance_reminder: ' . count($records) . ' reminder(s) to send.');

        $sent = 0;
        $courseurl_base = '/course/view.php?id=';

        // Avoid duplicate emails: group by user+course.
        $seen = [];

        foreach ($records as $rec) {
            $key = $rec->userid . '_' . $rec->courseid;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $daysleft = max(0, (int) ceil(($rec->enddate - $now) / DAYSECS));

            $subject = get_string('pluginname', 'local_leducon') .
                       ': ' . get_string('compliance_reminder_subject', 'local_leducon',
                           (object)['course' => $rec->coursename, 'days' => $daysleft]);

            $a = new \stdClass();
            $a->fullname = $rec->fullname;
            $a->coursename = $rec->coursename;
            $a->days = $daysleft;
            $a->deadline = userdate($rec->enddate, get_string('strftimedatetimeshort', 'langconfig'));
            $a->courseurl = $CFG->wwwroot . $courseurl_base . $rec->courseid;

            $body = get_string('compliance_reminder_body', 'local_leducon', $a);

            $touser = \core_user::get_user($rec->userid);
            if (!$touser || $touser->deleted) {
                continue;
            }

            $result = local_leducon_send_notification(
                $touser, $subject, $body, 'compliance_reminder',
                $courseurl_base . $rec->courseid, $rec->coursename
            );
            if ($result) {
                $sent++;
            }
        }

        mtrace("local_leducon compliance_reminder: sent {$sent} reminders.");
    }
}
