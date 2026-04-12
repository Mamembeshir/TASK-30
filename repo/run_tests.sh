#!/bin/bash
# run_tests.sh — Run MedVoyage tests (unit + feature, all inside Docker)
# Usage: ./run_tests.sh [--unit] [--feature] [--frontend] [--coverage]
# No flags = run unit + feature

set -e

echo "MedVoyage Test Suite"
echo "===================="

# ── Ensure tests run inside the Docker app container ─────────────────────────
# If vendor/autoload.php exists at /var/www/html we are already inside the app
# container. Otherwise we are on the host or a CI runner — build the images,
# start the DB, and run a one-off app container for the test suite.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    docker compose build --quiet
    docker compose up -d db            # start DB only; the run container talks to it
    docker compose run --rm app bash run_tests.sh "$@"
    EXIT=$?
    rm -rf storage/framework/testing   # clean up volume-mount artifacts
    exit $EXIT
fi

# ── From here we are inside the Docker app container ─────────────────────────

# ── Generate .env.testing from .env.example if it does not exist ─────────────
if [ ! -f .env.testing ]; then
    if [ ! -f .env.example ]; then
        echo "ERROR: .env.example not found — cannot generate .env.testing"
        exit 1
    fi
    echo "Generating .env.testing from .env.example …"
    cp .env.example .env.testing
    sed -i 's/^APP_ENV=.*/APP_ENV=testing/'                .env.testing
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=medvoyage_test/' .env.testing
    # Generate an APP_KEY if the example ships with an empty one
    if grep -q '^APP_KEY=$' .env.testing; then
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env.testing
    fi
fi

RUN_UNIT=false
RUN_FEATURE=false
RUN_FRONTEND=false
COVERAGE=false

# No flags → run everything
if [ $# -eq 0 ]; then
    RUN_UNIT=true
    RUN_FEATURE=true
fi

for arg in "$@"; do
    case $arg in
        --unit)     RUN_UNIT=true ;;
        --feature)  RUN_FEATURE=true ;;
        --frontend) RUN_FRONTEND=true ;;
        --browser)  RUN_FRONTEND=true ;;
        --coverage) COVERAGE=true ;;
        --all)      RUN_UNIT=true; RUN_FEATURE=true ;;
    esac
done

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

if [ "$RUN_FRONTEND" = true ]; then
    echo ""
    echo "--- Frontend Tests ---"
    echo "  (No browser/E2E tests configured — skipping)"
fi

if [ "$COVERAGE" = true ]; then
    echo ""
    echo "--- Coverage Report ---"
    php artisan test --coverage --min=80 || EXIT_CODE=1
fi

# ── Clean up temp artifacts left by Storage::fake() ──────────────────────────
rm -rf storage/framework/testing

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "All tests passed."
else
    echo "Some tests failed."
fi

exit $EXIT_CODE
