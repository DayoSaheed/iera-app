# Deploying the iERA App on cPanel

This guide takes the app from the release zip to a live subdomain. Replace `app.iera.ca` with the subdomain you choose and `cpuser` with your cPanel username.

## 1. Subdomain and PHP

1. cPanel → **Domains** → *Create A New Domain*: `app.iera.ca`.
   * Untick *Share document root*.
   * Document root: **`iera-app/public`** (the app's code and secrets then live in `/home/cpuser/iera-app`, one level above anything the web can reach).
   * If you see an "Index of /" listing with folders like `app/` and `config/`, the document root is `iera-app` instead of `iera-app/public`. Change it in cPanel → Domains → *Manage* → Document Root. The app also ships a root `.htaccess` that routes everything into `public/` and blocks the private folders, so it keeps working either way, but the `public/` document root is the correct setup.
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

cPanel → **MySQL® Databases**: create a database (e.g. `cpuser_iera`), a user (e.g. `cpuser_iera`) with a long generated password, and add the user to the database with **ALL PRIVILEGES**. Keep the three values for the wizard.

Optional: import `sql/schema.sql` with phpMyAdmin now. If you skip this, the wizard creates the tables for you.

## 5. Upload the application

1. cPanel → **File Manager** (or Terminal with `git clone https://github.com/DayoSaheed/iera-app.git iera-app`): the code must end up in `/home/cpuser/iera-app` so that the subdomain's document root `iera-app/public` contains `index.php`.
2. Make sure `storage/` is writable by PHP (default cPanel permissions are fine).
3. Optional: copy `config/config.sample.php` to `config/config.php` and fill in the database details. If you do not, the wizard asks for them and writes the file.

## 6. Run the setup wizard

Open `https://ieraca.wees.bio` (your subdomain). The wizard runs only until the first administrator exists:

1. **Requirements**: PHP version, extensions and writable folders.
2. **Database**: shows whether the connection from `config.php` works. If there is no config file, enter the database details and the wizard writes it. If the tables are missing, click **Create the tables now** (or import `sql/schema.sql` in phpMyAdmin and click Re-check).
3. **Application**: name, organisation, URL (detected), time zone, support and notification emails.
4. **Administrator**: your name, email and password. This creates the first superadmin + system admin and signs you in.

Then, in **System Admin → Settings**:

* **Email**: choose `smtp` and enter the cPanel mailbox (host `localhost`, port 465, `ssl`, the mailbox address and password), or `sendgrid` with an API key. Click **Save and send test email to me**. Sign-in codes for all other users depend on this.
* **Notifications, New Muslim, Volunteers**: the addresses, WhatsApp links, documents URL and training video.
* **Stripe Identity** and **SharePoint**: only if you keep those integrations.
* **Security**: require two-step sign-in for admins once email delivery works.

All of these are stored in the database (secrets encrypted with the application key); `config/config.php` only holds the database connection and the application URL/key.

## 7. Scheduled jobs

Three options, use any (or several):

* **cPanel cron** (recommended): Cron Jobs → every 5 minutes: `*/5 * * * * /usr/local/bin/php /home/cpuser/iera-app/cron/run.php >/dev/null 2>&1` (use the PHP path cPanel shows, e.g. `/usr/local/bin/ea-php82`).
* **Manually**: System Admin → Scheduled jobs → *Run now* per job, or *Run all jobs now*.
* **External pinger**: set a *Scheduler web key* in Settings → System and have any uptime/cron service request `https://ieraca.wees.bio/cron/run?key=THEKEY` every 5 minutes.

The scheduler decides internally which jobs are due, so running it more often is harmless.

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

- [ ] Setup wizard completed; `config.php` has `debug => false` and the HTTPS URL
- [ ] AutoSSL certificate valid, HTTP redirects to HTTPS
- [ ] Cron entry added and **Scheduler: OK** on the System Admin overview
- [ ] Settings → Email configured, test email received, SPF/DKIM green
- [ ] Settings filled in (emails, links, video)
- [ ] Admin two-step sign-in enabled (Settings → Security → *Require two-step sign-in for admins*)
- [ ] cPanel automatic backups on; **System Admin → Download SQL backup** tested once
- [ ] Old Netlify/AWS deployment switched off and its secrets rotated (MongoDB, SendGrid, Stripe, Microsoft)

## Updating later

Upload the new zip over the existing folder (never overwrite `config/config.php` or `storage/`), then open **System Admin → Overview** and click **Apply database updates** if the "Things to fix" list asks for it (the same as running `php tools/migrate.php` from the Terminal). The "Things to fix" list on the super admin dashboard also tells you about missing email settings, cron, permissions and other setup problems, each with a link to where it is fixed.

## Troubleshooting

* **500 error / blank page**: check `storage/logs/php-errors.log` and `storage/logs/app.log`; set `debug => true` briefly to see the message.
* **Codes not arriving**: System Admin → Email log shows every send and the error returned by the mail server.
* **Scheduler stale**: the cron command path is wrong or PHP CLI differs; run the command once manually in Terminal.
* **Uploads failing**: raise `upload_max_filesize`/`post_max_size` in Select PHP Version → Options.
