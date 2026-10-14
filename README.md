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

## Prerequisites

- PHP 8.3+ with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `intl`
- Composer 2
- Node.js 20+
- Docker (runs MySQL and MongoDB)

## Setup

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
| Laravel (40 tests) | `cd api-laravel && php artisan test` | SQLite in memory; no MySQL needed. HTTP to the audit service is faked. |
| Audit service (12 tests) | `cd audit-service && npm test` | Needs MongoDB from `docker compose`. Uses a separate `audit_service_test` database. |
| Frontend | `cd frontend && npm run build && npm run lint` | Type check, production build and lint. No frontend tests (optional in the brief). |

**CI:** [`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs all of the above on every push to `main` and every pull request. That's three jobs: Laravel (with a Pint style check), the audit service (with a MongoDB 7 service container), and the frontend.

What they cover:

- **Laravel:** login, rate limiting and token revocation; public validation, honeypot, field injection and rate limiting; every allowed and forbidden status transition; Staff vs Admin rules (only own requests, no self-assignment, Admin-only delete); list filters and pagination with no N+1 queries; event payloads on create, status change, assign and delete; the job's HMAC signature, retry on 5xx and fail-fast on 4xx; and the audit proxy, including the 503 when the service is down.
- **Audit service:** missing, wrong-secret and tampered signatures rejected; valid events stored; the same `eventId` stored once, including 5 concurrent copies; validation; `GET /events` auth, filters, ordering and pagination.

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

**What happens if the Audit Service is down for 10 minutes?**
Nothing visible to users of the main API. The change and its `PublishAuditEvent` job are written in the **same MySQL transaction** (the database queue works as a transactional outbox). An event is never lost for a saved change, and never sent for a rolled-back one. The HTTP call happens only in `queue:work`. During the outage each attempt fails and is retried with exponential backoff: 10s, 30s, 1m, 2m, 5m, 10m, 20m, about 38 minutes over 8 attempts. Once the service is back, the next retry delivers the event. The `eventId` is fixed when the job is created, so a retry after a timed-out-but-stored request is acknowledged as a duplicate, not stored twice. The dashboard shows "audit trail temporarily unavailable" (a `503` from the proxy) and keeps working. I checked this end to end by stopping the service, changing a status, and restarting it. **For production** I would add: a supervised worker (Supervisor/systemd or Horizon on Redis) on a dedicated `audit` queue; alerts on queue depth, job age and `failed_jobs`; a scheduled `queue:retry` or a dead-letter replay for jobs that ran out of attempts; and a circuit breaker so workers back off together instead of each hitting a dead service.

**How would the list endpoint and indexes behave with 5 million rows?**
Single-filter lists sorted by newest stay fast: the composite indexes `(status, created_at)`, `(assigned_to, created_at)` and `(service_type, created_at)` let MySQL read one index range in order and stop after `per_page` rows. Three things degrade: (1) **`LIKE '%term%'` search** can't use a B-tree index and scans the table, so I would use a MySQL `FULLTEXT` index or, better, a search engine (Meilisearch/OpenSearch) fed by the same events. (2) **`COUNT(*)` for pagination totals** gets slow, so I would switch to cursor pagination (`cursorPaginate` on `created_at, id`), which also removes deep `OFFSET` scans, and show approximate totals. (3) **Combined filters and other sort columns** (e.g. status + service + sort by name) may not match an index, so I would add indexes based on real query logs and the slow query log, not guesses. Beyond that: partition or archive closed requests older than N months, and use read replicas for list traffic. The audit service has the same `skip`-pagination issue and would move to a `(occurredAt, _id)` cursor.

**Which security risks did I consider?**
- *Public endpoint:* strict validation and length limits, a honeypot that silently drops bots, per-IP limits (5/min and 50/day), and `status`/`assigned_to` not mass assignable. Not done: CAPTCHA (e.g. Turnstile) and limits keyed on more than IP.
- *Auth:* Sanctum tokens expire after 8h, logout revokes the token, and login is throttled per email+IP. All authorization is in one Policy and is also enforced in Form Requests.
- *Token storage:* `sessionStorage` (cleared when the tab closes, not shared across tabs). Any JS-readable token is exposed to XSS. The better option is Sanctum's cookie-based SPA auth (httpOnly, SameSite, CSRF), which I left out to keep the setup simple. React escapes output, and there is no `dangerouslySetInnerHTML`.
- *Event signing:* HMAC-SHA256 over the **raw** bytes, compared in constant time; the body is capped at 64 KB, the schema is strict, and secrets come from env. The read endpoint uses a separate key held only by Laravel, so the browser has no audit credential and the Node service needs no CORS.
- *Replay attacks:* a captured, validly signed request can only re-deliver the same `eventId`, which is stored once, so replay can't create or change records. Not done: a signed timestamp header with a freshness window (it has to allow for retries up to ~40 min later) and key rotation with a key id.
- *Race conditions:* every change locks the row (`SELECT … FOR UPDATE`), then checks the status rule **and the permission again** on the fresh row. If an admin reassigns a request while a staff member is saving, the staff member gets a 403; two people can't both pass a status check. Duplicate events are blocked by MongoDB's unique `eventId` index, even when copies arrive at the same time.
- *Login:* the same message and roughly the same response time for "unknown email" and "wrong password" (a dummy bcrypt check runs when the email doesn't exist), so emails can't be discovered.
- *Other:* CORS is limited to configured origins, every API error is returned as JSON, and passwords are hashed with bcrypt. Set `TRUSTED_PROXIES` behind a load balancer so per-IP rate limits see the real client IP. Expired tokens are deleted daily by `sanctum:prune-expired` (needs `php artisan schedule:run` in cron).

**What I cut or simplified, and what I'd build next**
- Cut: frontend tests, Docker images for the apps (only the databases are in Compose), refresh tokens, and per-user rate limits on staff actions.
- Simplified: the database queue instead of Redis/Horizon, `LIKE` search, offset pagination, and an API-key-protected read endpoint instead of signed reads.
- Next: running the Laravel suite in CI against MySQL as well as SQLite; Docker images for the API and audit service; cursor pagination and full-text search; cookie-based SPA auth; Playwright tests for the main dashboard flows; and an outbox table with a relay so the queue backend could be changed without losing atomicity.

## AI tool usage

I used **Claude Code** (Anthropic's AI coding assistant, in the Claude desktop app) throughout this assessment:

- **Planning:** reading the brief, drafting the clarification questions to the reviewer, and splitting the work into Parts A–E.
- **Environment:** installing PHP 8.4 and Composer, and scaffolding the three projects and `docker-compose.yml`.
- **Implementation:** code for all three services (Laravel API, Node audit service, React dashboard).
- **Tests and verification:** writing the Laravel and Node tests, the end-to-end outage/recovery check, and browser testing of the dashboard.
- **Docs and Git:** commit messages, pull request descriptions, this README and the OpenAPI file.

I made or confirmed the key decisions: the clarifications with the reviewer (public repository, Admin-only assignment, soft delete), the Git identity, and the merge flow. All code was run and checked with the test suites, a manual end-to-end check (including stopping and restarting the audit service) and the dashboard in a browser.
