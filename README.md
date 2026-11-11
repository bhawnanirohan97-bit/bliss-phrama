# Bliss Pharmex AI Lead Catcher

Lead-catching bot for **Bliss Pharmex** (Nagpur), a third-party pharmaceutical exporter. It receives IndiaMART enquiries, matches them against the live product catalogue, scores export fit, and prepares human-reviewed outreach drafts.

- **Ingest:** IndiaMART Push API webhook with raw payload audit and idempotent storage.
- **Match:** deterministic product matcher over the imported catalogue (923 matchable SKUs).
- **Score:** policy `v1.1.0` across product fit, buyer intent, destination, quantity, and recency.
- **Draft:** WhatsApp, email, and call scripts generated for every lead that clears the hard gates.
- **Guardrails:** drafts are fact-checked and withheld on unsupported claims. A human always sends.
- **Stack:** Laravel 13, PHP 8.3+, SQLite, Blade, hand-written CSS. No Node/npm build step.

> Bliss Pharmex is a **third-party exporter**, not a manufacturer. Do not claim brand ownership, factories, certifications, stock, prices, or guarantees in outreach. Per-SKU principal/authorisation still needs verification.

## Repository layout

```
api/    Laravel application (webhook, matcher, scoring, drafting, dashboard)
docs/   PRD amendments, architecture, engine spec, test plan, deployment runbook
```

Start with `docs/01_PRD_AMENDMENTS.md` (authoritative v1.1) and `docs/02_ARCHITECTURE.md`.

## Quick start

Requirements: PHP 8.3+ with the SQLite and mbstring extensions, and Composer 2.

```bash
cd api
composer install
cp .env.example .env          # Windows: Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Then open <http://127.0.0.1:8000>. The dashboard, inbox, catalogue review, and settings pages are all served from there.

SQLite creates `api/database/database.sqlite` automatically during setup. If it does not exist:

```bash
php -r "touch('database/database.sqlite');"
php artisan migrate
```

## Configuration

Copy `api/.env.example` to `api/.env` and set:

| Variable | Purpose |
| --- | --- |
| `INDIAMART_WEBHOOK_SECRET` | Shared secret in the webhook path. Generate a long random value. |
| `APP_URL` | Public base URL, or the current tunnel URL during local trials. |
| `OPERATOR_*` | Export desk identity used in drafts (company, desk, city, phone, email). |
| `MATCHER_FLOOR` | Minimum matcher score, default `0.55`. |
| `POLICY_VERSION` | Scoring policy version, default `v1.1.0`. |
| `REJECT_DOMESTIC` | Reject India-destination enquiries, default `true`. |
| `DISPLAY_TIMEZONE` | Dashboard timezone, default `Asia/Kolkata`. |
| `MAIL_*` | SMTP settings for email alerts. |

Never commit `api/.env`. The app ignores it, along with SQLite files, logs, and vendor dependencies.

## IndiaMART webhook

Endpoint:

```
POST /api/webhooks/indiamart/{INDIAMART_WEBHOOK_SECRET}
```

Only lead types `B`, `W`, and `WA` are accepted. `BIZ` and `P` are ignored. The raw payload is stored in `webhook_events` for audit, then deduplicated into `leads`.

For a local trial, expose the app with a tunnel:

```bash
cloudflared tunnel --url http://127.0.0.1:8000 --no-autoupdate
```

Use the tunnel URL plus the webhook path in the IndiaMART Push API settings. Quick tunnels rotate when restarted; use a named Cloudflare tunnel for anything long-lived.

## Operator workflow

1. **Settings** — switch the bot on. While it is off, leads are stored as `held`; switching it on releases and scores the backlog.
2. **Inbox** — review scored leads. Hard rejects are hidden by default; inspect them at `/inbox?status=hard_rejected`.
3. **Lead detail** — see the matched SKU, assessment breakdown, and generated drafts. Copy a draft or open the pre-filled WhatsApp/email link, then send it yourself.
4. **Catalogue** — approve SKUs for export before the matcher will use them. Only `approved` SKUs are auto-matched.
5. **Regenerate** — request fresh drafts per channel; drafts are append-only, so earlier versions remain auditable.

## Catalogue and country data

```bash
# Import or refresh the catalogue from a CSV export
php artisan catalogue:import path/to/catalogue.csv

# Review export eligibility in bulk
php artisan catalogue:review --list
php artisan catalogue:review --category="Anti Cancer Tablets" --status=approved

# Import destination eligibility (iso2,name,class,notes)
php artisan countries:import path/to/countries.csv

# Re-score stored leads
php artisan leads:assess --all
```

Without an approved catalogue and a country table, matching and destination scoring cannot contribute: destination scores stay at 0 and the maximum total is 80.

## Tests

```bash
cd api
composer test        # clears config, then runs the PHPUnit suite
composer lint        # Laravel Pint
```

## Project status and known gaps

- IndiaMART Push API activation is not yet confirmed.
- Email alerts need an alert address and SMTP credentials.
- Country eligibility data has not been supplied.
- SKUs must be approved in `/catalogue` before production matching works.
- The Pull API backfill and WhatsApp sending are not implemented.
- Human review is mandatory; the system never sends messages on its own.
