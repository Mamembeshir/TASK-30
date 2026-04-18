#!/bin/bash
# run_tests.sh — Run MedVoyage tests (unit, feature, and/or e2e)
#
# Usage:
#   ./run_tests.sh                    # unit + feature + e2e  (default)
#   ./run_tests.sh --unit             # unit tests only
#   ./run_tests.sh --feature          # feature/API tests only
#   ./run_tests.sh --e2e              # browser E2E tests only
#   ./run_tests.sh --unit --feature   # PHP tests, no e2e
#   ./run_tests.sh --coverage         # add coverage report to PHP tests
#   ./run_tests.sh --all              # same as no flags (unit + feature + e2e)
#
# Exit codes: 0 = all selected suites passed, non-zero = at least one failed.

set -e

echo "MedVoyage Test Suite"
echo "===================="

# ── Parse flags ───────────────────────────────────────────────────────────────
RUN_UNIT=false
RUN_FEATURE=false
RUN_E2E=false
COVERAGE=false

if [ $# -eq 0 ]; then
    # Default: run everything
    RUN_UNIT=true
    RUN_FEATURE=true
    RUN_E2E=true
fi

for arg in "$@"; do
    case $arg in
        --unit)     RUN_UNIT=true ;;
        --feature)  RUN_FEATURE=true ;;
        --e2e)      RUN_E2E=true ;;
        --browser)  RUN_E2E=true ;;
        --coverage) COVERAGE=true ;;
        --all)      RUN_UNIT=true; RUN_FEATURE=true; RUN_E2E=true ;;
    esac
done

# ── HOST SIDE: orchestrate containers ─────────────────────────────────────────
# /var/www/html/vendor/autoload.php only exists inside the built image.
# On the host (and in CI) we delegate into Docker.
if [ ! -f /var/www/html/vendor/autoload.php ]; then

    EXIT_CODE=0

    # E2E tests require the full app stack to be up and healthy.
    # PHP-only tests only need the db container (existing behaviour).
    if [ "$RUN_E2E" = true ]; then
        echo "Starting full application stack for E2E tests..."
        docker compose up -d --build

        echo "Waiting for app container to be healthy (up to 120 s)..."
        WAITED=0
        until docker inspect medvoyage_app --format='{{.State.Health.Status}}' 2>/dev/null \
              | grep -q "^healthy$"; do
            sleep 3
            WAITED=$((WAITED + 3))
            if [ $WAITED -ge 120 ]; then
                echo "ERROR: App did not become healthy within 120 s — aborting."
                docker compose logs app | tail -30
                exit 1
            fi
        done
        echo "App is healthy."

        # ── Ensure the demo users are present, unlocked, and have the
        #    known test password ────────────────────────────────────────────
        # Seed when the member user is missing (preserves any data that may
        # already be in the DB), then explicitly reset password +
        # lockout counters for every seeded account.  Going through the User
        # model + Hash::make() (rather than trusting whatever the seeder
        # produced) means the test password works regardless of DB drift
        # between runs or past experiments.
        echo "Ensuring seeded demo users are usable for E2E tests..."
        USER_COUNT=$(docker compose exec -T db \
            psql -U "${DB_USERNAME:-medvoyage}" -d "${DB_DATABASE:-medvoyage}" \
                 -tAc "SELECT count(*) FROM users WHERE email='member@medvoyage.test'" \
            2>/dev/null | tr -cd '0-9')
        echo "  seeded member-user count: [$USER_COUNT]"
        if [ "$USER_COUNT" != "1" ]; then
            echo "  demo users missing — running php artisan db:seed --force..."
            docker compose exec -T app php artisan db:seed --force
        fi
        echo "  resetting test-account passwords and lockout counters..."
        docker compose exec -T app php artisan tinker --no-interaction --execute='
            foreach ([
                "admin@medvoyage.test",
                "reviewer@medvoyage.test",
                "finance@medvoyage.test",
                "doctor@medvoyage.test",
                "member@medvoyage.test",
            ] as $email) {
                $u = \App\Models\User::where("email", $email)->first();
                if (!$u) { echo "  MISSING: {$email}\n"; continue; }
                $u->forceFill([
                    "password"            => \Hash::make("Seed1234!@"),
                    "failed_login_count"  => 0,
                    "locked_until"        => null,
                ])->save();
                echo "  OK: {$email}\n";
            }
        ' 2>&1 | grep -E 'MISSING|OK' || true
    else
        docker compose build --quiet
        docker compose up -d db
    fi

    # ── PHP tests (unit and/or feature) ──────────────────────────────────────
    # Build the flag list to pass into the container (e2e is host-only).
    PHP_FLAGS=()
    $RUN_UNIT    && PHP_FLAGS+=(--unit)
    $RUN_FEATURE && PHP_FLAGS+=(--feature)
    $COVERAGE    && PHP_FLAGS+=(--coverage)

    RUN_PHP=false
    { $RUN_UNIT || $RUN_FEATURE; } && RUN_PHP=true

    if [ "$RUN_PHP" = true ]; then
        if [ "$RUN_E2E" = true ]; then
            # App is already running — exec into it directly.
            docker compose exec app bash run_tests.sh "${PHP_FLAGS[@]}"
        else
            # Fresh one-off container (entrypoint handles DB wait + migrations).
            docker compose run --rm app bash run_tests.sh "${PHP_FLAGS[@]}"
        fi
        PHP_EXIT=$?
        [ $PHP_EXIT -ne 0 ] && EXIT_CODE=$PHP_EXIT
    fi

    # ── E2E tests ─────────────────────────────────────────────────────────────
    if [ "$RUN_E2E" = true ]; then
        echo ""
        echo "--- E2E / Browser Tests (Playwright) ---"
        # Uses the public mcr.microsoft.com/playwright:v1.59.1-noble image
        # (see docker-compose.yml).  Docker will pull it on the first run in
        # a clean environment (CI) and reuse the cached copy thereafter.
        docker compose --profile e2e run --rm playwright
        E2E_EXIT=$?
        [ $E2E_EXIT -ne 0 ] && EXIT_CODE=$E2E_EXIT
    fi

    rm -rf storage/framework/testing 2>/dev/null || true

    echo ""
    if [ $EXIT_CODE -eq 0 ]; then
        echo "All tests passed."
    else
        echo "One or more test suites failed."
    fi
    exit $EXIT_CODE
fi

# ── CONTAINER SIDE: run PHP tests only ───────────────────────────────────────
# Playwright cannot be invoked from inside the app container; the host side
# already handles E2E orchestration before delegating here.

# ── Generate .env.testing from .env.example if it does not exist ─────────────
if [ ! -f .env.testing ]; then
    if [ ! -f .env.example ]; then
        echo "ERROR: .env.example not found — cannot generate .env.testing"
        exit 1
    fi
    echo "Generating .env.testing from .env.example..."
    cp .env.example .env.testing
    sed -i 's/^APP_ENV=.*/APP_ENV=testing/'                .env.testing
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=medvoyage_test/' .env.testing
    if grep -q '^APP_KEY=$' .env.testing; then
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env.testing
    fi
fi

EXIT_CODE=0

if [ "$RUN_UNIT" = true ] && [ "$RUN_FEATURE" = true ]; then
    echo ""
    echo "--- Unit + Feature Tests ---"
    php artisan test || EXIT_CODE=1
elif [ "$RUN_UNIT" = true ]; then
    echo ""
    echo "--- Unit Tests ---"
    php artisan test --testsuite=Unit || EXIT_CODE=1
elif [ "$RUN_FEATURE" = true ]; then
    echo ""
    echo "--- Feature Tests ---"
    php artisan test --testsuite=Feature || EXIT_CODE=1
fi

if [ "$COVERAGE" = true ]; then
    echo ""
    echo "--- Coverage Report ---"
    php artisan test --coverage --min=80 || EXIT_CODE=1
fi

rm -rf storage/framework/testing

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "All tests passed."
else
    echo "One or more test suites failed."
fi

exit $EXIT_CODE
