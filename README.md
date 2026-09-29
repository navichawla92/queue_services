# Service Queue

A multi-company (multi-tenant) **queue management and appointment system** for walk-in service businesses such as bank branches, clinics and government offices.

Customers take a ticket at a kiosk, by scanning a QR code or by booking online. Staff call them from a live queue dashboard. A lobby TV shows who is being called. Managers get reports, customer feedback and SMS notifications.

Built with Laravel 12, Livewire 3, Tailwind CSS 4 and Laravel Reverb (real-time updates).

---

## Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Install (first time)](#install-first-time)
4. [Run the app](#run-the-app)
5. [Demo accounts](#demo-accounts)
6. [Where to find things (URLs)](#where-to-find-things-urls)
7. [Running the tests](#running-the-tests)
8. [Project structure](#project-structure)
9. [Troubleshooting](#troubleshooting)
10. [More documentation](#more-documentation)

---

## Features

| Area | What it does |
|---|---|
| **Queue** | Tickets per department, call next, transfer, hold, recall, no-show. Updates live via WebSockets. |
| **Check-in** | Kiosk tablet, QR code / mobile phone, or receptionist desk. |
| **Appointments** | Online booking, staff availability and time off, reschedule/cancel links. |
| **Lobby display** | TV screen that shows called tickets with a chime, plus digital signage (slides, video, ticker). |
| **Notifications** | SMS via Twilio (templates, opt-out, delivery log). |
| **Feedback** | Customers rate their visit; low ratings alert managers. |
| **Reports** | KPIs, wait times, peak hours, per-location overview, CSV export. |
| **Admin** | Locations, departments, desks, services, staff, roles, opening hours, closures, branding, audit log. |
| **SaaS** | Many companies on one install, plans with feature limits, platform admin console. |

**User roles:** Company admin, Location manager, Receptionist, Employee, plus a Platform admin who manages all companies.

---

## Requirements

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.2 or newer | Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd`, `curl`, `fileinfo`, `bcmath` |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org) |
| MySQL / MariaDB | MySQL 8 or MariaDB 10.4+ | XAMPP's MariaDB works |
| Node.js | 20 or newer | Only for building CSS/JS |

On Windows the easiest route is **[XAMPP](https://www.apachefriends.org)**, which gives you PHP and MySQL. Make sure `php` works in a terminal: add `C:\xampp\php` to your `PATH`.

Check your setup:

```bash
php -v
composer -V
node -v
```

---

## Install (first time)

### 1. Get the code and dependencies

```bash
git clone <repository-url> service-app
cd service-app
composer install
npm install
```

### 2. Create the environment file

```bash
cp .env.example .env        # Windows PowerShell: copy .env.example .env
php artisan key:generate
```

### 3. Create the databases

Start MySQL (in XAMPP: open the XAMPP Control Panel and start **MySQL**), then create two databases, one for the app and one for the tests:

```bash
mysql -u root -e "CREATE DATABASE service_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE service_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

(With XAMPP, `mysql` is in `C:\xampp\mysql\bin`. You can also create the databases in phpMyAdmin at http://localhost/phpmyadmin.)

### 4. Edit `.env`

Open `.env` and set at least the following:

```ini
APP_URL=http://localhost:8000

DB_DATABASE=service_app
DB_USERNAME=root          # XAMPP default
DB_PASSWORD=              # XAMPP default is empty

# Real-time updates (Reverb). For local use, any values work; they just have to be filled in.
REVERB_APP_ID=local
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
```

Everything else can stay as it is for local development. SMS is **not** really sent unless `SMS_ALLOW_REAL_SENDS=true` or `APP_ENV=production`; messages are written to the log instead.

### 5. Create tables and demo data

```bash
php artisan migrate --seed
php artisan storage:link
```

With `APP_ENV=local`, the seeder creates a demo company ("Demo Company") with one location, departments, services, opening hours, and a login for every role (see [Demo accounts](#demo-accounts)).

### 6. Build the front-end

```bash
npm run build
```

---

## Run the app

### Option A: everything with one command (recommended)

```bash
composer dev
```

This starts five processes together (stop them all with `Ctrl+C`):

| Process | What it is for |
|---|---|
| `php artisan serve` | The web app at http://localhost:8000 |
| `php artisan queue:listen` | Background jobs (SMS, notifications) |
| `php artisan reverb:start` | WebSocket server for live queue updates (port 8080) |
| `php artisan schedule:work` | Scheduled tasks (auto no-show, end-of-day closeout, reminders, stats) |
| `npm run dev` | Vite: rebuilds CSS/JS instantly when you edit files |

Open **http://localhost:8000** and sign in with a [demo account](#demo-accounts).

### Option B: minimal

If you only want to click around:

```bash
php artisan serve
```

The app works without Reverb: the staff queue shows "Reconnecting…" and refreshes every 5 seconds instead of instantly. Background jobs won't run until you also start `php artisan queue:listen`.

---

## Demo accounts

Created by the seeder when `APP_ENV=local`. **The password for every account is `password`.**

| Email | Role | Lands on |
|---|---|---|
| `admin@example.com` | Company admin | Admin overview, with full access |
| `manager@example.com` | Location manager | Admin (their locations only) |
| `reception@example.com` | Receptionist | Queue and customer check-in |
| `employee@example.com` | Employee | Queue (calls and serves customers) |
| `platform@example.com` | Platform admin | `/platform`: manage all companies and plans |

After signing in, use the **menu on the left** to move around. What you see depends on your role and on the company's plan. Locked items need a higher plan.

---

## Where to find things (URLs)

| URL | Who | What |
|---|---|---|
| `/login` | Staff | Sign in |
| `/staff` | Staff | Live queue dashboard |
| `/staff/checkin` | Receptionist | Check a customer in at the desk |
| `/staff/appointments` | Staff | Today's appointments |
| `/admin` | Admins, managers | Admin overview (setup, reports, devices, SMS, settings) |
| `/platform` | Platform admin | Companies, plans, support sessions |
| `/kiosk` | Tablet | Self check-in kiosk (must be paired first) |
| `/display` | TV | Lobby display (must be paired first) |
| `/c/{code}` | Customer | Mobile check-in (from the QR code poster) |
| `/book/{code}` | Customer | Online booking |
| `/t/{code}` | Customer | Live ticket status |

**To try the kiosk or TV locally:** open http://localhost:8000/kiosk (or `/display`) in another browser window. It shows a 6-character code. Enter that code under **Admin → Kiosks & displays → Pair device**.

**To get the customer check-in link:** go to **Admin → Locations**, then open a location. Its QR code, link and printable poster are there.

---

## Running the tests

The tests use the separate `service_app_test` database created in step 3, with the same MySQL user as your `.env`.

```bash
composer test          # or: php artisan test
```

Other checks:

```bash
composer lint          # code style (Laravel Pint); fix with: vendor/bin/pint
composer analyse       # static analysis (PHPStan / Larastan)
```

---

## Project structure

```
app/
  Domain/            Business logic, grouped by area. Start here.
    Queue/             tickets, ordering, state machine, live broadcasting
    Routing/           rules that send a service to the right department/employee
    Scheduling/        appointments, availability, booking slots
    Organization/      locations, departments, desks, services, employees
    Notifications/     SMS providers, templates, dispatch
    Display/           lobby TV screen data and live updates
    Signage/           slides, videos, playlists and ticker
    Feedback/          customer ratings
    Analytics/         KPIs and daily stats
    Billing/           plans, feature gates, usage limits
    Tenancy/           multi-company isolation (TenantContext, TenantScope)
    Access/            roles, permissions, devices, audit log
  Livewire/          Screens (one class per page): Admin/, Staff/, PublicSite/, Platform/
  Http/Controllers/  Non-Livewire endpoints (exports, QR codes, devices, webhooks)
  View/Navigation.php  The left-hand menu (add new pages here)

resources/views/
  layouts/           app (staff/admin), guest (login), public, kiosk
  livewire/          Blade templates for each Livewire screen
resources/css/app.css  Tailwind setup plus shared UI classes (.btn, .card, .input, .data-table …)

routes/
  web.php   /            → redirect to login or home
  staff.php /staff/*     staff screens
  admin.php /admin/*     admin screens
  platform.php /platform/*
  public.php             customer pages (check-in, booking, ticket, feedback)
  display.php            kiosk and TV devices

database/seeders/    Roles, plans and local demo data
tests/Feature/       Feature tests grouped by area
docs/                Deployment and device setup guides
openspec/            Product specs and change proposals
```

**How multi-tenancy works, in short:** every company is a *tenant*. Tenant-owned models use a global scope, so queries only ever see the current company's data. The tenant is resolved from the signed-in user, or, on public pages, from an unguessable code in the URL.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `php` or `composer` not found | Add `C:\xampp\php` (and Composer) to your `PATH`, then reopen the terminal. |
| `SQLSTATE[HY000] [2002]` / connection refused | MySQL isn't running. Start it in the XAMPP Control Panel. |
| `Unknown database 'service_app'` | Create the databases (step 3). |
| Page has no styling | Run `npm run build`, or keep `npm run dev` running. |
| `Vite manifest not found` | Same as above: `npm run build`. |
| Staff queue says "Reconnecting…" | Reverb isn't running. Start `php artisan reverb:start` (or use `composer dev`), and check the `REVERB_*` values in `.env`. After changing them, re-run `npm run build` / restart `npm run dev`. |
| Uploaded logo / signage image not showing | Run `php artisan storage:link`. |
| Changed `.env` but nothing happens | Run `php artisan config:clear`. |
| Want a clean start | `php artisan migrate:fresh --seed` (**deletes all data**). |
| An employee gets **403** on "My availability" / "My feedback" | This is by design. Enable it under **Admin → Company settings → Staff self-service**. |

---

## More documentation

- [docs/deployment.md](docs/deployment.md): production setup (server, `.env`, queue worker, Reverb, cron, backups)
- [docs/display-and-kiosk-setup.md](docs/display-and-kiosk-setup.md): hardware and pairing for lobby TVs and kiosks
- [openspec/](openspec/): detailed product specifications
