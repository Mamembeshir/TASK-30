# MedVoyage

**Provider & Trip Enrollment System** — A fully offline-capable clinician credentialing, group medical trip enrollment, membership management, and billing reconciliation platform.

---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) + [Docker Compose](https://docs.docker.com/compose/install/)

That's it. No PHP, Node, Composer, or npm needed on your host machine.

---

## Quick Start

```bash
git clone <repo-url> medvoyage
cd medvoyage
docker compose up
```

Visit **http://localhost:8000** once startup completes (~60 s on first boot while assets build and migrations run).

---

## Demo Accounts

Run the demo seeder once (idempotent — safe to re-run):

```bash
make shell
php artisan db:seed-demo
```

| Username  | Password      | Role(s)                          |
|-----------|---------------|----------------------------------|
| `admin`   | `Admin123!@`  | Administrator                    |
| `drsmith` | `Doctor123!@` | Doctor + Member (APPROVED)       |
| `drjones` | `Doctor123!@` | Doctor (Under Review)            |
| `member1` | `Member123!@` | Member (Standard membership)     |
| `member2` | `Member123!@` | Member                           |
| `reviewer`| `Review123!@` | Credentialing Reviewer           |
| `finance` | `Finance123!@`| Finance Specialist               |

---

## Running Tests

All tests run **inside the Docker container** — no local PHP required.

```bash
# All tests (unit + feature)
./run_tests.sh
make test

# Individual suites
./run_tests.sh --unit      # Unit tests only
./run_tests.sh --feature   # Feature tests only
./run_tests.sh --coverage  # With coverage report (≥80% target)

# Via Make
make test-unit
make test-feature
make coverage
```

---

## Project Structure

```
app/
  Enums/          PHP 8.1 backed enums for every domain entity
  Events/         Broadcast events (SeatHeld, TripStatusChanged, …)
  Exceptions/     Custom exceptions (InvalidStatusTransition, StaleRecord)
  Http/Middleware/ AccountStatus, Idempotency, Admin
  Livewire/       Livewire 3 components grouped by module
  Models/         Eloquent models (one per file, UUID PKs)
  Services/       Business logic (TripService, SeatService, AuditService, …)
  Traits/         HasOptimisticLocking
config/
  medvoyage.php   App-specific config (seat hold minutes, waitlist offer minutes)
database/
  migrations/     Database schema
  factories/      Model factories for testing
  seeders/        Demo data seeders
public/
  fonts/          Self-hosted DM Sans, IBM Plex Sans, IBM Plex Mono (zero external requests)
resources/
  css/app.css     Tailwind + CSS design system variables
  js/app.js       Alpine.js + Laravel Echo (Reverb) entry point
  views/
    layouts/      app.blade.php (sidebar), guest.blade.php
    components/   Reusable Blade components
    livewire/     Livewire view templates
routes/
  web.php         All application routes
  channels.php    Reverb broadcast channel authorisation
  console.php     Scheduled commands
tests/
  Unit/           Isolated logic tests (services, enums, traits)
  Feature/        HTTP + Livewire integration tests
```

---

## Useful Commands

```bash
make shell       # Open a bash shell inside the app container
make tinker      # Open Laravel Tinker REPL
make migrate     # Run pending migrations
make fresh       # Drop and re-migrate + re-seed (destroys data)
make down        # Stop all containers
make test        # Run full test suite (unit + feature)
make test-unit   # Unit tests only
make test-feature# Feature tests only
make coverage    # With HTML coverage report
```
