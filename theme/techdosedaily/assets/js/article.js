/**
 * Article: share controls and the rail TOC position.
 * - Copy link (Clipboard API) and Share (Web Share API) are revealed only when supported;
 *   Email is a plain link and always works.
 * - TOC: aria-current follows the section being read. Never moves focus or scroll.
 */
(function () {
	const live = document.createElement('div');
	live.className = 'screen-reader-text';
	live.setAttribute('role', 'status');
	document.body.appendChild(live);

	if (navigator.clipboard && window.isSecureContext !== false) {
		document.querySelectorAll('[data-tdd-copy]').forEach((btn) => {
			btn.hidden = false;
			btn.addEventListener('click', async () => {
				try {
					await navigator.clipboard.writeText(btn.dataset.tddCopy);
					live.textContent = 'Link copied';
					const txt = btn.lastChild && btn.lastChild.nodeType === 3 ? btn.lastChild : null;
					if (txt) {
						const old = txt.textContent;
						txt.textContent = 'Link copied';
						setTimeout(() => { txt.textContent = old; }, 2000);
					}
				} catch (e) {
					live.textContent = 'Could not copy the link';
				}
			});
		});
	}
	if (navigator.share) {
		document.querySelectorAll('[data-tdd-share]').forEach((btn) => {
			btn.hidden = false;
			btn.addEventListener('click', () => {
				navigator.share({ title: btn.dataset.title, url: btn.dataset.tddShare }).catch(() => {});
			});
		});
	}

	const toc = document.querySelector('[data-tdd-toc]');
	if (!toc) return;
	const links = Array.from(toc.querySelectorAll('a[href^="#"]'));
	const targets = links.map((a) => document.getElementById(decodeURIComponent(a.hash.slice(1)))).filter(Boolean);
	let ticking = false;
	const update = () => {
		ticking = false;
		const line = window.innerHeight * 0.3;
		let current = targets[0];
		targets.forEach((t) => { if (t.getBoundingClientRect().top < line) current = t; });
		links.forEach((a) => (current && a.hash.slice(1) === current.id ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current')));
	};
	window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
	update();
})();
