## Purpose

Decides where each customer goes: which department's queue, which employees may serve them, in what order, and roughly how long they will wait. The decision is based on the requested service, configured rules and live staff availability.

## ADDED Requirements

### Requirement: Department routing
When a ticket is created, the system SHALL assign it to a department. The department comes from the first matching routing rule for the location, or else from the service's default department. Routing rules SHALL be able to match on service, customer type (walk-in or appointment), day of week and time of day.

#### Scenario: Default department
- **WHEN** a customer selects "Account Opening", whose default department is "New Accounts", and no rule matches
- **THEN** the ticket is placed in the "New Accounts" queue

#### Scenario: Time-based rule
- **WHEN** a rule routes "Loans" to "General Services" after 4 PM on Fridays and a customer checks in for Loans at 4:30 PM on a Friday
- **THEN** the ticket is placed in "General Services"

### Requirement: Eligible employees
The system SHALL determine the eligible employees for a ticket: those assigned to the ticket's location and department and skilled in its service. When an employee uses "call next", they MUST only receive tickets they are eligible for, unless a manager or receptionist explicitly assigns the ticket to them.

#### Scenario: Call next skips ineligible tickets
- **WHEN** an employee skilled only in "Deposits" presses "call next" and the oldest waiting ticket is for "Loans"
- **THEN** the employee is given the oldest waiting "Deposits" ticket instead

### Requirement: Queue ordering
Within a department, waiting tickets SHALL be ordered by priority, then by the time they entered the queue. Appointment check-ins SHALL get a configurable priority boost when they arrive within their appointment window. Held tickets MUST be excluded from "call next" until they are released.

#### Scenario: On-time appointment prioritized
- **WHEN** an appointment customer checks in within their window and appointment priority is enabled
- **THEN** their ticket is ordered ahead of walk-ins who checked in earlier

#### Scenario: Directly assigned ticket
- **WHEN** a ticket is assigned to a specific employee
- **THEN** it appears at the top of that employee's personal queue and is not offered to other employees via "call next"

### Requirement: Estimated wait time
The system SHALL compute an estimated wait for each waiting ticket from its position in the queue, the number of eligible Available/Busy employees, and the recent average service time for the service at that location, falling back to the service's configured expected duration when there is too little data. The estimate SHALL be recomputed whenever the queue changes and shown rounded to a friendly range (e.g. "about 10–15 min").

#### Scenario: Estimate shown at check-in
- **WHEN** a customer checks in with 4 people ahead, 2 eligible employees working, and an average service time of 8 minutes
- **THEN** the displayed estimate is approximately 16 minutes, shown as a range

#### Scenario: No eligible staff working
- **WHEN** no eligible employee is Available or Busy
- **THEN** the estimate shows "wait time unavailable" rather than a number

### Requirement: Unroutable service
If no department or eligible employee exists for the selected service at the location, the system SHALL NOT issue a ticket from self-service surfaces. It SHALL tell the customer that the service is not available right now, while receptionists MAY still create the ticket manually.

#### Scenario: No staff configured for service
- **WHEN** a kiosk customer selects a service no employee at the location is skilled in
- **THEN** the kiosk shows that the service is currently unavailable and suggests seeing the receptionist
