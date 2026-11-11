# PharmaLead AI — Deployment Runbook

Version 1.0 · Target: single Windows office PC · Audience: the solo developer, then the office operator for routine sections

This runbook is the complete operating procedure for the one machine that runs PharmaLead AI. Follow it top to bottom on a fresh install. For day-to-day operation, sections 9 and 10 are the only ones the operator needs.

---

## 1. Target environment

| Item | Value |
|---|---|
| Host | One Windows 11 Pro office PC, always on, mains powered |
| Role | Application server, database server, queue worker, scheduler; all in one box |
| Install root | `C:\PharmaLead` (never inside OneDrive or another sync folder) |
| API | `C:\PharmaLead\api` — Laravel 11, PHP 8.3 |
| Web source | `C:\PharmaLead\web` — React 19 / Vite source; build output is copied to `C:\PharmaLead\api\public\spa` |
| Served output | `C:\PharmaLead\api\public` — one document root for both `/api/*` and the SPA |
| Ops | `C:\PharmaLead\ops` — backup and restore scripts, logs |
| Database | MySQL 8, schema `pharmalead`, local only (`127.0.0.1:3306`) |
| Time zone | `Asia/Kolkata` for the OS, PHP (`APP_TIMEZONE`) and MySQL (`+05:30`) |
| Web server | Laragon (Apache or Nginx) on port 8080, or Docker Desktop; production uses one, not both |
| LAN access | Team browsers reach `http://pharmalead.local:8080` or `http://<pc-ip>:8080` on the private network only |

**Always-on assumption.** The PC is expected to be running 24/7. The scheduler fires every minute, the queue worker runs continuously, and the weekly ingest watcher polls for new leads. Design each job to be safe to miss:

- A missed scheduler tick is picked up on the next tick. Laravel's scheduler runs due tasks once; a 10-minute gap means those runs happen late, not twice.
- A missed ingest poll is caught up by the next poll, because ingestion is idempotent on `(source, external_id)`.
- Downtime never causes silent data loss. On restart the watcher fetches from its last successful watermark, re-fetches overlapping rows, and discards duplicates. The batch log records `gap_detected` with the start and end of the outage.
- If the machine is off across a week boundary (Monday 00:00 Asia/Kolkata), the previous week's unused allowance is already expired and is not recoverable. Record the loss in the waste metric and note it in the weekly report.

**Do not** run the production database from a OneDrive-synced folder. OneDrive locks files during sync and corrupts live MySQL data files.

---

## 2. Prerequisites and exact install steps

### 2.1 Software versions

| Software | Version | Purpose | Notes |
|---|---|---|---|
| Windows | 11 Pro (or 10 Pro) | OS | Pro is required for the Task Scheduler and local security policy features used here |
| Laragon | Full 6.x with PHP 8.3 | Web server + PHP + MySQL (option A) | Easiest on Windows |
| Docker Desktop | 4.x with WSL 2 | Container alternative (option B) | Use one of A or B |
| PHP | 8.3.x | Application runtime | `php -v` must report 8.3 |
| MySQL | 8.0.x | Production database | SQLite is for dev and tests only |
| Composer | 2.7+ | PHP dependencies | `composer -V` |
| Node.js | 20 LTS | Front-end build only | Not needed at runtime |
| Git | 2.4x | Source control and releases | `git --version` |
| 7-Zip | 24.x | Compress dumps | Used by the backup script |
| NSSM (optional) | 2.24 | Run the queue worker as a service instead of a scheduled task | Optional; the Task Scheduler path in section 3 is the default |

### 2.2 Install (option A: Laragon)

1. Install Laragon Full. Choose the PHP 8.3 build and MySQL 8.0 in the Laragon menu.
2. Set Laragon to run on port 8080 and start Apache (or Nginx) automatically at Windows startup.
3. Open a terminal and verify: `php -v` shows 8.3, `composer -V` shows 2.7+, `mysql --version` shows 8.0.
4. Install Node 20 LTS with the Windows installer; verify `node -v` shows `v20.x`.
5. Install Git and 7-Zip with defaults.
6. Create the install root:

```powershell
New-Item -ItemType Directory -Path C:\PharmaLead -Force
New-Item -ItemType Directory -Path C:\PharmaLead\ops -Force
New-Item -ItemType Directory -Path C:\PharmaLead\backups -Force
```

### 2.3 Install (option B: Docker Desktop)

1. Install Docker Desktop, enable the WSL 2 backend, and start it.
2. Create `C:\PharmaLead\docker-compose.yml` with three services: `app` (PHP 8.3 + Apache), `db` (MySQL 8.0 with a named volume at `/var/lib/mysql`), and `scheduler` (same image as `app`, command `php artisan schedule:work`).
3. Bind-mount `C:\PharmaLead\api` into the `app` container at `/var/www/html`.
4. Publish the app on host port 8080 and MySQL on `127.0.0.1:3306`. Do not publish MySQL on all interfaces.
5. Verify with `docker compose ps` that all three services are `running`, and `docker compose exec app php -v` shows 8.3.

Either option produces the same directory layout. The rest of this runbook assumes `C:\PharmaLead\api` on the host and `php` on PATH for the Laragon option.

### 2.4 Create the Laravel application

From `C:\PharmaLead`, run:

```powershell
composer create-project laravel/laravel:^11.0 api
```

If the repository is already cloned, use:

```powershell
git clone <repository-url> C:\PharmaLead\api
Set-Location C:\PharmaLead\api
composer install --no-dev --optimize-autoloader
```

### 2.5 Environment setup

```powershell
Set-Location C:\PharmaLead\api
Copy-Item .env.example .env
notepad .env
```

Fill `.env` using the reference in section 4. At minimum set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_TIMEZONE=Asia/Kolkata`, the `DB_*` block, the `MAIL_*` block, the AI block and the IndiaMART block. Set `WEEKLY_ALLOWANCE=75`.

Then:

```powershell
php artisan key:generate
```

Confirm `APP_KEY=base64:...` is non-empty. Never copy a production `APP_KEY` into a dev environment; encrypted columns become unreadable.

### 2.6 Database creation

```sql
CREATE DATABASE pharmalead CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pharmalead'@'127.0.0.1' IDENTIFIED BY '<strong-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON pharmalead.* TO 'pharmalead'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Use `utf8mb4` so Hinglish text and any non-Latin buyer names survive the round trip.

### 2.7 Migrations and seeders

```powershell
php artisan migrate --force
php artisan db:seed --class=CountryRulesSeeder --force
php artisan db:seed --class=ProductCatalogueSeeder --force
php artisan db:seed --class=SynonymsSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=AllowanceSeeder --force
```

`AllowanceSeeder` creates the current-week allowance row with `allotted_count = 75`, `used_count = 0`, `week_start` = the Monday of the current ISO week in Asia/Kolkata.

### 2.8 Front-end build

```powershell
Set-Location C:\PharmaLead\web
npm ci
npm run build
```

Set Vite's build output to `C:\PharmaLead\api\public\spa` (`build.outDir` in `vite.config.ts`). Point the web server document root at `C:\PharmaLead\api\public`. Laravel serves `/api/*` and returns the SPA shell for every non-API route. Keep the API and the SPA on one origin so cookies and CSRF work without CORS exceptions. Do not run a separate Vite dev server in production.

### 2.9 First-run verification

```powershell
Set-Location C:\PharmaLead\api
php artisan migrate:status
php artisan about
php artisan system:health-check
```

`migrate:status` shows no pending migrations. `about` shows the expected environment and PHP version. `system:health-check` exits 0 and prints the allowance row and the last ingest watermark.

Open `http://localhost:8080/` and sign in with the seeded admin account. Change the seeded password immediately.

---

## 3. Windows Task Scheduler tasks

Create the tasks under a dedicated Windows account (`pharmalead-svc`) with "Run whether user is logged on or not" and "Run with highest privileges". Store the account password in the Windows Credential Manager, not in a script.

| Task name | Trigger | Command | Working directory |
|---|---|---|---|
| `PharmaLead Scheduler` | Every 1 minute, indefinitely, starting at PC startup | `"C:\laragon\bin\php\php-8.3\php.exe" artisan schedule:run` | `C:\PharmaLead\api` |
| `PharmaLead Queue Worker` | At system startup, and again at user logon | `"C:\laragon\bin\php\php-8.3\php.exe" artisan queue:work --queue=high,default --tries=3 --backoff=30 --max-time=3600 --sleep=3` | `C:\PharmaLead\api` |
| `PharmaLead Nightly Backup` | Daily at 02:00, local time | `powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\PharmaLead\ops\backup.ps1"` | `C:\PharmaLead\ops` |
| `PharmaLead Ingest Watcher` | Every 5 minutes, and at system startup | `"C:\laragon\bin\php\php-8.3\php.exe" artisan leads:pull` | `C:\PharmaLead\api` |
| `PharmaLead Daily Digest` | Daily at 07:30, Monday to Saturday | `"C:\laragon\bin\php\php-8.3\php.exe" artisan system:digest` | `C:\PharmaLead\api` |

Settings for every task:

- **If the task is already running:** "Do not start a new instance" (the queue worker uses one process; the scheduler is idempotent).
- **If the task fails:** restart every 1 minute, up to 3 times, then send an alert (section 8).
- **Stop the task if it runs longer than:** 10 minutes for scheduler, watcher and digest; 12 hours for the queue worker (it restarts cleanly).
- **Run task as soon as possible after a scheduled start is missed:** enabled, so a reboot does not skip the nightly backup.
- **Conditions:** untick "Start the task only if the computer is on AC power" and untick "Stop if the computer switches to battery power".

Create the tasks from an elevated PowerShell prompt:

```powershell
$php = "C:\laragon\bin\php\php-8.3\php.exe"
$action = New-ScheduledTaskAction -Execute $php -Argument "artisan schedule:run" -WorkingDirectory "C:\PharmaLead\api"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
Register-ScheduledTask -TaskName "PharmaLead Scheduler" -Action $action -Trigger $trigger -User "pharmalead-svc" -RunLevel Highest
```

Repeat for the other four tasks with their own action, trigger and working directory. After registering, open Task Scheduler, right-click each task, choose Properties, and set the failure restart and "run missed task" options described above.

Verify all five tasks with:

```powershell
Get-ScheduledTask -TaskName "PharmaLead *" | Select-Object TaskName, State
```

All five must report `Ready` (or `Running` for the queue worker). The queue worker shows `Running` continuously.

---

## 4. `.env` reference

Fold this table into `C:\PharmaLead\api\.env`. Values are examples; replace every placeholder before production.

| Key | Example value | Required | Purpose |
|---|---|---|---|
| `APP_NAME` | `PharmaLead AI` | Yes | Displayed in mail and the UI |
| `APP_ENV` | `production` | Yes | `local` for dev, `production` on the office PC |
| `APP_KEY` | `base64:<generated>` | Yes | Encryption; generated by `key:generate` |
| `APP_DEBUG` | `false` | Yes | Must be `false` in production |
| `APP_URL` | `http://pharmalead.local:8080` | Yes | Absolute URLs in mail and links |
| `APP_TIMEZONE` | `Asia/Kolkata` | Yes | Drives week boundaries and the allowance expiry |
| `APP_LOCALE` | `en` | Yes | UI and validation language |
| `LOG_CHANNEL` | `daily` | Yes | Rotate logs daily |
| `LOG_LEVEL` | `warning` | Yes | `debug` only while diagnosing |
| `LOG_DAILY_DAYS` | `14` | Yes | Retention of application logs |
| `DB_CONNECTION` | `mysql` | Yes | `sqlite` for dev/test only |
| `DB_HOST` | `127.0.0.1` | Yes | Local only |
| `DB_PORT` | `3306` | Yes | Local MySQL |
| `DB_DATABASE` | `pharmalead` | Yes | Schema name |
| `DB_USERNAME` | `pharmalead` | Yes | Least-privilege user |
| `DB_PASSWORD` | `<strong-password>` | Yes | Never commit |
| `SESSION_DRIVER` | `database` | Yes | Survives restarts |
| `SESSION_SECURE_COOKIE` | `false` | Yes | `true` only after HTTPS is enabled |
| `QUEUE_CONNECTION` | `database` | Yes | No Redis on the office PC |
| `CACHE_STORE` | `database` | Yes | Simple, persistent |
| `MAIL_MAILER` | `smtp` | Yes | Drafts are sent by a human; the app itself only sends alerts and the digest |
| `MAIL_HOST` | `smtp.example.com` | Yes | Office mail provider |
| `MAIL_PORT` | `587` | Yes | STARTTLS port |
| `MAIL_USERNAME` | `alerts@example.com` | Yes | Sending mailbox |
| `MAIL_PASSWORD` | `<app-password>` | Yes | Use an app password, not the account password |
| `MAIL_ENCRYPTION` | `tls` | Yes | STARTTLS |
| `MAIL_FROM_ADDRESS` | `alerts@example.com` | Yes | From address for digest and alerts |
| `MAIL_FROM_NAME` | `PharmaLead AI` | Yes | From name |
| `AI_PROVIDER` | `null` | Yes | v1 ships `null`; v1.1 uses `gemini`. Allowed: `gemini`, `anthropic`, `ollama`, `null` |
| `AI_MONTHLY_CAP_USD` | `5.00` | Yes | Hard stop; requests are refused once reached |
| `AI_MAX_REQUESTS_PER_DAY` | `1500` | Yes | Secondary guardrail below the cap |
| `AI_TIMEOUT_SECONDS` | `20` | Yes | Per-request timeout; on timeout fall back to deterministic-only |
| `GEMINI_API_KEY` | `<key>` | Conditional | Required when `AI_PROVIDER=gemini` |
| `GEMINI_MODEL` | `gemini-2.0-flash` | Conditional | Free-tier model; verify current model name in P0 |
| `ANTHROPIC_API_KEY` | `<key>` | Conditional | Required when `AI_PROVIDER=anthropic` |
| `ANTHROPIC_MODEL` | `claude-3-5-haiku-latest` | Conditional | Paid option; disabled by default |
| `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | Conditional | Required when `AI_PROVIDER=ollama` |
| `OLLAMA_MODEL` | `llama3.1:8b` | Conditional | Local model name |
| `AI_SHADOW_MODE` | `true` | No | Legacy switch; v1.1 shadow is governed by the `ENGINE_M*` mode, not this key |
| `INDIAMART_BASE_URL` | `<from account manager>` | Conditional | Must be verified in P0 |
| `INDIAMART_API_KEY` | `<key>` | Conditional | Must be verified in P0 |
| `INDIAMART_API_SECRET` | `<secret>` | Conditional | Must be verified in P0 |
| `INDIAMART_SELLER_ID` | `<id>` | Conditional | Must be verified in P0 |
| `INDIAMART_POLL_MINUTES` | `5` | Yes | Ingest watcher interval |
| `INDIAMART_LOOKBACK_HOURS` | `72` | Yes | Overlap window to avoid missing leads after downtime |
| `WEEKLY_ALLOWANCE` | `75` | Yes | Contracted weekly quota |
| `WEEKLY_FEE_INR` | `<contracted weekly fee>` | Yes | Numerator of the derived unit cost; unit cost = fee / 75 |
| `ENGINE_M1_NORMALIZER` | `shadow` | Yes | M1 normaliser; values `off`, `shadow`, `live` |
| `ENGINE_M2_MATCHER` | `shadow` | Yes | M2 catalogue and matcher; values `off`, `shadow`, `live` |
| `ENGINE_M3_POLICY` | `shadow` | Yes | M3 scorer and policy; values `off`, `shadow`, `live` |
| `ENGINE_M4_LLM_EXTRACTION` | `off` | Yes | M4; v1.1 only |
| `ENGINE_M5_LLM_ADJUDICATION` | `off` | Yes | M5; v1.1 only |
| `ENGINE_M6_CONFIDENCE_GATING` | `off` | Yes | M6; v1.1 only |
| `ENGINE_M7_DRAFTS` | `shadow` | Yes | M7 template drafts; values `off`, `shadow`, `live` |
| `BACKUP_PATH` | `C:\PharmaLead\backups` | Yes | Local backup directory |
| `BACKUP_SECOND_PATH` | `D:\PharmaLead\backups` | Yes | Second location (external drive or another PC) |
| `BACKUP_RETENTION_DAILY` | `14` | Yes | Daily copies kept |
| `BACKUP_RETENTION_WEEKLY` | `4` | Yes | Weekly copies kept |
| `HEALTH_TOKEN` | `<random-string>` | Yes | Auth for the health endpoint when called by a monitor |
| `TELESCOPE_ENABLED` | `false` | No | Keep off in production |
| `PULSE_ENABLED` | `true` | No | Lightweight local metrics |

`WEEKLY_FEE_INR` and the AI keys are required only if those features are enabled; mark them conditional in your own copy.

Phase flags are three-position, never boolean: `off`, `shadow`, `live`. Every phase ships in `shadow` first. Promote to `live` only after the phase gate in `08_TEST_PLAN.md` passes on the frozen holdout, and record the metric and row count that justified it. The pilot runs M1, M2, M3 and M7 in `live`; M4, M5 and M6 stay `off` until v1.1. Hard gates are code, not configuration: no flag can disable them.

After editing `.env`, run:

```powershell
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`config:cache` is mandatory in production; without it, config changes are read on every request and are slower. Never run `config:cache` while `APP_DEBUG=true` with an incomplete `.env`.

---

## 5. Secrets handling

### 5.1 File permissions

Restrict `.env` to the service account and the developer's admin account. Remove inheritance and grant only the two required principals:

```powershell
$envFile = "C:\PharmaLead\api\.env"
icacls $envFile /inheritance:r
icacls $envFile /grant "pharmalead-svc:(R)"
icacls $envFile /grant "Administrator:(F)"
icacls $envFile /remove "Users" "Everyone" "Authenticated Users"
```

Also restrict `C:\PharmaLead\api\storage` and `C:\PharmaLead\ops` the same way. The web server process runs as `pharmalead-svc`, not as `SYSTEM`.

### 5.2 Git rules

`.gitignore` in the API repository must contain at least:

```gitignore
.env
.env.*
!.env.example
/storage/*.key
/storage/logs/*
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*
/bootstrap/cache/*
node_modules/
/dist/
*.sql
*.sql.gz
```

Verify before every commit:

```powershell
git status --short
git diff --cached --name-only | Select-String -Pattern "\.env$|\.sql$|\.sql\.gz$|key$"
```

If a secret was ever committed, treat it as leaked even after removal from history.

### 5.3 No secrets in the SPA

- Only `VITE_`-prefixed variables reach the browser. Never place a key, token or credential in a `VITE_` variable.
- The AI provider, IndiaMART connector and mail credentials are used only server-side.
- The production bundle scan runs in CI (test plan S7) and again before each release: grep `C:\PharmaLead\api\public\spa` for `INDIA`, `GEMINI`, `ANTHROPIC`, `APP_KEY`, `DB_PASSWORD`, bearer-token patterns and any `.env` value. Any hit blocks release.

### 5.4 Rotation

| Secret | Rotation interval | Procedure |
|---|---|---|
| `APP_KEY` | Only on suspected compromise | Requires re-encrypting encrypted columns; schedule downtime and follow the Laravel key-rotation guide |
| Database password | Every 180 days | `ALTER USER`, update `.env`, `config:clear`, `config:cache`, restart the queue worker |
| Mail app password | Every 180 days or on staff change | Regenerate at the provider, update `.env`, restart the queue worker |
| IndiaMART credentials | Per contract, or on staff change | Regenerate in the portal, update `.env`, test one ingest batch |
| AI provider key | Every 90 days | Regenerate at the provider, update `.env`, verify the cap meter still reads the new key |

**Leaked key rule:** rotate immediately. Do not investigate first, do not wait to understand how it leaked, and do not test whether it is still valid. Rotation takes priority over diagnosis. After rotation, check the provider's usage dashboard for anomalous spend, then review the audit log.

---

## 6. Backup and restore

### 6.1 Nightly backup

`C:\PharmaLead\ops\backup.ps1`:

```powershell
$ErrorActionPreference = "Stop"
$stamp = Get-Date -Format "yyyy-MM-dd"
$dump  = "C:\PharmaLead\backups\pharmalead_$stamp.sql"
$gz    = "$dump.gz"
$mysqlDump = "C:\laragon\bin\mysql\mysql-8.0\bin\mysqldump.exe"

& $mysqlDump --single-transaction --quick --routines --triggers --events `
  --default-character-set=utf8mb4 `
  -h 127.0.0.1 -u pharmalead -p"$env:DB_PASSWORD" pharmalead > $dump

if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }

& "C:\Program Files\7-Zip\7z.exe" a -tgzip -mx=7 $gz $dump | Out-Null
if ($LASTEXITCODE -ne 0) { throw "gzip failed" }

Remove-Item $dump
Copy-Item $gz "D:\PharmaLead\backups\" -Force

Get-ChildItem "C:\PharmaLead\backups\pharmalead_*.sql.gz" |
  Sort-Object LastWriteTime -Descending |
  Select-Object -Skip 14 | Remove-Item -Force
```

`--single-transaction` gives a consistent snapshot of InnoDB tables without locking the app. Run it at 02:00 when ingestion is idle. The backup is not complete until the copy to the second location succeeds; the script throws and the task alerts if it does not.

### 6.2 Retention

- **14 daily** copies in `C:\PharmaLead\backups`.
- **4 weekly** copies copied every Sunday to `D:\PharmaLead\backups\weekly\pharmalead_<year>-W<week>.sql.gz`, retained for 4 weeks.
- The second location is a physically separate drive (external USB or a second PC on the LAN). A backup that exists only on the same disk is not a backup.

### 6.3 Monthly restore drill

Run on the first Monday of each month, on a scratch database, never against production.

1. Pick the most recent daily dump.
2. Create the scratch database:

```sql
CREATE DATABASE pharmalead_scratch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Decompress and restore:

```powershell
& "C:\Program Files\7-Zip\7z.exe" e "C:\PharmaLead\backups\pharmalead_2026-10-22.sql.gz" -o"C:\PharmaLead\ops\tmp" -y
& "C:\laragon\bin\mysql\mysql-8.0\bin\mysql.exe" -h 127.0.0.1 -u pharmalead -p"$env:DB_PASSWORD" pharmalead_scratch < "C:\PharmaLead\ops\tmp\pharmalead_2026-10-22.sql"
```

4. Compare counts against production:

```sql
SELECT 'leads' AS t, COUNT(*) FROM pharmalead_scratch.leads
UNION ALL SELECT 'qualifications', COUNT(*) FROM pharmalead_scratch.qualifications
UNION ALL SELECT 'allowances', COUNT(*) FROM pharmalead_scratch.allowances
UNION ALL SELECT 'activities', COUNT(*) FROM pharmalead_scratch.activities
UNION ALL SELECT 'audit_logs', COUNT(*) FROM pharmalead_scratch.audit_logs;
```

5. Expected output: every count equals the production count captured at the same moment (allow for rows written after the dump by comparing against the dump's timestamp, not "now"). `SELECT used_count, allotted_count FROM pharmalead_scratch.allowances ORDER BY week_start DESC LIMIT 1;` matches production. A smoke query — the top 10 leads by score — returns the same SKUs and scores.

6. Record the result in `C:\PharmaLead\ops\restore-drill.log` with date, dump name, row counts and pass/fail. Drop the scratch database afterwards.

### 6.4 When a restore fails

1. Do not touch the production database. A failed restore on the scratch database is not a production incident.
2. Capture the exact error into the drill log.
3. Try the previous day's dump. If that restores, the newest dump is corrupt: mark it bad, keep it for diagnosis, and add a check to the backup script.
4. If two consecutive dumps fail, stop relying on the local copies and restore from the second location.
5. If every copy fails, escalate to the developer immediately and start a manual export of any data still reachable in production.
6. The build gate "backup restores successfully onto a scratch database" remains failed until a drill passes; do not release a new build while it is failed.

---

## 7. Power and reliability settings

### 7.1 Disable sleep and hibernation

Run once in an elevated PowerShell:

```powershell
powercfg /change standby-timeout-ac 0
powercfg /change hibernate-timeout-ac 0
powercfg /change monitor-timeout-ac 10
powercfg /change disk-timeout-ac 0
powercfg /hibernate off
```

`monitor-timeout-ac 10` is safe: the display sleeps, the machine does not. Never set `standby-timeout-ac` above 0 or the scheduler and queue worker stop firing.

Check the active scheme with `powercfg /query` and confirm `Current AC Power Setting Index` is `0x00000000` for standby and hibernate.

### 7.2 Windows Update and reboots

- Set Active Hours to cover the working day (07:00–22:00) so updates do not reboot mid-batch.
- Configure Windows Update to notify rather than auto-restart. Apply updates on a Friday after the weekly loop closes, or on a weekend.
- On reboot, the queue worker task starts at system startup and the ingest watcher catches up from its watermark. Confirm both are running after any reboot.
- A reboot during the Monday ingest is safe: ingestion is idempotent and resumes.
- A reboot that spans Monday 00:00 Asia/Kolkata expires unused allowance; that is a business loss, not a data loss, and must be recorded.

### 7.3 Task restart on failure

For each task, enable "Restart the task if it fails" with a 1-minute interval and 3 attempts. Enable "Run the task as soon as possible after a scheduled start is missed". Log the restart in the alert catalogue (section 8) so repeated failures are visible.

---

## 8. Network, health and monitoring

### 8.1 LAN access

- Give the PC a DHCP reservation or a static private IP (for example `192.168.1.50`).
- Optionally add a hosts entry or a local DNS record `pharmalead.local` pointing to that IP on each team machine.
- Bind the web server to the private interface. Do not bind MySQL to anything except `127.0.0.1`.

### 8.2 Firewall scope

```powershell
New-NetFirewallRule -DisplayName "PharmaLead HTTP" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
New-NetFirewallRule -DisplayName "PharmaLead Block Public" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Block -Profile Public
```

Allow port 8080 (and 8443, if HTTPS is enabled) on the **Private** profile only. Block on Domain and Public. Do not open MySQL (3306), the queue, or the scheduler to the network.

### 8.3 HTTPS decision point

The app is LAN-only and carries buyer personal data. Decide explicitly:

- **Stay HTTP on the LAN:** acceptable only while every client is on the trusted private network and the router does not forward port 8080. Revisit if anyone works remotely.
- **Enable HTTPS:** required the moment the app is reachable outside the LAN, or if the office Wi-Fi is shared with untrusted devices. Use a local certificate authority (for example `mkcert`) for `pharmalead.local` and set `SESSION_SECURE_COOKIE=true`.
- **Never expose the office PC directly to the internet.** If remote access is needed, use a VPN into the LAN; do not port-forward the web server.

Record the decision and its date in the operations log.

### 8.4 Health endpoint contract

`GET /up` (JSON when `Accept: application/json`, token-protected via `HEALTH_TOKEN`, also callable locally by `php artisan system:health-check`):

```json
{
  "status": "ok",
  "version": "1.4.2",
  "checked_at": "2026-10-22T09:15:00+05:30",
  "db": "ok",
  "queue": { "pending": 0, "failed": 0, "worker_running": true },
  "scheduler": { "last_tick_at": "2026-10-22T09:14:00+05:30" },
  "ingest": { "last_success_at": "2026-10-22T09:10:12+05:30", "last_status": "ok" },
  "allowance": { "week_start": "2026-10-19", "used": 48, "allotted": 75, "waste_inr": 0 },
  "disk": { "free_gb": 120 },
  "ai": { "provider": "null", "month_spend_usd": 0, "cap_usd": 5.0 },
  "backup": { "last_success_at": "2026-10-22T02:31:40+05:30" }
}
```

`status` is `ok` when all checks pass, `degraded` when a non-critical check fails (for example AI is over cap but deterministic scoring still works), and `down` when the database is unreachable or the scheduler has not ticked for 10 minutes.

### 8.5 Alert catalogue

| Alert | Trigger | Severity | Recipient | Channel |
|---|---|---|---|---|
| Scheduler stalled | No scheduler tick for 10 minutes | S1 | Developer | Email + SMS if configured |
| Queue backlog | `pending > 200` for 15 minutes, or `failed > 0` | S2 | Developer | Email |
| Ingest failed | Batch status `failed_schema` or `failed_connector` | S2 | Operator + developer | Email |
| Ingest gap | No successful ingest for 6 hours during a weekday | S2 | Operator + developer | Email |
| Allowance nearly exhausted | `used >= 70` of 75 | S3 | Operator | Email |
| Allowance mismatch | `used_count` differs from allocated-lead count | S1 | Developer | Email + SMS |
| Dashboard mismatch | Nightly reconciliation delta > 0 | S1 | Developer | Email |
| Backup failed | Backup task exits non-zero or the second copy missing | S2 | Developer | Email |
| Disk low | Free space < 20 GB | S2 | Developer | Email |
| AI cap reached | Month spend ≥ cap | S3 | Developer | Email |
| AI provider error | 5 consecutive provider failures | S3 | Developer | Email |
| Credential expired | Connector or mail returns an auth error | S2 | Developer | Email |
| Restore drill overdue | No successful drill in 35 days | S3 | Developer | Email |
| SSL certificate expiring | Fewer than 14 days (only if HTTPS enabled) | S3 | Developer | Email |

### 8.6 Daily digest

Sent at 07:30 Asia/Kolkata, Monday to Saturday, to the operator and the developer:

- Yesterday's leads: ingested, scored, contacted, untouched.
- Current week: used / 75, remaining, projected waste cost, days left.
- Top 5 untouched leads by score, with age in hours.
- Queue depth, failed jobs, last successful backup, last ingest, disk free.
- Any open S2 or S1 alert from the last 24 hours.

The digest is a notification, never an action. It must not contain buyer phone numbers or emails; it lists lead ids and company names only.

---

## 9. Routine operating procedure

### 9.1 The weekly loop (Monday to Friday, Asia/Kolkata)

| When | Step | Action |
|---|---|---|
| Monday 08:30 | Allowance check | Confirm the new week's allowance row exists: `allotted = 75`, `used = 0`, `week_start` = today. If missing, run `php artisan allowance:open-week` |
| Monday 09:00 | Export | Download the seller-portal export for the new week and confirm the row count against the portal's stated count (target 75) |
| Monday 09:15 | Ingestion | Drop the export into the watched folder or trigger `php artisan leads:pull --source=portal-export`. Watch the batch summary: inserted, skipped-duplicate, failed |
| Monday 09:30 | Review | Work the ranked inbox top-down. For each lead: read the score breakdown, confirm the matched SKU and destination, read the draft |
| Monday 10:00 | Contact | Edit the draft if needed, send via WhatsApp/email/phone by hand, then mark the lead contacted. This writes the activity row |
| Mon–Fri, twice daily | Follow-up | Filter by "awaiting reply" and by "untouched". Contact anything untouched older than 24 hours first |
| Daily 17:00 | Allowance check | Compare `used` against leads contacted. Investigate any gap before it compounds |
| Friday 15:30 | Allowance expiry check | Confirm the remaining allowance expires Monday 00:00 Asia/Kolkata and cannot be carried over. Contact every untouched `act_now` and `good` lead before close of business |
| Friday 16:00 | Weekly close | Confirm utilisation against 75, record waste cost, confirm next week's delivery expectation with the account manager |
| Friday 17:00 | Maintenance window | Apply Windows updates, deploy a new build if one is ready, run the weekly backup copy |

Rules:

- The system never sends a message. The operator sends every message and records it.
- Do not mark a lead contacted before the message is actually sent. The activity row is the evidence.
- Do not manually change a score. If a score is wrong, record the disagreement in the evaluation set and fix the matcher in a release.
- When the allowance is exhausted, stop attempting to allocate. Remaining leads are stored as `allowance_exhausted` and must not receive drafts.

### 9.2 If the machine was off

1. Start the PC and confirm the scheduler, queue worker and ingest watcher tasks are running.
2. Wait one scheduler tick (60 seconds) and one watcher cycle (5 minutes).
3. Check `/api/health`: `scheduler.last_tick_at` and `ingest.last_success_at` should both be recent.
4. Run `php artisan leads:pull --force` to catch up immediately rather than waiting for the next poll.
5. Review the batch log for `gap_detected`. Confirm the overlap window (`INDIAMART_LOOKBACK_HOURS=72`) covered the outage. If the outage was longer than 72 hours, extend the lookback for one run: `php artisan leads:pull --lookback-hours=<n>`.
6. If the outage crossed Monday 00:00, record the expired allowance in the waste metric and notify the operator.
7. Confirm the nightly backup ran on restart. If it did not, run `backup.ps1` manually and check the second-location copy.

---

## 10. Upgrade and rollback

### 10.1 Deploy a new build

1. Tag the release in git: `git tag -a v1.4.2 -m "M3 policy fix"`, then `git push --tags`.
2. On the office PC, enter maintenance mode: `php artisan down --retry=60`.
3. Fetch and check out the tag:

```powershell
Set-Location C:\PharmaLead\api
git fetch --tags
git checkout v1.4.2
composer install --no-dev --optimize-autoloader
```

4. Build the SPA:

```powershell
Set-Location C:\PharmaLead\web
npm ci
npm run build
```

5. Run migrations: `php artisan migrate --force`. Review the output for destructive operations before confirming.
6. Clear and rebuild caches:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

7. Restart the queue worker (stop the scheduled task, start it again) so it picks up new code.
8. Exit maintenance mode: `php artisan up`.
9. Verify `/up` returns `ok`, open the dashboard, and run one manual ingest in dry-run mode: `php artisan leads:pull --dry-run`.
10. Record the deployed tag and time in the operations log.

### 10.2 Roll back a release

1. Enter maintenance mode.
2. Check out the previous tag: `git checkout v1.4.1`, then `composer install --no-dev --optimize-autoloader`.
3. Rebuild the SPA from the previous tag.
4. **Migrations:** do not auto-roll-back. Inspect `php artisan migrate:status` and the new migrations. If they are additive (new tables or nullable columns), leave them in place. If they are destructive, restore the pre-deploy dump from section 6 into a scratch database, verify it, then restore production.
5. Rebuild caches and restart the queue worker.
6. Exit maintenance mode and re-run the health check and a dry-run ingest.
7. Write a short incident note: tag rolled back, reason, data impact, follow-up.

The previous release artefact (git tag + the matching `dist/` build) must be kept for at least the last 5 releases. A rollback that requires rebuilding from a tag that no longer builds is not a rollback path.

### 10.3 Roll back a model or policy change

Model and policy behaviour is controlled by flags and versioned configuration, not by redeploying code:

1. Turn the affected flag off in `.env` (`ENGINE_M4_LLM_EXTRACTION=off`, `ENGINE_M5_LLM_ADJUDICATION=off`, `ENGINE_M6_CONFIDENCE_GATING=off`).
2. `php artisan config:clear; php artisan config:cache`.
3. Restart the queue worker.
4. For a scoring-weight change, restore the previous weights file from git and run `php artisan policy:sync`.
5. Re-run the evaluation harness on the frozen holdout and confirm the metrics return to the previous baseline before leaving the change off.
6. Record the rollback and the metric that triggered it.

Because v1 has no live LLM, the common rollback is simply M2/M3 flag off, which returns the system to the last known deterministic baseline. In v1.1, shadow mode means an LLM rollback never affects the score the operator sees; only the shadow comparison is lost.

---

## 11. Troubleshooting

| # | Symptom | Likely cause | Fix |
|---|---|---|---|
| 1 | Queue not draining; leads never score | Worker task stopped, crashed, or restarted loop exhausted; `QUEUE_CONNECTION` mismatch | Check `Get-ScheduledTask -TaskName "PharmaLead Queue Worker"`; inspect `storage/logs/laravel.log`; run `php artisan queue:work --once` manually; confirm `QUEUE_CONNECTION=database`; restart the task |
| 2 | Ingestion partially failed | Connector timeout or portal returned a partial page | Check the batch log's resume offset; re-run `php artisan leads:pull --resume=<batch_id>`; confirm total equals the portal count; idempotency prevents duplicates |
| 3 | Duplicate batch detected | Same export ingested twice, or two watcher instances running | Confirm only one watcher task exists; the unique index `(source, external_id)` should have blocked inserts — if it did not, stop and treat as an S1 defect; reconcile counts |
| 4 | Allowance mismatch | A lead allocated without a ledger debit, or a manual status change | Run `php artisan allowance:reconcile`; compare `used_count` to allocated leads; if they differ, treat as S1, freeze ingestion, and repair from the audit log |
| 5 | Dashboard total mismatch | Cached aggregates stale, or a direct SQL change bypassed the app | Run the nightly reconciliation manually; clear the cache with `php artisan cache:clear`; investigate any source row changed outside the app |
| 6 | Mail not sending | Wrong SMTP host/port, expired app password, provider rate limit, firewall on 587 | Test `php artisan tinker` with `Mail::raw('test', fn($m) => $m->to('<you>')->subject('test'))`; check provider logs; verify `MAIL_*`; switch to port 465 with `ssl` if 587 is blocked |
| 7 | AI cap reached | Month spend hit `AI_MONTHLY_CAP_USD` | Expected behaviour: the system continues on the deterministic path. Do not raise the cap without a written decision; wait for the month to roll over or switch to `AI_PROVIDER=null` explicitly |
| 8 | Credential expired | IndiaMART or mail auth error | Rotate per section 5.4; update `.env`; `config:clear; config:cache`; restart the queue worker; run one dry-run ingest |
| 9 | Disk space low | Logs, backups or MySQL binlogs grew | Clear `storage/logs` older than 14 days; prune backups per retention; `PURGE BINARY LOGS BEFORE ...`; move archives to the second location; alert threshold is 20 GB free |
| 10 | Port conflict (8080 or 3306 already in use) | Another web server, IIS, or a second MySQL instance | `netstat -ano \| findstr :8080` then `Get-Process -Id <pid>`; stop the conflicting service or move PharmaLead to another port and update `APP_URL` and the firewall rule |
| 11 | Database locked / deadlock errors | Long transaction or concurrent allocation without retry | Confirm the allocation retry policy is active; find the blocking session with `SHOW PROCESSLIST`; kill only if idle and safe; add the missing retry before the next release |
| 12 | Backup failure | mysqldump missing, wrong password, disk full, second location unreachable | Run `backup.ps1` manually and read the thrown error; fix credentials/path; confirm `D:\PharmaLead\backups` is mounted; re-run immediately and verify both copies exist |
| 13 | Scheduler not firing | Task disabled, wrong working directory, PHP path changed after an upgrade | Check `Get-ScheduledTask`; run the command manually from `C:\PharmaLead\api`; re-point the action to the current PHP path; confirm the PC is not asleep |
| 14 | Wrong week boundary or expiry time | Time zone drift between Windows, PHP and MySQL | Set Windows to Asia/Kolkata, `APP_TIMEZONE=Asia/Kolkata`, MySQL `default-time-zone='+05:30'`; restart MySQL and the worker; re-check the allowance row's `week_start` |
| 15 | Static assets 404 after deploy | SPA rebuilt but the web root still points at the old `dist/` | Confirm the web server document root is `C:\PharmaLead\web\dist`; restart the web server; confirm the build hash changed in `index.html` |
| 16 | Session lost on every request | `APP_KEY` changed, session table missing, or cookie domain mismatch | Confirm `APP_KEY` is stable; run `php artisan migrate` for the sessions table; align `SESSION_DOMAIN` with the host used in the browser |

Escalate to the developer for any S1 symptom after the first fix attempt. Never "fix" an allowance or dashboard mismatch by editing rows directly in MySQL; repair through the application so the audit trail stays intact.
