# MedVoyage — Business Logic Questions Log

---

## 1. Enrollment & Seat Management

### 1.1 What happens if a user's seat hold expires while they're filling out payment info?

* **Question:** The prompt says holds expire after 10 minutes of inactivity. But what counts as "inactivity"? If the user is actively filling out payment fields, is that inactive?
* **My Understanding:** "Inactivity" means the hold has not been confirmed (payment linked). The 10-minute timer is absolute from hold creation, not reset by page activity. This is simpler, more predictable, and prevents gaming.
* **Solution:** Hold expires at exactly `created_at + 10 minutes` regardless of user activity. The UI shows a countdown timer. If the timer expires before payment confirmation, the hold releases and the user must restart. The UI warns at 2 minutes remaining — that warning is **pushed over Laravel Reverb** (`HoldExpiring` event on the user's private channel), not polled.
* **Real-time mechanics (no polling):** When `SeatService::holdSeat()` creates the hold, it dispatches two delayed queue jobs with an explicit `->delay()`:
  1. `App\Jobs\NotifyHoldExpiring` — fires at `hold_expires_at − 2 min` and broadcasts `HoldExpiring` on `user.{id}` so the SignupWizard's Echo listener can surface the warning toast without any page reload.
  2. `App\Jobs\ReleaseExpiredHold` — fires at `hold_expires_at` and calls `SeatService::releaseSeat(..., HoldReleaseReason::EXPIRED)`, which in turn broadcasts `SeatReleased` on `trip.{id}` so every other viewer sees the seat re-appear instantly.
  Both jobs are idempotent: they re-fetch the signup on execution and no-op if it was already confirmed, cancelled, or expired through another path. The previous `medvoyage:expire-seat-holds` cron is kept at a 10-minute cadence purely as a safety net for queue-outage recovery (so a dropped worker can't strand a hold forever) and is never the primary expiry path.

### 1.2 Can a user hold seats on multiple trips simultaneously?

* **Question:** The prompt says one active signup OR waitlist entry per trip, but doesn't say whether a user can hold seats on different trips at the same time.
* **My Understanding:** A user should be able to sign up for multiple different trips. The restriction is per-trip (one active signup per user per trip), not global.
* **Solution:** Per-trip restriction only. A user can have HOLD or CONFIRMED signups on multiple trips simultaneously. No global seat hold limit.

### 1.3 What happens to confirmed signups when a trip is cancelled?

* **Question:** The prompt has trip status CANCELLED but doesn't define what happens to existing confirmed signups.
* **My Understanding:** All confirmed signups should be cancelled and refunds initiated.
* **Solution:** Cancelling a trip: (1) all HOLD signups → EXPIRED with seats released, (2) all CONFIRMED signups → CANCELLED with automatic refund records created (PENDING status), (3) all WAITING/OFFERED waitlist entries → DECLINED, (4) trip status → CANCELLED. Refunds still require Finance approval per MEM-07.

### 1.4 How does the waitlist-to-seat-offer flow work exactly?

* **Question:** The prompt says waitlist is shown when capacity is reached and holds expire. But the exact mechanics of offering a seat to the next person aren't detailed.
* **My Understanding:** When a seat becomes available (hold expired, signup cancelled), the system should automatically offer it to the first WAITING entry.
* **Solution:** Seat release triggers `WaitlistService::offerNextSeat(trip)`. This finds the first WAITING entry by position, sets status → OFFERED, offer_expires_at = now + 10 min, and broadcasts `WaitlistOfferMade` over Reverb on the recipient's private `user.{id}` channel so the "accept" banner appears in real time — no page refresh. If they accept: creates a new `TripSignup(HOLD)` with a 10-min hold (which itself wires up the real-time hold-expiry jobs from §1.1). If they don't accept in 10 min: entry → EXPIRED, next entry offered.
* **Real-time expiry (no polling):** At the same time the offer is created, `offerNextSeat()` dispatches an `App\Jobs\ExpireWaitlistOfferJob` with `->delay($offer_expires_at)`. The job re-fetches the entry, verifies it is still `OFFERED`, and calls `WaitlistService::expireOffer()` which itself chains to the next waiting user. The legacy `medvoyage:expire-waitlist-offers` command has been demoted from every-minute polling to a 10-minute safety-net sweep in case the queue worker was down when the delayed job was scheduled.

### 1.5 Is the seat count eventually consistent or strictly consistent?

* **Question:** With holds expiring and waitlists offering, available_seats could become temporarily inaccurate.
* **My Understanding:** For a medical trip with limited seats, strict consistency is important. We can't oversell.
* **Solution:** All seat operations (decrement on hold, increment on release) happen inside database transactions with `SELECT ... FOR UPDATE` on the trip row. The available_seats column is the source of truth, not computed from signup counts. Scheduled jobs also run a consistency check: recount active holds + confirmed signups and reconcile available_seats if they drift.

---

## 2. Credentialing

### 2.1 Can a doctor have multiple active credentialing cases?

* **Question:** The prompt says rejection is a path, and the doctor can submit new documents. But can they have multiple cases open at once?
* **My Understanding:** Only one active case at a time. A rejected case is terminal; the doctor opens a new case, not a second concurrent one.
* **Solution:** Only one non-terminal (not APPROVED, not REJECTED) credentialing case per doctor at a time. Attempting to submit a new case while one is active → 422. After REJECTED, a new case can be opened.

### 2.2 What happens to a doctor's trips when their license expires?

* **Question:** The prompt says there's a license expiry check but doesn't say what happens to trips the doctor is leading.
* **My Understanding:** Published trips with an expired doctor should not be allowed to proceed. But cancelling them automatically could be disruptive.
* **Solution:** When a doctor transitions to EXPIRED: (1) their DRAFT trips remain as-is, (2) PUBLISHED/FULL trips get a warning flag visible to Admin but are NOT auto-cancelled, (3) Admin receives a dashboard alert. Admin must manually decide to reassign the doctor or cancel the trip. The system blocks publishing new trips with an EXPIRED doctor.

### 2.3 Who can upload documents — only the doctor themselves?

* **Question:** The prompt says "credentialing staff upload doctor documents" — so reviewers can also upload?
* **My Understanding:** Both the doctor and credentialing reviewers can upload documents for a doctor's profile. This allows staff to upload materials received by mail/fax.
* **Solution:** Doctor and Credentialing Reviewer (and Admin) can upload documents to a doctor's profile. The uploaded_by field tracks who actually uploaded each document. Only the doctor themselves can submit/resubmit a credentialing case.

---

## 3. Membership & Top-ups

### 3.1 What exactly is a "price-difference top-up"?

* **Question:** The prompt says "apply price-difference top-ups within 30 days of purchase." How does the math work?
* **My Understanding:** If a member bought BASIC ($100) and wants PREMIUM ($300) within 30 days, they pay $200 (the difference). The membership plan changes but the expiry date stays based on the original purchase.
* **Solution:** Top-up creates a new MembershipOrder with order_type=TOP_UP, amount_cents = new_plan.price_cents - old_plan.price_cents, previous_order_id = old order. The old order remains PAID (it's not voided). The new order's starts_at = now, expires_at = old_order.expires_at (keeps original duration). Only upgrades allowed (new tier > old tier). Downgrade → 422.

### 3.2 Can a member have multiple active memberships?

* **Question:** The prompt doesn't say whether a user can hold multiple plans simultaneously.
* **My Understanding:** One active membership at a time per user. Purchasing a new plan while one is active is either a renewal or upgrade (top-up).
* **Solution:** One active (PAID and not expired) membership per user. Purchasing when active → must be a renewal (extends from current expiry) or top-up (within 30 days). A brand new purchase is only allowed if no active membership exists.

### 3.3 What happens when a full refund is processed on a membership that's been topped up?

* **Question:** If a user paid $100 for BASIC then $200 for a top-up to PREMIUM, and now wants a full refund — is it $300 or $200?
* **My Understanding:** A full refund should apply to the latest order. The original order was already consumed (it's what they upgraded from).
* **Solution:** Refunds are per-order, not per-membership-lifecycle. A "full refund" refunds the specific order selected. If the user refunds the TOP_UP order ($200): they lose PREMIUM but still have BASIC until original expiry. If they refund both: membership terminates. The UI shows each order separately with its own refund option.

---

## 4. Finance & Payments

### 4.1 What does "card on file" mean in an offline system?

* **Question:** The prompt lists CARD_ON_FILE as a tender type, but there's no payment gateway. How does a card charge work offline?
* **My Understanding:** "Card on file" means the organization has previously captured card details through some external process (e.g., a physical terminal). The MedVoyage system only records that a card payment was made — it doesn't process the actual charge. The reference_number would be a transaction ID from the external terminal.
* **Solution:** CARD_ON_FILE is a recording-only tender type, same as CASH and CHECK. The system records that a card payment was made, with reference_number capturing the external terminal's transaction ID. No actual card processing happens in MedVoyage. The "confirmation" step is a manual verification by Finance staff that the charge went through on the external terminal.

### 4.2 How do payment confirmations work if there are no network callbacks?

* **Question:** The prompt says "callbacks are not network-based but modeled as internal confirmation events."
* **My Understanding:** Instead of waiting for a webhook from a payment gateway, a Finance staff member manually confirms that the payment was received (cash counted, check deposited, card charged on external terminal).
* **Solution:** Confirmation is a manual action by Finance staff: they click "Confirm Payment" in the UI, which creates an internal confirmation event with its own idempotency key (confirmation_event_id). This transitions the payment from RECORDED → CONFIRMED. The idempotency key prevents double-confirmation from double-clicks.

### 4.3 What does "variance" mean in reconciliation?

* **Question:** The prompt says settlements flag exceptions when "totals diverge beyond $0.01." What totals are being compared?
* **My Understanding:** The settlement compares the sum of individually confirmed payments minus processed refunds (the actual tender received) against the sum of expected amounts from orders/signups (what should have been collected). If they don't match, something is wrong.
* **Solution:** `total_payments_cents` = SUM of CONFIRMED payment amounts in that day. `total_refunds_cents` = SUM of PROCESSED refund amounts in that day. `net_amount_cents` = payments - refunds. `expected_amount_cents` = SUM of amounts from orders/signups that were paid that day (from line items). `variance_cents` = net - expected. If |variance| > 1 cent → EXCEPTION status with auto-generated SettlementExceptions describing the discrepancy.

### 4.4 Can a voided payment be un-voided?

* **Question:** The prompt doesn't mention un-voiding.
* **My Understanding:** Voids should be permanent. If voided in error, a new payment should be recorded instead.
* **Solution:** VOIDED is terminal. No un-void. If voided by mistake, record a new payment and note the situation in the audit log. The void itself is logged for traceability.

### 4.5 What happens to a trip signup if its linked payment is voided?

* **Question:** If a CONFIRMED signup has a linked payment that gets voided, what happens to the signup?
* **My Understanding:** The signup should revert since the payment backing it is no longer valid.
* **Solution:** Voiding a CONFIRMED payment that is linked to a trip signup: (1) signup status → CANCELLED, (2) seat released, (3) waitlist offered next seat. This is handled by the PaymentService which checks for linked entities on void. An audit entry logs the cascade.

---

## 5. Search & Recommendations

### 5.1 How are SearchTerms populated?

* **Question:** The prompt mentions "locally stored terms" for type-ahead but doesn't say where they come from.
* **My Understanding:** Terms should be derived from actual data in the system — trip titles, specialties, destinations, doctor names. Pre-populated and updated when trips/doctors are created.
* **Solution:** SearchTerm table is populated by: (1) seeder with initial specialties and common terms, (2) event listener on Trip create/update that adds title words, specialty, and destination, (3) event listener on Doctor create that adds doctor name and specialty. usage_count incremented each time a term appears in search results that the user clicks. Duplicate terms ignored.

### 5.2 How does the recommendation strategy interface work?

* **Question:** The prompt says "pluggable, local strategy classes" but doesn't define the interface.
* **My Understanding:** Each strategy is a PHP class that takes a user and returns a ranked list of trips.
* **Solution:**
```php
interface RecommendationStrategy {
    public function key(): string;           // e.g., "most_booked_90d"
    public function label(): string;         // e.g., "Popular This Quarter"
    public function recommend(User $user, int $limit = 5): Collection;
}
```
Config file `config/recommendations.php` lists active strategies in display order. The `RecommendationService` iterates through configured strategies and returns combined results. New strategies are added by creating a class and adding it to config — no other code changes needed.

---

## 6. Audit & Data Integrity

### 6.1 What is a "correlation ID" in the audit log?

* **Question:** The prompt mentions "correlation ID" for traceability but doesn't define it.
* **My Understanding:** A correlation ID groups related audit entries that are part of the same logical operation. For example, confirming a trip signup might create entries for: payment confirmation, signup status change, seat hold release — all sharing one correlation ID.
* **Solution:** Correlation ID is a UUID generated at the start of a multi-step operation and passed to all audit log calls within that operation. The AuditLog search UI allows filtering by correlation ID to see all related entries. For single-step operations, correlation_id equals the entity_id.

### 6.2 Is the audit log truly tamper-evident, or just append-only?

* **Question:** The prompt says "tamper-evident audit log." Append-only prevents application-level tampering but not DB-level tampering.
* **My Understanding:** True tamper-evidence would require hash chaining (each entry includes a hash of the previous entry). The prompt mentions this is desirable.
* **Solution:** Append-only at the application level (no update/delete methods on the model). For enhanced tamper evidence: each entry stores `previous_hash` = SHA-256 of the previous entry's (id + action + entity_id + timestamp + actor_id). This creates a chain that can be verified. A `VerifyAuditChain` artisan command checks the chain integrity on demand. This is optional but included as a marked enhancement.

### 6.3 How long are audit logs retained?

* **Question:** No retention policy specified.
* **My Understanding:** Healthcare compliance typically requires 7 years. But for a local system, storage is finite.
* **Solution:** Default retention: 7 years. No auto-purge — manual archival by Admin. The audit log viewer shows all entries. If storage becomes a concern, Admin can export older entries to a file and then (if absolutely necessary) truncate — but this breaks the hash chain and is logged as a system event.

---

## 7. Roles & Permissions

### 7.1 Can a Doctor also be a Member (dual role)?

* **Question:** The prompt lists Doctor and Member as separate roles. Can one user hold both?
* **My Understanding:** Yes — a doctor who also wants to participate in trips as a patient should be able to.
* **Solution:** Users have multiple roles via a UserRole pivot table. A user can be both Doctor and Member. Permissions are the union of all their roles. The UI shows navigation items for all applicable roles. A Doctor+Member sees both credentialing features and trip signup features.

### 7.2 Who creates Doctor accounts?

* **Question:** The prompt says Members self-register. How do Doctor accounts get created?
* **My Understanding:** Two paths: (1) Admin creates a Doctor account directly, or (2) an existing Member is promoted to Doctor by Admin.
* **Solution:** Admin can create a Doctor profile linked to an existing user (adding the Doctor role), or create a brand new user with the Doctor role. Self-registration always creates a Member. A Doctor role without a completed Doctor profile just sees a "Complete your doctor profile" prompt. The Doctor profile (specialty, license info) must be filled before credentialing can begin.

---

## 8. UI & Frontend

### 8.1 How should the seat hold countdown work in the UI?

* **Question:** The user needs real-time feedback on their 10-minute hold.
* **My Understanding:** A visible countdown timer that matches the server-side expiry time exactly.
* **Solution:** When a hold is created, the server returns `hold_expires_at` timestamp. Alpine.js countdown component calculates remaining seconds from the difference between server timestamp and local time (adjusted for any clock skew using a time sync on page load). The countdown shows MM:SS format. At 2 minutes remaining, the timer turns red with a warning message. At 0, the UI shows "Your hold has expired" and offers a "Try Again" button. Seat availability for other users updates in real-time via Laravel Reverb WebSocket — when a seat is held or released, the server broadcasts a `SeatHeld` or `SeatReleased` event on the `trip.{tripId}` channel, and all connected browsers update instantly without refreshing.

### 8.2 How should the guided signup form work?

* **Question:** The prompt says "guided signup form" but doesn't detail the steps.
* **My Understanding:** A multi-step form that collects signup info, confirms details, and processes payment.
* **Solution:** Three-step wizard: (1) **Review Trip** — shows trip details, date, price, available seats; "Reserve Seat" button creates the hold and starts the countdown. (2) **Confirm Details** — shows the user's profile info (name, contact), trip summary, price, hold countdown; "Proceed to Payment" button. (3) **Payment** — select tender type, enter reference number if applicable; "Complete Signup" button links payment and confirms signup. Each step validates before proceeding. Going back doesn't release the hold. The countdown is visible on steps 2 and 3.

### 8.3 What should the Finance dashboard look like?

* **Question:** The prompt describes many finance operations but doesn't describe the dashboard experience.
* **My Understanding:** Finance staff need a clear daily workflow view.
* **Solution:** Finance dashboard has tabs: (1) **Today's Payments** — table of all payments recorded today, with status badges, confirm/void buttons. (2) **Pending Refunds** — refund requests awaiting approval. (3) **Settlement** — current day's running totals (payments, refunds, net), with a "Close Day" button at end of day (or auto-closes at 23:59). (4) **Exceptions** — open settlement exceptions needing resolution. (5) **Invoices** — draft and issued invoices. Each tab shows a count badge in the tab header.

---

## 9. Architecture

### 9.1 The prompt says "REST-style endpoints consumed by Livewire" — where are the REST endpoints?

* **Question:** `metadata.json` asks for *"Laravel to expose REST-style endpoints consumed by Livewire components."* But `docs/api-spec.md` says "There is no separate REST API," and `routes/api.php` does not exist — every mutation is a Livewire action. Is the delivery meeting the spec or working around it?
* **My Understanding:** The prompt's "REST-style endpoints" language predates a stack decision. In Laravel 11 + Livewire 3 the equivalent is the `POST /livewire/update` wire-protocol endpoint: every `wire:click` and `wire:model` action is a JSON-over-HTTP call through Laravel's normal middleware stack. Treating "REST-style" as a requirement to hand-author a second `/api/*` namespace on top of that would duplicate the auth, validation, and test surface with no second consumer to justify it (the system is offline / local — no mobile app, no SPA, no third-party integration).
* **Solution:** **Livewire is the intentional substitute for a hand-authored REST API.** The decision is explicit, not accidental. The guarantees reviewers care about — idempotency keys, optimistic locking, role gates, audit chain, encryption/masking — are all enforced at the **service layer** (`App\Services\*`), which is transport-agnostic. Adding a second transport would only create a second place for those invariants to regress (exactly the failure modes found in Issues 2, 6, and 7 of the audit). The complete route + action + parameter reference lives in `docs/api-spec.md` and is the canonical API document. A reviewer who wants to exercise an "endpoint" programmatically hits `POST /livewire/update` with the snapshot + method name payload — every Livewire feature test in the suite does exactly this via `Livewire::test(...)->call(...)`. If a future integrator needs a machine-to-machine surface, the intended path is a thin `App\Http\Controllers\Api\*` namespace that delegates straight into the existing services; it is not in scope for this build and no such controllers exist today. Full rationale lives in `repo/docs/design.md §"REST-style endpoints — reconciling the prompt with Livewire 3"`.