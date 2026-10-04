# Editorial workflow

How a story gets from draft to the homepage. Field-by-field reference: `ADMIN-FIELDS.md`.

## Section rule

Every story has **exactly one primary section**. Use **Tech** only for broad technology stories when no specialist section (AI, Software, Cybersecurity, Startups, Big Tech, Cloud, Developer, Guides) fits. Do not use Tech as a default. Secondary discovery comes from **Topics** (OpenAI, AI Agents, …), never from extra sections.

## Placements, not flags

Homepage and section curation uses **placements**: homepage lead, homepage secondary 1–4, Editor's Picks 1–4, AI News Today lead and supporting stories, section lead and supporting stories, and Daily Tech Brief items 1–10. Each has an optional start time and expiry. Each slot shows, in order: (1) the most recently started entry that is still valid; (2) otherwise the next valid entry for that slot — so when a newer pick expires or is unpublished the earlier pick comes back; (3) otherwise the newest eligible story. A slot is never left empty (except Daily Tech Brief items, which list only stories editors choose), and a story never appears twice on the same page (the next candidate is used instead). Putting a story in a different position of the same placement moves it. Editors manage them in **Tech Dose Daily → Placements** (or from a story's "Homepage & section placement" panel): *Replace story →* picks a published story with an optional start and end; *Change times* and *Remove* act on the live entry. A slot with nothing placed says **Automatic fallback: newest eligible … story** and shows which story it is using. WP-CLI/REST remain for scripting only.

## Article URLs never change

A story's URL section (`/ai/…`) is fixed the first time it is published. If its primary section changes later, the URL stays the same, the story moves to the new section's page and labels, and any other section spelling (e.g. `/developer/<slug>/`) redirects (301) to the original URL. The slug should also not be edited after publication.

## Most Read

Counted anonymously in hourly buckets (no IPs, cookies or user IDs stored). Not counted: bots/crawlers, previews, logged-in editors/authors/admins, and repeat views from the same reader within 30 minutes. Windows: homepage and article sidebar 24 hours, section pages 7 days, author pages 30 days (option `tdd_core_most_read_windows`). The module is hidden until at least 3 stories have 5+ views in the window.

## Section and author pages

- **Section settings** (Tech Dose Daily → Sections → edit a section): one-line description, longer description, ordered topic navigation, module switches (analysis, guides, topic chips, desk, brief link), pinned stories (opens the placement editor), desk people, desk statement and "How we cover" link. The desk module stays hidden until desk people are set.
- **Author settings** (Users → Profile → Tech Dose Daily profile): people edit their own title, bio, location, beats, photo and social profiles; editors set About listing and Featured Reporting (up to 3 story IDs chosen by editors; otherwise the newest analysis/explainers are shown with an honest subtitle) and the reporter's editor. Only true, author-approved profile fields are ever shown.
- **Severity line** (`tdd_severity`, e.g. "Patch now · Critical") appears in the homepage Cybersecurity block only when set from the vendor/CVE rating.

## Story blocks

Key takeaways (one list), Why this matters (paragraphs) and Data table (title, unit line, rows typed as `cell | cell`, optional short labels for phones, highlighted row, source note) are in the inserter under Tech Dose Daily. Empty blocks print nothing. Superscript source references link to `#src-N` (the Nth source in the story's Sources list). The table of contents is automatic for 7+ minute stories with two or more H2s.

## Static pages

Policies, About, Contact and Newsletter are ordinary pages. Choose the template in the page sidebar: **Pages** (default, the policy layout), **Short page**, **About**, **Contact** or **Newsletter**.

- **Page fields** ("Page details" in the page sidebar; only the fields the chosen template uses): kicker (e.g. "Policy"), headline (when the H1 differs from the short page title used in menus and breadcrumbs), intro, mission statement and focus row (About), **last reviewed date** (set it only for a substantive change; otherwise the page's modified date is shown), review cadence (only if true), version history link + label (Advertise uses it for the media kit), summary line for "Related policies", numbered sections, related documents.
- **Sections and contents:** every H2 starts a section. Pages with four or more H2s get the contents (sticky side list on wide screens, collapsible "In this page" below 1200px); shorter pages use the short layout. Give headings a stable HTML anchor so links like `#corrections` keep working.
- **Blocks** (inserter → Tech Dose Daily): Policy callout ("In short", or neutral "Disclosure"), Policy notice (rule, or "What changed on …"), Definition list, Data table (style "Policy"), Contact CTA (always shown at the end of the page; links can be a URL, `contact:<topic>` or `page:<path>`), and for About: Coverage list, Editorial principles, Our editors, How we're funded, Contact routes. A block with no data prints nothing and its whole section (and contents entry) disappears.
- **Our editors** lists only people with "Show on About" turned on in their profile, ordered by "About order", using their real name, title, one-line bio and photo. Never add placeholder people.
- **Funding:** each source is Planned or Active, exactly as true today.
- **Contact settings** (Tech Dose Daily → Site settings, administrators): `tdd_contact_inboxes` (route → monitored role inbox; falls back to the site admin email), `tdd_contact_notes` (short note per route), `tdd_contact_expectations` (only commitments the desk can keep; the "What to expect" box is hidden when empty), `tdd_secure_tip` (real secure-channel instructions only; until then the page says no secure channel exists), `tdd_media_kit_url`. Contact messages are emailed to the route inbox with the sender as Reply-To; nothing is stored on the site. Limit: 5 messages per 10 minutes per visitor.
- **Newsletter page:** "A look inside" shows today's real Daily Tech Brief stories (3 or more) — keep the brief placements current. Benefits, promise and FAQ are blocks in the page content.
- **Privacy:** the Privacy Policy is the page chosen in Settings → Privacy; links everywhere follow that setting.

## Breaking

Breaking is a temporary state with an end time (default 6 hours), not a story type. Use it only for real breaking news. The label disappears on its own when the time passes.

## Publishing a story, step by step

1. **Write** in the editor. Add Key takeaways / Why this matters / Data table blocks where they help.
2. **Story details** (sidebar): section, story type (sponsor name appears only for Sponsored), deck, optional short headline, topics, "Edited by".
3. **Sources**: add rows in the order they are cited, primary first. Moving a row renumbers the [n] references — the panel warns you.
4. **Hero image**: set the featured image, then fill the credit (and source/licence when they apply) in "Hero image credit". It is saved on the image.
5. **Status & labels**: Breaking (with an end time) only for real breaking news; "vendor-reported" when figures are unverified; AI disclosure only when AI-generated material appears; severity line only for Cybersecurity.
6. **Publish**. The pre-publish checklist lists anything missing (section, deck, sources, editor, image credit, sponsor, breaking end); it advises, it does not block.
7. **Place** (editors): from the story's placement panel or the Placements screen.

After publication:

- **New information** → "Record a substantive update" + one-line note (shown as "Updated …"). Typos and polish are not updates.
- **An error** → Corrections → "Add a correction", say what was wrong and what is right. It is dated and becomes part of the public record; only administrators can change a published correction.
- **Section change** → the URL stays; the story moves to the new section page and label.

## Pages

Choose the template (Pages, Short page, About, Contact, Newsletter), then "Page details". Set "Last substantive change" only when the policy itself changes.
