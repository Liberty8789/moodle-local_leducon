<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/leducon/lib.php');

/**
 * PHPUnit tests for the report framework.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_leducon\reports\base_report
 * @covers    \local_leducon\reports\course_completion_report
 * @covers    \local_leducon\reports\enrollment_report
 */
class reports_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    public function test_report_factory_returns_valid_classes(): void {
        $types = \local_leducon_get_report_types();
        $this->assertIsArray($types);
        $this->assertNotEmpty($types);

        foreach ($types as $key => $label) {
            $report = \local_leducon_get_report($key);
            $this->assertNotNull($report, "Report factory returned null for type: {$key}");
            $this->assertInstanceOf(\local_leducon\reports\base_report::class, $report);
        }
    }

    public function test_report_factory_throws_for_invalid(): void {
        $this->expectException(\coding_exception::class);
        \local_leducon_get_report('nonexistent_report_type');
    }

    public function test_base_report_default_methods(): void {
        $report = \local_leducon_get_report('course_completion');
        $this->assertNotNull($report);

        $this->assertIsString($report->get_name());
        $this->assertNotEmpty($report->get_name());

        $columns = $report->get_columns();
        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);

        foreach ($columns as $key => $def) {
            $this->assertArrayHasKey('label', $def);
            $this->assertArrayHasKey('sortable', $def);
        }

        $this->assertIsString($report->get_required_capability());
        $this->assertIsArray($report->get_filter_options());
        $this->assertIsArray($report->get_actions());
    }

    public function test_get_data_returns_array(): void {
        $report = \local_leducon_get_report('enrollment');
        $this->assertNotNull($report);

        $data = $report->get_data();
        $this->assertIsArray($data);
    }

    public function test_get_insights_returns_valid_structure(): void {
        $report = \local_leducon_get_report('course_completion');
        $this->assertNotNull($report);

        $insights = $report->get_insights([]);
        $this->assertIsArray($insights);
        $this->assertEmpty($insights);

        $fakedata = [
            [
                'coursename' => 'Test Course',
                'category' => 'Test Cat',
                'enrolled' => 100,
                'completed' => 80,
                'inprogress' => 10,
                'notstarted' => 10,
                'completionrate' => '80.0%',
            ],
        ];

        $insights = $report->get_insights($fakedata);
        $this->assertIsArray($insights);

        foreach ($insights as $insight) {
            $this->assertArrayHasKey('icon', $insight);
            $this->assertArrayHasKey('type', $insight);
            $this->assertArrayHasKey('title', $insight);
            $this->assertArrayHasKey('detail', $insight);
            $this->assertContains($insight['type'], ['success', 'warning', 'danger', 'info']);
        }
    }

    public function test_get_summary_returns_valid_structure(): void {
        $report = \local_leducon_get_report('course_completion');
        $this->assertNotNull($report);

        $summary = $report->get_summary();
        $this->assertIsArray($summary);

        foreach ($summary as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('value', $item);
        }
    }

    public function test_enrollment_report_with_enrolled_users(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);

        $report = \local_leducon_get_report('enrollment');
        $report->set_filters([
            'from'   => time() - 86400,
            'to'     => time() + 86400,
        ]);

        $data = $report->get_data();
        $this->assertNotEmpty($data);

        $found = false;
        foreach ($data as $row) {
            if ((int)$row['totalenrolled'] > 0) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected at least one course with enrolments');
    }

    public function test_course_completion_insights_high_rate(): void {
        $report = \local_leducon_get_report('course_completion');

        $fakedata = [];
        for ($i = 0; $i < 5; $i++) {
            $fakedata[] = [
                'coursename' => 'Course ' . $i,
                'category' => 'Cat',
                'enrolled' => 50,
                'completed' => 40,
                'inprogress' => 5,
                'notstarted' => 5,
                'completionrate' => '80.0%',
            ];
        }

        $insights = $report->get_insights($fakedata);
        $types = array_column($insights, 'type');
        $this->assertContains('success', $types, 'Expected a success insight for high completion rate');
    }

    public function test_course_completion_insights_low_rate(): void {
        $report = \local_leducon_get_report('course_completion');

        $fakedata = [];
        for ($i = 0; $i < 5; $i++) {
            $fakedata[] = [
                'coursename' => 'Course ' . $i,
                'category' => 'Cat',
                'enrolled' => 50,
                'completed' => 5,
                'inprogress' => 5,
                'notstarted' => 40,
                'completionrate' => '10.0%',
            ];
        }

        $insights = $report->get_insights($fakedata);
        $types = array_column($insights, 'type');
        $this->assertContains('warning', $types, 'Expected a warning insight for low completion rate');
    }

    public function test_all_reports_have_get_insights(): void {
        $types = \local_leducon_get_report_types();
        foreach ($types as $key => $label) {
            $report = \local_leducon_get_report($key);
            if (!$report) {
                continue;
            }
            $this->assertTrue(
                method_exists($report, 'get_insights'),
                "Report {$key} is missing get_insights method"
            );
            $result = $report->get_insights([]);
            $this->assertIsArray($result, "get_insights for {$key} should return array");
        }
    }
}
