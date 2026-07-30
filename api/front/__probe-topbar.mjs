import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1500, height: 1000 } });
await p.goto('http://localhost:8081/api/login', { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
await p.goto('http://localhost:8081/api/admin/dashboard', { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1200);
console.log(await p.evaluate(() => {
  const out = [];
  const c = document.querySelector('.topnav-navbar > .container-fluid');
  const cs = getComputedStyle(c), cr = c.getBoundingClientRect();
  out.push(`container display=${cs.display} dir=${cs.direction} x=${Math.round(cr.x)} w=${Math.round(cr.width)} maxW=${cs.maxWidth} pad=${cs.paddingLeft}/${cs.paddingRight}`);
  for (const el of c.children) {
    const r = el.getBoundingClientRect(), s = getComputedStyle(el);
    out.push(`  <${el.tagName.toLowerCase()} class="${el.className}"> x=${Math.round(r.x)} w=${Math.round(r.width)} order=${s.order} mis=${s.marginInlineStart} disp=${s.display}`);
  }
  return out.join('\n');
}));
await b.close();
