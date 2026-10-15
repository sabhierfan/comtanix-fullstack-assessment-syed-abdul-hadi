# Quote Request Manager: Comtanix Full Stack Assessment

[![CI](https://github.com/sabhierfan/comtanix-fullstack-assessment-syed-abdul-hadi/actions/workflows/ci.yml/badge.svg)](https://github.com/sabhierfan/comtanix-fullstack-assessment-syed-abdul-hadi/actions/workflows/ci.yml)

An internal tool where prospects submit quote requests through a public form and staff review, assign and update them. Every important change is recorded in a separate audit service.

| Folder | Stack | Responsibility |
|---|---|---|
| [`api-laravel/`](api-laravel) | Laravel 13, PHP 8.4, MySQL 8.4, Sanctum | Auth, roles, quote requests, status workflow, event publishing |
| [`audit-service/`](audit-service) | Node.js, Express 5, TypeScript, MongoDB 7 | Verifies signed events, stores them once, serves the audit trail |
| [`frontend/`](frontend) | React 19, TypeScript, Vite, Tailwind, TanStack Query | Staff dashboard |
| [`docs/openapi.yaml`](docs/openapi.yaml) | OpenAPI 3.1 | All Laravel and Node endpoints |

```
Browser ──► Laravel API ──(DB transaction: change + queued job)──► MySQL
              │                                                      │
              │ GET audit trail (X-Api-Key)            queue:work ◄──┘
              ▼                                            │ POST /events (X-Signature: HMAC)
         Audit service ◄───────────────────────────────────┘
              │
              ▼
           MongoDB
```

## Quick start: everything in Docker (one command)

Only Docker is needed:

```bash
docker compose --profile app up -d --build --wait
```

| Service | URL / role |
|---|---|
| Dashboard | http://localhost:5173 (log in with a [seeded account](#seeded-logins)) |
| Laravel API | http://localhost:8000/api (runs migrations and seeds on first start) |
| Audit service | http://localhost:4000 (`/health` reports MongoDB status) |
| `queue` | `php artisan queue:work`: delivers audit events |
| `scheduler` | `php artisan schedule:work`: daily expired-token clean-up |
| `secrets` | Runs once: generates `APP_KEY`, `AUDIT_SIGNING_SECRET` and `AUDIT_READ_API_KEY` into a private volume shared by Laravel and Node, so no secret is committed |

Stop with `docker compose --profile app down` (add `-v` to delete the data). The images are for local review: the API uses `php artisan serve`. In production I would use PHP-FPM or FrankenPHP behind nginx, `composer install --no-dev`, and real secret management.

## Prerequisites (running without Docker)

- PHP 8.3+ with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `intl`
- Composer 2
- Node.js 20+
- Docker (runs MySQL and MongoDB)

## Setup (running without Docker)

### 1. Databases

```bash
docker compose up -d --wait
```

This starts MySQL on `3306` and MongoDB on `27017`. Development credentials are in [`docker-compose.yml`](docker-compose.yml). To override them, copy [`.env.example`](.env.example) to `.env` at the repo root.

### 2. Shared secrets

Laravel and the audit service share two values:

| Variable | Purpose |
|---|---|
| `AUDIT_SIGNING_SECRET` | Laravel signs each event with HMAC-SHA256 over the raw JSON body; the audit service verifies it. |
| `AUDIT_READ_API_KEY` | Laravel sends it as `X-Api-Key` when reading the audit trail for the dashboard. |

Generate each one (run the command twice):

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

Put the **same** values in `api-laravel/.env` and `audit-service/.env` after copying the `.env.example` files below.

### 3. Laravel API

```bash
cd api-laravel
composer install
cp .env.example .env          # then set AUDIT_SIGNING_SECRET and AUDIT_READ_API_KEY
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

In a second terminal, start the worker that delivers audit events:

```bash
cd api-laravel
php artisan queue:work --tries=8
```

### 4. Audit service

```bash
cd audit-service
npm install
cp .env.example .env          # same AUDIT_SIGNING_SECRET and AUDIT_READ_API_KEY as Laravel
npm run dev                   # http://localhost:4000
```

### 5. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                   # http://localhost:5173
```

## Seeded logins

`php artisan migrate --seed` creates 1 admin, 2 staff and 200 quote requests. These are development-only credentials.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@comtanix.test` | `password` |
| Staff | `staff1@comtanix.test` (Sara Staff) | `password` |
| Staff | `staff2@comtanix.test` (Omar Staff) | `password` |

Seeded rows are inserted directly, so they have no audit history. Events appear for changes made through the API.

## Running the tests

| Part | Command | Notes |
|---|---|---|
| Laravel (43 tests) | `cd api-laravel && php artisan test` | SQLite in memory by default; no MySQL needed. HTTP to the audit service is faked. |
| Laravel on MySQL | `DB_CONNECTION=mysql DB_DATABASE=quote_manager_test php artisan test` | Same tests on real MySQL, where row locks actually apply. Create the `quote_manager_test` database first. |
| Audit service (13 tests) | `cd audit-service && npm test` | Needs MongoDB from `docker compose`. Uses a separate `audit_service_test` database. |
| Frontend (10 tests) | `cd frontend && npm test && npm run lint && npm run build` | Vitest + Testing Library, lint, type check and production build. |

**CI:** [`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push to `main` and every pull request, as four jobs:
- Laravel on SQLite, with a Pint style check
- Laravel on a MySQL 8.4 service container
- the audit service, with a MongoDB 7 service container
- the frontend

`main` is protected: changes go through a pull request, and the CI checks must pass.

What they cover:

- **Laravel:** login, rate limiting and token revocation; public validation, honeypot, field injection and rate limiting; every allowed and forbidden status transition; Staff vs Admin rules (only own requests, no self-assignment, Admin-only delete); list filters and pagination with no N+1 queries; event payloads on create, status change, assign and delete; the job's HMAC signature, retry on 5xx and fail-fast on 4xx; and the audit proxy, including the 503 when the service is down.
- **Audit service:** missing, wrong-secret and tampered signatures rejected; valid events stored; the same `eventId` stored once, including 5 concurrent copies; validation; `GET /events` auth, filters, ordering and pagination; health check.
- **Frontend:** the API client (token, query building, 422 field errors, sign-out on 401, error reference on 5xx, network failure), the login flow (redirect to login, error display, redirect back after login, session saved) and the audit event descriptions.

## API

The full spec is [`docs/openapi.yaml`](docs/openapi.yaml). Import it into Postman (*Import → File*) or open it in [Swagger Editor](https://editor.swagger.io).

| Method | Endpoint | Who |
|---|---|---|
| `POST` | `/api/login`, `/api/logout` | Public / authenticated |
| `GET` | `/api/me` | Authenticated |
| `POST` | `/api/public/quote-requests` | Public (validated, honeypot, 5/min and 50/day per IP) |
| `GET` | `/api/quote-requests?search=&status=&assigned_to=&service_type=&sort=&page=&per_page=` | Admin, Staff |
| `GET` | `/api/quote-requests/{id}` | Admin, Staff |
| `PATCH` | `/api/quote-requests/{id}/status` | Admin, or the assigned Staff member |
| `PATCH` | `/api/quote-requests/{id}/assign` | Admin |
| `DELETE` | `/api/quote-requests/{id}` (soft delete) | Admin |
| `GET` | `/api/quote-requests/{id}/audit-events` | Admin, Staff (proxied from the audit service) |
| `GET` | `/api/users` | Admin, Staff |
| `POST` | `:4000/events` | Laravel only (`X-Signature`) |
| `GET` | `:4000/events?quoteRequestId=&type=&page=&perPage=` | Laravel only (`X-Api-Key`) |

**Status workflow:** `new → in_review → quoted → closed`, plus `in_review → closed`. Any other change returns `422` with `current_status` and `allowed_transitions`.

## Assumptions

- **Repository visibility:** the brief asked for a private repository with the reviewer added as a collaborator. The reviewer (Oliver James) asked instead for a **public** repository, with the link sent by email, so no collaborator was added.
- **Assignment:** only Admins assign or reassign; Staff cannot self-assign (confirmed by the reviewer). Assigning to `null` unassigns.
- **Delete:** an Admin-only soft-delete endpoint, `DELETE /api/quote-requests/{id}` (confirmed by the reviewer). Deleted requests return `404` and a `quote_request.deleted` event is recorded.
- **Visibility:** Staff can **view** every request (they need the full queue for context) but can only **change** requests assigned to them.
- **Admins** can change the status of any request, assigned or not.
- **Public form:** the submitter gets a generic success message, not the request id, so ids cannot be enumerated.
- **Service types and budget ranges** are fixed lists (enums), since the brief does not define them.
- **Audit timeline** is read through a Laravel proxy, not directly from the browser. See [Architecture notes](#architecture-notes-part-e).
- Each audit event's `occurredAt` is set when the change happens, not when it is delivered, so the timeline stays in the right order even after retries.

## Architecture notes (Part E)

**Audit service down for 10 minutes?**
Users notice nothing. Each change and its `PublishAuditEvent` job are saved in the **same MySQL transaction** (the database queue acts as a transactional outbox), so an event is never lost and never sent for a rolled-back change. Only `queue:work` calls the audit service; it retries with backoff (10s, 30s, 1m, 2m, 5m, 10m, 20m, about 38 minutes in total), so the next retry after recovery delivers the event. The `eventId` is fixed at dispatch, so a retry is stored once. The dashboard timeline shows "temporarily unavailable" (503) and everything else keeps working. I tested this by stopping and restarting the service. *For production:* a supervised worker (Horizon) on a dedicated queue, alerts on queue depth and `failed_jobs`, and a replay process for jobs that ran out of attempts.

**List endpoint with 5 million rows?**
Single-filter lists sorted by newest stay fast: the composite indexes `(status, created_at)`, `(assigned_to, created_at)` and `(service_type, created_at)` let MySQL read one index range and stop after one page. What degrades: `LIKE '%term%'` search scans the table (use FULLTEXT or a search engine); `COUNT(*)` totals and deep `OFFSET`s get slow (use cursor pagination on `created_at, id`); and filter combinations without a matching index (add indexes based on the slow query log). Then archive old closed requests and use read replicas.

**Security risks considered**
- *Public endpoint:* validation, a silent honeypot, per-IP rate limits (5/min, 50/day), and `status`/`assigned_to` not mass assignable. Left out: CAPTCHA.
- *Auth and race conditions:* tokens expire after 8h and are revoked on logout, and login is throttled. Login gives the same message and timing for unknown emails. One Policy holds all permission rules; each change locks the row and rechecks permission and status, so concurrent edits can't break the rules.
- *Token storage:* `sessionStorage` is readable by JavaScript, so XSS is the risk. Cookie-based Sanctum SPA auth would be safer; I left it out for simplicity.
- *Event signing:* HMAC-SHA256 over the raw body, constant-time comparison, 64 KB limit, strict schema. Reading uses a separate key held only by Laravel.
- *Replay attacks:* a replayed request repeats its `eventId`, which is stored only once. Left out: signed timestamps and key rotation.

**Cut, simplified, next**
Cut: refresh tokens, browser end-to-end tests, and a production web server image (Docker uses `php artisan serve`). Simplified: database queue instead of Redis, `LIKE` search, offset pagination. Next: cursor pagination and full-text search, cookie-based auth, Playwright tests.

## AI tool usage

I used **Claude Code** (Anthropic's AI coding assistant, in the Claude desktop app) throughout this assessment:

- **Planning:** reading the brief, drafting the clarification questions to the reviewer, and splitting the work into Parts A–E.
- **Environment:** installing PHP 8.4 and Composer, and scaffolding the three projects and `docker-compose.yml`.
- **Implementation:** code for all three services (Laravel API, Node audit service, React dashboard).
- **Tests and verification:** writing the Laravel and Node tests, the end-to-end outage/recovery check, and browser testing of the dashboard.
- **Docs and Git:** commit messages, pull request descriptions, this README and the OpenAPI file.

I made or confirmed the key decisions: the clarifications with the reviewer (public repository, Admin-only assignment, soft delete), the Git identity, and the merge flow. All code was run and checked with the test suites, a manual end-to-end check (including stopping and restarting the audit service) and the dashboard in a browser.
