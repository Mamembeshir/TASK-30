# Playwright test runner image for MedVoyage E2E tests.
#
# The image ships with @playwright/test and the shared libraries Chromium
# needs at runtime.  The Chromium binary itself is downloaded on first run
# and cached in the `playwright_cache` named volume (see docker-compose.yml).
#
# Tag is pinned to `repo-playwright:latest` so CI and local runs share the
# same name — the docker-compose `build:` block rebuilds this image when it
# is missing (which is always the case in a clean CI environment).

FROM node:20-bookworm-slim

# Chromium runtime dependencies.  Matches the minimal set Playwright's
# `playwright install --with-deps chromium` would install on Debian 12 —
# baking them in removes the per-run `apt-get install` network hop.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libglib2.0-0 libnss3 libnspr4 libdbus-1-3 libatk1.0-0 \
        libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 \
        libxdamage1 libxfixes3 libxrandr2 libgbm1 libpango-1.0-0 libcairo2 \
        libasound2 libatspi2.0-0 fonts-liberation \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Minimal package.json so `@playwright/test` can be resolved from /app when
# test specs (mounted at /app/e2e) do `import { test } from '@playwright/test'`.
# Version pinned to the one the config targets.
RUN echo '{"name":"medvoyage-e2e","version":"1.0.0","private":true,"type":"module","dependencies":{"@playwright/test":"1.59.1"}}' \
        > package.json \
 && npm install --no-audit --no-fund --silent
