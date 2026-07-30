# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this directory is

This is the **deployed web root** (`C:\xampp\htdocs\body`) for **My-Communication Academy** (`my-communication.uk`), an LMS. It is *not* a clean source repo — it is the served file tree. Two applications live here:

- **Backend** — an "Academy LMS" **CodeIgniter 3** app under `api/`, served from the `/api/` URL prefix. `api/` is its own git repository; the `body/` root is **not** version-controlled.
- **Frontend** — a **React 18 + TypeScript + Vite + shadcn-ui + Tailwind** SPA. Source lives in **`api/front/`**; the *built* output is copied to the web root as `index.html` + `_app/`.

So the same `api/` tree contains both the PHP backend (`api/application`, `api/system`) and the React frontend source (`api/front`).

## Build, deploy, lint

The React source is in `api/front/`. All npm commands run from there:

```bash
cd api/front
npm install            # or: bun install  (bun.lockb is present)
npm run dev            # Vite dev server on :8080, proxies /api_* to http://localhost/MyCommcation
npm run build          # vite build  +  node scripts/defer-css.mjs  → api/front/dist/
npm run lint           # eslint
```

`npm run build` output lands in `api/front/dist/` (`dist/index.html`, `dist/_app/`). **Deploying to the web root means copying that output up two levels:**

```bash
cp api/front/dist/index.html index.html          # web-root SPA entry
cp -r api/front/dist/_app/. _app/                # hashed JS/CSS/asset bundles
```

Production releases are then packaged as `deploy_*.zip` at the web root and uploaded to the live server (cPanel/FTP). There is **no CI/CD** — build and deploy are manual. See `api/front/DEPLOYMENT.md` (Arabic) for the full server-side procedure.

There is no automated test suite. Verification is done against the live site (`https://my-communication.uk`) with `curl` / PageSpeed, or the local XAMPP install.

## Editing rules (important)

- **Never hand-edit `_app/*.js`, `_app/*.css`, or root `index.html` as the source of truth** — they are Vite build artifacts. Change `api/front/src/**` (or `api/front/index.html`), rebuild, and copy up. Root `index.html` *is* checked/tuned by hand for the inline splash + CSP, so if you change it directly, mirror the change into `api/front/index.html` too.
- Backend edits go in `api/application/` (controllers, models, views, config). `api/system/` and all `vendor/` trees are third-party — leave them alone.
- **Backup convention:** before risky edits, this project saves siblings named `<file>.bak-<label>-<date>` (e.g. `Api_courses.php.bak-report-fixes-20260705`). These are inert; the root `.htaccess` blocks them (and `.sql`, `.map`, `.zip`, `.md`, etc.) from public access. Don't treat a `.bak-*` as the active file.
- The local XAMPP install is named `MyCommcation` (note the spelling) — the dev proxy in `vite.config.ts` targets `http://localhost/MyCommcation`, which differs from this `body` folder name.

## Architecture

### Request routing (root `.htaccess`)

The root `.htaccess` is the traffic cop and is ordered deliberately:

1. Force HTTPS, canonicalize `www` → non-`www`.
2. **Bot detection runs first.** If the User-Agent matches a crawler/social/AI bot (Googlebot, Bingbot, facebookexternalhit, ClaudeBot, GPTBot, Lighthouse, …) and the path isn't a real file or API path, the request is rewritten to **`seo-router.php`** for server-rendered HTML (see below). Real users skip this entirely.
3. `/api/*`, `/_app/*`, `/uploads/*`, `/assets/*` pass through (the latter two rewrite into `api/`).
4. Legacy `/api_<module>/*` URLs are **307-redirected** to `/api/api_<module>/*` for backward compatibility.
5. Everything else falls through to **`/index.html`** — the React SPA, which does its own client-side routing.

### Frontend ↔ backend contract

- The SPA calls the backend at **`/api/api_<module>/<endpoint>`** directly. Module base paths are defined in `api/front/src/lib/api/config.ts` (`API_MODULES`: AUTH=`/api/api_frontend`, COURSES=`/api/api_courses`, etc.). All HTTP helpers (`apiFetch`, `apiPost`, `apiPut`, `apiDelete`, `publicFetch`) live in that file; typed service functions are in `services.ts`, types in `types.ts`.
- **Auth is JWT.** Token sent as `Authorization: Bearer <token>` (or `?auth_token=`). Stored in `localStorage` (remember-me) or `sessionStorage`; a 401 clears it and redirects to `/login`. Client auth state is in `api/front/src/contexts/AuthContext.tsx`. Backend JWT + auth guards: `api/application/libraries/JWT.php` and `Api_security.php`.
- API responses use a consistent envelope: `{ status: boolean, data?, message?, pagination? }`.
- SPA routes are declared in `api/front/src/App.tsx`; pages in `api/front/src/pages/`. `ProtectedRoute` gates authenticated pages (watch, profile, my-courses, wishlist).

### Backend (CodeIgniter 3, under `api/`)

- Standard CI3 layout: `api/application/controllers|models|views|config|libraries`. Entry point `api/index.php`; the `api/.htaccess` dispatches everything to it and sets **CORS** (allowlisted to `my-communication.uk` + localhost).
- **API controllers** are the `Api_*.php` set: `Api_frontend` (auth/site), `Api_courses`, `Api_payment`, `Api_notifications`, `Api_messages`, `Api_reports`, `Api_admin`, `Api_webhooks`. Full endpoint reference: `api/API_DOCUMENTATION.md`.
- `api/application/config/routes.php` also defines clean **`/api/v2/*`** aliases mapping onto these controllers. Non-API controllers (`Home`, `Seo`, `Blog`, `Page`, `Sitemap_seo`, addons) serve the legacy server-rendered site and SEO.
- `base_url` and `index_page` auto-detect from the request (see `config.php`) — no hardcoded host.

### SEO server-rendering path

`seo-router.php` (web root) is the crawler entry point. It sanitizes the requested path, maps it to an `/api/seo/<route>` target (home, courses, `course_detail/<id>`, instructors, blog, static pages, else `not_found_page`), then makes a **loopback HTTP fetch** to that URL and streams the HTML back — so bots keep the canonical URL with no redirect. Those SEO views are rendered by the **`Seo.php`** controller (`home()`, `courses()`, `course_detail()`, `instructors()`, `not_found_page()`, …). Keep the route map in `seo-router.php`, the React routes in `App.tsx`, and `Seo.php`'s methods in sync when adding pages.

### Image proxy

`img.php` + the `/img/uploads/<path>.jpg` rewrite exist to give strict social crawlers (WhatsApp) a clean, query-string-free `og:image` URL that proxies from `uploads/`.

## Payments & integrations

Payment gateways are integrated as CI libraries under `api/application/libraries/` (Xendit, PayU/`openpayu_php`, plus Stripe/PayPal/Razorpay referenced in the payment API). Webhooks (`Api_webhooks`) emit events like `course.enrolled`, `payment.success`, `certificate.issued`.

## Security headers & CSP

- Root `.htaccess` sets HSTS (2yr + preload), `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, and COOP `same-origin-allow-popups` (kept permissive for OAuth/payment popups).
- The **CSP** lives in root `index.html` (`<meta http-equiv>`). It allows `'unsafe-inline'` (required by the inline splash handlers + framer-motion/Radix CSS-in-JS) but **not** `'unsafe-eval'`; media/frames are limited to Vimeo/YouTube. Update it when adding a third-party origin (scripts, fonts, embeds, `connect-src` API hosts).
