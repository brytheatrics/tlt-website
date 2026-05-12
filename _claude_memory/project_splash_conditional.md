---
name: Conditional splash page (defer to homepage when no photos)
description: Future enhancement — only show /splash/ when the current show has gallery photos; otherwise route / straight to /home/
type: project
---
The splash page (page-splash.php, post slug `splash`) is the full-screen takeover the site opens to. It cycles production photos from the current show. When there are no good photos to use, the splash is just a dim photo-less screen and the user wants to skip it entirely.

**Future enhancement:** detect whether the current show actually has gallery photos (via `get_attached_media('image', $current->ID)`); if zero photos, redirect / → /home/ in the `template_redirect` hook for the splash page (or change the front-page setting back to /home/ until photos are uploaded).

**Where the gating logic should live:**
- `functions.php` already has a `template_redirect` hook (around line 74) for migration redirects — natural place to add a splash → home fallback
- Or in `page-splash.php` itself: at the top, if `count($photo_urls) === 0`, `wp_safe_redirect( home_url('/home/') )` and exit

User flagged this 2026-05-12 as "not now, just a note for later". The Buy Tickets button bug on the splash was fixed in the same session (now falls back to `https://tlt.ludus.com/` if the current show lacks `show_ticket_url`).

**How to apply:** When the user comes back to wire up photos for upcoming shows (Sotto Voce, Bedroom Farce, etc.), implement the gating so the splash only fires when meaningful imagery exists.
