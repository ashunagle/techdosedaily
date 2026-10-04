// Lab measurements: Lighthouse (mobile = simulated Moto G Power/slow 4G; desktop preset) per page.
import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';
import desktop from 'lighthouse/core/config/desktop-config.js';
import fs from 'fs';
const B = (process.env.BASE || 'http://127.0.0.1:8090').replace(/\/$/, '');
const pages = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const out = {};
const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH, chromeFlags: ['--headless=new', '--no-sandbox'] });
for (const [name, path] of Object.entries(pages)) {
  for (const form of ['mobile', 'desktop']) {
    const flags = { port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: ['performance'] };
    flags.extraHeaders = { 'X-TDD-Perf-Test': '1', ...(process.env.TDD_BASIC_AUTH ? { Authorization: 'Basic ' + Buffer.from(process.env.TDD_BASIC_AUTH).toString('base64') } : {}), ...(process.env.LH_HEADERS ? JSON.parse(process.env.LH_HEADERS) : {}) }; // Never counted as a Most Read view.
    const r = await lighthouse(B + path, flags, form === 'desktop' ? desktop : undefined);
    const a = r.lhr.audits;
    if (r.lhr.runtimeError || !a['network-requests'].details) { out[`${name}@${form}`] = { error: (r.lhr.runtimeError || {}).code || 'no-details' }; console.error(name, form, 'ERROR', JSON.stringify(r.lhr.runtimeError)); continue; }
    const items = a['network-requests'].details.items;
    const by = {};
    let total = 0;
    for (const it of items) { const t = it.resourceType || 'Other'; by[t] = (by[t] || 0) + (it.transferSize || 0); total += it.transferSize || 0; }
    const hosts = [...new Set(items.map((i) => new URL(i.url).host))];
    out[`${name}@${form}`] = {
      score: Math.round(r.lhr.categories.performance.score * 100),
      fcp: Math.round(a['first-contentful-paint'].numericValue), lcp: Math.round(a['largest-contentful-paint'].numericValue),
      cls: +a['cumulative-layout-shift'].numericValue.toFixed(3), tbt: Math.round(a['total-blocking-time'].numericValue),
      ttfb: Math.round(a['server-response-time'].numericValue), requests: items.length, kb: Math.round(total / 1024),
      byType: Object.fromEntries(Object.entries(by).map(([k, v]) => [k, Math.round(v / 1024)])), hosts,
      lcpElement: JSON.stringify(a['lcp-breakdown-insight']?.details?.items?.find?.((i) => i.type === 'node')?.snippet || a['lcp-breakdown-insight']?.details?.items?.[1]?.snippet || '').slice(0, 140),
    };
    console.error(name, form, out[`${name}@${form}`].score, out[`${name}@${form}`].lcp, out[`${name}@${form}`].kb + 'KB');
  }
}
await chrome.kill();
console.log(JSON.stringify(out, null, 1));
