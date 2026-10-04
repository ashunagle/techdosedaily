/**
 * Editor UI for the story content blocks. Plain JS (no build step).
 * - tdd/key-takeaways: one list (inner core/list).
 * - tdd/why-this-matters: paragraphs (inner core/paragraph).
 * - tdd/data-table: title, unit line, source note and rows typed as "cell | cell | cell" lines;
 *   preview is the server render (same markup as the site).
 * - Static page blocks (Phase 4): policy callout / notice (inner blocks) and list-type blocks
 *   whose rows are typed as "field | field" lines, previewed with the server render.
 */
(function (wp) {
	if (!wp || !wp.blocks) return;
	const { registerBlockType } = wp.blocks;
	const el = wp.element.createElement;
	const { useBlockProps, useInnerBlocksProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, SelectControl, ToggleControl } = wp.components;
	const SSR = wp.serverSideRender;
	const __ = wp.i18n.__;

	const box = (cls, title, inner) => el('section', { className: cls }, el('div', { className: cls + '__title' }, title), inner);

	registerBlockType('tdd/key-takeaways', {
		edit: ({ attributes }) => {
			const props = useBlockProps();
			const inner = useInnerBlocksProps({}, { allowedBlocks: ['core/list'], template: [['core/list']], templateLock: 'all' });
			return el('div', props, box('tdd-takeaways', attributes.title, el('div', inner)));
		},
		save: () => el(wp.blockEditor.InnerBlocks.Content),
	});

	registerBlockType('tdd/why-this-matters', {
		edit: ({ attributes }) => {
			const props = useBlockProps();
			const inner = useInnerBlocksProps({}, { allowedBlocks: ['core/paragraph'], template: [['core/paragraph']] });
			return el('div', props, box('tdd-why', attributes.title, el('div', inner)));
		},
		save: () => el(wp.blockEditor.InnerBlocks.Content),
	});

	const toLines = (head, rows) => [head].concat(rows).filter((r) => r && r.length).map((r) => r.join(' | ')).join('\n');
	const fromLines = (text) => text.split('\n').map((l) => l.split('|').map((c) => c.trim())).filter((r) => r.some((c) => c));

	registerBlockType('tdd/data-table', {
		edit: ({ attributes, setAttributes }) => {
			const props = useBlockProps();
			const set = (k) => (v) => setAttributes({ [k]: v });
			return el('div', props,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Table', 'techdosedaily') },
						el(SelectControl, { label: __('Style', 'techdosedaily'), value: attributes.variant, options: [{ label: __('Data (numbers)', 'techdosedaily'), value: 'data' }, { label: __('Policy (text columns)', 'techdosedaily'), value: 'policy' }], onChange: set('variant') }),
						el(TextControl, { label: __('Title', 'techdosedaily'), value: attributes.title, onChange: set('title') }),
						el(TextControl, { label: __('Unit line', 'techdosedaily'), help: __('e.g. “Share of 500 tasks, vendor-reported”', 'techdosedaily'), value: attributes.subtitle, onChange: set('subtitle') }),
						el(TextareaControl, {
							label: __('Rows (first line = column headings)', 'techdosedaily'),
							help: __('One row per line, cells separated by |', 'techdosedaily'),
							rows: 8,
							value: toLines(attributes.head, attributes.rows),
							onChange: (v) => { const r = fromLines(v); setAttributes({ head: r[0] || [], rows: r.slice(1) }); },
						}),
						el(TextControl, { label: __('Short labels for phones', 'techdosedaily'), help: __('Optional, | separated, same order as the headings', 'techdosedaily'), value: (attributes.short || []).join(' | '), onChange: (v) => setAttributes({ short: v.split('|').map((c) => c.trim()) }) }),
						el(TextControl, { type: 'number', label: __('Highlighted row (0 = none)', 'techdosedaily'), value: attributes.focus, onChange: (v) => setAttributes({ focus: parseInt(v, 10) || 0 }) }),
						el(TextControl, { label: __('Source note', 'techdosedaily'), value: attributes.note, onChange: set('note') })
					)
				),
				attributes.rows.length
					? el(SSR, { block: 'tdd/data-table', attributes })
					: el('p', { className: 'tdd-table__sub' }, __('Data table: add rows in the block settings.', 'techdosedaily'))
			);
		},
		save: () => null,
	});
	/* ---------- Static pages (Phase 4) ---------- */

	registerBlockType('tdd/policy-callout', {
		edit: ({ attributes, setAttributes }) => {
			const props = useBlockProps();
			const inner = useInnerBlocksProps({}, { allowedBlocks: ['core/paragraph', 'core/list'], template: [['core/list']] });
			return el('div', props,
				el(InspectorControls, null, el(PanelBody, { title: __('Callout', 'techdosedaily') },
					el(TextControl, { label: __('Title', 'techdosedaily'), value: attributes.title, onChange: (v) => setAttributes({ title: v }) }),
					el(ToggleControl, { label: __('Neutral (disclosure) style', 'techdosedaily'), checked: attributes.neutral, onChange: (v) => setAttributes({ neutral: v }) }))),
				el('div', { className: 'tdd-pcallout' + (attributes.neutral ? ' tdd-pcallout--neutral' : '') }, el('div', { className: 'tdd-pcallout__t' }, attributes.title), el('div', inner)));
		},
		save: () => el(wp.blockEditor.InnerBlocks.Content),
	});

	registerBlockType('tdd/policy-notice', {
		edit: ({ attributes, setAttributes }) => {
			const props = useBlockProps();
			const inner = useInnerBlocksProps({}, { allowedBlocks: ['core/paragraph'], template: [['core/paragraph']], templateLock: 'all' });
			return el('div', props,
				el(InspectorControls, null, el(PanelBody, { title: __('Notice', 'techdosedaily') },
					el(ToggleControl, { label: __('“What changed” style', 'techdosedaily'), checked: attributes.change, onChange: (v) => setAttributes({ change: v }) }))),
				el('div', { className: 'tdd-pnotice' + (attributes.change ? ' tdd-pnotice--change' : '') },
					el(wp.blockEditor.RichText, { tagName: 'b', value: attributes.title, allowedFormats: [], placeholder: __('Notice title', 'techdosedaily'), onChange: (v) => setAttributes({ title: v }) }),
					el('div', inner)));
		},
		save: () => el(wp.blockEditor.InnerBlocks.Content),
	});

	// Rows typed as lines: "a | b | c" → [{k1:a,k2:b,k3:c}].
	const rowsToText = (rows, keys) => (rows || []).map((r) => keys.map((k) => (r && r[k]) || '').join(' | ')).join('\n');
	const textToRows = (text, keys) => text.split('\n').map((l) => {
		const c = l.split('|').map((x) => x.trim());
		const o = {};
		keys.forEach((k, i) => { o[k] = i === keys.length - 1 ? c.slice(i).join(' | ') : (c[i] || ''); });
		return o;
	}).filter((o) => o[keys[0]]);

	const listBlock = (name, keys, help, extra) => registerBlockType(name, {
		edit: ({ attributes, setAttributes }) => {
			const props = useBlockProps();
			return el('div', props,
				el(InspectorControls, null, el(PanelBody, { title: __('Content', 'techdosedaily') },
					keys && attributes.title !== undefined ? el(TextControl, { label: __('Heading', 'techdosedaily'), value: attributes.title, onChange: (v) => setAttributes({ title: v }) }) : null,
					keys ? el(TextareaControl, { label: help, help: __('One item per line, fields separated by |', 'techdosedaily'), rows: 8, value: rowsToText(attributes.rows, keys), onChange: (v) => setAttributes({ rows: textToRows(v, keys) }) }) : null,
					extra ? extra(attributes, setAttributes) : null)),
				el(SSR, { block: name, attributes, EmptyResponsePlaceholder: () => el('p', { className: 'tdd-table__sub' }, __('Nothing to show yet (hidden on the site until it has content).', 'techdosedaily')) }));
		},
		save: () => null,
	});

	listBlock('tdd/deflist', ['term', 'text'], __('Term | definition', 'techdosedaily'));
	listBlock('tdd/principles', ['title', 'text'], __('Principle | one sentence', 'techdosedaily'));
	listBlock('tdd/funding', ['name', 'status', 'text'], __('Source | Planned or Active | description', 'techdosedaily'));
	listBlock('tdd/nl-benefits', ['title', 'text'], __('Benefit | one sentence', 'techdosedaily'));
	listBlock('tdd/nl-promise', ['text'], __('Promise', 'techdosedaily'));
	listBlock('tdd/faq', ['q', 'a'], __('Question | answer', 'techdosedaily'));
	listBlock('tdd/team', null, '', () => el('p', null, __('People appear here when “Show on About” is turned on in their user profile.', 'techdosedaily')));
	listBlock('tdd/coverage-list', null, '', (a, set) => el(TextControl, { label: __('Leave out sections (slugs, comma separated)', 'techdosedaily'), value: (a.exclude || []).join(', '), onChange: (v) => set({ exclude: v.split(',').map((x) => x.trim()).filter(Boolean) }) }));
	listBlock('tdd/contact-routes', null, '', (a, set) => el('div', null,
		el(SelectControl, { label: __('Style', 'techdosedaily'), value: a.variant, options: [{ label: __('Compact (links to Contact)', 'techdosedaily'), value: 'compact' }, { label: __('Full', 'techdosedaily'), value: 'full' }], onChange: (v) => set({ variant: v }) }),
		el(TextControl, { label: __('Routes (keys, comma separated)', 'techdosedaily'), help: 'editorial, correction, tip, partnership, general', value: (a.only || []).join(', '), onChange: (v) => set({ only: v.split(',').map((x) => x.trim()).filter(Boolean) }) })));
	listBlock('tdd/nl-sample', null, '', (a, set) => el('div', null,
		el(TextareaControl, { label: __('How an issue is built', 'techdosedaily'), value: a.how, onChange: (v) => set({ how: v }) }),
		el(TextareaControl, { label: __('Delivery: label | text', 'techdosedaily'), rows: 4, value: rowsToText(a.delivery, ['title', 'text']), onChange: (v) => set({ delivery: textToRows(v, ['title', 'text']) }) }),
		el('p', null, __('The preview uses today’s Daily Tech Brief stories (at least three).', 'techdosedaily'))));
	listBlock('tdd/contact-cta', null, '', (a, set) => el('div', null,
		['title', 'text', 'primaryLabel', 'primaryUrl', 'secondaryLabel', 'secondaryUrl'].map((k) => el(TextControl, { key: k, label: k, value: a[k], help: /Url$/.test(k) ? __('URL, contact:<topic> or page:<path>', 'techdosedaily') : undefined, onChange: (v) => set({ [k]: v }) }))));
})(window.wp);
