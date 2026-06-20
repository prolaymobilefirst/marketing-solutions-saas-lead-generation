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
│   ├── submit-lead.js  # POST /api/submit-lead  (Vercel)
│   └── download-pdf.js # GET  /api/download-pdf (Vercel)
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
