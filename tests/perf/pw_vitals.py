#!/usr/bin/env python3
"""Core Web Vitals (lab) for pages Lighthouse cannot load (404 status): Playwright + CDP throttling
matching Lighthouse's mobile preset (150 ms RTT, 1.6 Mbps down, 4x CPU) and an unthrottled desktop run.
Sends X-TDD-Perf-Test so nothing is counted as a Most Read view. Usage: pw_vitals.py /path [/path…]"""
import asyncio, json, sys
from playwright.async_api import async_playwright
BASE = __import__('os').environ.get('BASE', 'http://127.0.0.1:8090').rstrip('/')
JS = """() => new Promise(res => { const o = { lcp: 0, cls: 0 };
  new PerformanceObserver(l => { for (const e of l.getEntries()) o.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) o.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
  setTimeout(() => { const n = performance.getEntriesByType('navigation')[0]; const r = performance.getEntriesByType('resource');
    o.fcp = (performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0; o.ttfb = n.responseStart;
    o.kb = Math.round((n.transferSize + r.reduce((a, x) => a + x.transferSize, 0)) / 1024); o.requests = r.length + 1;
    o.hosts = [...new Set(r.map(x => new URL(x.name).host))]; res(o); }, 3000); })"""
async def run(path, mobile, headers):
    async with async_playwright() as p:
        b = await p.chromium.launch()
        c = await b.new_context(viewport={'width': 412, 'height': 823} if mobile else {'width': 1350, 'height': 940}, device_scale_factor=1.75 if mobile else 1, is_mobile=mobile, extra_http_headers={'X-TDD-Perf-Test': '1', **headers})
        pg = await c.new_page()
        cdp = await c.new_cdp_session(pg)
        await cdp.send('Network.enable'); await cdp.send('Network.setCacheDisabled', {'cacheDisabled': True})
        if mobile:
            await cdp.send('Network.emulateNetworkConditions', {'offline': False, 'latency': 150, 'downloadThroughput': 1.6 * 1024 * 1024 / 8, 'uploadThroughput': 750 * 1024 / 8})
            await cdp.send('Emulation.setCPUThrottlingRate', {'rate': 4})
        r = await pg.goto(BASE + path, wait_until='load')
        o = await pg.evaluate(JS)
        o['status'] = r.status
        await b.close()
        return {k: (round(v) if isinstance(v, float) and k != 'cls' else round(v, 3) if k == 'cls' else v) for k, v in o.items()}
async def main():
    out = {}
    for path in sys.argv[1:]:
        for mode, headers in (('uncached', {'X-Cache-Bypass': '1'}), ('cached', {})):
            for form in ('mobile', 'desktop'):
                out[f'{path}@{form}@{mode}'] = await run(path, form == 'mobile', headers)
                print(path, form, mode, out[f'{path}@{form}@{mode}'], file=sys.stderr)
    print(json.dumps(out, indent=1))
asyncio.run(main())
