/**
 * Behaviour for the classic admin forms (section settings, profile, site settings):
 *  - [data-tdd-ordered]  ordered pickers (desk people, topic navigation, beats): add, move, remove
 *  - [data-tdd-repeat]   repeatable rows (social profiles, expectations)
 *  - [data-tdd-depends]  show a field only when a checkbox is on / a select has a value
 *  - [data-tdd-photo]    choose a portrait from the Media Library
 * No dependencies besides wp.media for the photo picker.
 */
(function () {
	const $$ = (sel, el) => Array.from((el || document).querySelectorAll(sel));
	const i18n = window.tddForms || {};
	const t = (k, d) => i18n[k] || d;

	/* Ordered pickers */
	function itemNode(wrap, id, label) {
		const li = document.createElement('li');
		li.dataset.id = id;
		const name = document.createElement('span');
		name.textContent = label;
		const hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = wrap.dataset.name;
		hidden.value = id;
		li.append(name, hidden);
		[['up', '↑', t('moveUp', 'Move up')], ['down', '↓', t('moveDown', 'Move down')], ['remove', '✕', t('remove', 'Remove')]].forEach(([act, sym, lbl]) => {
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'button-link';
			b.dataset.act = act;
			b.textContent = sym;
			b.setAttribute('aria-label', lbl + ': ' + label);
			li.append(b);
		});
		return li;
	}
	function refreshOrdered(wrap) {
		const items = $$('li', wrap.querySelector('ol'));
		items.forEach((li, i) => {
			li.querySelector('[data-act=up]').disabled = i === 0;
			li.querySelector('[data-act=down]').disabled = i === items.length - 1;
		});
		const sel = wrap.querySelector('select');
		const chosen = items.map((li) => li.dataset.id);
		$$('option', sel).forEach((o) => { if (o.value) o.disabled = chosen.includes(o.value); });
		const max = parseInt(wrap.dataset.max || '0', 10);
		sel.disabled = !!max && items.length >= max;
		const empty = wrap.querySelector('.tdd-ordered__empty');
		if (empty) empty.hidden = items.length > 0;
	}
	$$('[data-tdd-ordered]').forEach((wrap) => {
		const ol = wrap.querySelector('ol');
		$$('li', ol).forEach((li) => {
			const label = li.querySelector('span').textContent;
			li.replaceWith(itemNode(wrap, li.dataset.id, label));
		});
		wrap.querySelector('select').addEventListener('change', (e) => {
			const o = e.target.selectedOptions[0];
			if (!o || !o.value) return;
			ol.append(itemNode(wrap, o.value, o.textContent));
			e.target.value = '';
			refreshOrdered(wrap);
		});
		ol.addEventListener('click', (e) => {
			const b = e.target.closest('button[data-act]');
			if (!b) return;
			const li = b.closest('li');
			if (b.dataset.act === 'up' && li.previousElementSibling) li.previousElementSibling.before(li);
			if (b.dataset.act === 'down' && li.nextElementSibling) li.nextElementSibling.after(li);
			if (b.dataset.act === 'remove') li.remove();
			refreshOrdered(wrap);
			const again = li.isConnected && li.querySelector('[data-act=' + b.dataset.act + ']');
			(again && !again.disabled ? again : wrap.querySelector('select')).focus();
		});
		refreshOrdered(wrap);
	});

	/* Repeatable rows: a <template> holds one empty row; __i__ is replaced by a running index. */
	$$('[data-tdd-repeat]').forEach((wrap) => {
		const tpl = wrap.querySelector('template');
		let n = $$('.tdd-repeat__row', wrap).length;
		wrap.querySelector('[data-add]').addEventListener('click', () => {
			const row = document.createElement('div');
			row.innerHTML = tpl.innerHTML.replace(/__i__/g, String(n++));
			const el = row.firstElementChild;
			wrap.querySelector('[data-rows]').append(el);
			const first = el.querySelector('input, textarea');
			if (first) first.focus();
		});
		wrap.addEventListener('click', (e) => {
			const b = e.target.closest('[data-remove]');
			if (b) b.closest('.tdd-repeat__row').remove();
		});
	});

	/* Conditional fields */
	$$('[data-tdd-depends]').forEach((el) => {
		const ctl = document.getElementById(el.dataset.tddDepends);
		if (!ctl) return;
		const want = el.dataset.tddValue;
		const sync = () => {
			const on = ctl.type === 'checkbox' ? ctl.checked : want ? want.split('|').includes(ctl.value) : !!ctl.value;
			el.hidden = !on;
		};
		ctl.addEventListener('change', sync);
		sync();
	});

	/* Portrait picker */
	$$('[data-tdd-photo]').forEach((wrap) => {
		const input = wrap.querySelector('input[type=hidden]');
		const img = wrap.querySelector('img');
		const ph = wrap.querySelector('.tdd-photo__ph');
		const remove = wrap.querySelector('[data-photo-remove]');
		const show = (url) => {
			img.hidden = !url;
			if (url) img.src = url;
			if (ph) ph.hidden = !!url;
			remove.hidden = !url;
		};
		wrap.querySelector('[data-photo-choose]').addEventListener('click', () => {
			if (!window.wp || !wp.media) return;
			const frame = wp.media({ title: t('photoTitle', 'Choose a portrait'), library: { type: 'image' }, multiple: false, button: { text: t('photoButton', 'Use this photo') } });
			frame.on('select', () => {
				const a = frame.state().get('selection').first().toJSON();
				input.value = a.id;
				show((a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail : a).url);
			});
			frame.open();
		});
		remove.addEventListener('click', () => { input.value = '0'; show(''); });
	});
})();
