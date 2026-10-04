/* Header: compact-on-scroll + accessible menu drawer. Progressive enhancement:
   without JS the menu button is a link to the footer navigation. */
(() => {
	const header = document.querySelector('[data-tdd-header]');
	if (!header) return;

	// Compact header after the first 120px of scroll (no layout shift: header is sticky, height change is within it).
	const sentinel = document.createElement('div');
	sentinel.style.cssText = 'position:absolute;top:120px;height:1px;width:1px;pointer-events:none';
	document.body.prepend(sentinel);
	new IntersectionObserver(([e]) => header.classList.toggle('is-compact', !e.isIntersecting)).observe(sentinel);

	// Content-fit navigation: from 1200px the full section nav shows only if it actually fits
	// (other languages/fonts may be wider). Otherwise it collapses into the menu drawer.
	const nav = header.querySelector('.tdd-nav');
	const wide = matchMedia('(min-width: 1200px)');
	const fit = () => {
		if (!nav) return;
		header.classList.remove('is-nav-overflow');
		if (wide.matches && nav.scrollWidth > nav.clientWidth + 1) header.classList.add('is-nav-overflow');
	};
	if (nav) {
		new ResizeObserver(fit).observe(header);
		document.fonts && document.fonts.ready.then(fit);
	}

	const btn = header.querySelector('.tdd-header__menu');
	const drawer = document.getElementById('tdd-menu');
	if (!btn || !drawer) return;
	const close = drawer.querySelector('.tdd-drawer__close');
	const focusables = () => [...drawer.querySelectorAll('a[href], button:not([disabled])')];

	const open = () => {
		drawer.hidden = false;
		btn.setAttribute('aria-expanded', 'true');
		document.body.classList.add('tdd-menu-open');
		(focusables()[1] || close).focus();
		document.addEventListener('keydown', onKey);
	};
	const shut = () => {
		drawer.hidden = true;
		btn.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('tdd-menu-open');
		document.removeEventListener('keydown', onKey);
		btn.focus();
	};
	const onKey = (e) => {
		if (e.key === 'Escape') { e.preventDefault(); shut(); return; }
		if (e.key !== 'Tab') return;
		const f = focusables(); const first = f[0]; const last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	};
	btn.addEventListener('click', (e) => { e.preventDefault(); open(); });
	close.addEventListener('click', shut);
	wide.addEventListener('change', (m) => { if (m.matches && !drawer.hidden && !header.classList.contains('is-nav-overflow')) shut(); });
})();
