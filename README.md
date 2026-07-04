# MSF HMS — Hospital Management System (Phase 1)

Production-ready **Card Room Module** for a factory hospital. Digitizes patient cards, room assignments, visit register, and operational reports.

## Stack

- **Backend:** Laravel 12, PHP 8.3+
- **Frontend:** React 19, TypeScript, Inertia.js, Tailwind CSS
- **Database:** PostgreSQL 16+

## Prerequisites

Install on your machine:

1. [PHP 8.3+](https://windows.php.net/download/) with extensions: `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
2. [Composer](https://getcomposer.org/)
3. [PostgreSQL 16+](https://www.postgresql.org/download/)
4. [Node.js 20+](https://nodejs.org/) (already available)

Or use **XAMPP 8.2+** / **Laragon** on Windows for PHP + PostgreSQL.

## Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment
copy .env.example .env
php artisan key:generate

# 3. Configure PostgreSQL in .env
# DB_DATABASE=msf_hms
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# 4. Create database and migrate
createdb msf_hms
php artisan migrate --seed

# 5. Frontend
npm install
npm run dev

# 6. Run server (separate terminal)
php artisan serve
```

Open http://localhost:8000

**Default login:** `admin` / `password`

## Phase 1 Modules

- Authentication & RBAC (Admin, Card Officer, Department Head, General Manager)
- Patient registration, search, card numbering (`97266-0`, `97266-1`, …)
- Room assignment & Visit Register (digital big book)
- Dashboard & Reports (daily/weekly/monthly)
- User management & audit logging

## Documentation

- [`docs/cursor-master-prompt.md`](docs/cursor-master-prompt.md) — Project constitution
- [`.cursor/rules/hms-rules.md`](.cursor/rules/hms-rules.md) — Cursor agent rules
- [`MSF HMS.docx`](MSF%20HMS.docx) — Original specification

## Tests

```bash
php artisan test
```

## Project Structure

```
app/
  Modules/CardRoom/     # Phase 1 module (Controllers, Services, Requests)
  Models/               # Eloquent models
  Policies/             # RBAC policies
  Services/             # Shared services (AuditLog)
resources/js/Pages/     # React/Inertia screens
database/migrations/    # PostgreSQL schema (ERD v3)
```

## Next Steps

- PDF/Excel report export
- Docker/Sail deployment config
- Additional feature tests for room assignment and insurance validation
