#!/bin/bash
# run_tests.sh — Run MedVoyage tests (unit + feature, all inside Docker)
# Usage: ./run_tests.sh [--unit] [--feature] [--frontend] [--coverage]
# No flags = run unit + feature

set -e

echo "MedVoyage Test Suite"
echo "===================="

# If running on the host, proxy into the app container
if [ ! -f /.dockerenv ]; then
    docker compose exec app bash run_tests.sh "$@"
    exit $?
fi

# ── Generate .env.testing from .env.example if it does not exist ──────────────
if [ ! -f .env.testing ]; then
    if [ ! -f .env.example ]; then
        echo "ERROR: .env.example not found — cannot generate .env.testing"
        exit 1
    fi
    echo "Generating .env.testing from .env.example …"
    cp .env.example .env.testing
    # Override values that must differ for the test environment
    sed -i 's/^APP_ENV=.*/APP_ENV=testing/'           .env.testing
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=medvoyage_test/' .env.testing
    # Generate an APP_KEY if the example ships with an empty one
    if grep -q '^APP_KEY=$' .env.testing; then
        php artisan key:generate --env=testing --no-interaction --quiet
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

# Run unit + feature together as a single artisan invocation so PHPUnit
# collects both suites in one pass.  Splitting into two separate
# --testsuite flags does not reliably union suites in PHPUnit 10/11 —
# the last flag silently wins, causing one suite to be skipped.
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

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "All tests passed."
else
    echo "Some tests failed."
fi

exit $EXIT_CODE
