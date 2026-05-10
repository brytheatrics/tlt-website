---
name: TLT website migration project
description: Active project migrating tacomalittletheatre.com from Squarespace to self-hosted WordPress
type: project
originSessionId: 5f0ace95-9de5-423f-bfe3-8d250d3cfb60
---
Migrating tacomalittletheatre.com from Squarespace ($19/mo Basic, blocks code) to self-hosted WordPress (~$10-15/mo + full code access).

**Why:** Squarespace's Business plan ($35/mo, +$16/mo) is the only way to add custom code on Squarespace. That's not worth it. Blake wants to embed an existing Google Apps Script callboard behind a password and add other custom team tools without paying Squarespace's upcharge. Migration also lets us restructure show pages from `/blog/[slug]` (invisible to Google as events) to `/shows/[slug]` with proper Event schema markup.

**How to apply:** Working directory is `C:\Users\blake\dev\TLT_Website\`. Read `PROJECT.md` there at start of any session for current status, decisions, scrape state, and progress checklist. Domain is at GoDaddy; email is Google Workspace (do not touch DNS for email). Target stack: WordPress.org + Bricks or Elementor on Cloudways or SiteGround.
