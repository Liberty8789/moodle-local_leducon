<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task: evaluate alert rules and dispatch notifications.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_leducon\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Iterates over all active alert rules stored in local_leducon_alertrules,
 * evaluates each rule against current learner data, and sends email notifications
 * to teachers and/or extra email addresses when the condition is triggered.
 *
 * Runs every 4 hours by default (see db/tasks.php).
 */
class alert_checker extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('nav_alertrules', 'local_leducon');
    }

    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->libdir . '/moodlelib.php');

        $rules = $DB->get_records('local_leducon_alertrules', ['active' => 1]);

        if (empty($rules)) {
            mtrace('local_leducon alert_checker: no active rules, nothing to do.');
            return;
        }

        foreach ($rules as $rule) {
            mtrace("local_leducon alert_checker: evaluating rule [{$rule->id}] '{$rule->name}'");
            try {
                $this->evaluate_rule($rule);
            } catch (\Throwable $e) {
                mtrace("local_leducon alert_checker: error in rule [{$rule->id}]: " . $e->getMessage());
            }
        }
    }

    protected function evaluate_rule(\stdClass $rule): void {
        global $DB;

        $matches = [];

        switch ($rule->condition_type) {
            case 'inactive_days':
                $matches = $this->check_inactive_days($rule);
                break;
            case 'grade_below':
                $matches = $this->check_grade_below($rule);
                break;
            case 'completion_below':
                $matches = $this->check_completion_below($rule);
                break;
            case 'attempts_zero':
                $matches = $this->check_attempts_zero($rule);
                break;
            default:
                mtrace("local_leducon alert_checker: unknown condition_type '{$rule->condition_type}', skipping.");
                return;
        }

        if (empty($matches)) {
            mtrace("  -> no matches.");
            return;
        }

        mtrace("  -> " . count($matches) . " student(s) matched.");
        $this->send_notifications($rule, $matches);
    }

    protected function check_inactive_days(\stdClass $rule): array {
        global $DB;

        $thresholdtime = time() - ((int)$rule->threshold * DAYSECS);
        $params        = ['thresholdtime' => $thresholdtime];
        $coursefilter   = '';

        if ((int)$rule->courseid > 0) {
            $coursefilter        = 'AND e.courseid = :courseid';
            $params['courseid'] = (int)$rule->courseid;
        }

        $cohortjoin = '';
        if ((int)$rule->cohortid > 0) {
            $cohortjoin         = 'JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid';
            $params['cohortid'] = (int)$rule->cohortid;
        }

        $sql = "SELECT u.id AS userid,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       u.email,
                       e.courseid,
                       c.fullname AS coursename
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {course} c ON c.id = e.courseid
                  {$cohortjoin}
                 WHERE ue.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND (u.lastaccess < :thresholdtime OR u.lastaccess = 0)
                   {$coursefilter}";

        return $DB->get_records_sql($sql, $params);
    }

    protected function check_grade_below(\stdClass $rule): array {
        global $DB;

        $params       = ['threshold' => (float)$rule->threshold];
        $coursefilter  = '';

        if ((int)$rule->courseid > 0) {
            $coursefilter        = 'AND gi.courseid = :courseid';
            $params['courseid'] = (int)$rule->courseid;
        }

        $cohortjoin = '';
        if ((int)$rule->cohortid > 0) {
            $cohortjoin         = 'JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid';
            $params['cohortid'] = (int)$rule->cohortid;
        }

        $sql = "SELECT u.id AS userid,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       u.email,
                       gi.courseid,
                       c.fullname AS coursename,
                       gg.finalgrade AS grade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
                  JOIN {user} u ON u.id = gg.userid
                  JOIN {course} c ON c.id = gi.courseid
                  {$cohortjoin}
                 WHERE gg.finalgrade IS NOT NULL
                   AND (gg.finalgrade / NULLIF(gi.grademax, 0) * 100) < :threshold
                   AND u.deleted = 0
                   AND u.suspended = 0
                   {$coursefilter}";

        return $DB->get_records_sql($sql, $params);
    }

    protected function check_completion_below(\stdClass $rule): array {
        global $DB;

        $params       = [];
        $coursefilter  = '';

        if ((int)$rule->courseid > 0) {
            $coursefilter        = 'AND e.courseid = :courseid';
            $params['courseid'] = (int)$rule->courseid;
        }

        $cohortjoin = '';
        if ((int)$rule->cohortid > 0) {
            $cohortjoin         = 'JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid';
            $params['cohortid'] = (int)$rule->cohortid;
        }

        $sql = "SELECT u.id AS userid,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       u.email,
                       e.courseid,
                       c.fullname AS coursename
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {course} c ON c.id = e.courseid
                  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                  {$cohortjoin}
                 WHERE ue.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                   {$coursefilter}";

        return $DB->get_records_sql($sql, $params);
    }

    protected function check_attempts_zero(\stdClass $rule): array {
        global $DB;

        $params       = [];
        $coursefilter  = '';

        if ((int)$rule->courseid > 0) {
            $coursefilter        = 'AND e.courseid = :courseid';
            $params['courseid'] = (int)$rule->courseid;
        }

        $cohortjoin = '';
        if ((int)$rule->cohortid > 0) {
            $cohortjoin         = 'JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid';
            $params['cohortid'] = (int)$rule->cohortid;
        }

        $sql = "SELECT u.id AS userid,
                       " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname,
                       u.email,
                       e.courseid,
                       c.fullname AS coursename
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {course} c ON c.id = e.courseid
                  {$cohortjoin}
                 WHERE ue.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   {$coursefilter}
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {quiz_attempts} qa
                         JOIN {quiz} q ON q.id = qa.quiz
                        WHERE qa.userid = u.id
                          AND q.course = e.courseid
                   )";

        return $DB->get_records_sql($sql, $params);
    }

    protected function send_notifications(\stdClass $rule, array $matches): void {
        global $DB, $CFG;

        $noreplyuser = \core_user::get_noreply_user();

        $recipients = [];
        if (!empty($rule->notify_emails)) {
            foreach (explode(',', $rule->notify_emails) as $addr) {
                $addr = trim($addr);
                if ($addr !== '' && validate_email($addr)) {
                    $recipients[] = $addr;
                }
            }
        }

        if (empty($recipients) && !(int)$rule->notify_teachers) {
            mtrace('  -> no recipients configured for this rule.');
            return;
        }

        $conditionlabel = $this->condition_label($rule);

        $subject = '[' . get_string('pluginname', 'local_leducon') . '] ' .
                   get_string('alertrules_title', 'local_leducon') . ': ' . $rule->name;

        $bodylines   = [];
        $bodylines[] = get_string('pluginname', 'local_leducon') . ' — Alert: ' . $rule->name;
        $bodylines[] = 'Condition: ' . $conditionlabel;
        $bodylines[] = 'Students matching (' . count($matches) . '):';
        $bodylines[] = str_repeat('-', 60);

        foreach ($matches as $student) {
            $bodylines[] = sprintf(
                '  %s (%s) — Course: %s',
                $student->fullname,
                $student->email,
                $student->coursename ?? 'N/A'
            );
        }

        $bodylines[] = '';
        $bodylines[] = 'Generated at ' . userdate(time()) . '.';

        $body = implode("\n", $bodylines);

        foreach ($recipients as $emailaddress) {
            $touser             = new \stdClass();
            $touser->id         = -1;
            $touser->email      = $emailaddress;
            $touser->firstname  = '';
            $touser->lastname   = '';
            $touser->username   = $emailaddress;
            $touser->auth       = 'manual';
            $touser->confirmed  = 1;
            $touser->deleted    = 0;
            $touser->suspended  = 0;
            $touser->mnethostid = $CFG->mnet_localhost_id ?? 1;
            $touser->emailstop  = 0;
            $touser->lang       = $CFG->lang ?? 'en';
            $touser->timezone   = $CFG->timezone ?? '99';
            $touser->mailformat = 1;

            $result = email_to_user($touser, $noreplyuser, $subject, $body, nl2br(htmlspecialchars($body, ENT_QUOTES)));
            mtrace("  -> email to {$emailaddress}: " . ($result ? 'ok' : 'failed'));
        }
    }

    protected function condition_label(\stdClass $rule): string {
        switch ($rule->condition_type) {
            case 'inactive_days':
                return get_string('condition_inactive_days', 'local_leducon', (int)$rule->threshold);
            case 'grade_below':
                return get_string('condition_grade_below', 'local_leducon', (float)$rule->threshold);
            case 'completion_below':
                return get_string('condition_completion_below', 'local_leducon', (float)$rule->threshold);
            case 'attempts_zero':
                return get_string('condition_attempts_zero', 'local_leducon');
            default:
                return $rule->condition_type;
        }
    }
}
