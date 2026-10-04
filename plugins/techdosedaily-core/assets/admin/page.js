/**
 * "Page details" for static pages in the block editor (no build step).
 * Shows only the fields the chosen template uses:
 *   Pages / Short page (policies)  kicker · headline · intro · last reviewed · cadence · link · summary · numbering · related documents
 *   About                          kicker · headline · mission statement · intro · focus row · numbering · summary
 *   Contact                        kicker · headline · intro · last reviewed · summary
 *   Newsletter                     headline · intro · summary
 * Everything is registered page meta (sanitized in Core).
 */
(function (wp, cfg) {
	if (!wp || !wp.plugins || !cfg) return;
	const { createElement: h, Fragment, useState } = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const ed = wp.editor;
	const C = wp.components;
	const { __, sprintf } = wp.i18n;

	const Text = (p) => h(C.TextControl, Object.assign({ __nextHasNoMarginBottom: true, __next40pxDefaultSize: true }, p));
	const Area = (p) => h(C.TextareaControl, Object.assign({ __nextHasNoMarginBottom: true }, p));
	const Toggle = (p) => h(C.ToggleControl, Object.assign({ __nextHasNoMarginBottom: true }, p));
	const Select = (p) => h(C.SelectControl, Object.assign({ __nextHasNoMarginBottom: true, __next40pxDefaultSize: true }, p));
	const Note = (p) => h('p', { className: 'tdd-note' + (p.tone ? ' is-' + p.tone : '') }, p.children);
	const Field = (p) => h('div', { className: 'tdd-f' + (p.className ? ' ' + p.className : '') }, p.children);
	const Label = (p) => h('div', { className: 'tdd-label' }, p.children);

	const KIND = {
		'': 'policy',
		'page-short': 'policy',
		'page-about': 'about',
		'page-contact': 'contact',
		'page-newsletter': 'newsletter',
	};

	function usePage() {
		return useSelect((s) => {
			const e = s('core/editor');
			return {
				template: e.getEditedPostAttribute('template') || '',
				title: e.getEditedPostAttribute('title') || '',
				slug: e.getEditedPostAttribute('slug') || '',
				meta: e.getEditedPostAttribute('meta') || {},
				modified: e.getCurrentPostAttribute('modified') || '',
			};
		}, []);
	}

	function Rows({ list, onChange, fields, addLabel, empty, max, itemLabel }) {
		const [open, setOpen] = useState(-1);
		const set = (i, k, v) => onChange(list.map((r, j) => (j === i ? Object.assign({}, r, { [k]: v }) : r)));
		const move = (i, d) => {
			const j = i + d;
			if (j < 0 || j >= list.length) return;
			const next = list.slice();
			[next[i], next[j]] = [next[j], next[i]];
			onChange(next);
			setOpen(j);
		};
		return h(Fragment, null,
			list.length ? null : h(Note, null, empty),
			h('ol', { className: 'tdd-rows' }, list.map((r, i) =>
				h('li', { key: i, className: 'tdd-row' + (open === i ? ' is-open' : '') },
					h('div', { className: 'tdd-row__head' },
						h('button', { type: 'button', className: 'tdd-row__title', 'aria-expanded': open === i, onClick: () => setOpen(open === i ? -1 : i) },
							h('span', { className: 'tdd-row__n' }, i + 1),
							h('span', null, itemLabel(r))),
						h('span', { className: 'tdd-row__acts' },
							h(C.Button, { size: 'small', icon: 'arrow-up-alt2', label: __('Move up', 'techdosedaily-core'), disabled: i === 0, onClick: () => move(i, -1) }),
							h(C.Button, { size: 'small', icon: 'arrow-down-alt2', label: __('Move down', 'techdosedaily-core'), disabled: i === list.length - 1, onClick: () => move(i, 1) }))),
					open === i
						? h('div', { className: 'tdd-row__body' },
							fields.map((f) => f.options
								? h(Select, { key: f.key, label: f.label, value: r[f.key] || f.options[0].value, options: f.options, onChange: (v) => set(i, f.key, v) })
								: h(Text, { key: f.key, label: f.label, type: f.type || 'text', value: r[f.key] || '', help: f.help, onChange: (v) => set(i, f.key, v) })),
							h(C.Button, { variant: 'link', isDestructive: true, onClick: () => { onChange(list.filter((_, j) => j !== i)); setOpen(-1); } }, __('Remove', 'techdosedaily-core')))
						: null))),
			!max || list.length < max
				? h(C.Button, { variant: 'secondary', onClick: () => { const blank = {}; fields.forEach((f) => { blank[f.key] = f.options ? f.options[0].value : ''; }); onChange(list.concat([blank])); setOpen(list.length); } }, addLabel)
				: null
		);
	}

	function PagePanel() {
		const p = usePage();
		const { editPost } = useDispatch('core/editor');
		const m = p.meta;
		const set = (k, v) => editPost({ meta: { [k]: v } });
		const kind = KIND[p.template] || 'policy';
		const isShort = p.template === 'page-short';
		const focus = Array.isArray(m.tdd_focus) ? m.tdd_focus : [];
		const docs = Array.isArray(m.tdd_related_docs) ? m.tdd_related_docs : [];
		const reviewed = m.tdd_last_reviewed || '';
		const fallbackDate = p.modified ? wp.date.dateI18n('M j, Y', p.modified) : '';

		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-page', title: __('Page details', 'techdosedaily-core'), className: 'tdd-panel', initialOpen: true },
			h(Note, null, sprintf(__('Layout: %s. Change it under Template.', 'techdosedaily-core'), cfg.templates[p.template] || cfg.templates[''])),

			kind !== 'newsletter'
				? h(Field, null, h(Text, { label: __('Kicker', 'techdosedaily-core'), value: m.tdd_kicker || '', onChange: (v) => set('tdd_kicker', v), help: __('Small line above the title, e.g. “Policy”, “About”, “Partner with us”.', 'techdosedaily-core') }))
				: null,
			h(Field, null,
				h(Text, { label: __('Headline', 'techdosedaily-core'), value: m.tdd_page_headline || '', placeholder: p.title, onChange: (v) => set('tdd_page_headline', v),
					help: __('Optional. The big heading on the page. Empty = the page title, which stays short for menus and breadcrumbs.', 'techdosedaily-core') })),

			kind === 'about'
				? h(Field, null, h(Text, { label: __('Mission statement', 'techdosedaily-core'), value: m.tdd_statement || '', onChange: (v) => set('tdd_statement', v), help: __('One line under the headline.', 'techdosedaily-core') }))
				: null,
			h(Field, null, h(Area, { label: kind === 'newsletter' ? __('Deck', 'techdosedaily-core') : __('Intro', 'techdosedaily-core'), rows: 3, value: m.tdd_intro || '', onChange: (v) => set('tdd_intro', v) })),

			kind === 'about'
				? h(Field, null,
					h(Label, null, __('Focus row (three items)', 'techdosedaily-core')),
					h(Rows, {
						list: focus, max: 3, onChange: (v) => set('tdd_focus', v),
						fields: [{ key: 'title', label: __('Title', 'techdosedaily-core') }, { key: 'text', label: __('Text', 'techdosedaily-core') }],
						addLabel: __('Add item', 'techdosedaily-core'), empty: __('Not shown while empty.', 'techdosedaily-core'),
						itemLabel: (r) => r.title || __('(untitled)', 'techdosedaily-core'),
					}))
				: null,

			kind === 'policy' || kind === 'contact'
				? h(Field, { className: 'tdd-f--group' },
					h(Label, null, __('Last updated', 'techdosedaily-core')),
					h(Text, { type: 'date', label: __('Last substantive change', 'techdosedaily-core'), value: reviewed, onChange: (v) => set('tdd_last_reviewed', v),
						help: reviewed ? null : sprintf(__('Empty: readers see the last edit date (%s). Set it when the policy itself changes, not for typo fixes.', 'techdosedaily-core'), fallbackDate) }),
					kind === 'policy'
						? h(Fragment, null,
							h(Text, { label: __('Review cadence', 'techdosedaily-core'), value: m.tdd_review_cadence || '', placeholder: __('Reviewed every six months', 'techdosedaily-core'), onChange: (v) => set('tdd_review_cadence', v), help: __('Only if the newsroom really does this.', 'techdosedaily-core') }),
							h(Text, { type: 'url', label: __('Link in the “Last updated” line', 'techdosedaily-core'), value: m.tdd_version_url || '', onChange: (v) => set('tdd_version_url', v), help: __('Version history, or the media kit on Advertise.', 'techdosedaily-core') }),
							m.tdd_version_url ? h(Text, { label: __('Link text', 'techdosedaily-core'), value: m.tdd_version_label || '', placeholder: __('Version history →', 'techdosedaily-core'), onChange: (v) => set('tdd_version_label', v) }) : null)
						: null)
				: null,

			(kind === 'policy' && !isShort) || kind === 'about'
				? h(Field, null, h(Toggle, { label: __('Number the sections', 'techdosedaily-core'), checked: !!m.tdd_numbered, onChange: (v) => set('tdd_numbered', v), help: __('For pages people cite (“section 4”). Each H2 starts a section; 4 or more sections add the contents list.', 'techdosedaily-core') }))
				: null,

			h(Field, null, h(Text, { label: __('Summary in “Related” lists', 'techdosedaily-core'), value: m.tdd_summary || '', onChange: (v) => set('tdd_summary', v), placeholder: cfg.summaries[p.slug] || '', help: __('A few words, e.g. “How we fix errors”.', 'techdosedaily-core') })),

			kind === 'policy'
				? h(Field, null,
					h(Label, null, __('Related documents', 'techdosedaily-core')),
					h(Rows, {
						list: docs, onChange: (v) => set('tdd_related_docs', v),
						fields: [
							{ key: 'title', label: __('Title', 'techdosedaily-core') },
							{ key: 'url', label: __('Link', 'techdosedaily-core'), type: 'url' },
							{ key: 'kind', label: __('Type', 'techdosedaily-core'), options: [{ value: 'policy', label: __('Our policy', 'techdosedaily-core') }, { value: 'external', label: __('External document', 'techdosedaily-core') }] },
						],
						addLabel: __('Add document', 'techdosedaily-core'), empty: __('Optional. Listed at the end of the page.', 'techdosedaily-core'),
						itemLabel: (r) => r.title || __('(untitled)', 'techdosedaily-core'),
					}))
				: null,

			kind === 'contact' && cfg.settingsUrl ? h(Note, null, h('a', { href: cfg.settingsUrl }, __('Inboxes, notes and the secure-tip text are in Site settings →', 'techdosedaily-core'))) : null,
			kind === 'newsletter' ? h(Note, null, __('“A look inside” uses today’s Daily Tech Brief stories automatically.', 'techdosedaily-core')) : null
		);
	}

	wp.plugins.registerPlugin('tdd-page', { render: () => h(PagePanel) });
})(window.wp, window.tddPage);
