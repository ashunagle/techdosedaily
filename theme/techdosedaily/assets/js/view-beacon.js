/* Most Read: one cookieless view ping per article page (TechDoseDaily Core stores counts only).
   Not sent by automated browsers (navigator.webdriver: Lighthouse runs, Playwright/Selenium tests),
   nor while a page is only prerendered/prefetched — it waits until a person actually sees it. */
(() => {
	const el = document.querySelector('[data-tdd-view]');
	if (!el || !navigator.sendBeacon || navigator.webdriver) return;
	const send = () => navigator.sendBeacon(el.dataset.endpoint, new Blob([JSON.stringify({ id: Number(el.dataset.tddView) })], { type: 'application/json' }));
	const start = () => {
		if (document.visibilityState === 'visible') setTimeout(send, 3000);
		else document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && setTimeout(send, 3000), { once: true });
	};
	if (document.prerendering) document.addEventListener('prerenderingchange', start, { once: true });
	else start();
})();
