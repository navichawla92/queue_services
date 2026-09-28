## Purpose

Prepares the platform for commercial SaaS by modeling subscription plans with feature flags and limits and by metering each tenant's usage. Billing can then be added later without restructuring.

## ADDED Requirements

### Requirement: Plans with features and limits
Platform Admins SHALL be able to define plans. Each plan specifies feature flags (e.g. appointments, digital signage, feedback, white-label, advanced analytics, API access) and numeric limits (e.g. max locations, max staff users, max displays, monthly SMS allowance, media storage). Each tenant MUST be assigned exactly one plan. The initial internal tenant SHALL be on an unlimited internal plan.

#### Scenario: Assign plan
- **WHEN** a platform admin assigns the "Standard" plan to tenant A
- **THEN** tenant A's available features and limits reflect the Standard plan immediately

### Requirement: Feature gating
The system SHALL hide or disable features not included in a tenant's plan across the UI and enforce this server-side. Attempts to use a gated feature MUST be refused with an upgrade message.

#### Scenario: Signage not in plan
- **WHEN** a tenant without the signage feature opens the signage section
- **THEN** the section shows an "available on higher plans" message and the signage API endpoints refuse requests

### Requirement: Limit enforcement
The system SHALL prevent creating resources beyond plan limits (locations, staff users, displays). For metered limits such as SMS it SHALL apply the plan's policy: either hard-stop sending or allow overage and flag it. Tenants MUST be warned at 80% and 100% of any limit.

#### Scenario: Location limit reached
- **WHEN** a tenant with a 3-location limit tries to create a fourth location
- **THEN** creation is refused with a message explaining the limit

#### Scenario: SMS allowance warning
- **WHEN** a tenant has used 80% of its monthly SMS allowance
- **THEN** its company admins see a warning banner and receive an email

### Requirement: Usage metering
The system SHALL record per-tenant usage per billing period: SMS segments sent, active locations, active staff users, paired displays, tickets created, appointments booked and media storage used. Usage SHALL be viewable by the tenant's company admins and by platform admins, and MUST be available as exportable per-period summaries for future invoicing.

#### Scenario: View usage
- **WHEN** a company admin opens the usage page
- **THEN** current-period usage for each metered item is shown against the plan's limits

### Requirement: Platform administration console
Platform Admins SHALL have a console to list tenants with their plan, status and headline usage, and to create tenants, suspend or reactivate them, change their plan, and start an audited support session.

#### Scenario: Provision a tenant
- **WHEN** a platform admin creates tenant "Acme" with the Standard plan and an initial company admin email
- **THEN** the tenant is created with default settings and the company admin receives an invitation email
