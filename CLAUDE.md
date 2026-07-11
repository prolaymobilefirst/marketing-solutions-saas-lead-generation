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
- **Exception:** two tiny serverless functions (`netlify/functions/` + `api/`) exist solely to proxy the Make.com webhook server-side, so the webhook URL is never exposed to the browser — see `README.md` § PDF Delivery. The lead-magnet PDF itself is never served by the site in any form (no download endpoint exists); it's delivered exclusively by the automated email Make.com sends on a genuine webhook success — this was an explicit client requirement, since a visitor entering a fake email must not be able to obtain the PDF directly from the site. Everything else in the project remains pure static/vanilla JS.
- The browser never calls the Make.com webhook directly anymore — it posts to `/api/submit-lead`, which proxies Make.com server-side (URL configured via `MAKE_WEBHOOK_URL` env var, both Netlify and Vercel)
- **Second exception:** a PHP admin CMS (`admin/`) plus PHP twins of the lead funnel (`api-php/*.php`, `php/`) exist because the project's planned long-term host is **Hostinger** (classic Apache + PHP shared hosting — no Node runtime, no managed serverless functions). The site is currently deployed via git to Vercel and will move to Hostinger later; the PHP code was built for that eventual target, so it's **inert on Vercel/Netlify today** (they don't execute PHP) and only becomes live once hosted on Apache+PHP. See `README.md` § Admin Panel and § Hostinger / PHP Deployment. The Node/Vercel/Netlify implementation is untouched and keeps working in the meantime — this is an additive third runtime target, not a replacement.
- The CMS is intentionally flat-file, not database-backed: editable page copy/SEO/schema live directly in the static HTML files as `data-cms="<key>"`-tagged regions, patched surgically on save (`admin/includes/htmlpatch.php`) rather than rebuilt from a template. Affiliate links and blog post metadata live in `content/*.json`; saving a blog post regenerates its `blog-<slug>.html` and `blog.html`'s article grid/schema from those JSON files.
- All PHP code (`admin/`, `api-php/`, `php/`) targets **PHP 8.3+** — avoid deprecated `${}` string interpolation, dynamic property assignment without `#[AllowDynamicProperties]`, and passing `null` to non-nullable internal string-function parameters.
- The root `.htaccess` sets `Cache-Control` headers via `mod_rewrite` env-var tagging + `mod_headers` (1-year immutable for `css/js/assets`, short cache for `partials/`, must-revalidate for HTML pages, `no-store` for `/admin/`, `/api/`, `/api-php/`) — keep this in parity with `_headers` (Netlify) and `vercel.json` (Vercel) when changing cache policy.
- `css/styles.css`, `js/quiz.js`, `js/webhook.js` and `js/layout.js` are **gitignored, readable working copies** — every HTML page actually loads `css/styles.min.css` / `js/*.min.js` (git-tracked, cache-busted via `?v=`). A change only ships if it's applied to both the readable file *and* its `.min` counterpart; there's no build step to regenerate one from the other automatically, so re-minify by hand (or with `terser` for JS) after editing the source.

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

**Results logic**: The results page shows 2 or 3 recommended software cards, looked up directly from `content/affiliate-links.json` — keyed by the Step 3 `quiz_connexion` answer (current accounting software), normalized through `CONNEXION_BUCKET` in `js/webhook.js`: `autre` and `pennylane` map to their own same-named bucket (3 entries each), while `sage`, `cegid` and `ebp` all share the `sage_ebp` bucket (2 entries — Cegid was never given its own recommendation set, so it rides on the closest analog). Step 2's priority answer (`quiz_volume`) is still collected and sent to Make.com but no longer drives the recommendation. 100% Particuliers (B2C) clients skip Steps 2-3 and see a dedicated out-of-scope banner instead of the recommendation grid. This mapping/lookup lives entirely in `js/webhook.js`'s `buildRecommendations()`/`buildCardHTML()` — no server-side logic. `.articles-grid` gets an `articles-grid--three` modifier class when 3 cards render. The admin panel's "Liens affiliés" section (`admin/sections/affiliates.php`) edits this same file/bucket structure — its `CONNEXION_BUCKETS` constant must stay in sync with `CONNEXION_BUCKET` in `js/webhook.js` (bucket keys and per-bucket slot counts).

**Webhook payload** forwarded to Make.com (by `lib/submit-lead.js` / `php/submit-lead.php`, server-side) must include:
```json
{ "first_name": "", "email": "", "clientele": "", "statut": "", "volume": "", "connexion": "", "logiciel": "", "rapport": "", "timestamp": "" }
```
`logiciel` mirrors `connexion` (Step 3's current-software answer: sage/cegid/ebp/pennylane/autre) under the label the Make.com sheet column expects. `rapport` is always `"Oui"` — the Make.com call only happens on a genuine lead submission, and Make.com only emails the PDF on that same successful call, so every row that reaches the sheet corresponds to a report having been sent.

**PDF delivery**: `js/webhook.js` posts the form to `/api/submit-lead`, which validates the payload and proxies it to Make.com server-side. The response is just `{ ok: true }` — no token, no file, nothing the client could use to fetch a PDF. On success, the page redirects to `merci.html`; **there is no download endpoint anywhere in the codebase** (no `/api/download-pdf`, no equivalent PHP route). The PDF is delivered exclusively by the automated email Make.com sends as part of that same scenario. `server/assets/sample.pdf` and the admin's "PDF (lead magnet)" section (`admin/sections/pdf.php`) are a reference copy only — nothing reads that file to serve it to a visitor. **Do not reintroduce a client-facing PDF download/redirect of any kind** — this was deliberately removed after a security report that a token-gated direct-download let visitors get the PDF with a fake, unverified email address.

**Thank-you page**: `merci.html` is a standalone, `noindex` page (no lead form, no `js/webhook.js`) shown immediately after a successful submission. It shows a static confirmation message and a self-contained inline script counts down 8 seconds before redirecting to `index.html`; it also has a manual "Retour à l'accueil" link. It's listed alongside `quiz`/`results`/`blog` in `vercel.json`'s must-revalidate header rule (Netlify's `_headers` already covers all `*.html` generically, and `.htaccess`'s clean-URL rewrite tags every resolved page automatically).

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
