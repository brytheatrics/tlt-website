---
name: Squarespace duplicate-slug pages are real distinct content
description: Pages with -randomsuffix slugs (e.g. -emp99, -frlk2-z29c7) on TLT's Squarespace are NEVER duplicates; they're distinct content
type: feedback
originSessionId: 5f0ace95-9de5-423f-bfe3-8d250d3cfb60
---
On TLT's Squarespace site, URLs with auto-generated suffixes like `/blog/board/kay-emp99`, `/blog/2017/cyrano-de-burger-shack-a-clubtlt-summer-production-frlk2-z29c7`, `/blog/2018/sexiest-9glhh` are **NEVER duplicates of the base slug**. They are real, distinct content.

**Why:** Chris (Blake's boss) duplicates an existing page as a template, changes the content, but keeps a similar slug. Squarespace's URL collision-handling auto-appends a random suffix. Result: each suffixed URL is a different board member, a different show, a different reading — same template, different content.

**How to apply:** Never deduplicate, merge, or collapse "duplicate-suffix" URLs during migration. Each one becomes its own entry. Examples from this site:
- `/blog/board/kay`, `/blog/board/kay-emp99`, `/blog/board/kay-njsdj`, `/blog/board/kay-rdzag` = four different board members
- `/blog/2018/sexiest`, `/blog/2018/sexiest-9glhh` = two different Off the Shelf staged readings
- `/blog/2017/cyrano-de-burger-shack-...-frlk2-z29c7` chain = different ClubTLT shows

For the URL redirect map, suffixed URLs need their own clean target slugs — not a redirect to the base slug. Treat each as unique.
