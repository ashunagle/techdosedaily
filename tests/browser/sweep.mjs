// Responsive sweep (VISUAL-QA checks) with the locally installed Chrome — no browser download.
//   BASE=https://staging.techdosedaily.com TDD_BASIC_AUTH=user:pass node sweep.mjs [out-dir]
// Per page × width (360 390 430 768 1024 1280 1440 1920): horizontal overflow, header height (68 / 56 below 768),
// exactly one <h1>, no duplicate ids, every image has alt, no console errors / page errors / failed requests,
// no third-party requests. Full-page screenshots at 390 and 1440 for comparison with 03-Approved.
// Lab traffic: X-TDD-Perf-Test on every request (never counted in Most Read).
import { chromium } from 'playwright-core';
import fs from 'fs';
import path from 'path';

const BASE = (process.env.BASE || 'http://127.0.0.1:8090').replace(/\/$/, '');
const HOST = new URL(BASE).host;
const AUTH = process.env.TDD_BASIC_AUTH || '';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const OUT = process.argv[2] || path.join('..', 'staging', 'out', 'sweep-' + new Date().toISOString().replace(/[:.]/g, '').slice(0, 15) + 'Z');
const WIDTHS = [360, 390, 430, 768, 1024, 1280, 1440, 1920];
const SHOTS = [390, 1440];
const PAGES = {
  home: '/', latest: '/latest/', section: '/ai/', 'section-p2': '/ai/page/2/', topic: '/topic/ai-agents/', 'story-type': '/story-type/analysis/',
  author: '/author/priya-sample/', search: '/?s=AI', 404: '/no-such-page-xyz/',
  'article-analysis': '/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/',
  'article-explainer': '/ai/agents-that-use-a-computer-are-moving-from-demos-to-daily-work/',
  'article-guide': '/guides/run-a-local-ai-coding-assistant-without-sending-code-off-your-machine/',
  'article-review': '/tech/a-week-with-a-lightweight-laptop-built-around-an-on-device-ai-chip/',
  'article-sponsored': '/developer/sample-sponsored-explainer-what-a-managed-vector-database-does/',
  'article-nohero': '/software/sample-a-short-news-story-with-no-image-editor-or-topics/',
  // Static pages (about, contact, newsletter, editorial-standards, advertise) are drafts on staging until the copy exists.
};
fs.mkdirSync(OUT, { recursive: true });

const [user, ...pw] = AUTH.split(':');
const browser = await chromium.launch({ executablePath: CHROME, headless: true });
const ctx = await browser.newContext({
  httpCredentials: AUTH ? { username: user, password: pw.join(':'), origin: BASE } : undefined,
  extraHTTPHeaders: { 'X-TDD-Perf-Test': '1' },
});
const results = [];
let fails = 0;
for (const [name, p] of Object.entries(PAGES)) {
  for (const w of WIDTHS) {
    const page = await ctx.newPage();
    await page.setViewportSize({ width: w, height: 900 });
    const errors = [], failed = [], third = new Set();
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text().slice(0, 200)));
    page.on('pageerror', (e) => errors.push('pageerror: ' + String(e).slice(0, 200)));
    page.on('requestfailed', (r) => failed.push(r.url().slice(0, 120) + ' ' + (r.failure()?.errorText || '')));
    page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).host === HOST && r.url() !== BASE + p) failed.push(`${r.status()} ${r.url().slice(0, 120)}`); });
    page.on('request', (r) => { const h = new URL(r.url()).host; if (h && h !== HOST && !r.url().startsWith('data:')) third.add(h); });
    const resp = await page.goto(BASE + p, { waitUntil: 'networkidle', timeout: 60000 });
    await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 50)); } window.scrollTo(0, 0); });
    await page.waitForTimeout(300);
    const m = await page.evaluate(() => {
      const ids = [...document.querySelectorAll('[id]')].map((e) => e.id);
      const dup = [...new Set(ids.filter((id, i) => ids.indexOf(id) !== i))];
      const hdr = document.querySelector('.tdd-header');
      return {
        scrollWidth: document.documentElement.scrollWidth,
        h1: document.querySelectorAll('h1').length,
        dupIds: dup,
        noAlt: [...document.images].filter((i) => !i.hasAttribute('alt')).map((i) => (i.currentSrc || i.src).slice(-60)),
        headerH: hdr ? Math.round(hdr.getBoundingClientRect().height) : null,
      };
    });
    const expectH = w < 768 ? 56 : 68;
    const issues = [];
    if (m.scrollWidth > w) issues.push(`horizontal overflow (${m.scrollWidth}px)`);
    if (m.h1 !== 1) issues.push(`${m.h1} <h1>`);
    if (m.dupIds.length) issues.push('duplicate ids ' + m.dupIds.join(','));
    if (m.noAlt.length) issues.push('images without alt ' + m.noAlt.join(','));
    if (m.headerH !== null && m.headerH !== expectH) issues.push(`header ${m.headerH}px (want ${expectH})`);
    if (errors.length) issues.push('console: ' + errors.join(' | '));
    if (failed.length) issues.push('failed requests: ' + failed.join(' | '));
    if (third.size) issues.push('third-party: ' + [...third].join(','));
    if (SHOTS.includes(w)) await page.screenshot({ path: path.join(OUT, `${name}-${w}.png`), fullPage: true });
    results.push({ page: name, width: w, status: resp?.status(), ...m, issues });
    fails += issues.length ? 1 : 0;
    console.log(`${issues.length ? 'FAIL' : 'PASS'} ${name} @${w} (HTTP ${resp?.status()})${issues.length ? ' — ' + issues.join('; ') : ''}`);
    await page.close();
  }
}
await browser.close();
fs.writeFileSync(path.join(OUT, 'sweep.json'), JSON.stringify(results, null, 1));
console.log(`\n${results.length - fails} of ${results.length} page×width checks clean → ${OUT}`);
process.exit(fails ? 1 : 0);
