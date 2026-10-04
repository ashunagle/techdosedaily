# Architecture

```
WordPress 7.1 · Hostinger (LiteSpeed)
│
├── plugins/
│   └── techdosedaily-core/        ← publication data model (theme-independent)
│       ├── sections               top-level section URLs, primary section, reserved slugs
│       ├── taxonomies             tdd_format (story type), tdd_topic
│       ├── meta                   post / attachment / user fields
│       ├── editorial              breaking state, updates, corrections, sources, reading time
│       ├── placements             editorial placement table + REST + WP-CLI
│       ├── popularity             cookieless view counts (Most Read)
│       ├── newsletter             Provider interface → MailPoet adapter; /tdd/v1/subscribe
│       ├── security               token, honeypot, throttle, spam hook
│       ├── pages                  static page fields (header, last reviewed, related documents)
│       ├── contact                contact routes, inboxes, /tdd/v1/contact + no-JS PRG
│       ├── seo                    description fallback, noindex rules, sitemap exclusions (via Yoast/core hooks)
│       └── schema                 single JSON-LD owner + Core graph (see SEO-SCHEMA.md)
│
└── themes/
    └── techdosedaily/             ← presentation only
        ├── templates, parts, patterns
        ├── blocks (server-rendered; print approved markup)
        ├── assets/css (generated from the design system + layout.css)
        └── assets/js (header/drawer, newsletter states, contact form, pager, view beacon)
```

## Rules

- The theme reads data only through `tdd_core_*` functions, always guarded with `function_exists`. It never touches `tdd_*` meta keys or Core's tables directly.
- Core never prints presentation markup. It returns data, WP_Errors and REST responses.
- Each concern has exactly one owner. See the ownership table in `plugins/techdosedaily-core/README.md` (Yoast vs Core vs theme).
- **Light mode only in V1.** Tokens keep their dark values in `tokens.json`, but no dark CSS is generated or shipped.

## URLs

| Thing | URL |
|---|---|
| Section | `/ai/`, `/ai/page/2/`, `/ai/feed/` (`/category/ai/…` 301s here) |
| Story | `/<section>/<slug>/` — section frozen at first publication; other spellings 301 |
| Topic | `/topic/<slug>/` |
| Story type | `/story-type/<slug>/` |
| Author | `/author/<slug>/` |
| Latest | `/latest/` (Posts page) |
| Static pages | `/editorial-standards/`, `/about/`, `/contact/`, `/newsletter/` … (Privacy = the WordPress privacy page setting) |
| Search | `/?s=<term>&section[]=ai&format[]=analysis&date=week&author[]=<nicename>&sort=newest` (noindex) |

Section slugs are reserved: a top-level Page can't use them.

## Sections policy

Every story has exactly **one primary section**. Use **Tech** only for broad technology stories when no specialist section fits. Secondary discovery comes from **Topics**, not from extra sections. Sections have no child categories.
