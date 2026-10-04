# TechDoseDaily — rules for anyone (human or AI) working in this repo

Repo root = `D:\TechDoseDaily\06-WordPress`. Theme = `theme/techdosedaily` (presentation). Plugin = `plugins/techdosedaily-core` (data model). See `ARCHITECTURE.md`.

## Implementation, not redesign
- The V1 designs are approved and frozen in `../03-Approved`. The design system is `../01-Design-System`.
- Never change colours, type, spacing, radii, shadows, layout or copy tone. No gradients, card shadows, extra animation, new components or page builders.
- If something cannot be built as designed: preserve the intent, choose the simplest WordPress implementation, document it in `VISUAL-QA.md` as a known difference, and ask.
- Never ship fictional content (people, numbers, sources, issues). Track placeholders in `PRE-LAUNCH-PLACEHOLDERS.md`.

## Boundary: plugin vs theme
- Data, taxonomies, meta, placements, newsletter/forms processing, schema, REST → `techdosedaily-core`.
- Templates, patterns, visual blocks, CSS, JS, layout → theme. The theme reads data only via `tdd_core_*()` functions (guarded).
- Light mode only. Tech is a fallback section, not a default. Breaking is a state, not a story type.

## How styles work
- `theme/techdosedaily/assets/css/*.css` except `layout.css` are GENERATED from `design-source/bundle.css` and `design-source/tokens.json` (`npm run build`). Never edit them by hand.
- `.tdd--m X` mobile rules become `.tdd X` inside `@media (max-width: 767px)`, in place, so cascade and specificity match the approved previews exactly.
- `layout.css` is the only hand-written stylesheet: gutters, in-between widths, drawer, skip link. Tokens only.
- Blocks print the approved markup with the same class names. Don't rename classes.

## Invariants (check every screen)
Containers 1360 / 1280 / 740 · header 68 (mobile 56) · nav 14/600 · homepage feature 50 · article H1 46/32, body 18/1.7 and 17/1.68 · static H1 44/32 · bg #FAFAF8 · primary #2563EB only strong accent · radii 4/6/8/10 · focus ring 2px primary + 2px offset · touch targets ≥ 44px · 20px mobile padding · no horizontal scroll 360–430.

## Workflow
Phase by phase (see `IMPLEMENTATION-MAP.md`). Stop for approval after each phase with screenshots next to the approved PNGs and an updated `VISUAL-QA.md`.
