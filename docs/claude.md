# MedVoyage — Project Instructions (claude.md)

You are building **MedVoyage**, a Provider & Trip Enrollment system. These instructions apply to EVERY task in this project. Do not ask for confirmation on anything covered here — just follow it.

---

## Tech Stack (Non-Negotiable)

- **Backend:** Laravel 11+ (PHP 8.3+), Livewire 3
- **Database:** PostgreSQL 16+
- **Frontend:** Blade + Livewire + Alpine.js + Tailwind CSS 3.4+
- **Real-time:** Laravel Reverb (local WebSocket server) + Laravel Echo (JS client, bundled via Vite)
- **UI Components:** shadcn/ui port for Laravel (if available) OR hand-built components following the design system below
- **Auth:** Laravel built-in auth with local username/password (no Socialite, no external providers)
- **Queue:** Laravel Queue with database driver (local, no Redis required)
- **File Storage:** Local disk only (storage/app)
- **Testing:** Pest PHP (unit + feature + frontend component tests)
- **No internet dependency at runtime.** Everything runs offline.

### Real-Time Architecture (Laravel Reverb)
Seat availability, hold countdowns, and waitlist updates use **real-time WebSocket broadcasting** — not polling.

- **Laravel Reverb** is Laravel's first-party WebSocket server. It runs locally as a separate process inside the Docker container. No Pusher, no Ably, no external service.
- **Laravel Echo** is the JavaScript client that connects to Reverb. It's bundled into `app.js` via Vite at build time — no CDN.
- **Broadcasting driver:** set to `reverb` in `.env`. Reverb runs on a local port (default 8080) alongside the app.

**How it works:**
1. Backend dispatches a Laravel Event (e.g., `SeatAvailabilityChanged`) that implements `ShouldBroadcast`.
2. Reverb receives the event and pushes it to all connected WebSocket clients on the relevant channel.
3. Laravel Echo on the frontend listens to the channel and updates the UI instantly.

**Channels used:**
- `trip.{tripId}` — public presence channel. Broadcasts: seat count changes, hold created/released, trip status changes (PUBLISHED→FULL→PUBLISHED).
- `user.{userId}` — private channel. Broadcasts: waitlist offer notifications, hold expiry warnings, signup confirmations.
- `finance.dashboard` — private channel (Finance role). Broadcasts: new payment recorded, settlement closed.

**Events to broadcast:**
```php
// All implement ShouldBroadcast
SeatHeld::class          // → trip.{id}: available_seats updated
SeatReleased::class      // → trip.{id}: available_seats updated
SignupConfirmed::class   // → trip.{id}: booking_count updated
TripStatusChanged::class // → trip.{id}: status updated (e.g., FULL)
WaitlistOfferMade::class // → user.{id}: "A seat is available! Accept within 10 min"
HoldExpiring::class      // → user.{id}: "Your hold expires in 2 minutes"
PaymentRecorded::class   // → finance.dashboard: new payment in today's list
SettlementClosed::class  // → finance.dashboard: settlement status updated
```

**Frontend listening (in Livewire components):**
```javascript
// In Alpine.js or Livewire's getListeners()
Echo.channel('trip.' + tripId)
    .listen('SeatHeld', (e) => { this.availableSeats = e.availableSeats; })
    .listen('SeatReleased', (e) => { this.availableSeats = e.availableSeats; })
    .listen('TripStatusChanged', (e) => { this.tripStatus = e.status; });
```

**Livewire integration:** Use `$this->dispatch()` from Livewire components to sync server state after receiving a WebSocket event. Or use Livewire's built-in Echo integration: `#[On('echo:trip.{tripId},SeatHeld')]`.

**Reverb in Docker:** The entrypoint script starts Reverb as a background process: `php artisan reverb:start --host=0.0.0.0 --port=8080 &`. The docker-compose exposes port 8080 alongside port 8000 for the app.

**Offline verification:** Reverb runs locally — no internet needed. It's a PHP process, same as the Laravel app. WebSocket connections are between the browser and the local server only.

### Offline Asset Strategy (Critical)
Tailwind CSS and Alpine.js are **build-time tools**, not runtime dependencies. Here's how they work offline:

- **Tailwind CSS:** Compiled during `docker-compose build` (or entrypoint) via `npm run build`. This produces a single static `public/build/assets/app.css` file. At runtime, the browser loads this pre-compiled CSS file — no Node, no npm, no internet needed.
- **Alpine.js:** Bundled into `public/build/assets/app.js` via Vite during the same build step. Ships as a static JS file. No CDN.
- **Fonts:** Self-hosted in `public/fonts/`. Loaded via `@font-face` in CSS. No Google Fonts CDN calls.
- **Vite manifest:** Laravel's `@vite` directive reads `public/build/manifest.json` to resolve asset paths. This file is generated at build time and committed or rebuilt on container start.

**The build step runs ONCE inside Docker** (during `docker-compose build` or the entrypoint script). After that, the app serves only static files — no Node process running, no Vite dev server, no internet.

**npm and Node are NOT needed at runtime.** They exist in the Docker image only for the build step. A multi-stage Dockerfile can even exclude Node from the final image if desired.

**Verification:** After `docker-compose up`, disconnect the network. The app must still load with full styling, fonts, and Alpine.js interactivity. If anything breaks, an asset was not properly compiled or a CDN link leaked in.

---

## Design System — "Clinical Precision" Aesthetic

The UI must look like a premium healthcare operations platform — not a generic Bootstrap/Tailwind dashboard. Follow these rules on EVERY page and component:

### Typography
- **Display/Headings:** `"DM Sans"` (Google Fonts, self-hosted in /public/fonts/) — weight 500 for headings, 700 for emphasis
- **Body:** `"IBM Plex Sans"` — weight 400 for body, 500 for labels, 600 for buttons
- **Monospace (codes, IDs, amounts):** `"IBM Plex Mono"` — weight 400
- **Never** use Inter, Roboto, Arial, or system-ui as primary fonts
- Self-host all fonts. No CDN calls at runtime.

### Color Palette
```css
:root {
    /* Core */
    --surface-primary: #FAFBFC;
    --surface-secondary: #F1F4F8;
    --surface-elevated: #FFFFFF;
    --surface-sunken: #E8ECF1;
    
    /* Brand */
    --brand-primary: #1B6B93;      /* Deep teal — trust, medical authority */
    --brand-primary-hover: #155A7D;
    --brand-primary-light: #E8F4F8;
    --brand-secondary: #2D9596;    /* Lighter teal for accents */
    
    /* Semantic */
    --success: #0F766E;
    --success-light: #ECFDF5;
    --warning: #B45309;
    --warning-light: #FFFBEB;
    --danger: #B91C1C;
    --danger-light: #FEF2F2;
    --info: #1D4ED8;
    --info-light: #EFF6FF;
    
    /* Text */
    --text-primary: #111827;
    --text-secondary: #4B5563;
    --text-tertiary: #9CA3AF;
    --text-inverse: #FFFFFF;
    
    /* Borders & Dividers */
    --border-default: #E5E7EB;
    --border-strong: #D1D5DB;
    --border-focus: #1B6B93;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.05);
    --shadow-focus: 0 0 0 3px rgba(27,107,147,0.15);
}
```

### Component Standards

**Cards:** White background, 1px `--border-default`, `border-radius: 12px`, `--shadow-sm`. On hover (interactive cards): `--shadow-md` with 150ms transition. No colored borders unless indicating status.

**Buttons:**
- Primary: `--brand-primary` bg, white text, `border-radius: 8px`, `font-weight: 600`, `padding: 10px 20px`. Hover darkens 10%. Pressed scales to 0.98.
- Secondary: white bg, `--brand-primary` text, 1px `--brand-primary` border. Hover fills `--brand-primary-light`.
- Danger: `--danger` bg only for destructive actions (refund, void, reject). Always require confirmation dialog.
- Disabled: `opacity: 0.5`, `cursor: not-allowed`.
- All buttons have a subtle transition (150ms ease).

**Forms:**
- Labels above inputs, `font-weight: 500`, `--text-secondary`, `font-size: 0.875rem`.
- Inputs: `border-radius: 8px`, 1px `--border-default`, `padding: 10px 14px`. Focus: `--border-focus` + `--shadow-focus`.
- Error state: `--danger` border + error message below in `--danger` text, `font-size: 0.8rem`.
- Required fields marked with a small `*` in `--danger`, not a full word.
- Group related fields in sections with a light heading and subtle divider.

**Tables:**
- Header row: `--surface-secondary` bg, `font-weight: 600`, `text-transform: uppercase`, `font-size: 0.75rem`, `letter-spacing: 0.05em`, `--text-tertiary`.
- Rows: white bg, `border-bottom: 1px solid var(--border-default)`. Hover: `--surface-secondary`.
- Zebra striping: **NO** — use hover only.
- Sticky header on scroll.
- Status columns use pill badges (see below).

**Badges/Pills:**
- Small rounded pills for statuses: `border-radius: 9999px`, `padding: 2px 10px`, `font-size: 0.75rem`, `font-weight: 600`.
- Colors: success=`--success` text on `--success-light` bg; warning=`--warning` on `--warning-light`; danger=`--danger` on `--danger-light`; info=`--info` on `--info-light`; neutral=`--text-secondary` on `--surface-secondary`.
- Map statuses to colors consistently everywhere: Active/Approved/Completed → success; Pending/Under Review → warning; Rejected/Voided/Expired → danger; Draft/Hold → neutral.

**Navigation:**
- Left sidebar, `width: 260px`, `--surface-elevated` bg, `border-right: 1px solid var(--border-default)`.
- Nav items: `padding: 10px 16px`, `border-radius: 8px`, `font-weight: 500`. Active: `--brand-primary-light` bg + `--brand-primary` text. Hover: `--surface-secondary`.
- Collapse to icons on mobile (< 768px).
- Group nav items by section with small uppercase labels.

**Modals/Dialogs:**
- Backdrop: `rgba(0,0,0,0.4)` with `backdrop-filter: blur(4px)`.
- Modal: `--surface-elevated`, `border-radius: 16px`, `--shadow-lg`, `max-width: 560px`, centered.
- Destructive confirmations: danger-colored action button + clear description of consequences.

**Loading States:**
- Skeleton loaders matching the shape of content (not spinners).
- Livewire wire:loading shows a subtle top progress bar (2px, `--brand-primary`, animated left-to-right).

**Empty States:**
- Centered illustration area (simple SVG or icon), heading, description, and a CTA button.
- Never show a blank page or just "No data."

**Toast Notifications:**
- Top-right, `border-radius: 12px`, slide in from right with 300ms ease.
- Auto-dismiss after 5 seconds. Include dismiss X.
- Color-coded left border (4px) matching severity.

### Layout Rules
- Max content width: `1280px`, centered with auto margins.
- Page padding: `32px` on desktop, `16px` on mobile.
- Section spacing: `32px` between major sections, `16px` between related elements.
- Never stack more than 3 levels of nesting visually.

### Responsive
- Sidebar collapses below 768px to a hamburger menu.
- Tables become card-based views on mobile.
- Form layouts go single-column on mobile.

---

## Coding Conventions

### Laravel
- **One model per file.** Models go in `app/Models/`.
- **Form Requests** for all validation — never validate in controllers or Livewire components directly.
- **Service classes** in `app/Services/` for business logic. Controllers and Livewire components call services. Services call models. Never put query logic in controllers.
- **Enums** as PHP 8.1+ backed enums in `app/Enums/`.
- **State machines** implemented as methods on the model that validate transitions and throw `InvalidStateTransitionException`. Each transition records an audit log entry.
- **Audit logging:** use an `AuditLog` model. Every create/update/delete/approve/export calls `AuditLog::record(...)` with: actor_id, action, entity_type, entity_id, before (JSON), after (JSON), ip_address, idempotency_key, correlation_id, timestamp. AuditLog table is append-only — no updates, no deletes, no soft deletes.
- **Idempotency:** all POST/PUT endpoints accept `X-Idempotency-Key` header. Middleware checks `idempotency_records` table. If key exists and matches endpoint, return cached response. If new, process and cache. Keys expire after 24 hours.
- **Optimistic locking:** all key tables include a `version` column (integer, default 1). Every update checks `WHERE version = ?` and increments. If 0 rows affected, throw `StaleRecordException` (HTTP 409).
- **Encryption:** sensitive fields use `Crypt::encryptString()` / `decryptString()`. Store encrypted, display masked by role. Masking logic in a `MaskingService` — never in Blade templates directly.
- **Money:** use integer cents everywhere internally. Display formatting via a `formatCurrency()` helper. No floating point for money.
- **Dates/times:** store in UTC. Display in the configured facility timezone. Use Carbon exclusively.

### Livewire
- Components in `app/Livewire/`, grouped by module: `app/Livewire/Credentialing/`, `app/Livewire/Trips/`, etc.
- Use Livewire form objects for complex forms.
- Wire:model.live only for search/filter inputs. Everything else uses wire:model (lazy) or form submission.
- Emit events for cross-component communication. Document events in a comment at the top of each component.
- Loading states: always use `wire:loading` with skeleton or progress bar.

### Blade
- Layouts in `resources/views/layouts/`.
- Components in `resources/views/components/` — create reusable components for: button, badge, card, input, select, textarea, modal, table, empty-state, toast.
- Each page is a Livewire full-page component.
- No raw HTML forms — always Livewire form handling.

### Testing

Three test layers, all required:

**1. Unit Tests** (`tests/Unit/`)
- Test individual service methods, model methods, state machine transitions, enums, helpers in isolation.
- Mock dependencies. No database, no HTTP.
- File naming: `{Class}Test.php` (e.g., `SeatServiceTest.php`, `PaymentStatusTest.php`).
- Every state machine: test ALL valid transitions AND ALL invalid transitions.
- Every calculation: test with known inputs and expected outputs.
- Every helper: test edge cases (e.g., `formatCurrency(0)`, `formatCurrency(-500)`).

**2. API / Feature Tests** (`tests/Feature/`)
- Test full HTTP request → response cycles through Laravel's test client.
- Hit actual routes, middleware, and database. Uses test database (refreshed per test).
- File naming: `{Feature}Test.php` (e.g., `TripSignupTest.php`, `CredentialingWorkflowTest.php`).
- Every permission: test authorized access succeeds AND unauthorized returns 403.
- Every validation rule: test valid input passes AND invalid returns 422 with correct field errors.
- Every idempotency scenario: test duplicate key returns cached response without side effects.
- Every optimistic lock: test stale version returns 409.

**3. Frontend / Livewire Component Tests** (`tests/Frontend/`)
- Test Livewire components using Laravel's built-in Livewire testing utilities (`Livewire::test()`).
- Verifies components render correctly, respond to user interactions, emit events, and update state.
- File naming: `{Component}ComponentTest.php` (e.g., `SignupWizardComponentTest.php`).
- No real browser needed — these run in PHP via Livewire's test harness.
- Test:
  - Component renders with correct initial state
  - Form inputs bind and validate correctly
  - Button clicks trigger correct actions and state changes
  - Livewire events are emitted/received correctly
  - Components show/hide elements based on role and state
  - Error messages display on validation failure
  - Loading states appear during wire:loading
  - Real-time event listeners update component state (mock broadcast events)

**General test rules:**
- Name tests descriptively: `it('rejects signup when no seats available')`.
- Every test file must test both happy path AND failure cases.
- Tests must be runnable with a single command (see Startup & Scripts below).
- Target: ≥ 80% coverage on business logic (Services/, Models/, Livewire/).
- All tests run in Docker with no external dependencies. No browser, no Chrome, no Selenium.

### File Structure
```
app/
  Enums/
  Exceptions/
  Http/
    Middleware/
    Requests/          ← Form Request classes
  Livewire/
    Auth/
    Credentialing/
    Trips/
    Membership/
    Finance/
    Admin/
    Search/
  Models/
  Services/
  Strategies/          ← Recommendation strategy classes
database/
  migrations/
  seeders/
public/
  fonts/               ← Self-hosted DM Sans, IBM Plex Sans, IBM Plex Mono (committed to git)
resources/
  views/
    layouts/
    components/        ← Reusable Blade components
    livewire/          ← Livewire views, grouped by module
  css/
    app.css            ← Tailwind + CSS variables
docs/
  api-spec.md          ← Generated at end (STAYS)
  design.md            ← Generated at end (STAYS)
  questions.md         ← Decision log (STAYS)
tests/
  Unit/                ← Isolated logic tests (services, models, enums, helpers)
  Feature/             ← HTTP + API integration tests
  Frontend/            ← Livewire component rendering + interaction tests
run_tests.sh           ← Test runner script (STAYS)
docker-compose.yml     ← Single command startup (STAYS)
Makefile               ← Convenience commands (STAYS)
README.md              ← Project overview (STAYS)
.gitattributes         ← Line endings: * text=auto eol=lf (STAYS)
.env.example           ← Config template (STAYS)
claude.md              ← Build instructions (REMOVED at end — see Project Cleanup)
```

> **Note:** `build-prompts.md` and `PRD.md` are YOUR working files. They are used during development but never committed to the repo. `claude.md` IS in the repo during development but removed at the end.

---

## Startup & Scripts (Non-Negotiable)

### Single-Command Startup
The entire application MUST start with one command:
```bash
docker-compose up
```
This must:
1. Build the Laravel app container (PHP 8.3 + all extensions + Node 20 + Composer)
2. Start PostgreSQL 16
3. **Build assets** during container startup: `npm run build` compiles Tailwind CSS + Alpine.js + Laravel Echo into static files in `public/build/`. This runs ONCE on container start, not continuously.
4. Run migrations automatically on first start
5. Seed database if empty (first-time setup)
6. **Start Laravel Reverb** WebSocket server on port 8080 (background process)
7. Serve the app via `php artisan serve` or nginx + php-fpm on port 8000 (NOT `npm run dev`)
8. Be fully usable at `http://localhost:8000` with real-time WebSocket connections after startup completes

No manual steps after `docker-compose up`. The docker-compose entrypoint script handles: wait for DB → `npm run build` → migrate → seed (if empty) → start Reverb → serve.

**After startup, Node is idle.** Only PHP (app + Reverb) serves requests. The compiled CSS/JS in `public/build/` is static.

### docker-compose.yml services
```yaml
services:
  app:        # Laravel + PHP 8.3 + Node 20 + Composer
              # Entrypoint starts both the web server (port 8000) and Reverb (port 8080)
  db:         # PostgreSQL 16
```
Ports exposed: `8000` (web app), `8080` (Reverb WebSocket).

### run_tests.sh
A single script at the project root that runs ALL three test layers in order:
```bash
#!/bin/bash
# run_tests.sh — Run all MedVoyage tests
# Usage: ./run_tests.sh [--unit] [--feature] [--frontend] [--coverage]
# No flags = run all layers

set -e

echo "🧪 MedVoyage Test Suite"
echo "======================"

# Run inside Docker if not already
if [ ! -f /.dockerenv ]; then
    docker-compose exec app bash run_tests.sh "$@"
    exit $?
fi

RUN_UNIT=false
RUN_FEATURE=false
RUN_FRONTEND=false
COVERAGE=false

# Parse flags (no flags = run all)
if [ $# -eq 0 ]; then
    RUN_UNIT=true; RUN_FEATURE=true; RUN_FRONTEND=true
fi
for arg in "$@"; do
    case $arg in
        --unit) RUN_UNIT=true ;;
        --feature) RUN_FEATURE=true ;;
        --frontend) RUN_FRONTEND=true ;;
        --coverage) COVERAGE=true ;;
    esac
done

EXIT_CODE=0

if [ "$RUN_UNIT" = true ]; then
    echo ""
    echo "━━━ Unit Tests ━━━"
    php artisan test --testsuite=Unit || EXIT_CODE=1
fi

if [ "$RUN_FEATURE" = true ]; then
    echo ""
    echo "━━━ Feature / API Tests ━━━"
    php artisan test --testsuite=Feature || EXIT_CODE=1
fi

if [ "$RUN_FRONTEND" = true ]; then
    echo ""
    echo "━━━ Frontend / Livewire Component Tests ━━━"
    php artisan test --testsuite=Frontend || EXIT_CODE=1
fi

if [ "$COVERAGE" = true ]; then
    echo ""
    echo "━━━ Coverage Report ━━━"
    php artisan test --coverage --min=80 || EXIT_CODE=1
fi

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ All tests passed"
else
    echo "❌ Some tests failed"
fi

exit $EXIT_CODE
```
This script MUST be created in Step 0. It MUST be executable (`chmod +x`).

### Makefile
```makefile
setup:         docker-compose up -d --build
serve:         docker-compose up
down:          docker-compose down
migrate:       docker-compose exec app php artisan migrate
seed:          docker-compose exec app php artisan db:seed-demo
test:          docker-compose exec app bash run_tests.sh
test-unit:     docker-compose exec app bash run_tests.sh --unit
test-api:      docker-compose exec app bash run_tests.sh --feature
test-frontend: docker-compose exec app bash run_tests.sh --frontend
coverage:      docker-compose exec app bash run_tests.sh --coverage
shell:         docker-compose exec app bash
tinker:        docker-compose exec app php artisan tinker
fresh:         docker-compose exec app php artisan migrate:fresh --seed
```

### README.md
Every project MUST have a README.md at the root with:
1. **Project name and one-line description**
2. **Prerequisites**: Docker, Docker Compose (that's it — everything else is in the container)
3. **Quick Start**: `docker-compose up` → visit `http://localhost:8000`
4. **Demo Accounts**: table of seeded users with username/password/role
5. **Running Tests**: `./run_tests.sh` or `make test` (with flag descriptions)
6. **Project Structure**: brief directory overview pointing to docs/
7. **Documentation**: links to docs/api-spec.md, docs/design.md, docs/questions.md

---

## Machine Independence (Non-Negotiable)

The repo must work identically on any machine (macOS, Linux, Windows with WSL) with only Docker installed. Zero local dependencies beyond Docker.

### Rules
- **No host-installed PHP, Composer, Node, or npm required.** Everything runs inside Docker containers.
- **No hardcoded absolute paths.** Use only relative paths from project root. No `/home/user/...`, no `/Users/...`, no `C:\...`.
- **No OS-specific shell commands.** Scripts must work in bash (Docker runs Linux). No PowerShell, no cmd.exe assumptions.
- **No host-level port conflicts assumed.** Document default ports in README. Use `.env` for port overrides (e.g., `APP_PORT=8000`, `DB_PORT=5432`).
- **All tool versions pinned in Docker.** PHP version, Node version, PostgreSQL version — all defined in Dockerfiles/docker-compose, not relying on whatever's installed on the host.
- **No volume mounts to OS-specific paths.** Use named Docker volumes for database persistence, not host directory mounts (except the project source code itself).
- **No `sudo` in any script.** Docker handles permissions inside containers.
- **Line endings:** `.gitattributes` must enforce LF for all text files (`* text=auto eol=lf`). Shell scripts must use LF, not CRLF.
- **Font files committed to repo.** Self-hosted fonts in `public/fonts/` are checked into git — no download step required.
- **No global npm/composer installs on host.** All package installs happen inside the container during `docker-compose build`.

### Verification
After cloning the repo on a fresh machine with only Docker:
```bash
git clone <repo>
cd medvoyage
docker-compose up
# App is running at localhost:8000 — done.
```
No other commands, no README "prerequisites" beyond Docker.

---

## Project Cleanup (Final Step)

At the end of the project, the repo must be cleaned to production state. **Build-time files that are not part of the running application are removed.**

### Files to REMOVE from the repo at the end:
- `claude.md` — build instructions for AI, not needed at runtime
- `docs/PRD.md` — specification document, not needed at runtime
- `build-prompts.md` — never goes into the repo at all (it's your personal workflow file)

### Files that REMAIN:
```
docs/
  api-spec.md          ← API documentation (stays)
  design.md            ← Architecture documentation (stays)
  questions.md         ← Decision log (stays)
README.md              ← Project overview (stays at root)
run_tests.sh           ← Test runner (stays at root)
docker-compose.yml     ← Startup (stays at root)
Makefile               ← Convenience commands (stays at root)
.gitattributes         ← Line endings (stays at root)
.env.example           ← Config template (stays at root)
```

### Final docs/ folder contains EXACTLY three files:
```
docs/
  api-spec.md
  design.md
  questions.md
```
Nothing else. No PRD, no claude.md, no build-prompts, no assumptions.

---

## What NOT to Do

- **No external API calls at runtime.** No CDNs, no payment gateways, no email services.
- **No CDN links in Blade templates.** No `<link href="https://...">`, no `<script src="https://...">`. Every asset is local.
- **No Vite dev server at runtime.** `npm run dev` is for local development only. Production/Docker uses `npm run build` once, then serves static files.
- **No `@vite` pointing to external URLs.** The Vite manifest must resolve to local `public/build/` files only.
- **No Redis.** Use database queue driver.
- **No JavaScript frameworks.** Alpine.js (bundled via Vite, not CDN) is the only JS. No React, Vue, jQuery.
- **No generic UI.** Follow the design system above exactly. If a component isn't specified, extrapolate from the design system's principles.
- **No TODOs or stubs.** Every feature is fully implemented or not started.
- **No floating point for money.** Integer cents only.
- **No raw SQL** unless performance-critical and documented.
- **No mass assignment without $fillable.** Every model defines $fillable explicitly.
- **No business logic in Blade templates.** Logic goes in Livewire components or services.
- **No manual setup steps.** `docker-compose up` must be the ONLY command needed to start the app.
- **No tests without assertions.** Every test must assert something specific — no empty test methods.
- **No skipping test layers.** Every step must include unit tests AND feature/API tests AND frontend component tests for any Livewire components built in that step.
- **No hardcoded paths.** No absolute paths anywhere. No `/home/`, `/Users/`, `C:\`. Relative paths only.
- **No host dependencies.** No requiring PHP, Node, Composer, or npm on the host machine. Docker only.
- **No OS-specific commands.** No PowerShell, no `cmd.exe`, no macOS-only flags. Bash scripts must run in Linux (Docker).
- **No CRLF line endings.** Use `.gitattributes` with `* text=auto eol=lf`.

---

## Recurring Patterns (Copy-Paste Saved)

When I say **"add audit logging"** — it means call `AuditLog::record()` on every create, update, status change, approval, and export in that feature. Include before/after JSON diff.

When I say **"add idempotency"** — it means the endpoint/action checks `X-Idempotency-Key`, uses `IdempotencyMiddleware`, and returns cached response on duplicate.

When I say **"add optimistic locking"** — it means the model has a `version` column, updates use `WHERE version = ?`, and stale writes throw 409.

When I say **"standard CRUD with audit"** — it means: model, migration, Form Request, Service class, Livewire component, Blade view, audit logging on all writes, optimistic locking, and full tests (unit + feature + frontend component).

When I say **"follow the design system"** — it means use the exact colors, typography, spacing, component styles, and layout rules from this file. Do not freestyle.

When I say **"full test coverage"** — it means:
1. **Unit tests** for every service method and state machine transition (valid + invalid)
2. **Feature/API tests** for every route/action: happy path, validation errors (422), permission denied (403), optimistic lock (409), idempotency (duplicate key)
3. **Frontend component tests** for every Livewire component: renders correctly, form bindings work, actions trigger correct state changes, events emitted, role-based visibility
All three layers go in their respective directories: `tests/Unit/`, `tests/Feature/`, `tests/Frontend/`.