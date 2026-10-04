/**
 * Placement editor (no build step). Homepage · Section pages · Daily Tech Brief.
 * Each slot shows: the live story (placed, with its window), what comes back if it ends,
 * scheduled entries, or — when nothing is placed — the story the automatic fallback is using.
 */
(function (wp, cfg) {
	const root = document.getElementById('tdd-board');
	if (!wp || !cfg || !root) return;
	const { createElement: h, Fragment, useState, useEffect, useRef, render, createRoot } = wp.element;
	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const fmt = (iso) => (iso ? wp.date.dateI18n('D M j, g:i a', iso) : '');
	const isFuture = (iso) => !!iso && new Date(iso).getTime() > Date.now();
	const sectionName = (ids) => {
		const s = cfg.sections.find((x) => (ids || []).includes(x.id));
		return s ? s.name : '';
	};
	const decode = (s) => {
		const t = document.createElement('textarea');
		t.innerHTML = s || '';
		return t.value;
	};

	const TABS = [
		['home', __('Homepage', 'techdosedaily-core')],
		['section', __('Section pages', 'techdosedaily-core')],
		['brief', __('Daily Tech Brief', 'techdosedaily-core')],
	];

	function DateField({ id, label, value, onChange, help }) {
		return h('div', null,
			h('label', { htmlFor: id, style: { display: 'block', fontWeight: 600, marginBottom: 4 } }, label),
			h('input', { id, type: 'datetime-local', value: value || '', onChange: (e) => onChange(e.target.value) }),
			help ? h('p', { className: 'description' }, help) : null
		);
	}

	/* Story search: newest published stories, or matches for the typed words. */
	function Picker({ id, onPick, picked }) {
		const [q, setQ] = useState('');
		const [items, setItems] = useState(null);
		const timer = useRef(0);
		useEffect(() => {
			clearTimeout(timer.current);
			timer.current = setTimeout(() => {
				const path = wp.url.addQueryArgs('/wp/v2/posts', { per_page: 12, status: 'publish', search: q || undefined, _fields: 'id,title,date_gmt,categories' });
				apiFetch({ path }).then(setItems).catch(() => setItems([]));
			}, q ? 250 : 0);
			return () => clearTimeout(timer.current);
		}, [q]);
		return h('div', { className: 'tdd-picker' },
			h('label', { htmlFor: id, style: { display: 'block', fontWeight: 600, marginBottom: 4 } }, __('Story', 'techdosedaily-core')),
			h('input', { id, type: 'search', className: 'regular-text', placeholder: __('Search published stories…', 'techdosedaily-core'), value: q, onChange: (e) => setQ(e.target.value), autoComplete: 'off' }),
			items === null
				? h('p', null, __('Loading…', 'techdosedaily-core'))
				: items.length
					? h('ul', { role: 'listbox', 'aria-label': __('Stories', 'techdosedaily-core') },
						items.map((p) => h('li', { key: p.id },
							h('button', { type: 'button', role: 'option', 'aria-selected': picked && picked.id === p.id, onClick: () => onPick({ id: p.id, title: decode(p.title.rendered) }) },
								decode(p.title.rendered),
								h('small', null, [sectionName(p.categories), fmt(p.date_gmt + 'Z')].filter(Boolean).join(' · '))))))
					: h('p', null, __('No published stories match.', 'techdosedaily-core'))
		);
	}

	function SlotEditor({ board, slot, mode, entry, onDone, onCancel, announce }) {
		const [picked, setPicked] = useState(null);
		const [start, setStart] = useState(entry && entry.start_at && mode === 'times' ? wp.date.date('Y-m-d\\TH:i', entry.start_at) : '');
		const [end, setEnd] = useState(entry && entry.expires_at && mode === 'times' ? wp.date.date('Y-m-d\\TH:i', entry.expires_at) : '');
		const [busy, setBusy] = useState(false);
		const [err, setErr] = useState('');
		const base = 'tdd-' + board.placement + '-' + slot.position;
		const save = () => {
			setBusy(true);
			setErr('');
			const req = mode === 'times'
				? apiFetch({ path: '/tdd/v1/placements/' + entry.id, method: 'PATCH', data: { start_at: start || entry.start_at, expires_at: end || null } })
				: apiFetch({ path: '/tdd/v1/placements', method: 'POST', data: { post_id: picked.id, placement: board.placement, position: slot.position, section: board.section, start_at: start || '', expires_at: end || '' } });
			req.then(() => {
				announce(mode === 'times' ? __('Times saved.', 'techdosedaily-core') : sprintf(__('“%s” placed.', 'techdosedaily-core'), picked.title));
				onDone();
			}).catch((e) => setErr(e.message || __('That did not work. Try again.', 'techdosedaily-core'))).finally(() => setBusy(false));
		};
		const valid = mode === 'times' ? true : !!picked;
		return h('div', { className: 'tdd-slot__edit', role: 'group', 'aria-label': mode === 'times' ? __('Change times', 'techdosedaily-core') : __('Choose a story', 'techdosedaily-core') },
			mode !== 'times' ? h(Picker, { id: base + '-q', onPick: setPicked, picked }) : null,
			picked ? h('p', null, h('strong', null, __('Selected: ', 'techdosedaily-core')), picked.title) : null,
			h('div', { className: 'tdd-row2' },
				h(DateField, { id: base + '-start', label: __('Starts', 'techdosedaily-core'), value: start, onChange: setStart, help: mode === 'times' ? null : __('Empty = now.', 'techdosedaily-core') }),
				h(DateField, { id: base + '-end', label: __('Ends (optional)', 'techdosedaily-core'), value: end, onChange: setEnd, help: __('Empty = until another story replaces it.', 'techdosedaily-core') })
			),
			mode !== 'times' && (slot.current || slot.queue.length)
				? h('p', { className: 'description' }, __('The story there now steps back and returns if this one ends.', 'techdosedaily-core'))
				: null,
			err ? h('p', { className: 'notice-error', style: { color: '#b91c1c' }, role: 'alert' }, err) : null,
			h('div', { className: 'tdd-acts' },
				h('button', { type: 'button', className: 'button button-primary', disabled: !valid || busy, onClick: save }, busy ? __('Saving…', 'techdosedaily-core') : mode === 'times' ? __('Save times', 'techdosedaily-core') : __('Place story', 'techdosedaily-core')),
				h('button', { type: 'button', className: 'button', onClick: onCancel }, __('Cancel', 'techdosedaily-core'))
			)
		);
	}

	function Window({ e }) {
		const parts = [];
		parts.push(isFuture(e.start_at) ? sprintf(__('starts %s', 'techdosedaily-core'), fmt(e.start_at)) : sprintf(__('since %s', 'techdosedaily-core'), fmt(e.start_at)));
		parts.push(e.expires_at ? sprintf(__('ends %s', 'techdosedaily-core'), fmt(e.expires_at)) : __('until replaced', 'techdosedaily-core'));
		if (e.by) parts.push(sprintf(__('by %s', 'techdosedaily-core'), e.by));
		return h('span', null, parts.join(' · '));
	}

	function StoryLink({ post, muted }) {
		if (!post) return h('span', null, __('(story deleted)', 'techdosedaily-core'));
		return h('a', { className: 'tdd-slot__story', href: post.edit || post.link, style: muted ? { fontWeight: 400 } : null }, post.title);
	}

	function Slot({ board, slot, reload, announce }) {
		const [mode, setMode] = useState(null); // replace | times
		const [entry, setEntry] = useState(null);
		const [confirm, setConfirm] = useState(0);
		const end = (e) => apiFetch({ path: '/tdd/v1/placements/' + e.id, method: 'DELETE' }).then(() => { announce(sprintf(__('“%s” removed from this slot.', 'techdosedaily-core'), e.post ? e.post.title : '')); setConfirm(0); reload(); });
		const cur = slot.current;
		const fb = board.fallback;
		let body;
		if (cur) {
			body = h(Fragment, null,
				h(StoryLink, { post: cur.post }),
				h('div', { className: 'tdd-slot__meta' },
					h('span', { className: 'tdd-chip tdd-chip--placed' }, __('Placed', 'techdosedaily-core')),
					cur.post && cur.post.section ? h('span', null, cur.post.section) : null,
					cur.post && cur.post.breaking ? h('span', { className: 'tdd-chip tdd-chip--breaking' }, __('Breaking', 'techdosedaily-core')) : null,
					h(Window, { e: cur })
				)
			);
		} else if (slot.auto) {
			body = h(Fragment, null,
				h('div', { className: 'tdd-slot__auto' }, sprintf(__('Automatic fallback: %s', 'techdosedaily-core'), fb.label)),
				h(StoryLink, { post: slot.auto, muted: true }),
				h('div', { className: 'tdd-slot__meta' }, h('span', { className: 'tdd-chip tdd-chip--auto' }, __('Automatic', 'techdosedaily-core')), slot.auto.section ? h('span', null, slot.auto.section) : null)
			);
		} else {
			body = h('div', { className: 'tdd-slot__auto' }, fb.mode === 'none' ? __('Empty — the brief only lists stories you choose.', 'techdosedaily-core') : __('Empty — no eligible story yet.', 'techdosedaily-core'));
		}
		const queue = slot.queue.map((e) =>
			h('li', { key: e.id },
				e.skipped === 'shown-above' ? __('Skipped (already shown higher on the page): ', 'techdosedaily-core')
					: e.skipped === 'unpublished' ? __('Waiting (story not published): ', 'techdosedaily-core')
						: __('Returns if the story above ends: ', 'techdosedaily-core'),
				h(StoryLink, { post: e.post, muted: true }), ' · ', h(Window, { e }), ' ',
				confirm === e.id
					? h('button', { type: 'button', className: 'button-link button-link-delete', 'aria-label': sprintf(__('Confirm removing “%s”', 'techdosedaily-core'), e.post ? e.post.title : ''), onClick: () => end(e) }, __('Confirm remove', 'techdosedaily-core'))
					: h('button', { type: 'button', className: 'button-link', 'aria-label': sprintf(__('Remove “%s”', 'techdosedaily-core'), e.post ? e.post.title : ''), onClick: () => setConfirm(e.id) }, __('Remove', 'techdosedaily-core'))
			));
		const sched = slot.scheduled.map((e) =>
			h('li', { key: e.id },
				h('span', { className: 'tdd-chip tdd-chip--sched' }, __('Scheduled', 'techdosedaily-core')), ' ',
				h(StoryLink, { post: e.post, muted: true }), ' · ', h(Window, { e }), ' ',
				h('button', { type: 'button', className: 'button-link', 'aria-label': sprintf(__('Change times for “%s”', 'techdosedaily-core'), e.post ? e.post.title : ''), onClick: () => { setEntry(e); setMode('times'); } }, __('Change times', 'techdosedaily-core')), ' ',
				confirm === e.id
					? h('button', { type: 'button', className: 'button-link button-link-delete', 'aria-label': sprintf(__('Confirm removing “%s”', 'techdosedaily-core'), e.post ? e.post.title : ''), onClick: () => end(e) }, __('Confirm remove', 'techdosedaily-core'))
					: h('button', { type: 'button', className: 'button-link', 'aria-label': sprintf(__('Remove “%s”', 'techdosedaily-core'), e.post ? e.post.title : ''), onClick: () => setConfirm(e.id) }, __('Remove', 'techdosedaily-core'))
			));
		return h('div', { className: 'tdd-slot' + (cur ? '' : ' is-auto') },
			h('div', { className: 'tdd-slot__n', 'aria-label': sprintf(__('Position %d', 'techdosedaily-core'), slot.position) }, slot.position),
			h('div', null, body, queue.length || sched.length ? h('ul', { className: 'tdd-slot__queue' }, sched, queue) : null),
			h('div', { className: 'tdd-slot__acts' },
				h('button', { type: 'button', className: 'button', 'aria-label': sprintf(cur ? __('Replace story in position %d', 'techdosedaily-core') : __('Choose story for position %d', 'techdosedaily-core'), slot.position), onClick: () => { setEntry(null); setMode(mode === 'replace' ? null : 'replace'); }, 'aria-expanded': mode === 'replace' }, cur ? __('Replace story →', 'techdosedaily-core') : __('Choose story →', 'techdosedaily-core')),
				cur ? h('button', { type: 'button', className: 'button', 'aria-label': sprintf(__('Change times for position %d', 'techdosedaily-core'), slot.position), 'aria-expanded': mode === 'times', onClick: () => { setEntry(cur); setMode(mode === 'times' ? null : 'times'); } }, __('Change times', 'techdosedaily-core')) : null,
				cur
					? confirm === cur.id
						? h('button', { type: 'button', className: 'button button-link-delete', onClick: () => end(cur) }, __('Confirm remove', 'techdosedaily-core'))
						: h('button', { type: 'button', className: 'button', 'aria-label': sprintf(__('Remove “%s” from this slot', 'techdosedaily-core'), cur.post ? cur.post.title : ''), onClick: () => setConfirm(cur.id) }, __('Remove', 'techdosedaily-core'))
					: null
			),
			mode ? h(SlotEditor, { board, slot, mode, entry, announce, onCancel: () => setMode(null), onDone: () => { setMode(null); reload(); } }) : null
		);
	}

	function Board({ board, reload, announce }) {
		return h('section', { className: 'tdd-card', 'aria-labelledby': 'tdd-b-' + board.placement },
			h('div', { className: 'tdd-card__h' },
				h('h2', { id: 'tdd-b-' + board.placement }, board.label),
				h('small', null, board.fallback.mode === 'none' ? __('Empty slots stay empty.', 'techdosedaily-core') : sprintf(__('Empty slots: %s.', 'techdosedaily-core'), board.fallback.label))
			),
			board.slots.map((s) => h(Slot, { key: board.placement + s.position, board, slot: s, reload, announce }))
		);
	}

	function App() {
		const [tab, setTab] = useState(cfg.tab || 'home');
		const [section, setSection] = useState(cfg.section || (cfg.sections[0] || {}).id || 0);
		const [data, setData] = useState(null);
		const [msg, setMsg] = useState('');
		const load = () => {
			const args = tab === 'section' ? { group: 'section', section } : { group: tab };
			apiFetch({ path: wp.url.addQueryArgs('/tdd/v1/placements/board', args) }).then(setData).catch((e) => setData({ error: e.message }));
		};
		useEffect(() => { setData(null); load(); }, [tab, section]);
		const sec = cfg.sections.find((s) => s.id === section);
		return h(Fragment, null,
			h('div', { className: 'tdd-tabs', role: 'tablist', 'aria-label': __('Pages', 'techdosedaily-core') },
				TABS.map(([k, label]) => h('button', { key: k, type: 'button', role: 'tab', id: 'tdd-tab-' + k, 'aria-selected': tab === k, 'aria-controls': 'tdd-panel', onClick: () => setTab(k) }, label))
			),
			h('div', { id: 'tdd-panel', role: 'tabpanel', 'aria-labelledby': 'tdd-tab-' + tab },
				tab === 'section'
					? h('div', { className: 'tdd-sectionpick' },
						h('label', { htmlFor: 'tdd-sec' }, __('Section', 'techdosedaily-core')),
						h('select', { id: 'tdd-sec', value: section, onChange: (e) => setSection(parseInt(e.target.value, 10)) }, cfg.sections.map((s) => h('option', { key: s.id, value: s.id }, s.name))),
						sec ? h('a', { href: cfg.homeUrl + sec.slug + '/', target: '_blank', rel: 'noopener' }, __('View section page ↗', 'techdosedaily-core')) : null
					)
					: tab === 'home'
						? h('p', null, h('a', { href: cfg.homeUrl, target: '_blank', rel: 'noopener' }, __('View homepage ↗', 'techdosedaily-core')), ' · ', __('Preview of automatic picks skips stories already shown higher on the page, as the live page does.', 'techdosedaily-core'))
						: null,
				h('div', { className: 'screen-reader-text', role: 'status', 'aria-live': 'polite' }, msg),
				data === null
					? h('p', null, __('Loading…', 'techdosedaily-core'))
					: data.error
						? h('div', { className: 'notice notice-error' }, h('p', null, data.error))
						: data.map((b) => h(Board, { key: b.placement, board: b, reload: load, announce: setMsg }))
			)
		);
	}

	if (createRoot) createRoot(root).render(h(App));
	else render(h(App), root);
})(window.wp, window.tddBoard);
