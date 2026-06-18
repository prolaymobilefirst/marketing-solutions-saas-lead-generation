# Facturation Électronique 2026 — Affiliate Lead Generation Funnel

A lightweight, mobile-first lead generation funnel in French that guides visitors through a 3-step qualification quiz about the French Electronic Invoicing Reform 2026, then delivers personalised software recommendations and captures leads via a Make.com webhook.

## Tech Stack

- Pure HTML5, CSS3, Vanilla JavaScript — no frameworks, no build tools
- Static files — deployable to Netlify or Vercel with zero configuration
- Make.com webhook called via browser `fetch` (AJAX, no page reload)

---

## Local Development

No build step required. Serve the project root with any static HTTP server.

```bash
# Python (built-in, no install needed)
python3 -m http.server 8080

# Node (if npx is available)
npx serve .
```

Open `http://localhost:8080` in your browser.

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

The lead capture form (on `results.html` and `blog.html`) posts to a Make.com webhook.

**Step 1 — Create your Make.com scenario:**
1. Log in to [Make.com](https://www.make.com)
2. Create a new Scenario
3. Add a **Webhooks → Custom webhook** trigger
4. Copy the generated webhook URL

**Step 2 — Paste the URL into `js/webhook.js`:**

```js
// Line 7 in js/webhook.js
const WEBHOOK_URL = 'https://hook.eu1.make.com/YOUR_WEBHOOK_ID_HERE';
```

Replace `REPLACE_WITH_YOUR_WEBHOOK_ID` with your actual webhook path.

**Payload sent on each successful form submission:**

```json
{
  "first_name": "Marie",
  "email": "marie@example.com",
  "statut": "tpe_pme",
  "volume": "50k_200k",
  "connexion": "saas",
  "timestamp": "2026-06-17T14:32:00.000Z"
}
```

**Quiz answer values:**

| Field | Possible values |
|-------|----------------|
| `statut` | `tpe_pme`, `entrepreneur_individuel`, `micro_entrepreneur`, `association` |
| `volume` | `moins_15k`, `15k_50k`, `50k_200k`, `plus_200k` |
| `connexion` | `saas`, `expert_comptable`, `excel`, `automatiser` |

---

## Deployment

### Netlify (recommended)

1. Push the repository to GitHub
2. Log in to [Netlify](https://app.netlify.com) → **Add new site → Import an existing project**
3. Connect your GitHub repo
4. Set **Publish directory** to `.` (project root)
5. Click **Deploy site**

The `netlify.toml` configures clean URLs automatically (e.g. `/quiz` → `quiz.html`).
The `_headers` file sets cache-control headers for all static assets.

### Vercel

1. Push the repository to GitHub
2. Log in to [Vercel](https://vercel.com) → **Add New Project**
3. Import the GitHub repo — no build command needed
4. Click **Deploy**

The `vercel.json` configures `cleanUrls: true`.

---

## Session Storage Keys

The quiz writes answers to `sessionStorage`. The results page reads them to determine recommendations.

| Key | Written by | Read by |
|-----|-----------|---------|
| `quiz_statut` | `quiz.js` (step 1) | `webhook.js` |
| `quiz_volume` | `quiz.js` (step 2) | `webhook.js` |
| `quiz_connexion` | `quiz.js` (step 3) | `webhook.js` |
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
