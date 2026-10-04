/**
 * Pagination: real /page/N/ links always work (no JS needed). With JS, "Load more" appends the
 * next page in place, keeps the URL in step (history.replaceState) and, after three loads,
 * turns into a plain "Go to page N →" link (approved behaviour). Date-grouped feeds merge a day
 * that continues across pages. Focus moves to the first new story.
 */
(() => {
	const btn0 = document.querySelector('.tdd-pager__more[data-feed]');
	if (!btn0) return;
	const sel = btn0.dataset.feed;
	const feed = document.querySelector(sel);
	if (!feed) return;
	let loads = 0;

	const pageNum = (href) => (new URL(href, location.href).pathname.match(/\/page\/(\d+)/) || [0, ''])[1];

	const arm = (btn) => {
		const next = btn.closest('.tdd-pager').querySelector('[data-tdd-next]');
		if (!next) { btn.remove(); return; }
		if (loads >= 3) {
			const a = document.createElement('a');
			a.className = btn.className;
			a.href = next.href;
			a.textContent = 'Go to page ' + pageNum(next.href) + ' →';
			btn.replaceWith(a);
			return;
		}
		btn.hidden = false;
		btn.addEventListener('click', () => load(btn, next.href));
	};

	const load = async (btn, href) => {
		btn.setAttribute('aria-disabled', 'true');
		try {
			const html = await (await fetch(href, { credentials: 'same-origin' })).text();
			const doc = new DOMParser().parseFromString(html, 'text/html');
			const items = doc.querySelector(sel);
			if (!items) { location.href = href; return; }
			const incoming = Array.from(items.children);
			let first = null;
			const lastDate = Array.from(feed.querySelectorAll('.tdd-feed-date')).pop();
			const lastList = feed.lastElementChild;
			if (lastDate && incoming[0] && incoming[0].matches('.tdd-feed-date') && incoming[0].dataset.day === lastDate.dataset.day && incoming[1] && lastList && lastList.tagName === 'OL') {
				incoming.shift();
				const ol = incoming.shift();
				first = ol.firstElementChild;
				lastList.append(...ol.children);
			}
			if (!first && incoming[0]) {
				first = incoming[0].matches('.tdd-feed-date') ? incoming[1] && incoming[1].firstElementChild : incoming[0];
			}
			feed.append(...incoming);
			loads += 1;
			history.replaceState(null, '', href);
			const pager = btn.closest('.tdd-pager');
			const newPager = doc.querySelector('.tdd-pager');
			if (newPager) {
				pager.replaceWith(newPager);
				const nb = newPager.querySelector('.tdd-pager__more');
				if (nb) arm(nb);
			} else {
				pager.remove();
			}
			const link = first && (first.matches('a') ? first : first.querySelector('a'));
			if (link) link.focus();
		} catch (e) {
			location.href = href;
		}
	};

	arm(btn0);
})();
