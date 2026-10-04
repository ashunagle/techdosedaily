/**
 * Tech Dose Daily story panels for the block editor (no build step).
 *
 * Story details · Status & labels · Sources · Corrections · Hero image credit · Placement ·
 * pre-publish checklist. Fields appear only when they apply (sponsor only for Sponsored, breaking
 * times only when Breaking is on, correction entry only after publication, severity only in
 * Cybersecurity…). Everything is saved as registered post meta / taxonomy terms with the post.
 */
(function (wp, cfg) {
	if (!wp || !wp.plugins || !cfg) return;
	const { createElement: h, Fragment, useState, useEffect } = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const { registerPlugin } = wp.plugins;
	const ed = wp.editor;
	const C = wp.components;
	const { __, sprintf, _n } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const fmt = (iso, f) => (iso ? wp.date.dateI18n(f || 'M j, Y g:i a', iso) : '');
	const toInput = (iso) => (iso ? wp.date.date('Y-m-d\\TH:i', iso) : '');
	const nowIso = () => new Date().toISOString();
	const isFuture = (iso) => !!iso && new Date(iso).getTime() > Date.now();

	/* ---------- shared data ---------- */
	function usePost() {
		return useSelect((s) => {
			const e = s('core/editor');
			return {
				id: e.getCurrentPostId(),
				status: e.getEditedPostAttribute('status'),
				savedStatus: e.getCurrentPostAttribute('status'),
				meta: e.getEditedPostAttribute('meta') || {},
				savedMeta: e.getCurrentPostAttribute('meta') || {},
				formats: e.getEditedPostAttribute('tdd_format') || [],
				featured: e.getEditedPostAttribute('featured_media') || 0,
				excerpt: e.getEditedPostAttribute('excerpt') || '',
			};
		}, []);
	}
	function useEdit() {
		const { editPost } = useDispatch('core/editor');
		return {
			editPost,
			setMeta: (k, v) => editPost({ meta: { [k]: v } }),
		};
	}
	const sectionById = (id) => cfg.sections.find((s) => s.id === id);
	const formatById = (id) => cfg.formats.find((f) => f.id === id);
	const isPublished = (p) => p.savedStatus === 'publish' || p.savedStatus === 'future';

	const Field = (props) => h('div', { className: 'tdd-f' + (props.className ? ' ' + props.className : '') }, props.children);
	const Note = (props) => h('p', { className: 'tdd-note' + (props.tone ? ' is-' + props.tone : '') }, props.children);
	const Count = (len, max) => h('span', { className: 'tdd-count' + (len > max ? ' is-over' : '') }, len + ' / ' + max);
	const DateTime = (props) =>
		h(C.TextControl, {
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			type: 'datetime-local',
			label: props.label,
			help: props.help,
			value: toInput(props.value),
			onChange: (v) => props.onChange(v || ''),
		});
	// datetime-local values are wall-clock times in the site's timezone; Core stores them as ISO
	// with the site offset (tdd_core_sanitize_datetime), so the naive value is sent as-is.

	const Select = (p) => h(C.SelectControl, Object.assign({ __nextHasNoMarginBottom: true, __next40pxDefaultSize: true }, p));
	const Text = (p) => h(C.TextControl, Object.assign({ __nextHasNoMarginBottom: true, __next40pxDefaultSize: true }, p));
	const Area = (p) => h(C.TextareaControl, Object.assign({ __nextHasNoMarginBottom: true }, p));
	const Toggle = (p) => h(C.ToggleControl, Object.assign({ __nextHasNoMarginBottom: true }, p));

	/* ---------- 1. Story details ---------- */
	function StoryPanel() {
		const p = usePost();
		const { editPost, setMeta } = useEdit();
		const m = p.meta;
		const fmtTerm = formatById(p.formats[0]) || cfg.formats.find((f) => f.slug === 'news');
		const sponsored = fmtTerm && fmtTerm.slug === 'sponsored';
		const deck = m.tdd_deck || '';
		const short = m.tdd_short_title || '';
		const section = m.tdd_primary_section || 0;
		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-story', title: __('Story details', 'techdosedaily-core'), className: 'tdd-panel', initialOpen: true },
			h(Field, null,
				h(Select, {
					label: __('Section', 'techdosedaily-core'),
					value: String(section),
					options: [{ value: '0', label: __('— Choose one section —', 'techdosedaily-core') }].concat(cfg.sections.map((s) => ({ value: String(s.id), label: s.name }))),
					onChange: (v) => {
						const id = parseInt(v, 10) || 0;
						setMeta('tdd_primary_section', id);
						editPost({ categories: id ? [id] : [] });
						if (id && sectionById(id) && sectionById(id).slug !== 'cybersecurity' && m.tdd_severity) setMeta('tdd_severity', '');
					},
					help: __('Exactly one. Use Tech only when no specialist section fits — Topics handle everything else.', 'techdosedaily-core'),
				}),
				section && isPublished(p) && p.savedMeta.tdd_primary_section && p.savedMeta.tdd_primary_section !== section
					? h(Note, { tone: 'info' }, __('The story keeps its published URL. It moves to the new section page and label.', 'techdosedaily-core'))
					: null
			),
			h(Field, null,
				h(Select, {
					label: __('Story type', 'techdosedaily-core'),
					value: fmtTerm ? String(fmtTerm.id) : '',
					options: cfg.formats.map((f) => ({ value: String(f.id), label: f.name })),
					onChange: (v) => {
						const f = formatById(parseInt(v, 10));
						editPost({ tdd_format: f ? [f.id] : [] });
						if (f && f.slug !== 'sponsored' && m.tdd_sponsor) setMeta('tdd_sponsor', '');
					},
				})
			),
			sponsored
				? h(Field, { className: 'tdd-f--group' },
					h(Text, {
						label: __('Sponsor', 'techdosedaily-core'),
						value: m.tdd_sponsor || '',
						onChange: (v) => setMeta('tdd_sponsor', v),
						help: __('Required. Shown as the sponsor and in the Sponsored label. Sponsored stories are produced separately from the newsroom.', 'techdosedaily-core'),
					}),
					!m.tdd_sponsor ? h(Note, { tone: 'warn' }, __('Add the sponsor’s name before publishing.', 'techdosedaily-core')) : null
				)
				: null,
			h(Field, null,
				h(Area, {
					label: h(Fragment, null, __('Deck', 'techdosedaily-core'), ' ', Count(deck.length, 160)),
					value: deck,
					rows: 3,
					onChange: (v) => setMeta('tdd_deck', v),
					help: __('One or two sentences under the headline. Also the search description.', 'techdosedaily-core'),
				})
			),
			h(Field, null,
				h(Text, {
					label: h(Fragment, null, __('Short headline', 'techdosedaily-core'), ' ', Count(short.length, 60)),
					value: short,
					onChange: (v) => setMeta('tdd_short_title', v),
					help: __('Optional. For compact lists such as the Daily Tech Brief.', 'techdosedaily-core'),
				})
			),
			h(Field, null,
				h('div', { className: 'tdd-label' }, __('Topics', 'techdosedaily-core')),
				ed.PostTaxonomiesFlatTermSelector ? h(ed.PostTaxonomiesFlatTermSelector, { slug: 'tdd_topic' }) : null,
				h(Note, null, __('Companies, products and themes readers follow, e.g. OpenAI, AI agents.', 'techdosedaily-core'))
			),
			h(Field, null,
				h(Select, {
					label: __('Edited by', 'techdosedaily-core'),
					value: String(m.tdd_editor || 0),
					options: [{ value: '0', label: __('— Not set —', 'techdosedaily-core') }].concat(cfg.editors.map((u) => ({ value: String(u.id), label: u.name }))),
					onChange: (v) => setMeta('tdd_editor', parseInt(v, 10) || 0),
					help: __('Shown as “Edited by” on the story.', 'techdosedaily-core'),
				})
			),
			m.tdd_reading_time ? h(Note, null, sprintf(__('About %d min read (recalculated on save).', 'techdosedaily-core'), m.tdd_reading_time)) : null
		);
	}

	/* ---------- 1b. Status & labels ---------- */
	function LabelsPanel() {
		const p = usePost();
		const { setMeta } = useEdit();
		const m = p.meta;
		const [updating, setUpdating] = useState(false);
		const [aiOpen, setAiOpen] = useState(!!m.tdd_ai_disclosure);
		const breakingOn = !!m.tdd_breaking_until && (isFuture(m.tdd_breaking_until) || m.tdd_breaking_until !== p.savedMeta.tdd_breaking_until);
		const breakingEnded = !!m.tdd_breaking_until && !isFuture(m.tdd_breaking_until) && m.tdd_breaking_until === p.savedMeta.tdd_breaking_until;
		const sec = sectionById(m.tdd_primary_section || 0);
		const newUpdate = m.tdd_updated_at && m.tdd_updated_at !== p.savedMeta.tdd_updated_at;

		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-labels', title: __('Status & labels', 'techdosedaily-core'), className: 'tdd-panel' },
			/* Breaking */
			h(Field, { className: breakingOn ? 'tdd-f--group is-breaking' : '' },
				h(Toggle, {
					label: __('Breaking', 'techdosedaily-core'),
					checked: breakingOn,
					help: breakingOn ? null : __('Real breaking news only. It switches itself off.', 'techdosedaily-core'),
					onChange: (on) => {
						if (on) {
							setMeta('tdd_breaking_from', '');
							setMeta('tdd_breaking_until', new Date(Date.now() + cfg.breakingHours * 3600e3).toISOString());
						} else {
							setMeta('tdd_breaking_until', '');
							setMeta('tdd_breaking_from', '');
						}
					},
				}),
				breakingOn
					? h(Fragment, null,
						h(DateTime, {
							label: __('Starts', 'techdosedaily-core'),
							value: m.tdd_breaking_from,
							help: __('Leave empty to start at publication.', 'techdosedaily-core'),
							onChange: (v) => setMeta('tdd_breaking_from', v),
						}),
						h(DateTime, {
							label: __('Ends', 'techdosedaily-core'),
							value: m.tdd_breaking_until,
							help: sprintf(__('Default %d hours. The label disappears on its own.', 'techdosedaily-core'), cfg.breakingHours),
							onChange: (v) => setMeta('tdd_breaking_until', v),
						}),
						m.tdd_breaking_until && !isFuture(m.tdd_breaking_until) ? h(Note, { tone: 'warn' }, __('The end time is in the past.', 'techdosedaily-core')) : null
					)
					: breakingEnded
						? h(Note, null, sprintf(__('Breaking ended %s.', 'techdosedaily-core'), fmt(m.tdd_breaking_until)))
						: null
			),
			/* Substantive update (published stories only) */
			isPublished(p)
				? h(Field, { className: newUpdate || updating ? 'tdd-f--group' : '' },
					h('div', { className: 'tdd-label' }, __('Update', 'techdosedaily-core')),
					p.savedMeta.tdd_updated_at && !newUpdate
						? h(Note, null, sprintf(__('Last substantive update: %1$s — %2$s', 'techdosedaily-core'), fmt(p.savedMeta.tdd_updated_at), p.savedMeta.tdd_update_note || __('no note', 'techdosedaily-core')))
						: null,
					newUpdate || updating
						? h(Fragment, null,
							h(Text, {
								label: __('What changed', 'techdosedaily-core'),
								value: m.tdd_update_note || '',
								onChange: (v) => setMeta('tdd_update_note', v),
								help: sprintf(__('Shown as “Updated %s” with this note.', 'techdosedaily-core'), fmt(m.tdd_updated_at)),
							}),
							!m.tdd_update_note ? h(Note, { tone: 'warn' }, __('Add a one-line note before updating the story.', 'techdosedaily-core')) : null,
							h(C.Button, {
								variant: 'link',
								onClick: () => {
									setMeta('tdd_updated_at', p.savedMeta.tdd_updated_at || '');
									setMeta('tdd_update_note', p.savedMeta.tdd_update_note || '');
									setUpdating(false);
								},
							}, __('Cancel — this edit is not an update', 'techdosedaily-core'))
						)
						: h(Fragment, null,
							h(C.Button, {
								variant: 'secondary',
								onClick: () => {
									setMeta('tdd_updated_at', nowIso());
									setMeta('tdd_update_note', '');
									setUpdating(true);
								},
							}, __('Record a substantive update', 'techdosedaily-core')),
							h(Note, null, __('Only for new information. Typos, formatting and headline polish are not updates.', 'techdosedaily-core'))
						)
				)
				: null,
			/* Claims & disclosures */
			h(Field, null,
				h('div', { className: 'tdd-label' }, __('Claims & disclosures', 'techdosedaily-core')),
				h(Toggle, {
					label: __('Includes company figures we could not verify', 'techdosedaily-core'),
					checked: !!m.tdd_vendor_reported,
					onChange: (v) => setMeta('tdd_vendor_reported', v),
					help: m.tdd_vendor_reported ? __('Readers see a “vendor-reported” note.', 'techdosedaily-core') : null,
				}),
				h(Toggle, {
					label: __('Contains AI-generated material', 'techdosedaily-core'),
					checked: aiOpen,
					onChange: (v) => {
						setAiOpen(v);
						if (!v) setMeta('tdd_ai_disclosure', '');
					},
				}),
				aiOpen
					? h(Area, {
						label: __('Disclosure shown to readers', 'techdosedaily-core'),
						value: m.tdd_ai_disclosure || '',
						rows: 2,
						onChange: (v) => setMeta('tdd_ai_disclosure', v),
						help: __('Say what was generated, e.g. “The illustration was generated with an AI image tool.”', 'techdosedaily-core'),
					})
					: null
			),
			/* Severity: Cybersecurity only */
			sec && sec.slug === 'cybersecurity'
				? h(Field, null,
					h(Text, {
						label: __('Severity line', 'techdosedaily-core'),
						value: m.tdd_severity || '',
						placeholder: 'Patch now · Critical',
						onChange: (v) => setMeta('tdd_severity', v),
						help: __('Only from the vendor or CVE rating. Shown in the homepage Cybersecurity block.', 'techdosedaily-core'),
					})
				)
				: null
		);
	}

	/* ---------- 2. Sources ---------- */
	function SourcesPanel() {
		const p = usePost();
		const { setMeta } = useEdit();
		const list = Array.isArray(p.meta.tdd_sources) ? p.meta.tdd_sources : [];
		const [open, setOpen] = useState(-1);
		const [moved, setMoved] = useState(false);
		const save = (next) => setMeta('tdd_sources', next);
		const update = (i, k, v) => save(list.map((s, j) => (j === i ? Object.assign({}, s, { [k]: v }) : s)));
		const move = (i, d) => {
			const j = i + d;
			if (j < 0 || j >= list.length) return;
			const next = list.slice();
			[next[i], next[j]] = [next[j], next[i]];
			save(next);
			setOpen(j);
			setMoved(true);
		};
		const typeLabel = (v) => (cfg.sourceTypes.find((t) => t.value === v) || { label: v }).label.split(' — ')[0];
		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-sources', title: sprintf(__('Sources (%d)', 'techdosedaily-core'), list.length), className: 'tdd-panel' },
			list.length ? null : h(Note, null, __('List what the story relies on, primary sources first. Readers see them numbered at the end.', 'techdosedaily-core')),
			moved ? h(Note, { tone: 'warn' }, __('Moving a source renumbers it. Check the [1], [2]… references in the text.', 'techdosedaily-core')) : null,
			h('ol', { className: 'tdd-rows' },
				list.map((s, i) =>
					h('li', { key: i, className: 'tdd-row' + (open === i ? ' is-open' : '') },
						h('div', { className: 'tdd-row__head' },
							h('button', { type: 'button', className: 'tdd-row__title', 'aria-expanded': open === i, onClick: () => setOpen(open === i ? -1 : i) },
								h('span', { className: 'tdd-row__n' }, i + 1),
								h('span', null, s.title || __('(untitled source)', 'techdosedaily-core'), h('small', null, typeLabel(s.type) + (s.publisher ? ' · ' + s.publisher : '')))
							),
							h('span', { className: 'tdd-row__acts' },
								h(C.Button, { size: 'small', icon: 'arrow-up-alt2', label: __('Move up', 'techdosedaily-core'), disabled: i === 0, onClick: () => move(i, -1) }),
								h(C.Button, { size: 'small', icon: 'arrow-down-alt2', label: __('Move down', 'techdosedaily-core'), disabled: i === list.length - 1, onClick: () => move(i, 1) })
							)
						),
						open === i
							? h('div', { className: 'tdd-row__body' },
								h(Text, { label: __('Title', 'techdosedaily-core'), value: s.title || '', onChange: (v) => update(i, 'title', v), help: !s.title ? __('Required — untitled sources are dropped on save.', 'techdosedaily-core') : null }),
								h(Text, { label: __('Link', 'techdosedaily-core'), type: 'url', value: s.url || '', onChange: (v) => update(i, 'url', v) }),
								h(Select, { label: __('Type', 'techdosedaily-core'), value: s.type || 'primary', options: cfg.sourceTypes, onChange: (v) => update(i, 'type', v) }),
								s.type === 'confidential' ? h(Note, { tone: 'warn' }, __('Confidential sources need an editor’s approval and a reason given in the story.', 'techdosedaily-core')) : null,
								h(Text, { label: __('Publisher / organisation', 'techdosedaily-core'), value: s.publisher || '', onChange: (v) => update(i, 'publisher', v) }),
								h(Text, { label: __('Date', 'techdosedaily-core'), value: s.date || '', placeholder: 'YYYY-MM-DD', onChange: (v) => update(i, 'date', v), help: __('When the source was published, if known.', 'techdosedaily-core') }),
								h(Text, { label: __('Note', 'techdosedaily-core'), value: s.note || '', onChange: (v) => update(i, 'note', v), help: __('Optional, e.g. “Vendor-reported benchmark”.', 'techdosedaily-core') }),
								h(C.Button, { variant: 'link', isDestructive: true, onClick: () => { save(list.filter((_, j) => j !== i)); setOpen(-1); } }, __('Remove source', 'techdosedaily-core'))
							)
							: null
					)
				)
			),
			h(C.Button, { variant: 'secondary', onClick: () => { save(list.concat([{ title: '', url: '', type: list.length ? 'supporting' : 'primary', publisher: '', date: '', note: '' }])); setOpen(list.length); } }, __('Add source', 'techdosedaily-core'))
		);
	}

	/* ---------- 2b. Corrections ---------- */
	function CorrectionsPanel() {
		const p = usePost();
		const { setMeta } = useEdit();
		const saved = Array.isArray(p.savedMeta.tdd_corrections) ? p.savedMeta.tdd_corrections : [];
		const edited = Array.isArray(p.meta.tdd_corrections) ? p.meta.tdd_corrections : [];
		const draft = edited.length > saved.length ? edited[edited.length - 1] : null;
		if (!isPublished(p) && !saved.length) {
			return h(ed.PluginDocumentSettingPanel, { name: 'tdd-corrections', title: __('Corrections', 'techdosedaily-core'), className: 'tdd-panel' },
				h(Note, null, __('Corrections can be added after the story is published.', 'techdosedaily-core')));
		}
		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-corrections', title: sprintf(__('Corrections (%d)', 'techdosedaily-core'), saved.length), className: 'tdd-panel' },
			saved.length
				? h('ul', { className: 'tdd-log' }, saved.map((c, i) => h('li', { key: i }, h('time', null, fmt(c.time, 'M j, Y')), c.text)))
				: null,
			saved.length && isPublished(p) ? h(Note, null, __('Published corrections are a public record. They can’t be edited or removed here.', 'techdosedaily-core')) : null,
			draft
				? h('div', { className: 'tdd-f--group' },
					h(Area, {
						label: __('New correction', 'techdosedaily-core'),
						value: draft.text,
						rows: 4,
						onChange: (v) => setMeta('tdd_corrections', saved.concat([{ time: draft.time, text: v }])),
						help: __('Say what was wrong and what is right. It is dated today and shown on the story when you update it.', 'techdosedaily-core'),
					}),
					!draft.text ? h(Note, { tone: 'warn' }, __('Empty corrections are not saved.', 'techdosedaily-core')) : null,
					h(C.Button, { variant: 'link', onClick: () => setMeta('tdd_corrections', saved) }, __('Cancel', 'techdosedaily-core'))
				)
				: h(C.Button, { variant: 'secondary', onClick: () => setMeta('tdd_corrections', saved.concat([{ time: nowIso(), text: '' }])) }, __('Add a correction', 'techdosedaily-core'))
		);
	}

	/* ---------- 2c. Hero image credit ---------- */
	function ImagePanel() {
		const p = usePost();
		const id = p.featured;
		const media = useSelect((s) => (id ? s('core').getEditedEntityRecord('postType', 'attachment', id) : null), [id]);
		const { editEntityRecord } = useDispatch('core');
		if (!id) {
			return h(ed.PluginDocumentSettingPanel, { name: 'tdd-image', title: __('Hero image credit', 'techdosedaily-core'), className: 'tdd-panel' },
				h(Note, null, __('Set a featured image to add its credit and source.', 'techdosedaily-core')));
		}
		if (!media || !media.meta) {
			return h(ed.PluginDocumentSettingPanel, { name: 'tdd-image', title: __('Hero image credit', 'techdosedaily-core'), className: 'tdd-panel' }, h(C.Spinner));
		}
		const mm = media.meta;
		const set = (k, v) => editEntityRecord('postType', 'attachment', id, { meta: Object.assign({}, mm, { [k]: v }) });
		const type = mm.tdd_source_type || '';
		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-image', title: __('Hero image credit', 'techdosedaily-core'), className: 'tdd-panel' },
			h(Note, null, __('Saved on the image, so the credit travels wherever the image is reused.', 'techdosedaily-core')),
			h(Text, { label: __('Credit', 'techdosedaily-core'), value: mm.tdd_credit || '', onChange: (v) => set('tdd_credit', v), help: __('e.g. “Company handout” or “Photo: Agency name”.', 'techdosedaily-core') }),
			!mm.tdd_credit ? h(Note, { tone: 'warn' }, __('Every published image needs a credit.', 'techdosedaily-core')) : null,
			h(Select, { label: __('Source', 'techdosedaily-core'), value: type, options: cfg.imageTypes, onChange: (v) => set('tdd_source_type', v) }),
			type === 'ai-generated' ? h(Note, { tone: 'info' }, __('Readers see an “AI-generated” label wherever this image appears.', 'techdosedaily-core')) : null,
			type && type !== 'photo' && type !== 'graphic'
				? h(Text, { label: __('Source link', 'techdosedaily-core'), type: 'url', value: mm.tdd_source_url || '', onChange: (v) => set('tdd_source_url', v), help: __('Where it came from, or the licence record.', 'techdosedaily-core') })
				: null,
			type === 'licensed' || type === 'official'
				? h(Text, { label: __('Licence / usage terms', 'techdosedaily-core'), value: mm.tdd_license_note || '', onChange: (v) => set('tdd_license_note', v), help: __('e.g. “Editorial use only”.', 'techdosedaily-core') })
				: null
		);
	}

	/* ---------- 3. Placement (editors) ---------- */
	function PlacementPanel() {
		const p = usePost();
		const [rows, setRows] = useState(null);
		const [form, setForm] = useState(null);
		const [busy, setBusy] = useState(false);
		const [err, setErr] = useState('');
		const load = () => apiFetch({ path: '/tdd/v1/placements/post/' + p.id }).then(setRows).catch(() => setRows([]));
		useEffect(() => { if (cfg.canPlace && isPublished(p)) load(); }, [p.id, p.savedStatus]);
		if (!cfg.canPlace) return null;
		const title = __('Homepage & section placement', 'techdosedaily-core');
		if (!isPublished(p)) {
			return h(ed.PluginDocumentSettingPanel, { name: 'tdd-place', title, className: 'tdd-panel' }, h(Note, null, __('Publish or schedule the story to place it.', 'techdosedaily-core')));
		}
		const type = form ? cfg.placements.find((t) => t.key === form.placement) : null;
		const submit = () => {
			setBusy(true);
			setErr('');
			apiFetch({ path: '/tdd/v1/placements', method: 'POST', data: { post_id: p.id, placement: form.placement, position: form.position, section: form.section, expires_at: form.expires || '' } })
				.then(() => { setForm(null); load(); })
				.catch((e) => setErr(e.message || __('Could not place the story.', 'techdosedaily-core')))
				.finally(() => setBusy(false));
		};
		return h(ed.PluginDocumentSettingPanel, { name: 'tdd-place', title, className: 'tdd-panel' },
			rows === null ? h(C.Spinner) : null,
			rows && rows.length
				? h('ul', { className: 'tdd-log' }, rows.map((r) =>
					h('li', { key: r.id },
						h('strong', null, r.label + (r.section ? ' · ' + r.section : '')),
						' ' + sprintf(__('position %d', 'techdosedaily-core'), r.position),
						h('br'),
						h('small', null, (isFuture(r.start_at) ? sprintf(__('Starts %s', 'techdosedaily-core'), fmt(r.start_at)) : sprintf(__('Since %s', 'techdosedaily-core'), fmt(r.start_at))) + (r.expires_at ? ' · ' + sprintf(__('until %s', 'techdosedaily-core'), fmt(r.expires_at)) : ' · ' + __('until replaced', 'techdosedaily-core'))),
						' ',
						h(C.Button, { variant: 'link', isDestructive: true, onClick: () => apiFetch({ path: '/tdd/v1/placements/' + r.id, method: 'DELETE' }).then(load) }, __('End', 'techdosedaily-core'))
					)))
				: rows ? h(Note, null, __('Not placed. Homepage and section slots fill automatically with the newest stories.', 'techdosedaily-core')) : null,
			form
				? h('div', { className: 'tdd-f--group' },
					h(Select, {
						label: __('Place in', 'techdosedaily-core'),
						value: form.placement,
						options: cfg.placements.map((t) => ({ value: t.key, label: t.label })),
						onChange: (v) => setForm(Object.assign({}, form, { placement: v, position: 1 })),
					}),
					type && type.max > 1
						? h(Select, { label: __('Position', 'techdosedaily-core'), value: String(form.position), options: Array.from({ length: type.max }, (_, i) => ({ value: String(i + 1), label: String(i + 1) })), onChange: (v) => setForm(Object.assign({}, form, { position: parseInt(v, 10) })) })
						: null,
					type && type.needsSection
						? h(Select, { label: __('Section page', 'techdosedaily-core'), value: String(form.section), options: cfg.sections.map((s) => ({ value: String(s.id), label: s.name })), onChange: (v) => setForm(Object.assign({}, form, { section: parseInt(v, 10) })) })
						: null,
					h(DateTime, { label: __('Ends (optional)', 'techdosedaily-core'), value: form.expires, help: __('Empty = until another story replaces it.', 'techdosedaily-core'), onChange: (v) => setForm(Object.assign({}, form, { expires: v })) }),
					err ? h(Note, { tone: 'warn' }, err) : null,
					h('div', { className: 'tdd-acts' },
						h(C.Button, { variant: 'primary', isBusy: busy, disabled: busy, onClick: submit }, __('Place story', 'techdosedaily-core')),
						h(C.Button, { variant: 'tertiary', onClick: () => setForm(null) }, __('Cancel', 'techdosedaily-core'))
					),
					h(Note, null, __('The story currently in that slot steps back and returns if this one ends.', 'techdosedaily-core'))
				)
				: h(C.Button, { variant: 'secondary', onClick: () => setForm({ placement: 'homepage_lead', position: 1, section: p.meta.tdd_primary_section || (cfg.sections[0] || {}).id, expires: '' }) }, __('Place this story…', 'techdosedaily-core')),
			h('p', null, h('a', { href: cfg.boardUrl }, __('Open the placement editor →', 'techdosedaily-core')))
		);
	}

	/* ---------- Pre-publish checklist (advisory) ---------- */
	function Checklist() {
		const p = usePost();
		const m = p.meta;
		const media = useSelect((s) => (p.featured ? s('core').getEditedEntityRecord('postType', 'attachment', p.featured) : null), [p.featured]);
		const fmtTerm = formatById(p.formats[0]) || cfg.formats.find((f) => f.slug === 'news');
		const items = [
			[!!m.tdd_primary_section, __('Section chosen', 'techdosedaily-core'), __('Choose the story’s section', 'techdosedaily-core')],
			[!!(m.tdd_deck || '').trim(), __('Deck written', 'techdosedaily-core'), __('Write a deck (also the search description)', 'techdosedaily-core')],
			[(m.tdd_sources || []).some((s) => s.title), __('Sources listed', 'techdosedaily-core'), __('List at least one source', 'techdosedaily-core')],
			[!!m.tdd_editor, __('Editor named', 'techdosedaily-core'), __('Name the editor who reviewed it', 'techdosedaily-core')],
		];
		if (p.featured) items.push([!!(media && media.meta && media.meta.tdd_credit), __('Hero image credited', 'techdosedaily-core'), __('Add the hero image credit', 'techdosedaily-core')]);
		if (fmtTerm && fmtTerm.slug === 'sponsored') items.push([!!m.tdd_sponsor, __('Sponsor named', 'techdosedaily-core'), __('Add the sponsor’s name', 'techdosedaily-core')]);
		if (m.tdd_breaking_until) items.push([isFuture(m.tdd_breaking_until), __('Breaking end time set', 'techdosedaily-core'), __('Breaking end time is in the past', 'techdosedaily-core')]);
		const missing = items.filter((i) => !i[0]).length;
		return h(ed.PluginPrePublishPanel, { title: missing ? sprintf(_n('Story checklist: %d to check', 'Story checklist: %d to check', missing, 'techdosedaily-core'), missing) : __('Story checklist: ready', 'techdosedaily-core'), initialOpen: !!missing },
			h('ul', { className: 'tdd-check' }, items.map((i, k) => h('li', { key: k, className: i[0] ? 'is-ok' : 'is-missing' }, i[0] ? i[1] : i[2])))
		);
	}

	/* ---------- Save the hero image's credit together with the story ---------- */
	let wasSaving = false;
	wp.data.subscribe(() => {
		const e = wp.data.select('core/editor');
		if (!e) return;
		const saving = e.isSavingPost() && !e.isAutosavingPost();
		if (wasSaving && !saving) {
			const id = e.getEditedPostAttribute('featured_media');
			const core = wp.data.select('core');
			if (id && core.hasEditsForEntityRecord('postType', 'attachment', id)) {
				wp.data.dispatch('core').saveEditedEntityRecord('postType', 'attachment', id).catch(() => {
					wp.data.dispatch('core/notices').createErrorNotice(__('The image credit could not be saved. Edit it in the Media Library.', 'techdosedaily-core'), { type: 'snackbar' });
				});
			}
		}
		wasSaving = saving;
	});

	/* ---------- Replace the generic panels these fields supersede ---------- */
	function HideCorePanels() {
		const { removeEditorPanel, toggleEditorPanelOpened } = useDispatch('core/editor');
		useEffect(() => {
			['taxonomy-panel-category', 'taxonomy-panel-tdd_format', 'taxonomy-panel-tdd_topic', 'taxonomy-panel-post_tag', 'post-excerpt'].forEach((n) => removeEditorPanel && removeEditorPanel(n));
			// Open "Story details" the first time; after that the editor remembers each person's choice.
			try {
				if (!window.localStorage.getItem('tdd-story-panel-seen')) {
					if (!wp.data.select('core/editor').isEditorPanelOpened('tdd-story/tdd-story')) toggleEditorPanelOpened('tdd-story/tdd-story');
					window.localStorage.setItem('tdd-story-panel-seen', '1');
				}
			} catch (e) { /* storage unavailable: leave panels as they are */ }
		}, []);
		return null;
	}

	registerPlugin('tdd-story', {
		render: () => h(Fragment, null, h(HideCorePanels), h(StoryPanel), h(LabelsPanel), h(SourcesPanel), h(CorrectionsPanel), h(ImagePanel), h(PlacementPanel), h(Checklist)),
	});
})(window.wp, window.tddStory);
