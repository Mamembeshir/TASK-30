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

### No Separate REST API

All mutations happen through **Livewire actions** (server-to-server AJAX). There are no standalone API endpoints; the Livewire wire-protocol is the only mutation channel.

### Scheduled Commands

Five artisan commands run on a cron inside the container:

| Command | Frequency |
|---|---|
| `medvoyage:expire-seat-holds` | Every minute |
| `medvoyage:expire-waitlist-offers` | Every minute |
| `medvoyage:check-license-expiry` | Daily |
| `medvoyage:close-daily-settlement` | Daily 23:59 |
| `medvoyage:reconcile-seats` | Every 5 minutes |

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
medvoyage:expire-seat-holds (every minute)
  ├─ find expired HOLD signups
  ├─ cancel signup, release SeatHold
  ├─ increment available_seats
  └─ WaitlistService::offerNext()
       ├─ find first WAITING entry for trip
       ├─ update entry: status=OFFERED, offer_expires_at = now + waitlist_offer_minutes
       └─ (notify user — UI badge)
        │
        ▼ (user acts on offer)
TripDetail: holdSeat() — same as normal signup flow
  OR
medvoyage:expire-waitlist-offers (every minute)
  └─ entry → EXPIRED → offerNext() again
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
| `audit_logs` | `id`, `actor_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `previous_hash` | Append-only; tamper-evident chain via `previous_hash` |
| `idempotency_records` | `idempotency_key`, `endpoint` (unique together), `expires_at` | TTL-based; prevents duplicate mutations |

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
| `trip_waitlist_entries` | `trip_id`, `user_id` (unique), `position`, `status`, `offer_expires_at` | — |
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

- `AuditService::log()` is called for every significant action (role change, trip publish, payment confirm, case approve/reject, etc.).
- Each row stores `before_data` / `after_data` as JSONB and a `previous_hash` (SHA-256 of the prior row) forming a tamper-evident chain.
- Append-only: no `updated_at`; no `UPDATE` on `audit_logs`.

### File Security

- Doctor documents are stored outside `public/` (in `storage/app/private/doctor-documents/`).
- Downloads are served through `DocumentService::stream()` after an authorisation check (document owner, credentialing reviewer, or admin).
- File checksum (`sha256`) stored in `doctor_documents.checksum` and verified on download.

### Idempotency

- Mutation endpoints (holdSeat, recordPayment, purchase membership, etc.) accept a client-generated `idempotency_key`.
- `IdempotencyRecord` stores the key + endpoint + response for `idempotency_ttl_hours` hours (default 24).
- On replay: the stored response is returned without re-executing the action.

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
