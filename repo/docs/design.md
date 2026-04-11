# MedVoyage — System Design

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Module Breakdown](#2-module-breakdown)
3. [Data Flow Diagrams](#3-data-flow-diagrams)
4. [State Machines](#4-state-machines)
5. [Database Schema Summary](#5-database-schema-summary)
6. [Security](#6-security)
7. [Recommendation Architecture](#7-recommendation-architecture)

---

## 1. Architecture Overview

MedVoyage is a **server-driven web application** built on:

| Layer | Technology |
|---|---|
| Web framework | Laravel 11 (PHP 8.3) |
| UI / reactivity | Livewire 3 + Alpine.js |
| Styling | Tailwind CSS 3.4 |
| Database | PostgreSQL 16 |
| Runtime | Docker (PHP-FPM 8.3 + Nginx) |
| Queue / cache driver | Database (no Redis dependency) |

### Request Lifecycle

```
Browser
  │
  ├─ GET /route  ──► Laravel Router ──► Livewire component mount
  │                                        │
  │                                        ▼
  │                                    authorize()   ← role/ownership gate (403)
  │                                        │
  │                                        ▼
  │                                    mount() / computed properties
  │                                        │
  │                                        ▼
  │                                    Blade view rendered, returned as HTML
  │
  └─ Action (wire:click)
       │
       ▼
     Livewire AJAX ──► Component method ──► Service layer ──► Eloquent / DB
                           │
                           ├─ throws ValidationException  → 422 (wire:model errors)
                           ├─ throws AuthorizationException → 403
                           ├─ throws StaleRecordException  → 409
                           └─ success: redirect / emit event
```

### "REST-style endpoints" — reconciling the prompt with Livewire 3

The project prompt (see `metadata.json`) calls for *"Laravel to expose
REST-style endpoints consumed by Livewire components"*. In modern Laravel
11 + Livewire 3 this is **already what we do**, just not as hand-authored
per-resource routes. Every `wire:click` action flows through a single
HTTP endpoint (`POST /livewire/update`) that accepts a JSON payload of
`{component, snapshot, calls: [{method, params}]}` and returns a JSON
patch. Under the hood:

- Transport is HTTP + JSON.
- Every call passes through the normal Laravel middleware stack
  (`auth`, `account.status`, CSRF, throttle). There is no bypass.
- Validation uses the standard `Livewire\Validate` / `$rules` pipeline
  and surfaces as 422 with the same shape a FormRequest would emit.
- Idempotency keys (`idempotency_key` column + `MembershipService` /
  `PaymentService` dedupe) are enforced at the **service layer**, so
  every mutation path — HTTP or queue worker — passes through the
  same guard.
- Optimistic locking (`saveWithLock()` → `StaleRecordException` → 409)
  is also at the service layer for the same reason.
- The complete list of component routes, actions, parameters, auth
  gates, success redirects, and error codes lives in
  `docs/api-spec.md` and is treated as the canonical API reference.

**Two transport layers share a single service layer.**

A thin `App\Http\Controllers\Api\*` namespace exposes the same operations
as the Livewire components via proper REST routes registered under the
`/api` prefix (`routes/api.php`, `bootstrap/app.php`).  The REST layer:

- delegates exclusively to the same services the Livewire components call
  — there is no duplicated business logic;
- is protected by the same session guard (`auth:web`) plus CSRF
  verification (`VerifyApiCsrfToken`) and role-gating middleware
  (`account.status`, `finance`, `credentialing`);
- carries the same idempotency-key contract: `Idempotency-Key` header or
  `idempotency_key` body field is accepted on every mutation and
  propagated to the service layer.

Current REST surface (see `docs/api-spec.md §"REST API"` for the full
reference):

| Method | Path | Role gate |
|---|---|---|
| GET | `/api/trips` | authenticated |
| GET | `/api/trips/{trip}` | authenticated (PUBLISHED/FULL only; admin sees all) |
| POST | `/api/trips/{trip}/hold` | authenticated |
| POST | `/api/trips/{trip}/waitlist` | authenticated |
| POST | `/api/payments` | `finance` |
| POST | `/api/payments/{payment}/confirm` | `finance` |
| POST | `/api/payments/{payment}/void` | `finance` |
| POST | `/api/credentialing/cases/{case}/assign` | `credentialing` |
| POST | `/api/credentialing/cases/{case}/approve` | `credentialing` |
| POST | `/api/credentialing/cases/{case}/reject` | `credentialing` |

**Why the "one guard, one rule book" property is preserved:** every
security-sensitive invariant — ownership checks, idempotency deduplication,
state-machine transitions, audit logging — lives at the service layer, not
at the transport.  Adding a second transport does not add a second place for
those invariants to regress; it adds a second entry point that reaches the
same guard.

Livewire component mutations still go through `POST /livewire/update`
(the wire-protocol endpoint) and continue to be the primary UI path.
The REST layer exists to satisfy the prompt's "REST-style endpoints
consumed by Livewire components" requirement and to provide a documented
machine-to-machine surface.

### Scheduled Commands

Five artisan commands run on a cron inside the container. Daily jobs are
pinned to the facility timezone (`config('app.facility_timezone')`, default
`America/New_York`) so the 23:59 settlement cutoff is honored regardless of
the server's `APP_TIMEZONE` (UTC in production).

| Command | Frequency | Role |
|---|---|---|
| `medvoyage:expire-seat-holds` | Every 10 minutes | Safety net only — real path is `App\Jobs\ReleaseExpiredHold` dispatched with `->delay($hold_expires_at)` from `SeatService::holdSeat()` |
| `medvoyage:expire-waitlist-offers` | Every 10 minutes | Safety net only — real path is `App\Jobs\ExpireWaitlistOfferJob` dispatched with `->delay($offer_expires_at)` from `WaitlistService::offerNextSeat()` |
| `medvoyage:reconcile-seats` | Every 5 minutes | Primary path (drift repair) |
| `medvoyage:close-settlement` | Daily 23:59 (facility time) | Primary path |
| `medvoyage:check-license-expiry` | Daily 01:00 (facility time) | Primary path |

**Why the seat-hold / waitlist-offer commands are demoted:** hold and
waitlist-offer expiry are driven by the **real-time WebSocket path** — see
`docs/questions.md §1.1 / §1.4`. When a hold or offer is created, the
service immediately queues a delayed job (`App\Jobs\ReleaseExpiredHold`,
`App\Jobs\NotifyHoldExpiring`, `App\Jobs\ExpireWaitlistOfferJob`) at the
exact expiry timestamp; the queue worker in the container picks them up
the instant they come due. The cron sweeps exist only to recover records
that were stranded if the queue worker was down at the moment the delayed
job was scheduled. That is why the cadence is 10 minutes, not 1.

---

## 2. Module Breakdown

### Auth

Handles registration, login, logout, and profile display.

- **Components:** `Auth\Login`, `Auth\Register`, `Auth\Profile`
- **Key rules:** Account lock after excessive failed logins (`locked_until`); account suspension by admin (`status = SUSPENDED`); guest-only routes redirect authenticated users.
- **Data:** `users`, `user_profiles`, `user_roles`, `sessions`

### Trips

Core booking module — browse, hold, sign up, manage.

- **Components:** `Trips\TripList`, `Trips\TripDetail`, `Trips\SignupWizard`, `Trips\MySignups`, `Trips\TripManage`
- **Services:** `TripService`, `SeatService`, `WaitlistService`
- **Key rules:** Only PUBLISHED trips are browseable; hold expires after `seat_hold_minutes` (default 10); FULL trips accept waitlist only; only ADMIN can create/publish/close/cancel trips; lead doctor must be APPROVED.
- **Data:** `trips`, `trip_signups`, `seat_holds`, `trip_waitlist_entries`, `trip_reviews`

### Credentialing

Doctor credential verification workflow.

- **Components:** `Credentialing\DoctorProfile`, `Credentialing\CaseList`, `Credentialing\CaseDetail`
- **Services:** `CredentialingService`, `DocumentService`
- **Key rules:** Doctor must upload LICENSE + BOARD_CERTIFICATION before submitting a case; only one active case per doctor; documents streamed securely (no public URLs); license expiry checked daily.
- **Data:** `doctors`, `doctor_documents`, `credentialing_cases`, `credentialing_actions`

### Membership

Plan catalog, purchase, top-up, and refund flows.

- **Components:** `Membership\PlanCatalog`, `Membership\MyMembership`, `Membership\PurchaseFlow`, `Membership\TopUpFlow`, `Membership\RefundRequest`, `Membership\OrderHistory`, `Membership\RefundApproval`
- **Services:** `MembershipService`
- **Key rules:** Only one active membership per user; top-up only within 30-day window; downgrade not allowed via top-up; refund request requires a PAID order and a reason ≥ 10 chars.
- **Data:** `membership_plans`, `membership_orders`

### Finance

Payment recording, confirmation, settlement reconciliation, invoicing, refunds.

- **Components:** `Finance\FinanceDashboard`, `Finance\PaymentIndex`, `Finance\PaymentRecord`, `Finance\PaymentDetail`, `Finance\SettlementIndex`, `Finance\SettlementDetail`, `Finance\InvoiceIndex`, `Finance\InvoiceBuilder`, `Finance\InvoiceDetail`, `Finance\StatementExport`
- **Services:** `PaymentService`, `SettlementService`, `InvoiceService`
- **Key rules:** Payment is RECORDED → CONFIRMED → (optionally) VOIDED; daily settlement auto-closes at 23:59; variance ≤ 1 cent → RECONCILED, else EXCEPTION; confirmation event IDs are idempotent (409 on reuse).
- **Data:** `payments`, `refunds`, `settlements`, `settlement_exceptions`, `invoices`, `invoice_line_items`

### Search & Recommendations

Full-text trip search with type-ahead, history, and ML-free recommendations.

- **Components:** `Search\TripSearch`, `Search\Recommendations`
- **Services:** `SearchService`, `RecommendationService`
- **Key rules:** Search history stored per user; type-ahead draws from `search_terms`; recommendations are strategy-based (config-driven, no model training).
- **Data:** `search_terms`, `user_search_histories`

### Admin

User management, audit log, and system configuration.

- **Components:** `Admin\UserList`, `Admin\UserDetail`, `Admin\AuditLogViewer`, `Admin\SystemConfig`
- **Services:** `AuditService`
- **Key rules:** All admin routes are ADMIN-only (403 for others); every significant action appends an `audit_log` row; `UserDetail` uses optimistic locking to prevent concurrent edits.
- **Data:** `audit_logs`, (all tables touched)

---

## 3. Data Flow Diagrams

### 3.1 Trip Signup Flow

```
User clicks "Hold Seat"
        │
        ▼
SeatService::holdSeat()
  ├─ check: user not already signed up / on waitlist
  ├─ check: trip status = PUBLISHED
  ├─ check: available_seats > 0
  ├─ decrement available_seats (DB transaction)
  ├─ create TripSignup (status=HOLD, version=1)
  ├─ create SeatHold (expires_at = now + seat_hold_minutes)
  └─ return signup
        │
        ▼
SignupWizard component mounted
  ├─ Step 1: Emergency contact + dietary
  ├─ Step 2: Payment tender + reference
  ├─ Step 3: Notes + submit
        │
        ▼
SeatService::confirmSeat()
  ├─ check: signup.status = HOLD
  ├─ check: hold not expired
  ├─ update signup: status=CONFIRMED, confirmed_at=now
  ├─ saveWithLock() — 409 if stale
  └─ redirect to dashboard
```

### 3.2 Waitlist Flow

```
User clicks "Join Waitlist"
        │
        ▼
WaitlistService::joinWaitlist()
  ├─ check: trip is PUBLISHED or FULL
  ├─ check: user not already signed up / on waitlist
  ├─ create TripWaitlistEntry (position = MAX + 1, status=WAITING)
        │
        ▼ (when a hold expires or signup is cancelled)
App\Jobs\ReleaseExpiredHold (delayed job, fires at hold_expires_at)
  ├─ re-fetch signup, no-op if already confirmed/cancelled
  ├─ SeatService::releaseSeat(EXPIRED)
  │    ├─ cancel signup, release SeatHold
  │    ├─ increment available_seats
  │    └─ broadcast SeatReleased on trip.{id} (Reverb)
  └─ WaitlistService::offerNextSeat()
       ├─ find first WAITING entry for trip
       ├─ update entry: status=OFFERED, offer_expires_at = now + waitlist_offer_minutes
       ├─ broadcast WaitlistOfferMade on user.{id} (Reverb) — real-time accept banner
       └─ queue App\Jobs\ExpireWaitlistOfferJob delayed until offer_expires_at
        │
        ▼ (user acts on offer — real-time, no polling)
TripDetail: holdSeat() — same as normal signup flow
  OR
App\Jobs\ExpireWaitlistOfferJob (delayed job, fires at offer_expires_at)
  └─ entry → EXPIRED → offerNextSeat() again

Both scheduled commands (medvoyage:expire-seat-holds, every 10 minutes;
medvoyage:expire-waitlist-offers, every 10 minutes) are safety-net sweeps
for stranded records after a queue-worker outage — they are not the
primary expiry path.
```

### 3.3 Payment-to-Settlement Flow

```
Finance staff records payment
        │
        ▼
PaymentService::recordPayment()
  ├─ idempotency check (idempotency_key + endpoint) — returns existing if found
  ├─ create Payment (status=RECORDED, version=1)
  └─ link to settlement for today (auto-create OPEN settlement if needed)
        │
        ▼
PaymentDetail: confirm(confirmationEventId)
  ├─ check: payment.status = RECORDED
  ├─ check: confirmationEventId not already used (409 if duplicate)
  ├─ Payment → CONFIRMED
  ├─ cascade: MembershipOrder → PAID  (if linked)
  └─ cascade: TripSignup → CONFIRMED  (if linked)
        │
        ▼
medvoyage:close-daily-settlement (daily 23:59)
  ├─ sum all CONFIRMED payments for today → net_amount_cents
  ├─ |variance| ≤ 1 cent → status=RECONCILED
  └─ else → status=EXCEPTION, create SettlementException rows
        │
        ▼
SettlementDetail: resolveException() / reReconcile()
```

### 3.4 Credentialing Flow

```
Doctor uploads documents (DoctorProfile)
  └─ DocumentService::store() → DoctorDocument record + file on disk

Doctor clicks "Submit Case"
  ├─ check: LICENSE + BOARD_CERTIFICATION documents present
  ├─ check: no active case for this doctor
  ├─ create CredentialingCase (status=SUBMITTED)
  └─ Doctor credentialing_status → UNDER_REVIEW
        │
        ▼
Reviewer (CaseDetail)
  ├─ assignReviewer()    → case.assigned_reviewer = reviewer.id
  ├─ startReview()       → SUBMITTED → IN_REVIEW
  ├─ requestMaterials()  → IN_REVIEW → MATERIALS_REQUESTED
  ├─ resubmitCase()      → MATERIALS_REQUESTED → RE_REVIEW  (doctor action)
  ├─ approve()           → case=APPROVED; doctor=APPROVED; doctor.activated_at=now
  └─ reject()            → case=REJECTED; doctor=REJECTED
```

---

## 4. State Machines

### 4.1 User Status

```
         register()
ACTIVE ◄──────────────── (new user)
  │
  ├──suspend()──► SUSPENDED ──activate()──► ACTIVE
  └──lock()────► LOCKED ────unlock()────► ACTIVE
```

| Status | Description |
|---|---|
| `ACTIVE` | Normal account |
| `SUSPENDED` | Admin-suspended; cannot log in |
| `LOCKED` | Too many failed logins; temporary |

### 4.2 Doctor Credentialing Status

```
NOT_SUBMITTED ──submitCase()──► UNDER_REVIEW
                                     │
                         ┌───────────┴───────────┐
                     approve()              reject()
                         │                     │
                         ▼                     ▼
                      APPROVED             REJECTED
                                              │
                                       resubmitCase()
                                              │
                                              ▼
                                         UNDER_REVIEW
```

### 4.3 Credentialing Case Status

```
SUBMITTED ──startReview()──► IN_REVIEW
               │
               ├──requestMaterials()──► MATERIALS_REQUESTED
               │                              │
               │                       resubmitCase()
               │                              │
               │                              ▼
               │                          RE_REVIEW ──startReview()──► IN_REVIEW
               │
               ├──approve()──► APPROVED
               └──reject()───► REJECTED
```

### 4.4 Trip Status

```
(new) ──save()──► DRAFT ──publish()──► PUBLISHED ──close()──► FULL
                                             │
                                        cancel()
                                             │
                                             ▼
                                         CANCELLED
```

| Status | Visible to members |
|---|---|
| `DRAFT` | No |
| `PUBLISHED` | Yes (available) |
| `FULL` | Yes (waitlist only) |
| `CANCELLED` | No |

### 4.5 Trip Signup Status

```
(holdSeat) ──► HOLD ──confirmSeat()──► CONFIRMED
                 │
          hold expires / void
                 │
                 ▼
            CANCELLED
```

### 4.6 Waitlist Entry Status

```
(joinWaitlist) ──► WAITING ──offerNext()──► OFFERED ──holdSeat()──► (WAITING removed)
                                               │
                                    offer expires
                                               │
                                               ▼
                                           EXPIRED
```

### 4.7 Payment Status

```
(recordPayment) ──► RECORDED ──confirm()──► CONFIRMED
                                   │
                                void()
                                   │
                                   ▼
                                VOIDED
```

### 4.8 Membership Order Status

```
(purchase/topUp) ──► PENDING ──payment confirmed──► PAID
                                                      │
                                               order expires
                                                      │
                                                      ▼
                                                  EXPIRED
```

### 4.9 Settlement Status

```
(daily create) ──► OPEN ──close()──► RECONCILED (variance ≤ 1¢)
                             │
                          EXCEPTION (variance > 1¢)
                             │
                    resolveException() × n
                             │
                    reReconcile()
                             │
                             ▼
                         RECONCILED
```

### 4.10 Invoice Status

```
(save) ──► DRAFT ──send()──► SENT ──markPaid()──► PAID
              │                │
           void()           void()
              │                │
              └──────►  VOIDED ◄┘
```

### 4.11 Refund Status

```
(submit) ──► PENDING ──approve()──► APPROVED
                │
            reject()
                │
                ▼
           REJECTED
```

---

## 5. Database Schema Summary

### Core / Auth

| Table | Key columns | Notes |
|---|---|---|
| `users` | `id (uuid)`, `username`, `email`, `status`, `failed_login_count`, `locked_until`, `version` | `HasUuids`, `HasOptimisticLocking` |
| `user_profiles` | `user_id (pk, fk)`, `first_name`, `last_name`, `address_encrypted`, `ssn_fragment_encrypted` | 1-to-1 with users; PII encrypted |
| `user_roles` | `user_id`, `role` | No timestamps; use `addRole()` / `removeRole()` |
| `sessions` | `id`, `user_id`, `payload` | DB-backed sessions |
| `audit_logs` | `id`, `actor_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `previous_hash`, `row_hash` | Append-only (PG trigger + model hooks); `row_hash` chain verified by `medvoyage:verify-audit-chain` |

### Credentialing

| Table | Key columns | Notes |
|---|---|---|
| `doctors` | `user_id (unique)`, `specialty`, `npi_number`, `license_number_encrypted`, `credentialing_status`, `version` | License PII encrypted + masked |
| `doctor_documents` | `doctor_id`, `document_type`, `file_path`, `checksum` | `(doctor_id, document_type, checksum)` unique |
| `credentialing_cases` | `doctor_id`, `status`, `assigned_reviewer`, `version` | `HasOptimisticLocking` |
| `credentialing_actions` | `case_id`, `action`, `actor_id`, `notes` | Append-only audit trail for each case |

### Trips

| Table | Key columns | Notes |
|---|---|---|
| `trips` | `lead_doctor_id`, `status`, `available_seats`, `booking_count`, `average_rating`, `version` | `HasOptimisticLocking` |
| `trip_signups` | `trip_id`, `user_id`, `status`, `hold_expires_at`, `idempotency_key`, `version` | `idempotency_key` unique |
| `seat_holds` | `signup_id (unique)`, `expires_at`, `released`, `release_reason` | 1-to-1 with signup |
| `trip_waitlist_entries` | `trip_id`, `user_id` (unique), `position`, `status`, `offer_expires_at`, `idempotency_key` (unique) | — |
| `trip_reviews` | `(trip_id, user_id)` unique, `rating (1-5)`, `status` | `HasOptimisticLocking` |

### Membership

| Table | Key columns | Notes |
|---|---|---|
| `membership_plans` | `name`, `price_cents`, `duration_months`, `tier`, `is_active`, `version` | — |
| `membership_orders` | `user_id`, `plan_id`, `order_type`, `amount_cents`, `previous_order_id`, `status`, `starts_at`, `expires_at`, `idempotency_key`, `version` | Self-referential FK for top-up chain |

### Finance

| Table | Key columns | Notes |
|---|---|---|
| `payments` | `user_id`, `tender_type`, `amount_cents`, `status`, `confirmation_event_id (unique)`, `settlement_id`, `idempotency_key`, `version` | — |
| `refunds` | `payment_id`, `amount_cents`, `refund_type`, `status`, `approved_by`, `idempotency_key`, `version` | — |
| `settlements` | `settlement_date (unique)`, `status`, `total_payments_cents`, `net_amount_cents`, `variance_cents`, `version` | One row per calendar day |
| `settlement_exceptions` | `settlement_id`, `exception_type`, `status`, `resolved_by`, `version` | — |
| `invoices` | `user_id`, `invoice_number (unique)`, `total_cents`, `status`, `version` | — |
| `invoice_line_items` | `invoice_id`, `description`, `amount_cents`, `line_type`, `sort_order` | — |

### Search

| Table | Key columns | Notes |
|---|---|---|
| `search_terms` | `term (unique)`, `category`, `usage_count` | Auto-populated by TripObserver on create/update |
| `user_search_histories` | `user_id`, `query`, `filters (jsonb)`, `result_count`, `searched_at` | Index on `(user_id, searched_at)` |

### Conventions

- All domain entity PKs are **UUIDs** (`HasUuids` trait); infrastructure tables (jobs, cache, etc.) use auto-increment integers.
- All monetary values are stored as **integer cents** (no `decimal` for money).
- Every table that supports concurrent edits carries a **`version` integer** and uses `HasOptimisticLocking`.
- PII columns use `_encrypted` (AES-256-GCM via `EncryptionService`) and `_mask` (display-safe suffix) pairs.
- `created_at` / `updated_at` are present on all tables except append-only or infrastructure tables.

---

## 6. Security

### Authentication & Session

- Password hashing: `bcrypt` (Laravel default, cost 12).
- Session driver: database (`sessions` table); session cookies are `HttpOnly`, `SameSite=Lax`, `Secure` in production.
- Remember-me token: stored in `remember_token` column (random 60-char string).
- Account lockout: 5 consecutive failed logins → `locked_until = now + 15 minutes`.

### Authorisation

- Role-based: `MEMBER`, `DOCTOR`, `ADMIN`, `CREDENTIALING_REVIEWER`, `FINANCE_SPECIALIST`.
- Each Livewire component calls `authorize()` in `mount()` before rendering (throws `AuthorizationException` → 403).
- Ownership checks (e.g. `SignupWizard`) compare `Auth::id()` against the resource's `user_id`.

### PII Encryption

- Fields encrypted at rest: `license_number`, `address`, `ssn_fragment`.
- Algorithm: AES-256-GCM via `EncryptionService` (wraps Laravel `Crypt`).
- Display: `_mask` columns store the last 4 digits / truncated version for UI display.
- Encrypted columns are never returned in search queries or exported in bulk.

### Audit Trail

- `AuditService::log()` is called for every significant action (role change, trip publish, payment confirm, case approve/reject, statement/document export, etc.).
- Each row stores `before_data` / `after_data` as JSONB.
- **Export traceability (audit Issue 5):** every export path emits an audit entry. `SettlementService::exportStatement()` records `settlement.statement_exported` (with the `regenerated` flag), and `DocumentService::download()` records `doctor_document.downloaded` with the doc type and checksum. Route closures delegate to these service methods so no export path can short-circuit the audit hook.
- **Tamper-evidence is enforced at three independent layers** (audit Issue 4):
  1. **Model layer:** `AuditLog::updating` / `deleting` hooks throw `LogicException`. No `updated_at`.
  2. **Database layer:** PostgreSQL `BEFORE UPDATE` / `BEFORE DELETE` triggers on `audit_logs` raise an exception, blocking bypass via the query builder or raw SQL.
  3. **Cryptographic layer:** every row stores `row_hash` = SHA-256 over a canonicalized payload (id, action, entity, actor, before/after, correlation/idempotency, created_at, **previous_hash**). Rows form a chain; `row_hash` of row N is the `previous_hash` of row N+1.
- Verification: `php artisan medvoyage:verify-audit-chain` walks the chain and fails on the first row whose `previous_hash` doesn't match the preceding `row_hash` or whose `row_hash` doesn't match its recomputed canonical value. Pin the current head hash out-of-band (e.g., log it to a WORM store) to make retroactive forgery detectable.

### File Security

- Doctor documents are stored outside `public/` (in `storage/app/private/doctor-documents/`).
- Downloads are served through `DocumentService::stream()` after an authorisation check (document owner, credentialing reviewer, or admin).
- File checksum (`sha256`) stored in `doctor_documents.checksum` and verified on download.

### Idempotency

**Service-layer is the sole enforcement path.** Every mutating service method
(`SeatService::holdSeat`, `PaymentService::recordPayment`,
`MembershipService::purchase` / `renew` / `topUp` / `requestRefund`, …)
accepts a caller-supplied `idempotencyKey` and dedupes on it by looking up a
`idempotency_key` column on the owning domain table:

| Domain write | Backing column |
|---|---|
| Seat hold | `trip_signups.idempotency_key` |
| Waitlist join | `trip_waitlist_entries.idempotency_key` |
| Payment record | `payments.idempotency_key` |
| Membership purchase / renew / top-up | `membership_orders.idempotency_key` |
| Refund request | `refunds.idempotency_key` |

Each column has a uniqueness guarantee, and every service checks for an
existing row with the same key *before* starting its transaction — a retry
returns the existing record instead of creating a duplicate or throwing.

Callers (Livewire components) are required to pass a **deterministic** key
derived from stable state (user id, order id, signup id, …). See e.g.
`SignupWizard::$paymentIdempotencyKey`, `TripDetail::$holdIdempotencyKey`, and
`RefundRequest::submit()`. Random UUIDs per click are a bug — they would give
every retry a fresh key and defeat the dedupe.

**No HTTP-layer middleware.** An earlier `IdempotencyMiddleware` inspected an
`X-Idempotency-Key` header on POST/PUT/PATCH requests against a separate
`idempotency_records` table, but in this Livewire-only app every mutation
flows through `POST /livewire/update` (the wire-protocol endpoint) and that
header is never sent. The middleware was never wired to any route and was
removed along with its backing table and model in migration
`2026_04_11_000002_drop_idempotency_records_table`. Service-layer dedupe is
transport-agnostic and covers HTTP, queue workers, and console commands with
a single enforcement surface.

### Optimistic Locking

- Models that support concurrent edits use `HasOptimisticLocking::saveWithLock()`.
- The UPDATE includes `WHERE version = $currentVersion`; 0 rows affected → `StaleRecordException` (HTTP 409).
- UI displays a "record was modified" error on 409 and re-fetches the latest data.

---

## 7. Recommendation Architecture

Recommendations are served by `RecommendationService` through the `Search\Recommendations` Livewire component. The system is **strategy-based**: each strategy is a PHP class implementing `RecommendationStrategy`, and the active list is defined in `config/recommendations.php`.

### Strategy Interface

```php
interface RecommendationStrategy
{
    public function label(): string;
    public function fetch(User $user, int $limit): Collection; // returns Trip[]
}
```

### Built-in Strategies

| Strategy class | Label | Logic |
|---|---|---|
| `MostBookedLast90Days` | "Popular Trips" | Trips with highest `booking_count` in the last 90 days |
| `SimilarSpecialty` | "Trips Like Yours" | PUBLISHED trips matching the user's past signup specialties |
| `UpcomingSoonest` | "Coming Up Soon" | PUBLISHED trips sorted by `start_date` ascending |

### Extending

To add a new recommendation section:
1. Create a class in `app/Strategies/` implementing `RecommendationStrategy`.
2. Append it to the `strategies` array in `config/recommendations.php`.
3. No other code changes are required (`RecommendationService` iterates the config array).

### Caching

Each strategy result is cached for 5 minutes (keyed by `recommendations:{userId}:{strategyClass}`). The cache is invalidated when the user books a new trip.

### Limits

Each strategy returns at most `config('recommendations.limit', 6)` trips. The `Recommendations` component renders one labeled card section per strategy.
