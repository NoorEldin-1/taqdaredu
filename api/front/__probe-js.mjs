import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1400, height: 900 } });
await p.goto('http://localhost:8081/api/login', { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
await p.goto('http://localhost:8081/api/admin/themes', { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1500);
console.log(await p.evaluate(async () => {
  const raw = await (await fetch(location.href, { credentials: 'include' })).text();
  const inServerHtml = raw.includes('script-2.js');
  const el = document.querySelector('script[src*="script-2.js"]');
  const parent = el ? el.parentElement : null;
  return [
    `script-2.js present in SERVER html: ${inServerHtml}`,
    `parent of tag in DOM: <${parent ? parent.tagName : 'n/a'} id="${parent?.id}" class="${parent?.className}">`,
    `prev sibling: ${el?.previousElementSibling?.outerHTML?.slice(0, 120)}`,
  ].join('\n');
}));
await b.close();
