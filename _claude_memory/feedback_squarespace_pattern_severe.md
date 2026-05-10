---
name: TLT Squarespace URL mangling is severe and systemic
description: Squarespace duplicate-slug suffixes have completely scrambled show URLs over time; user notes are the canonical decoder
type: feedback
originSessionId: 5f0ace95-9de5-423f-bfe3-8d250d3cfb60
---
The Squarespace duplicate-slug problem at TLT is much worse than typical sites. Chris's workflow over years was: duplicate an existing show page → change content → publish. Squarespace appended a random suffix every single time. Result: **URL slug bears no relationship to actual content.**

Concrete example: `/blog/2016/the-underpants-5ypj7-em9m8-l4mjh-y6c62-hkpgb-86s8n` is the **Macbeth** production page (NOT The Underpants).

Same pattern in `/blog/20212022/clue-*`: clue, terms, wizard of oz, chorus line, happiest song plays last, luck of irish, silent sky — all hidden behind `clue-*` slugs.

And in `/blog/20192020/holmes-*`: holmes, twas the night before christmas. And `/blog/20192020/chorusline-*`: chorus line, terms of endearment (cancelled COVID), manchurian candidate (cancelled COVID).

Even staff pages: `/blog/2015/office-manager-lyb47` is the current Development Director, `/blog/2015/office-manager-373ag-9pnef-aclg5` is the Lead Carpenter, etc. — each "office-manager-*" suffix is a different role.

**How to apply during migration:**
- The `decisions.json` notes from Blake's triage are the ONLY canonical mapping of URL → real content. Use them when building the URL redirect map and Show post type slugs.
- Never trust the slug to match the content. Always read the page title, body, or the user's note.
- For the new WP site, build clean slugs from page TITLES (e.g. "macbeth" from H1), never from URL slugs.
- Consider this the strongest single argument FOR the migration — Squarespace makes this category of bug inevitable.
