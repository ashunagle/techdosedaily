"""Responsive screenshots + overflow check. Usage: python3 tests/shots.py <url> <outprefix> [widths...]"""
import asyncio, sys, json
from playwright.async_api import async_playwright
URL, OUT = sys.argv[1], sys.argv[2]
WIDTHS = [int(w) for w in sys.argv[3:]] or [360, 390, 430, 768, 1024, 1280, 1440, 1920]
async def main():
    async with async_playwright() as p:
        b = await p.chromium.launch()
        res = {}
        for w in WIDTHS:
            pg = await b.new_page(viewport={'width': w, 'height': 900}, device_scale_factor=1)
            await pg.goto(URL, wait_until='networkidle')
            await pg.evaluate("(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,60))}window.scrollTo(0,0)})()")
            await pg.wait_for_timeout(500)
            sw = await pg.evaluate('document.documentElement.scrollWidth')
            hh = await pg.evaluate("(()=>{const h=document.querySelector('.tdd-header');return h?Math.round(h.getBoundingClientRect().height):null})()")
            vis = await pg.evaluate("""(()=>{const q=s=>{const e=document.querySelector(s);return e?getComputedStyle(e).display:'-'};return {nav:q('.tdd-nav'),sub:q('.tdd-header__subscribe'),menu:q('.tdd-header__menu'),fd:q('.tdd-footer--desktop'),fm:q('.tdd-footer--mobile')}})()""")
            await pg.screenshot(path=f'{OUT}-{w}.png', full_page=True)
            res[w] = {'scrollWidth': sw, 'overflow': sw > w, 'headerH': hh, **vis}
            await pg.close()
        await b.close()
        print(json.dumps(res, indent=1))
asyncio.run(main())
