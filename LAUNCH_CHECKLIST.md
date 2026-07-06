# Launch Checklist

Pre-launch and post-launch tasks for the TLT WordPress site. Roughly ordered by priority — top items are blockers, bottom items are nice-to-haves.

> **Status notation:** `[ ]` = not done · `[x]` = done · `[skip]` = decided not to do

---

## P0 — Blockers (do before pointing domain)

### Email deliverability

The current setup uses PHP's `mail()` from `wordpress@tlt.local`. In production, Gmail/Outlook/iCloud will silently drop or spam-foldering this. **Fix before launch.**

- [ ] Install **WP Mail SMTP** (free plugin)
- [ ] Connect it to one of:
  - Google Workspace (Chris's mailbox) — easiest if TLT already has Workspace
  - Microsoft 365 (same idea)
  - **Postmark / SendGrid / Mailgun** — dedicated transactional service, best deliverability, free tier covers TLT volume
- [ ] In WP Mail SMTP, set "From Email" to a real address on the sending domain (e.g. `noreply@tacomalittletheatre.com` or `boxoffice@…`)
- [ ] Add SPF, DKIM, DMARC records for the sending domain in DNS (the SMTP service will give you exact values)
- [ ] Send a test email from WP Mail SMTP and confirm it lands in Gmail's inbox (not spam)
- [ ] Submit each form once from a real browser and confirm the recipient receives the email

**Where each form's email is configured to go:**

| Form | Recipient |
|---|---|
| Contact (`/contact/`) | `boxoffice@tacomalittletheatre.com` |
| Donation Request (`/donation-request/`) | `info@tacomalittletheatre.com` |
| Volunteer Signup (`/volunteer/`, currently disabled in nav) | `volunteers@tacomalittletheatre.com` |

If any of these addresses don't exist or should change, update them in **Contact → (form) → Mail tab** before launch.

### Form submission storage

CF7 emails-and-forgets. If a recipient loses an email, the submission is gone forever.

- [ ] Install **Flamingo** (free plugin by the CF7 author)
- [ ] Verify it's logging submissions (submit a test form, check Flamingo → Inbound Messages)

### Domain + HTTPS

- [ ] Point `tacomalittletheatre.com` DNS A/AAAA records at production host
- [ ] Confirm HTTPS certificate (Let's Encrypt or host-provided)
- [ ] Update WordPress site URL: **Settings → General** → change both URLs from `http://tlt.local` to `https://tacomalittletheatre.com`
- [ ] Run a search-replace on the database to fix hard-coded `tlt.local` references:
  ```
  wp search-replace 'http://tlt.local' 'https://tacomalittletheatre.com' --skip-columns=guid
  wp search-replace 'tlt.local' 'tacomalittletheatre.com' --skip-columns=guid
  ```
- [ ] **Don't update `guid`** — it's a permanent post ID. Leaving the old guid alone is the WP convention.
- [ ] Visit `/wp-admin/options-permalink.php` once to flush rewrite rules
- [ ] Force HTTPS redirect in `.htaccess` or nginx config

### Redirects from old Squarespace URLs

The old site used URLs like `/blog/donationrequest`, `/blog/2021/tlt-wins-national-award`, etc. Without redirects, anyone bookmarking those gets a 404.

- [ ] Check `wordpress/themes/tlt/redirects.csv` — that's the migration redirect map I built
- [ ] Confirm production has either:
  - Server-level redirects (nginx or .htaccess), or
  - A redirect plugin (e.g. **Redirection**) that reads the CSV

---

## P1 — Strongly recommended (do before announcing launch)

### Backups

- [ ] Set up daily database backups (host-provided, or **UpdraftPlus** plugin)
- [ ] Set up weekly file backups (`wp-content/uploads/` especially — that's all images, programs, posters)
- [ ] Verify a restore actually works (do a dry-run restore to a staging environment)
- [ ] Store backups off-site (not just on the same server)

### Security hardening

- [ ] Force strong passwords on the Chris admin account; consider giving Chris a second non-admin "Editor" account for day-to-day editing
- [ ] Install **Limit Login Attempts Reloaded** (free) to block brute-force logins
- [ ] Optional but recommended: enable **2FA** via Wordfence or similar
- [ ] Disable the WP file editor (no in-admin code editing) — add to `wp-config.php`:
  ```php
  define( 'DISALLOW_FILE_EDIT', true );
  ```
- [ ] Turn off WP debug output in `wp-config.php`:
  ```php
  define( 'WP_DEBUG', false );
  define( 'WP_DEBUG_DISPLAY', false );
  ```
- [ ] Block `xmlrpc.php` at the server level (not used by anything we built)

### Show transitions (layered animated heroes)

The home page hero auto-rotates as the current show closes — when one show's
`show_close_date` passes, the next upcoming show takes over. Each show with a
layered hero (PSD broken into ordered PNG layers under
`/wp-content/uploads/hero-layers/<slug>/`) gets its own animated entrance —
on **both desktop and mobile** when a `mobile/` subfolder also exists with
portrait-oriented versions of the same layers.
**Verify every show in the season works before launch — once we cut over, a
broken hero is the first thing every visitor sees.**

**Critical PSD rule (so the crop doesn't cut off the animation):** each
animated layer's PNG must extend past the canvas in the direction it slides
in from. Person enters from right → layer canvas needs transparent space to
the right. Podium rises from below → layer canvas extends below. If the
layer is exactly canvas-sized, the slide animation reveals the IMG box's
hard edge — see CLAUDE.md → "Hero PSD design spec" for the full contract.

- [ ] For each of the 7 shows in 2026–2027, confirm `hero-layers/<slug>/`
      exists with the numbered desktop PNG layers (`1-bg.png`, `2-…png`, …)
      and a `composite.jpg`
- [ ] For each show, confirm `hero-layers/<slug>/mobile/` exists with the
      same set of numbered portrait PNGs + a `composite.jpg` for the
      mobile static fallback (extraction script:
      `C:/temp/extract_outsider_mobile.py`, copy + rename per show)
- [ ] Test each show's animation on **both** desktop AND a real phone via
      Local Sites' Live Link — slide-in elements should appear without
      revealing hard IMG-box edges. If a slide reveals an edge, the PSD
      needs more bleed in that direction OR the CSS slide distance for
      that layer needs to be reduced
- [ ] Use `tlt_today()` date override (in theme `functions.php`) to
      fast-forward and confirm the hero swaps cleanly the day after each
      show closes — walk all 7 transitions
- [ ] Confirm the splash → home wipe still hands off cleanly on the new
      show (re-test for each one — different posters/backgrounds expose
      timing bugs)
- [ ] Confirm `prefers-reduced-motion: reduce` disables the layer
      animation and shows the static composite instead
- [ ] Check the "Coming Soon" hero mode for the very-next-up show after
      the season ends — it falls back to the next season's first show
      or a recap

### Final content review

- [ ] Walk through every page in **MAINTENANCE.md** → "Quick reference" table and verify it looks right
- [ ] Search for any leftover placeholder text: `BIO PENDING`, `placeholder`, `Lorem ipsum`, `(verify)`, `{{`, `TODO`
- [ ] Confirm contact info matches reality:
  - Phone: `(253) 272-2281`
  - Box office email
  - Address: 210 N "I" Street, Tacoma WA 98403
  - Federal ID: 91-0485763
- [ ] Confirm Customizer fields (Appearance → Customize → Contact Information / Social Media / Mission) are filled with final copy
- [ ] Confirm season is correct: 2026–2027 shows are the upcoming season

### Performance

- [ ] Install a caching plugin: **WP Super Cache** or **W3 Total Cache** (free) — both fine
- [ ] Consider Cloudflare in front of the site (free tier; gives DDoS protection + edge caching)
- [ ] Image optimization at upload time: install **Imagify** or **ShortPixel** for any new uploads (existing images already optimized during migration)

### Analytics / SEO

- [ ] Install Google Analytics 4 (via **Site Kit by Google** plugin, or paste GA snippet into `header.php`)
- [ ] Submit `https://tacomalittletheatre.com/sitemap.xml` to Google Search Console
- [ ] Verify ownership in Search Console + Bing Webmaster Tools
- [ ] Confirm robots.txt allows indexing (it should, by default — check `/robots.txt`)
- [ ] Set up a simple uptime monitor (UptimeRobot is free) — pings the site every 5 min, emails you if it goes down

---

## P2 — Nice to have (post-launch is fine)

### Plugin / WordPress updates

- [ ] Set WordPress core to auto-update for minor versions (default behavior)
- [ ] Plan a quarterly review of plugin updates — don't auto-update plugins in production without testing
- [ ] Subscribe to WP security mailing list (**Wordfence** newsletter or similar)

### Forms

- [ ] Add reCAPTCHA or hCaptcha to the contact form if you start getting spam (CF7 has built-in support; install **reCAPTCHA for Contact Form 7** or use CF7's official module)
- [ ] Set up auto-reply emails to submitters (CF7 → form → Mail tab → "Mail (2)") — useful for donation requests so the submitter has a record

### Accessibility

- [ ] Run an accessibility audit: open the site in Chrome, DevTools → Lighthouse → Accessibility
- [ ] Confirm color contrast on red text against backgrounds (the accent color is dark enough but worth checking)
- [ ] Confirm all images have meaningful `alt` text (some imported Squarespace ones have generic `alt=""` — could improve)

### Other ideas

- [ ] Consider a "site-status" widget in the admin dashboard so Chris can see at a glance: # of forms last 7 days, # of upcoming shows, # of open auditions
- [ ] Newsletter signup integration with Ludus (their `/subscribe.php` URL is already in use across the site)
- [ ] Set up Flamingo to forward submissions to a Slack channel (via Zapier or similar) if real-time alerts are useful

---

## Where things live (reference)

| Thing | Location |
|---|---|
| Custom theme | `wordpress/themes/tlt/` |
| Custom plugin (post types) | `wordpress/plugins/tlt-post-types/` |
| Import / one-off scripts | `wordpress/import/` |
| Migration redirect map | `wordpress/themes/tlt/redirects.csv` |
| Staff-facing maintenance guide | `MAINTENANCE.md` |
| Deployment notes | `wordpress/DEPLOYMENT.md` |
| Project status / migration log | `PROJECT.md` |

---

## After launch

- [ ] Watch the Flamingo inbox for the first few days — confirm forms are flowing
- [ ] Check Google Search Console after a week — verify pages are getting indexed
- [ ] Check uptime monitor — confirm no spurious alerts
- [ ] Take a final database backup once everything is settled, archive it as the "launch baseline"
