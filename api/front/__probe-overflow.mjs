import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1500, height: 1000 } });
await p.goto('http://localhost:8081/api/login', { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
await p.goto('http://localhost:8081/api/' + process.argv[2], { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1500);
console.log(await p.evaluate(() => {
  const bad = [];
  document.querySelectorAll('*').forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.width && r.height && r.left < -1) {
      // report only the outermost offenders, not every descendant
      if (!el.parentElement || el.parentElement.getBoundingClientRect().left >= -1) {
        const s = getComputedStyle(el);
        bad.push(`<${el.tagName.toLowerCase()} class="${String(el.className).slice(0,70)}" id="${el.id}"> x=${Math.round(r.left)} w=${Math.round(r.width)} pos=${s.position} float=${s.float} disp=${s.display}`);
      }
    }
  });
  return bad.slice(0, 15).join('\n') || 'no overflow';
}));
await b.close();
