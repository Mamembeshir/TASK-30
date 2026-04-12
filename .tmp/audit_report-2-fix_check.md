# MedVoyage Findings Recheck Status (Round 5)
Date: 2026-04-12

## Final status (current static snapshot)
- Fixed: 8
- Partially fixed: 0
- Not fixed: 0

## Newly resolved since round 4
1. Payment confirm now accepts and propagates `idempotency_key` (with fallback to `confirmation_event_id`)
- Controller accepts + derives + passes key:
  - `app/Http/Controllers/Api/PaymentApiController.php:84-93`
  - `app/Http/Controllers/Api/PaymentApiController.php:96-100`
- Service now supports explicit idempotency key and records it:
  - `app/Services/PaymentService.php:74-78`
  - `app/Services/PaymentService.php:82-87`
  - `app/Services/PaymentService.php:129`

## Still confirmed fixed
- Audit body fallback for idempotency: `app/Services/AuditService.php:37-40`
- CSRF no-Origin JSON exemption removed: `app/Http/Middleware/VerifyApiCsrfToken.php:75-78`
- Settlement exception scoped + 404 JSON: `app/Http/Controllers/Api/SettlementApiController.php:72-82`
- Lead physician fallback rendering: `resources/views/livewire/trips/trip-detail.blade.php:36-38`
- Universal write-endpoint idempotency acceptance appears implemented across API controllers (including previously missing ones)
- Livewire mutations consume REST endpoints via HTTP client:
  - `app/Services/ApiClient.php:9-35`
  - `docs/api-spec.md:3-17`
