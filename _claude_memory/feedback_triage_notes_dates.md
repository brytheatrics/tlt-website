---
name: Triage notes — names reliable, dates not
description: Blake's notes in triage/decisions.json identify content correctly but may have wrong years
type: feedback
originSessionId: 5f0ace95-9de5-423f-bfe3-8d250d3cfb60
---
When using Blake's notes from `triage/decisions.json`, trust them for **what the page is about** (show name, person name, page topic) — he read the actual page content for each one — but **do not trust dates/years** in the notes. He flagged that he may have made date mistakes during triage.

**How to apply:** When building Show post type entries or per-season URLs, derive the year/season from the scraped HTML (page body, meta tags, dated content), not from any year mentioned in the user's note. The note's role is to tell you "this is Macbeth" — the page itself tells you "this Macbeth ran in 2017."
