# Admin fields and data model (Phase 5)

Every field below is registered in **TechDoseDaily Core** (sanitizer + permission check + REST schema). The theme only reads them. "Shown when" describes the admin UI: fields appear only when they apply.

## Who sees what

| Role | Story panels | Placements | Sections | Site settings | Profile |
|---|---|---|---|---|---|
| Contributor / Author | Story details, Status & labels, Sources, Corrections, Hero image credit, checklist | — | — | — | Own public profile |
| Editor | + Homepage & section placement panel | ✅ | ✅ | — | + About listing and Featured Reporting (own profile; administrators set them for others). No raw HTML/scripts since Phase 8. |
| Administrator | everything | ✅ | ✅ | ✅ | everything |

## Stories (post meta, block-editor sidebar)

| Panel | Field | Key | Shown when / rules |
|---|---|---|---|
| Story details | Section | `tdd_primary_section` (+ syncs the single category) | Always. Exactly one; changing it after publication keeps the URL (note shown). |
| | Story type | `tdd_format` taxonomy | Always. One of News, Analysis, Explainer, Guide, Review, Sponsored. |
| | Sponsor | `tdd_sponsor` | Only for **Sponsored**. Cleared when the type changes. |
| | Deck | `tdd_deck` | Always, with a 160-character guide. Also the meta description. |
| | Short headline | `tdd_short_title` | Optional, 60-character guide (Daily Tech Brief). |
| | Topics | `tdd_topic` taxonomy | Always (token field). |
| | Edited by | `tdd_editor` | Users who can edit others' posts. |
| Status & labels | Breaking | `tdd_breaking_from`, `tdd_breaking_until` | Times appear only when the toggle is on; default length from Site settings; warns when the end is in the past. |
| | Substantive update | `tdd_updated_at`, `tdd_update_note` | Published stories only, behind "Record a substantive update"; note required; can be cancelled. |
| | Vendor-reported | `tdd_vendor_reported` | Toggle. |
| | AI disclosure | `tdd_ai_disclosure` | Text appears only when "Contains AI-generated material" is on. |
| | Severity line | `tdd_severity` | Only for **Cybersecurity**. |
| Sources | Repeatable, ordered rows | `tdd_sources` [{title, url, type, publisher, date, note}] | Collapsed rows, move up/down, warning when renumbered; confidential type shows the approval rule; untitled rows dropped. Links must be http(s). **Confidential:** only the description (title), type and date are published; link, publisher and note stay internal (Phase 8). |
| Corrections | Dated entries | `tdd_corrections` [{time, text}] | Entry field only after publication and only after "Add a correction". Published corrections are read-only — text and date — from first publication on (also after unpublishing); only administrators can change or remove them, on every write path (Phase 8). |
| Hero image credit | Credit, source type, source link, licence | attachment `tdd_credit`, `tdd_source_type`, `tdd_source_url`, `tdd_license_note` | Saved on the image with the story. Link only for external sources; licence only for licensed/official; AI-generated shows the label note. Same fields in the Media Library. |
| Placement (editors) | Current placements + "Place this story…" | placements table | Published/scheduled stories. |
| Pre-publish checklist | Section, deck, sources, editor, image credit, sponsor, breaking end | — | Advisory, never blocks publishing. |
| (automatic) | Reading time | `tdd_reading_time` | Computed on save. |
| (automatic) | URL section | `_tdd_permalink_section` | Frozen at first publication. |

The generic Categories, Story types, Tags and Excerpt panels are removed for stories; these fields replace them. The story list shows a Section column with a Breaking chip.

## Placements (Tech Dose Daily → Placements)

Tabs: **Homepage** (lead, secondary 1–4, AI News Today lead + supporting, Editor's Picks 1–4), **Section pages** (per section: lead, secondary 1–3), **Daily Tech Brief** (1–10). Each slot shows one of:

- **Placed** story with section, Breaking chip and window ("since … · ends … / until replaced · by …"), with *Replace story →*, *Change times*, *Remove* (two-step confirm).
- **Automatic fallback: newest eligible AI story** (or section / homepage wording) and the story it is currently using.
- Scheduled entries (start in the future) and the stories that return when the top one ends.

Replace = search published stories, optional start and end. End before start is refused; drafts cannot be placed; Daily Tech Brief never auto-fills. Storage: `wp_tdd_placements` (post, placement, position, section, start, expiry, author). WP-CLI and REST remain for scripting only.

## Static pages (page meta, "Page details" sidebar)

| Field | Key | Templates |
|---|---|---|
| Kicker | `tdd_kicker` | Policy, Short, About, Contact |
| Headline (H1; empty = page title) | `tdd_page_headline` | all |
| Intro / deck | `tdd_intro` | all |
| Mission statement | `tdd_statement` | About |
| Focus row (max 3) | `tdd_focus` [{title, text}] | About |
| Last substantive change (empty = modified date) | `tdd_last_reviewed` | Policy, Short, Contact |
| Review cadence | `tdd_review_cadence` | Policy, Short |
| Link in "Last updated" line + text | `tdd_version_url`, `tdd_version_label` | Policy, Short (media kit on Advertise) |
| Numbered sections | `tdd_numbered` | Policy, About |
| Summary in "Related" lists | `tdd_summary` | all |
| Related documents | `tdd_related_docs` [{title, url, kind}] | Policy, Short |

## Sections (Tech Dose Daily → Sections; category term meta)

| Field | Key | Notes |
|---|---|---|
| Name, slug, description | core | Description = longer intro on the section page. |
| One-line description | `tdd_short_description` | About coverage list; Sections list column. |
| Topic navigation (max 7, ordered) | `tdd_topic_nav` | Empty = most-used topics in the section. |
| Modules on/off | `tdd_section_hidden` | Analysis & explainers, Practical guides, Follow-a-topic chips, Section desk, Daily Tech Brief link. Modules also hide themselves when empty. |
| Pinned stories | — | Button opens the placement editor on that section. |
| Desk people (max 6, ordered) | `tdd_desk_members` | Real accounts only; desk module hidden until set. |
| Desk statement, "How we cover" link | `tdd_desk_note`, `tdd_desk_url` | |

## People (Users → Profile, "Tech Dose Daily profile")

| Field | Key | Who edits |
|---|---|---|
| Role / title, one-line bio, "How I report", location, experience line | `tdd_title`, `tdd_short_bio`, `tdd_note`, `tdd_location`, `tdd_covering_since` | The person |
| Profile photo (media picker) | `tdd_photo` | The person (with upload rights) or an editor |
| Beats (4–8 topics, ordered) | `tdd_beats` | The person |
| Social profiles (repeatable) | `tdd_social` [{label, url}] | The person |
| Email link on author page | `tdd_public_email` | The person |
| Listed on About + order | `tdd_show_on_about`, `tdd_about_order` | Editors |
| Featured Reporting (max 3, own published stories) | `tdd_featured_posts` | Editors |
| Reporter's editor | `tdd_editor_user` | Administrators |

## Site settings (administrators; options)

| Group | Option | Notes |
|---|---|---|
| Launch readiness | — | Live checklist: Yoast active, structured-data owner, inboxes, sender, privacy page, policy pages, newsletter, secure tips, media kit, About editors, section one-liners, site icon, sample data, search-engine visibility. |
| Contact | `tdd_contact_inboxes` (per route; Corrections = standards desk), `tdd_contact_notes`, `tdd_contact_from`, `tdd_contact_expectations`, `tdd_secure_tip` | Invalid inbox → error, previous address kept. Sample `example.*` addresses are flagged. From = site mailbox; reader is only Reply-To. |
| Partnerships | `tdd_media_kit_url` | Empty = link hidden. |
| Newsletter | `tdd_newsletter_mailpoet_list` | Shows the active provider and whether it is ready. |
| Publishing | `tdd_breaking_hours`, `tdd_core_most_read_windows` | Bounded values. |
| Structured data | `tdd_core_schema_owner` (`yoast` default, `core`) | One JSON-LD graph per page. If Yoast is chosen but not active, the Core graph is printed and a warning shown. See `SEO-SCHEMA.md`. |
