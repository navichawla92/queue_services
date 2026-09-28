## Context

This is a greenfield repository (`C:\xampp\htdocs\service-app`), developed locally under XAMPP (Apache, PHP, MySQL). The stack was chosen with the stakeholders: **Laravel + MySQL**, with **Twilio behind a provider interface** for SMS. See `proposal.md` for motivation and scope, and `specs/*/spec.md` for the behavior contract.

These constraints shape the design:
- One company at launch, but data must be isolated per company for a future SaaS product. Retrofitting multi-tenancy later is costly, so it goes in from the first migration.
- Four real-time surfaces (staff dashboard, lobby TV, customer status page, kiosk confirmation) must update within about 2 seconds without refresh.
- Lobby TVs are unattended, often cheap smart-TV browsers or HDMI sticks on flaky networks.
- XAMPP on Windows is for development only. Production is a Linux host able to run long-lived processes (WebSocket server, queue worker, scheduler).

## Goals / Non-Goals

**Goals:**
- A single Laravel monolith with clear module boundaries that map to the capabilities.
- Tenant isolation enforced by default at the query, channel and job level, not left to each developer to remember.
- A deterministic ticket state machine with concurrency-safe "call next".
- Real-time fan-out that degrades gracefully to polling.
- KPI queries that are correct first, then fast enough.

**Non-Goals:**
- Microservices, a separate SPA or a public REST API for third parties. Internal JSON endpoints only; API access is a plan flag reserved for later.
- A database-per-tenant or schema-per-tenant model (see Decision 2).
- Offline-first kiosks. The kiosk requires connectivity and shows an error when offline.

## Decisions

### 1. Laravel 11 modular monolith
Code is organized by domain under `app/Domain/{Tenancy,Access,Organization,Routing,Queue,Display,Signage,Notifications,Scheduling,Feedback,Analytics,Billing}`. Each domain holds its models, actions, events and policies. HTTP controllers, Livewire components and routes stay grouped by surface (`admin`, `staff`, `public`, `display`, `platform`).
- *Why:* one deployable unit fits the team size and hosting, and domain folders keep the capability boundaries visible.
- *Alternatives:* separate services (too much operational overhead); default flat Laravel layout (becomes unnavigable at this size).

### 2. Multi-tenancy: shared database, `tenant_id` column plus global scope
Every tenant-owned table has an indexed, non-null `tenant_id` FK. A `BelongsToTenant` trait:
- adds a global scope filtering by the current tenant;
- auto-fills `tenant_id` on create;
- throws if no tenant is bound (this enforces the tenant-isolation spec's "reject writes without tenant").

A `TenantContext` singleton is set by:
- (a) middleware from the authenticated user;
- (b) the public identifier in the route for kiosk, QR, booking and feedback pages;
- (c) the device token for displays and kiosks;
- (d) a global queue hook that writes the dispatching tenant's `tenant_id` into every job payload and restores it around job execution (`JobProcessing` sets it; `JobProcessed`/`JobExceptionOccurred` restore the previous context), so no job can forget to opt in;
- (e) the scheduler, which iterates tenants explicitly.

Composite unique indexes include `tenant_id`. Route model binding goes through the scoped query, so another tenant's ID yields 404.
- *Why:* the cheapest model to operate, and it scales to hundreds of tenants. Laravel's global scopes make "safe by default" achievable. Adding `stancl/tenancy` later remains possible.
- *Alternatives:* database-per-tenant (strongest isolation, but heavy migrations and ops); `stancl/tenancy` from the start (adds complexity we don't need yet).
- *Guardrail:* an automated test suite seeds two tenants and asserts that every route, broadcast channel and job refuses cross-tenant access. A static check fails CI if a model with a `tenant_id` column lacks the trait.

### 3. Identity and roles
- Laravel's built-in auth (Breeze or Fortify) handles sessions, password reset and throttling.
- Roles and permissions use `spatie/laravel-permission` with its **teams** feature keyed by `tenant_id`.
- Location scoping is stored in a `location_user` pivot and checked in policies.
- Platform Admins are flagged on the user record (`is_platform_admin`) and bypass the tenant scope only in `platform/*` routes, through an explicit `withoutTenantScope()` call that is audited.
- Kiosks and displays are `devices` rows holding a hashed long-lived token, which is stored in the browser's `localStorage` and cookie after pairing. They are authenticated by a custom guard that only allows the `display` and `kiosk` routes and channels.
- Auditing: an `audit_logs` table (append-only, with no update or delete routes) is written by model observers and explicit calls in actions.

### 4. Ticket state machine and concurrency
- `tickets` holds the current status; `ticket_events` is the append-only history of every transition, with actor, from/to status, desk and timestamps.
- All transitions go through a single `TicketStateMachine` service that validates the allowed transitions from the staff-queue spec.
- Denormalized timing columns (`queued_at`, `first_called_at`, `service_started_at`, `completed_at`, `total_hold_seconds`) are kept for fast KPIs.
- Every queue-mutating action (check-in, call next, call, transfer, hold/release, complete, no-show, cancel) runs in a DB transaction that first takes a **per-location queue lock**: `SELECT … FOR UPDATE` on that location's `location_queue_states` row. That row also holds the `queue_version`, which is incremented in the same transaction.
- Inside the lock, **call next** reads the ordered eligible waiting tickets (`LIMIT 1`) and updates the chosen one to Called. Concurrent callers at the same location are therefore serialized: each gets a different ticket, or none.
- Ticket numbers come from a `ticket_sequences` row per (location, department, local_date), incremented while the location lock is held.
- Duplicate check-in is blocked by an active-ticket lookup (customer + location) made under the same lock.
- *Why this over `SKIP LOCKED`:* it works on MariaDB 10.4, which XAMPP bundles, as well as on MySQL 8 and MariaDB 10.6 or later. It also gives `queue_version` a single atomic home. Serializing per location costs little, since a lobby sees at most a few queue actions per second.
- *Alternatives:* `SELECT … FOR UPDATE SKIP LOCKED` (needs MySQL 8 or MariaDB 10.6+, and allows more parallelism than we need); optimistic locking with retry (more code paths); Redis locks (another dependency for no gain).

### 5. Real-time: Laravel Reverb + Echo, with polling fallback
- Domain events such as `TicketCalled` and `QueueChanged` are broadcast on private channels:
  - `tenant.{t}.location.{l}.queue` for staff;
  - `tenant.{t}.display.{d}` for TVs;
  - `tenant.{t}.ticket.{publicToken}` for the customer status page, authorized by the unguessable token.
- Payloads are **small deltas plus a monotonically increasing `queue_version`**. When a client detects a version gap, or reconnects, it refetches a full snapshot from a JSON endpoint. That snapshot is the single source of truth for the dashboard, display and status page.
- When disconnected, clients poll the snapshot endpoint every 5–10 s.
- Broadcasts are dispatched `afterCommit` so clients never see uncommitted state.
- *Why Reverb:* first-party, self-hosted, no per-message cost, and it speaks the Pusher protocol, so we can move to Pusher or Ably with a config change.
- *Alternatives:* SSE (one-directional, awkward with PHP-FPM); plain polling (simple, but can't reliably meet 2-second highlights at scale); Pusher (paid, external).

### 6. Staff UI: Blade + Livewire 3 + Alpine + Tailwind; lobby display: vanilla JS page
- Admin, staff, kiosk, booking and feedback pages use Livewire to keep the stack PHP-centric.
- The lobby **display** is a standalone lightweight page (Alpine + Echo, no Livewire round-trips). It renders from the snapshot JSON and handles the call-highlight queue, signage playlist and media caching (Service Worker + Cache API) on the client. This keeps TVs responsive and resilient to server blips.
- The chime uses the Web Audio API. Browsers need a one-time user gesture at pairing to allow audio, and the pairing screen asks for a tap/click.
- Display layouts are JSON configs (zones, orientation, rows, ticker), rendered with CSS grid.

### 7. Routing and wait-time estimation
- `routing_rules` hold ordered rules per location. Each rule has match criteria (service, customer type, weekday set, time window) and a target department and priority. Evaluation stops at the first match, with a fallback to the service's default department.
- Eligibility is a join over the employee/department/service skill pivots, live `employee_status`, and the location.
- Estimate = `ceil(position / max(1, activeEligibleStaff)) × avgServiceMinutes`:
  - `avgServiceMinutes` is a rolling 14-day average per (location, service), stored in a cache table refreshed hourly, with a fallback to the service's expected duration if there are fewer than 20 samples;
  - the result is rounded to a 5-minute range.
- Estimates are recomputed on `QueueChanged`, debounced per location.

### 8. SMS via a notification pipeline
- A `SmsProvider` interface has two implementations: `TwilioSmsProvider` (twilio/sdk) and `LogSmsProvider`.
- Provider credentials are stored per tenant, with optional per-location sender overrides, in encrypted columns (Laravel `encrypted` cast).
- Domain events map to `NotificationEvent` types. A `NotificationDispatcher` checks, in order:
  1. whether the event is enabled for the tenant/location;
  2. consent;
  3. opt-out;
  4. quiet hours;
  5. plan allowance.

  It then renders the template (a restricted `{{placeholder}}` renderer, not Blade, so users cannot run code) and writes an `sms_messages` row (queued) before dispatching a queued job.
- The job re-checks freshness (e.g. ticket still Waiting/Called) before sending, and retries with exponential backoff on 5xx/timeout.
- Twilio status and inbound webhooks are validated with Twilio's request signature. Inbound STOP/START/HELP updates `sms_opt_outs`, keyed by (tenant, phone).
- *Alternatives:* Laravel Notifications channel (fine, but less control over staleness checks and logging); Vonage (kept possible via the interface).

### 9. Scheduling model
- `employee_schedules` hold recurring weekly rules, `time_off` holds exceptions, and `location_hours`, `department_hours` and `closures` define when places are open.
- A `SlotFinder` computes open slots for a (location, service, [employee], date): hours ∩ schedule − time off − booked appointments − capacity caps. Slots are not stored; they are computed on demand.
- Double-booking prevention works in two steps. First, the booking locks the candidate employees' rows (`SELECT … FOR UPDATE`, in id order). Then it re-checks overlap and the hourly capacity with **locking reads**. A plain re-read is not enough: under InnoDB REPEATABLE READ it would still see the transaction's earlier snapshot and miss a booking another request just committed. A multi-process test caught exactly this. "Any available employee" bookings pick the least-booked free employee at booking time.
- The manage link uses a signed, unguessable token.
- Reminders are generated by a scheduler task every 5 minutes, which finds appointments whose reminder offset has passed and records `reminders_sent` for idempotency.
- Auto-no-show for tickets and appointments, and end-of-day closeout, are scheduler tasks per location time zone.

### 10. Analytics: fact rows from tickets plus nightly rollups
- Reports read from `tickets` and `ticket_events` for "today" and from a `daily_stats` rollup table (per tenant/location/department/service/employee/date, holding counts, sum and count of wait/service seconds, and hour histograms) for historical ranges.
- Rollups are recomputed nightly and for any day touched by late changes, so they are idempotent upserts.
- Historical names are preserved because rollups keep foreign keys and setup rows are soft-deactivated, never deleted.
- Charts use Chart.js (via CDN or npm).
- CSV exports stream via a queued job for large ranges.
- *Alternative:* a separate OLAP store (overkill at this scale).

### 11. Signage
- `signage_items` hold content (typed, with JSON payload and media path), and `playlists` with `playlist_items` define ordering. `playlist_schedules` define both *where* and *when* a playlist plays. The target is the company, a location or one display. The conditions are a date range, weekdays and a time window. An `is_default` flag covers times when nothing else is scheduled. The most specific active schedule wins. This single table replaces the separate `display_playlists` assignment table.
- `playlist_items` has no tenant column, so every pivot operation first resolves the playlist through the tenant scope.
- Media is stored on the Laravel filesystem (local disk in dev, S3-compatible storage in prod) and served with cache headers.
- Displays receive a resolved "manifest" (current playlist + items + media URLs + hash). A `SignageChanged` broadcast plus a 60-second poll of the manifest hash keeps displays current.
- QR slides are generated server-side (`simplesoftwareio/simple-qrcode` or `bacon/bacon-qr-code`).

### 12. Plans, limits and usage
- `plans` hold JSON features and limits; `tenants.plan_id` links each tenant to its plan.
- A `Plan` gate/middleware (`feature:signage`) and a `LimitGuard` are used in create actions.
- `usage_counters` are keyed by (tenant, metric, period) and incremented transactionally by the relevant actions and by the SMS job (using the segment count from the provider).
- Warnings at 80% and 100% are raised on increment.

### 13. Time zones and data
- All timestamps are stored in UTC. The location time zone is used for display, "local day" sequencing, schedules and report bucketing.
- Phone numbers are normalized to E.164 with `giggsey/libphonenumber-for-php`.
- A retention job anonymizes customer PII older than the tenant's retention period, keeping aggregate facts.

### 14. Organization model
- **Departments belong to a location**, so ticket prefixes are unique per location (`unique(location_id, prefix)`).
- **Services are company-wide.** A service is offered at a location through `location_service`, and that row holds the location's **default department** for the service. The service catalog (name, duration, channels) can only be edited by users covering all locations. Location managers choose what their location offers and which department it routes to.
- **Employees** are a serving profile (`employees`) on top of a staff `User`. An employee's work locations are the user's location assignments (`location_user`), which avoids a second, drifting list. Departments and skills (services) are per employee.
- **Hours** live in one `opening_hours` table: rows with a null department are the location's hours; rows for a department are that department's own hours. A department with any rows uses only those, intersected with the location's hours; otherwise it inherits the location's hours. `closures` replace the weekly hours for a date (closed all day, or special hours), per location or per department. Walk-ins are refused in the last `walkin_cutoff_minutes` of a window. Everything is evaluated in the location's time zone (`OperatingHours`).
- **Live status** (`available` / `busy` / `on_break` / `offline`) plus the current location and desk sit on the employee row. Starting a shift picks the chosen desk, else the current desk at that location, else the default desk. Status columns are excluded from setup auditing.

## Risks / Trade-offs

- **[Forgotten tenant scope on a raw query or new model]** → The trait is mandatory (CI check), `DB::table` is banned for tenant tables (lint rule), and the two-tenant isolation test suite runs on every route and channel.
- **[Reverb/WebSocket unavailable on some hosting]** → Snapshot polling fallback is built in. Reverb is Pusher-protocol compatible, so we can switch to a hosted provider through config.
- **[TV browsers block autoplay audio or sleep the screen]** → A one-time tap at pairing unlocks audio. Videos are muted by default, and the Wake Lock API is used where supported. We will publish a recommended-hardware list (e.g. Chromium-based sticks) and a kiosk-mode setup guide.
- **[SMS compliance (A2P 10DLC, TCPA) and costs]** → Consent is captured and stored with a timestamp, STOP/HELP is handled, quiet hours apply, allowances and warnings are per tenant, and the log driver is used outside production.
- **[Wait estimates perceived as inaccurate]** → Estimates are shown as ranges and fall back to configured durations. Estimate vs actual is tracked, so the formula can be tuned later without spec changes.
- **[Scope size: one very large change]** → Tasks are ordered so each phase ships a usable slice (foundation → queue + display → SMS → appointments → signage → feedback/analytics → SaaS). Reviews and merges go phase by phase.
- **[Per-location queue lock becomes a hot spot]** → Lock hold time is kept to single-digit milliseconds (no external calls or broadcasts inside the lock; broadcasts go `afterCommit`). If a very large location ever needs more throughput, switch to `SKIP LOCKED` on MySQL 8.
- **[Database compatibility]** → Supported: MariaDB 10.4+ (XAMPP dev) and MySQL 8 or MariaDB 10.6+ (production). Avoid features that are unavailable on MariaDB 10.4, such as `SKIP LOCKED`, `CHECK`-dependent logic, and window functions in hot paths. Use `DATETIME` rather than `TIMESTAMP` for NOT NULL time columns: without `explicit_defaults_for_timestamp`, MariaDB 10.4 rejects a second NOT NULL `TIMESTAMP` that has no default. Run CI against MariaDB 10.4.
- **[Fan-out cost per queue change]** A benchmark with 150 people waiting and 50 paired displays found three problems, now fixed:
  (a) Displays were pinged one publish each. A single `DisplayChanged` event now carries up to 100 display channels, which the Pusher protocol delivers in one request.
  (b) Positions and estimates were computed ticket by ticket. `QueuePositions::estimateMany()` now does one ordered query per location (staff snapshot 827 ms → 57 ms).
  (c) Server-side publishing to `localhost` on Windows first tried IPv6 and cost about 200 ms per publish. Use an IPv4 host (`127.0.0.1`) for the local Reverb server.
  Result: check-in takes about 124 ms and call-next about 12 ms, including all broadcasts and SMS decisions.
  Note: the Pusher client does not throw when Reverb is down. `LiveUpdates` has a circuit breaker for broadcasts that do throw, and screens poll regardless.
- **[Rollup drift]** → Rollups are idempotent upserts, recomputed nightly for the trailing 7 days, and "today" is always computed live.

## Migration Plan

This is a greenfield build, so no data migration is needed. Deployment:
1. Provision a Linux host (or managed PaaS): PHP 8.2+, MySQL 8 or MariaDB 10.4+, Redis (optional, recommended for queue and cache), and HTTPS.
2. Run the migrations and seed the internal tenant with an unlimited internal plan and the first company admin.
3. Run the supervisor-managed processes: `php artisan reverb:start`, `php artisan queue:work`, and cron for `schedule:run`.
4. Configure Twilio (messaging service, A2P registration, and the status/inbound webhook URLs pointing at the app).
5. Pilot at one location: pair one kiosk and one TV, onboard its staff, then roll out to the other locations.

Rollback: before go-live, redeploy the previous release. After go-live, offices can fall back to manual queueing while a fix is deployed. Migrations are additive only.

## Open Questions

- Exact hardware for kiosks and TVs (the tablet model, and the TV stick vs smart-TV browser). This affects the setup guide only.
- Supported languages beyond English for the kiosk and SMS templates at launch. The i18n framework is built either way.
- Production hosting provider (VPS vs Laravel Forge/Cloud). This does not change the architecture.
- Final default SMS wording, to be reviewed by marketing/compliance before launch.
