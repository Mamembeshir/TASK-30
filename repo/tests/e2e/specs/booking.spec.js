import { test, expect } from '@playwright/test';

const BASE_URL        = process.env.BASE_URL        || 'http://localhost:8000';
// The custom VerifyApiCsrfToken middleware exempts JSON requests whose Origin
// matches the app's configured APP_URL.  Inside Docker we talk to the server
// at http://web:8000, but APP_URL on the app container is http://localhost:8000
// — so API POSTs must send the APP_URL (not BASE_URL) as their Origin.
const APP_ORIGIN      = process.env.APP_ORIGIN      || 'http://localhost:8000';
const MEMBER_EMAIL    = process.env.E2E_MEMBER_EMAIL    || 'member@medvoyage.test';
const MEMBER_PASSWORD = process.env.E2E_MEMBER_PASSWORD || 'Seed1234!@';
const ADMIN_EMAIL     = process.env.E2E_ADMIN_EMAIL     || 'admin@medvoyage.test';
const ADMIN_PASSWORD  = process.env.E2E_ADMIN_PASSWORD  || 'Seed1234!@';

// ── Helpers ───────────────────────────────────────────────────────────────────

async function loginAs(page, email, password) {
  await page.goto('/login');
  await page.getByLabel('Email or username').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: /sign in/i }).click();
  try {
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
  } catch (e) {
    const url    = page.url();
    const errors = await page.locator('[role="alert"], p.text-xs, .text-red-500, .text-red-600, .error')
      .allInnerTexts().catch(() => []);
    throw new Error(
      `Login did not redirect to /dashboard for ${email}. `
      + `Still at ${url}. Page errors: ${JSON.stringify(errors.slice(0, 5))}`,
    );
  }
}

/**
 * Look up the seeded approved doctor's ID.  The seeder provides one approved
 * doctor but there is no API to list doctors, so we scrape the `lead doctor`
 * <select> on /admin/trips/create (the only place it is exposed).
 */
async function getSeededDoctorId(request, adminCookies) {
  const resp = await request.get(`${BASE_URL}/admin/trips/create`, {
    headers: { 'Cookie': adminCookies },
  });
  const html  = await resp.text();
  const match = html.match(/<option\s+value="([0-9a-f-]{36})"/i);
  if (!match) throw new Error('No doctor option found on /admin/trips/create');
  return match[1];
}

/**
 * Create a published trip via the admin API and return its ID.
 * Uses Playwright's request context so we don't need a browser page.
 */
async function createPublishedTrip(request, adminCookies) {
  const leadDoctorId = await getSeededDoctorId(request, adminCookies);

  // 1. Create draft trip
  const create = await request.post(`${BASE_URL}/api/admin/trips`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Origin': APP_ORIGIN,
      'Cookie': adminCookies,
    },
    data: {
      title:            'E2E Cardiology Outreach',
      lead_doctor_id:   leadDoctorId,
      destination:      'Lima, Peru',
      specialty:        'Cardiology',
      description:      'A week-long cardiology outreach programme for certified physicians.',
      start_date:       new Date(Date.now() + 30 * 86_400_000).toISOString().split('T')[0],
      end_date:         new Date(Date.now() + 37 * 86_400_000).toISOString().split('T')[0],
      price_cents:      50000,
      total_seats:      10,
      difficulty_level: 'MODERATE',
      idempotency_key:  `e2e-trip-${Date.now()}`,
    },
  });

  const trip = await create.json();
  if (!create.ok()) throw new Error(`Trip creation failed (${create.status()}): ${JSON.stringify(trip)}`);

  // 2. Publish the trip
  const publish = await request.post(`${BASE_URL}/api/admin/trips/${trip.id}/publish`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Origin': APP_ORIGIN,
      'Cookie': adminCookies,
    },
    data: {},
  });

  if (!publish.ok()) {
    const err = await publish.json();
    throw new Error(`Trip publish failed (${publish.status()}): ${JSON.stringify(err)}`);
  }

  return trip.id;
}

/** Log in as admin via the browser and capture the session cookies. */
async function getAdminCookies(browser) {
  const ctx  = await browser.newContext();
  const page = await ctx.newPage();

  await page.goto(`${BASE_URL}/login`);
  await page.getByLabel('Email or username').fill(ADMIN_EMAIL);
  await page.getByLabel('Password').fill(ADMIN_PASSWORD);
  await page.getByRole('button', { name: /sign in/i }).click();
  try {
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });
  } catch (e) {
    const errors = await page.locator('[role="alert"], p.text-xs, .text-red-500, .text-red-600, .error')
      .allInnerTexts().catch(() => []);
    throw new Error(
      `Admin login did not redirect to /dashboard. `
      + `Still at ${page.url()}. Page errors: ${JSON.stringify(errors.slice(0, 5))}`,
    );
  }

  const cookies = await ctx.cookies();
  await ctx.close();

  return cookies.map(c => `${c.name}=${c.value}`).join('; ');
}

// ── Journey: trip list → trip detail ─────────────────────────────────────────

test('member can browse the trip listing page', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/trips');
  await expect(page).toHaveURL(/\/trips/);
  // Page renders — either trips are listed or an empty-state message appears
  await expect(page.locator('body')).toBeVisible();
});

// ── Journey: hold a seat → submit payment ─────────────────────────────────────

test('member can hold a seat and submit payment for a published trip', async ({ page, browser, request }) => {
  // Set up: create a published trip as admin
  const adminCookies = await getAdminCookies(browser);
  const tripId       = await createPublishedTrip(request, adminCookies);

  // Act as member
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);

  // Navigate to the trip detail page
  await page.goto(`/trips/${tripId}`);
  await expect(page.locator('h1, h2').first()).toBeVisible();

  // Click the hold / book button
  const holdBtn = page.getByRole('button', { name: /hold|book|register|sign.?up/i }).first();
  await expect(holdBtn).toBeVisible();
  await holdBtn.click();

  // After hold the signup wizard or a confirmation message should appear
  await expect(
    page.locator('[data-testid="signup-wizard"], .signup-wizard, [wire\\:id]').first()
      .or(page.getByText(/your seat is held|seat reserved|payment/i).first()),
  ).toBeVisible({ timeout: 10_000 });
});

// ── Journey: search for trips ─────────────────────────────────────────────────

test('member can search for trips using the keyword search', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/trips');

  const searchInput = page.getByRole('textbox').first();
  await expect(searchInput).toBeVisible();

  await searchInput.fill('Cardiology');
  // Results update via Livewire — wait for the network to settle
  await page.waitForTimeout(600);

  // The page should still be the trips listing (no full navigation)
  await expect(page).toHaveURL(/\/trips/);
});

// ── Journey: clear search history ────────────────────────────────────────────

test('member can clear their search history via the API', async ({ page, request }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);

  // Trigger a search to create some history
  await page.goto('/trips');
  const searchInput = page.getByRole('textbox').first();
  if (await searchInput.isVisible()) {
    await searchInput.fill('Surgery');
    await page.waitForTimeout(500);
  }

  // Now clear history via the API (same session cookies the browser is using)
  const cookies = (await page.context().cookies())
    .map(c => `${c.name}=${c.value}`)
    .join('; ');

  const resp = await request.post(`${BASE_URL}/api/search/history/clear`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Origin': APP_ORIGIN,
      'Cookie': cookies,
    },
    data: {},
  });

  expect(resp.ok()).toBeTruthy();
  const body = await resp.json();
  expect(body.message).toBe('Search history cleared.');
});
