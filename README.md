# NIM Membership Register

A registry workbench for managing NIM membership records, new-member imports, grade upgrades, audit history, and administrative reporting.

The Laravel application lives in [`nim/`](nim/). Commands below are run from that directory unless stated otherwise.

---

## Contents

- [Overview](#overview)
- [Repository layout](#repository-layout)
- [Features](#features)
- [Domain rules](#domain-rules)
- [Technology stack](#technology-stack)
- [Requirements](#requirements)
- [Local setup](#local-setup)
- [Environment variables](#environment-variables)
- [Database and seeding](#database-and-seeding)
- [Application map](#application-map)
- [Routes and permissions](#routes-and-permissions)
- [Import workflow](#import-workflow)
- [Upgrade workflow](#upgrade-workflow)
- [Audit logging](#audit-logging)
- [Reports and exports](#reports-and-exports)
- [Testing and quality checks](#testing-and-quality-checks)
- [Deployment notes](#deployment-notes)
- [Troubleshooting](#troubleshooting)

---

## Overview

The system replaces spreadsheet-based membership administration with a controlled web workflow:

1. Upload a CSV or XLSX workbook.
2. Validate and preview every row before anything is committed.
3. Review detected headings, grades, contact details, and row-level errors.
4. Process the batch in a database transaction.
5. Trace every privileged or state-changing action in an immutable audit log.

Access is role-based. Editors perform registry work; Super Admins additionally manage users, roles, system settings, upgrade types, and audit history.

The interface uses a registry-workbench visual language: paper background, institutional navy, verdigris for verified states, brass for review states, serif numerals, and ledger-style tables.

---

## Repository layout

```text
memebergen/
├── README.md                 # This document
├── .gitignore                # Keeps private local files out of commits
└── nim/                      # Laravel application root
    ├── app/
    │   ├── Enums/            # Batch type, status, and row-status enums
    │   ├── Exports/          # Maatwebsite Excel exports
    │   ├── Http/
    │   │   ├── Controllers/  # Web controllers
    │   │   ├── Requests/     # Form requests and authorization
    │   │   └── Middleware/   # Inertia shared data
    │   ├── Imports/          # Heading-row and spreadsheet readers
    │   ├── Models/           # Eloquent models
    │   ├── Providers/        # Auth, Fortify, and event wiring
    │   └── Services/         # Domain services and audit logger
    ├── config/               # App, membership, permission, Fortify config
    ├── database/
    │   ├── factories/
    │   ├── migrations/
    │   └── seeders/
    ├── resources/js/
    │   ├── components/       # Shared UI and layout components
    │   ├── layouts/
    │   ├── pages/            # Inertia page components
    │   └── types/
    ├── routes/               # Web, settings, and console routes
    └── tests/
        ├── Feature/
        └── Unit/
```

Generated Wayfinder route/action helpers under `resources/js/actions`, `resources/js/routes`, and `resources/js/wayfinder` are intentionally ignored and are regenerated during the frontend build.

---

## Features

### Membership register

- Search and filter by membership number, name, grade, email, and registration date.
- Numeric-only membership numbers rendered as six-digit strings.
- Member detail view with current grade, contact information, registration date, grade history, and upgrades.
- Permission-protected register export with spreadsheet-safe value sanitisation.

### New registrations

- CSV/XLSX upload with file type, MIME, size, worksheet-count, heading, and row-count validation.
- Row-level preview before commit.
- Sequential number allocation under a database row lock.
- Re-uploading the same workbook is allowed; file hashes are recorded but not treated as unique blockers.
- Detailed batch view with row outcomes and error messages.

### Membership upgrades

- Upgrade types are configured by Super Admins.
- Uploads require a membership number and an upgrade type.
- Key information is matched against the register before processing.
- Upgrades move a member by exactly one grade step and retain the original membership number.
- Full upgrade and membership-history records are preserved.
- Batch result exports are available to authorised users.

### Batch history

- Unified, read-only list of registration and upgrade batches.
- Filters for search text, batch type, status, and upload date range.
- Summary counters for total, awaiting-review, completed, and failed batches.
- Direct links back to each batch’s review screen.

### Dashboard

- Live member count, new registrations this month, upgrades this month, and batches awaiting review.
- Grade distribution and the five most recent batches.
- Shortcut actions respect the signed-in user’s permissions.

### Reports

- Date, grade, and text filters across the register and upgrade history.
- Summary metrics, grade distribution, upgrade-type distribution, and six-month trends.
- Filtered XLSX exports for both the register and upgrade history.

### User management

- Super Admin-only create, edit, role assignment, activation, and deactivation.
- Password reset links are delivered by Laravel’s standard password broker.
- Last login timestamp is recorded on successful authentication.
- Inactive accounts cannot authenticate.
- Safeguards prevent self-deactivation, self-demotion, and changing the last active Super Admin.
- Password hashes and reset tokens are never displayed or written to audit metadata.

### Audit logs

- Read-only audit trail with actor, action, affected record or batch, timestamp, IP address, and user agent.
- Before/after metadata for sensitive changes.
- Credentials, remember tokens, and two-factor secrets are scrubbed recursively.
- No audit edit or delete routes exist; the model also rejects updates and deletes.

### System settings

- Super Admin-only import limits: maximum file size, maximum rows, and validation chunk size.
- Database overrides take precedence over environment defaults.
- Numbering policy and grade-transition rules are shown as fixed, read-only policies.
- Every settings change is audited with before/after values.

---

## Domain rules

| Area | Rule |
| --- | --- |
| Membership numbers | Numeric only, six digits by default, zero-padded. |
| Allocation | Continues after the highest existing number; gaps below `999999` are filled first; expands to 7+ digits only after the six-digit range is exhausted. |
| Concurrency | Allocation is performed inside a transaction with a locked sequence row. |
| Retention | A membership number never changes when a member is upgraded. |
| Grades | `Graduate → Associate → Member → Fellow`. |
| Upgrades | Exactly one forward step per upload; key identity fields must match. |
| Registration date | Displayed in the register and used for registration reporting. |
| Upgrade effective date | Shown from the upgrade record’s update timestamp in member history. |
| Seed data | Default seeding creates only roles, permissions, and grades — no member records. |

---

## Technology stack

- **Backend:** Laravel 13, PHP 8.3+, Pest 5, Laravel Pint, Larastan
- **Auth:** Laravel Fortify, Spatie Laravel Permission
- **Frontend:** Inertia 3, React 19, TypeScript, Tailwind CSS 4, Radix UI, Wayfinder
- **Spreadsheets:** Maatwebsite Excel 4 / PhpSpreadsheet
- **Database:** MySQL for deployment; in-memory SQLite for tests

---

## Requirements

- PHP 8.3 or newer with `pdo_mysql` for MySQL deployments
- Composer 2
- Node.js and npm
- MySQL 8+ (or another supported relational database)
- `pdo_sqlite` and `sqlite3` PHP extensions for running the test suite locally when they are not enabled by default

---

## Local setup

From the repository root:

```bash
cd nim
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

Configure the database in `.env`, then:

```bash
php artisan migrate --force
php artisan db:seed --force
npm ci
npm run build
php artisan serve
```

For development with automatic frontend rebuilds, run `npm run dev` in a second terminal, or use `composer run dev` if you prefer a single managed development process.

Create the first Super Admin with an authorised provisioning method, then sign in at `/login`. The seeded roles are `Editor` and `Super Admin`.

---

## Environment variables

| Variable | Purpose | Default |
| --- | --- | --- |
| `APP_NAME` | Application name shown in the UI and notifications | `Laravel` |
| `APP_ENV` | Environment name | `local` |
| `APP_DEBUG` | Debug output | `true` locally; disable in production |
| `APP_URL` | Canonical application URL | `http://localhost:8000` |
| `DB_CONNECTION` | Database driver (`mysql` in deployment) | `sqlite` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection details | commented out |
| `SESSION_DRIVER` | Session store | `database` |
| `CACHE_STORE` | Cache store | `database` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `FILESYSTEM_DISK` | Disk used for uploaded workbooks | `local` |
| `MAIL_MAILER` | Mail transport for password resets | `log` |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Password-reset sender | `hello@example.com` |
| `MEMBERSHIP_IMPORT_MAX_KILOBYTES` | Default maximum upload size | `10240` |
| `MEMBERSHIP_IMPORT_MAX_ROWS` | Default maximum data rows per workbook | `10000` |
| `MEMBERSHIP_IMPORT_CHUNK_SIZE` | Default spreadsheet chunk size | `500` |

The three `MEMBERSHIP_IMPORT_*` values are defaults only. Super Admins can override them in **Settings → System settings**; stored overrides take precedence.

---

## Database and seeding

Migrations create the framework tables plus the membership domain:

- roles and permissions
- membership grades
- members and normalised-name indexes
- membership history and upgrade records
- membership batches and row-level outcomes
- membership sequence state
- upgrade types
- audit logs
- user status and last-login fields
- system settings

`DatabaseSeeder` runs `AccessControlSeeder` and `MembershipGradeSeeder` only. It creates:

- permissions and the `Editor` / `Super Admin` roles
- grades `Graduate`, `Associate`, `Member`, and `Fellow`

Optional one-off induction seeders exist for authorised offline sources. Their source workbooks are not bundled with the application, and those seeders are not part of the default seed run.

---

## Application map

### Backend services

| Service | Responsibility |
| --- | --- |
| `MembershipNumberAllocator` | Locked, gap-aware, numeric-only number allocation |
| `MembershipGradeResolver` | Resolves spreadsheet grade labels to configured grades |
| `NewMembershipImportService` | Stores, validates, and previews registration uploads |
| `NewMembershipBatchProcessor` | Commits reviewed registration batches |
| `MembershipUpgradeImportService` | Stores, validates, and previews upgrade uploads |
| `UpgradeBatchProcessor` | Commits reviewed upgrade batches |
| `SystemSettings` | Effective settings with database overrides |
| `AuditLogger` | Immutable audit entries with secret scrubbing |

### Frontend pages

| Path | Component | Purpose |
| --- | --- | --- |
| `/dashboard` | `pages/dashboard.tsx` | Metrics and recent activity |
| `/members` | `pages/members/index.tsx` | Register search and detail |
| `/membership-batches/create` | `pages/membership-batches/create.tsx` | Registration upload and preview |
| `/upgrade-batches/create` | `pages/upgrade-batches/create.tsx` | Upgrade upload and preview |
| `/batch-history` | `pages/batch-history/index.tsx` | Batched import history |
| `/reports` | `pages/reports/index.tsx` | Summaries, trends, and exports |
| `/users` | `pages/users/index.tsx` | Super Admin user management |
| `/audit-logs` | `pages/audit-logs/index.tsx` | Read-only audit trail |
| `/settings/system` | `pages/settings/system/index.tsx` | Import limits and fixed policies |
| `/settings/upgrade-types` | `pages/settings/upgrade-types.tsx` | Upgrade type administration |

---

## Routes and permissions

All application routes except the welcome page require authentication and email verification.

| Area | Main routes | Required permission |
| --- | --- | --- |
| Dashboard | `GET /dashboard` | `dashboard.view` |
| Register | `GET /members`, `GET /members/{member}`, `GET /members/export` | `membership.register.view` / `.export` |
| Registration import | `GET/POST /membership-batches`, `GET /membership-batches/template` | `membership.register.import` |
| Registration review | `GET /membership-batches/{batch}` | `membership.batches.view` |
| Registration processing | `POST /membership-batches/{batch}/process` | `membership.register.process` |
| Upgrade import | `GET/POST /upgrade-batches`, `GET /upgrade-batches/template` | `membership.upgrades.import` |
| Upgrade review | `GET /upgrade-batches/{batch}` | `membership.batches.view` |
| Upgrade processing/export | `POST .../process`, `GET .../export` | `membership.upgrades.process` / `.export` |
| Batch history | `GET /batch-history` | `membership.batches.view` |
| Reports | `GET /reports` | `membership.register.view` |
| Report exports | `GET /reports/register/export`, `GET /reports/upgrades/export` | `membership.register.export` / `membership.upgrades.export` |
| Users | `GET/POST /users`, `PATCH /users/{user}`, `POST /users/{user}/password-reset` | `users.manage` |
| Audit logs | `GET /audit-logs` | `audit-logs.view` |
| Settings | `GET/PATCH /settings/system` | `upgrade-types.manage` / `settings.manage` |
| Upgrade types | `GET/POST/PATCH /settings/upgrade-types[...]` | `upgrade-types.manage` |

**Role summary**

- **Editor:** all dashboard, register, import, processing, export, upgrade, and batch-history permissions.
- **Super Admin:** every Editor permission plus user management, audit review, system settings, and upgrade-type management.
- **No role:** receives `403` for protected application routes.

---

## Import workflow

1. **Download the template** from the upload screen.
2. **Upload** a `.csv` or `.xlsx` file inside the configured size and row limits.
3. **Validation** checks the file hash, worksheet count, detected headings, required columns, grade labels, numeric membership numbers, duplicate numbers, and row-level identity fields.
4. **Preview** shows valid, warning, and invalid rows with messages. Nothing is committed at this stage.
5. **Review** the batch summary and row details.
6. **Process** the batch. Registration numbers are allocated or preserved, grade changes are applied, history is written, and audit entries are recorded inside the same transaction.
7. **Export** row-level results where supported.

Failed or cancelled processing does not leave partial audit entries. A failed validation run can be corrected and re-uploaded, including the same source file.

---

## Upgrade workflow

1. Select an active upgrade type.
2. Upload a workbook containing membership numbers and intended grades.
3. The validator confirms the member exists, key details match, and the intended grade is exactly one step forward.
4. Preview and review the batch.
5. Processing updates the member’s grade, writes a `MembershipUpgrade` record and `MembershipHistory` entry, keeps the membership number unchanged, and records an audit entry.

---

## Audit logging

`AuditLogger` writes an `audit_logs` row for every significant operation, including:

- login, failed login, and logout
- uploads, validation failures, reviews, processing, and exports
- member and upgrade changes
- user creation, role/status changes, and password-reset requests
- upgrade type changes
- system settings changes
- report exports

Each entry records:

- `actor_id` and `actor_name`
- `action`
- `subject_type`, `subject_id`, and `subject_label`
- JSON `metadata`, typically with `before` / `after` snapshots
- `ip_address` and `user_agent`
- `created_at`

The `AuditLog` model rejects updates and deletes. There are no audit edit or delete routes. Sensitive keys such as passwords, password confirmations, remember tokens, and two-factor secrets are removed recursively before persistence.

---

## Reports and exports

Exports are generated with Maatwebsite Excel and use spreadsheet-safe sanitisation:

- control characters are removed
- values beginning with `=`, `+`, `-`, `@`, tab, or carriage return are escaped as text
- membership numbers are forced to text format so zero padding is preserved

Available exports:

- membership register
- upgrade history from reports
- upgrade batch results
- membership batch templates
- upgrade batch templates

Every report export is audited with the active filters.

---

## Testing and quality checks

The test suite uses an in-memory SQLite database via `phpunit.xml` and `RefreshDatabase`.

```bash
# PHP formatting
vendor/bin/pint --dirty --format agent

# Full test suite
php artisan test
# or, when SQLite extensions are not enabled for the CLI:
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact

# Frontend formatting and lint
npm run check

# TypeScript
npm run types:check

# Production build
npm run build
```

At the time of writing, the full suite passes with **123 tests / 771 assertions**, and all frontend checks and the production build pass.

CI runs the same checks through the repository workflow. Generated Wayfinder types and the production build output are refreshed automatically during frontend builds.

---

## Deployment notes

1. Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`.
2. Configure MySQL, session, cache, queue, and mail settings in `.env`.
3. Run `php artisan migrate --force` and `php artisan db:seed --force` on a fresh database.
4. Run `npm ci && npm run build`.
5. Serve the `nim/public` directory as the document root.
6. Make `nim/storage` and `nim/bootstrap/cache` writable by the web server.
7. Configure scheduled queue workers if asynchronous work is enabled in your environment.
8. Back up the database, uploaded workbooks, and `.env` securely.
9. Provision the first Super Admin through an authorised administrative process.
10. Review audit logs and system settings after deployment.

Uploaded workbooks are stored on the configured `FILESYSTEM_DISK` under `membership-imports/` and `membership-upgrade-imports/`. Restrict access to that storage location.

---

## Troubleshooting

| Symptom | Resolution |
| --- | --- |
| `Unable to locate file in Vite manifest` | Run `npm run build`, or start `npm run dev` and retry. |
| Tests fail with SQLite driver errors | Enable `pdo_sqlite` and `sqlite3`, or use the `php -d extension=...` command shown above. |
| `403` after login | The account has no role or lacks the permission for that route. Assign a role as Super Admin. |
| Inactive user cannot sign in | Expected behaviour. Reactivate the account in user management. |
| Upload rejected as too large | Check the effective limits in Settings → System settings and the `MEMBERSHIP_IMPORT_*` defaults. |
| Grade label not recognised | Use one of the configured active grade names: Graduate, Associate, Member, Fellow. |
| Password reset email not received | Check mail configuration; with `MAIL_MAILER=log`, the message is written to the application log. |
| Missing generated route helpers | Run `npm run build`; Wayfinder regenerates them. |

---

## License

The Laravel skeleton uses the MIT license. See `nim/composer.json` for package metadata.
