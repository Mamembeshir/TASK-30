# MedVoyage — Design Decisions & Open Questions

This document records non-obvious decisions made during implementation and open questions that may affect future development.

---

## Resolved Decisions

### AUTH-01 — Account Lockout Threshold
**Decision:** Lock account after 5 consecutive failed logins for 15 minutes.
**Rationale:** Balance between security and user friction. Avoids permanent lockout (which causes support burden) while still defeating brute-force attempts.

### AUTH-02 — Guest-Only Routes
**Decision:** `/login` and `/register` redirect authenticated users to `/dashboard` rather than showing the form.
**Rationale:** Avoids confusing double-session scenarios; matches standard SaaS behavior.

---

### TRIP-01 — Seat Hold Duration
**Decision:** Configurable via `SEAT_HOLD_MINUTES` env var (default 10 minutes).
**Rationale:** A fixed value in code is hard to tune for different trip types. The env var allows ops to adjust without a deploy.

### TRIP-02 — Available Seats Decrement Timing
**Decision:** `available_seats` is decremented when a HOLD is created (not when CONFIRMED).
**Rationale:** Prevents overselling during the hold window. The `medvoyage:reconcile-seats` command re-syncs every 5 minutes to correct any drift from crashes or orphaned holds.

### TRIP-03 — Waitlist Offer Expiry
**Decision:** Configurable via `WAITLIST_OFFER_MINUTES` env var (default 10 minutes).
**Rationale:** Same as seat hold — tuneable without code change. When an offer expires, the next person in the queue is automatically offered the seat.

### TRIP-04 — Trip Editing Restricted to DRAFT
**Decision:** Only DRAFT trips can be edited. PUBLISHED and later statuses are immutable.
**Rationale:** Prevents retroactive changes that would surprise members who already signed up (e.g., price, destination, dates).

### TRIP-05 — Lead Doctor Must Be APPROVED
**Decision:** `TripManage::publish()` throws a validation error if the lead doctor's credentialing status is not `APPROVED`.
**Rationale:** Ensures no trip goes live with an unverified doctor leading it.

---

### CRED-01 — Required Document Types
**Decision:** `LICENSE` and `BOARD_CERTIFICATION` are the minimum required documents before a credentialing case can be submitted.
**Rationale:** These are the legally relevant documents for medical volunteer work. Additional document types (e.g., `CV`, `MALPRACTICE`) are accepted but not required.

### CRED-02 — One Active Case Per Doctor
**Decision:** A doctor cannot submit a new case while one is `SUBMITTED`, `IN_REVIEW`, `MATERIALS_REQUESTED`, or `RE_REVIEW`.
**Rationale:** Prevents reviewers from being flooded with duplicate submissions; forces the doctor to wait for resolution.

### CRED-03 — Document Storage Location
**Decision:** Documents stored in `storage/app/private/doctor-documents/`, not in `public/`.
**Rationale:** Documents contain sensitive medical credentials and must not be publicly accessible. All downloads go through `DocumentService::stream()` with an auth check.

### CRED-04 — License Expiry Warning Window
**Decision:** Doctors are flagged when their license expires within 30 days (daily check).
**Rationale:** Gives doctors time to renew before expiry; the flag does not automatically restrict them from trips (that is a manual admin decision).

---

### FIN-01 — Monetary Representation
**Decision:** All monetary values stored as integer cents (e.g., `$99.95` = `9995`).
**Rationale:** Avoids floating-point rounding errors. All arithmetic happens on integers; display formatting is done at the view layer.

### FIN-02 — Settlement Variance Tolerance
**Decision:** Variance ≤ 1 cent is treated as RECONCILED automatically; any larger variance creates an EXCEPTION.
**Rationale:** Floating-point and rounding edge cases in real-world payment processing mean a 1-cent tolerance is standard practice.

### FIN-03 — Confirmation Event Idempotency
**Decision:** `confirmation_event_id` is a unique column on `payments`; reusing it returns a 409 conflict.
**Rationale:** Prevents double-confirming a payment if a request is retried after network failure.

### FIN-04 — Payment-to-Membership Cascade
**Decision:** Confirming a payment automatically transitions any linked `membership_order` to `PAID`.
**Rationale:** Finance staff should not have to manually mark each order; confirming the payment is the single source of truth.

### FIN-05 — Top-Up Window
**Decision:** Top-up is allowed only within 30 days of the original membership purchase (`top_up_eligible_until`).
**Rationale:** Prevents members from "upgrading" a membership that is about to expire for minimal cost.

---

### SRCH-01 — Search Terms Population
**Decision:** `search_terms` is populated automatically by a `TripObserver` when a trip is created or updated.
**Rationale:** Ensures type-ahead suggestions reflect actual trip data without a separate indexing job.

### SRCH-02 — No External Search Engine
**Decision:** Search uses PostgreSQL `ILIKE` (case-insensitive substring match) rather than Elasticsearch or similar.
**Rationale:** Avoids operational complexity for this scale. Can be upgraded to full-text search (`tsvector`) or an external engine if query latency becomes an issue.

### SRCH-03 — Search History Retention
**Decision:** `user_search_histories` rows are not pruned automatically (no TTL command).
**Rationale:** Kept simple for now. A future `medvoyage:prune-search-history` command could remove records older than N days.

---

### SEC-01 — PII Encryption Key Rotation
**Decision:** PII is encrypted with Laravel's `APP_KEY` via `EncryptionService`. Key rotation is a manual ops procedure.
**Rationale:** Automated key rotation would require re-encrypting all rows; this is an operational concern left out of scope for v1.

### SEC-02 — Audit Log Hash Chain
**Decision:** Each `audit_log` row stores a SHA-256 hash of the previous row's hash (`previous_hash`). A broken chain detects tampering.
**Rationale:** Provides a lightweight tamper-evident audit trail without requiring an external append-only store.

### SEC-03 — No Soft Deletes
**Decision:** Hard deletes are used throughout; deleted data is not recoverable via the application.
**Rationale:** Simplifies queries (no `deleted_at IS NULL` everywhere). The audit log provides a record of what existed before deletion.

---

### OPS-01 — Docker-Only Prerequisites
**Decision:** The application requires only Docker and Docker Compose to run locally.
**Rationale:** Eliminates "works on my machine" issues; no PHP, Node.js, or PostgreSQL installation required on the host.

### OPS-02 — No Redis Dependency
**Decision:** Queue and cache drivers are both set to `database`.
**Rationale:** Reduces infrastructure dependencies for a medical volunteer platform that does not need sub-millisecond cache performance. Redis can be added later by changing `QUEUE_CONNECTION` and `CACHE_STORE` env vars.

---

## Open Questions

### Q-01 — Payment Gateway Integration
**Status:** Open
**Question:** The current system records payments manually (finance staff enters tender type and reference number). Should a real payment gateway (Stripe, Braintree) be integrated?
**Impact:** Would affect `PaymentService`, `TripSignup` confirmation flow, and the `Membership\PurchaseFlow` component. The idempotency and settlement infrastructure is already gateway-agnostic.

### Q-02 — Email Notifications
**Status:** Open
**Question:** No email is sent today for any event (hold confirmation, waitlist offer, credentialing result, etc.). Should a notification system be added?
**Impact:** Would require configuring a mail driver (SMTP/SES) and adding `Notification` classes. Livewire UI already shows status; email would be additive.

### Q-03 — Trip Cancellation Refund Policy
**Status:** Open
**Question:** When a trip is cancelled by admin, existing CONFIRMED signups are not automatically refunded. What is the intended refund policy?
**Impact:** Would need a `TripService::cancel()` side-effect to create `Refund` records for each linked payment.

### Q-04 — Multi-Currency Support
**Status:** Open
**Question:** All prices are in a single currency (cents, implicitly USD). Should the system support multiple currencies?
**Impact:** Would require a `currency` column on `payments`, `membership_orders`, and `trips`, plus an FX conversion layer.

### Q-05 — Doctor License Expiry Enforcement
**Status:** Open
**Question:** The daily `check-license-expiry` command flags doctors but does not restrict them from leading trips. Should an expired license automatically unpublish trips?
**Impact:** Would require a new transition in the Trip state machine and a notification to admin.

### Q-06 — Concurrent Review Assignment
**Status:** Open
**Question:** Two reviewers can both call `assignReviewer()` on the same case simultaneously. The last write wins (no lock). Should `CredentialingCase` use optimistic locking for assignment?
**Impact:** Minor — credentialing cases already have a `version` column and use `HasOptimisticLocking`. Calling `saveWithLock()` in `assignReviewer()` would be a one-line fix.

### Q-07 — Search History Privacy
**Status:** Open
**Question:** Users can clear their own search history via `clearHistory()`. Should admins be able to view or export individual users' search histories?
**Impact:** Would be an admin policy and UI concern; the data is already stored in `user_search_histories`.
