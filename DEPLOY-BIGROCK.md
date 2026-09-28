# Deploying IPO Darbaar on BigRock (cPanel)

This guide assumes a BigRock Linux shared plan with SSH access and PHP 8.3 or newer. Roll out on a staging subdomain first (for example `beta.your-domain.in`), check Admin → System, then point the main domain at it.

## 1. One-time cPanel setup

1. **PHP version**: cPanel → *MultiPHP Manager* → set the domain to PHP 8.3+. Under *Select PHP Version → Extensions*, make sure `gd`, `pdo_mysql`, `mbstring`, `intl`, `fileinfo` and `openssl` are ticked.
2. **Find the PHP CLI binary** over SSH. It is usually `/opt/cpanel/ea-php83/root/usr/bin/php`:
   ```bash
   ls /opt/cpanel/ea-php8*/root/usr/bin/php
   ```
   Use this path wherever `php` appears below (or `export PHP_BIN=...`).
3. **MySQL**: cPanel → *MySQL Databases*. Create a database and a user (both get your cPanel username as a prefix), then add the user to the database with *All privileges*.
4. **Mailbox for sending**: cPanel → *Email Accounts*. Create `noreply@your-domain.in`. SMTP host is `mail.your-domain.in`, port 465 (SSL).
5. **Email deliverability**: cPanel → *Email Deliverability*. Repair/enable SPF and DKIM for the domain so digests don't land in spam.
6. **SSL**: cPanel → *SSL/TLS Status* → run AutoSSL for the domain (and the staging subdomain).

## 2. Get the code onto the server

```bash
cd ~
git clone <your-repo-url> ipodarbar
cd ipodarbar
curl -sS https://getcomposer.org/installer | $PHP_BIN   # creates composer.phar if composer isn't installed
cp .env.production.example .env
nano .env                                                # fill in APP_URL, DB_*, MAIL_*, IPO_API_KEY, TELEGRAM_*
$PHP_BIN composer.phar install --no-dev --optimize-autoloader
$PHP_BIN artisan key:generate
chmod -R ug+rwx storage bootstrap/cache
```

## 3. Point the web root at `public/`

**Option A (preferred): symlink.** Move the old web root aside and link it to the app:

```bash
mv ~/public_html ~/public_html.old
ln -s ~/ipodarbar/public ~/public_html
```

For a subdomain, set its document root in cPanel → *Domains* to `ipodarbar/public` instead.

**Option B: copy.** Some plans don't allow a symlinked `public_html`. Then:

1. Copy `deploy/bigrock/public_html-index.php` to `~/public_html/index.php`.
2. Set `APP_PUBLIC_PATH=/home/<user>/public_html` in `.env`.
3. Deploy with `PUBLIC_HTML=~/public_html bash deploy/bigrock/deploy.sh`, which copies the assets on each deploy.

Generated logos (`/logos`) and share cards (`/og`) are written into the public folder on first request and then served as static files.

## 4. First run

```bash
$PHP_BIN artisan migrate --force
$PHP_BIN artisan ipo:sync --full                 # first import (~30 s)
$PHP_BIN artisan darbaar:admin you@example.com   # prompts for a password
$PHP_BIN artisan optimize
```

## 5. Cron (one entry runs everything)

cPanel → *Cron Jobs* → *Every minute*:

```
* * * * * cd ~/ipodarbar && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

This runs the 15-minute IPO sync, the 03:30 full sync, Telegram posts (09:00 digest, 18:30 GMP board, new-IPO alerts), the 08:00 email digest and a short queue worker that sends queued mail. While cron is healthy, page requests never trigger a sync.

## 6. Check it works

Log in at `/admin` and open **System**. It should show:

- PHP 8.3+ with `gd`, `webp`, `freetype` and `pdo_mysql` available;
- a cron heartbeat from the last minute and a recent sync time;
- zero failed queue jobs.

Use the buttons there to send a test email and a test Telegram message. Then set up ads, analytics and broker links in **Settings**.

## 7. Search engines

1. **Google Search Console**: add the domain property, choose the *HTML tag* method and paste the tag in Admin → Settings → Search engines. Then submit `https://<your-domain>/sitemap.xml`.
2. **Bing Webmaster Tools**: import from Search Console, or verify with the meta tag in the same Settings card.
3. **IndexNow**: put a random key in `.env` as `INDEXNOW_KEY=` (for example the output of `openssl rand -hex 16`), then run `$PHP_BIN artisan optimize`. Check that `/indexnow-key.txt` shows the key. The scheduler submits changed pages every 30 minutes.
4. Add the site's Telegram, X, YouTube and other profile links in Settings so they appear in the Organization schema.

## Updating

```bash
cd ~/ipodarbar && PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy/bigrock/deploy.sh
```

The script pulls the latest code, puts the site in maintenance mode, installs dependencies, migrates, rebuilds caches and brings the site back up.

## Switching the main domain

1. Confirm staging works end to end (System page green, test email and Telegram received).
2. Repeat steps 2–5 for the main domain's web root (or point it at the same app folder).
3. Update `APP_URL` in `.env`, run `$PHP_BIN artisan optimize`.
4. After DNS points to BigRock and the site is live, delete the old Vercel project.
