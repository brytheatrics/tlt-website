# Maintaining the TLT Website

A practical guide for Chris (and future TLT staff) on how to update the site.

> This is a first draft — Blake will refine after launch. The basic idea: figure out **what kind of thing you're updating**, then follow the matching section below.

---

## Quick reference

| What you want to do | Where to do it |
|---|---|
| Add a new show | Shows → Add New Show |
| Update a show's cast, dates, program PDF, etc. | Shows → (pick the show) → Edit |
| Open auditions for a show | Shows → (pick the show) → set Audition Status & dates |
| Add a Murder Mystery Dinner | Shows → Add New Show → Program Type: Murder Mystery Dinner |
| Add an Off the Shelf reading | Shows → Add New Show → Program Type: Off the Shelf |
| Add a board or staff member | Team → Add New Team Member |
| Update splash page photos | Shows → (current show) → Splash Gallery field |
| Add a new audition opening | Just open the show and set the audition fields — the /auditions/ page auto-updates |
| Promote a new event on the homepage | (Once Promotions are set up) Promotions → Add New |
| Update site address / phone / mission | Appearance → Customize → Contact Information / Mission |
| Update social media links | Appearance → Customize → Social Media |
| Add a press release or news post | Posts → Add New → Category: Press |
| Add a job posting | Posts → Add New → Category: Job Openings |
| Create a fundraising landing page | Pages → Add New → Template: Campaign |
| Create a special-event splash page | Pages → Add New → Template: Designed Page |

---

## The system at a glance

The site is built around a few **post types** (Shows, Team, Posts) and a small library of **page templates**. You don't usually design pages — you fill in fields, and the templates make them look right.

**Most of the homepage and other key pages are auto-generated** from your data:
- The "Now Playing" hero on the homepage picks itself based on the show dates you set
- The season grid pulls in shows automatically
- The "Prior Seasons" page auto-archives shows after they close
- The auditions hub page lists all currently-open auditions from the Show records

You don't have to remember to "take things down" — date-driven content disappears on its own when the date passes.

---

## Shows

### Adding a new show

1. **Shows → Add New Show**
2. **Title** — show title, in CAPS to match the site style (e.g. "THE OUTSIDER")
3. **Program type** — pick the right one:
   - **Mainstage** for regular season productions
   - **Off the Shelf** for staged readings (these get URLs at `/off-the-shelf/<slug>/`)
   - **Murder Mystery Dinner** for dinner shows (often at off-site venues)
   - **Children's / Family** for kid-targeted productions
   - **Special event** for galas, anniversaries, one-offs
4. **Open Date / Close Date** — these drive almost everything: hero, season grid, prior-seasons archive, status badges. **YYYY-MM-DD format.**
5. **Director / Music Director / Choreographer** — fill in what's relevant; leave blank for the rest
6. **Tagline** — short subtitle shown on hero / cards (e.g. "Politics have never been this awkward… or this funny.")
7. **Ticket URL** — link to the Ludus page or wherever tickets are sold
8. **Program PDF URL** — once you have the printed program, upload to Media and paste the URL here
9. **Featured image** — the poster image (upper-right "Set featured image"). This becomes the show card image, hero fallback, etc.
10. **Body** — the show description / synopsis goes in the main editor area

**Click Publish.** The show appears in the season grid, the hero (if it's currently running or up next), and the Prior Seasons archive (once it closes).

### Editing a show

Just open the show from **Shows** in the admin and edit any field. Save.

### Murder Mystery Dinner specifics

In addition to the standard fields:
- **Venue Name** — e.g. "La Quinta Inn"
- **Venue Address** — full address
- **Dinner Menu** — HTML content (use `<h4>` for course headings)

These render as a special section on the show page.

### Off the Shelf specifics

Fill in the same fields as a regular show. The URL will automatically resolve at `/off-the-shelf/<slug>/`, and the show will appear on the `/off-the-shelf` hub page grouped by season.

### Show photo gallery

Once a show has closed, you'll typically have production photos. To add them:
1. Upload the photos to **Media**
2. In the show edit screen, fill in **Production Photo Gallery** as a JSON array:
   ```
   [
     {"url": "/wp-content/uploads/2026/05/photo1.jpg", "alt": "Cast at podium", "caption": "Opening night"},
     {"url": "/wp-content/uploads/2026/05/photo2.jpg", "alt": "Curtain call", "caption": ""}
   ]
   ```
3. Save. The photo gallery appears at the bottom of the show page.

> *(This will get a friendlier UI once ACF is installed — for now it's manual JSON.)*

### Splash page photos

The splash page (when someone visits the bare domain) cycles through production photos of the currently-running show. To enable:
1. Upload splash photos to **Media** (use horizontal, atmospheric images — same style as the existing splash)
2. In the show edit screen, fill in **Splash Gallery** as a JSON array of URLs:
   ```
   ["https://tlt.local/wp-content/uploads/2026/05/splash1.jpg", "https://tlt.local/wp-content/uploads/2026/05/splash2.jpg"]
   ```
3. Save. As soon as the show is "currently running" (today is between Open and Close dates), the splash page activates with these photos.

When the show closes, the splash auto-disables. When the next show with splash photos opens, splash re-enables with their photos. **Zero manual switching.**

---

## Auditions

The **`/auditions/` page is a single hub** that lists every currently-open audition. You don't make new audition pages — you set audition fields on each show, and the hub page lists them automatically.

To open auditions for a show:
1. Open the show in **Shows**
2. Scroll to the **Auditions** section
3. Set:
   - **Audition Status:**
     - *Scheduled* — dates announced, signups not yet open
     - *Open for signups* — actively accepting auditioners
     - *Cast* — show is cast; row remains for a short window then hide
     - *Closed* — hide from the hub
   - **Audition Dates** (human-readable: "September 21–23, 2025")
   - **Audition Location** (defaults to TLT if blank)
   - **Audition Packet PDF URL** — link to the audition packet
   - **Audition Signup URL** — Casting Manager link or similar
   - **Show Logo URL** — small logo for the audition row (optional)
4. Save

The audition row appears on `/auditions/` automatically. When you change Status to *Cast* or *Closed*, the row updates or hides accordingly.

---

## Posts (News, Press, Job Openings)

For news posts, press releases, job postings, etc.:

1. **Posts → Add New**
2. Fill in title and body
3. Pick the appropriate **Category** (right sidebar):
   - **Press** for press releases (shows on `/press/`)
   - **Job Openings** for jobs (shows on `/job-openings/`)
   - **News** for general news (shows on `/news/` if we add a page)
4. Add a **Featured Image** for the thumbnail
5. Publish

The post appears in the relevant listing page automatically.

---

## Promotions (homepage and other page banners)

> *(Coming soon — once the Promotions post type is wired up.)*

Promotions will be a way to add a temporary banner to the homepage, education page, or other pages. Each promo has start/end dates so it auto-disappears when no longer relevant. Until that's set up, banners can be added by editing the relevant page template directly.

---

## Site-wide settings

Things that apply to the whole site (address, phone, mission, social links) live in **Appearance → Customize**:

- **Contact Information** — address, phone, emails, Federal Tax ID
- **Mission / Vision / Land Acknowledgement** — footer text blocks
- **Social Media** — Facebook, Instagram, YouTube, etc.

Update once; reflects everywhere.

**Brand colors and fonts** are not in the Customizer. They live in code (CSS variables) so the site stays visually consistent. If you ever need to change them, it's a 5-minute dev task.

---

## Pages

Most pages on the site use specific templates that do the design work for you. When creating a new page, pick the right template (right sidebar → **Page Attributes → Template**):

- **Default** — for prose pages with optional inline images (mission, history, policies)
- **Auditions Hub** — single use, already assigned to `/auditions/`
- **Ticketing** — for pricing / ticket info pages (uses optional pricing tier fields)
- **Campaign** — Flush-style fundraising pages
- **Post Listing** — pages that auto-list posts of a chosen category (press, jobs)
- **Designed Page** — for image-heavy promo pages (gift cards, class announcements, partner deals). Just fill in image, headline, body, and up to 3 CTA buttons.
- **Contact** — main content + sidebar (hours, address, map, phone, email)
- **Video Archive** — video grid for recorded programs

The right template makes everything look polished. **Don't try to build layouts in the editor — use templates.**

---

## Photos and uploads

- Upload through **Media → Add New** or directly in the page editor
- Use descriptive filenames (`outsider-poster-2026.jpg` not `IMG_4729.jpg`)
- Always fill in **Alt Text** when uploading — this matters for accessibility AND for SEO
- Large images get auto-resized; you don't need to optimize before uploading
- For show photos, aim for at least 1600px wide

---

## URLs and where pages live

- Pages live at `/<slug>/` by default (e.g. `/about/`, `/contact/`)
- To put a page under a parent (e.g. `/education/students-on-stage/`), set the **Parent** dropdown in the page editor
- Shows live at `/shows/<slug>/` (or `/off-the-shelf/<slug>/` for staged readings)
- Posts live at `/news/<slug>/` (or whatever the post permalink structure is set to)

---

## Where to navigate in the admin

- **Dashboard** → home of the admin
- **Posts** → news, press releases, jobs
- **Media** → all uploads
- **Pages** → static pages (about, contact, etc.)
- **Shows** → mainstage productions, OTS, Murder Mystery Dinners
- **Team** → board and staff profiles
- **Contact Forms** (CF7) → manage forms; pasted as shortcodes into pages
- **Appearance → Menus** → primary nav, footer nav structure
- **Appearance → Customize** → site-wide settings
- **Users** → admin users (handle with care — only admins should be admins)

---

## When something breaks

If a page suddenly looks wrong:

1. Check that the page has the right **Template** assigned (Page Attributes → Template)
2. Check the **Featured Image** is set if the template uses one
3. Check the **required meta fields** are filled in (especially dates and program type for shows)
4. Look at the page on the front end while logged in — the admin bar at the top has an "Edit" link

If a date-driven thing isn't auto-updating (e.g. a show didn't move to Prior Seasons):

1. Check the show's **Close Date** is correctly set in YYYY-MM-DD format and is in the past
2. Check the show's **Program Type** is set to "mainstage" (or the correct value)

If you broke something and want to roll back:
1. Most editor screens have a **Revisions** link in the right sidebar — click it to see history and restore an older version

---

## What I (Chris) can't do without a developer

- Change the site's colors or fonts (intentionally locked)
- Build a new page template from scratch (talk to Blake or hire a WP dev)
- Major redesigns or new section types
- Plugin updates that change DB schema (back up first if doing this myself)

For anything in those categories, reach out to Blake or hire a WordPress contractor familiar with custom themes.

---

## A few golden rules

1. **Always fill in dates correctly.** They drive almost everything else.
2. **Pick the right template** when creating a new page. The right template = a beautifully-styled page with no design work.
3. **Don't duplicate pages** the way Squarespace let you. Make a new page with the appropriate template instead.
4. **Add alt text** to every image. Always.
5. **Use the Customizer** for site-wide settings, not edit footer.php directly.
6. **When in doubt, ask** — better to ask Blake than to invent a workaround that breaks consistency.

---

*Last updated: 2026-05-13. This document will evolve as the site does.*
