<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\reports;

/**
 * Abstract base class for all Leducon analytics reports.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_report {

    /** @var array  Raw filter values set by the caller. */
    protected $filters = [];

    abstract public function get_name(): string;
    abstract public function get_columns(): array;
    abstract public function get_data(): array;

    public function set_filters(array $filters) {
        $this->filters = $filters;
        return $this;
    }

    protected function filter(string $key, $default = null) {
        return $this->filters[$key] ?? $default;
    }

    protected function default_range_seconds(): int {
        $days = (int) get_config('local_leducon', 'defaultperiod') ?: 30;
        return $days * DAYSECS;
    }

    protected function resolve_from(): int {
        $from = $this->filter('datefrom', null);
        // A value of 0 means "all time (from epoch)" — only fall back to
        // default range when the filter was never set (null).
        if ($from !== null) {
            return (int) $from;
        }
        return time() - $this->default_range_seconds();
    }

    protected function resolve_to(): int {
        $to = (int) $this->filter('dateto', 0);
        return $to > 0 ? $to : time();
    }

    protected function date_where(string $column): array {
        return [
            "$column >= :datefrom AND $column <= :dateto",
            ['datefrom' => $this->resolve_from(), 'dateto' => $this->resolve_to()],
        ];
    }

    protected function include_inactive(): bool {
        return (bool) get_config('local_leducon', 'includeinactive');
    }

    protected function user_active_sql(string $alias = 'u'): string {
        if ($this->include_inactive()) {
            return '';
        }
        return "AND {$alias}.deleted = 0 AND {$alias}.suspended = 0";
    }

    protected function fmt_pct($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2);
    }

    protected function fmt_date($ts): string {
        if (empty($ts)) {
            return '-';
        }
        return userdate((int) $ts, get_string('strftimedatetimeshort', 'langconfig'));
    }

    protected function threshold(string $key, $default = 0) {
        $val = get_config('local_leducon', $key);
        return $val !== false && $val !== '' ? (float) $val : (float) $default;
    }

    public function get_summary(): array {
        return [];
    }

    public function get_chart_html(array $data = []): ?string {
        return null;
    }

    public function get_trend_data(): array {
        return [];
    }

    /**
     * Generate dynamic insights based on the report data.
     *
     * Each insight is an associative array:
     *   'icon'    => emoji or icon class
     *   'type'    => 'success' | 'warning' | 'danger' | 'info'
     *   'title'   => short headline (e.g. "Completion rate improving")
     *   'detail'  => one-sentence explanation
     *
     * @param array $data The report data (from get_data()).
     * @return array
     */
    public function get_insights(array $data): array {
        return [];
    }

    /**
     * @return array Action buttons rendered below the table.
     *               Each entry: ['key' => string, 'label' => string, 'class' => string].
     */
    public function get_actions(): array {
        return [];
    }

    /**
     * @param string $key   Action key from get_actions().
     * @param array  $data  POST data (filters, sesskey already verified).
     * @return string Flash message to display after redirect.
     */
    public function execute_action($key, array $data) {
        return '';
    }

    /**
     * @return string Capability required to view this report.
     */
    public function get_required_capability(): string {
        return 'local/leducon:viewanalytics';
    }

    /**
     * @return array Which filter dropdowns to show.
     *               Keys: show_course, show_category, show_cohort, show_org.
     */
    public function get_filter_options(): array {
        return [
            'show_course'   => true,
            'show_category' => true,
            'show_cohort'   => true,
            'show_org'      => true,
        ];
    }

    protected function kpi_color(float $pct, string $type = 'completion'): string {
        return local_leducon_kpi_color($pct, $type);
    }

    /**
     * Get org-unit filter SQL based on current filters.
     *
     * Usage in a report:
     *   list($orgsql, $orgparams) = $this->org_filter('u');
     *   $sql .= $orgsql;
     *   $params = array_merge($params, $orgparams);
     *
     * @param string $useralias User table alias (default 'u').
     * @return array [$sql, $params]
     */
    protected function org_filter(string $useralias = 'u'): array {
        $orgunitid = (int) $this->filter('orgunitid', 0);
        if ($orgunitid <= 0) {
            return ['', []];
        }

        return \local_leducon\org\org_helper::get_org_filter_sql($orgunitid, $useralias);
    }
}
