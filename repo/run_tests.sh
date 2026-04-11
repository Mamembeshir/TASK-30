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

if [ "$RUN_UNIT" = true ]; then
    echo ""
    echo "--- Unit Tests ---"
    php artisan test --testsuite=Unit || EXIT_CODE=1
fi

if [ "$RUN_FEATURE" = true ]; then
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
