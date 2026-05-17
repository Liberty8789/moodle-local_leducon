@local @local_leducon
Feature: Leducon analytics reports
  As a manager
  I can view reports with insights
  So that I understand learning outcomes

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Manager views course completion report
    Given I log in as "admin"
    When I am on the "local_leducon > analytics > report" page with "reporttype=course_completion"
    Then I should see "Course Completion"
    And ".ld-report-table" "css_element" should exist

  @javascript
  Scenario: Manager views enrollment report
    Given I log in as "admin"
    When I am on the "local_leducon > analytics > report" page with "reporttype=enrollment"
    Then I should see "Enrolment"
    And ".ld-report-table" "css_element" should exist

  @javascript
  Scenario: Manager views report with insights strip
    Given I log in as "admin"
    When I am on the "local_leducon > analytics > report" page with "reporttype=course_completion"
    Then ".ld-insights-strip" "css_element" should exist

  @javascript
  Scenario: Redirected standalone pages still work
    Given I log in as "admin"
    When I am on the "local_leducon > analytics > atrisk" page
    Then I should see "At-Risk"
    And the url should contain "report.php"
