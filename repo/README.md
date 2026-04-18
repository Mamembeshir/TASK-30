# MedVoyage

**Project type:** fullstack

A fullstack clinician credentialing, group medical trip enrollment, membership management, and billing reconciliation platform. MedVoyage handles the complete provider journey — from initial credentialing through trip booking, seat management, waitlist processing, and finance settlement — in a single, self-contained application.

## Architecture & Tech Stack

* **Frontend:** Livewire 3, Alpine.js, TailwindCSS (compiled via Vite)
* **Backend:** PHP 8.2, Laravel 11, Laravel Reverb (WebSocket), scheduled queue workers
* **Database:** PostgreSQL 16
* **Containerization:** Docker & Docker Compose (required — no local PHP/Node needed)

## Project Structure

```text
.
├── app/
│   ├── Enums/                  # PHP backed enums for every domain entity
│   ├── Events/                 # Broadcast events (SeatHeld, TripStatusChanged, …)
│   ├── Exceptions/             # Custom exceptions (InvalidStatusTransition, StaleRecord)
│   ├── Http/
│   │   ├── Controllers/Api/    # REST API controllers (one per domain)
│   │   └── Middleware/         # AccountStatus, Credentialing, Finance, Admin, VerifyApiCsrfToken
│   ├── Livewire/               # Livewire 3 components grouped by module
│   ├── Models/                 # Eloquent models (UUID PKs throughout)
│   ├── Services/               # Business logic (TripService, SeatService, AuditService, …)
│   └── Traits/                 # HasOptimisticLocking
├── config/
│   └── medvoyage.php           # App-specific config (seat hold minutes, waitlist offer TTL)
├── database/
│   ├── factories/              # Model factories for testing
│   ├── migrations/             # Database schema
│   └── seeders/                # Demo data seeders
├── resources/
│   ├── css/app.css             # Tailwind + design system CSS variables
│   ├── js/app.js               # Alpine.js + Laravel Echo (Reverb) entry point
│   └── views/                  # Blade layouts, components, and Livewire templates
├── routes/
│   ├── api.php                 # REST API routes (/api/*)
│   ├── web.php                 # Web/Livewire routes
│   └── channels.php            # Reverb broadcast channel authorisation
├── tests/
│   ├── Feature/Api/            # HTTP API integration tests (Pest)
│   ├── Feature/Auth/           # Authentication flow tests
│   ├── Feature/Credentialing/  # Credentialing service and Livewire tests
│   ├── Feature/Trips/          # Trip and signup workflow tests
│   ├── Feature/Workflows/      # Cross-domain workflow tests
│   ├── Unit/                   # Isolated service, enum, and trait tests
│   └── e2e/                    # Playwright browser tests
│       ├── playwright.config.js
│       └── specs/              # auth, booking, review-admin specs
├── .env.example                # Example environment variables
├── docker-compose.yml          # Multi-container orchestration
├── Dockerfile                  # App image (PHP + Node for asset builds)
├── entrypoint.sh               # Container startup (migrations, seeds, queue worker)
├── run_tests.sh                # Standardized test execution script
└── Makefile                    # Convenience aliases for common commands
```

## Prerequisites

This project runs entirely inside containers. You only need:

* [Docker](https://docs.docker.com/get-docker/)
* [Docker Compose](https://docs.docker.com/compose/install/)

No PHP, Node, Composer, or npm installation is required on the host machine.

## Running the Application

1. **Copy the example environment file:**
   ```bash
   cp .env.example .env
   ```
   The entrypoint handles key generation automatically, but copying `.env` first avoids any timing edge-cases on first boot.

2. **Build and start all containers:**
   ```bash
   docker-compose up --build
   ```
   (On Docker Compose v2 the equivalent command is `docker compose up --build`; both
   are supported — use whichever your Docker installation provides.)

   On first boot the container installs dependencies, builds frontend assets, runs
   migrations, and seeds demo data (~60 s). Subsequent starts are much faster.

3. **Access the app:**
   * Application: `http://localhost:8000`
   * WebSocket (Reverb): `ws://localhost:8080`

4. **Stop the application:**
   ```bash
   docker-compose down -v
   ```

## Verifying the Application

After `docker-compose up` completes the first-boot sequence, use the following
checks to confirm the system is running correctly. All three must pass.

### 1. Container health

```bash
docker compose ps
```

Expected: two services reported `(healthy)` — `medvoyage_app` and `medvoyage_db`.

### 2. HTTP smoke test

```bash
curl -sI http://localhost:8000/login
```

Expected: `HTTP/1.1 200 OK` with a `Set-Cookie: XSRF-TOKEN=...` header in the
response.

```bash
curl -s http://localhost:8000/login | grep -o 'Sign in'
```

Expected output: `Sign in` (the login form has rendered).

### 3. UI smoke flow

1. Open `http://localhost:8000` in a browser — you should be redirected to
   `/login`.
2. Sign in with `member@medvoyage.test` / `Seed1234!@` (see
   [Seeded Credentials](#seeded-credentials) below).
3. Expected: the browser lands on `/dashboard` and the MedVoyage top bar is
   visible. Submitting the logout form (via the account menu) must return you
   to `/login`.

If any of the three checks fails, see [Troubleshooting](#troubleshooting)
below.

## Testing

All tests — unit, feature/API, and browser E2E — are executed through a single script. No local PHP or Node runtime is required.

```bash
chmod +x run_tests.sh
./run_tests.sh
```

Running with no flags executes **all three suites** (unit + feature + E2E) in order. The script exits `0` on success and non-zero on any failure, making it CI/CD compatible.

**Selective suites:**

```bash
./run_tests.sh --unit              # Unit tests only
./run_tests.sh --feature           # Feature/API integration tests only
./run_tests.sh --e2e               # Browser E2E tests only
./run_tests.sh --unit --feature    # PHP tests without E2E
./run_tests.sh --coverage          # Add coverage report to whichever PHP suites run
./run_tests.sh --all               # Explicit equivalent of no flags
```

**Via Make:**

```bash
make test           # Unit + Feature + E2E  (default)
make test-unit      # Unit tests only
make test-feature   # Feature tests only
make test-e2e       # E2E only
make coverage       # Unit + Feature with coverage report
```

**How the script works:**

* **PHP tests** run inside the `app` Docker container. The script detects whether it is already inside a container and adapts accordingly — no manual `docker exec` needed.
* **E2E tests** use the official public `mcr.microsoft.com/playwright:v1.59.1-noble` image (Node 20 + `@playwright/test` + all browsers pre-installed). Docker pulls it the first time and caches it thereafter. When E2E is included, the script starts the full application stack (`docker compose up -d --build`), waits up to 120 s for the app healthcheck to pass, checks that the seeded demo users exist (runs `php artisan db:seed --force` only if they're missing) and clears any AUTH-02 lockout counters from prior runs, then invokes Playwright via `docker compose --profile e2e run --rm playwright`.
* The three Playwright spec suites cover `auth`, `booking` (including a full hold-to-payment journey), and `review-admin` (admin pages and membership flows).

## Seeded Credentials

The database is pre-seeded automatically on first boot. The login form accepts both username and email address.

| Role | Email | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@medvoyage.test` | `Seed1234!@` | Full access to all modules: user management, trip administration, and review moderation. |
| **Credentialing Reviewer** | `reviewer@medvoyage.test` | `Seed1234!@` | Can assign, approve, reject, and request materials for credentialing cases. |
| **Finance Specialist** | `finance@medvoyage.test` | `Seed1234!@` | Can record payments, manage invoices, and close settlement periods. |
| **Doctor + Member** | `doctor@medvoyage.test` | `Seed1234!@` | Pre-credentialed doctor who can also enroll in trips as a member. |
| **Member** | `member@medvoyage.test` | `Seed1234!@` | Standard member — can search trips, hold seats, join waitlists, and manage membership. |

## Useful Commands

```bash
make shell          # Open a bash shell inside the running app container
make tinker         # Open Laravel Tinker REPL
make migrate        # Run pending migrations
make seed           # Re-run seeders (demo data)
make fresh          # Drop all tables, re-migrate, and re-seed (destroys data)
make down           # Stop and remove all containers
make test           # Run unit + feature + E2E tests
make test-unit      # Unit tests only
make test-feature   # Feature tests only
make test-e2e       # Browser E2E tests only
make coverage       # Unit + Feature with HTML coverage report
make docs-check     # Verify artisan command names and middleware list match source
```

## Troubleshooting

| Symptom | Likely cause | Fix |
| :--- | :--- | :--- |
| `medvoyage_db` reports `unhealthy` and logs `chown: /var/lib/postgresql/data/mysql.sock: No such file or directory` | Stale `db_data` volume left over from a previous project using MySQL | `docker-compose down -v` to wipe the volume, then `docker-compose up --build` |
| `medvoyage_app` never reaches `healthy` — `curl http://localhost:8000/login` returns empty/connection refused | First-boot steps still running (composer install, `npm run build`, migrations, seeders) | Watch `docker compose logs -f app`; the app is ready when you see `Starting development server: http://0.0.0.0:8000`. First boot can take ~60 s. |
| Port `8000` or `5432` already in use | Another process is bound to those ports on the host | Stop the conflicting service, or export `APP_PORT=8001` / `DB_PORT=5433` before `docker-compose up` |
| Playwright E2E tests fail with `net::ERR_CONNECTION_CLOSED` | Chromium auto-upgrades `http://app:*` to HTTPS because of an HSTS-preload rule on the `.app` TLD | Already worked around — the app container is reachable from the Playwright network under the alias `web`, and `BASE_URL` points at `http://web:8000` for that reason. Do not rename the alias. |
| E2E login tests fail with *"The provided credentials do not match our records."* | Demo users never got seeded, or the AUTH-02 lockout counters are non-zero from prior failed test attempts | `run_tests.sh --e2e` already checks for the seeded member and re-seeds if missing, and zeroes out the lockout counters. If you still see this, run `make fresh` to re-run migrations + seed. |
| API tests return `419 CSRF token mismatch` | Request sent without the `Origin` header matching `APP_URL` | Send JSON requests with `Origin: http://localhost:8000` (the custom `VerifyApiCsrfToken` middleware exempts same-origin JSON) or include an `X-CSRF-TOKEN` header |
| `docker compose exec app …` fails with `service "app" is not running` | The stack was started with `docker-compose down` (or the container crashed) | Re-run `docker-compose up -d` and wait for the `app` healthcheck to pass |
