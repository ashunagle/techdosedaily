/* Newsletter sign-up: progressive enhancement over a normal form POST.
   States (approved NewsletterFormStates): invalid · already · submitting · success (+ unavailable/error).
   Talks only to /wp-json/tdd/v1/subscribe — never to a provider directly. */
(() => {
	const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
	const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ESC[c]);

	document.querySelectorAll('[data-tdd-newsletter] form[data-rest]').forEach((form) => {
		const input = form.querySelector('input[type="email"]');
		const button = form.querySelector('button[type="submit"]');
		const status = form.querySelector('.tdd-nl-status');
		const label = button.textContent;
		const errId = input.id + '-err';

		const clear = () => {
			form.classList.remove('is-error');
			input.removeAttribute('aria-invalid');
			input.setAttribute('aria-describedby', input.id + '-fine');
			status.innerHTML = '';
		};
		const note = (msg, isError) => {
			status.innerHTML = `<p class="tdd-nlsign__note" id="${errId}">${esc(msg)}</p>`;
			if (isError) {
				form.classList.add('is-error');
				input.setAttribute('aria-invalid', 'true');
				input.setAttribute('aria-describedby', `${errId} ${input.id}-fine`);
				input.focus();
			}
		};

		input.addEventListener('input', () => form.classList.contains('is-error') && clear());

		let busy = false;
		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			if (busy) return;
			clear();
			if (!input.value.trim() || !input.checkValidity()) {
				note('Enter an email address like name@example.com', true);
				return;
			}
			// aria-disabled (not disabled) keeps keyboard focus on the button while submitting.
			busy = true;
			button.setAttribute('aria-disabled', 'true');
			button.textContent = 'Subscribing…';
			try {
				const res = await fetch(form.dataset.rest, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						email: input.value.trim(),
						tdd_token: form.querySelector('[name="tdd_token"]').value,
						tdd_hp: form.querySelector('[name="tdd_hp"]').value,
					}),
				});
				const data = await res.json().catch(() => ({}));
				const state = data.state || 'error';
				if (state === 'subscribed' || state === 'pending') {
					const panel = document.createElement('div');
					panel.className = 'tdd-nlok';
					panel.setAttribute('role', 'status');
					panel.tabIndex = -1;
					// Title comes from Core: "Check your inbox" (double opt-in) or "You’re subscribed".
					panel.innerHTML = `<b>${esc(data.title || (state === 'subscribed' ? 'You’re subscribed' : 'Check your inbox'))}</b>${esc(data.message)}`;
					form.replaceWith(panel);
					panel.focus();
					return;
				}
				note(data.message || 'Something went wrong. Please try again.', state === 'invalid');
			} catch (err) {
				note('Something went wrong. Please try again.', false);
			} finally {
				busy = false;
				if (button.isConnected) {
					button.removeAttribute('aria-disabled');
					button.textContent = label;
				}
			}
		});
	});
})();
