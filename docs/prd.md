# MedVoyage Provider & Trip Enrollment System — Implementation Specification

This file is the complete PRD. Refer to `claude.md` for tech stack, design system, and coding conventions that apply to every task.

---

## 1. Title

**MedVoyage Provider & Trip Enrollment System v1.0**
An offline-capable clinician credentialing, group medical trip enrollment, membership management, and billing reconciliation platform.

---

## 2. Tech Stack

Defined in `claude.md`. Summary: Laravel 11 + Livewire 3 + PostgreSQL 16 + Tailwind + Alpine.js + Laravel Reverb (local WebSocket). Fully offline. No external dependencies at runtime.

---

## 3. Execution Contract

### 3.1 Offline Constraints
- Fully functional without internet. All services, fonts, assets, and computation are local.
- No CDN, external API, payment gateway, email service, or DNS dependency at runtime.
- Self-hosted fonts. Queue uses database driver (no Redis).

### 3.2 Determinism
- All monetary values stored as integer cents. Display formatting via `formatCurrency()` helper.
- Dates stored in UTC, displayed in facility timezone.
- Settlement batch close is deterministic: runs at 23:59 local facility time daily.
- Seat hold expiry is exactly 10 minutes from creation, checked on every relevant request AND by scheduled job.

### 3.3 Decision Log
- All ambiguities and design decisions tracked in `docs/questions.md`.
- Single source of truth for "why" — no separate assumptions document.

---

## 4. Product Overview

### 4.1 Purpose
MedVoyage enables healthcare organizations to manage clinician credentialing, organize group medical trips with controlled enrollment, sell membership plans, handle internal payment recording and financial reconciliation — all without internet connectivity. The system maintains end-to-end auditability across every operational domain.

### 4.2 Core Domains
1. **Credentialing** — Doctor document management, multi-step review workflow
2. **Trip Enrollment** — Trip creation, seat management, guided signup, waitlist, holds
3. **Membership** — Plan purchasing, renewals, top-ups, refunds
4. **Finance** — Payment recording (offline tender), settlements, reconciliation, invoicing
5. **Search & Recommendations** — Keyword search, filters, pluggable recommendation strategies
6. **Audit & Security** — Tamper-evident logging, encryption, role-based access

---

## 5. In-Scope Modules

| Module | Key Capabilities |
|---|---|
| `auth` | Registration, login, lockout, session management, role assignment |
| `credentialing` | Doctor profiles, document upload (license/board cert/CV), review workflow with request-more-materials and rejection paths, activation |
| `trips` | Trip CRUD, capacity/seats, guided signup form, seat hold (10-min TTL), hold release, waitlist queue, real-time availability |
| `membership` | Plan catalog, purchase, renewal, 30-day top-up upgrade, refund (full/partial), plan history |
| `finance` | Offline payment recording (cash/check/card-on-file), daily settlement batch, reconciliation with $0.01 tolerance, invoice management, void, statement export |
| `search` | Full-text keyword search, multi-filter (specialty/date/difficulty/duration/prerequisites), sorting, type-ahead from local terms, personal search history |
| `recommendations` | Pluggable strategy classes (most booked 90 days, similar specialty, etc.), strategy registry |
| `reviews` | Trip reviews and ratings by members, moderation |
| `audit` | Append-only audit log, searchable by user/date/type/correlation ID |
| `admin` | User management, role assignment, system configuration |

---

## 6. Out of Scope

1. **External payment gateways** — Payments are offline tender recording only.
2. **Email/SMS notifications** — No external messaging services.
3. **Video/telehealth** — This is trip enrollment, not clinical delivery.
4. **Insurance verification** — No external insurance API calls.
5. **Mobile native apps** — Responsive web only.
6. **Multi-language** — English only.
7. **Multi-organization/tenancy** — Single organization deployment.
8. **External recommendation APIs** — All recommendation logic is local strategy classes.

---

## 7. Actors & Roles

| Role | Description |
|---|---|
| Member | Patients/members; browse trips, sign up, purchase memberships, leave reviews |
| Doctor | Clinicians; submit credentials, view assigned trips |
| Credentialing Reviewer | Staff reviewing doctor credentials; manage credentialing workflow |
| Finance Specialist | Staff handling payments, settlements, reconciliation, invoicing |
| System Administrator | Full access; user management, configuration, audit log access |

### Role Rules
- Self-registration creates ACTIVE Member. No admin approval needed for Members.
- Doctor accounts created by Admin or promoted from Member.
- Credentialing Reviewer, Finance Specialist, Admin assigned by Admin only.
- A user can hold multiple roles (e.g., Doctor + Member).
- At least one Admin exists (seeded at setup).

---

## 8. Core Data Model

### Entity Relationships

```
User 1──* UserRole (M2M)
User 1──1 UserProfile

Doctor 1──1 User
Doctor 1──* DoctorDocument
Doctor 1──* CredentialingCase
CredentialingCase 1──* CredentialingAction

Trip 1──1 Doctor (lead provider, must be APPROVED)
Trip 1──* TripSignup
TripSignup 1──1 SeatHold
Trip 1──* TripWaitlistEntry
Trip 1──* TripReview

MembershipPlan 1──* MembershipOrder
User 1──* MembershipOrder
MembershipOrder 1──* MembershipTopUp

User 1──* Payment
Payment 1──* Refund

Settlement 1──* SettlementException
Invoice 1──* InvoiceLineItem

SearchTerm (autocomplete dictionary)
UserSearchHistory (user, query, timestamp)
```

### Key Entities

#### User
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| username | VARCHAR(150) | UNIQUE, ^[a-zA-Z0-9._-]+$ |
| password_hash | VARCHAR(255) | bcrypt |
| email | VARCHAR(255) | UNIQUE |
| status | ENUM(PENDING, ACTIVE, SUSPENDED, DEACTIVATED) | Default ACTIVE for self-reg |
| failed_login_count | INT | Default 0 |
| locked_until | TIMESTAMP | NULL |
| version | INT | Default 1 |

#### UserProfile
| Field | Type | Constraints |
|---|---|---|
| user_id | FK(User) | PK |
| first_name | VARCHAR(100) | NOT NULL |
| last_name | VARCHAR(100) | NOT NULL |
| date_of_birth | DATE | NULL |
| phone | VARCHAR(20) | NULL |
| address_encrypted | BYTEA | AES encrypted |
| address_mask | VARCHAR(50) | |
| ssn_fragment_encrypted | BYTEA | AES encrypted, last 4 only |
| ssn_fragment_mask | VARCHAR(10) | e.g., "***-**-1234" |

#### Doctor
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| user_id | FK(User) | UNIQUE |
| specialty | VARCHAR(200) | NOT NULL |
| npi_number | VARCHAR(10) | NULL |
| license_number_encrypted | BYTEA | AES encrypted |
| license_number_mask | VARCHAR(20) | |
| license_state | VARCHAR(2) | US state code |
| license_expiry | DATE | NOT NULL |
| credentialing_status | ENUM(NOT_SUBMITTED, UNDER_REVIEW, MORE_MATERIALS_REQUESTED, APPROVED, REJECTED, EXPIRED) | Default NOT_SUBMITTED |
| activated_at | TIMESTAMP | NULL |
| version | INT | Default 1 |

#### DoctorDocument
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| doctor_id | FK(Doctor) | NOT NULL |
| document_type | ENUM(LICENSE, BOARD_CERTIFICATION, CV, INSURANCE, OTHER) | NOT NULL |
| file_path | VARCHAR(500) | Local storage |
| file_name | VARCHAR(255) | Original name |
| file_size | INT | Bytes, max 10 MB (10485760) |
| mime_type | ENUM(application/pdf, image/jpeg, image/png) | Validated by content |
| checksum | VARCHAR(64) | SHA-256, unique per doctor + document_type |
| uploaded_by | FK(User) | NOT NULL |
| uploaded_at | TIMESTAMP | UTC |

#### CredentialingCase
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| doctor_id | FK(Doctor) | NOT NULL |
| status | ENUM(SUBMITTED, INITIAL_REVIEW, MORE_MATERIALS_REQUESTED, RE_REVIEW, APPROVED, REJECTED) | NOT NULL |
| assigned_reviewer | FK(User) | NULL |
| submitted_at | TIMESTAMP | NOT NULL |
| resolved_at | TIMESTAMP | NULL |
| version | INT | Default 1 |

#### CredentialingAction
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| case_id | FK(CredentialingCase) | NOT NULL |
| action | ENUM(SUBMIT, ASSIGN, START_REVIEW, REQUEST_MATERIALS, RECEIVE_MATERIALS, APPROVE, REJECT) | NOT NULL |
| actor_id | FK(User) | NOT NULL |
| notes | TEXT | NULL |
| timestamp | TIMESTAMP | UTC |

#### Trip
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| title | VARCHAR(300) | NOT NULL |
| description | TEXT | Max 5000 chars |
| lead_doctor_id | FK(Doctor) | NOT NULL, must be APPROVED |
| specialty | VARCHAR(200) | NOT NULL |
| destination | VARCHAR(300) | NOT NULL |
| start_date | DATE | NOT NULL, must be future for publish |
| end_date | DATE | NOT NULL, > start_date |
| difficulty_level | ENUM(EASY, MODERATE, CHALLENGING) | NOT NULL |
| estimated_duration_days | INT | Computed from dates |
| prerequisites | TEXT | NULL |
| total_seats | INT | >= 1, <= 500 |
| available_seats | INT | Maintained by app logic |
| price_cents | INT | >= 0 |
| status | ENUM(DRAFT, PUBLISHED, FULL, CLOSED, CANCELLED) | Default DRAFT |
| booking_count | INT | Default 0 |
| average_rating | DECIMAL(3,2) | NULL |
| version | INT | Default 1 |
| created_by | FK(User) | NOT NULL |

#### TripSignup
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| trip_id | FK(Trip) | NOT NULL |
| user_id | FK(User) | NOT NULL |
| status | ENUM(HOLD, CONFIRMED, CANCELLED, EXPIRED) | Default HOLD |
| hold_expires_at | TIMESTAMP | created_at + 10 minutes |
| confirmed_at | TIMESTAMP | NULL |
| cancelled_at | TIMESTAMP | NULL |
| payment_id | FK(Payment) | NULL |
| idempotency_key | VARCHAR(64) | UNIQUE |
| version | INT | Default 1 |

#### SeatHold
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| trip_id | FK(Trip) | NOT NULL |
| signup_id | FK(TripSignup) | UNIQUE |
| held_at | TIMESTAMP | UTC |
| expires_at | TIMESTAMP | held_at + 10 min |
| released | BOOLEAN | Default FALSE |
| released_at | TIMESTAMP | NULL |
| release_reason | ENUM(CONFIRMED, EXPIRED, CANCELLED, MANUAL) | NULL |

#### TripWaitlistEntry
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| trip_id | FK(Trip) | NOT NULL |
| user_id | FK(User) | NOT NULL |
| position | INT | Auto-assigned, FIFO |
| status | ENUM(WAITING, OFFERED, ACCEPTED, DECLINED, EXPIRED) | Default WAITING |
| offered_at | TIMESTAMP | NULL |
| offer_expires_at | TIMESTAMP | offered_at + 10 min |
| UNIQUE | (trip_id, user_id) per active entry | |

#### TripReview
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| trip_id | FK(Trip) | NOT NULL |
| user_id | FK(User) | NOT NULL |
| rating | INT | 1-5 |
| review_text | TEXT | Max 2000 chars, optional |
| status | ENUM(ACTIVE, FLAGGED, REMOVED) | Default ACTIVE |
| version | INT | Default 1 |
| UNIQUE | (trip_id, user_id) | One per user per trip |

#### MembershipPlan
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| name | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| price_cents | INT | NOT NULL |
| duration_months | INT | 1-60 |
| tier | ENUM(BASIC, STANDARD, PREMIUM) | NOT NULL |
| is_active | BOOLEAN | Default TRUE |
| version | INT | Default 1 |

#### MembershipOrder
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| user_id | FK(User) | NOT NULL |
| plan_id | FK(MembershipPlan) | NOT NULL |
| order_type | ENUM(PURCHASE, RENEWAL, TOP_UP) | NOT NULL |
| amount_cents | INT | NOT NULL |
| previous_order_id | FK(self) | NULL |
| status | ENUM(PENDING, PAID, REFUNDED, PARTIALLY_REFUNDED, VOIDED) | Default PENDING |
| starts_at | TIMESTAMP | NOT NULL |
| expires_at | TIMESTAMP | NOT NULL |
| top_up_eligible_until | TIMESTAMP | starts_at + 30 days |
| payment_id | FK(Payment) | NULL |
| idempotency_key | VARCHAR(64) | UNIQUE |
| version | INT | Default 1 |

#### Payment
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| user_id | FK(User) | NOT NULL |
| tender_type | ENUM(CASH, CHECK, CARD_ON_FILE) | NOT NULL |
| amount_cents | INT | NOT NULL |
| reference_number | VARCHAR(100) | NULL |
| status | ENUM(RECORDED, CONFIRMED, VOIDED, REFUNDED, PARTIALLY_REFUNDED) | Default RECORDED |
| confirmed_at | TIMESTAMP | NULL |
| confirmation_event_id | VARCHAR(64) | Idempotency for internal confirmation |
| settlement_id | FK(Settlement) | NULL |
| idempotency_key | VARCHAR(64) | UNIQUE |
| version | INT | Default 1 |

#### Refund
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| payment_id | FK(Payment) | NOT NULL |
| amount_cents | INT | <= original payment |
| refund_type | ENUM(FULL, PARTIAL) | NOT NULL |
| reason | TEXT | Min 10 chars |
| status | ENUM(PENDING, APPROVED, PROCESSED, REJECTED) | Default PENDING |
| approved_by | FK(User) | NULL |
| processed_at | TIMESTAMP | NULL |
| idempotency_key | VARCHAR(64) | UNIQUE |
| version | INT | Default 1 |

#### Settlement
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| settlement_date | DATE | UNIQUE |
| status | ENUM(OPEN, CLOSED, RECONCILED, EXCEPTION) | Default OPEN |
| total_payments_cents | INT | |
| total_refunds_cents | INT | |
| net_amount_cents | INT | payments - refunds |
| expected_amount_cents | INT | |
| variance_cents | INT | net - expected |
| closed_at | TIMESTAMP | NULL |
| reconciled_by | FK(User) | NULL |
| reconciled_at | TIMESTAMP | NULL |
| statement_file_path | VARCHAR(500) | NULL |
| version | INT | Default 1 |

#### SettlementException
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| settlement_id | FK(Settlement) | NOT NULL |
| exception_type | ENUM(VARIANCE, MISSING_CONFIRMATION, DUPLICATE_PAYMENT, ORPHAN_REFUND) | NOT NULL |
| description | TEXT | NOT NULL |
| amount_cents | INT | NULL |
| status | ENUM(OPEN, RESOLVED, WRITTEN_OFF) | Default OPEN |
| resolved_by | FK(User) | NULL |
| resolution_note | TEXT | NULL, min 10 chars when resolving |
| version | INT | Default 1 |

#### Invoice
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| user_id | FK(User) | NOT NULL |
| invoice_number | VARCHAR(50) | UNIQUE, auto: MV-YYYY-NNNNN |
| total_cents | INT | NOT NULL |
| status | ENUM(DRAFT, ISSUED, PAID, VOIDED) | Default DRAFT |
| issued_at | TIMESTAMP | NULL |
| due_date | DATE | NULL |
| notes | TEXT | NULL |
| version | INT | Default 1 |

#### InvoiceLineItem
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| invoice_id | FK(Invoice) | NOT NULL |
| description | VARCHAR(500) | NOT NULL |
| amount_cents | INT | NOT NULL |
| line_type | ENUM(TRIP_SIGNUP, MEMBERSHIP, ADJUSTMENT, REFUND_CREDIT) | NOT NULL |
| reference_id | UUID | NULL |
| sort_order | INT | NOT NULL |

#### AuditLog
Defined in `claude.md`. Append-only. Fields: actor_id, action, entity_type, entity_id, before_data, after_data, ip_address, idempotency_key, correlation_id, timestamp.

#### SearchTerm
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| term | VARCHAR(200) | UNIQUE |
| category | ENUM(SPECIALTY, DESTINATION, TRIP_NAME, DOCTOR_NAME) | NOT NULL |
| usage_count | INT | Default 0 |

#### UserSearchHistory
| Field | Type | Constraints |
|---|---|---|
| id | UUID | PK |
| user_id | FK(User) | NOT NULL |
| query | VARCHAR(500) | NOT NULL |
| filters_json | JSON | |
| searched_at | TIMESTAMP | UTC |
| | | Keep last 20 per user |

#### IdempotencyRecord
Defined in `claude.md`. Key, endpoint, response_status, response_body, created_at. Expires 24h.

---

## 9. Business Rules

### 9.1 Authentication
| ID | Rule |
|---|---|
| AUTH-01 | Password: min 10 chars, 1 upper, 1 lower, 1 digit, 1 special. |
| AUTH-02 | 5 failed logins → locked 15 min. Count doesn't increment during lockout. Timer absolute. |
| AUTH-03 | Successful login resets count and clears lock. |
| AUTH-04 | Session: 8h inactivity timeout, 24h absolute lifetime. |
| AUTH-05 | Self-registration → ACTIVE Member. No approval needed. |

### 9.2 Credentialing
| ID | Rule |
|---|---|
| CRED-01 | Required for submission: LICENSE + BOARD_CERTIFICATION. CV optional. |
| CRED-02 | Files: PDF/JPEG/PNG only, max 10 MB. |
| CRED-03 | SHA-256 checksum on upload. Duplicate checksum for same doctor + doc type → reject. |
| CRED-04 | Case must be assigned to reviewer before review starts. |
| CRED-05 | "Request materials" returns case to doctor with notes. Doctor uploads and resubmits. |
| CRED-06 | Approval → doctor.credentialing_status = APPROVED, activated_at = now. Only APPROVED doctors lead trips. |
| CRED-07 | Rejection is final for that case. New case with new docs required. |
| CRED-08 | Daily job: if license_expiry < today → doctor status → EXPIRED. |

### 9.3 Trip Enrollment
| ID | Rule |
|---|---|
| TRIP-01 | Publish requires: lead doctor APPROVED, seats >= 1, start_date future, price set. |
| TRIP-02 | Signup → HOLD status, seat held 10 min. available_seats decremented immediately. |
| TRIP-03 | Hold expires at exactly 10 min: signup → EXPIRED, seat released, waitlist next offered. |
| TRIP-04 | Hold expiry checked lazily (on request) + eagerly (job every 2 min). |
| TRIP-05 | Confirming signup requires linked payment. |
| TRIP-06 | available_seats = 0 → trip status FULL. New signups go to waitlist. |
| TRIP-07 | Waitlist is FIFO. Seat release → first WAITING entry OFFERED with 10-min window. |
| TRIP-08 | Expired waitlist offer → next entry offered. |
| TRIP-09 | Max one active signup (HOLD/CONFIRMED) OR one active waitlist entry per user per trip. |
| TRIP-10 | Cancel CONFIRMED → seat released, refund initiated. |
| TRIP-11 | booking_count: +1 on CONFIRMED, -1 on CANCELLED. |
| TRIP-12 | average_rating recomputed on review create/update/delete. |

### 9.4 Membership
| ID | Rule |
|---|---|
| MEM-01 | Purchase: starts_at = now, expires_at = now + duration_months. |
| MEM-02 | Renewal extends from current expires_at. If expired, from now. |
| MEM-03 | Top-up within 30 days: price difference only. Upgrades only (higher tier). |
| MEM-04 | Top-up eligible: top_up_eligible_until >= today AND order PAID. |
| MEM-05 | Full refund → REFUNDED, expires_at = now (immediate termination). |
| MEM-06 | Partial refund → PARTIALLY_REFUNDED, membership stays active until original expiry. |
| MEM-07 | Refunds require Finance Specialist approval. |

### 9.5 Finance
| ID | Rule |
|---|---|
| FIN-01 | Payments are offline tender: CASH, CHECK, CARD_ON_FILE. No gateway. |
| FIN-02 | Confirmation is internal event with idempotency (confirmation_event_id). |
| FIN-03 | Void: only RECORDED or CONFIRMED. Excluded from settlement. |
| FIN-04 | Settlement batch: daily at 23:59 local. Includes CONFIRMED payments + PROCESSED refunds for that day. |
| FIN-05 | Settlement generates local statement file. |
| FIN-06 | Variance > $0.01 → EXCEPTION with SettlementExceptions created. |
| FIN-07 | Variance <= $0.01 → auto-RECONCILED. |
| FIN-08 | Exceptions must be RESOLVED or WRITTEN_OFF before re-reconciliation. |
| FIN-09 | Invoice numbers: MV-{YYYY}-{5-digit seq}. |
| FIN-10 | Void invoice: only DRAFT or ISSUED. PAID cannot be voided. |
| FIN-11 | Integer cents everywhere. formatCurrency() for display. |

### 9.6 Search
| ID | Rule |
|---|---|
| SRCH-01 | Full-text on: trip title, description, specialty, destination, doctor name. |
| SRCH-02 | Filters: specialty, date range, difficulty, duration range, prerequisites. |
| SRCH-03 | Sort: most booked, newest, highest rated, price asc, price desc. |
| SRCH-04 | Type-ahead from SearchTerm table, top 5 by usage_count, min 2 chars. |
| SRCH-05 | Search history: last 20 per user. User can clear all. |
| SRCH-06 | Strategies implement RecommendationStrategy interface: recommend(User, limit): Collection. |
| SRCH-07 | Default strategies: MostBookedLast90Days, SimilarSpecialty, UpcomingSoonest. |
| SRCH-08 | Strategy registry in config — swappable without code changes. |

### 9.7 Reviews
| ID | Rule |
|---|---|
| REV-01 | Only Members with CONFIRMED signup + trip ended can review. |
| REV-02 | One review per user per trip. |
| REV-03 | Rating 1-5. Text max 2000 chars, optional. |
| REV-04 | Admin can flag → FLAGGED (hidden). |

### 9.8 General
| ID | Rule |
|---|---|
| AUD-01 | Audit log append-only. No update/delete. |
| AUD-02 | Log every create, update, status change, approval, rejection, void, export. |
| AUD-03 | Searchable by: user, date range, entity type, action, correlation ID. |
| AUD-04 | Optimistic locking on all key tables (version column). |
| AUD-05 | Idempotency on all POST endpoints (X-Idempotency-Key). |
| AUD-06 | Sensitive fields encrypted at rest, masked by role. |
| AUD-07 | Files: max 10 MB, PDF/JPEG/PNG, SHA-256 checksum, local storage. |

---

## 10. State Machines

### 10.1 User Account
```
[PENDING] ──(Admin activates)──► [ACTIVE]
[ACTIVE] ──(Admin suspends)──► [SUSPENDED]
[ACTIVE] ──(Admin deactivates)──► [DEACTIVATED]
[SUSPENDED] ──(Admin reactivates)──► [ACTIVE]
[SUSPENDED] ──(Admin deactivates)──► [DEACTIVATED]
[DEACTIVATED] ──(terminal)
```
Self-registered Members start ACTIVE. PENDING for admin-created accounts only.

### 10.2 Credentialing Case
```
[SUBMITTED] ──(Assign reviewer)──► [INITIAL_REVIEW]
[INITIAL_REVIEW] ──(Request materials)──► [MORE_MATERIALS_REQUESTED]
[INITIAL_REVIEW] ──(Approve)──► [APPROVED]
[INITIAL_REVIEW] ──(Reject)──► [REJECTED]
[MORE_MATERIALS_REQUESTED] ──(Doctor resubmits)──► [RE_REVIEW]
[RE_REVIEW] ──(Approve)──► [APPROVED]
[RE_REVIEW] ──(Reject)──► [REJECTED]
[RE_REVIEW] ──(Request more)──► [MORE_MATERIALS_REQUESTED]
[APPROVED] ──(terminal)
[REJECTED] ──(terminal)
```

### 10.3 Doctor Credentialing Status
```
[NOT_SUBMITTED] ──(Submit case)──► [UNDER_REVIEW]
[UNDER_REVIEW] ──(Case requests materials)──► [MORE_MATERIALS_REQUESTED]
[UNDER_REVIEW] ──(Approved)──► [APPROVED]
[UNDER_REVIEW] ──(Rejected)──► [REJECTED]
[MORE_MATERIALS_REQUESTED] ──(Resubmit)──► [UNDER_REVIEW]
[APPROVED] ──(License expires)──► [EXPIRED]
[REJECTED] ──(New case)──► [UNDER_REVIEW]
[EXPIRED] ──(New case)──► [UNDER_REVIEW]
```

### 10.4 Trip Status
```
[DRAFT] ──(Publish)──► [PUBLISHED]
[PUBLISHED] ──(All seats filled)──► [FULL]
[PUBLISHED] ──(Close)──► [CLOSED]
[FULL] ──(Seat released)──► [PUBLISHED]
[FULL] ──(Close)──► [CLOSED]
[DRAFT|PUBLISHED|FULL] ──(Cancel)──► [CANCELLED]
[CLOSED] ──(terminal)
[CANCELLED] ──(terminal)
```

### 10.5 Trip Signup
```
[HOLD] ──(Payment confirmed)──► [CONFIRMED]
[HOLD] ──(10 min expires)──► [EXPIRED]
[HOLD] ──(Cancel)──► [CANCELLED]
[CONFIRMED] ──(Cancel)──► [CANCELLED]
[EXPIRED] ──(terminal)
[CANCELLED] ──(terminal)
```

### 10.6 Waitlist Entry
```
[WAITING] ──(Seat releases, next in line)──► [OFFERED]
[OFFERED] ──(Accept within 10 min)──► [ACCEPTED] → creates TripSignup(HOLD)
[OFFERED] ──(10 min expires)──► [EXPIRED]
[OFFERED] ──(Decline)──► [DECLINED]
[WAITING] ──(Cancel)──► [DECLINED]
[ACCEPTED|EXPIRED|DECLINED] ──(terminal)
```

### 10.7 Membership Order
```
[PENDING] ──(Payment confirmed)──► [PAID]
[PENDING] ──(Void)──► [VOIDED]
[PAID] ──(Full refund)──► [REFUNDED]
[PAID] ──(Partial refund)──► [PARTIALLY_REFUNDED]
[PARTIALLY_REFUNDED] ──(terminal for refunds)
[REFUNDED|VOIDED] ──(terminal)
```

### 10.8 Payment
```
[RECORDED] ──(Confirm)──► [CONFIRMED]
[RECORDED] ──(Void)──► [VOIDED]
[CONFIRMED] ──(Void)──► [VOIDED]
[CONFIRMED] ──(Full refund)──► [REFUNDED]
[CONFIRMED] ──(Partial refund)──► [PARTIALLY_REFUNDED]
[VOIDED|REFUNDED] ──(terminal)
```

### 10.9 Refund
```
[PENDING] ──(Approve)──► [APPROVED]
[PENDING] ──(Reject)──► [REJECTED]
[APPROVED] ──(Process)──► [PROCESSED]
[REJECTED|PROCESSED] ──(terminal)
```

### 10.10 Settlement
```
[OPEN] ──(Batch close 23:59)──► [CLOSED]
[CLOSED] ──(Variance <= $0.01)──► [RECONCILED]
[CLOSED] ──(Variance > $0.01)──► [EXCEPTION]
[EXCEPTION] ──(All exceptions resolved)──► [RECONCILED]
[RECONCILED] ──(terminal)
```

### 10.11 Invoice
```
[DRAFT] ──(Issue)──► [ISSUED]
[DRAFT] ──(Void)──► [VOIDED]
[ISSUED] ──(Mark paid)──► [PAID]
[ISSUED] ──(Void)──► [VOIDED]
[PAID|VOIDED] ──(terminal)
```

---

## 11. Permissions Matrix

| Resource | Member | Doctor | Cred Reviewer | Finance | Admin |
|---|---|---|---|---|---|
| Browse/search trips | ✅ | ✅ | ❌ | ❌ | ✅ |
| Sign up for trips | ✅ | ❌ | ❌ | ❌ | ✅ |
| Leave reviews | ✅ | ❌ | ❌ | ❌ | ✅ |
| View own signups/orders | ✅ | ✅ | ❌ | ❌ | ✅ |
| Create/edit trips | ❌ | ❌ | ❌ | ❌ | ✅ |
| Submit credentials | ❌ | ✅ | ❌ | ❌ | ❌ |
| Upload doctor docs | ❌ | ✅ | ✅ | ❌ | ✅ |
| Review credentials | ❌ | ❌ | ✅ | ❌ | ✅ |
| Purchase membership | ✅ | ✅ | ❌ | ❌ | ✅ |
| Record/confirm payments | ❌ | ❌ | ❌ | ✅ | ✅ |
| Process refunds | ❌ | ❌ | ❌ | ✅ | ✅ |
| Void payments/invoices | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage settlements | ❌ | ❌ | ❌ | ✅ | ✅ |
| Export statements | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage invoices | ❌ | ❌ | ❌ | ✅ | ✅ |
| Manage users/roles | ❌ | ❌ | ❌ | ❌ | ✅ |
| View audit logs | ❌ | ❌ | ❌ | ❌ | ✅ |
| Flag/remove reviews | ❌ | ❌ | ❌ | ❌ | ✅ |
| View sensitive unmasked | Own only | Own only | ❌ | ❌ | ✅ (logged) |

---

## 12. Scheduled Jobs

| Job | Schedule | Description |
|---|---|---|
| ExpireSeatHolds | Every 2 min | Expire HOLD signups past 10-min TTL, release seats, offer to waitlist |
| ExpireWaitlistOffers | Every 2 min | Expire OFFERED entries past 10 min, offer next |
| CloseDailySettlement | Daily 23:59 local | Close settlement, generate statement, reconcile or flag |
| CheckLicenseExpiry | Daily 06:00 UTC | APPROVED doctors with expired license → EXPIRED |
| CleanIdempotencyRecords | Daily 03:00 UTC | Remove records > 24h old |
| CleanExpiredSessions | Daily 04:00 UTC | Remove expired sessions |

---

## 13. Validation Rules

### Strings
| Field | Min | Max | Pattern |
|---|---|---|---|
| username | 3 | 150 | ^[a-zA-Z0-9._-]+$ |
| first/last name | 1 | 100 | Unicode letters, spaces, hyphens |
| trip title | 1 | 300 | Printable Unicode |
| trip description | 0 | 5000 | |
| review text | 0 | 2000 | |
| refund/exception reason | 10 | 2000 | |

### Numeric
| Field | Min | Max |
|---|---|---|
| total_seats | 1 | 500 |
| price_cents | 0 | 99999999 |
| rating | 1 | 5 |
| duration_months | 1 | 60 |

### Files
| Context | Types | Max |
|---|---|---|
| Doctor documents | PDF, JPEG, PNG | 10 MB |

---

## 14. Non-Functional

- **Offline:** Full operation without internet.
- **Reliability:** PostgreSQL ACID + optimistic locking.
- **Recovery:** DB queue driver; jobs resume on restart. Stale holds cleaned by scheduled job.
- **Performance:** Pages < 1s. Seat check < 200ms. Search < 500ms.
- **Capacity:** 100 concurrent users.

---

## 15. Phases

### Phase 1: Foundation (Weeks 1–2)
Auth, users, roles, profiles, encryption, masking, audit, idempotency, optimistic locking, UI shell with full design system, fonts, reusable components.

### Phase 2: Credentialing (Weeks 3–4)
Doctor, documents, case workflow, reviewer assignment, all credential paths, license expiry job, UI.

### Phase 3: Trips & Enrollment (Weeks 5–7)
Trip CRUD, seats, signup, hold/expire/release, waitlist FIFO, payment link, reviews, UI.

### Phase 4: Membership & Finance (Weeks 8–10)
Plans, orders, purchase/renew/top-up, refund, payments, confirmation, settlement, reconciliation, invoicing, void, export, UI.

### Phase 5: Search & Polish (Weeks 11–12)
Full-text search, filters, sort, type-ahead, history, recommendations, strategy registry, workflow tests, seed data, docs.

---

## 16. Definition of Done

1. All 11 state machines enforced server-side.
2. All permissions enforced.
3. Audit log on every write.
4. Optimistic locking prevents stale overwrites (409).
5. Idempotency prevents duplicate orders/payments/refunds.
6. Seat holds expire at exactly 10 min; waitlist offers expire correctly.
7. Settlement flags variance > $0.01.
8. All money in integer cents.
9. Encryption + masking verified.
10. File validation (type, size, checksum dedup).
11. Search with all filters/sorts works.
12. Recommendation strategies pluggable via config.
13. Fully offline.
14. Tests ≥ 80% coverage on business logic.
15. UI follows design system from claude.md — not generic.

---

## 17. Deliverables

| Deliverable | Description |
|---|---|
| Laravel Application | Backend + Livewire frontend, all modules |
| Migrations | Full chain |
| Seed Data | `db:seed-demo` command: admin, members, doctors, trips, plans, payments, settlements |
| Test Suite — Unit | `tests/Unit/` — isolated service, model, enum, helper tests (Pest) |
| Test Suite — Feature | `tests/Feature/` — HTTP + API workflow tests (Pest) |
| Test Suite — Frontend | `tests/Frontend/` — Livewire component rendering + interaction tests (Pest) |
| `run_tests.sh` | Root script: runs all test layers. Flags: --unit, --feature, --frontend, --coverage |
| `README.md` | Quick start, demo accounts, test instructions, project structure |
| Fonts | DM Sans, IBM Plex Sans, IBM Plex Mono in /public/fonts/ |
| Config | .env.example, scheduled tasks, queue config |
| `docker-compose.yml` | Laravel + PostgreSQL. `docker-compose up` = fully running app, no manual steps |
| Makefile | serve, test, test-unit, test-api, test-frontend, coverage, migrate, seed, shell, tinker, fresh |
| `docs/api-spec.md` | All endpoints |
| `docs/design.md` | Architecture + state machines |
| `docs/questions.md` | Resolved ambiguities |
| `claude.md` | Project instructions (root, **removed at project end**) |

**During development:** claude.md at project root, PRD.md and build-prompts.md are your working files (not committed).
**After final step:** docs/ contains EXACTLY api-spec.md, design.md, questions.md. claude.md is deleted from repo. README.md, run_tests.sh, docker-compose.yml, Makefile, .gitattributes, .env.example stay at root.