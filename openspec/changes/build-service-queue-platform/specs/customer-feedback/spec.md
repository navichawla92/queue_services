## Purpose

Captures customer satisfaction after each completed service so management can track service quality by employee, department, location and time.

## ADDED Requirements

### Requirement: Feedback request after service
When feedback is enabled for a location, the system SHALL send a feedback request SMS after a ticket is completed, following a configurable delay (default 10 minutes). The SMS contains a unique, single-use feedback link. It MUST be sent only if the customer consented to SMS and at most once per visit.

#### Scenario: Feedback request sent
- **WHEN** a consenting customer's ticket is completed at a location with feedback enabled
- **THEN** a feedback SMS with a unique link is sent after the configured delay

#### Scenario: Frequency cap
- **WHEN** the same customer completes a second visit within the tenant's feedback cooldown (default 7 days)
- **THEN** no second feedback request is sent

### Requirement: Feedback form
The feedback link SHALL open a tenant-branded, mobile-friendly page. It asks for an overall rating from 1–5 (required) and an optional comment, and MAY include up to 3 additional tenant-defined rating questions. The link MUST expire after a configurable period (default 7 days) and after one submission.

#### Scenario: Submit feedback
- **WHEN** a customer selects 4 stars and writes a comment
- **THEN** the response is stored, linked to the ticket's employee, service, department and location, and a thank-you message is shown

#### Scenario: Link already used
- **WHEN** a customer opens a feedback link that has already been submitted
- **THEN** the page shows "Thanks, we've already received your feedback"

### Requirement: Low-score alerts
Tenants SHALL be able to configure an alert threshold (e.g. rating ≤ 2). When a response falls at or below the threshold, the location's managers MUST be notified in the app and optionally by email.

#### Scenario: Poor rating alert
- **WHEN** a customer submits a rating of 1 at location X
- **THEN** location X's managers see an alert with the ticket, employee and comment

### Requirement: Feedback review
Managers SHALL be able to list and filter feedback responses by employee, department, service, location, rating and date range, and view the associated visit. Employees MAY view their own feedback if the tenant allows it.

#### Scenario: Filter by employee
- **WHEN** a manager filters feedback to employee Maria for last month
- **THEN** only Maria's responses from last month are listed with the average rating
