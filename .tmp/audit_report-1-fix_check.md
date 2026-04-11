# Previous Issues Rereview Status (Round 2, Static-Only)

Date: 2026-04-11 (EAT)

## Summary
- Fixed: 5
- Partially Fixed: 0
- Not Fixed: 0

## Status by Previously Reported Issue

1) Blocker: `trip`/`signup` mismatch in signup wizard
- Status: **Fixed**
- Evidence:
  - Mismatch guard added: `app/Livewire/Trips/SignupWizard.php:63`
  - Payment amount tied to signup’s trip: `app/Livewire/Trips/SignupWizard.php:110`
  - Regression test present: `tests/Feature/Trips/TripManageTest.php:133`

2) High: Universal idempotency contract incomplete across write paths
- Status: **Fixed**
- Evidence:
  - Waitlist join now explicitly accepts idempotency key: `app/Services/WaitlistService.php:30`
  - Dedupe by key and natural-key fallback: `app/Services/WaitlistService.php:36`, `app/Services/WaitlistService.php:45`
  - Waitlist idempotency key persisted on model: `app/Models/TripWaitlistEntry.php:22`
  - Schema support added: `database/migrations/2026_04_11_000004_add_idempotency_key_to_trip_waitlist_entries.php:30`
  - Caller now passes stable waitlist key: `app/Livewire/Trips/TripDetail.php:63`, `app/Livewire/Trips/TripDetail.php:119`
  - Unit coverage added: `tests/Unit/Services/WaitlistServiceTest.php:37`, `tests/Unit/Services/WaitlistServiceTest.php:58`

3) Medium: Critical security fixes under-tested
- Status: **Fixed**
- Evidence:
  - Private channel auth tests: `tests/Feature/Auth/ChannelAuthTest.php:15`, `tests/Feature/Auth/ChannelAuthTest.php:26`
  - Session regeneration tests: `tests/Feature/Auth/LoginTest.php:159`, `tests/Feature/Auth/LoginTest.php:177`
  - Hidden trip direct-access tests: `tests/Feature/Trips/TripLivewireTest.php:67`, `tests/Feature/Trips/TripLivewireTest.php:76`, `tests/Feature/Trips/TripLivewireTest.php:85`
  - Cross-user refund denial test: `tests/Feature/Membership/MembershipLivewireTest.php:206`

4) Medium: API docs drift on profile behavior
- Status: **Fixed**
- Evidence:
  - Profile action/params/sensitivity notes documented: `docs/api-spec.md:68`, `docs/api-spec.md:69`, `docs/api-spec.md:72`

5) Low: Dead/unclear idempotency middleware integration
- Status: **Fixed**
- Evidence:
  - Middleware alias removed: `bootstrap/app.php:15`
  - Dead table removal migration: `database/migrations/2026_04_11_000002_drop_idempotency_records_table.php:33`

## Notes
- This is still a static-only check; runtime behavior remains Manual Verification Required.
- No regressions were found in the previously fixed blocker/security items during this pass.
