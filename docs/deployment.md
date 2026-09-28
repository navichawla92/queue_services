# Deployment runbook

Production target: a Linux host (VPS or managed PaaS such as Laravel Forge/Cloud) behind HTTPS. XAMPP is for local development only.

## 1. Requirements

| Component | Version / notes |
|---|---|
| PHP | 8.2+ with `pdo_mysql`, `mbstring`, `intl`, `gd`, `curl`, `fileinfo`, `bcmath`, `pcntl` (for Reverb/queue signals) |
| Database | MySQL 8, or MariaDB 10.4+ (10.6+ recommended). InnoDB, `utf8mb4`. |
| Redis | Optional but recommended for cache, queue and sessions |
| Node | 20+ (build step only) |
| Web server | Nginx or Apache with PHP-FPM; HTTPS certificate (Let's Encrypt) |
| Process manager | Supervisor (or systemd) for the queue worker and Reverb |
| Storage | Local disk, or S3-compatible bucket for signage media (`MEDIA_DISK=s3`) |

## 2. First deploy

```bash
git clone <repo> /var/www/service-app && cd /var/www/service-app
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# edit .env — see section 3
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=PlansSeeder --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Create the internal tenant and first company admin with the platform console (`/platform`) after creating a platform admin:

```bash
php artisan tinker --execute='$u = App\Models\User::create(["name"=>"Platform Admin","email"=>"ops@yourco.com","password"=>Hash::make(Str::random(32))]); $u->forceFill(["is_platform_admin"=>true])->save();'
php artisan tinker --execute='Password::sendResetLink(["email"=>"ops@yourco.com"]);'
```

Then in `/platform` → **New tenant** (plan: *Internal (unlimited)*), which emails the first company admin an invitation.

## 3. Environment (.env)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://queue.yourco.com

DB_CONNECTION=mysql
DB_HOST=… DB_DATABASE=service_app DB_USERNAME=… DB_PASSWORD=…

SESSION_DRIVER=database        # or redis
SESSION_LIFETIME=120           # idle timeout (minutes)
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database      # or redis
CACHE_STORE=database           # or redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=… REVERB_APP_KEY=… REVERB_APP_SECRET=…
REVERB_HOST=queue.yourco.com   # public host the browsers connect to
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1   # where reverb:start listens (proxied by Nginx)
REVERB_SERVER_PORT=8080

MAIL_MAILER=smtp …             # password resets, invitations, alerts
MEDIA_DISK=public              # or s3 (+ AWS_* variables)
```

Real SMS are only sent when `APP_ENV=production` (or `SMS_ALLOW_REAL_SENDS=true`) **and** the tenant configured Twilio in **Admin → SMS notifications**.

## 4. Long-running processes (Supervisor)

```ini
[program:service-app-queue]
command=php /var/www/service-app/artisan queue:work --sleep=1 --tries=5 --max-time=3600
autostart=true
autorestart=true
numprocs=2
user=www-data
stopwaitsecs=3600

[program:service-app-reverb]
command=php /var/www/service-app/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
```

Cron (scheduler — auto-no-show, end-of-day closeout, reminders, rollups, retention):

```
* * * * * www-data php /var/www/service-app/artisan schedule:run >> /dev/null 2>&1
```

After every deploy: `php artisan queue:restart && php artisan reverb:restart`.

## 5. Nginx (WebSocket proxy + headers)

```nginx
location /app {                      # Reverb (Pusher protocol) WebSocket
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 60s;
    proxy_pass http://127.0.0.1:8080;
}
location /apps { proxy_pass http://127.0.0.1:8080; }   # server-side publish API

# Recommended CSP at the proxy (Livewire/Alpine need 'unsafe-eval'):
add_header Content-Security-Policy "default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; connect-src 'self' wss://queue.yourco.com; frame-ancestors 'self'" always;
```

The app itself sends `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and (over HTTPS) HSTS.

## 6. Twilio

1. Buy a number (or create a Messaging Service) and complete **A2P 10DLC** brand + campaign registration for US traffic.
2. In **Admin → SMS notifications**: provider *Twilio*, Account SID, Auth Token, sender number / Messaging Service SID.
3. In Twilio, set the number's **incoming message webhook** (POST) to the URL shown on that page:
   `https://queue.yourco.com/webhooks/sms/twilio/<tenant-public-id>/inbound`
   Delivery status callbacks are sent per message automatically.
4. Send a test by checking yourself in with SMS consent; watch **Admin → SMS → Message log**.

## 7. Backups & monitoring

- Nightly `mysqldump --single-transaction` (retain 30 days, off-site), plus the media disk/bucket.
- Monitor: HTTP health `GET /up`, queue backlog (`jobs` table size), failed jobs (`php artisan queue:failed`), Reverb process, disk space.
- Logs: `storage/logs/laravel.log` (rotate daily); ship to your log service.

## 8. Rollback

Before go-live: redeploy the previous release. After go-live: keep the previous release directory, switch the symlink back, `php artisan queue:restart`. Migrations are additive; do not roll them back in production. Offices can fall back to manual queueing (receptionist check-in still works if SMS/WebSockets are down — screens poll).

## 9. Pilot checklist (one location first)

- [ ] Location, departments, services, desks, hours, routing configured
- [ ] Staff accounts invited, skills and desks set, everyone signed in once
- [ ] Kiosk and lobby TV paired (see `docs/display-and-kiosk-setup.md`), sound enabled, visible from the farthest seat
- [ ] QR poster printed and placed
- [ ] SMS: consent → check-in confirmation → "representative ready" received on a real phone
- [ ] Run a full morning; review Reports and the Audit log; collect staff feedback
- [ ] Roll out to remaining locations
