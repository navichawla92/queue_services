## Purpose

Controls who can sign in and what each person can see and do. Roles and permissions are scoped to a tenant and, where relevant, to specific locations. Sensitive actions are audited.

## ADDED Requirements

### Requirement: User authentication
The system SHALL authenticate staff users with email and password. It SHALL support password reset by email, lock out an account temporarily after repeated failed attempts, and expire idle sessions after a configurable timeout.

#### Scenario: Successful sign-in
- **WHEN** an active user submits valid credentials
- **THEN** the user is signed in and taken to the default screen for their role

#### Scenario: Repeated failed sign-in
- **WHEN** a user submits wrong credentials 5 times within 15 minutes
- **THEN** further attempts for that account are blocked for 15 minutes

#### Scenario: Deactivated user
- **WHEN** a deactivated user tries to sign in
- **THEN** sign-in is refused and any existing sessions for that user are invalidated

### Requirement: Built-in roles
The system SHALL provide these roles: Platform Admin (cross-tenant), Company Admin, Location Manager, Receptionist and Employee. Each role SHALL grant a defined set of permissions:
- Company Admin: full control of their tenant.
- Location Manager: manage setup, queue, signage and reports for assigned locations.
- Receptionist: check in customers and manage the queue for assigned locations.
- Employee: serve customers from the queue for assigned locations and view their own stats.

#### Scenario: Employee attempts admin action
- **WHEN** a user with only the Employee role requests the service management page
- **THEN** the system responds with "forbidden"

#### Scenario: Location Manager limited to assigned locations
- **WHEN** a Location Manager assigned to location X requests the queue for location Y of the same tenant
- **THEN** the system responds with "forbidden"

### Requirement: Location-scoped role assignment
Company Admins SHALL be able to assign roles to users for all locations or for a specific set of locations. Permission checks MUST take both the role and the location scope into account.

#### Scenario: Multi-location receptionist
- **WHEN** a receptionist is assigned to locations X and Y
- **THEN** the receptionist can switch between and act on the queues of X and Y only

### Requirement: Platform admin separation
Platform Admins SHALL manage tenants and plans. Access to a tenant's operational data (e.g. for support) MUST be recorded in the audit log with the admin's identity and reason.

#### Scenario: Platform admin impersonation
- **WHEN** a platform admin opens tenant A's dashboard for support
- **THEN** an audit entry records the admin, tenant, time and stated reason, and a visible banner indicates the support session

### Requirement: Audit logging
The system SHALL record an audit entry for security-relevant and operational actions: sign-in and sign-out, role changes, setup changes, ticket lifecycle actions, note edits, customer data export or deletion, and settings changes. Each entry SHALL capture the actor, tenant, location, action, target, time and before/after values where applicable. Audit entries MUST NOT be editable through the application.

#### Scenario: Ticket transfer audited
- **WHEN** an employee transfers ticket A-012 to another department
- **THEN** an audit entry records the employee, the ticket, the source and target departments, and the time

### Requirement: Device credentials for kiosks and displays
Kiosks and lobby displays SHALL authenticate as devices using a pairing code, not a staff login. Device credentials MUST be limited to their bound location and function, and MUST be revocable by admins.

#### Scenario: Revoked device
- **WHEN** an admin revokes a paired lobby display
- **THEN** the display stops receiving data within 60 seconds and shows the pairing screen
