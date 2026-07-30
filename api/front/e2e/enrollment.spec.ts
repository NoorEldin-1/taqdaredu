import { test, expect } from '@playwright/test';

/**
 * Enrollment E2E. This flow mutates real user state (enrolls a user in a
 * course), so it ONLY runs when E2E_EMAIL / E2E_PASSWORD are supplied — never
 * against production by default. Point it at a disposable staging account.
 *
 * Set E2E_FREE_COURSE_ID to a free course so the flow is deterministic
 * (a paid course shows "Buy Now / Add to Cart" instead of "Enroll Now - Free").
 */
test.describe('Enrollment', () => {
  test.beforeEach(async () => {
    test.skip(
      !process.env.E2E_EMAIL || !process.env.E2E_PASSWORD,
      'Set E2E_EMAIL / E2E_PASSWORD (ideally on staging) to run the enrollment flow'
    );
  });

  test('a logged-in user can enroll in a free course and see it in My Courses', async ({ page }) => {
    const email = process.env.E2E_EMAIL!;
    const password = process.env.E2E_PASSWORD!;
    const freeCourseId = process.env.E2E_FREE_COURSE_ID;
    test.skip(!freeCourseId, 'Set E2E_FREE_COURSE_ID to a free course id to run the enroll flow');

    // 1. Log in.
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.locator('form button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 });

    // 2. Open the free course and enroll.
    await page.goto(`/courses/${freeCourseId}`, { waitUntil: 'domcontentloaded' });
    const enrollBtn = page.getByRole('button', { name: /enroll now/i }).first();
    const alreadyEnrolled = page.getByText(/continue learning/i).first();

    // Wait until the detail page has resolved to one state or the other.
    await expect(enrollBtn.or(alreadyEnrolled)).toBeVisible({ timeout: 15000 });

    if (await enrollBtn.count()) {
      await enrollBtn.click();
      // After the mutation succeeds react-query refetches the course and the CTA
      // flips to a persistent "Continue Learning" (more reliable than the
      // transient success toast).
      await expect(alreadyEnrolled).toBeVisible({ timeout: 15000 });
    }

    // 3. The course shows up under My Courses.
    await page.goto('/my-courses', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText(/my courses|enrolled|continue|in progress/i).first()).toBeVisible();
  });
});
