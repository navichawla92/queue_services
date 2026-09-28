## Why

Our offices handle walk-in customers and appointments with no shared view of who is waiting, who is being served, or how long it takes. That makes the lobby experience poor, staffing decisions guesswork, and performance across locations invisible. We need a queue management and customer flow platform that covers check-in through feedback. It will be built for our own company first, and its architecture must let it become a multi-company SaaS product later without a rewrite.

## What Changes

This is a greenfield build. The repository is empty, so there are no breaking changes. The change delivers:

- **Tenant-isolated foundation**: every record belongs to a company (tenant). All data access is scoped to that tenant from day one, even though only one company will exist at launch.
- **Authentication and role-based permissions** for platform admins, company admins, location managers, receptionists and employees.
- **Organization setup**: locations, departments, services, employees, desks/rooms, employee skills, service assignments, operating hours and routing rules.
- **Customer check-in** through a lobby kiosk/tablet, a per-location QR code, a customer's own phone, or a receptionist. The customer enters a name and phone number and picks a service; the system routes them to a department, issues a queue ticket, and shows an estimated wait. Walk-ins and scheduled appointments both check in this way.
- **Real-time staff queue dashboard**: call next, assign, transfer between employees or departments, hold, start service, complete service, mark no-show, and add internal notes.
- **Browser-based lobby TV display**: shows now-serving tickets with desk/room, the waiting list, and a visual highlight plus chime when a customer is called. It updates live and needs no manual refresh.
- **Digital signage**: admin-managed content (announcements, promo slides, images, videos, QR codes, service info, seasonal messages) with playlists and schedules, shown in a zone next to the queue area on the lobby display.
- **SMS notifications** through Twilio, behind a swappable provider interface. Each company or location can configure templates and on/off toggles for check-in confirmation, wait estimate, queue updates, "you're next", "representative ready", desk/room assignment, appointment reminders and feedback requests. Opt-out (STOP) is honored.
- **Appointment scheduling**: online booking against employee and location availability, confirmations, SMS reminders, rescheduling, cancellation, and no-show tracking. Appointments merge into the same live queue as walk-ins.
- **Customer feedback**: an optional post-service SMS survey link with a rating and comment, attributed to employee, department and location.
- **Analytics and KPIs**: customers served, average wait and service times, volume per employee, department and location, peak hours, no-show and abandonment rates, appointments vs walk-ins, daily, weekly and monthly volume, and satisfaction. All reports filter by employee, service, department, location and date range. A multi-location management dashboard is included.
- **SaaS-ready capabilities**: per-tenant white-label branding (logo, colors, name), a plan/feature-limit model and usage metering. These are designed now so billing can be added later.

### Non-goals (this change)

- Payment collection and subscription billing integration (e.g. Stripe). Only the plan/limit model and usage metering are built.
- A self-service public tenant sign-up flow. Platform admins provision tenants.
- Native mobile apps. All customer and staff surfaces are responsive web.
- Calendar sync (Google/Outlook) and two-way SMS conversations beyond STOP/HELP keywords.

## Capabilities

### New Capabilities

- `tenant-isolation`: Company (tenant) model, tenant resolution, strict data scoping, and per-tenant branding.
- `access-control`: User accounts, authentication, roles, and permission checks scoped to tenant and location.
- `organization-setup`: Management of locations, departments, services, employees, desks/rooms, skills, service assignments and operating hours.
- `customer-routing`: Routing rules that pick the department and eligible employees for a ticket, and the wait-time estimate.
- `customer-check-in`: Kiosk, QR, mobile and receptionist check-in flows that create queue tickets for walk-ins and arriving appointments.
- `staff-queue`: Real-time staff dashboard and ticket lifecycle actions (call, assign, transfer, hold, start, complete, no-show, notes).
- `lobby-display`: Browser-based, device-paired lobby TV display with live now-serving, waiting list and call highlighting.
- `digital-signage`: Content library, playlists, schedules and screen layouts for lobby displays.
- `sms-notifications`: Provider abstraction (Twilio), configurable templates and triggers, delivery tracking, and opt-out handling.
- `appointment-scheduling`: Availability, online booking, confirmations, reminders, rescheduling, cancellation and no-show tracking.
- `customer-feedback`: Post-service feedback requests and satisfaction capture.
- `analytics-reporting`: KPI computation, filterable reports, and the cross-location management dashboard.
- `saas-plans-usage`: Plan and feature-limit model and usage metering, to prepare for future subscription billing.

### Modified Capabilities

None. There are no existing specs.

## Impact

- **New codebase**: a Laravel 11+ application on PHP 8.2+ with MySQL 8, runnable under XAMPP for development.
- **Dependencies**: Laravel Reverb (WebSockets), the Laravel queue worker and scheduler, the Twilio PHP SDK, and a frontend stack (Blade + Livewire/Alpine, Tailwind). A chart library is needed for analytics.
- **Infrastructure**: a queue worker process, the cron scheduler, a Reverb WebSocket server, public HTTPS URLs for the Twilio status/inbound webhooks and QR/mobile check-in, and file storage for signage media.
- **External services**: a Twilio account with a messaging-capable number per tenant or location, plus A2P 10DLC registration for US traffic.
- **Security and privacy**: customer PII (names, phone numbers) is stored, which requires retention rules, tenant isolation tests and audit logging of staff actions.
