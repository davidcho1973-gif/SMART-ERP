# NAHSHON MEP — Multi-Site Workforce

A Laravel implementation of the **NAHSHON MEP 인원관리** design (multi-site MEP
construction workforce management for US / Arizona project sites). Recreated
pixel-for-pixel from the Claude Design prototype, on a real Laravel backend.

Stack: **Laravel 13 · Livewire · Blade · Tailwind (Vite) · SQLite (dev)**.

## Features

- **Login** — Google sign-in (demo) + role-based demo entry (Admin / Site Manager / Worker)
- **Dashboard** — 3 comparable layouts (A classic · B attendance ring · C bento), site-scoped
- **Sites & Companies** — company cards, crews, crew-lead assignment, `CREATE` company / crew (typed site name)
- **Employees** — search / crew filter, editable detail drawer, access control, terminate / reactivate / delete
- **Badge Registration** — 3-step wizard: front OCR (with face auto-crop) → back scan → assign
  (crew · rate · employee type · access) + **NFC tag** → UID's last 9 chars prefixed `N-` becomes the employee ID
- **Attendance (QR)** — reader / site-QR / manual modes; company·crew·lead list → auto-generated crew QR + printable card
- **Payroll** — bi-weekly USD, **no tax** (net = gross), OT 1.5× beyond 40h/week; clickable rows →
  punch-history drawer → **Payment Voucher** with check # + signature lines + print
- **Worker mobile** — clock in/out, no-lunch toggle, early-leave with reasons, my QR, hours log
  (with shift-time grace-window adjustment + lunch rules), payslip, profile
- **3 languages** EN / ES / KO with live switching; **3 access levels** (site managers have no payroll)

## Business rules (ported to `app/Support`)

- `Shift` — grace window ±30 min snaps a punch to the scheduled time; 1h unpaid lunch (11:00–12:00)
  counted only when present through it and not skipped; paid = (adjusted out − in) − lunch.
- `Payroll` — 80h/period regular cap, OT 1.5× beyond it, no withholding. `history()` reconstructs a
  plausible day-by-day breakdown that sums to the period hours.
- `Qr` — deterministic decorative QR-style SVG (prototype visual, not a real payload).
- `Money` — USD formatting.

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # SQLite, seeds 3 sites · 3 companies · 5 crews · 17 employees
npm run build                # or: npm run dev
php artisan serve
```

Open http://127.0.0.1:8000 and pick a role (or the Google demo button) on the login screen.
Switch **role** and **language** any time from the top demo bar.

## Architecture

- `app/Livewire/WorkforceApp.php` — single full-page Livewire component holding all UI state and
  actions (navigation, wizards, modals, CRUD). Data mutations persist to the database.
- `app/Support/ViewModel.php` — pure read layer that derives all display data from state + Eloquent
  (the PHP port of the prototype's `renderVals`).
- `resources/views/livewire/partials/*` — one Blade partial per screen; inline styles carried over
  from the prototype for pixel fidelity, with `app/Support/Ui.php` / `Icons.php` for repeated snippets.
- `lang/{en,es,ko}/app.php` — the trilingual dictionary.
- Models: `Site`, `Company`, `Team` (crew), `Employee`.

## Integration seams (simulated in this build, ready to wire)

These are demo simulations in the prototype and remain so here — each has a clean seam for a real
integration:

- **Google auth** — the button runs the demo entry. Add [Laravel Socialite], set
  `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / redirect URI, and point the button at an
  `/auth/google` redirect + callback.
- **Badge OCR + face crop** — `WorkforceApp::startScanF()` fakes the scan. Replace with a real
  OCR/vision API call that fills the extracted fields and cropped face.
- **NFC UID** — `WorkforceApp::startScanN()` returns a fixed UID. Replace with Web NFC / a dedicated
  reader; the `N-` + last-9 ID derivation already lives in `nfcId()`.
- **QR payloads** — `Qr::pattern()` is decorative. Encode real company/crew/lead data for production.

[Laravel Socialite]: https://laravel.com/docs/socialite

## Deployment (Laravel Cloud)

The Laravel app lives at the repository root (the original design bundle is preserved under
`design-source/`), so it deploys directly.

1. On [Laravel Cloud](https://cloud.laravel.com) → **New Application** → connect the GitHub repo
   `davidcho1973-gif/SMART-ERP`, branch `main`.
2. **Database:** choose **SQLite** (simplest for the demo) or a managed Postgres/MySQL.
3. **Environment:** set `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, and click
   **Generate app key** (sets `APP_KEY`). For SQLite, `DB_CONNECTION=sqlite`.
4. **Deploy command** (runs on each deploy — seeds the demo data):
   ```
   touch database/database.sqlite && php artisan migrate:fresh --seed --force
   ```
   Laravel Cloud auto-runs `composer install` and `npm run build`.
5. **Deploy** → the app is served at the Laravel Cloud URL. Pick a role on the login screen.

> The build is a demo (Google auth / OCR / NFC / QR are simulated), so anyone with the URL can
> explore it. Add real auth before using it with production data.
