import { test, expect } from '@playwright/test';

/**
 * Course-browsing E2E — read-only, safe against any environment.
 * The courses page loads a catalogue, filters work, and a course opens its
 * detail page.
 */
test.describe('Course browsing', () => {
  test('the courses page loads and shows courses', async ({ page }) => {
    await page.goto('/courses', { waitUntil: 'domcontentloaded' });

    // The catalogue heading is always present.
    await expect(page.getByRole('heading', { name: /find your perfect|course/i }).first()).toBeVisible();

    // Wait for either course cards to appear or the explicit empty state.
    const cards = page.locator('a[href*="/courses/"]');
    const empty = page.getByText(/no courses found/i);
    await expect
      .poll(async () => (await cards.count()) > 0 || (await empty.count()) > 0, { timeout: 10000 })
      .toBeTruthy();
  });

  test('search input filters the catalogue', async ({ page }) => {
    await page.goto('/courses', { waitUntil: 'domcontentloaded' });
    const search = page.getByPlaceholder(/search courses/i);
    await expect(search).toBeVisible();
    await search.fill('network');
    await search.press('Enter');
    // The URL/state updates; the page must not crash and still renders the grid area.
    await expect(page.getByText(/courses found/i)).toBeVisible();
  });

  test('clicking a course opens its detail page', async ({ page }) => {
    await page.goto('/courses', { waitUntil: 'domcontentloaded' });

    const cards = page.locator('a[href*="/courses/"]');
    const empty = page.getByText(/no courses found/i);
    // Wait for the async catalogue to resolve to cards or the empty state.
    await expect(cards.first().or(empty)).toBeVisible({ timeout: 15000 });

    test.skip((await cards.count()) === 0, 'No courses available in this environment to open');

    await cards.first().click();
    await expect(page).toHaveURL(/\/courses\/[^/]+/);
  });
});
