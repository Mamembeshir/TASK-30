.PHONY: setup serve down migrate seed test test-unit test-feature coverage shell tinker fresh

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

# Run all tests (unit + feature) inside the app container
test:
	docker compose exec app bash run_tests.sh

test-unit:
	docker compose exec app bash run_tests.sh --unit

test-feature:
	docker compose exec app bash run_tests.sh --feature

test-frontend:
	docker compose exec app bash run_tests.sh --frontend

coverage:
	docker compose exec app bash run_tests.sh --coverage

shell:
	docker compose exec app bash

tinker:
	docker compose exec app php artisan tinker
