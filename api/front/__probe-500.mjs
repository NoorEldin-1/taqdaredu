import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1400, height: 900 } });
await p.goto('http://localhost:8081/api/login', { waitUntil: 'domcontentloaded' });
await p.fill('input[name="email"]', 'temp-claude@tagdar.local');
await p.fill('input[name="password"]', 'TempClaude!2026');
await p.press('input[name="password"]', 'Enter');
await p.waitForLoadState('networkidle').catch(() => {});
for (let i = 1; i <= 3; i++) {
  const r = await p.goto('http://localhost:8081/api/admin/frontend_settings', { waitUntil: 'domcontentloaded' });
  console.log(`attempt ${i}: HTTP ${r.status()}`);
  if (r.status() !== 200) {
    const t = await p.evaluate(() => document.body.innerText.slice(0, 1400));
    console.log(t);
    break;
  }
}
await b.close();
