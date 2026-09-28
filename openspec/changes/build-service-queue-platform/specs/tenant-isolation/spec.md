## Purpose

Defines companies (tenants) as the top-level owner of all data. Each company's information stays strictly isolated from every other company, and each company can apply its own branding. This lets the platform run for one company today and many companies as a SaaS product later.

## ADDED Requirements

### Requirement: Every business record belongs to exactly one tenant
The system SHALL associate every business record with exactly one tenant: locations, departments, services, employees, desks, customers, tickets, appointments, notifications, feedback, signage content, displays, settings and audit entries. The system MUST reject creation of a business record without a tenant.

#### Scenario: Record created within a tenant context
- **WHEN** an authenticated user of tenant A creates a service
- **THEN** the service is stored as belonging to tenant A

#### Scenario: Record created without tenant context
- **WHEN** a business record is created outside any tenant context (e.g. from a background job that did not set a tenant)
- **THEN** the system rejects the write with an error and persists nothing

### Requirement: Data access is scoped to the current tenant
The system SHALL resolve a current tenant for every request, job, scheduled task, WebSocket channel and webhook. It SHALL return, modify or broadcast only data belonging to that tenant. Access to another tenant's record MUST behave as if the record does not exist.

#### Scenario: Cross-tenant read attempt
- **WHEN** a user of tenant A requests a ticket ID that belongs to tenant B
- **THEN** the system responds with "not found" and reveals no data from tenant B

#### Scenario: Cross-tenant real-time subscription attempt
- **WHEN** a client authenticated for tenant A attempts to subscribe to a real-time channel for a location of tenant B
- **THEN** the subscription is denied

#### Scenario: Background job runs in tenant context
- **WHEN** a queued SMS job for tenant B executes
- **THEN** it reads only tenant B's templates, settings and customer data

### Requirement: Tenant resolution for public surfaces
The system SHALL resolve the tenant for unauthenticated public surfaces (kiosk, QR/mobile check-in, online booking, lobby display, feedback page) from an unguessable public identifier in the URL or from the paired device. It SHALL NOT resolve the tenant from any value the visitor can freely change.

#### Scenario: QR check-in link resolves tenant and location
- **WHEN** a customer opens a location's check-in QR URL
- **THEN** the check-in page shows only that location's tenant branding and services

#### Scenario: Unknown public identifier
- **WHEN** a visitor opens a public URL with an unknown or revoked identifier
- **THEN** the system shows a generic "not found" page

### Requirement: Tenant lifecycle
Platform administrators SHALL be able to create, suspend and reactivate tenants. While a tenant is suspended, its users MUST NOT be able to sign in and its public surfaces MUST show an "unavailable" message. Its data MUST be retained.

#### Scenario: Suspended tenant
- **WHEN** a platform admin suspends tenant A
- **THEN** tenant A's staff cannot sign in, its kiosks and displays show "service unavailable", and no SMS is sent on its behalf

### Requirement: Per-tenant branding
Each tenant SHALL be able to configure a display name, logo, primary and accent colors, and optional custom text for public surfaces. The kiosk, mobile check-in, booking, feedback, lobby display and SMS sender name (where the provider supports it) MUST use the tenant's branding. They MUST NOT show platform branding when white-label is enabled for the tenant's plan.

#### Scenario: Branding applied to kiosk
- **WHEN** tenant A has uploaded a logo and set its primary color
- **THEN** tenant A's kiosk and lobby display render that logo and color

#### Scenario: White-label not in plan
- **WHEN** a tenant's plan does not include white-label
- **THEN** public surfaces show a small "powered by" platform mark alongside the tenant's branding

### Requirement: Tenant-level settings
Each tenant SHALL have its own settings: time zone default, locale, ticket number format, data retention period and notification defaults. Locations MAY override the time zone. All times shown to users and used in scheduling MUST use the applicable location's time zone.

#### Scenario: Location time zone override
- **WHEN** a location sets its time zone to America/Chicago while the tenant default is America/New_York
- **THEN** that location's queue, display, appointments and reports show times in Central time
