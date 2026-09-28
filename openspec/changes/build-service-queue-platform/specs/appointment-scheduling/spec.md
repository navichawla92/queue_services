## Purpose

Lets customers book, reschedule and cancel appointments online against real employee and location availability. Appointments are merged into the same live queue and dashboard as walk-ins.

## ADDED Requirements

### Requirement: Employee availability
Admins and employees (for themselves, if permitted) SHALL be able to define recurring weekly working hours per employee per location, plus date-specific time off. Bookable slots MUST fall within the intersection of location hours, department hours (if set) and the employee's working hours, minus time off and existing appointments.

#### Scenario: Time off blocks slots
- **WHEN** an employee has time off on Tuesday afternoon
- **THEN** no slots with that employee are offered on Tuesday afternoon

### Requirement: Location capacity for appointments
Admins SHALL be able to limit the share of staff capacity reserved for appointments per location and time block. Walk-in capacity is then protected, and slots beyond the limit MUST NOT be offered.

#### Scenario: Appointment cap reached
- **WHEN** a location limits appointments to 2 concurrent per hour and 2 are booked for 10–11 AM
- **THEN** no further 10–11 AM slots are offered at that location

### Requirement: Online booking
Customers SHALL be able to book online without an account through a public, tenant-branded booking page. They choose a location, a service, optionally a specific employee (if enabled), and a date and time slot, then enter their name and phone number (email optional) and give SMS consent. Slot duration MUST derive from the service's expected duration plus a configurable buffer. Booking MUST be protected against double-booking, so two customers cannot book the same employee slot.

#### Scenario: Successful booking
- **WHEN** a customer selects Loans at location X on Friday 10:00 AM and submits valid details
- **THEN** an appointment is created in Booked status, a confirmation SMS with a confirmation code and manage link is sent, and the slot is no longer offered

#### Scenario: Concurrent booking of last slot
- **WHEN** two customers submit the same last available slot at the same time
- **THEN** one booking succeeds and the other is told the slot was just taken and shown alternatives

### Requirement: Booking window rules
Admins SHALL configure a minimum lead time (e.g. 2 hours ahead) and a maximum horizon (e.g. 60 days ahead) per location. The booking page MUST only offer slots within the window.

#### Scenario: Too-soon slot hidden
- **WHEN** the minimum lead time is 2 hours and it is 9:15 AM
- **THEN** the earliest slot offered is 11:15 AM or later

### Requirement: Staff booking
Receptionists and managers SHALL be able to create, edit, reschedule and cancel appointments for customers from the staff console. They MAY override availability with a warning.

#### Scenario: Staff override
- **WHEN** a manager books an appointment outside an employee's working hours
- **THEN** the system warns about the conflict and saves the appointment once the manager confirms

### Requirement: Reschedule and cancel
Customers SHALL be able to reschedule or cancel through the unique manage link in their confirmation or reminder, subject to a configurable cutoff (e.g. not within 1 hour of the start). Rescheduling MUST release the original slot. Each change MUST send the corresponding SMS.

#### Scenario: Customer cancels
- **WHEN** a customer cancels a Friday 10 AM appointment via the manage link 1 day ahead
- **THEN** the appointment becomes Cancelled, the slot becomes available again and a cancellation SMS is sent

#### Scenario: Past cutoff
- **WHEN** a customer attempts to reschedule 30 minutes before the start and the cutoff is 1 hour
- **THEN** the page tells them to call the location instead

### Requirement: Reminders
The system SHALL send appointment reminders by SMS at configurable offsets before the start (default 24 hours and 2 hours). Each reminder includes manage and check-in links. A reminder MUST NOT be sent for a cancelled or rescheduled-away appointment.

#### Scenario: 24-hour reminder
- **WHEN** an appointment starts at 10 AM tomorrow
- **THEN** a reminder SMS is sent at about 10 AM today, respecting quiet hours

### Requirement: Appointment statuses and no-show tracking
Appointments SHALL move through the statuses Booked, Confirmed (optional), Arrived, In Service, Completed, Cancelled and No-Show. An appointment not checked in within a configurable grace period after its start SHALL be marked No-Show automatically. No-show counts SHALL be tracked per customer, and admins MAY restrict online booking for customers above a no-show threshold.

#### Scenario: Automatic no-show
- **WHEN** a 2:00 PM appointment has not checked in by 2:15 PM with a 15-minute grace period
- **THEN** it is marked No-Show and the customer's no-show count increments

### Requirement: Unified operational view
The staff dashboard SHALL show today's upcoming appointments for the location, labeled by time and employee, next to the live queue. Arrived appointments MUST appear in the same queue as walk-ins, clearly labeled as appointments.

#### Scenario: Upcoming appointment visible
- **WHEN** a receptionist views the dashboard at 1:30 PM
- **THEN** appointments starting in the next hours are listed with their status, and any that have arrived appear in the live queue marked "Appt"
