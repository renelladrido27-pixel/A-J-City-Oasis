# Deploying A & J OASIS to Hostinger

Everything below is done once. After that, updating the live site is one
command (`bash deploy.sh`, see the end).

Throughout, replace:

| Placeholder | Example |
|---|---|
| `YOURDOMAIN` | `ajoasis.online` |
| `u123456789` | your Hostinger SSH username (shown in hPanel) |

---

## 1. Buy the plan (in the browser)

- Choose a plan **with SSH access** (Premium or Business — check the plan's
  feature list for "SSH access"). The cheapest "Single" tier may not have it.
- Claim the free domain that comes with the plan.

## 2. hPanel settings (in the browser)

1. **Websites → Manage → Advanced → PHP Configuration** → set PHP **8.3**
   (8.2 minimum).
2. **Databases → MySQL Databases** → create a database and user. Write down
   the full names Hostinger gives them (e.g. `u123456789_ajoasis`) and the
   password.
3. **Advanced → SSH Access** → enable it and note the host, port (usually
   `65002`) and username.
4. **Security → SSL** → make sure the free SSL certificate is installed for
   the domain.

## 3. Install the app (over SSH)

From your laptop (PowerShell or Git Bash):

```bash
ssh -p 65002 u123456789@YOURSERVERIP
```

Then on the server:

```bash
php -v        # must say 8.2 or higher
cd ~/domains/YOURDOMAIN
git clone https://github.com/renelladrido27-pixel/A-J-City-Oasis.git app
cd app
composer2 install --no-dev --optimize-autoloader   # or "composer" if composer2 isn't found
cp .env.production.example .env
nano .env
```

In `nano`, fill in the blanks (arrow keys to move, **Ctrl+O Enter** to save,
**Ctrl+X** to exit):

- `APP_URL=https://YOURDOMAIN`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 2
- `PUSHER_*`, `MAIL_PASSWORD`, `XENDIT_SECRET_KEY` — same values as your
  local `.env` (never paste them into GitHub or a group chat)
- leave `XENDIT_CALLBACK_TOKEN` empty for now (step 6)

Then:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # properties, the 53 rooms, sample room photos
php artisan storage:link
php artisan app:create-admin OWNER@EMAIL.COM --name="Jose Valle"
php artisan app:create-admin STAFF@EMAIL.COM --name="Caretaker" --role=staff
php artisan optimize
```

`app:create-admin` asks for the password at a hidden prompt. The demo
`admin@ajoasis.test / password` accounts are **not** created on the server —
that password is public on GitHub.

If `storage:link` fails ("symlink() has been disabled"), run instead:

```bash
ln -s ../storage/app/public public/storage
```

## 4. Point the domain at Laravel's `public` folder

```bash
cd ~/domains/YOURDOMAIN
mv public_html public_html_old
ln -s app/public public_html
```

Open `https://YOURDOMAIN` — the A & J OASIS home page should load.

## 5. Cron job (in the browser)

**Advanced → Cron Jobs → Custom**, run **every minute** (`* * * * *`):

```
/usr/bin/php /home/u123456789/domains/YOURDOMAIN/app/artisan schedule:run
```

(Check the PHP path with `which php` over SSH if `/usr/bin/php` doesn't work.)
This runs the daily overdue-payment check and expires unpaid bookings.

## 6. Xendit webhook (in the Xendit dashboard, Test mode)

1. **Settings → Webhooks** → set the **Invoices paid** URL to
   `https://YOURDOMAIN/api/webhooks/xendit`.
2. Copy the **Verification token** shown on that page.
3. On the server: `nano .env` → `XENDIT_CALLBACK_TOKEN=` paste → save, then:

```bash
php artisan optimize
```

> Any time you edit `.env` on the server, run `php artisan optimize`
> afterwards — the settings are cached and changes are ignored until then.

## 7. Check everything

```bash
php artisan app:doctor
```

Every line should be ✓. Paste the output to Claude if anything shows ✗.
(The cron line turns ✓ a minute or two after step 5.)

## 8. Mobile app

Build the Android app against the live server (on your laptop, from
`A-J-City-Oasis/mobile`):

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://YOURDOMAIN/api
```

The APK is at `mobile/build/app/outputs/flutter-apk/app-release.apk`.

---

## Updating the live site later

Push changes to GitHub as usual, then on the server:

```bash
cd ~/domains/YOURDOMAIN/app
bash deploy.sh
```

It puts the site in maintenance mode, pulls the code, installs packages,
runs migrations, re-caches, brings the site back up and runs `app:doctor`.
