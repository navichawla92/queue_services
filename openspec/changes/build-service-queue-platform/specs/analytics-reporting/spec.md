## Purpose

Gives managers accurate operational KPIs and filterable reports within and across locations, including a centralized multi-location dashboard, so they can make staffing and service-quality decisions.

## ADDED Requirements

### Requirement: KPI definitions
The system SHALL compute these KPIs with consistent definitions:
- **Customers served**: tickets Completed.
- **Average wait time**: from the first entry into the queue to the first call, excluding hold time.
- **Average service time**: from service start to completion.
- **Served per employee, department and location.**
- **Peak hours**: check-ins per hour of day and day of week.
- **No-show rate**: no-shows ÷ (completed + no-shows), shown separately for tickets and for appointments.
- **Abandoned queue rate**: tickets cancelled by the customer or closed unserved, ÷ all tickets.
- **Appointments vs walk-ins**: counts and share.
- **Daily, weekly and monthly volume.**
- **Customer satisfaction**: average rating and response rate.

KPI definitions MUST be documented in the UI (e.g. info tooltips).

#### Scenario: Wait time excludes hold
- **WHEN** a ticket waited 10 minutes, was held 5 minutes, then waited 3 more minutes before being called
- **THEN** its wait time counts as 13 minutes

### Requirement: Report filters
All reports SHALL be filterable by date range, location, department, service and employee, and filters MUST combine. Date ranges MUST be interpreted in each location's local time zone. Users MUST only see data for locations within their access scope.

#### Scenario: Combined filters
- **WHEN** a manager filters by location X, department Loans and the last 30 days
- **THEN** every KPI and chart reflects only Loans tickets at X in that period

#### Scenario: Scope restriction
- **WHEN** a Location Manager for X opens reports
- **THEN** only location X appears as a filter option and in results

### Requirement: Location operations dashboard
Each location SHALL have a live dashboard for managers. It shows the current waiting count, longest current wait, average wait today, served today, staff status (available/busy/break/offline) and SLA breaches (tickets waiting longer than a configurable threshold).

#### Scenario: SLA breach highlighted
- **WHEN** a ticket has waited longer than the 20-minute threshold
- **THEN** it is highlighted on the location dashboard

### Requirement: Multi-location management dashboard
Company Admins and multi-location managers SHALL have a centralized dashboard that compares all their locations. It shows live queue status per location and period KPIs side by side, and lets them drill down into any location.

#### Scenario: Compare locations
- **WHEN** a company admin opens the management dashboard for this week
- **THEN** each location's served count, average wait, average service time, no-show rate and satisfaction are listed side by side with live waiting counts

### Requirement: Charts and trends
Reports SHALL include trend charts over time for volume, wait time and satisfaction, a heatmap of peak hours, and breakdowns by employee, department, service and location.

#### Scenario: Peak hours heatmap
- **WHEN** a manager views the peak hours report for a quarter
- **THEN** a day-of-week by hour-of-day heatmap of check-ins is shown

### Requirement: Export
Users with reporting permission SHALL be able to export any report's underlying rows and aggregated results to CSV, respecting the applied filters and their access scope. Exports MUST be recorded in the audit log.

#### Scenario: CSV export
- **WHEN** a manager exports the employee performance report
- **THEN** a CSV with one row per employee and the filtered KPIs is downloaded and the export is audited

### Requirement: Report performance
Standard reports over up to 12 months of data for one tenant SHALL load within 5 seconds under normal load. Historical figures MUST NOT change when setup data is later renamed or deactivated; for example, a renamed department's history stays attributed to it.

#### Scenario: Deactivated employee history
- **WHEN** an employee is deactivated
- **THEN** their historical served counts and ratings remain in reports for past periods
