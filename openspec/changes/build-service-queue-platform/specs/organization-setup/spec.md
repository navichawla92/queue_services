## Purpose

Lets administrators model how each company operates: its locations, departments, services, staff, desks/rooms, skills and hours. Check-in, routing, scheduling and reporting all build on this model.

## ADDED Requirements

### Requirement: Manage locations
Company Admins SHALL be able to create, edit, deactivate and reactivate locations. Each location has a name, address, time zone, contact phone, public check-in identifier and active status. A deactivated location MUST NOT accept check-ins or bookings, and its historical data MUST remain available in reports.

#### Scenario: Deactivate location
- **WHEN** an admin deactivates location X
- **THEN** X's kiosk, QR check-in and booking pages show "not currently accepting customers", and past tickets at X remain in reports

### Requirement: Manage departments
Admins SHALL be able to create, edit, reorder and deactivate departments within a location. Each department has a name, a ticket prefix (e.g. "A") and a display color. Ticket prefixes MUST be unique within a location.

#### Scenario: Duplicate prefix rejected
- **WHEN** an admin creates a department with prefix "A" at a location where "A" is already used
- **THEN** the system rejects the save with a validation error

### Requirement: Manage services
Admins SHALL be able to create, edit, reorder and deactivate services. Each service has a name, description, default department, expected duration, availability for walk-in and/or appointment, the locations offering it, and whether customers can see it at check-in.

#### Scenario: Service hidden from kiosk
- **WHEN** a service is marked not customer-selectable
- **THEN** it does not appear on the kiosk, mobile check-in or booking pages but staff can still assign it

### Requirement: Manage employees
Admins SHALL be able to create, edit and deactivate employees. For each employee they set a user account, display name (shown on the lobby TV), locations, departments, skills/services they can perform, and default desk/room.

#### Scenario: Employee assigned services
- **WHEN** an admin assigns services "Account Opening" and "Loans" to an employee
- **THEN** the employee becomes eligible for routing of tickets for those services at their locations

### Requirement: Manage desks and rooms
Admins SHALL be able to create desks/rooms per location. Each desk or room has a display label (e.g. "Desk 3", "Room B") and an optional department. Employees SHALL choose or be defaulted to a desk when they start their shift. The chosen desk is shown on the lobby display when they call a customer.

#### Scenario: Employee changes desk
- **WHEN** an employee switches from Desk 3 to Desk 5 during their shift
- **THEN** subsequent calls announce Desk 5 on the lobby display

### Requirement: Operating hours and closures
Admins SHALL define weekly operating hours per location, optionally narrower hours per department, and date-specific closures or special hours (holidays). Walk-in check-in MUST be refused outside open hours, with an optional grace window before closing that is configurable per location.

#### Scenario: Check-in after last-walk-in cutoff
- **WHEN** a customer tries to check in 10 minutes before closing at a location with a 15-minute cutoff
- **THEN** check-in is refused with a message showing the next opening time

#### Scenario: Holiday closure
- **WHEN** a closure is defined for a date
- **THEN** no walk-in check-ins or appointment slots are offered for that location on that date

### Requirement: Employee availability status
Employees SHALL be able to set their live status to Available, Busy (serving), On Break or Offline. Only Available employees MUST be offered new tickets by routing.

#### Scenario: Employee goes on break
- **WHEN** an employee sets status to On Break
- **THEN** routing stops assigning them new tickets and their status is visible to managers
