import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  globalSetup: './globalSetup.js',
  timeout: 30_000,
  retries: 1,

  // php artisan serve is single-threaded — parallel workers cause connections
  // to be dropped (ERR_CONNECTION_CLOSED). Run one test at a time.
  workers: 1,

  reporter: [['list'], ['html', { outputFolder: '/tmp/playwright-report', open: 'never' }]],

  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    navigationTimeout: 15_000,

    // Standard flags for running Chromium inside a Docker container:
    //   --no-sandbox / --disable-setuid-sandbox → the renderer sandbox cannot
    //   escape the container's user namespace and would crash silently.
    //   --disable-dev-shm-usage → Docker limits /dev/shm to 64 MB which
    //   Chromium's compositor can exhaust.
    // See also the `aliases: [web]` on the app service in docker-compose.yml
    // — Chromium auto-upgrades `http://app:*` to HTTPS (HSTS preload on the
    // `.app` TLD matches single-label `app` too), which is why the tests
    // reach the server under the `web` alias instead.
    launchOptions: {
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
      ],
    },
  },

  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});
