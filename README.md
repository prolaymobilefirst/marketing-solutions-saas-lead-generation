# Facturation Électronique 2026 — Affiliate Lead Generation Funnel

A lightweight, mobile-first lead generation funnel in French that guides visitors through a 3-step qualification quiz about the French Electronic Invoicing Reform 2026, then delivers personalised software recommendations and captures leads via a Make.com webhook.

## Tech Stack

- Pure HTML5, CSS3, Vanilla JavaScript — no frameworks, no build tools
- Static files — deployable to Netlify or Vercel
- Two tiny serverless functions (`netlify/functions/` + `api/`) proxy the Make.com webhook server-side — see [PDF Delivery](#pdf-delivery)

---

## Local Development

No build step required.

**If you only need to work on pages/styles (no lead form, no PDF download):**
serve the project root with any static HTTP server.

```bash
# Python (built-in, no install needed)
python3 -m http.server 8080

# Node (if npx is available)
npx serve .
```

**If you need to test the lead form:** a plain static server returns 404 on
`/api/submit-lead` — it only exists once Netlify/Vercel run their function
runtime, which needs an account login even for local `netlify dev`/`vercel
dev`. Use the included zero-dependency dev server instead, which emulates
`/api/*` by calling the same `api/*.js` handlers directly:

```bash
MAKE_WEBHOOK_URL=https://webhook.site/your-test-id node dev-server.js 8080
```

`MAKE_WEBHOOK_URL` is optional but recommended for local testing — without
it, `submit-lead` calls the real production webhook hardcoded in
`lib/submit-lead.js`. Point it at a [webhook.site](https://webhook.site) URL
(or similar) to inspect submissions without touching production.

Open `http://localhost:8080` in your browser either way.

> **Note:** The partials system (`js/layout.js`) uses `fetch()` to inject shared header/footer HTML. This requires a real HTTP server — opening `index.html` directly as a `file://` URL will not work.

**Test on mobile:** Open DevTools → Toggle device toolbar → set width to 375px minimum.

---

## File Structure

```
/
├── index.html          # Page 1 — Landing page / hero
├── quiz.html           # Pages 2–4 — Quiz steps (JS-driven, single file)
├── results.html        # Page 5 — Personalised results + lead capture form
├── blog.html           # Blog — 3 SEO articles + lead capture CTA
├── dev-server.js       # Local-only: static files + /api/* emulation (see Local Development)
│
├── css/
│   └── styles.css      # Single stylesheet with CSS custom properties
│
├── js/
│   ├── layout.js       # Injects shared partials (header, footer, partner banner)
│   ├── quiz.js         # Quiz engine: step navigation, Session Storage, validation
│   └── webhook.js      # Recommendation engine + Make.com webhook + form handling
│
├── partials/
│   ├── header.html     # Shared site header (logo + trust badge)
│   ├── footer.html     # Shared footer (copyright, blog link, legal links)
│   └── partner-banner.html  # Shared partner logo strip
│
├── assets/
│   └── images/         # PNG illustrations (convert to WebP for production)
│
├── server/
│   └── assets/
│       └── sample.pdf  # The lead-magnet PDF. Kept for reference only —
│                       # no page or API serves this file; delivery is by
│                       # Make.com email only (see "PDF Delivery" below).
│
├── lib/
│   └── submit-lead.js  # Validates the form payload, proxies Make.com
│
├── netlify/functions/
│   └── submit-lead.js  # POST /api/submit-lead  (Netlify)
│
├── api/
│   └── submit-lead.js   # POST /api/submit-lead  (Vercel)
│
├── api-php/             # PHP twin of api/ — NOT named api/ on purpose: Vercel's
│   │                    # build scans api/ and rejects a .js/.php pair sharing a
│   │                    # basename as a "conflicting path". .htaccess rewrites
│   │                    # /api/submit-lead here on Apache.
│   └── submit-lead.php  # POST /api/submit-lead  (Hostinger/Apache+PHP)
│
├── php/                # Shared PHP libs for the lead funnel (PHP twin of lib/*.js)
│   ├── config.php        # env var (or config.local.php) reader
│   └── submit-lead.php   # payload validation + Make.com proxy (cURL)
│
├── admin/               # Flat-file CMS admin panel (PHP — see "Admin Panel" below)
│   ├── index.php          # login / first-run bootstrap
│   ├── dashboard.php      # shell + sidebar
│   ├── sections/          # one file per admin section (pages, seo, media, pdf, affiliates, blog)
│   └── includes/          # auth, CSRF, flat-file I/O, the [data-cms] patch engine
│
├── content/             # Flat-file data store the admin panel reads/writes
│   ├── affiliate-links.json  # public — fetched by js/webhook.js on results.html
│   └── blog/*.json           # admin-only — source of truth for each blog post
│
├── templates/
│   └── blog-post.template.html  # skeleton used to generate new blog-<slug>.html files
│
├── docs/               # Project documentation
│   ├── milestone.md    # Live status tracker
│   ├── deliverable.md  # Client deliverable checklist
│   └── plan.html       # Execution plan
│
├── designs/            # Design mockups (pixel-perfect reference)
│   ├── desktop/        # 5 desktop screens
│   └── mobile/         # 5 mobile screens
│
├── _headers            # Netlify cache-control headers
├── netlify.toml        # Netlify clean-URL redirect config
└── vercel.json         # Vercel clean-URL config
```

---

## Webhook Configuration

The lead capture form (on `results.html` and `blog.html`) no longer calls Make.com
directly from the browser — it posts to `/api/submit-lead`, a serverless function
that proxies Make.com server-side, keeping the webhook URL off the client. See
[PDF Delivery](#pdf-delivery) for how the PDF itself reaches the visitor.

**Step 1 — Create your Make.com scenario:**
1. Log in to [Make.com](https://www.make.com)
2. Create a new Scenario
3. Add a **Webhooks → Custom webhook** trigger
4. Copy the generated webhook URL

**Step 2 — Set it as an environment variable** (Netlify: Site settings →
Environment variables; Vercel: Project settings → Environment Variables):

```
MAKE_WEBHOOK_URL=https://hook.eu1.make.com/YOUR_WEBHOOK_ID_HERE
```

If unset, the functions fall back to the URL currently hardcoded in `lib/submit-lead.js`.

**Payload forwarded to Make.com on each successful form submission:**

```json
{
  "first_name": "Marie",
  "email": "marie@example.com",
  "clientele": "mixte",
  "statut": "tpe_pme",
  "volume": "50k_200k",
  "connexion": "saas",
  "timestamp": "2026-06-17T14:32:00.000Z"
}
```

**Quiz answer values:**

| Field | Possible values |
|-------|----------------|
| `clientele` | `b2c`, `b2b`, `mixte` |
| `statut` | `tpe_pme`, `entrepreneur_individuel`, `micro_entrepreneur`, `association` |
| `volume` | `moins_15k`, `15k_50k`, `50k_200k`, `plus_200k` |
| `connexion` | `saas`, `expert_comptable`, `excel`, `automatiser` |

---

## PDF Delivery

The lead-magnet PDF must never be downloadable through the site itself — not
via the lead form, not via a direct/bookmarked URL, not in any way. The site
has **no download endpoint and no route that ever serves the file**:
`POST /api/submit-lead` validates the form payload, calls Make.com
server-side (the webhook URL is never exposed to the browser), and returns
only `{ ok: true }` — no file, no token, nothing the client could use to
fetch a PDF. Delivery happens entirely outside this codebase: Make.com
emails the plan to the visitor as part of the same scenario that receives
the webhook call.

`server/assets/sample.pdf` (and the admin panel's "PDF (lead magnet)"
section, which manages it) exist only so an editor has a reference copy on
hand — that file is never read by any page or API. `netlify.toml` and
`vercel.json` also redirect `/downloads/*` and `/server/*` to `/` as
defense in depth, and `.htaccess` does the same on Apache.

---

## Admin Panel (Flat-File CMS)

A PHP admin panel at `/admin` lets a non-technical editor manage page copy,
images, SEO/schema, the reference copy of the lead-magnet PDF, the affiliate
software catalog, and blog posts — all without a database. **It only runs on a
PHP-capable host (Apache/Hostinger, or `php -S` locally) — Vercel and
Netlify don't execute PHP**, so the admin panel is a no-op while the site
is deployed there. See [Hostinger Deployment](#hostinger--php-deployment)
below.

**First run:** visit `/admin` — if no admin account exists yet, you'll be
prompted to create the one (and only) admin account, no CLI/SSH needed.
Credentials are stored as a bcrypt hash in `admin/data/admin-users.json`
(gitignored — never commit real credentials).

**Architecture — the HTML files themselves are the flat-file store.**
Editable regions on `index.html`, `quiz.html`, `results.html`, `blog.html`,
and the blog post template are marked with `data-cms="<key>"` attributes.
The admin's patch engine (`admin/includes/htmlpatch.php`) scans a page for
these attributes to build its edit form, and on save, surgically rewrites
only the targeted byte range — the rest of the file is untouched, so a
save never reformats or reflows markup you didn't ask to change.

| Section | What it manages |
|---|---|
| Pages | Text/images on `index.html`, `quiz.html`, `results.html`, `blog.html` (`[data-cms]` regions) |
| SEO | `<title>`, meta description, meta keywords, canonical, OG tags, and raw JSON-LD per page, plus a **Global** tab for the Google Tag Manager container ID and Google/Bing site-verification codes |
| Médiathèque | Upload/list/delete `assets/images/*` — real MIME-sniffed, extension-whitelisted, randomly renamed |
| Favicon & Logo | Upload/replace the site favicon (ICO/PNG/SVG, synced to every page) and the header logo (WebP/PNG/JPG/SVG, patched directly into `partials/header.html` since it's a single shared partial) |
| PDF | Replace the reference copy of the lead-magnet PDF (`server/assets/sample.pdf`) — kept for the editor's reference only, never served by the site |
| Liens affiliés | CRUD over `content/affiliate-links.json` — the software catalog rendered on the results page |
| Blog | Create/edit/delete posts (`content/blog/<slug>.json`) — saving regenerates `blog-<slug>.html` from `templates/blog-post.template.html` and refreshes `blog.html`'s article grid + `ld+json` `blogPost` list |

**Site-wide settings (GTM, search-engine verification, favicon)** are a
different mechanism from the per-page `[data-cms]` editor, since the same
value needs to land on every page at once: `content/site-settings.json` is
the source of truth, and saving it (`admin/includes/site_settings.php`)
rewrites the `<!-- GTM_HEAD_START -->…<!-- GTM_HEAD_END -->`,
`<!-- SITE_META_START -->…<!-- SITE_META_END -->`, and
`<!-- GTM_BODY_START -->…<!-- GTM_BODY_END -->` comment-marker blocks
already present in every page's `<head>`/`<body>` — including
`templates/blog-post.template.html`, so new blog posts pick up the current
settings automatically the moment they're published.

**Security notes:** every admin form is CSRF-protected; login attempts are
file-based rate-limited (5 attempts → 5 min lockout); `admin/data/`,
`php/`, and `content/blog/*.json` are blocked from direct web access via
`.htaccess`; uploads are validated by real file content (`finfo`), not by
filename or the client's claimed `Content-Type`, and `assets/images/`
disables PHP execution as defense in depth.

---

## Hostinger / PHP Deployment

The static pages and the admin panel are designed to run on classic
Apache + PHP shared hosting (e.g. Hostinger) with **no database and no
Node runtime**. Requires **PHP 8.3 or later** (the codebase avoids all
patterns deprecated up to and including 8.3 — no `${}` string
interpolation, no dynamic property assignment, no nullable-to-non-nullable
implicit coercions). To deploy there instead of Netlify/Vercel:

1. Upload the whole repository to your hosting's public web root via
   Hostinger's File Manager, Git deploy, or FTP.
2. Set `MAKE_WEBHOOK_URL` (optional — falls back to the URL hardcoded in
   `php/submit-lead.php`). If your plan doesn't expose a real
   environment-variable panel for plain PHP, copy
   `php/config.local.php.example` to `php/config.local.php` (gitignored)
   and fill in the constant there instead.
3. Make sure `mod_rewrite` and `mod_headers` are enabled — `.htaccess` at
   the project root already routes `/api/submit-lead` →
   `api-php/submit-lead.php` (Apache only; Netlify/Vercel keep using their
   own Node functions in `api/`, so nothing else changes if you stay on
   those platforms), and sets the same Cache-Control policy as the
   `_headers` (Netlify) / `vercel.json` (Vercel) configs: 1-year immutable
   for `css/`, `js/`, `assets/`, a 5-minute cache for `partials/`,
   must-revalidate for HTML pages, and `no-store` for `/admin/`, `/api/`,
   and `/api-php/`.
4. Visit `/admin` to create the admin account (see above).

Both the lead funnel (`api-php/*.php`) and the CMS (`admin/`) are PHP twins of
the existing Node implementation, with the same request/response
contracts — `js/webhook.js` doesn't need to know which backend it's
talking to.

---

## Deployment

### Netlify (recommended)

1. Push the repository to GitHub
2. Log in to [Netlify](https://app.netlify.com) → **Add new site → Import an existing project**
3. Connect your GitHub repo
4. Set **Publish directory** to `.` (project root)
5. Optionally add the **`MAKE_WEBHOOK_URL`** environment variable in Site settings
6. Click **Deploy site**

The `netlify.toml` configures clean URLs automatically (e.g. `/quiz` → `quiz.html`),
bundles `netlify/functions/`, and proxies `/api/*` to them.
The `_headers` file sets cache-control headers for all static assets.

### Vercel

1. Push the repository to GitHub
2. Log in to [Vercel](https://vercel.com) → **Add New Project**
3. Import the GitHub repo — no build command needed
4. Optionally add the **`MAKE_WEBHOOK_URL`** environment variable in Project settings
5. Click **Deploy**

The `vercel.json` configures `cleanUrls: true`. Files under `api/` are
auto-detected as serverless functions.

---

## Session Storage Keys

The quiz writes answers to `sessionStorage`. The results page reads them to determine recommendations.

| Key | Written by | Read by |
|-----|-----------|---------|
| `quiz_clientele` | `quiz.js` (step 1) | `quiz.js` (B2C short-circuit), `webhook.js` |
| `quiz_statut` | `quiz.js` (step 2) | `webhook.js` |
| `quiz_volume` | `quiz.js` (step 3) | `webhook.js` |
| `quiz_connexion` | `quiz.js` (step 4) | `webhook.js` |
| `quiz_current_step` | `quiz.js` | `quiz.js` (restore on refresh) |

---

## GDPR Compliance

- The lead capture form on `results.html` and `blog.html` includes a mandatory GDPR checkbox (`id="gdpr"`)
- Form submission is blocked client-side if the checkbox is not checked
- The checkbox text references the privacy policy link
- No data is stored client-side beyond `sessionStorage` (cleared when the browser tab closes)

---

## Performance Notes

To reach PageSpeed ≥ 90:

1. **Convert images to WebP:** `cwebp -q 80 input.png -o output.webp` (requires [libwebp](https://developers.google.com/speed/webp/download))
2. **Minify CSS:** `npx csso css/styles.css --output css/styles.min.css`
3. **Minify JS:** `npx terser js/quiz.js -o js/quiz.min.js` (repeat for each JS file)
4. Update HTML `<link>` and `<script>` tags to reference `.min.css` / `.min.js`
5. Critical assets already use `rel="preload"`; below-fold images use `loading="lazy"`
