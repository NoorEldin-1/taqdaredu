import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1500, height: 1000 } });
await p.goto('http://localhost:8081/api/login', { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
await p.goto('http://localhost:8081/api/admin/frontend_settings', { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1500);
console.log(await p.evaluate(() => {
  const ul = document.querySelector('.nav-pills.nav-justified');
  const out = [];
  const su = getComputedStyle(ul);
  out.push(`UL w=${Math.round(ul.getBoundingClientRect().width)} flexWrap=${su.flexWrap} minW=${su.minWidth} width=${su.width} items=${ul.children.length}`);
  const first = ul.children[0];
  if (first) {
    const sf = getComputedStyle(first);
    out.push(`  item0 flex=${sf.flex} minW=${sf.minWidth} w=${Math.round(first.getBoundingClientRect().width)} ws=${sf.whiteSpace} text="${first.textContent.trim().slice(0,25)}"`);
  }
  let el = ul.parentElement, i = 0;
  while (el && i++ < 8) {
    const s = getComputedStyle(el), r = el.getBoundingClientRect();
    out.push(`ANC${i} <${el.tagName.toLowerCase()} class="${String(el.className).slice(0,55)}"> x=${Math.round(r.left)} w=${Math.round(r.width)} disp=${s.display} width=${s.width} minW=${s.minWidth} ovf=${s.overflowX}`);
    el = el.parentElement;
  }
  return out.join('\n');
}));
await b.close();
