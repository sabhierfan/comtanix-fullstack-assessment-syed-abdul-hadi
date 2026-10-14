# Quote Request Manager — Comtanix Full Stack Assessment

An internal tool where prospects submit quote requests through a public form and staff review, assign and update them. Every important change is recorded in a separate audit service.

| Folder | Stack | Responsibility |
|---|---|---|
| [`api-laravel/`](api-laravel) | Laravel 13, PHP 8.4, MySQL 8.4, Sanctum | Auth, roles, quote requests, status workflow, event publishing |
| [`audit-service/`](audit-service) | Node.js, Express 5, TypeScript, MongoDB 7 | Receives signed events and exposes the audit trail |
| [`frontend/`](frontend) | React 19, TypeScript, Vite, Tailwind | Staff dashboard |

## Prerequisites

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `sqlite3`
- Composer 2
- Node.js 20+
- Docker (for MySQL and MongoDB)

## Setup

### 1. Databases

```bash
docker compose up -d --wait
```

### 2. Shared signing secret

Laravel signs every audit event with HMAC-SHA256; the audit service verifies it. Generate one secret and put the same value in both `.env` files:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

- `api-laravel/.env` → `AUDIT_SIGNING_SECRET=<secret>`
- `audit-service/.env` → `AUDIT_SIGNING_SECRET=<secret>`

### 3. Laravel API

```bash
cd api-laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
php artisan queue:work     # in a second terminal, delivers audit events
```

### 4. Audit service

```bash
cd audit-service
npm install
cp .env.example .env
npm run dev                # http://localhost:4000
```

### 5. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                # http://localhost:5173
```

## Running tests

_TODO_

## Seeded logins

_TODO_

## API

_TODO: endpoint table and Postman/OpenAPI file._

## Assumptions

- **Repository visibility:** the brief asked for a private repository with the reviewer added as a collaborator. The reviewer (Oliver James) asked instead for a **public** repository with the link sent by email, so no collaborator was added.
- **Assignment:** only Admins can assign or reassign requests (confirmed by the reviewer). Staff cannot self-assign.
- **Delete:** an Admin-only soft-delete endpoint, `DELETE /api/quote-requests/{id}`, is provided (confirmed by the reviewer).

## Architecture notes (Part E)

_TODO_

## AI tool usage

_TODO_
