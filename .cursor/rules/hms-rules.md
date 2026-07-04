# HMS Phase 1 — Cursor Rules

You are building the **MSF Hospital Management System — Phase 1 Card Room Module**.

Full constitution: [`docs/cursor-master-prompt.md`](../../docs/cursor-master-prompt.md)

## Stack (non-negotiable)

- **Backend:** Laravel 12, PHP 8.3+, PostgreSQL 16+
- **Frontend:** React 19, TypeScript (strict), Inertia.js, Tailwind, shadcn/ui, TanStack Table, React Hook Form, Zod
- **Architecture:** Modular Monolith under `app/Modules/CardRoom/`

## Scope

**Build:** Auth, User Management, Patients, Patient Search, Room Assignment, Visit Register, Reports, Dashboard, User Profile, Audit Logging.

**Do NOT build:** Billing, Laboratory, Pharmacy, OPD Clinical Records, Inventory, HMIS Integration.

## Business Rules

- Patient types and rooms come from **database seeders** — never hardcode in PHP/TS
- Card numbers: `{employee_no}-{dependent_no}` generated in `PatientService`
- Child relationship + age >= 18 → block with validation error
- Insurance patient type → `insurance_no` required
- Every room assignment creates a Visit record; visits are not deleted

## Backend

- Business logic in **Services** only — controllers are thin (validate → authorize → service → response)
- Use Form Requests, Policies, and Events
- Log audit actions via `AuditLogService` for: login, logout, patient CRUD, room assignment, user changes
- Passwords: argon2id (Laravel default)

## Frontend

- TypeScript strict mode on all files
- React Hook Form + Zod for every form
- Theme: Green primary, White secondary, Yellow accent
- Layout: left sidebar + top nav; cards, dialogs, data tables; search-first UX
- Avoid Bootstrap look and crowded screens

## RBAC

Roles: Admin, Card Officer, Department Head, General Manager.

- Every route: `auth` middleware
- Every action: Policy check
- General Manager: read-only (no patient CRUD or room assignment)

## Database

Tables: `users`, `roles`, `departments`, `patient_types`, `relationship_types`, `patients`, `rooms`, `visits`, `audit_logs`.

Index: `card_number`, `employee_no`, `insurance_no`, `full_name`, `visit_date`.

## Testing

Write Feature + Unit tests for: patient creation, room assignment, age validation, insurance validation.

## Code Quality

- SOLID, Laravel conventions, meaningful names, reusable components
- Never duplicate business logic or skip validation
- Prioritize maintainability and security over speed of generation
