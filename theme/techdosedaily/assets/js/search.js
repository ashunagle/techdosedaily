/**
 * Search: clear button, filters auto-apply (desktop), mobile filter bottom sheet
 * (focus moves into it, Esc / close button return focus, scroll locked while open).
 * Without JS the filters form has a submit button and the mobile "Filters" link jumps to it.
 */
(() => {
	const field = document.querySelector('.tdd-search');
	if (field) {
		const input = field.querySelector('input[type="search"]');
		const clear = field.querySelector('.tdd-search__clear');
		const sync = () => { clear.hidden = !input.value; };
		input.addEventListener('input', sync);
		clear.addEventListener('click', () => { input.value = ''; sync(); input.focus(); });
	}

	const form = document.querySelector('[data-tdd-filters]');
	if (!form) return;
	const mq = window.matchMedia('(max-width: 767px)');
	// Keep URLs clean: "Any time" (empty value) is not sent.
	form.addEventListener('submit', () => form.querySelectorAll('input[value=""]').forEach((i) => { i.disabled = true; }));
	form.addEventListener('change', () => { if (!mq.matches) form.requestSubmit(); });

	const open = document.querySelector('[data-tdd-filters-open]');
	const close = form.querySelector('.tdd-filters__close');
	const sheet = form.closest('.sp-filters');
	if (!open || !sheet) return;
	close.hidden = false;
	let last = null;
	const onKey = (e) => {
		if (e.key === 'Escape') hide();
		if (e.key === 'Tab') {
			const f = Array.from(form.querySelectorAll('a[href], button:not([hidden]), input')).filter((el) => el.offsetParent !== null);
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		}
	};
	const show = () => {
		last = document.activeElement;
		sheet.classList.add('is-open');
		sheet.setAttribute('role', 'dialog');
		sheet.setAttribute('aria-modal', 'true');
		open.setAttribute('aria-expanded', 'true');
		document.documentElement.classList.add('tdd-menu-open');
		document.body.classList.add('tdd-menu-open');
		form.querySelector('h2').setAttribute('tabindex', '-1');
		form.querySelector('h2').focus();
		document.addEventListener('keydown', onKey);
	};
	const hide = () => {
		sheet.classList.remove('is-open');
		sheet.removeAttribute('role');
		sheet.removeAttribute('aria-modal');
		open.setAttribute('aria-expanded', 'false');
		document.documentElement.classList.remove('tdd-menu-open');
		document.body.classList.remove('tdd-menu-open');
		document.removeEventListener('keydown', onKey);
		if (last) last.focus();
	};
	open.addEventListener('click', (e) => { if (mq.matches) { e.preventDefault(); show(); } });
	close.addEventListener('click', hide);
	sheet.addEventListener('click', (e) => { if (e.target === sheet) hide(); });
	document.documentElement.classList.add('tdd-js-search');
})();
