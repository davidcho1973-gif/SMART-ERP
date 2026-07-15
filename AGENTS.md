# SMART-ERP Agent Guide

## Project

SMART-ERP is a Laravel 13 and Livewire 4 workforce and accounting application for multi-site construction operations. The repository serves staging from `main` and production from `production`.

## Standard commands

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
composer test
npm run build
```

Tests use an in-memory SQLite database and a deterministic test-only admin password through `phpunit.xml`. Image-related tests require the PHP GD extension. Do not use the tracked `database/database.sqlite` as a test fixture or update it as part of a feature.

The repository has pre-existing Pint differences. Do not include a repository-wide formatting rewrite in a feature PR. A dedicated baseline-format task must land before Pint becomes a required CI check.

## Architecture

- `app/Livewire/WorkforceApp.php` is the central UI shell and a merge-conflict hotspot. Keep changes narrow and extract domain behavior instead of adding unrelated responsibilities.
- `app/Support/ViewModel.php` is the read/view-model layer. Keep mutations out of it.
- `app/Support/*` contains business rules for access, attendance, payroll, shifts, communications, and formatting.
- `resources/views/livewire/partials/*` contains one Blade partial per screen.
- `lang/{en,es,ko}/app.php` must remain key-compatible across all three locales.

## Collaboration workflow

- Every change starts from a GitHub issue with acceptance criteria, risk level, implementer, and reviewer.
- Use one implementer and one independent reviewer. Do not let Claude and Codex edit the same branch concurrently.
- Use an isolated worktree and a branch named `codex/<issue>-<slug>` or `claude/<issue>-<slug>`.
- Never push directly to `main` or `production`.
- Keep one active PR at a time that changes `WorkforceApp.php`, `ViewModel.php`, `lang/*`, or database migrations.
- The implementing agent must report changed files, commands run, test results, remaining risks, and rollback notes in the PR.
- The reviewing agent reviews the diff first. It must not silently rewrite the implementer's branch.

## Definition of done

- Acceptance criteria are covered by tests or a documented manual verification.
- `composer test` and `npm run build` pass.
- Authorization is enforced server-side, not only by hiding UI controls.
- Site-scoped actors cannot read or mutate out-of-scope records.
- EN, ES, and KO labels are updated together.
- Schema changes include a production-safe migration and rollback assessment.
- User-facing UI changes include screenshots for desktop and relevant mobile layouts.

## Database and deployment safety

- Production deploys run `php artisan migrate --force` only.
- Never run `migrate:fresh`, `db:wipe`, destructive reseeding, or demo seeding against production.
- `migrate:fresh` is allowed only in disposable local or CI databases that are explicitly configured for testing.
- Do not modify production data, credentials, OAuth settings, cloud storage, or deployment environments without explicit human approval.
- Do not commit `.env` files, API keys, OAuth secrets, user uploads, database dumps, or real employee data.

## High-risk domains

Treat authentication, RBAC, payroll, accounting, attendance, GPS verification, uploads, storage, migrations, and deployment as high risk. Before editing these areas:

1. State invariants and likely failure modes.
2. Add or update focused tests.
3. Ask the independent agent to review the final diff.
4. Require human approval before production promotion.

## Review guidelines

Prioritize actionable correctness and security findings over style commentary.

- Flag authentication or authorization bypasses, including missing capability and site-scope checks.
- Flag exposure of credentials, PII, employee documents, receipts, chat attachments, or database contents.
- Verify uploads enforce membership/role checks, safe storage disks, size/type validation, and non-public access where required.
- Verify payroll, overtime, progress billing, dates, rounding, and currency calculations at boundary conditions.
- Verify attendance changes preserve duplicate-punch prevention, clock-state transitions, GPS trust rules, and audit history.
- Verify migrations preserve existing production data and do not rely on demo-only state.
- Verify write actions re-check permissions on the server even when the UI already hides the action.
- Require regression tests for every confirmed bug fix.
- Treat any production data-loss path, privilege escalation, credential exposure, or materially incorrect payroll/accounting result as a release blocker.
