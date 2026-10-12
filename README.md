# iERA App (PHP)

A complete rebuild of the iERA volunteer and outreach portal in **pure PHP 8.2+ and MySQL**, with no framework and no build step. It runs on ordinary cPanel shared hosting.

## What it does

| Area | Features |
|---|---|
| Sign-in | Step-by-step: account type → email → one-time code (or password) → two-step confirmation for admins. Rate limited, audited, remember-me. |
| Registration | Multi-step volunteer and New Muslim forms defined in editable form templates, ID upload, pending-status timeline, training video acknowledgement, optional Stripe Identity. |
| Teams | My Teams, pending volunteers, lead management, team membership and leads. |
| Street Dawah | Calendar, recurring event series with automatic occurrences, event registration with time slots, lead attendance marking, event reports generated when an event ends. |
| New Muslim | Mentor assignment, accept/decline, weekly mentor reports with lesson-sequence check, mentee pages, comments, handbooks. |
| Reports | Pending/submitted lists with filters and CSV, submit on behalf (leads), escalation emails. |
| Admin | Volunteers / New Muslims / Organizations / Pending lists, full user editor, status workflow with emails, teams, analytics with charts and CSV, Da'ee activity. |
| System Admin | Settings, email templates (with test send), form template editor, scheduled jobs with run-now, email log, audit log, admin accounts, two-step reset, maintenance mode, SQL backup, legacy MongoDB import, impersonation. |
| Automation | 15 scheduled jobs (reports, reminders, inactivity, SharePoint sync, exports, housekeeping) driven by one cron entry. |

## Requirements

* PHP 8.1+ (8.2 or 8.3 recommended) with `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`
* MySQL 5.7+ / MariaDB 10.3+
* Apache with `mod_rewrite` (cPanel default) or any server that routes to `public/index.php`

## Quick start (local)

```bash
cp config/config.sample.php config/config.php     # edit the database and mail settings
php tools/install.php                             # creates tables and loads defaults
php tools/create-admin.php you@example.org You    # first superadmin + system admin
php -S 127.0.0.1:8085 -t public tools/dev-server.php
```

Open http://127.0.0.1:8085, choose **Volunteer**, enter the admin email, and read the code from `storage/logs/mail.log` (mail driver `log`).

Run the automated checks with `php tests/smoke.php` (uses a throwaway SQLite database; set `IERA_SMOKE_MYSQL=1` plus `IERA_DB_*` variables to run them against MySQL).

## Project layout

```
public/          document root (index.php, .htaccess, assets)
app/Core         router, request/response, database, session, auth, CSRF, views, validator, mailer
app/Controllers  one class per module
app/Models       database access (users, teams, events, reports, ...)
app/Services     dynamic forms, email, recurrence, report generation, stats, jobs, integrations, importer
app/Views        PHP templates (layouts, partials, one folder per module)
config/          config.sample.php -> copy to config.php (never committed)
sql/             schema.sql and migrations/
cron/run.php     scheduler entry point (every 5 minutes)
tools/           install, create-admin, migrate, import, dev-server
storage/         uploads, logs, cache, backups, exports (outside the web root)
tests/           smoke test
```

See **DEPLOYMENT.md** for the cPanel deployment, DNS and migration steps.
