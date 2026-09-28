## 1. Project Setup

- [x] 1.1 Create Laravel 11+ project in the repo root, configure `.env` for local MariaDB 10.4 (XAMPP), UTC app time zone, and verify `php artisan serve` works
- [x] 1.2 Install and configure Tailwind, Alpine, Livewire 3, Laravel Echo, and Laravel Reverb; verify a test broadcast reaches the browser
- [x] 1.3 Add dependencies: spatie/laravel-permission, twilio/sdk, giggsey/libphonenumber-for-php, a QR code library, Chart.js
- [x] 1.4 Create the domain folder structure (`app/Domain/*`) and route files per surface (admin, staff, public, display, platform)
- [x] 1.5 Set up Pest/PHPUnit with a MySQL test database, factories, and a CI script (tests + Pint + static analysis)
- [x] 1.6 Configure the queue (database or Redis driver), scheduler entry, and filesystem disks (local dev / S3-compatible prod)

## 2. Tenancy Foundation

- [x] 2.1 Create `plans` and `tenants` migrations/models (status, branding fields, settings JSON, public identifier, plan_id)
- [x] 2.2 Implement the `TenantContext` singleton and the `BelongsToTenant` trait (global scope, auto-fill, throw when no tenant is bound)
- [x] 2.3 Implement tenant-resolution middleware for authenticated users, public identifiers (device-token resolution is part of 3.6)
- [x] 2.4 Implement tenant propagation for queued jobs (global payload hook) and tenant iteration for scheduled tasks
- [x] 2.5 Add a CI check that every model with a `tenant_id` column uses `BelongsToTenant`
- [x] 2.6 Build the two-tenant isolation test harness (seed tenants A and B; helper assertions for routes, channels and jobs)
- [x] 2.7 Implement tenant suspension handling (block sign-in, public "unavailable" page; SMS suppression is enforced in 10.4)
- [x] 2.8 Implement tenant branding (logo upload, colors, display name) and a shared public layout that applies it, including the "powered by" mark when white-label is not in the plan
- [x] 2.9 Implement tenant/location settings resolution (time zone, locale, ticket format, retention) with location overrides

## 3. Access Control and Audit

- [x] 3.1 Install auth scaffolding (Breeze/Fortify): login, logout, password reset, throttling/lockout, idle session timeout
- [ ] 3.2 Configure spatie permissions with teams = tenant; seed roles (Company Admin, Location Manager, Receptionist, Employee) and permissions
- [x] 3.3 Add the `location_user` pivot and location-scope checks in policies; add a location switcher for multi-location users
- [x] 3.4 Implement the platform-admin flag, `platform/*` route group, and audited support sessions with a visible banner
- [x] 3.5 Create the append-only `audit_logs` table, an audit service and model observers; add an audit log viewer for company admins
- [x] 3.6 Implement the `devices` table, pairing-code flow, device guard with device-token tenant resolution, and revocation (with tests for revoked devices)
- [x] 3.7 Write role and location-scope authorization tests for every admin/staff route

## 4. Organization Setup

- [ ] 4.1 Extend locations (base table + model created in 3.3) and create migrations/models for departments (unique prefix per location), services, service-location pivot, desks/rooms
- [ ] 4.2 Create employee profile model, employee-location/department pivots, employee skills (employee-service pivot), default desk
- [ ] 4.3 Create location hours, department hours and closures tables, plus an "is open / next opening" service with last-walk-in cutoff
- [ ] 4.4 Build admin CRUD screens (Livewire) for locations, departments, services, desks, employees and hours/closures, with validation and deactivate/reactivate
- [ ] 4.5 Implement live employee status (Available/Busy/On Break/Offline) and desk selection at shift start
- [ ] 4.6 Generate the per-location public check-in identifier, QR code image and printable QR poster

## 5. Routing and Wait Estimates

- [ ] 5.1 Create the `routing_rules` table and an admin UI for ordered rules (service, customer type, weekdays, time window → department, priority)
- [ ] 5.2 Implement the department resolver (first matching rule, else the service default) with unit tests
- [ ] 5.3 Implement the eligible-employees query (location + department + skill + status)
- [ ] 5.4 Implement the rolling average service time cache (per location/service, hourly refresh, fallback to expected duration)
- [ ] 5.5 Implement the wait estimator (position, active eligible staff, average) with range rounding and a "unavailable" state; add unit tests
- [ ] 5.6 Implement the unroutable-service check for self-service surfaces

## 6. Ticket Core and State Machine

- [ ] 6.1 Create `customers` (E.164 phone, consent fields, no-show count), `tickets` (status, channel, priority, timing columns, public token) and `ticket_events` tables
- [ ] 6.2 Implement `ticket_sequences` and daily per-location/department number generation under a lock (A-001 reset per local day)
- [ ] 6.3 Implement `TicketStateMachine` with the allowed transitions, `ticket_events` recording, hold-time tracking and audit entries
- [ ] 6.4 Implement queue ordering (priority, then queued_at; appointment boost; held excluded; direct assignments)
- [ ] 6.5 Implement the per-location queue lock (`location_queue_states` row with `queue_version`, `SELECT … FOR UPDATE`), use it for all queue mutations, and build the atomic "call next" on it, with a concurrency test proving no double-claim
- [ ] 6.6 Implement actions: call specific, recall, assign, transfer (with policy for queue position), hold/release, start, complete (with outcome), no-show, cancel by customer
- [ ] 6.7 Implement internal notes on tickets and customers (never exposed publicly)
- [ ] 6.8 Emit domain events (`TicketCreated`, `TicketCalled`, `QueueChanged`, …) after commit with an incrementing per-location `queue_version`

## 7. Customer Check-In

- [ ] 7.1 Build the shared check-in flow (Livewire): name, phone (configurable required), service selection, optional department choice, SMS consent, validation
- [ ] 7.2 Implement returning-customer matching by phone and duplicate active-ticket prevention
- [ ] 7.3 Build the kiosk surface: device-paired, full-screen, large touch targets, text size/high contrast, language switch, 60 s inactivity reset, confirmation screen
- [ ] 7.4 Build the QR/mobile check-in surface on the public location identifier, respecting hours and location active status
- [ ] 7.5 Build the live ticket status page (public token, Echo subscription, polling fallback, leave-queue action)
- [ ] 7.6 Build receptionist check-in in the staff console (channel "receptionist", can bypass the unroutable check)
- [ ] 7.7 Implement appointment check-in by phone, confirmation code or SMS link, with fallback to walk-in
- [ ] 7.8 Add i18n scaffolding (language files) for all customer-facing strings

## 8. Staff Queue Dashboard

- [ ] 8.1 Build the snapshot JSON endpoint for a location queue (tickets, statuses, staff, `queue_version`)
- [ ] 8.2 Build the staff dashboard (Livewire + Echo): table columns per spec, live waiting timer, appointment/walk-in badge, notes indicator
- [ ] 8.3 Add filters (department, service, status, employee, customer type) and a "My queue" view
- [ ] 8.4 Wire the action buttons (call next, call, recall, assign, transfer dialog, hold/release, start, complete, no-show, notes) with permission checks
- [ ] 8.5 Implement reconnect/resync behavior (version-gap detection, reconnect indicator, polling fallback)
- [ ] 8.6 Add scheduler tasks: auto-no-show for called tickets, and end-of-day closeout to "Closed unserved" per location time zone
- [ ] 8.7 Add a today's-appointments panel alongside the live queue
- [ ] 8.8 Write feature tests for every lifecycle action and for invalid transitions

## 9. Lobby Display

- [ ] 9.1 Build display registration in admin (location, departments, layout, orientation, name display option) and the pairing screen with a code
- [ ] 9.2 Build the standalone display page (Alpine + Echo) that renders from a display snapshot endpoint and persists its device token
- [ ] 9.3 Implement the now-serving panel (ticket, desk/room, optional employee name, privacy-safe customer name option)
- [ ] 9.4 Implement the waiting list panel with row limit and "+N more", plus optional average wait per department
- [ ] 9.5 Implement the call highlight queue (sequential highlights, configurable duration, Web Audio chime unlocked at pairing, recall re-highlight)
- [ ] 9.6 Implement the layout engine (queue-only, split zones, header, clock, ticker) with remote layout change via broadcast and a 30 s config poll
- [ ] 9.7 Implement resilience: auto-reconnect, offline indicator, keep last state, polling fallback, Wake Lock, hidden cursor
- [ ] 9.8 Test legibility at 720p, 1080p, 4K and in portrait; document TV/kiosk setup (browser kiosk mode, auto-start)

## 10. SMS Notifications

- [ ] 10.1 Define the `SmsProvider` interface; implement `TwilioSmsProvider` and `LogSmsProvider`; tenant/location sender config with encrypted credentials
- [ ] 10.2 Create `sms_messages`, `sms_opt_outs`, `notification_settings` (per tenant, with location overrides) and `sms_templates` tables
- [ ] 10.3 Implement the restricted placeholder renderer, default templates for every event, and a template editor with preview and segment count
- [ ] 10.4 Implement `NotificationDispatcher` (tenant active → enabled → consent → opt-out → quiet hours → allowance), then write the log row and dispatch the queued job
- [ ] 10.5 Implement the send job with freshness re-check, exponential backoff retries and a stale-skip
- [ ] 10.6 Implement Twilio status callback and inbound webhooks with signature validation; STOP/START/HELP handling
- [ ] 10.7 Wire queue events to notifications: check-in confirmation, wait update threshold, position update, you're next, representative ready, desk change, transfer
- [ ] 10.8 Build the message log UI for managers (filter by status, event, date)

## 11. Appointment Scheduling

- [ ] 11.1 Create `employee_schedules`, `time_off`, `appointments` (status, confirmation code, manage token) and `appointment_reminders` tables
- [ ] 11.2 Build the admin/employee availability UI (weekly hours per location, time off) and booking settings (lead time, horizon, buffer, cutoff, capacity caps)
- [ ] 11.3 Implement `SlotFinder` (hours ∩ schedule − time off − bookings − caps, within the booking window) with unit tests
- [ ] 11.4 Build the public booking page (location → service → optional employee → date/slot → details + consent) with double-booking protection under lock
- [ ] 11.5 Build staff booking/edit/reschedule/cancel in the console with override warnings
- [ ] 11.6 Build the customer manage page (reschedule/cancel via signed token, cutoff enforcement)
- [ ] 11.7 Implement confirmation, reschedule, cancellation and reminder SMS (scheduler every 5 min, idempotent, quiet hours)
- [ ] 11.8 Implement appointment status flow, auto-no-show after grace, customer no-show count and optional booking restriction
- [ ] 11.9 Link appointment check-in tickets to appointments and sync status (Arrived, In Service, Completed, No-Show)

## 12. Digital Signage

- [ ] 12.1 Create `signage_items`, `playlists`, `playlist_items`, `playlist_schedules`, `display_playlists` and `ticker_messages` tables
- [ ] 12.2 Build the content library UI for all item types with media upload validation (type/size per plan) and QR slide generation
- [ ] 12.3 Build the playlist editor (ordering, durations, date ranges) and schedule editor (date range, weekdays, time, default playlist, specificity precedence)
- [ ] 12.4 Implement the manifest resolver endpoint (current playlist + items + media URLs + hash) and a `SignageChanged` broadcast
- [ ] 12.5 Implement the display-side player: rotation, video playback, skip expired items, Service Worker media caching, 60 s manifest poll
- [ ] 12.6 Implement call-highlight priority over signage (overlay, mute video during chime) and ticker rendering

## 13. Customer Feedback

- [ ] 13.1 Create `feedback_requests` (token, expiry, used) and `feedback_responses` (ratings, comment, attribution) tables, plus per-tenant feedback settings (delay, cooldown, extra questions, alert threshold)
- [ ] 13.2 Trigger a delayed feedback request on completion, respecting consent, cooldown and quiet hours
- [ ] 13.3 Build the public branded feedback page with single-use, expiring link handling
- [ ] 13.4 Implement low-score alerts (in-app notification and optional email to location managers)
- [ ] 13.5 Build the feedback review list with filters and employee self-view (tenant setting)

## 14. Analytics and Reporting

- [ ] 14.1 Create the `daily_stats` rollup table and an idempotent nightly rollup job (trailing 7 days recompute)
- [ ] 14.2 Implement the KPI query service with documented definitions (live for today, rollups for history), respecting location time zones and access scope
- [ ] 14.3 Build the report filters component (date range, location, department, service, employee) shared by all reports
- [ ] 14.4 Build reports: overview KPIs, volume trends (daily/weekly/monthly), wait/service times, per employee/department/location, peak-hours heatmap, no-show and abandonment, appointments vs walk-ins, satisfaction
- [ ] 14.5 Build the live location operations dashboard (waiting count, longest wait, staff status, SLA breach highlighting)
- [ ] 14.6 Build the multi-location management dashboard with side-by-side comparison and drill-down
- [ ] 14.7 Implement CSV export (streamed or queued for large ranges) with audit logging
- [ ] 14.8 Add KPI correctness tests with fixture scenarios (e.g. hold excluded from wait) and a performance check on 12 months of seeded data

## 15. SaaS Plans, Usage and Platform Console

- [ ] 15.1 Implement the plan features/limits schema, the seeded "Internal (unlimited)" plan and sample commercial plans
- [ ] 15.2 Implement `feature:*` middleware/gates and UI gating with upgrade messaging
- [ ] 15.3 Implement `LimitGuard` for locations, staff users and displays; SMS overage policy
- [ ] 15.4 Implement `usage_counters` metering (SMS segments, locations, users, displays, tickets, appointments, storage) with 80%/100% warnings (banner + email)
- [ ] 15.5 Build the tenant usage page and per-period usage export
- [ ] 15.6 Build the platform console: tenant list, create tenant with invite, suspend/reactivate, change plan, start support session

## 16. Privacy, Hardening and Launch

- [ ] 16.1 Implement the PII retention/anonymization job per tenant retention setting
- [ ] 16.2 Run the full two-tenant isolation suite across all routes, channels, jobs and webhooks; fix any leaks
- [ ] 16.3 Security review: rate-limit public endpoints (check-in, booking, feedback), CSRF, signed URLs, upload scanning, security headers
- [ ] 16.4 Load test: concurrent call next, 50 displays per location, and a burst of check-ins; verify 2 s real-time targets
- [ ] 16.5 Write the deployment runbook (supervisor for Reverb/queue, cron, Twilio webhooks, A2P registration, backups) and seed the internal tenant
- [ ] 16.6 Pilot at one location (kiosk + TV + staff onboarding), collect feedback, then roll out to the remaining locations
