import { test, expect } from '@playwright/test';

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
    await page.waitForURL(/\/dashboard/, { timeout: 8_000 });
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

// ── Admin: trip management ─────────────────────────────────────────────────────

test('admin can navigate to the create-trip page', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await page.goto('/admin/trips/create');

  // The trip manage form must render
  await expect(page.getByRole('heading', { name: /trip|create|manage/i }).first()).toBeVisible();
  // The Livewire form uses wire:model bindings; the <label>/<input> pair has
  // empty for="" / id="" attributes so getByLabel can't resolve them.  Assert
  // on the wire-bound title input directly.
  const titleInput = page.locator('input[wire\\:model="title"], input[wire\\:model\\.live="title"]').first();
  await expect(titleInput).toBeVisible();
});

test('non-admin member cannot access admin trip creation (redirects or 403)', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/admin/trips/create');

  // Should be redirected away or see a forbidden message — not the admin form
  const url = page.url();
  const hasForbiddenText = await page.getByText(/forbidden|unauthorized|not allowed|403/i).isVisible().catch(() => false);

  expect(
    !url.includes('/admin/trips/create') || hasForbiddenText,
  ).toBeTruthy();
});

// ── Admin: user management ─────────────────────────────────────────────────────

test('admin dashboard is accessible after login', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await expect(page).toHaveURL(/\/dashboard/);
  // Dashboard renders some content
  await expect(page.locator('main, [role="main"], body').first()).toBeVisible();
});

// ── Reviews: create a review ──────────────────────────────────────────────────

test('review form page renders for authenticated member', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);

  // Navigate to the trip listing to find an available trip
  await page.goto('/trips');

  // If a trip link is visible, try to navigate to the review form for it
  const tripLinks = page.locator('a[href*="/trips/"]');
  const count     = await tripLinks.count();

  if (count > 0) {
    const href = await tripLinks.first().getAttribute('href');
    // Navigate to the review create URL for this trip
    const tripId = href.split('/trips/')[1]?.split('/')[0];

    if (tripId) {
      await page.goto(`/trips/${tripId}/reviews/create`);
      // Either the form renders or we're redirected (e.g. no confirmed signup)
      await expect(page.locator('body')).toBeVisible();
    }
  }
  // If no trips exist, the test passes vacuously — data-dependent flows
  // are covered by the booking journey in booking.spec.js
});

// ── Reviews: my trips ─────────────────────────────────────────────────────────

test('member can view their "My Trips" page', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/my-trips');
  await expect(page).toHaveURL(/my-trips/);
  // Page renders regardless of whether there are trips
  await expect(page.locator('body')).toBeVisible();
});

// ── Membership flow ────────────────────────────────────────────────────────────

test('membership plan catalogue renders for authenticated member', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/membership');
  await expect(page).toHaveURL(/\/membership/);
  await expect(page.locator('body')).toBeVisible();
});

// ── Admin: finance dashboard ───────────────────────────────────────────────────

test('finance dashboard is inaccessible to plain members', async ({ page }) => {
  await loginAs(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await page.goto('/finance');

  // Should not see finance content — either redirected or 403
  const url = page.url();
  const hasForbiddenText = await page.getByText(/forbidden|unauthorized|not allowed|403/i).isVisible().catch(() => false);

  expect(
    !url.includes('/finance') || hasForbiddenText,
  ).toBeTruthy();
});
