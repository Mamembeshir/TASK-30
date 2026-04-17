.PHONY: setup serve down migrate seed test test-unit test-feature test-e2e coverage shell tinker fresh docs-check

# Build and start (detached)
setup:
	docker compose up -d --build

# Start in foreground (logs visible)
serve:
	docker compose up

down:
	docker compose down

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed

# Run all tests: unit + feature + e2e (Playwright)
test:
	./run_tests.sh

# PHP-only suites (no e2e, no full stack startup required)
test-unit:
	./run_tests.sh --unit

test-feature:
	./run_tests.sh --feature

# Browser E2E only (starts full stack automatically if not already up)
test-e2e:
	./run_tests.sh --e2e

coverage:
	./run_tests.sh --unit --feature --coverage

shell:
	docker compose exec app bash

tinker:
	docker compose exec app php artisan tinker

# Verify that command names in docs match what exists in source.
# Run this after changing any artisan command signature or scheduled cadence.
docs-check:
	@echo "=== Checking scheduled command names in docs/api-spec.md vs app/Console/Commands/ ==="
	@# Only check commands that appear in the scheduler (routes/console.php).
	@# Dev-only utilities (db:seed-demo, medvoyage:verify-audit-chain, …) are
	@# not scheduled and intentionally absent from api-spec.md.
	@for sig in $$(grep -h 'protected \$$signature' app/Console/Commands/*.php | grep -oE "'[^']+'" | tr -d "'" | cut -d' ' -f1); do \
	    grep -qF "$$sig" routes/console.php || continue; \
	    grep -q "$$sig" docs/api-spec.md \
	    && echo "  OK  $$sig" \
	    || echo "  MISSING  $$sig  (scheduled in console.php but not in docs/api-spec.md)"; \
	done
	@echo "=== Checking middleware names in README.md vs app/Http/Middleware/ ==="
	@for mw in $$(ls app/Http/Middleware/ | sed 's/Middleware\.php$$//; s/\.php$$//'); do \
	    grep -q "$$mw" README.md \
	    && echo "  OK  $$mw" \
	    || echo "  MISSING  $$mw  (in Middleware/ but not in README.md)"; \
	done
	@echo "Done."
