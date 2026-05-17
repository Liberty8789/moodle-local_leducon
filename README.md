# LedUcon Learning Platform

A comprehensive Moodle local plugin that adds enterprise-grade analytics, gamification, organisational management, and reporting capabilities to any Moodle LMS installation.

**Plugin type:** `local`
**Component:** `local_leducon`
**Current version:** 2.1.0
**Moodle compatibility:** 3.9 - 5.2
**License:** [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html)
**Copyright:** 2024 Liberty Education Resources

---

## Features

### Analytics & Reporting

LedUcon ships with **21 built-in report types**, all accessible from a unified report viewer with a grouped sidebar, date/course/category/cohort filters, KPI summary cards, dynamic insights, charts, and full export support.

| Report | Description |
|--------|-------------|
| Course Completion | Completion rates across all courses with trend charts |
| Enrolment | Enrolment counts, methods, and growth over time |
| Grade Analytics | Grade distributions and averages by course |
| Time Spent | Time-on-task analysis per course and user |
| Badge | Badge issuance tracking and earning rates |
| Certificate | Certificate generation and download activity |
| Compliance | Mandatory course completion tracking with deadlines |
| Instructor | Teaching load and activity per instructor |
| Category | Course category performance overview |
| Login Activity | Login frequency, patterns, and inactive users |
| User Activity | Per-user engagement metrics and last access |
| Assignment | Submission rates, on-time vs late, grading backlog |
| Quiz Analytics | Attempt counts, pass rates, score distributions |
| SCORM Analytics | SCORM package completion and score tracking |
| Forum | Discussion and reply activity per forum |
| ILT/VILT | Face-to-face and virtual session tracking with costs |
| At-Risk Students | Identifies students at risk of falling behind |
| Teacher View | Per-course student progress for teachers |
| Manager View | Team member progress with at-risk badges |
| Skills Coverage | Skills/competency mapping across the organisation |
| ROI Analyst | Per-course return on investment analysis |

#### Custom Report Builder

Build ad-hoc reports from any Moodle datasource with:
- Drag-and-drop column selection
- Configurable conditions (equals, contains, greater than, etc.)
- Scope filters: cohort, department, institution, organisation unit, country, city, and custom profile fields
- Save, load, and share report configurations
- Full export to CSV, Excel, and PDF

#### Standalone Analytics Pages

- **Dashboard** — KPI cards with quick-glance metrics
- **Overview** — Site-wide completion, enrolment, and grade summaries
- **Heatmap** — 7x24 hour/day-of-week activity grid
- **Leaderboard** — Ranked student lists by course, cohort, or department with privacy controls
- **My Report** — Personal report card with course progress, quiz results, login history, and SCORM activity
- **Compare** — Period-vs-period delta analysis
- **ROI** — Executive KPI tab with cost-per-completion metrics
- **Org Report** — Organisational hierarchy drill-down analytics

#### Export Formats

All reports support export to:
- **CSV** — Raw data for spreadsheet analysis
- **Excel** (.xlsx) — Formatted workbook export
- **PDF** — Print-ready report cards with charts and branding

### Gamification Engine

A full gamification system to drive learner engagement:

- **XP Engine** — Award experience points for course completions, quiz scores, logins, forum participation, and badges earned. Configurable point values per activity type.
- **Levels** — Define level thresholds with custom names, icons, and XP requirements. Users level up automatically as they earn XP.
- **Streaks** — Track consecutive-day login streaks with bonus XP multipliers. Visual streak counters on the dashboard.
- **Achievements** — Configurable achievement badges triggered by milestones (e.g., "Complete 5 courses", "7-day streak").
- **Learning Paths** — Curate sequences of courses into structured learning journeys with progress tracking.
- **Campaigns** — Time-limited gamification events with leaderboards and bonus multipliers.
- **Rewards & Redemption** — Points-based reward catalogue where learners can redeem XP for rewards.
- **Recognition** — Peer-to-peer recognition system for kudos and shout-outs.
- **Certificates** — Auto-generated PDF certificates for course and path completion.
- **Leaderboard** — Gamification leaderboard with filtering by course, cohort, or department.

### Organisation Management

- **Org Structure** — Define hierarchical organisation units (departments, divisions, teams)
- **Team Assignment** — Assign users to organisation units with manager roles
- **Manager Dashboard** — Team overview with member progress, at-risk alerts, and spotlight features
- **Org Reports** — Drill-down analytics by organisation hierarchy

### Notifications & Automation

- **Insight Alerts** — Automated notifications when report insights detect anomalies (high dropout rates, grading backlogs, etc.)
- **At-Risk Notifications** — Email alerts to teachers when students are flagged as at-risk
- **Compliance Reminders** — Automated reminders for mandatory course deadlines
- **Weekly Digest** — Scheduled weekly summary emails to managers
- **Nudges** — Engagement nudges for inactive learners
- **Alert Rules** — Admin-configurable alert thresholds and notification channels

### Scheduled Tasks

| Task | Description |
|------|-------------|
| `alert_checker` | Evaluates alert rules and sends notifications |
| `campaign_check` | Processes active gamification campaigns |
| `compliance_reminder` | Sends deadline reminders for mandatory courses |
| `data_retention` | Cleans up expired data per retention policy |
| `email_reports` | Sends scheduled report emails |
| `insight_notifier` | Processes and distributes insight alerts |
| `precompute_reports` | Pre-computes report data for performance |
| `process_streaks` | Updates login streak tracking |
| `report_scheduler` | Manages scheduled report generation |
| `send_nudges` | Sends engagement nudges to inactive users |
| `weekly_digest` | Generates and sends weekly digest emails |

### Web Services API

External service endpoints for integration:

| Endpoint | Description |
|----------|-------------|
| `local_leducon_get_kpi_aggregates` | Retrieve aggregated KPI data |
| `local_leducon_get_report_data` | Fetch report data programmatically |
| `local_leducon_get_report_summary` | Get report summary/KPI cards |
| `local_leducon_get_report_insights` | Retrieve dynamic insights for a report |

### Privacy & Compliance

- Full **Moodle Privacy API** implementation (GDPR compliant)
- Data export and deletion support for all user data
- Configurable data retention policies
- Role-based access control with granular capabilities

---

## Requirements

- **Moodle** 3.9 or later (tested up to 5.2)
- **PHP** 7.2 or later
- **Database:** MySQL/MariaDB or PostgreSQL (uses Moodle's database abstraction layer)

---

## Installation

### Method 1: Upload via Moodle Admin

1. Download `local_leducon.zip` from the [Releases](https://github.com/Liberty8789/moodle-local_leducon/releases) page
2. Go to **Site administration > Plugins > Install plugins**
3. Upload the zip file and follow the prompts
4. Complete the database upgrade when prompted

### Method 2: Manual Installation

1. Clone or download this repository
2. Copy the `leducon` folder to `{moodle_root}/local/leducon`
3. Visit **Site administration > Notifications** to trigger the database upgrade

### Method 3: Git

```bash
cd /path/to/moodle/local
git clone https://github.com/Liberty8789/moodle-local_leducon.git leducon
```

Then visit **Site administration > Notifications** to complete installation.

---

## Configuration

After installation, configure the plugin at:

**Site administration > Plugins > Local plugins > Leducon Learning Platform**

Key settings include:
- Gamification point values and level thresholds
- Alert rule thresholds (at-risk criteria, compliance deadlines)
- Report scheduling and email preferences
- Data retention periods
- Organisation structure setup

---

## Capabilities

The plugin defines the following capabilities for fine-grained access control:

| Capability | Description |
|------------|-------------|
| `local/leducon:viewanalytics` | View analytics and reports |
| `local/leducon:managegamification` | Manage gamification settings |
| `local/leducon:manageorg` | Manage organisation structure |
| `local/leducon:viewteam` | View team/manager dashboard |
| `local/leducon:managecustomreports` | Create and manage custom reports |

---

## File Structure

```
leducon/
├── admin/                  # Admin management pages
│   ├── alertrules.php      # Alert rule configuration
│   ├── campaigns.php       # Gamification campaigns
│   ├── levels.php          # Level definitions
│   ├── org_structure.php   # Organisation hierarchy
│   ├── paths.php           # Learning paths
│   ├── recognitions.php    # Recognition management
│   ├── rewards.php         # Reward catalogue
│   ├── skills_manage.php   # Skills/competency management
│   └── team_assign.php     # Team assignments
├── ajax/                   # AJAX endpoints
│   └── org_ajax.php        # Organisation tree operations
├── analytics/              # Analytics & report pages
│   ├── atrisk.php          # At-risk students
│   ├── compare.php         # Period comparison
│   ├── custom_reports.php  # Custom report builder
│   ├── custom_report_view.php # Custom report viewer
│   ├── export.php          # Data export handler
│   ├── exportpdf.php       # PDF report card generator
│   ├── exportreport.php    # Report export (CSV/Excel/PDF)
│   ├── heatmap.php         # Activity heatmap
│   ├── iltupload.php       # ILT data import
│   ├── leaderboard.php     # Student leaderboards
│   ├── managerview.php     # Manager dashboard
│   ├── myreport.php        # Personal report card
│   ├── org_report.php      # Organisation reports
│   ├── overview.php        # Site overview dashboard
│   ├── report.php          # Unified report viewer
│   ├── roi.php             # ROI analytics
│   ├── roi_costs.php       # Cost management
│   ├── skillsreport.php    # Skills coverage
│   └── teacherview.php     # Teacher dashboard
├── classes/
│   ├── event/              # Event observers
│   ├── external/           # Web service endpoints
│   ├── gamify/             # Gamification engine
│   │   ├── achievement_manager.php
│   │   ├── campaign_manager.php
│   │   ├── certificate_generator.php
│   │   ├── level_manager.php
│   │   ├── path_manager.php
│   │   ├── streak_tracker.php
│   │   └── xp_engine.php
│   ├── org/                # Organisation management
│   ├── privacy/            # Privacy API provider
│   ├── reports/            # Report class framework
│   │   ├── base_report.php # Abstract base class
│   │   └── ...             # 21 report implementations
│   └── task/               # Scheduled tasks
├── db/                     # Database definitions
│   ├── access.php          # Capabilities
│   ├── caches.php          # Cache definitions
│   ├── events.php          # Event observers
│   ├── install.php         # Install routines
│   ├── install.xml         # Table schemas (XMLDB)
│   ├── messages.php        # Message providers
│   ├── services.php        # Web services
│   ├── tasks.php           # Scheduled tasks
│   └── upgrade.php         # Upgrade routines
├── gamify/                 # Gamification user pages
├── lang/en/                # English language strings
├── manager/                # Manager pages
├── tests/                  # PHPUnit and Behat tests
├── index.php               # Plugin entry point
├── lib.php                 # Core library functions
├── settings.php            # Admin settings
├── styles.css              # Plugin styles
└── version.php             # Version info
```

---

## Testing

### PHPUnit

```bash
vendor/bin/phpunit --testsuite local_leducon_testsuite
```

### Behat

```bash
vendor/bin/behat --config /path/to/behat/behat.yml --tags @local_leducon
```

---

## Changelog

### v2.1.0 (2026-05-17)
- Added 21 report types with unified report viewer
- Added custom report builder with scope filters (cohort, department, org unit, profile fields)
- Added gamification engine (XP, levels, streaks, achievements, paths, campaigns)
- Added organisation hierarchy management
- Added at-risk student detection with email notifications
- Added ILT/VILT session management and analytics
- Added SCORM, quiz, assignment, and forum analytics
- Fixed status inconsistency across all reports where grade could show 100% while status showed "In Progress"
- Fixed cross-version compatibility (Moodle 3.9 - 5.2) for message providers
- Added defensive table existence checks for all module-dependent reports
- Privacy API compliance (GDPR)
- Web services API for external integration

---

## Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/Liberty8789/moodle-local_leducon/issues) page.

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the [GNU General Public License](https://www.gnu.org/copyleft/gpl.html) for more details.
