# Cursor Master Prompt — Hospital Management System Phase 1

> Project constitution for all HMS development. Cursor and developers must follow this document across every file.

---

## PROJECT OVERVIEW

You are building a production-ready Hospital Management System (HMS) for a factory hospital.

This system will be deployed to a real hospital environment with multiple users and future expansion into OPD, Laboratory, Pharmacy, Billing, Emergency, Inventory, and HMIS modules.

**Current scope is ONLY Phase 1: Card Room Module.**

The system must be maintainable, secure, scalable, and easy for non-technical hospital staff to use.

---

## TECHNOLOGY STACK

### Frontend

- React 19
- TypeScript
- Inertia.js
- Tailwind CSS
- shadcn/ui
- TanStack Table
- React Hook Form
- Zod

### Backend

- Laravel 12
- PHP 8.3+

### Database

- PostgreSQL 16+

### Infrastructure

- Ubuntu Server
- Nginx
- PHP-FPM
- PostgreSQL

### Architecture

- Modular Monolith

---

## UI DESIGN REQUIREMENTS

### Theme Colors

- **Primary:** Green
- **Secondary:** White
- **Accent:** Yellow

### Requirements

- Modern, clean, professional, responsive, accessible, hospital-friendly

### Avoid

- Bootstrap-style appearance
- Outdated layouts
- Crowded screens

### Use

- Cards, dialogs, data tables, search-first layouts

---

## PHASE 1 MODULES

### Build ONLY

- Authentication
- User Management
- Patient Management
- Patient Search
- Room Assignment
- Visit Register
- Reports
- Dashboard
- User Profile
- Audit Logging

### Do NOT Build

- Billing
- Laboratory
- Pharmacy
- OPD Clinical Records
- Inventory
- HMIS Integration

---

## BUSINESS RULES

### Patient Types

Supported values (stored in `patient_types` table — **never hardcode**):

- Employee
- Family
- OS
- Insurance
- Federal Police
- Defense Police
- Kuteba
- Staff
- free

### Family Structure

Card numbers are generated programmatically from `employee_no` + `dependent_no`:

| Relationship | Example   |
|--------------|-----------|
| Employee     | `97266-0` |
| Wife         | `97266-1` |
| Child        | `97266-2` |
| Child        | `97266-3` |

### Child Age Rule

If `relationship = Child` **and** `age >= 18`:

- Service not allowed under family account
- Display validation error: "Dependent child exceeded age limit."

### Insurance Rule

If `patient_type = Insurance` → `insurance_no` is **required**.

### Visit Rule

Every room assignment **must** create a Visit record. A Visit is the digital replacement of the physical register book. Visits cannot be deleted; only status updates allowed.

---

## DATABASE TABLES

Required tables:

- `users`
- `roles`
- `departments`
- `patient_types`
- `relationship_types`
- `patients`
- `rooms`
- `visits`
- `audit_logs`

Use PostgreSQL foreign keys. Index: `card_number`, `employee_no`, `insurance_no`, `full_name`, `visit_date`.

---

## ROLE-BASED ACCESS CONTROL

| Role              | Access                                              |
|-------------------|-----------------------------------------------------|
| Admin             | Full access                                         |
| Card Officer      | Patient CRUD, room assignment, reports              |
| Department Head   | Card Officer + department reports, export           |
| General Manager   | View hospital reports only (no patient edits)       |

Use Laravel Policies on every controller action and Inertia page.

---

## SCREENS

1. Login
2. Dashboard
3. Patient Search
4. Patient Registration
5. Patient Details
6. Assign Room
7. Visit Register
8. Reports
9. User Profile
10. User Management

---

## PATIENT SEARCH REQUIREMENTS

Search by: Card Number, Name, Employee Number, Insurance Number, Phone.

Results must return in **< 2 seconds** for normal datasets.

---

## VISIT REGISTER REQUIREMENTS

Replaces the physical hospital book. Must support:

- Date filter
- Room filter
- Patient type filter
- Export (Excel, PDF)

---

## REPORT REQUIREMENTS

### Daily Report Categories

Total Visits, Employee, Family, OS, Insurance, Federal Police, Defense Police, Kuteba, Staff, free

### Room Utilization

Visits per room (OPD 4–8, Eye, Emergency, Under 5, Doctor Room)

### Periods

Daily, Weekly, Monthly — export PDF and Excel.

---

## AUDIT LOGGING

Automatically log: Login, Logout, Patient Create/Update/Deactivate, Room Assignment, User Changes.

Store: User, Action, Timestamp, IP Address, Old Values (JSONB), New Values (JSONB).

---

## BACKEND RULES

Use: Controllers, Services, Repositories (optional), Form Requests, Policies, Events.

**Business logic belongs in Services. Never place business logic inside controllers.**

### Folder Structure (Modular Monolith)

```
app/
  Modules/
    CardRoom/
      Controllers/
      Services/
      Requests/
      Policies/
  Models/
  Services/        # shared services (AuditLogService)
  Http/Middleware/
```

---

## FRONTEND RULES

- TypeScript everywhere (strict mode)
- React Hook Form + Zod validation
- Reusable components under `resources/js/components/`
- Pages under `resources/js/Pages/`
- Avoid duplicated code

---

## SECURITY REQUIREMENTS

- Passwords: **argon2id** (Laravel default)
- Never store plaintext passwords
- All routes protected via `auth` middleware
- Authorization required for every page via Policies

---

## TESTING REQUIREMENTS

Generate Feature Tests and Unit Tests for critical business logic:

- Patient creation
- Room assignment
- Age validation (child >= 18)
- Insurance validation

---

## FUTURE COMPATIBILITY

Design database and architecture to support future modules (OPD, Laboratory, Pharmacy, Billing, Emergency, Inventory, HMIS) **without redesigning existing tables**.

Use `departments` table and FK relationships from day one.

---

## CODE QUALITY RULES

### Always

- SOLID principles
- Laravel conventions
- TypeScript strict mode
- Meaningful names
- Reusable components
- Clean comments for non-obvious business logic

### Never

- Hardcode patient types or room names
- Duplicate business logic
- Skip validation

---

## OUTPUT EXPECTATION

Generate production-ready code. Prioritize:

1. Maintainability
2. Security
3. Scalability
4. Readability
5. Performance

over rapid code generation.
