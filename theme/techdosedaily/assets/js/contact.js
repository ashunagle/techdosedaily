/**
 * Contact form (approved ContactForm states): client validation with an error summary,
 * submitting state, success panel. Progressive enhancement over a normal POST
 * (admin-post.php → Post/Redirect/Get), which keeps working without JavaScript.
 * Talks only to /wp-json/tdd/v1/contact.
 */
(() => {
	const form = document.querySelector('form[data-tdd-contact]');
	if (!form) {
		const ok = document.querySelector('#form .tdd-cform__ok'); // No-JS success after the redirect.
		if (ok) ok.focus();
		return;
	}
	const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
	const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ESC[c]);
	let msgs = {};
	let labels = {};
	try { msgs = JSON.parse(form.dataset.msgs || '{}'); labels = JSON.parse(form.dataset.labels || '{}'); } catch (e) { /* server copy stays */ }
	const lc = (s) => s.charAt(0).toLowerCase() + s.slice(1);
	const summary = form.querySelector('[data-tdd-summary]');
	const button = form.querySelector('button[type="submit"]');
	const buttonLabel = button.textContent;
	const fields = ['name', 'email', 'topic', 'url', 'message'];
	const control = (k) => form.querySelector('#cf-' + k);
	form.noValidate = true; // Our messages replace the browser's once JS runs.

	const setError = (k, msg) => {
		const el = control(k);
		const wrap = el.closest('.tdd-field');
		const err = wrap.querySelector('.tdd-field__err');
		const hint = wrap.querySelector('.tdd-field__hint');
		const desc = [];
		if (hint) desc.push(hint.id);
		if (msg) {
			wrap.classList.add('is-error');
			el.setAttribute('aria-invalid', 'true');
			err.textContent = msg;
			err.hidden = false;
			desc.push(err.id);
		} else {
			wrap.classList.remove('is-error');
			el.removeAttribute('aria-invalid');
			err.textContent = '';
			err.hidden = true;
		}
		desc.length ? el.setAttribute('aria-describedby', desc.join(' ')) : el.removeAttribute('aria-describedby');
	};

	const showSummary = (html) => {
		summary.innerHTML = html;
		const box = summary.firstElementChild;
		if (box) box.focus();
	};

	const showErrors = (errors) => {
		fields.forEach((k) => setError(k, errors[k] || ''));
		const keys = fields.filter((k) => errors[k]);
		if (!keys.length) return;
		const items = keys.map((k) => `<li><a href="#cf-${k}">${esc(labels[k] || k)}: ${esc(lc(errors[k]))}</a></li>`).join('');
		const title = keys.length === 1 ? 'Check 1 field' : `Check ${keys.length} fields`;
		showSummary(`<div class="tdd-errsum" role="alert" tabindex="-1"><b>${esc(title)}</b><ul>${items}</ul></div>`);
	};

	const showFailure = (text) => {
		fields.forEach((k) => setError(k, ''));
		showSummary(`<div class="tdd-errsum" role="alert" tabindex="-1"><b>Your message wasn’t sent</b>${esc(text)}</div>`);
	};

	const validate = () => {
		const e = {};
		const v = (k) => control(k).value.trim();
		if (!v('name') || v('name').length > 100) e.name = msgs.name;
		if (!v('email') || !control('email').checkValidity() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) e.email = msgs.email;
		if (!v('topic')) e.topic = msgs.topic;
		if (v('url') && (!/^https?:\/\//i.test(v('url')) || !control('url').checkValidity())) e.url = msgs.url;
		if (!v('message') || v('message').length > 5000) e.message = msgs.message;
		return e;
	};

	// Summary links move focus to the field (not just scroll).
	summary.addEventListener('click', (ev) => {
		const a = ev.target.closest('a[href^="#cf-"]');
		if (!a) return;
		const el = document.getElementById(a.hash.slice(1));
		if (el) { ev.preventDefault(); el.focus(); }
	});

	// Clear a field's error as soon as it is corrected.
	fields.forEach((k) => control(k).addEventListener('input', () => {
		if (control(k).closest('.tdd-field').classList.contains('is-error') && !validate()[k]) setError(k, '');
	}));

	// Route links preselect the topic.
	// Route links preselect the topic without reloading the page (and without losing typed text).
	document.querySelectorAll('[data-tdd-topic]').forEach((a) => a.addEventListener('click', (ev) => {
		const sel = control('topic');
		if (!sel.querySelector(`option[value="${a.dataset.tddTopic}"]`)) return;
		ev.preventDefault();
		sel.value = a.dataset.tddTopic;
		try { history.replaceState(null, '', a.href); } catch (e) { /* ignore */ }
		const first = ['name', 'email', 'message'].map(control).find((el) => !el.value.trim()) || sel;
		first.focus();
	}));

	let busy = false;
	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		if (busy) return;
		const errors = validate();
		if (Object.keys(errors).length) { showErrors(errors); return; }
		summary.innerHTML = '';
		busy = true;
		button.setAttribute('aria-disabled', 'true');
		button.textContent = 'Sending…';
		const data = {};
		new FormData(form).forEach((val, key) => { data[key] = val; });
		try {
			const res = await fetch(form.dataset.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
			const out = await res.json().catch(() => ({}));
			if (out.state === 'sent') {
				const wrap = document.createElement('div');
				wrap.id = 'form';
				wrap.innerHTML = `<div class="tdd-cform__ok" role="status" tabindex="-1"><b>Message sent</b>${esc(out.message || '')} <a href="${esc(form.dataset.home || '/')}">Back to Tech Dose Daily →</a></div>`;
				form.replaceWith(wrap);
				wrap.firstElementChild.focus();
				return;
			}
			if (out.state === 'invalid' && out.errors) { showErrors(out.errors); return; }
			showFailure(out.message || 'Please try again in a moment.');
		} catch (e) {
			showFailure('Please check your connection and try again.');
		} finally {
			busy = false;
			if (button.isConnected) { button.removeAttribute('aria-disabled'); button.textContent = buttonLabel; }
		}
	});

	// After a no-JS round trip, move focus to the summary or the success panel.
	const flash = form.querySelector('.tdd-errsum');
	if (flash) flash.focus();
})();
