// Walk every page reachable from the admin sidebar and report, per page: real
// horizontal overflow, JS console errors, any element left with a vendor colour
// that fails on the navy rail, and remaining Latin-script UI text.
import { chromium } from 'playwright';

const BASE = 'http://localhost:8081/api';
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 1500, height: 1000 } });
const p = await ctx.newPage();

const errors = [];
p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text().slice(0, 100)); });
p.on('pageerror', (e) => errors.push('PAGEERROR ' + e.message.slice(0, 100)));

await p.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
await p.goto(`${BASE}/admin/dashboard`, { waitUntil: 'domcontentloaded' });

// Real routes, taken from the sidebar itself rather than guessed.
const links = await p.evaluate(() =>
  [...new Set([...document.querySelectorAll('.left-side-menu a[href]')]
    .map((a) => a.getAttribute('href'))
    .filter((h) => h && !h.startsWith('javascript') && h.includes('/admin/')))]);
console.log(`discovered ${links.length} sidebar routes\n`);

for (const href of links) {
  errors.length = 0;
  const label = href.replace(/^.*\/admin\//, 'admin/');
  let r, status;
  try {
    const resp = await p.goto(href, { waitUntil: 'domcontentloaded' });
    status = resp ? resp.status() : 0;
    await p.waitForTimeout(1200);
    r = await p.evaluate(() => {
      const doc = document.documentElement;
      const scrolls = (el) => ['auto', 'scroll', 'hidden', 'clip'].includes(getComputedStyle(el).overflowX);
      let minX = 0;
      document.querySelectorAll('*').forEach((el) => {
        const bb = el.getBoundingClientRect();
        if (!bb.width || !bb.height || bb.left >= minX) return;
        for (let a = el.parentElement; a && a !== doc; a = a.parentElement) if (scrolls(a)) return;
        minX = bb.left;
      });
      const stale = new Set();
      document.querySelectorAll('.left-side-menu *').forEach((el) => {
        const c = getComputedStyle(el).color;
        if (/^rgb\((49, 58, 70|108, 117, 125|114, 124, 245)\)/.test(c)) stale.add(c);
      });
      const latin = new Set();
      document.querySelectorAll('.content-page button, .content-page .btn, .content-page th, .content-page label, .card-title, .header-title, .page-title, .nav-link').forEach((el) => {
        const t = el.textContent.trim();
        if (/^[A-Za-z][A-Za-z ()/&.-]{2,}$/.test(t)) latin.add(t.slice(0, 30));
      });
      return {
        isAdmin: !!document.querySelector('.left-side-menu'),
        ovf: Math.round(minX),
        docOvf: doc.scrollWidth - doc.clientWidth,
        stale: [...stale],
        latin: [...latin].slice(0, 5),
      };
    });
  } catch (e) {
    console.log(`${label.padEnd(36)} FAILED ${String(e).slice(0, 50)}`);
    continue;
  }
  const f = [];
  if (status !== 200) f.push(`HTTP ${status}`);
  if (!r.isAdmin) f.push('NOT-ADMIN-LAYOUT');
  if (r.ovf < -1 || r.docOvf > 1) f.push(`OVERFLOW minX=${r.ovf} doc=${r.docOvf}`);
  if (r.stale.length) f.push(`STALE ${r.stale.join(',')}`);
  if (r.latin.length) f.push(`EN ${r.latin.join(' | ')}`);
  if (errors.length) f.push(`JS ${[...new Set(errors)].slice(0, 1).join('')}`);
  console.log(`${label.padEnd(36)} ${f.length ? f.join('  ·  ') : 'ok'}`);
}
await b.close();
