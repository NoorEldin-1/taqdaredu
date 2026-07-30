# taqdaredu.com — local setup

> **Read this instead of `CLAUDE.md`.** That file was inherited from the
> project this codebase was cloned from (**My-Communication Academy**,
> `my-communication.uk`) and still describes *that* site — wrong domain, wrong
> paths, wrong local folder name. Same goes for `api/front/DEPLOYMENT.md` and the
> `AUDIT-*.md` reports. Treat all of them as historical notes, not instructions.

## What this repo is

The source of **taqdaredu.com**, an Arabic e-learning platform. The repo mirrors
the deployed web root (`/home/taqdaredu.com/public_html` on the server), so paths
here match paths in production one-for-one.

| Path | What it is |
| --- | --- |
| `api/` | **CodeIgniter 3** backend (PHP 8.2), served under the `/api/` URL prefix |
| `api/front/` | **React 18 + TypeScript + Vite + shadcn/ui + Tailwind** frontend *source* |
| `_app/`, `index.html` | the **built** frontend, as currently deployed |
| `api/assets/` | admin-panel CSS/JS/images shipped with the base LMS template |
| `api/uploads/` | user-uploaded media (course thumbnails, avatars…) |

Yes — the frontend source lives *inside* `api/`. That is inherited, not a
choice; the `api/` directory is both the PHP app and the React project root.

## Prerequisites

PHP 8.2+, Composer, Node 20+, MySQL/MariaDB, Apache with `mod_rewrite`.
On Windows, XAMPP covers Apache + PHP + MariaDB.

## Setup

### 1. Dependencies

```bash
cd api        && composer install
cd api/front  && npm install
```

Neither `api/vendor/` nor `api/front/node_modules/` is tracked.
`api/vendor/` is ~190 MB, 176 MB of which is `google/apiclient-services`.

### 2. Config files (not tracked — they hold secrets)

```bash
cd api/application/config
cp database.php.example database.php   # fill in your local DB credentials
cp secrets.php.example  secrets.php    # fill in encryption_key
```

`config.php` merges `secrets.php` into `$config` when the file exists. Without
it, `encryption_key` stays empty and CodeIgniter's Encryption library refuses to
run — deliberately, so a missing secret fails loudly instead of silently falling
back to a key that would be public in this repo.

Generate an `encryption_key` with:

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```

> The production `encryption_key` is **not** in this repo and must not be
> changed casually: rotating it invalidates every existing session and anything
> already encrypted with the old key.

`base_url` needs no configuration — `config.php` derives it from
`$_SERVER['HTTP_HOST']`, so the app works on any hostname or port.

### 3. Frontend env

```bash
cd api/front
cp .env.example .env.local
```

### 4. Database

```bash
mysql -u root -e "CREATE DATABASE taqdaredu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root taqdaredu < your-dump.sql
```

Dumps are never committed (`*.sql` is ignored) — they carry real user data. Take
one from the server with `mysqldump`.

**Gotcha:** the server runs MariaDB 10.11, whose `mysqldump` prefixes the file
with `/*M!999999\- enable the sandbox mode */`. Older clients (e.g. MariaDB 10.4
in XAMPP) fail on that line with `Unknown command '\-'`. Strip it first:

```bash
sed -i '1{/^\/\*M!999999/d}' your-dump.sql
```

### 5. Apache vhost

The root `.htaccess` uses `RewriteBase /` plus rules such as `RewriteRule ^api/`,
so **the site must sit at a document root** — dropping it into a subfolder of
`htdocs` breaks routing. Give it its own port instead:

```apache
# xampp/apache/conf/extra/httpd-vhosts.conf
Listen 8081

<VirtualHost *:8081>
    ServerName localhost
    DocumentRoot "C:/work/projects/taqdaredu"
    <Directory "C:/work/projects/taqdaredu">
        Options Indexes FollowSymLinks Includes ExecCGI
        AllowOverride All          # required — the project leans on .htaccess
        Require all granted
    </Directory>
    ErrorLog  "logs/taqdaredu-error.log"
    CustomLog "logs/taqdaredu-access.log" common
</VirtualHost>
```

Then `httpd -t` to check syntax and restart Apache. `.htaccess` already exempts
`localhost`, `127.0.0.1` and `tagdar.local` (with any port) from its
force-HTTPS rule, so plain HTTP works locally.

## Running

| | |
| --- | --- |
| Full site, as deployed | <http://localhost:8081/> |
| Admin panel | <http://localhost:8081/api/login> |
| Frontend with hot reload | `cd api/front && npm run dev` → <http://localhost:8080/> |

In dev the Vite server proxies `/api/*` to `VITE_DEV_API_TARGET`. It also
rewrites `Authorization: Bearer <token>` into a `?auth_token=` query parameter,
because Apache-on-Windows behind the proxy hop drops the header before PHP sees
it. The backend accepts both.

## Build & deploy

There is **no CI/CD** — it is manual.

```bash
cd api/front
npm run build                      # → api/front/dist/
cp dist/index.html ../../index.html
cp -r dist/_app/. ../../_app/
```

Root `index.html` is hand-tuned (inline splash screen + CSP) *and* references the
hashed filenames inside `_app/`, which is why both are tracked here. If you edit
it directly, mirror the change into `api/front/index.html` too — otherwise the
next build overwrites you.

Never hand-edit `_app/*.js` or `_app/*.css`. They are build output.

## Inherited quirks worth knowing

- **`api/application/config/rest.php`** is the REST-controller config; API keys
  live in the `api_keys` table, not in files.
- **SMTP and payment-gateway credentials live in the database** (`settings`,
  `payment_gateways`), not in config files.
- **`ENVIRONMENT` defaults to `production`** (`api/index.php`), which suppresses
  PHP errors. Set `CI_ENV=development` in the vhost to see them locally:
  `SetEnv CI_ENV development`.
- **The previous workflow left artefacts in the web root** — ~40 `deploy_*.zip`,
  nine `index.html.bak-*` files, `_backup_audit_*/`, `_rollback_snapshot_*/`,
  `eruda.js` (a debug console!), and `myco_uk (2).sql`. None are in this repo,
  but they are **still on the live server** and some are publicly reachable.
- **`api/assets/backend/login/custom.js`** contains a Google Maps API key
  that ships with the base LMS template. It is not ours and is already public in
  every copy of that template.
