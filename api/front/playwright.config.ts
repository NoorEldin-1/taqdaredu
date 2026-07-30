import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for the My-Communication Academy SPA.
 *
 * baseURL defaults to the Vite dev server (`npm run dev` → :8080), which proxies
 * /api_* to the local backend. Override with PLAYWRIGHT_BASE_URL to target a
 * staging/live environment, e.g.:
 *   PLAYWRIGHT_BASE_URL=https://my-communication.uk npx playwright test
 *
 * Before the first run: `npx playwright install chromium`.
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080';

export default defineConfig({
  testDir: './e2e',
  // Vite dev compiles routes on demand — the first hit of a route can be slow,
  // so allow generous timeouts locally.
  timeout: 90_000,
  expect: { timeout: 10_000 },
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL,
    navigationTimeout: 60_000,
    // Don't block on every sub-resource (images/fonts); the SPA is interactive
    // once the DOM is ready.
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  // Auto-start the dev server locally unless we're pointed at a remote target.
  webServer: process.env.PLAYWRIGHT_BASE_URL
    ? undefined
    : {
        command: 'npm run dev',
        url: 'http://localhost:8080',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
      },
});
