# Facturation Électronique 2026 — Affiliate Lead Generation Funnel

A lightweight, mobile-first lead generation funnel in French that guides visitors through a 3-step qualification quiz about the French Electronic Invoicing Reform 2026, then delivers personalised software recommendations and captures leads via a Make.com webhook.

## Tech Stack

- Pure HTML5, CSS3, Vanilla JavaScript — no frameworks, no build tools
- Static files — deployable to Netlify or Vercel
- Two tiny serverless functions (`netlify/functions/` + `api/`) proxy the Make.com webhook and gate the PDF download — see [PDF Download Protection](#pdf-download-protection)

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

**If you need to test the lead form or the PDF download gate:** a plain
static server returns 404 on `/api/submit-lead` and `/api/download-pdf` —
those only exist once Netlify/Vercel run their function runtime, which needs
an account login even for local `netlify dev`/`vercel dev`. Use the included
zero-dependency dev server instead, which emulates `/api/*` by calling the
same `api/*.js` handlers directly:

```bash
PDF_TOKEN_SECRET=local-dev-secret MAKE_WEBHOOK_URL=https://webhook.site/your-test-id node dev-server.js 8080
```

`MAKE_WEBHOOK_URL` is optional but recommended for local testing — without
it, `submit-lead` calls the real production webhook hardcoded in
`lib/submit-lead.js`. Point it at a [webhook.site](https://webhook.site) URL
(or similar) to inspect submissions without touching production. If
`PDF_TOKEN_SECRET` is omitted, `dev-server.js` falls back to an insecure
default and logs a warning — fine for local use, never for deployment.

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
│   ├── header.html     # Shared site header (logo + Mon Compte button)
│   ├── footer.html     # Shared footer (copyright, blog link, legal links)
│   └── partner-banner.html  # Shared partner logo strip
│
├── assets/
│   └── images/         # PNG illustrations (convert to WebP for production)
│
├── server/
│   └── assets/
│       └── sample.pdf  # The lead-magnet PDF. NOT publicly served — only
│                       # readable by download-pdf, never at a static URL.
│
├── lib/
│   ├── pdf-token.js    # Signs/verifies the short-lived PDF download token
│   └── submit-lead.js  # Validates the form payload, proxies Make.com
│
├── netlify/functions/
│   ├── submit-lead.js  # POST /api/submit-lead  (Netlify)
│   └── download-pdf.js # GET  /api/download-pdf (Netlify)
│
├── api/
│   ├── submit-lead.js   # POST /api/submit-lead  (Vercel)
│   └── download-pdf.js  # GET  /api/download-pdf (Vercel)
│
├── api-php/             # PHP twins of api/ — NOT named api/ on purpose: Vercel's
│   │                    # build scans api/ and rejects a .js/.php pair sharing a
│   │                    # basename as a "conflicting path". .htaccess rewrites
│   │                    # /api/submit-lead and /api/download-pdf here on Apache.
│   ├── submit-lead.php  # POST /api/submit-lead  (Hostinger/Apache+PHP)
│   └── download-pdf.php # GET  /api/download-pdf (Hostinger/Apache+PHP)
│
├── php/                # Shared PHP libs for the lead funnel (PHP twin of lib/*.js)
│   ├── config.php        # env var (or config.local.php) reader
│   ├── pdf-token.php      # HMAC sign/verify, same scheme as lib/pdf-token.js
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
that proxies Make.com server-side. See [PDF Download Protection](#pdf-download-protection)
for why.

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

## PDF Download Protection

The lead-magnet PDF (`server/assets/sample.pdf`) must never be downloadable
except as the direct result of a successful Make.com webhook call — not by
guessing a URL, not by replaying a request, not by visiting a stale link.
A purely static site can't enforce that (any file under the published
directory is fetchable by anyone who knows the URL), so this flow uses two
small serverless functions instead:

1. **`POST /api/submit-lead`** — validates the form payload, calls Make.com
   server-side (the webhook URL is never exposed to the browser), and only
   on a genuine `2xx` response issues a short-lived signed token
   (`lib/pdf-token.js`, 120s TTL, HMAC-SHA256 — stateless, no database).
2. **`GET /api/download-pdf?token=...`** — verifies the token's signature
   and expiry. Valid → streams the PDF with
   `Content-Disposition: attachment`. Invalid, expired, or missing → **302
   redirect to `/`**, every time, including direct/bookmarked access.

The PDF itself lives in `server/assets/`, which is never part of the public
site — it's bundled into the functions (`included_files` in `netlify.toml`
for Netlify; Vercel includes it in the function's filesystem automatically).
`netlify.toml` and `vercel.json` also redirect `/downloads/*` and
`/server/*` to `/` as defense in depth.

**Required environment variable (both platforms):**

```
PDF_TOKEN_SECRET=<any long random string>
```

Functions throw if this is unset — generate one with `openssl rand -hex 32`.

**Note on the 120s window:** the token is stateless (no server-side
revocation list), so it's technically replayable within that short window
if intercepted. It is never exposed anywhere except in the one
`submit-lead` response immediately consumed by the browser. If you need
true single-use guarantees, swap in a small KV store (Netlify Blobs /
Vercel KV) to mark tokens as spent.

---

## Admin Panel (Flat-File CMS)

A PHP admin panel at `/admin` lets a non-technical editor manage page copy,
images, SEO/schema, the gated lead-magnet PDF, the affiliate software
catalog, and blog posts — all without a database. **It only runs on a
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
| SEO | `<title>`, meta description, canonical, OG tags, and raw JSON-LD per page |
| Médiathèque | Upload/list/delete `assets/images/*` — real MIME-sniffed, extension-whitelisted, randomly renamed |
| PDF | Replace the gated lead-magnet PDF (`server/assets/sample.pdf`) |
| Liens affiliés | CRUD over `content/affiliate-links.json` — the software catalog rendered on the results page |
| Blog | Create/edit/delete posts (`content/blog/<slug>.json`) — saving regenerates `blog-<slug>.html` from `templates/blog-post.template.html` and refreshes `blog.html`'s article grid + `ld+json` `blogPost` list |

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
Node runtime**. To deploy there instead of Netlify/Vercel:

1. Upload the whole repository to your hosting's public web root via
   Hostinger's File Manager, Git deploy, or FTP.
2. Set `PDF_TOKEN_SECRET` (and optionally `MAKE_WEBHOOK_URL`). If your
   plan doesn't expose a real environment-variable panel for plain PHP,
   copy `php/config.local.php.example` to `php/config.local.php`
   (gitignored) and fill in the constants there instead.
3. Make sure `mod_rewrite` is enabled — `.htaccess` at the project root
   already routes `/api/submit-lead` → `api-php/submit-lead.php` and
   `/api/download-pdf` → `api-php/download-pdf.php` (Apache only; Netlify/Vercel
   keep using their own Node functions in `api/`, so nothing else changes if
   you stay on those platforms).
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
5. Add the **`PDF_TOKEN_SECRET`** (and optionally `MAKE_WEBHOOK_URL`) environment variables in Site settings
6. Click **Deploy site**

The `netlify.toml` configures clean URLs automatically (e.g. `/quiz` → `quiz.html`),
bundles `netlify/functions/`, and proxies `/api/*` to them.
The `_headers` file sets cache-control headers for all static assets.

### Vercel

1. Push the repository to GitHub
2. Log in to [Vercel](https://vercel.com) → **Add New Project**
3. Import the GitHub repo — no build command needed
4. Add the **`PDF_TOKEN_SECRET`** (and optionally `MAKE_WEBHOOK_URL`) environment variables in Project settings
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
