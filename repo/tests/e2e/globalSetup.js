// globalSetup.js — run once before any Playwright test.
//
// Uses Node's built-in http module (no Chromium) to confirm the app is
// reachable on the Docker network.  If this probe fails the run aborts with a
// clear diagnostic message instead of 19 ERR_CONNECTION_CLOSED errors.

import http from 'http';

function probe(url) {
  return new Promise((resolve, reject) => {
    const req = http.get(url, (res) => {
      res.resume(); // drain the socket
      resolve(res.statusCode);
    });
    req.on('error', reject);
    req.setTimeout(5_000, () => {
      req.destroy();
      reject(new Error(`timeout after 5 s`));
    });
  });
}

export default async function globalSetup() {
  const baseUrl   = process.env.BASE_URL || 'http://localhost:8000';
  const loginUrl  = `${baseUrl}/login`;
  const maxWaitMs = 30_000;
  const start     = Date.now();

  console.log(`\n[globalSetup] Probing ${loginUrl} (up to ${maxWaitMs / 1000} s)…`);

  let last;
  while (Date.now() - start < maxWaitMs) {
    try {
      const status = await probe(loginUrl);
      console.log(`[globalSetup] ✓ App responded HTTP ${status} — browser tests starting.\n`);
      return;
    } catch (err) {
      last = err;
      console.log(`[globalSetup]   not ready yet (${err.message}), retrying in 3 s…`);
      await new Promise(r => setTimeout(r, 3_000));
    }
  }

  throw new Error(
    `[globalSetup] App at ${loginUrl} not reachable after ${maxWaitMs / 1000} s.\n` +
    `Last error: ${last?.message}\n` +
    `Check that the app container is healthy before running E2E tests.`,
  );
}
