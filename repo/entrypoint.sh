#!/bin/bash
set -e

echo "============================================"
echo "  MedVoyage — Starting Application"
echo "============================================"

# ── 1. Wait for PostgreSQL ────────────────────────────────────────────────────
echo "[1/7] Waiting for PostgreSQL..."
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-medvoyage}" -d "${DB_DATABASE:-medvoyage}" -q; do
    sleep 1
done
echo "      PostgreSQL is ready."

# ── 1b. Ensure PHP vendor dependencies are present ──────────────────────────
# The volume mount (.:/var/www/html) can overwrite vendor/ from the image.
# This must run before any php artisan command.
if [ ! -f vendor/autoload.php ]; then
    echo "      vendor/ missing — running composer install..."
    composer install --no-interaction --no-scripts --prefer-dist --quiet
    composer run-script post-autoload-dump 2>/dev/null || true
    echo "      Composer install complete."
fi

# ── 2. Ensure .env exists and has APP_KEY ────────────────────────────────────
echo "[2/7] Checking .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
    echo "      Generated .env and app key."
else
    echo "      .env found."
    # Generate app key if missing or empty
    if ! grep -q "^APP_KEY=.\+" .env; then
        php artisan key:generate --force
        echo "      Generated missing app key."
    fi
fi

# ── 3. Create test database (used by run_tests.sh inside the container) ───────
echo "[3/7] Ensuring test database exists..."
PGPASSWORD="${DB_PASSWORD:-secret}" psql \
    -h "${DB_HOST:-db}" \
    -U "${DB_USERNAME:-medvoyage}" \
    -tc "SELECT 1 FROM pg_database WHERE datname = '${DB_DATABASE:-medvoyage}_test'" \
    | grep -q 1 \
  || PGPASSWORD="${DB_PASSWORD:-secret}" psql \
        -h "${DB_HOST:-db}" \
        -U "${DB_USERNAME:-medvoyage}" \
        -c "CREATE DATABASE \"${DB_DATABASE:-medvoyage}_test\";" \
  && echo "      Test database ready." \
  || echo "      Test database already exists."

# ── 4. Build frontend assets ─────────────────────────────────────────────────
echo "[4/7] Building frontend assets (Tailwind + Alpine.js via Vite)..."
# node_modules may be absent if the host volume was mounted over the build layer
if [ ! -x node_modules/.bin/vite ]; then
    echo "      node_modules missing — running npm install..."
    npm install --silent
fi
if [ ! -d public/build ] || [ ! -f public/build/manifest.json ]; then
    npm run build
    echo "      Assets built successfully."
else
    echo "      Assets already built, skipping."
fi

# ── 5. Run migrations ─────────────────────────────────────────────────────────
echo "[5/7] Running database migrations..."
php artisan migrate --force
echo "      Migrations complete."

# ── 6. Seed if empty ──────────────────────────────────────────────────────────
echo "[6/7] Checking if database needs seeding..."
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "0")
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "      Seeding database with demo data..."
    php artisan db:seed --force
    echo "      Database seeded."
else
    echo "      Database already has data ($USER_COUNT users), skipping seed."
fi

# ── 7. Start services ─────────────────────────────────────────────────────────
echo "[7/7] Starting services..."

# Laravel Reverb WebSocket server (background)
php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction &
echo "      Reverb WebSocket running on port 8080 (PID $!)."

# Queue worker — picks up delayed jobs used for real-time seat-hold and
# waitlist-offer expiry (see App\Jobs\*). Without this the delayed jobs
# would pile up in the `jobs` table and never fire. --sleep=1 keeps the
# expiry latency under a second; --tries=3 gives transient DB blips a
# chance to recover before a job is marked failed.
php artisan queue:work --sleep=1 --tries=3 --queue=default &
echo "      Queue worker running (PID $!)."

# Scheduler — runs the 10-minute safety-net sweeps for stranded holds/offers
# (the real-time delayed jobs handle the primary path).
php artisan schedule:work &
echo "      Scheduler running (PID $!)."

echo ""
echo "  App URL:    http://localhost:8000"
echo "  Reverb WS:  ws://localhost:8080"
echo "  Tests:      ./run_tests.sh  (or: make test)"
echo "============================================"

# If a command was passed (e.g. `docker compose run app bash run_tests.sh`),
# execute it instead of starting the web server.
if [ $# -gt 0 ]; then
    exec "$@"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
