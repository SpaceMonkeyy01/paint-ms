# Deploying to Render

The app runs on Render as a Docker web service (`Dockerfile`, serversideup/php 8.3
fpm-nginx) with a managed **PostgreSQL** database. Render has no managed MySQL;
the app has no MySQL-specific SQL, so production runs on Postgres while local
dev can stay on MySQL.

Everything is declared in `render.yaml` (a Render Blueprint).

## First deploy

1. Push `master` to GitHub (Render deploys from the repo).
2. In the [Render dashboard](https://dashboard.render.com): **New → Blueprint**,
   connect the `paint-ms` repo, pick the `master` branch. Render reads
   `render.yaml` and shows the two resources it will create:
   - `paint-ms` — web service, **starter** plan (~$7/mo)
   - `paint-ms-db` — Postgres, **basic-256mb** plan (~$6/mo)
3. It will prompt for the two secrets (`sync: false` vars):
   - `APP_KEY` — generate locally with `php artisan key:generate --show` and paste it
   - `AIRTABLE_TOKEN` — the Airtable PAT from `docs/airtable-sync.md` (leave blank
     to deploy without the sync; the command skips cleanly when no token)
4. Apply. First build takes a few minutes (composer + npm + Docker image).

## What happens on boot

- serversideup automations run `php artisan migrate --force` and cache
  config/routes/views (`AUTORUN_ENABLED=true` in the Dockerfile).
- `docker/entrypoint.d/99-app.sh` then:
  - seeds the database **only if it is empty** (dev users + full legacy import);
  - starts `php artisan schedule:work` in the background, which runs
    `airtable:sync-orders` every 15 minutes.

## After the first deploy — do immediately

The seeder creates the three dev logins, all with password `password`:

| email | role |
|---|---|
| admin@pms.local | admin |
| store@pms.local | store |
| painter@pms.local | painter |

**Log in as each user and change the password from the profile page** (or create
real users and retire these) before sharing the URL with anyone.

## Plan notes

- **Free web service**: works, but sleeps after 15 min idle — first request then
  takes ~50s, and the in-container scheduler (Airtable sync) stops while asleep.
- **Free Postgres**: expires and is **deleted after 30 days**. Never use it for
  real ledger data.
- Region is `singapore` (closest to Karachi). Change in `render.yaml` if needed.

## Redeploys

Every push to `master` auto-deploys. Migrations run automatically; the seeder is
skipped because the database is no longer empty. Zero-downtime: Render waits for
the `/up` health check before switching traffic.

## Troubleshooting

- **Logs**: service → Logs tab (`LOG_CHANNEL=stderr` sends Laravel logs there).
- **Console**: service → Shell tab gives you a shell in the container
  (`php artisan tinker`, spot-check stock, re-run the sync by hand with
  `php artisan airtable:sync-orders`).
- **Mixed content / http redirects**: `bootstrap/app.php` trusts Render's proxy
  (`trustProxies('*')`); if URLs come out as `http://`, check that line survived.
