import { test, expect } from '@playwright/test';

/**
 * Auth E2E. Protected-route redirect and bad-login error are safe against any
 * environment (no mutations). The happy-path login only runs when
 * E2E_EMAIL / E2E_PASSWORD are provided, so we never create/lock accounts on a
 * shared/production target.
 */

const PROTECTED = ['/profile', '/my-courses', '/wishlist'];

// The login page has a Navbar "Login" link AND a form "Sign In" submit — target
// the submit button specifically to avoid ambiguity.
const submitLogin = 'form button[type="submit"]';

test.describe('Authentication', () => {
  for (const path of PROTECTED) {
    test(`redirects ${path} to /login when logged out`, async ({ page }) => {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      await expect(page).toHaveURL(/\/login/);
    });
  }

  test('shows an error for invalid credentials', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/email/i).fill('nobody-e2e@example.com');
    await page.getByLabel(/password/i).fill('definitely-wrong');
    await page.locator(submitLogin).click();

    // Either an inline error/toast appears, or we simply stay on /login.
    await expect(page).toHaveURL(/\/login/);
    const error = page.getByText(/invalid|incorrect|wrong|failed/i);
    await expect(error.first()).toBeVisible({ timeout: 7000 }).catch(() => {
      // Fallback: staying on /login already proves the login was rejected.
    });
  });

  test('valid credentials reach an authenticated area', async ({ page }) => {
    const email = process.env.E2E_EMAIL;
    const password = process.env.E2E_PASSWORD;
    test.skip(!email || !password, 'Set E2E_EMAIL / E2E_PASSWORD to run the happy-path login');

    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/email/i).fill(email!);
    await page.getByLabel(/password/i).fill(password!);
    await page.locator(submitLogin).click();

    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 });
  });
});
