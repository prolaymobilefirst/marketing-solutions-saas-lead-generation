# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Electronic Invoicing Reform 2026 — Affiliate Lead Generation Funnel**

A lightweight, mobile-first lead generation funnel in French that guides visitors through a 3-step qualification quiz about the French Electronic Invoicing Reform 2026, then delivers personalised software recommendations and captures leads via a Make.com webhook.

Full project scope: `docs/Electronic_Invoicing_Reform_2026_Project_Scope.pdf`
Milestones: `docs/milestone.md` · Deliverable checklist: `docs/deliverable.md` · Execution plan: `docs/plan.html`

## Tech Stack Constraints

- **Pure HTML5, CSS3, Vanilla JavaScript only** — no frameworks (no React, Vue, Angular, jQuery)
- **No WordPress**, no build tools, no npm dependencies in the output
- Static files deployed to Netlify or Vercel
- **Exception:** two tiny serverless functions (`netlify/functions/` + `api/`) exist solely to gate the PDF lead-magnet download behind a real, server-verified Make.com webhook success — see `README.md` § PDF Download Protection. This was a deliberate, explicit deviation from "no backend": a purely static site cannot prevent direct access to a public file, and the client requirement was that the PDF must never be downloadable except after a genuine successful webhook response. Everything else in the project remains pure static/vanilla JS.
- The browser never calls the Make.com webhook directly anymore — it posts to `/api/submit-lead`, which proxies Make.com server-side (URL configured via `MAKE_WEBHOOK_URL` env var, both Netlify and Vercel)
- **Second exception:** a PHP admin CMS (`admin/`) plus PHP twins of the lead funnel (`api-php/*.php`, `php/`) exist because the project's planned long-term host is **Hostinger** (classic Apache + PHP shared hosting — no Node runtime, no managed serverless functions). The site is currently deployed via git to Vercel and will move to Hostinger later; the PHP code was built for that eventual target, so it's **inert on Vercel/Netlify today** (they don't execute PHP) and only becomes live once hosted on Apache+PHP. See `README.md` § Admin Panel and § Hostinger / PHP Deployment. The Node/Vercel/Netlify implementation is untouched and keeps working in the meantime — this is an additive third runtime target, not a replacement.
- The CMS is intentionally flat-file, not database-backed: editable page copy/SEO/schema live directly in the static HTML files as `data-cms="<key>"`-tagged regions, patched surgically on save (`admin/includes/htmlpatch.php`) rather than rebuilt from a template. Affiliate links and blog post metadata live in `content/*.json`; saving a blog post regenerates its `blog-<slug>.html` and `blog.html`'s article grid/schema from those JSON files.
- All PHP code (`admin/`, `api-php/`, `php/`) targets **PHP 8.3+** — avoid deprecated `${}` string interpolation, dynamic property assignment without `#[AllowDynamicProperties]`, and passing `null` to non-nullable internal string-function parameters.
- The root `.htaccess` sets `Cache-Control` headers via `mod_rewrite` env-var tagging + `mod_headers` (1-year immutable for `css/js/assets`, short cache for `partials/`, must-revalidate for HTML pages, `no-store` for `/admin/`, `/api/`, `/api-php/`) — keep this in parity with `_headers` (Netlify) and `vercel.json` (Vercel) when changing cache policy.

## Local Development

No build step. Serve the project root with any static HTTP server:

```bash
# Python (no install needed)
python3 -m http.server 8080

# Or Node (if npx available)
npx serve .
```

Open `http://localhost:8080` to view. Test on mobile viewport in DevTools (375px width minimum).

## Intended File Structure

```
/
├── index.html          # Page 1 — Landing page
├── quiz.html           # Pages 2-4 — Quiz steps (single file, JS-driven)
├── results.html        # Page 5 — Results + Blog
├── css/
│   └── styles.css      # Single stylesheet with CSS custom properties
├── js/
│   ├── quiz.js         # Quiz engine: step navigation, Session Storage, validation
│   └── webhook.js      # Make.com fetch call, duplicate prevention, form handling
└── assets/
    └── images/         # Optimised WebP images
```

## Architecture Decisions

**Single-page quiz approach**: All three quiz steps (Clientèle, Priorité, Connexion) live in one HTML file (`quiz.html`). JavaScript shows/hides step sections without navigation, updating the progress bar and Session Storage on each transition.

**Session Storage contract**: Each quiz answer is stored under keys `quiz_clientele`, `quiz_volume`, `quiz_connexion` (the storage/wire key names are historical — `quiz_volume` now holds the Step 2 "priorité numéro 1" answer: `simple_gratuit` / `automatiser_compta` / `gestion_crm`, not an invoice-volume bucket). Back-navigation must restore the previously selected card's active state.

**Results logic**: The results page shows exactly 2 recommended software cards, looked up directly from `content/affiliate-links.json` (keyed by the Step 2 `quiz_volume` value, each key holding a fixed 2-entry array) — Step 3's current-software answer is still collected and sent to Make.com but no longer affects the recommendation. 100% Particuliers (B2C) clients skip Steps 2-3 and see a dedicated out-of-scope banner instead of the recommendation grid. This mapping/lookup lives entirely in `js/webhook.js`'s `buildRecommendations()` — no server-side logic.

**Webhook payload** forwarded to Make.com (by `lib/submit-lead.js` / `php/submit-lead.php`, server-side) must include:
```json
{ "first_name": "", "email": "", "clientele": "", "statut": "", "volume": "", "connexion": "", "logiciel": "", "rapport": "", "timestamp": "" }
```
`logiciel` mirrors `connexion` (Step 3's current-software answer: sage/cegid/quickbooks/pennylane/autre) under the label the Make.com sheet column expects. `rapport` is always `"Oui"` — the Make.com call only happens on a genuine lead submission, and the PDF token is only issued after it succeeds, so every row that reaches the sheet corresponds to a report having been sent.

**PDF download gating**: `js/webhook.js` posts the form to `/api/submit-lead`. Only on a genuine Make.com success does that function return a short-lived signed token (`lib/pdf-token.js`); the client then requests `/api/download-pdf?token=...`. Any request to that endpoint without a valid, unexpired token gets a 302 redirect to `/` — including direct/bookmarked access. The PDF itself (`server/assets/sample.pdf`) is never reachable as a static file. Requires `PDF_TOKEN_SECRET` env var on both platforms.

## Design Reference

Mockups are in `designs/` — **pixel-perfect match is a hard acceptance criterion**:
- `designs/desktop/` — 5 desktop screens (pages 1–4 + blog)
- `designs/mobile/` — 5 mobile screens (same pages)

Key visual tokens observed in mockups:
- Brand: `#1b3a4b` (navy), `#2a7f7f` (teal), `#00bfae` (accent), `#0d9488` (CTA button)
- Logo: shield-check icon + "2026" wordmark, top-left
- Top progress bar: 4 labelled steps — Étape 1: Statut → Étape 2: Volume → Étape 3: Connexion → Étape 4: Résultat
- Quiz option layout: 2×2 card grid per step, icon + label per card
- Fixed bottom strip: partner logo banner ("Marketing Solutions SaaS")
- Trust badge top-right: "100% Gratuit & Indépendant"

## Key Requirements to Never Break

- **GDPR checkbox** on the lead capture form is mandatory — the form must not submit without it
- **Duplicate submission prevention** — disable the submit button immediately after a successful webhook POST
- **Session Storage state** must survive back-button navigation within the quiz
- **No full-page reloads** during quiz step transitions
- **PageSpeed target ≥ 90** — keep assets minified and images compressed (WebP)
- All copy is in **French**
