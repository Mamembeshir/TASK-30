import { test, expect } from '@playwright/test';

// Credentials from UserSeeder — default password: Seed1234!@
const MEMBER_EMAIL    = process.env.E2E_MEMBER_EMAIL    || 'member@medvoyage.test';
const MEMBER_PASSWORD = process.env.E2E_MEMBER_PASSWORD || 'Seed1234!@';
const ADMIN_EMAIL     = process.env.E2E_ADMIN_EMAIL     || 'admin@medvoyage.test';
const ADMIN_PASSWORD  = process.env.E2E_ADMIN_PASSWORD  || 'Seed1234!@';

// ── Helpers ───────────────────────────────────────────────────────────────────

async function login(page, email, password) {
  await page.goto('/login');
  await page.getByLabel('Email or username').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: /sign in/i }).click();
  // Wait for Livewire's redirect to /dashboard to actually land.  If it
  // doesn't, surface the real error on the /login page — otherwise CI
  // failures look like opaque timeouts.
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

// ── Redirect guard ────────────────────────────────────────────────────────────

test('unauthenticated visitor is redirected to /login', async ({ page }) => {
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login/);
});

test('visiting root redirects to /login when not authenticated', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/login/);
});

// ── Login page ────────────────────────────────────────────────────────────────

test('login page renders the sign-in form', async ({ page }) => {
  await page.goto('/login');
  await expect(page.getByLabel('Email or username')).toBeVisible();
  await expect(page.getByLabel('Password')).toBeVisible();
  await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
});

test('member can log in and reaches the dashboard', async ({ page }) => {
  await login(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await expect(page).toHaveURL(/\/dashboard/);
});

test('admin can log in and reaches the dashboard', async ({ page }) => {
  await login(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await expect(page).toHaveURL(/\/dashboard/);
});

test('wrong password shows a validation error', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email or username').fill(MEMBER_EMAIL);
  await page.getByLabel('Password').fill('wrong-password-that-will-fail');
  await page.getByRole('button', { name: /sign in/i }).click();

  // Livewire renders validation errors in-page; the user stays on /login
  await expect(page).toHaveURL(/\/login/);
  // Some error indicator is visible (error paragraph or alert)
  await expect(page.locator('p.text-xs, [role="alert"]').first()).toBeVisible();
});

// ── Logout ────────────────────────────────────────────────────────────────────

test('authenticated user can log out and is redirected to /login', async ({ page }) => {
  await login(page, MEMBER_EMAIL, MEMBER_PASSWORD);
  await expect(page).toHaveURL(/\/dashboard/);

  // The logout form posts to /logout (GET is not allowed).  Submit it and
  // wait for the redirect-to-login in a single step — running both in
  // parallel via Promise.all guarantees we don't race the navigation.
  await Promise.all([
    page.waitForURL(/\/login/, { timeout: 10_000 }),
    page.evaluate(() => {
      const form = document.querySelector('form[action*="logout"]');
      if (form) form.submit();
    }),
  ]);
  await expect(page).toHaveURL(/\/login/);
});

// ── Registration page ─────────────────────────────────────────────────────────

test('registration page renders the create-account form', async ({ page }) => {
  await page.goto('/register');
  await expect(page.getByLabel('Email address')).toBeVisible();
  await expect(page.getByLabel('Password').first()).toBeVisible();
  await expect(page.getByRole('button', { name: /create account/i })).toBeVisible();
});
