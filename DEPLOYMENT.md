# Deploying the iERA App on cPanel

This guide takes the app from the release zip to a live subdomain. Replace `app.iera.ca` with the subdomain you choose and `cpuser` with your cPanel username.

## 1. Subdomain and PHP

1. cPanel → **Domains** → *Create A New Domain*: `app.iera.ca`.
   * Untick *Share document root*.
   * Document root: **`iera-app/public`** (the app's code and secrets then live in `/home/cpuser/iera-app`, one level above anything the web can reach).
2. cPanel → **MultiPHP Manager** → select the subdomain → **PHP 8.2** or newer.
3. cPanel → **Select PHP Version** → *Extensions*: tick `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`.
   Under *Options* set `upload_max_filesize` and `post_max_size` to at least 16M.

## 2. DNS

In the DNS zone of `iera.ca` (wherever the domain is managed: cPanel Zone Editor, Cloudflare, the registrar) add:

| Type | Name | Value |
|---|---|---|
| A | `app` | the server's IPv4 address |

If the zone is on Cloudflare, keep the record **DNS only** (grey cloud) until AutoSSL has issued the certificate, then you may proxy it. Propagation usually takes minutes, up to a few hours.

## 3. SSL

cPanel → **SSL/TLS Status** → tick `app.iera.ca` → *Run AutoSSL*. Wait until a valid certificate shows. The app's `.htaccess` redirects HTTP to HTTPS and cookies are marked Secure.

## 4. Database

1. cPanel → **MySQL® Databases**: create a database (e.g. `cpuser_iera`), a user (e.g. `cpuser_iera`) with a long generated password, and add the user to the database with **ALL PRIVILEGES**.
2. cPanel → **phpMyAdmin** → select the database → *Import* → `sql/schema.sql` → Go. You should see 25 tables.

## 5. Upload the application

1. Build the release zip on your computer: `./scripts/package.sh` (creates `dist/iera-app-YYYYMMDD-HHMM.zip`, without secrets, tests or screenshots).
2. cPanel → **File Manager** → home folder → create `iera-app` → upload the zip into it → right-click → *Extract* → delete the zip.
3. The result must look like:
   ```
   /home/cpuser/iera-app/
   ├── public/        ← subdomain document root
   ├── app/  config/  sql/  cron/  tools/  storage/  data/
   ```
4. Make sure `storage/` and its sub-folders are writable by PHP (permissions 750 or 755 are fine on cPanel because PHP runs as your user).

## 6. Configure

1. Copy `config/config.sample.php` to `config/config.php` and edit:
   * `app.url` → `https://app.iera.ca`, `app.debug` → `false`, `app.timezone` → `America/Toronto`
   * `app.key` → 64 random hex characters (`php -r "echo bin2hex(random_bytes(32));"`)
   * `db.*` → the database name, user and password from step 4
   * `mail.driver` → `smtp` with the cPanel mailbox credentials (host `mail.iera.ca` or `localhost`, port 465 `ssl` or 587 `tls`), or `sendgrid` with an API key
   * `stripe.*` and `sharepoint.*` only if you keep those integrations (they are off by default)
   * `cron.secret` → a random string, only needed if you cannot use cPanel cron
2. Load the defaults and create your admin account from cPanel → **Terminal** (or SSH):
   ```bash
   cd ~/iera-app
   php tools/install.php
   php tools/create-admin.php admin@iera.ca Admin Name
   ```
   If Terminal is not available, the defaults are loaded automatically on first use and you can create the admin by signing in once with your email, then running the `create-admin` step later, or by temporarily inserting the row in phpMyAdmin (`type = superadmin`, `status = approved`, `is_sysadmin = 1`).
3. Open `https://app.iera.ca`, choose **Volunteer**, enter the admin email and the emailed code.
4. Go to **System Admin → Settings** and fill in the organisation name, support and notification emails, WhatsApp group links, documents URL and training video.
5. **System Admin → Overview** shows the health checks; everything should be green.

## 7. Cron

cPanel → **Cron Jobs** → add, every 5 minutes:

```
*/5 * * * * /usr/local/bin/php /home/cpuser/iera-app/cron/run.php >/dev/null 2>&1
```

(Use the PHP binary path cPanel shows, e.g. `/usr/local/bin/ea-php82`.) The scheduler decides internally which jobs are due; **System Admin → Scheduled jobs** shows every run and lets you run any job manually.

## 8. Email

Create the sender mailbox (e.g. `no-reply@iera.ca`) in cPanel → **Email Accounts** and make sure SPF/DKIM are enabled under **Email Deliverability**, otherwise codes and reminders land in spam. Send yourself a test from **System Admin → Email templates → Send test email**.

## 9. Migrating the data from the old app

1. From the old MongoDB (Atlas), export each collection:
   ```bash
   mongoexport --uri "$MONGO_URI" --db iera_db --collection iera_teams --jsonArray --out iera_teams.json
   # repeat for iera_users, iera_events, iera_sub_events, iera_reports, additional_user_info, form_templates
   ```
2. Import them **in this order** from **System Admin → Import** (or `php tools/import.php <collection> <file>` for large files): teams → users → events → sub_events → reports → additional_user_info → form_templates.
3. Check the counts in **Admin Dashboard** and **System Admin → Overview**, then ask a few volunteers to sign in with their email. Their user IDs are preserved, so existing report and attendance history stays linked.
4. Optional: skip the `form_templates` import to keep the improved default forms shipped with this app.

## 10. Go-live checklist

- [ ] `config.php` has `debug => false`, a real `app.key`, HTTPS URL
- [ ] AutoSSL certificate valid, HTTP redirects to HTTPS
- [ ] Cron entry added and **Scheduler: OK** on the System Admin overview
- [ ] Test email received, SPF/DKIM green
- [ ] Settings filled in (emails, links, video)
- [ ] Admin two-step sign-in enabled (Settings → Security → *Require two-step sign-in for admins*)
- [ ] cPanel automatic backups on; **System Admin → Download SQL backup** tested once
- [ ] Old Netlify/AWS deployment switched off and its secrets rotated (MongoDB, SendGrid, Stripe, Microsoft)

## Updating later

Upload the new zip over the existing folder (never overwrite `config/config.php` or `storage/`), then run `php tools/migrate.php` from the Terminal to apply any new files in `sql/migrations/`.

## Troubleshooting

* **500 error / blank page**: check `storage/logs/php-errors.log` and `storage/logs/app.log`; set `debug => true` briefly to see the message.
* **Codes not arriving**: System Admin → Email log shows every send and the error returned by the mail server.
* **Scheduler stale**: the cron command path is wrong or PHP CLI differs; run the command once manually in Terminal.
* **Uploads failing**: raise `upload_max_filesize`/`post_max_size` in Select PHP Version → Options.
