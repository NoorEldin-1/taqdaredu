import { test, expect, request as playwrightRequest } from '@playwright/test';

/**
 * SEO server-rendering E2E.
 *
 * When a crawler UA hits a public path, the root .htaccess routes the request to
 * seo-router.php, which returns fully server-rendered HTML (with <title> and
 * <meta name="description">) instead of the JS SPA shell.
 *
 * This needs the PHP backend, so it targets SEO_BASE_URL (falls back to
 * PLAYWRIGHT_BASE_URL). It skips against the Vite dev server (:8080), which has
 * no PHP. To run it against the deployed site:
 *   SEO_BASE_URL=https://my-communication.uk npx playwright test seo
 */
const GOOGLEBOT =
  'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

const seoBase = process.env.SEO_BASE_URL || process.env.PLAYWRIGHT_BASE_URL || '';

test.describe('SEO server-rendering (seo-router.php)', () => {
  test.skip(
    !seoBase || seoBase.includes(':8080'),
    'SEO rendering requires the PHP backend — set SEO_BASE_URL to a deployed origin'
  );

  const paths = ['/', '/courses'];

  for (const path of paths) {
    test(`crawler request for ${path} returns rendered HTML with title + meta description`, async () => {
      const ctx = await playwrightRequest.newContext({
        baseURL: seoBase,
        extraHTTPHeaders: { 'User-Agent': GOOGLEBOT },
      });

      const res = await ctx.get(path);
      expect(res.ok(), `expected 2xx for ${path}, got ${res.status()}`).toBeTruthy();

      const html = await res.text();
      expect(html).toMatch(/<title>[^<]+<\/title>/i);
      expect(html).toMatch(/<meta[^>]+name=["']description["'][^>]+content=["'][^"']+["']/i);

      await ctx.dispose();
    });
  }

  test('crawler gets an og:title for social sharing on the home page', async () => {
    const ctx = await playwrightRequest.newContext({
      baseURL: seoBase,
      extraHTTPHeaders: { 'User-Agent': GOOGLEBOT },
    });
    const res = await ctx.get('/');
    const html = await res.text();
    expect(html).toMatch(/<meta[^>]+property=["']og:title["']/i);
    await ctx.dispose();
  });
});
