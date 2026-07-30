// Log into the admin panel and screenshot the pages named on argv.
// Usage: node admin-shot.mjs <label> <path> [<path> ...]
import { chromium } from 'playwright';

const BASE = 'http://localhost:8081/api';
const [label, ...paths] = process.argv.slice(2);

const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 1500, height: 1000 } });
const p = await ctx.newPage();

await p.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
// The page has a hidden header-search submit button that resolves first, so
// submit via the password field rather than by clicking a generic selector.
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});

for (const path of paths) {
  const name = path.replace(/[^a-z0-9]+/gi, '-');
  await p.goto(`${BASE}/${path}`, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1800);
  await p.screenshot({ path: `shot-${label}-${name}.png`, fullPage: true });

  // Computed colours for the elements the vendor theme styles for a LIGHT
  // sidebar — these are the ones that go invisible on navy.
  const probe = await p.evaluate(() => {
    const get = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return `${sel}: MISSING`;
      const s = getComputedStyle(el);
      return `${sel}: color=${s.color} bg=${s.backgroundColor} bgImg=${s.backgroundImage.slice(0, 50)}`;
    };
    const doc = document.documentElement;
    // Only count elements that are NOT inside an overflow scroll container —
    // content extending past such a container is scrollable by design, not an
    // overflow bug (the settings tab strip is a legitimate example).
    const scrolls = (el) => {
      const o = getComputedStyle(el).overflowX;
      return o === 'auto' || o === 'scroll' || o === 'hidden' || o === 'clip';
    };
    let minX = 0;
    document.querySelectorAll('*').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height || r.left >= minX) return;
      for (let a = el.parentElement; a && a !== doc; a = a.parentElement) if (scrolls(a)) return;
      minX = r.left;
    });
    return [
      get('.leftbar-user-name'),
      get('.side-nav-title'),
      get('.side-nav-second-level li a'),
      get('.side-nav-item.menuitem-active > .side-nav-link'),
      get('.left-side-menu'),
      `scrollW=${doc.scrollWidth} clientW=${doc.clientWidth} minLeftX=${Math.round(minX)}`,
    ].join('\n');
  });
  console.log(`--- ${path} ---\n${probe}\n`);
}

await b.close();
