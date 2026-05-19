<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom report engine — dynamically builds SQL from a JSON definition.
 *
 * Data sources define available tables, joins, and columns.
 * The admin picks columns, adds conditions, and the engine builds safe
 * parameterised queries. No raw SQL input is ever accepted from users.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_report extends base_report {

    /** @var \stdClass|null  The saved report record from local_leducon_custom_rpts. */
    protected $definition = null;

    /** @var array  Decoded JSON config. */
    protected $config = [];

    /** @var string  Data source key. */
    protected $datasource = 'completions';

    public function __construct(\stdClass $definition = null) {
        if ($definition) {
            $this->definition = $definition;
            $this->datasource = $definition->datasource ?? 'completions';
            $this->config = json_decode($definition->config ?? '{}', true) ?: [];
        }
    }

    public function get_name(): string {
        return $this->definition->name ?? get_string('customreport_title', 'local_leducon');
    }

    // ======================================================================
    // DATA SOURCE DEFINITIONS
    // ======================================================================

    /**
     * Returns all available data sources with their metadata.
     */
    public static function get_datasources(): array {
        return [
            'completions' => [
                'label' => get_string('cr_ds_completions', 'local_leducon'),
                'description' => get_string('cr_ds_completions_desc', 'local_leducon'),
            ],
            'grades' => [
                'label' => get_string('cr_ds_grades', 'local_leducon'),
                'description' => get_string('cr_ds_grades_desc', 'local_leducon'),
            ],
            'enrollments' => [
                'label' => get_string('cr_ds_enrollments', 'local_leducon'),
                'description' => get_string('cr_ds_enrollments_desc', 'local_leducon'),
            ],
            'quiz_attempts' => [
                'label' => get_string('cr_ds_quiz', 'local_leducon'),
                'description' => get_string('cr_ds_quiz_desc', 'local_leducon'),
            ],
            'logins' => [
                'label' => get_string('cr_ds_logins', 'local_leducon'),
                'description' => get_string('cr_ds_logins_desc', 'local_leducon'),
            ],
            'assignments' => [
                'label' => get_string('cr_ds_assignments', 'local_leducon'),
                'description' => get_string('cr_ds_assignments_desc', 'local_leducon'),
            ],
            'users' => [
                'label' => get_string('cr_ds_users', 'local_leducon'),
                'description' => get_string('cr_ds_users_desc', 'local_leducon'),
            ],
        ];
    }

    /**
     * Returns available columns for a data source.
     * Each column: key => [label, sql_expression, type (text|number|date)].
     */
    public static function get_datasource_columns(string $ds): array {
        global $DB;

        // Moodle-portable fullname expression (works on MySQL, PostgreSQL, MariaDB).
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

        $cols = [];
        switch ($ds) {
            case 'completions':
                $cols = [
                    'fullname'       => ['label' => 'Full Name',       'sql' => $fullname,            'type' => 'text'],
                    'email'          => ['label' => 'Email',           'sql' => 'u.email',            'type' => 'text'],
                    'username'       => ['label' => 'Username',        'sql' => 'u.username',          'type' => 'text'],
                    'coursename'     => ['label' => 'Course',          'sql' => 'c.fullname',          'type' => 'text'],
                    'category'       => ['label' => 'Category',        'sql' => 'cc.name',             'type' => 'text'],
                    'timecompleted'  => ['label' => 'Date Completed',  'sql' => 'comp.timecompleted',  'type' => 'date'],
                ];
                break;

            case 'grades':
                $cols = [
                    'fullname'     => ['label' => 'Full Name',   'sql' => $fullname,         'type' => 'text'],
                    'email'        => ['label' => 'Email',       'sql' => 'u.email',         'type' => 'text'],
                    'coursename'   => ['label' => 'Course',      'sql' => 'c.fullname',       'type' => 'text'],
                    'finalgrade'   => ['label' => 'Final Grade', 'sql' => 'gg.finalgrade',    'type' => 'number'],
                    'grademax'     => ['label' => 'Max Grade',   'sql' => 'gi.grademax',      'type' => 'number'],
                    'gradepct'     => ['label' => 'Grade %',     'sql' => 'CASE WHEN gi.grademax > 0 THEN ROUND(gg.finalgrade / gi.grademax * 100, 1) ELSE 0 END', 'type' => 'number'],
                    'timemodified' => ['label' => 'Date Graded', 'sql' => 'gg.timemodified',  'type' => 'date'],
                ];
                break;

            case 'enrollments':
                $cols = [
                    'fullname'      => ['label' => 'Full Name',     'sql' => $fullname,            'type' => 'text'],
                    'email'         => ['label' => 'Email',         'sql' => 'u.email',            'type' => 'text'],
                    'coursename'    => ['label' => 'Course',        'sql' => 'c.fullname',          'type' => 'text'],
                    'category'      => ['label' => 'Category',      'sql' => 'cc.name',             'type' => 'text'],
                    'enrolmethod'   => ['label' => 'Enrol Method',  'sql' => 'e.enrol',             'type' => 'text'],
                    'timeenrolled'  => ['label' => 'Date Enrolled', 'sql' => 'ue.timecreated',      'type' => 'date'],
                    'status'        => ['label' => 'Status',        'sql' => "CASE WHEN ue.status = 0 THEN 'Active' ELSE 'Suspended' END", 'type' => 'text'],
                ];
                break;

            case 'quiz_attempts':
                $cols = [
                    'fullname'    => ['label' => 'Full Name',     'sql' => $fullname,          'type' => 'text'],
                    'email'       => ['label' => 'Email',         'sql' => 'u.email',          'type' => 'text'],
                    'quizname'    => ['label' => 'Quiz',          'sql' => 'q.name',            'type' => 'text'],
                    'coursename'  => ['label' => 'Course',        'sql' => 'c.fullname',        'type' => 'text'],
                    'attempt'     => ['label' => 'Attempt #',     'sql' => 'qa.attempt',        'type' => 'number'],
                    'sumgrades'   => ['label' => 'Score',         'sql' => 'qa.sumgrades',      'type' => 'number'],
                    'state'       => ['label' => 'State',         'sql' => 'qa.state',          'type' => 'text'],
                    'timefinish'  => ['label' => 'Date Finished', 'sql' => 'qa.timefinish',     'type' => 'date'],
                ];
                break;

            case 'logins':
                $cols = [
                    'fullname'     => ['label' => 'Full Name',  'sql' => $fullname,             'type' => 'text'],
                    'email'        => ['label' => 'Email',      'sql' => 'u.email',             'type' => 'text'],
                    'timecreated'  => ['label' => 'Login Time', 'sql' => 'l.timecreated',       'type' => 'date'],
                    'ip'           => ['label' => 'IP Address', 'sql' => 'l.ip',                'type' => 'text'],
                ];
                break;

            case 'assignments':
                $cols = [
                    'fullname'       => ['label' => 'Full Name',     'sql' => $fullname,            'type' => 'text'],
                    'email'          => ['label' => 'Email',         'sql' => 'u.email',            'type' => 'text'],
                    'assignname'     => ['label' => 'Assignment',    'sql' => 'a.name',              'type' => 'text'],
                    'coursename'     => ['label' => 'Course',        'sql' => 'c.fullname',          'type' => 'text'],
                    'status'         => ['label' => 'Status',        'sql' => 'asub.status',         'type' => 'text'],
                    'timesubmitted'  => ['label' => 'Submitted',     'sql' => 'asub.timemodified',   'type' => 'date'],
                    'grade'          => ['label' => 'Grade',         'sql' => 'ag.grade',            'type' => 'number'],
                ];
                break;

            case 'users':
                $cols = [
                    'fullname'      => ['label' => 'Full Name',    'sql' => $fullname,          'type' => 'text'],
                    'email'         => ['label' => 'Email',        'sql' => 'u.email',          'type' => 'text'],
                    'username'      => ['label' => 'Username',     'sql' => 'u.username',        'type' => 'text'],
                    'department'    => ['label' => 'Department',   'sql' => 'u.department',      'type' => 'text'],
                    'institution'   => ['label' => 'Institution',  'sql' => 'u.institution',     'type' => 'text'],
                    'lastaccess'    => ['label' => 'Last Access',  'sql' => 'u.lastaccess',      'type' => 'date'],
                    'timecreated'   => ['label' => 'Created',      'sql' => 'u.timecreated',     'type' => 'date'],
                    'suspended'     => ['label' => 'Status',       'sql' => "CASE WHEN u.suspended = 0 THEN 'Active' ELSE 'Suspended' END", 'type' => 'text'],
                ];
                break;
        }
        return $cols;
    }

    /**
     * Returns available condition operators.
     */
    public static function get_operators(): array {
        return [
            'eq'       => '= (equals)',
            'neq'      => '!= (not equal)',
            'gt'       => '> (greater than)',
            'gte'      => '>= (at least)',
            'lt'       => '< (less than)',
            'lte'      => '<= (at most)',
            'contains' => 'contains',
            'starts'   => 'starts with',
            'empty'    => 'is empty',
            'notempty' => 'is not empty',
        ];
    }

    // ======================================================================
    // QUERY BUILDER
    // ======================================================================

    /**
     * Build the FROM + JOIN clause for a data source.
     */
    protected function build_from(): string {
        $base = '';
        switch ($this->datasource) {
            case 'completions':
                $base = "{course_completions} comp
                    JOIN {user} u ON u.id = comp.userid AND u.deleted = 0
                    JOIN {course} c ON c.id = comp.course
                    JOIN {course_categories} cc ON cc.id = c.category";
                break;

            case 'grades':
                $base = "{grade_grades} gg
                    JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemtype = 'course'
                    JOIN {user} u ON u.id = gg.userid AND u.deleted = 0
                    JOIN {course} c ON c.id = gi.courseid";
                break;

            case 'enrollments':
                $base = "{user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                    JOIN {course} c ON c.id = e.courseid
                    JOIN {course_categories} cc ON cc.id = c.category";
                break;

            case 'quiz_attempts':
                $base = "{quiz_attempts} qa
                    JOIN {quiz} q ON q.id = qa.quiz
                    JOIN {course} c ON c.id = q.course
                    JOIN {user} u ON u.id = qa.userid AND u.deleted = 0";
                break;

            case 'logins':
                $base = "{logstore_standard_log} l
                    JOIN {user} u ON u.id = l.userid AND u.deleted = 0";
                break;

            case 'assignments':
                $base = "{assign_submission} asub
                    JOIN {assign} a ON a.id = asub.assignment
                    JOIN {course} c ON c.id = a.course
                    JOIN {user} u ON u.id = asub.userid AND u.deleted = 0
               LEFT JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.userid = u.id AND ag.attemptnumber = asub.attemptnumber";
                break;

            case 'users':
            default:
                $base = "{user} u";
                break;
        }

        return $base;
    }

    /**
     * Returns base WHERE clause for the data source.
     */
    protected function build_base_where(): array {
        switch ($this->datasource) {
            case 'completions':
                return ["comp.timecompleted IS NOT NULL", []];
            case 'grades':
                return ["gg.finalgrade IS NOT NULL", []];
            case 'logins':
                return ["l.action = 'loggedin'", []];
            case 'users':
                return ["u.deleted = 0 AND u.id > 1", []];
            default:
                return ["1 = 1", []];
        }
    }

    /**
     * Build scope filter SQL (cohort, department, org unit, profile fields).
     * Returns [$wheres_array, $params_array].
     */
    protected function build_scope_filters(): array {
        global $DB;

        $scope = $this->config['scope'] ?? [];
        $wheres = [];
        $params = [];

        $cohortid = (int) ($scope['cohortid'] ?? 0);
        if ($cohortid > 0) {
            $wheres[] = "u.id IN (SELECT userid FROM {cohort_members} WHERE cohortid = :scope_cohortid)";
            $params['scope_cohortid'] = $cohortid;
        }

        $department = trim($scope['department'] ?? '');
        if ($department !== '') {
            $wheres[] = $DB->sql_like('u.department', ':scope_dept', false);
            $params['scope_dept'] = '%' . $DB->sql_like_escape($department) . '%';
        }

        $institution = trim($scope['institution'] ?? '');
        if ($institution !== '') {
            $wheres[] = $DB->sql_like('u.institution', ':scope_inst', false);
            $params['scope_inst'] = '%' . $DB->sql_like_escape($institution) . '%';
        }

        $city = trim($scope['city'] ?? '');
        if ($city !== '') {
            $wheres[] = $DB->sql_like('u.city', ':scope_city', false);
            $params['scope_city'] = '%' . $DB->sql_like_escape($city) . '%';
        }

        $country = trim($scope['country'] ?? '');
        if ($country !== '') {
            $wheres[] = "u.country = :scope_country";
            $params['scope_country'] = $country;
        }

        $orgunitid = (int) ($scope['orgunitid'] ?? 0);
        if ($orgunitid > 0) {
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('local_leducon_org_members')) {
                $wheres[] = "u.id IN (SELECT userid FROM {local_leducon_org_members} WHERE orgunitid = :scope_orgid)";
                $params['scope_orgid'] = $orgunitid;
            }
        }

        // Custom user profile fields.
        $profilefields = $scope['profilefields'] ?? [];
        $pfi = 0;
        foreach ($profilefields as $pf) {
            $fieldid = (int) ($pf['fieldid'] ?? 0);
            $pfval = trim($pf['value'] ?? '');
            if ($fieldid > 0 && $pfval !== '') {
                $alias = 'spf' . $pfi;
                $pname = 'scope_pf' . $pfi;
                $wheres[] = "u.id IN (SELECT userid FROM {user_info_data} {$alias} WHERE {$alias}.fieldid = :{$pname}_fid AND " .
                    $DB->sql_like("{$alias}.data", ":{$pname}_val", false) . ")";
                $params["{$pname}_fid"] = $fieldid;
                $params["{$pname}_val"] = '%' . $DB->sql_like_escape($pfval) . '%';
                $pfi++;
            }
        }

        return [$wheres, $params];
    }

    /**
     * Returns available scope filter options for the UI.
     */
    public static function get_scope_options(): array {
        global $DB;

        $options = [];

        // Cohorts.
        $cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name', 0, 200);
        $options['cohorts'] = [];
        foreach ($cohorts as $c) {
            $options['cohorts'][$c->id] = format_string($c->name);
        }

        // Departments (distinct values from user table).
        $depts = $DB->get_records_sql(
            "SELECT DISTINCT department FROM {user} WHERE department <> '' AND deleted = 0 ORDER BY department ASC",
            null, 0, 100
        );
        $options['departments'] = [];
        foreach ($depts as $d) {
            $options['departments'][] = $d->department;
        }

        // Institutions.
        $insts = $DB->get_records_sql(
            "SELECT DISTINCT institution FROM {user} WHERE institution <> '' AND deleted = 0 ORDER BY institution ASC",
            null, 0, 100
        );
        $options['institutions'] = [];
        foreach ($insts as $i) {
            $options['institutions'][] = $i->institution;
        }

        // Org units (if table exists).
        $options['orgunits'] = [];
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_leducon_org_units')) {
            $orgunits = $DB->get_records('local_leducon_org_units', ['enabled' => 1], 'name ASC', 'id, name', 0, 200);
            foreach ($orgunits as $ou) {
                $options['orgunits'][$ou->id] = format_string($ou->name);
            }
        }

        // Custom profile fields.
        $options['profilefields'] = [];
        $fields = $DB->get_records('user_info_field', null, 'sortorder ASC', 'id, name, shortname');
        foreach ($fields as $f) {
            $options['profilefields'][$f->id] = format_string($f->name);
        }

        // Countries.
        $options['countries'] = get_string_manager()->get_list_of_countries();

        return $options;
    }

    /**
     * Build WHERE conditions from the config.
     */
    protected function build_conditions(): array {
        global $DB;

        $conditions = $this->config['conditions'] ?? [];
        $allcols = self::get_datasource_columns($this->datasource);
        $wheres = [];
        $params = [];
        $pi = 0;

        foreach ($conditions as $cond) {
            $field = $cond['field'] ?? '';
            $op = $cond['operator'] ?? 'eq';
            $val = $cond['value'] ?? '';

            if (!isset($allcols[$field])) {
                continue;
            }

            $sqlexpr = $allcols[$field]['sql'];
            $coltype = $allcols[$field]['type'];
            $pname = 'cp' . $pi++;

            // Safe date conversion — returns 0 (epoch) if strtotime fails.
            $resolve = function ($v, $type) {
                if ($type !== 'date') {
                    return $v;
                }
                $ts = strtotime($v);
                return ($ts !== false) ? $ts : 0;
            };

            switch ($op) {
                case 'eq':
                    $wheres[] = "$sqlexpr = :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'neq':
                    $wheres[] = "$sqlexpr != :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'gt':
                    $wheres[] = "$sqlexpr > :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'gte':
                    $wheres[] = "$sqlexpr >= :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'lt':
                    $wheres[] = "$sqlexpr < :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'lte':
                    $wheres[] = "$sqlexpr <= :{$pname}";
                    $params[$pname] = $resolve($val, $coltype);
                    break;
                case 'contains':
                    $wheres[] = $DB->sql_like($sqlexpr, ":{$pname}", false);
                    $params[$pname] = '%' . $DB->sql_like_escape($val) . '%';
                    break;
                case 'starts':
                    $wheres[] = $DB->sql_like($sqlexpr, ":{$pname}", false);
                    $params[$pname] = $DB->sql_like_escape($val) . '%';
                    break;
                case 'empty':
                    $wheres[] = "($sqlexpr IS NULL OR $sqlexpr = '')";
                    break;
                case 'notempty':
                    $wheres[] = "($sqlexpr IS NOT NULL AND $sqlexpr != '')";
                    break;
            }
        }
        return [$wheres, $params];
    }

    // ======================================================================
    // base_report INTERFACE
    // ======================================================================

    public function get_columns(): array {
        $selected = $this->config['columns'] ?? [];
        $allcols = self::get_datasource_columns($this->datasource);
        $result = [];
        foreach ($selected as $key) {
            if (isset($allcols[$key])) {
                $result[$key] = [
                    'label'    => $allcols[$key]['label'],
                    'sortable' => true,
                ];
            }
        }
        return $result;
    }

    public function get_data(): array {
        global $DB;

        $selected = $this->config['columns'] ?? [];
        $allcols = self::get_datasource_columns($this->datasource);
        $maxrows = (int) get_config('local_leducon', 'maxexportrows') ?: 5000;

        if (empty($selected)) {
            return [];
        }

        // Build SELECT.
        $selectparts = [];
        foreach ($selected as $key) {
            if (isset($allcols[$key])) {
                $selectparts[] = $allcols[$key]['sql'] . ' AS ' . $key;
            }
        }
        if (empty($selectparts)) {
            return [];
        }

        $selectsql = implode(', ', $selectparts);
        $fromsql = $this->build_from();
        [$basewhere, $baseparams] = $this->build_base_where();
        [$condwheres, $condparams] = $this->build_conditions();
        [$scopewheres, $scopeparams] = $this->build_scope_filters();

        $where = $basewhere;
        if (!empty($condwheres)) {
            $where .= ' AND ' . implode(' AND ', $condwheres);
        }
        if (!empty($scopewheres)) {
            $where .= ' AND ' . implode(' AND ', $scopewheres);
        }
        $params = array_merge($baseparams, $condparams, $scopeparams);

        // Order by.
        $orderby = '';
        $ordercol = $this->config['orderby'] ?? '';
        $orderdir = strtoupper($this->config['orderdir'] ?? 'ASC');
        if ($ordercol && isset($allcols[$ordercol])) {
            $dir = in_array($orderdir, ['ASC', 'DESC']) ? $orderdir : 'ASC';
            $orderby = ' ORDER BY ' . $allcols[$ordercol]['sql'] . ' ' . $dir;
        }

        $sql = "SELECT {$selectsql} FROM {$fromsql} WHERE {$where}{$orderby}";

        try {
            $records = $DB->get_records_sql($sql, $params, 0, $maxrows);
        } catch (\dml_exception $e) {
            debugging('Custom report SQL error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw $e;
        }

        // Format output.
        $data = [];
        foreach ($records as $rec) {
            $row = [];
            foreach ($selected as $key) {
                $val = $rec->$key ?? '';
                if (isset($allcols[$key]) && $allcols[$key]['type'] === 'date' && !empty($val) && is_numeric($val)) {
                    $val = userdate((int)$val, get_string('strftimedatetimeshort', 'langconfig'));
                } elseif (isset($allcols[$key]) && $allcols[$key]['type'] === 'number' && is_numeric($val)) {
                    $val = number_format((float)$val, 2);
                }
                $row[$key] = $val;
            }
            $data[] = $row;
        }
        return $data;
    }

    // ======================================================================
    // CRUD HELPERS
    // ======================================================================

    /**
     * Save a new custom report.
     */
    public static function create(string $name, string $description, string $datasource,
                                   array $config, int $userid, bool $shared = false): int {
        global $DB;
        $record = new \stdClass();
        $record->name = $name;
        $record->description = $description;
        $record->datasource = $datasource;
        $record->config = json_encode($config);
        $record->shared = $shared ? 1 : 0;
        $record->createdby = $userid;
        $record->timecreated = time();
        $record->timemodified = time();
        return $DB->insert_record('local_leducon_custom_rpts', $record);
    }

    /**
     * Update an existing custom report.
     */
    public static function update(int $id, string $name, string $description, string $datasource,
                                   array $config, bool $shared = false): void {
        global $DB;
        $record = new \stdClass();
        $record->id = $id;
        $record->name = $name;
        $record->description = $description;
        $record->datasource = $datasource;
        $record->config = json_encode($config);
        $record->shared = $shared ? 1 : 0;
        $record->timemodified = time();
        $DB->update_record('local_leducon_custom_rpts', $record);
    }

    /**
     * Delete a custom report and its schedules.
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records('local_leducon_rpt_schedule', ['reportid' => $id]);
        $DB->delete_records('local_leducon_custom_rpts', ['id' => $id]);
    }

    /**
     * Get all reports visible to a user (their own + shared).
     */
    public static function get_user_reports(int $userid): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT * FROM {local_leducon_custom_rpts}
              WHERE createdby = :uid OR shared = 1
              ORDER BY name ASC",
            ['uid' => $userid]
        );
    }

    /**
     * Returns pre-built report templates.
     */
    public static function get_templates(): array {
        return [
            'monthly_completion' => [
                'name'        => get_string('tpl_monthly_completion', 'local_leducon'),
                'description' => get_string('tpl_monthly_completion_desc', 'local_leducon'),
                'datasource'  => 'completions',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'coursename', 'category', 'timecompleted'],
                    'conditions' => [],
                    'scope'    => [],
                    'orderby'  => 'timecompleted',
                    'orderdir' => 'DESC',
                ],
            ],
            'grade_summary' => [
                'name'        => get_string('tpl_grade_summary', 'local_leducon'),
                'description' => get_string('tpl_grade_summary_desc', 'local_leducon'),
                'datasource'  => 'grades',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'coursename', 'finalgrade', 'grademax', 'gradepct'],
                    'conditions' => [
                        ['field' => 'gradepct', 'operator' => 'gt', 'value' => '0'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'gradepct',
                    'orderdir' => 'DESC',
                ],
            ],
            'low_performers' => [
                'name'        => get_string('tpl_low_performers', 'local_leducon'),
                'description' => get_string('tpl_low_performers_desc', 'local_leducon'),
                'datasource'  => 'grades',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'coursename', 'gradepct', 'timemodified'],
                    'conditions' => [
                        ['field' => 'gradepct', 'operator' => 'lt', 'value' => '50'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'gradepct',
                    'orderdir' => 'ASC',
                ],
            ],
            'new_enrolments' => [
                'name'        => get_string('tpl_new_enrolments', 'local_leducon'),
                'description' => get_string('tpl_new_enrolments_desc', 'local_leducon'),
                'datasource'  => 'enrollments',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'coursename', 'category', 'enrolmethod', 'timeenrolled', 'status'],
                    'conditions' => [],
                    'scope'    => [],
                    'orderby'  => 'timeenrolled',
                    'orderdir' => 'DESC',
                ],
            ],
            'quiz_performance' => [
                'name'        => get_string('tpl_quiz_performance', 'local_leducon'),
                'description' => get_string('tpl_quiz_performance_desc', 'local_leducon'),
                'datasource'  => 'quiz_attempts',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'quizname', 'coursename', 'attempt', 'sumgrades', 'state', 'timefinish'],
                    'conditions' => [
                        ['field' => 'state', 'operator' => 'eq', 'value' => 'finished'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'timefinish',
                    'orderdir' => 'DESC',
                ],
            ],
            'failed_quiz_attempts' => [
                'name'        => get_string('tpl_failed_quiz', 'local_leducon'),
                'description' => get_string('tpl_failed_quiz_desc', 'local_leducon'),
                'datasource'  => 'quiz_attempts',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'quizname', 'coursename', 'attempt', 'sumgrades', 'timefinish'],
                    'conditions' => [
                        ['field' => 'sumgrades', 'operator' => 'lt', 'value' => '50'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'sumgrades',
                    'orderdir' => 'ASC',
                ],
            ],
            'overdue_assignments' => [
                'name'        => get_string('tpl_overdue_assignments', 'local_leducon'),
                'description' => get_string('tpl_overdue_assignments_desc', 'local_leducon'),
                'datasource'  => 'assignments',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'assignname', 'coursename', 'status', 'timesubmitted', 'grade'],
                    'conditions' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'new'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'timesubmitted',
                    'orderdir' => 'ASC',
                ],
            ],
            'login_activity' => [
                'name'        => get_string('tpl_login_activity', 'local_leducon'),
                'description' => get_string('tpl_login_activity_desc', 'local_leducon'),
                'datasource'  => 'logins',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'timecreated', 'ip'],
                    'conditions' => [],
                    'scope'    => [],
                    'orderby'  => 'timecreated',
                    'orderdir' => 'DESC',
                ],
            ],
            'inactive_users' => [
                'name'        => get_string('tpl_inactive_users', 'local_leducon'),
                'description' => get_string('tpl_inactive_users_desc', 'local_leducon'),
                'datasource'  => 'users',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'department', 'institution', 'lastaccess', 'suspended'],
                    'conditions' => [],
                    'scope'    => [],
                    'orderby'  => 'lastaccess',
                    'orderdir' => 'ASC',
                ],
            ],
            'department_roster' => [
                'name'        => get_string('tpl_department_roster', 'local_leducon'),
                'description' => get_string('tpl_department_roster_desc', 'local_leducon'),
                'datasource'  => 'users',
                'config'      => [
                    'columns'  => ['fullname', 'email', 'username', 'department', 'institution', 'lastaccess', 'timecreated'],
                    'conditions' => [
                        ['field' => 'suspended', 'operator' => 'eq', 'value' => 'Active'],
                    ],
                    'scope'    => [],
                    'orderby'  => 'fullname',
                    'orderdir' => 'ASC',
                ],
            ],
        ];
    }

    /**
     * Create a report from a template for a given user.
     */
    public static function create_from_template(string $templatekey, int $userid): int {
        $templates = self::get_templates();
        if (!isset($templates[$templatekey])) {
            throw new \coding_exception('Unknown template: ' . $templatekey);
        }
        $tpl = $templates[$templatekey];
        return self::create($tpl['name'], $tpl['description'], $tpl['datasource'], $tpl['config'], $userid, false);
    }

    /**
     * Export report data as CSV string.
     */
    public function export_csv(): string {
        $columns = $this->get_columns();
        $data = $this->get_data();

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM so Excel auto-detects encoding.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array_map(function($c) { return $c['label']; }, $columns));
        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
}
